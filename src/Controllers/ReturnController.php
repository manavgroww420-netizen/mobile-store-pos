<?php
declare(strict_types=1);

namespace AMH\Controllers;

use AMH\Auth;
use AMH\Authorization;
use AMH\Database;
use AMH\Security;
use AMH\Financial;
use AMH\AuditLogger;
use AMH\View;

/**
 * Sales Returns, Item Restocking & Administrative Void Controller
 *
 * Implements Prompt 8 of 12:
 * 1. Customer Return Processing (restocked vs defective/scrap dispositions).
 * 2. Revenue, COGS, and Profit reversals according to strict accounting rules.
 * 3. Inventory double-entry restoration (movement_type = 'SALE_RETURN').
 * 4. Serialized phone status transitions (RESTOCKED_GOOD -> IN_STOCK, DEFECTIVE_SCRAP -> SCRAPPED).
 * 5. Customer receivable adjustments (offsetting open credit) and cash/UPI payouts (SALE_REFUND).
 * 6. Full Administrative Sale Voiding (void_transactions permission).
 */
class ReturnController
{
    private static function getProductNameCol(): string
    {
        static $hasModelName = null;
        if ($hasModelName === null) {
            try {
                $cols = Database::fetchAll("DESCRIBE products");
                $fields = array_column($cols, 'Field');
                $hasModelName = in_array('model_name', $fields, true) && !in_array('name', $fields, true);
            } catch (\Throwable) {
                try {
                    $cols = Database::fetchAll("PRAGMA table_info(products)");
                    $fields = array_column($cols, 'name');
                    $hasModelName = in_array('model_name', $fields, true) && !in_array('name', $fields, true);
                } catch (\Throwable) {
                    $hasModelName = true;
                }
            }
        }
        return $hasModelName ? 'pr.model_name' : 'pr.name';
    }

    private static function getInvQtyCol(): string
    {
        static $hasQuantity = null;
        if ($hasQuantity === null) {
            try {
                $cols = Database::fetchAll("DESCRIBE inventory_stock");
                $fields = array_column($cols, 'Field');
                $hasQuantity = in_array('quantity', $fields, true) && !in_array('current_quantity', $fields, true);
            } catch (\Throwable) {
                try {
                    $cols = Database::fetchAll("PRAGMA table_info(inventory_stock)");
                    $fields = array_column($cols, 'name');
                    $hasQuantity = in_array('quantity', $fields, true) && !in_array('current_quantity', $fields, true);
                } catch (\Throwable) {
                    $hasQuantity = true;
                }
            }
        }
        return $hasQuantity ? 'quantity' : 'current_quantity';
    }

    private static function updateInvStock(int $productId, int $newQty): void
    {
        static $cols = null;
        if ($cols === null) {
            try {
                $raw = Database::fetchAll("DESCRIBE inventory_stock");
                $cols = array_column($raw, 'Field');
            } catch (\Throwable) {
                try {
                    $raw = Database::fetchAll("PRAGMA table_info(inventory_stock)");
                    $cols = array_column($raw, 'name');
                } catch (\Throwable) {
                    $cols = ['quantity'];
                }
            }
        }

        if (in_array('quantity', $cols, true) && in_array('current_quantity', $cols, true)) {
            Database::execute("
                UPDATE inventory_stock 
                SET current_quantity = :new_qty, quantity = :new_qty, updated_at = NOW() 
                WHERE product_id = :pid
            ", [':new_qty' => $newQty, ':pid' => $productId]);
        } elseif (in_array('quantity', $cols, true)) {
            Database::execute("
                UPDATE inventory_stock 
                SET quantity = :new_qty, updated_at = NOW() 
                WHERE product_id = :pid
            ", [':new_qty' => $newQty, ':pid' => $productId]);
        } else {
            Database::execute("
                UPDATE inventory_stock 
                SET current_quantity = :new_qty, updated_at = NOW() 
                WHERE product_id = :pid
            ", [':new_qty' => $newQty, ':pid' => $productId]);
        }
    }
    /**
     * List all sale returns with pagination, date filters, and financial metrics.
     */
    public function index(): void
    {
        Auth::requireLogin();

        $search   = trim($_GET['q'] ?? '');
        $dateFrom = trim($_GET['date_from'] ?? '');
        $dateTo   = trim($_GET['date_to'] ?? '');

        $page = max(1, (int)($_GET['page'] ?? 1));
        $perPage = 15;
        $offset = ($page - 1) * $perPage;

        $whereClauses = [];
        $params = [];

        if ($dateFrom !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateFrom)) {
            $whereClauses[] = "sr.return_date >= :date_from";
            $params[':date_from'] = $dateFrom;
        }

        if ($dateTo !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateTo)) {
            $whereClauses[] = "sr.return_date <= :date_to";
            $params[':date_to'] = $dateTo;
        }

        if ($search !== '') {
            $whereClauses[] = "(
                sr.return_number LIKE :q1 OR 
                s.sale_number LIKE :q2 OR 
                s.customer_name LIKE :q3 OR 
                s.customer_phone LIKE :q4 OR
                sr.reason LIKE :q5
            )";
            $qWild = '%' . $search . '%';
            $params[':q1'] = $qWild;
            $params[':q2'] = $qWild;
            $params[':q3'] = $qWild;
            $params[':q4'] = $qWild;
            $params[':q5'] = $qWild;
        }

        $whereSql = !empty($whereClauses) ? "WHERE " . implode(" AND ", $whereClauses) : "";

        // Total count
        $countSql = "
            SELECT COUNT(*) 
            FROM sale_returns sr
            JOIN sales s ON s.id = sr.sale_id
            {$whereSql}
        ";
        $totalItems = (int)Database::fetchColumn($countSql, $params);
        $totalPages = max(1, (int)ceil($totalItems / $perPage));

        // Aggregate statistics
        $statsSql = "
            SELECT 
                COUNT(*) AS total_returns_count,
                COALESCE(SUM(sr.total_refund_amount), 0.00) AS total_refunds,
                COALESCE(SUM(sr.total_restocked_cogs), 0.00) AS total_restocked_cogs,
                COALESCE(SUM(sr.total_scrapped_cogs), 0.00) AS total_scrapped_cogs,
                COALESCE(SUM(sr.net_profit_reversed), 0.00) AS total_profit_reversed
            FROM sale_returns sr
            JOIN sales s ON s.id = sr.sale_id
            {$whereSql}
        ";
        $stats = Database::fetchOne($statsSql, $params) ?: [
            'total_returns_count'  => 0,
            'total_refunds'        => '0.00',
            'total_restocked_cogs' => '0.00',
            'total_scrapped_cogs'  => '0.00',
            'total_profit_reversed'=> '0.00',
        ];

        // Fetch paginated returns
        $sql = "
            SELECT 
                sr.*,
                s.sale_number, s.customer_name, s.customer_phone,
                u.full_name AS created_by_name
            FROM sale_returns sr
            JOIN sales s ON s.id = sr.sale_id
            JOIN users u ON u.id = sr.created_by
            {$whereSql}
            ORDER BY sr.return_date DESC, sr.return_time DESC, sr.id DESC
            LIMIT {$perPage} OFFSET {$offset}
        ";
        $returns = Database::fetchAll($sql, $params);

        $canManageSales = Authorization::hasPermission('manage_sales') || Authorization::hasPermission('perform_sales') || Auth::isOwner();
        $canViewCosts   = Authorization::hasPermission('view_purchase_costs') || Auth::isOwner();
        $canViewProfit  = Authorization::hasPermission('view_profit_margins') || Auth::isOwner();

        View::render('returns/index', [
            'pageTitle'      => 'Customer Sales Returns & Reversals',
            'returns'        => $returns,
            'stats'          => $stats,
            'canManageSales' => $canManageSales,
            'canViewCosts'   => $canViewCosts,
            'canViewProfit'  => $canViewProfit,
            'filters'        => [
                'q'         => $search,
                'date_from' => $dateFrom,
                'date_to'   => $dateTo,
            ],
            'pagination'     => [
                'page'       => $page,
                'perPage'    => $perPage,
                'totalItems' => $totalItems,
                'totalPages' => $totalPages,
            ],
        ], 'main');
    }

    /**
     * Show form to initiate a return for a specific counter sale.
     */
    public function create(array $params): void
    {
        $this->requireReturnPermission();

        $saleId = (int)($params['id'] ?? 0);
        $sale = Database::fetchOne("
            SELECT s.*, u.full_name AS cashier_name
            FROM sales s
            JOIN users u ON u.id = s.created_by
            WHERE s.id = :id
        ", [':id' => $saleId]);

        if (!$sale) {
            View::flash('danger', 'Sale record not found.');
            Security::safeRedirect('/sales');
            return;
        }

        if ((int)$sale['is_voided'] === 1) {
            View::flash('danger', 'Cannot process return: This sale transaction was already administratively voided.');
            Security::safeRedirect("/sales/{$saleId}");
            return;
        }

        $prodNameCol = self::getProductNameCol();

        // Fetch sale items with remaining returnable quantity
        $items = Database::fetchAll("
            SELECT 
                si.*,
                {$prodNameCol} AS product_name, pr.brand, pr.sku,
                ph.phone_type, ph.status AS phone_status,
                d.imei_1, d.imei_2,
                COALESCE((
                    SELECT SUM(sri.quantity)
                    FROM sale_return_items sri
                    WHERE sri.sale_item_id = si.id
                ), 0) AS returned_quantity
            FROM sale_items si
            JOIN products pr ON pr.id = si.product_id
            LEFT JOIN phones ph ON ph.id = si.phone_id
            LEFT JOIN devices d ON d.id = ph.device_id
            WHERE si.sale_id = :id
            ORDER BY si.id ASC
        ", [':id' => $saleId]);

        // Compute returnable quantity for each item
        $hasReturnableItems = false;
        foreach ($items as &$item) {
            $item['returnable_quantity'] = max(0, (int)$item['quantity'] - (int)$item['returned_quantity']);
            if ($item['returnable_quantity'] > 0) {
                $hasReturnableItems = true;
            }
        }
        unset($item);

        if (!$hasReturnableItems) {
            View::flash('warning', "All items on sale {$sale['sale_number']} have already been completely returned.");
            Security::safeRedirect("/sales/{$saleId}");
            return;
        }

        // Calculate maximum cash refundable: total paid minus already refunded cash
        $totalPaid = (float)$sale['paid_amount'];
        $alreadyRefunded = (float)Database::fetchColumn("
            SELECT COALESCE(SUM(amount), 0.00)
            FROM payments
            WHERE reference_table = 'sale_returns' 
              AND reference_id IN (SELECT id FROM sale_returns WHERE sale_id = :sid)
              AND transaction_type = 'SALE_REFUND'
        ", [':sid' => $saleId]);
        $maxCashRefundable = max(0.00, $totalPaid - $alreadyRefunded);

        $canViewCosts  = Authorization::hasPermission('view_purchase_costs') || Auth::isOwner();
        $canViewProfit = Authorization::hasPermission('view_profit_margins') || Auth::isOwner();

        View::render('returns/create', [
            'pageTitle'          => "Process Return: {$sale['sale_number']}",
            'sale'               => $sale,
            'items'              => $items,
            'maxCashRefundable'  => $maxCashRefundable,
            'canViewCosts'       => $canViewCosts,
            'canViewProfit'      => $canViewProfit,
        ], 'main');
    }

    /**
     * Process and store a customer return with full financial and inventory reversals.
     */
    public function store(array $params): void
    {
        $this->requireReturnPermission();

        $saleId = (int)($params['id'] ?? 0);

        $csrfToken = $_POST['csrf_token'] ?? null;
        if (!Security::validateCsrfToken($csrfToken)) {
            View::flash('danger', 'Security verification failed (CSRF token missing or expired).');
            Security::safeRedirect("/sales/{$saleId}/return");
            return;
        }

        $returnDate = trim($_POST['return_date'] ?? date('Y-m-d'));
        $returnReason = trim($_POST['reason'] ?? '');
        $refundPaymentMode = strtoupper(trim($_POST['refund_payment_mode'] ?? 'CASH'));

        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $returnDate)) {
            $returnDate = date('Y-m-d');
        }

        if ($returnReason === '') {
            View::flash('danger', 'Please enter a valid reason for the customer return.');
            Security::safeRedirect("/sales/{$saleId}/return");
            return;
        }

        $allowedModes = ['CASH', 'UPI', 'BANK_TRANSFER', 'STORE_CREDIT'];
        if (!in_array($refundPaymentMode, $allowedModes, true)) {
            $refundPaymentMode = 'CASH';
        }

        $returnQtys        = $_POST['return_qty'] ?? [];
        $restockConditions = $_POST['restock_condition'] ?? [];

        $operatorId = Auth::id() ?? 1;

        try {
            $createdReturnId = 0;

            Database::transaction(function() use (
                $saleId, $returnDate, $returnReason, $refundPaymentMode,
                $returnQtys, $restockConditions, $operatorId, &$createdReturnId
            ) {
                // 1. Lock sale row
                $sale = Database::fetchOne("
                    SELECT * 
                    FROM sales 
                    WHERE id = :id 
                    FOR UPDATE
                ", [':id' => $saleId]);

                if (!$sale) {
                    throw new \RuntimeException('Sale record not found.');
                }
                if ((int)$sale['is_voided'] === 1) {
                    throw new \RuntimeException('Cannot return items from an administratively voided sale.');
                }

                $prodNameCol = self::getProductNameCol();
                // 2. Lock and fetch sale items
                $saleItems = Database::fetchAll("
                    SELECT si.*, {$prodNameCol} AS product_name
                    FROM sale_items si
                    JOIN products pr ON pr.id = si.product_id
                    WHERE si.sale_id = :id
                    FOR UPDATE
                ", [':id' => $saleId]);

                $itemsById = [];
                foreach ($saleItems as $si) {
                    $itemsById[(int)$si['id']] = $si;
                }

                // 3. Validate line items selected for return
                $linesToProcess = [];
                $totalRefundAmount = '0.00';
                $totalRestockedCogs = '0.00';
                $totalScrappedCogs = '0.00';
                $totalProfitReversed = '0.00';

                foreach ($returnQtys as $itemIdRaw => $qtyRaw) {
                    $itemId = (int)$itemIdRaw;
                    $qty = (int)$qtyRaw;

                    if ($qty <= 0) {
                        continue;
                    }

                    if (!isset($itemsById[$itemId])) {
                        throw new \RuntimeException("Invalid sale line item #{$itemId}.");
                    }

                    $si = $itemsById[$itemId];

                    // Check already returned quantity
                    $alreadyReturned = (int)Database::fetchColumn("
                        SELECT COALESCE(SUM(quantity), 0)
                        FROM sale_return_items
                        WHERE sale_item_id = :si_id
                    ", [':si_id' => $itemId]);

                    $returnable = (int)$si['quantity'] - $alreadyReturned;
                    if ($qty > $returnable) {
                        throw new \RuntimeException("Return quantity ({$qty}) exceeds available returnable quantity ({$returnable}) for '{$si['product_name']}'.");
                    }

                    // Serialized phone check: only 1 can be returned
                    if ($si['item_type'] === 'SERIALIZED_PHONE' && $qty !== 1) {
                        throw new \RuntimeException("Serialized phone line items must be returned with quantity exactly 1.");
                    }

                    $condition = strtoupper(trim($restockConditions[$itemId] ?? 'RESTOCKED_GOOD'));
                    if (!in_array($condition, ['RESTOCKED_GOOD', 'DEFECTIVE_SCRAP'], true)) {
                        $condition = 'RESTOCKED_GOOD';
                    }

                    // Proportional calculations per unit based on net_sale_price
                    // net_unit_price = net_sale_price / quantity
                    $totalLineNet = (string)$si['net_sale_price'];
                    $totalLineQty = (int)$si['quantity'];
                    $unitNetPrice = (string)round((float)$totalLineNet / $totalLineQty, 2);

                    // Line refund subtotal = unitNetPrice * qty
                    $subtotalRefund = Financial::mul($unitNetPrice, (string)$qty);
                    $totalRefundAmount = Financial::add($totalRefundAmount, $subtotalRefund);

                    $unitCost = (string)$si['unit_cost'];
                    $subtotalCost = Financial::mul($unitCost, (string)$qty);

                    // Item profit reversed = subtotalRefund - subtotalCost
                    $lineProfitReversed = Financial::sub($subtotalRefund, $subtotalCost);
                    $totalProfitReversed = Financial::add($totalProfitReversed, $lineProfitReversed);

                    if ($condition === 'RESTOCKED_GOOD') {
                        $totalRestockedCogs = Financial::add($totalRestockedCogs, $subtotalCost);
                        $cogsReversed = $subtotalCost;
                    } else {
                        // DEFECTIVE_SCRAP: Cost is NOT reversed back into inventory
                        $totalScrappedCogs = Financial::add($totalScrappedCogs, $subtotalCost);
                        $cogsReversed = '0.00';
                    }

                    $linesToProcess[] = [
                        'sale_item'          => $si,
                        'quantity'           => $qty,
                        'refund_unit_price'  => $unitNetPrice,
                        'unit_cost'          => $unitCost,
                        'subtotal_refund'    => $subtotalRefund,
                        'cogs_reversed'      => $cogsReversed,
                        'profit_reversed'    => $lineProfitReversed,
                        'restock_condition'  => $condition,
                    ];
                }

                if (empty($linesToProcess)) {
                    throw new \RuntimeException('No items were selected for return. Please enter a quantity >= 1.');
                }

                // 4. Generate unique return number
                $returnNumber = 'RET-' . date('Ymd', strtotime($returnDate)) . '-' . strtoupper(bin2hex(random_bytes(2)));
                $returnTime = date('H:i:s');

                // 5. Insert header into sale_returns
                Database::execute("
                    INSERT INTO sale_returns (
                        return_number, sale_id, return_date, return_time,
                        total_refund_amount, total_restocked_cogs, total_scrapped_cogs,
                        net_profit_reversed, refund_payment_mode, reason,
                        created_by, created_at
                    ) VALUES (
                        :rnum, :sid, :rdate, :rtime,
                        :tramt, :rcogs, :scogs,
                        :nprev, :mode, :reason,
                        :cby, NOW()
                    )
                ", [
                    ':rnum'   => $returnNumber,
                    ':sid'    => $saleId,
                    ':rdate'  => $returnDate,
                    ':rtime'  => $returnTime,
                    ':tramt'  => $totalRefundAmount,
                    ':rcogs'  => $totalRestockedCogs,
                    ':scogs'  => $totalScrappedCogs,
                    ':nprev'  => $totalProfitReversed,
                    ':mode'   => $refundPaymentMode,
                    ':reason' => $returnReason,
                    ':cby'    => $operatorId,
                ]);

                $returnId = (int)Database::lastInsertId();
                $createdReturnId = $returnId;

                // 6. Insert lines and update inventory/phones
                foreach ($linesToProcess as $line) {
                    $si = $line['sale_item'];

                    Database::execute("
                        INSERT INTO sale_return_items (
                            return_id, sale_item_id, product_id, phone_id,
                            quantity, refund_unit_price, unit_cost, subtotal_refund,
                            cogs_reversed, profit_reversed, restock_condition, created_at
                        ) VALUES (
                            :rid, :si_id, :pid, :phid,
                            :qty, :rprice, :ucost, :srefund,
                            :cogsrev, :profrev, :cond, NOW()
                        )
                    ", [
                        ':rid'     => $returnId,
                        ':si_id'   => $si['id'],
                        ':pid'     => $si['product_id'],
                        ':phid'    => $si['phone_id'],
                        ':qty'     => $line['quantity'],
                        ':rprice'  => $line['refund_unit_price'],
                        ':ucost'   => $line['unit_cost'],
                        ':srefund' => $line['subtotal_refund'],
                        ':cogsrev' => $line['cogs_reversed'],
                        ':profrev' => $line['profit_reversed'],
                        ':cond'    => $line['restock_condition'],
                    ]);

                    if ($si['item_type'] === 'ACCESSORY') {
                        if ($line['restock_condition'] === 'RESTOCKED_GOOD') {
                            // Restore inventory stock
                            $qtyCol = self::getInvQtyCol();
                            $stockRow = Database::fetchOne("
                                SELECT {$qtyCol} 
                                FROM inventory_stock 
                                WHERE product_id = :pid 
                                FOR UPDATE
                            ", [':pid' => $si['product_id']]);

                            $oldQty = (int)($stockRow[$qtyCol] ?? 0);
                            $newQty = $oldQty + $line['quantity'];

                            self::updateInvStock((int)$si['product_id'], $newQty);

                            // Record double-entry stock movement
                            Database::execute("
                                INSERT INTO stock_movements (
                                    product_id, movement_type, quantity_change,
                                    quantity_before, quantity_after, unit_cost,
                                    reference_table, reference_id, notes, created_by, created_at
                                ) VALUES (
                                    :pid, 'SALE_RETURN', :qchange,
                                    :qbefore, :qafter, :ucost,
                                    'sale_returns', :ref_id, :notes, :cby, NOW()
                                )
                            ", [
                                ':pid'     => $si['product_id'],
                                ':qchange' => $line['quantity'],
                                ':qbefore' => $oldQty,
                                ':qafter'  => $newQty,
                                ':ucost'   => $line['unit_cost'],
                                ':ref_id'  => $returnId,
                                ':notes'   => "Restocked return {$returnNumber} from sale {$sale['sale_number']}",
                                ':cby'     => $operatorId,
                            ]);
                        }
                    } elseif ($si['item_type'] === 'SERIALIZED_PHONE') {
                        $phoneId = (int)$si['phone_id'];
                        $phone = Database::fetchOne("
                            SELECT * FROM phones WHERE id = :id FOR UPDATE
                        ", [':id' => $phoneId]);

                        if ($line['restock_condition'] === 'RESTOCKED_GOOD') {
                            // Phone returns to IN_STOCK, clear sale references
                            Database::execute("
                                UPDATE phones
                                SET status = 'IN_STOCK', sale_item_id = NULL, sold_at = NULL, updated_at = NOW()
                                WHERE id = :pid
                            ", [':pid' => $phoneId]);

                            // Re-link physical device to active phone
                            if (!empty($phone['device_id'])) {
                                Database::execute("
                                    UPDATE devices 
                                    SET active_phone_id = :pid, updated_at = NOW() 
                                    WHERE id = :did
                                ", [
                                    ':pid' => $phoneId,
                                    ':did' => $phone['device_id'],
                                ]);
                            }
                        } else {
                            // Phone marked SCRAPPED
                            Database::execute("
                                UPDATE phones
                                SET status = 'SCRAPPED', updated_at = NOW()
                                WHERE id = :pid
                            ", [':pid' => $phoneId]);

                            if (!empty($phone['device_id'])) {
                                Database::execute("
                                    UPDATE devices 
                                    SET active_phone_id = NULL, updated_at = NOW() 
                                    WHERE id = :did
                                ", [':did' => $phone['device_id']]);
                            }
                        }
                    }
                }

                // 7. Settle financial adjustments (Customer Due reduction vs Cash payout)
                $currentDue = (string)$sale['due_amount'];
                $creditOffset = '0.00';
                $cashRefund = '0.00';

                if ((float)$currentDue > 0.00) {
                    // Offset against open credit (Udhar) first
                    if ((float)$totalRefundAmount <= (float)$currentDue) {
                        $creditOffset = $totalRefundAmount;
                        $newDue = Financial::sub($currentDue, $creditOffset);
                        $cashRefund = '0.00';
                    } else {
                        $creditOffset = $currentDue;
                        $cashRefund = Financial::sub($totalRefundAmount, $creditOffset);
                        $newDue = '0.00';
                    }

                    // Update sale due_amount and payment_status
                    $newStatus = ((float)$newDue <= 0.00) ? 'PAID' : 'PARTIAL';
                    Database::execute("
                        UPDATE sales 
                        SET due_amount = :due, payment_status = :status, updated_at = NOW() 
                        WHERE id = :id
                    ", [
                        ':due'    => $newDue,
                        ':status' => $newStatus,
                        ':id'     => $saleId,
                    ]);
                } else {
                    // Sale was fully paid: entire refund is paid out in cash/UPI
                    $cashRefund = $totalRefundAmount;
                }

                // 8. If cash/UPI payout occurs, record SALE_REFUND in payments table
                if ((float)$cashRefund > 0.00 && $refundPaymentMode !== 'STORE_CREDIT') {
                    $paymentNumber = 'REF-' . date('Ymd', strtotime($returnDate)) . '-' . strtoupper(bin2hex(random_bytes(3)));
                    Database::execute("
                        INSERT INTO payments (
                            payment_number, transaction_type, reference_table,
                            reference_id, vendor_id, payment_date, amount,
                            payment_mode, notes, received_by, created_at
                        ) VALUES (
                            :pnum, 'SALE_REFUND', 'sale_returns',
                            :ref_id, NULL, :pdate, :amt,
                            :pmode, :notes, :rcv_by, NOW()
                        )
                    ", [
                        ':pnum'   => $paymentNumber,
                        ':ref_id' => $returnId,
                        ':pdate'  => $returnDate,
                        ':amt'    => $cashRefund,
                        ':pmode'  => $refundPaymentMode,
                        ':notes'  => "Customer payout for return {$returnNumber} (Sale {$sale['sale_number']})",
                        ':rcv_by' => $operatorId,
                    ]);
                }

                // 9. Audit log
                AuditLogger::log(
                    'SALE_RETURN_CREATED',
                    'sale_returns',
                    $returnId,
                    null,
                    [
                        'return_number'        => $returnNumber,
                        'sale_id'              => $saleId,
                        'sale_number'          => $sale['sale_number'],
                        'total_refund_amount'  => $totalRefundAmount,
                        'credit_offset'        => $creditOffset,
                        'cash_payout'          => $cashRefund,
                        'restocked_cogs'       => $totalRestockedCogs,
                        'scrapped_cogs'        => $totalScrappedCogs,
                        'net_profit_reversed'  => $totalProfitReversed,
                        'payment_mode'         => $refundPaymentMode,
                        'reason'               => $returnReason,
                    ],
                    "Processed customer return {$returnNumber} for sale {$sale['sale_number']}"
                );
            });

            View::flash('success', "Customer return processed successfully. Inventory and financial balances adjusted.");
            Security::safeRedirect("/returns/{$createdReturnId}");
        } catch (\Throwable $e) {
            View::flash('danger', 'Return failed: ' . $e->getMessage());
            Security::safeRedirect("/sales/{$saleId}/return");
        }
    }

    /**
     * View detailed return record and line reversals.
     */
    public function show(array $params): void
    {
        Auth::requireLogin();

        $id = (int)($params['id'] ?? 0);
        $return = Database::fetchOne("
            SELECT 
                sr.*,
                s.sale_number, s.sale_date, s.customer_name, s.customer_phone,
                u.full_name AS created_by_name
            FROM sale_returns sr
            JOIN sales s ON s.id = sr.sale_id
            JOIN users u ON u.id = sr.created_by
            WHERE sr.id = :id
        ", [':id' => $id]);

        if (!$return) {
            View::flash('danger', 'Return record not found.');
            Security::safeRedirect('/returns');
            return;
        }

        $prodNameCol = self::getProductNameCol();

        // Fetch returned line items
        $items = Database::fetchAll("
            SELECT 
                sri.*,
                {$prodNameCol} AS product_name, pr.brand, pr.sku,
                ph.phone_type, ph.status AS phone_status,
                d.imei_1, d.imei_2
            FROM sale_return_items sri
            JOIN products pr ON pr.id = sri.product_id
            LEFT JOIN phones ph ON ph.id = sri.phone_id
            LEFT JOIN devices d ON d.id = ph.device_id
            WHERE sri.return_id = :id
            ORDER BY sri.id ASC
        ", [':id' => $id]);

        // Fetch any payment refund records
        $refundPayments = Database::fetchAll("
            SELECT p.*, u.full_name AS processed_by_name
            FROM payments p
            JOIN users u ON u.id = p.received_by
            WHERE p.reference_table = 'sale_returns' 
              AND p.reference_id = :id 
              AND p.transaction_type = 'SALE_REFUND'
            ORDER BY p.id ASC
        ", [':id' => $id]);

        $canViewCosts  = Authorization::hasPermission('view_purchase_costs') || Auth::isOwner();
        $canViewProfit = Authorization::hasPermission('view_profit_margins') || Auth::isOwner();

        View::render('returns/show', [
            'pageTitle'      => "Return Record: {$return['return_number']}",
            'return'         => $return,
            'items'          => $items,
            'refundPayments' => $refundPayments,
            'canViewCosts'   => $canViewCosts,
            'canViewProfit'  => $canViewProfit,
        ], 'main');
    }

    /**
     * Administratively void a sale checkout (Owner / void_transactions permission required).
     */
    public function voidSale(array $params): void
    {
        $this->requireVoidPermission();

        $saleId = (int)($params['id'] ?? 0);

        $csrfToken = $_POST['csrf_token'] ?? null;
        if (!Security::validateCsrfToken($csrfToken)) {
            View::flash('danger', 'Security verification failed (CSRF token missing or expired).');
            Security::safeRedirect("/sales/{$saleId}");
            return;
        }

        $voidReason = trim($_POST['void_reason'] ?? '');
        if ($voidReason === '') {
            View::flash('danger', 'Please provide a valid business reason for voiding this transaction.');
            Security::safeRedirect("/sales/{$saleId}");
            return;
        }

        $operatorId = Auth::id() ?? 1;

        try {
            Database::transaction(function() use ($saleId, $voidReason, $operatorId) {
                // 1. Lock sale row
                $sale = Database::fetchOne("
                    SELECT * 
                    FROM sales 
                    WHERE id = :id 
                    FOR UPDATE
                ", [':id' => $saleId]);

                if (!$sale) {
                    throw new \RuntimeException('Sale record not found.');
                }

                if ((int)$sale['is_voided'] === 1) {
                    throw new \RuntimeException("Sale {$sale['sale_number']} is already voided.");
                }

                // Check if any returns were already processed against this sale
                $returnCount = (int)Database::fetchColumn("
                    SELECT COUNT(*) 
                    FROM sale_returns 
                    WHERE sale_id = :sid
                ", [':sid' => $saleId]);

                if ($returnCount > 0) {
                    throw new \RuntimeException("Cannot void sale: {$returnCount} return transaction(s) have already been recorded against it.");
                }

                // 2. Fetch line items and restore physical devices & stock
                $items = Database::fetchAll("
                    SELECT * 
                    FROM sale_items 
                    WHERE sale_id = :sid 
                    FOR UPDATE
                ", [':sid' => $saleId]);

                foreach ($items as $item) {
                    if ($item['item_type'] === 'ACCESSORY') {
                        $qtyCol = self::getInvQtyCol();
                        $stockRow = Database::fetchOne("
                            SELECT {$qtyCol} 
                            FROM inventory_stock 
                            WHERE product_id = :pid 
                            FOR UPDATE
                        ", [':pid' => $item['product_id']]);

                        $oldQty = (int)($stockRow[$qtyCol] ?? 0);
                        $newQty = $oldQty + (int)$item['quantity'];

                        self::updateInvStock((int)$item['product_id'], $newQty);

                        Database::execute("
                            INSERT INTO stock_movements (
                                product_id, movement_type, quantity_change,
                                quantity_before, quantity_after, unit_cost,
                                reference_table, reference_id, notes, created_by, created_at
                            ) VALUES (
                                :pid, 'ADJUSTMENT_ADD', :qchange,
                                :qbefore, :qafter, :ucost,
                                'sales', :ref_id, :notes, :cby, NOW()
                            )
                        ", [
                            ':pid'     => $item['product_id'],
                            ':qchange' => $item['quantity'],
                            ':qbefore' => $oldQty,
                            ':qafter'  => $newQty,
                            ':ucost'   => $item['unit_cost'],
                            ':ref_id'  => $saleId,
                            ':notes'   => "Stock restored from voided sale {$sale['sale_number']}",
                            ':cby'     => $operatorId,
                        ]);
                    } elseif ($item['item_type'] === 'SERIALIZED_PHONE') {
                        $phoneId = (int)$item['phone_id'];
                        $phone = Database::fetchOne("
                            SELECT * FROM phones WHERE id = :id FOR UPDATE
                        ", [':id' => $phoneId]);

                        Database::execute("
                            UPDATE phones
                            SET status = 'IN_STOCK', sale_item_id = NULL, sold_at = NULL, updated_at = NOW()
                            WHERE id = :pid
                        ", [':pid' => $phoneId]);

                        if (!empty($phone['device_id'])) {
                            Database::execute("
                                UPDATE devices 
                                SET active_phone_id = :pid, updated_at = NOW() 
                                WHERE id = :did
                            ", [
                                ':pid' => $phoneId,
                                ':did' => $phone['device_id'],
                            ]);
                        }
                    }
                }

                // 3. Mark sale voided
                Database::execute("
                    UPDATE sales
                    SET is_voided = 1,
                        voided_by = :vby,
                        voided_at = NOW(),
                        void_reason = :vreason,
                        due_amount = 0.00,
                        payment_status = 'PAID',
                        updated_at = NOW()
                    WHERE id = :id
                ", [
                    ':vby'     => $operatorId,
                    ':vreason' => $voidReason,
                    ':id'      => $saleId,
                ]);

                // 4. Audit log
                AuditLogger::log(
                    'SALE_VOIDED',
                    'sales',
                    $saleId,
                    [
                        'is_voided'      => 0,
                        'final_total'    => $sale['final_total_amount'],
                        'paid_amount'    => $sale['paid_amount'],
                        'due_amount'     => $sale['due_amount'],
                        'payment_status' => $sale['payment_status'],
                    ],
                    [
                        'is_voided'   => 1,
                        'voided_by'   => $operatorId,
                        'void_reason' => $voidReason,
                    ],
                    "Administratively voided sale {$sale['sale_number']}: {$voidReason}"
                );
            });

            View::flash('success', "Sale transaction {$saleId} successfully voided and inventory restored.");
            Security::safeRedirect("/sales/{$saleId}");
        } catch (\Throwable $e) {
            View::flash('danger', 'Void failed: ' . $e->getMessage());
            Security::safeRedirect("/sales/{$saleId}");
        }
    }

    /**
     * Permission verification helper for returns operations.
     */
    private function requireReturnPermission(): void
    {
        Auth::requireLogin();
        if (!Authorization::hasPermission('manage_sales') && !Authorization::hasPermission('perform_sales') && !Auth::isOwner()) {
            http_response_code(403);
            View::flash('danger', 'Access denied: You lack permission to process sales returns (manage_sales required).');
            Security::safeRedirect('/sales');
            exit;
        }
    }

    /**
     * Permission verification helper for administrative voids.
     */
    private function requireVoidPermission(): void
    {
        Auth::requireLogin();
        if (!Authorization::hasPermission('void_transactions') && !Auth::isOwner()) {
            http_response_code(403);
            View::flash('danger', 'Access denied: Only shop owners or administrators with void_transactions permission can void sales.');
            Security::safeRedirect('/sales');
            exit;
        }
    }
}
