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
 * Counter Sales & Customer Credit Controller
 *
 * Implements internal sales records, multi-item cart processing (accessories & serialized handsets),
 * stock ledger deductions, phone episode retirement (status = 'SOLD'), exact proportional discount allocation,
 * customer credit / receivable tracking, multiple payment receipts, and authoritative profit formulations.
 *
 * NOTE: This is strictly an internal business management system, NOT a customer-facing tax invoicing platform.
 */
class SaleController
{
    /**
     * List sales records with search, date filters, payment status, credit filters, and pagination.
     */
    public function index(): void
    {
        Auth::requireLogin();

        $statusFilter = strtoupper(trim($_GET['status'] ?? 'ALL'));
        if (!in_array($statusFilter, ['ALL', 'PAID', 'PARTIAL', 'UNPAID'], true)) {
            $statusFilter = 'ALL';
        }

        $creditOnly = !empty($_GET['credit_only']) && $_GET['credit_only'] === '1';
        $dateFrom   = trim($_GET['date_from'] ?? '');
        $dateTo     = trim($_GET['date_to'] ?? '');
        $search     = trim($_GET['q'] ?? '');

        $page = max(1, (int)($_GET['page'] ?? 1));
        $perPage = 15;
        $offset = ($page - 1) * $perPage;

        $whereClauses = [];
        $params = [];

        if ($creditOnly) {
            $whereClauses[] = "s.due_amount > 0.00";
        } elseif ($statusFilter !== 'ALL') {
            $whereClauses[] = "s.payment_status = :status";
            $params[':status'] = $statusFilter;
        }

        if ($dateFrom !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateFrom)) {
            $whereClauses[] = "s.sale_date >= :date_from";
            $params[':date_from'] = $dateFrom;
        }

        if ($dateTo !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateTo)) {
            $whereClauses[] = "s.sale_date <= :date_to";
            $params[':date_to'] = $dateTo;
        }

        if ($search !== '') {
            $whereClauses[] = "(
                s.sale_number LIKE :q1 OR 
                s.customer_name LIKE :q2 OR 
                s.customer_phone LIKE :q3
            )";
            $qWild = '%' . $search . '%';
            $params[':q1'] = $qWild;
            $params[':q2'] = $qWild;
            $params[':q3'] = $qWild;
        }

        $whereSql = !empty($whereClauses) ? "WHERE " . implode(" AND ", $whereClauses) : "";

        // Total records count
        $countSql = "SELECT COUNT(*) FROM sales s {$whereSql}";
        $totalItems = (int)Database::fetchColumn($countSql, $params);
        $totalPages = max(1, (int)ceil($totalItems / $perPage));

        // Aggregate financial stats for summary cards
        $statsSql = "
            SELECT 
                COUNT(*) AS total_sales_count,
                COALESCE(SUM(s.final_total_amount), 0.00) AS total_revenue,
                COALESCE(SUM(s.paid_amount), 0.00) AS total_paid,
                COALESCE(SUM(s.due_amount), 0.00) AS total_due,
                COALESCE(SUM(s.gross_profit), 0.00) AS total_gross_profit
            FROM sales s 
            {$whereSql}
        ";
        $stats = Database::fetchOne($statsSql, $params) ?: [
            'total_sales_count'  => 0,
            'total_revenue'      => '0.00',
            'total_paid'         => '0.00',
            'total_due'          => '0.00',
            'total_gross_profit' => '0.00',
        ];

        // Retrieve paginated records
        $sql = "
            SELECT s.*, u.username AS cashier_username,
                   (SELECT COUNT(*) FROM sale_items si WHERE si.sale_id = s.id) AS items_count
            FROM sales s
            LEFT JOIN users u ON u.id = s.created_by
            {$whereSql}
            ORDER BY s.sale_date DESC, s.sale_time DESC, s.id DESC
            LIMIT {$perPage} OFFSET {$offset}
        ";
        $sales = Database::fetchAll($sql, $params);

        $canPerformSales = Authorization::hasPermission('perform_sales') || Authorization::hasPermission('manage_sales') || Auth::isOwner();
        $canViewCosts = Authorization::hasPermission('view_purchase_costs') || Auth::isOwner();
        $canViewProfits = Authorization::hasPermission('view_profit_margins') || Auth::isOwner();

        View::render('sales/index', [
            'pageTitle'        => $creditOnly ? 'Customer Credit (Udhar) Ledger' : 'Counter Sales Records',
            'sales'            => $sales,
            'stats'            => $stats,
            'currentPage'      => $page,
            'totalPages'       => $totalPages,
            'totalItems'       => $totalItems,
            'statusFilter'     => $statusFilter,
            'creditOnly'       => $creditOnly,
            'dateFrom'         => $dateFrom,
            'dateTo'           => $dateTo,
            'search'           => $search,
            'canPerformSales'  => $canPerformSales,
            'canViewCosts'     => $canViewCosts,
            'canViewProfits'   => $canViewProfits,
        ], 'main');
    }

    /**
     * Show the fast counter sale entry interface.
     */
    public function create(): void
    {
        $this->requireSalesPermission();

        // 1. Fetch available accessories with current stock on hand
        $accessories = Database::fetchAll("
            SELECT p.id, p.category_id, p.brand, p.model_name, p.default_sale_price,
                   COALESCE(s.quantity, 0) AS stock_qty,
                   COALESCE(s.avg_unit_cost, 0.00) AS avg_unit_cost
            FROM products p
            LEFT JOIN inventory_stock s ON s.product_id = p.id
            WHERE p.is_active = 1 AND p.item_type = 'ACCESSORY'
            ORDER BY p.brand ASC, p.model_name ASC
        ");

        // 2. Fetch available in-stock serialized phone episodes
        $phones = Database::fetchAll("
            SELECT ph.id, ph.product_id, ph.phone_type, ph.ram, ph.storage, ph.color,
                   ph.device_condition, ph.total_cost_basis, ph.expected_sale_price,
                   d.imei_1, d.imei_2, d.serial_number,
                   pr.brand, pr.model_name, pr.default_sale_price
            FROM phones ph
            JOIN devices d ON d.id = ph.device_id
            JOIN products pr ON pr.id = ph.product_id
            WHERE ph.status = 'IN_STOCK'
            ORDER BY pr.brand ASC, pr.model_name ASC, ph.id DESC
        ");

        $canApplyDiscounts = Authorization::hasPermission('apply_discounts') || Authorization::hasPermission('manage_sales') || Auth::isOwner();
        $canViewCosts = Authorization::hasPermission('view_purchase_costs') || Auth::isOwner();

        View::render('sales/create', [
            'pageTitle'         => 'New Counter Sale Entry',
            'accessories'       => $accessories,
            'phones'            => $phones,
            'canApplyDiscounts' => $canApplyDiscounts,
            'canViewCosts'      => $canViewCosts,
            'todayDate'         => date('Y-m-d'),
            'nowTime'           => date('H:i:s'),
        ], 'main');
    }

    /**
     * Process counter sale transaction atomically.
     */
    public function store(): void
    {
        $this->requireSalesPermission();

        $csrfToken = $_POST['csrf_token'] ?? null;
        if (!Security::validateCsrfToken($csrfToken)) {
            View::flash('danger', 'Security verification failed (CSRF token missing or expired). Please try again.');
            Security::safeRedirect('/sales/create');
            return;
        }

        $saleDate        = trim($_POST['sale_date'] ?? date('Y-m-d'));
        $saleTime        = trim($_POST['sale_time'] ?? date('H:i:s'));
        $customerName    = trim($_POST['customer_name'] ?? '');
        $customerPhone   = trim($_POST['customer_phone'] ?? '');
        $notes           = trim($_POST['notes'] ?? '');
        $discountRaw     = trim($_POST['discount_amount'] ?? '0.00');
        $initialPayRaw   = trim($_POST['initial_payment'] ?? '0.00');
        $paymentMode     = strtoupper(trim($_POST['payment_mode'] ?? 'CASH'));
        $transactionRef  = trim($_POST['transaction_ref'] ?? '');
        $rawItems        = $_POST['items'] ?? [];

        $errors = [];

        // Validate date & time
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $saleDate)) {
            $errors[] = 'Sale date must be in YYYY-MM-DD format.';
        }
        if (!preg_match('/^\d{2}:\d{2}(:\d{2})?$/', $saleTime)) {
            $saleTime = date('H:i:s');
        }
        if (strlen($saleTime) === 5) {
            $saleTime .= ':00';
        }

        // Validate customer phone if provided
        if ($customerPhone !== '' && !preg_match('/^[6-9]\d{9}$/', $customerPhone)) {
            $errors[] = 'Customer phone number must be a valid 10-digit Indian mobile number.';
        }

        // Validate items container
        if (empty($rawItems) || !is_array($rawItems)) {
            $errors[] = 'A sale must contain at least one accessory or serialized mobile phone.';
        }

        // Validate discount permission & amount
        $canApplyDiscounts = Authorization::hasPermission('apply_discounts') || Authorization::hasPermission('manage_sales') || Auth::isOwner();
        $discountAmount = '0.00';
        if ($discountRaw !== '' && (float)$discountRaw > 0.00) {
            if (!$canApplyDiscounts) {
                $errors[] = 'You do not have authorization to apply sale discounts (apply_discounts permission required).';
            } elseif (!is_numeric($discountRaw) || (float)$discountRaw < 0.00) {
                $errors[] = 'Discount amount must be a positive decimal number.';
            } else {
                $discountAmount = Financial::toDecimal((float)$discountRaw);
            }
        }

        // Validate initial payment
        $initialPayment = '0.00';
        if ($initialPayRaw !== '' && (float)$initialPayRaw > 0.00) {
            if (!is_numeric($initialPayRaw) || (float)$initialPayRaw < 0.00) {
                $errors[] = 'Initial payment amount cannot be negative.';
            } else {
                $initialPayment = Financial::toDecimal((float)$initialPayRaw);
            }

            $allowedModes = ['CASH', 'UPI', 'CARD', 'BANK_TRANSFER', 'CHEQUE'];
            if (!in_array($paymentMode, $allowedModes, true)) {
                $errors[] = 'Please select a valid payment mode (CASH, UPI, CARD, BANK_TRANSFER, CHEQUE).';
            }
        }

        if (!empty($errors)) {
            View::flash('danger', implode(' ', $errors));
            Security::safeRedirect('/sales/create');
            return;
        }

        $cashierId = Auth::id() ?? 1;

        try {
            $createdSaleId = 0;
            $createdSaleNumber = '';

            Database::transaction(function() use (
                $saleDate, $saleTime, $customerName, $customerPhone, $notes,
                $discountAmount, $initialPayment, $paymentMode, $transactionRef,
                $rawItems, $cashierId, &$createdSaleId, &$createdSaleNumber
            ) {
                // 1. Generate unique internal sale record identifier
                $saleNumber = 'SAL-' . date('Ymd', strtotime($saleDate)) . '-' . strtoupper(bin2hex(random_bytes(3)));
                $createdSaleNumber = $saleNumber;

                $validatedLines = [];
                $subtotalAccumulator = '0.00';
                $selectedPhoneIds = [];

                // 2. Process and lock every line item
                foreach ($rawItems as $idx => $line) {
                    $itemType = strtoupper(trim((string)($line['item_type'] ?? '')));

                    if ($itemType === 'ACCESSORY') {
                        $productId = (int)($line['product_id'] ?? 0);
                        $qty = (int)($line['quantity'] ?? 0);
                        $unitPriceRaw = trim((string)($line['unit_sale_price'] ?? ''));

                        if ($productId <= 0) {
                            throw new \RuntimeException("Item #" . ($idx + 1) . ": Invalid product selected.");
                        }

                        // Verify product exists and is active
                        $product = Database::fetchOne("
                            SELECT id, brand, model_name, item_type, is_active 
                            FROM products 
                            WHERE id = :id
                        ", [':id' => $productId]);

                        if (!$product || (int)$product['is_active'] !== 1) {
                            throw new \RuntimeException("Item #" . ($idx + 1) . ": Product is inactive or does not exist.");
                        }
                        if ($product['item_type'] !== 'ACCESSORY') {
                            throw new \RuntimeException("Item #" . ($idx + 1) . ": Selected product is not a non-serialized accessory.");
                        }

                        if ($qty <= 0) {
                            throw new \RuntimeException("Item #" . ($idx + 1) . " ({$product['brand']} {$product['model_name']}): Quantity must be at least 1.");
                        }

                        if (!is_numeric($unitPriceRaw) || (float)$unitPriceRaw < 0) {
                            throw new \RuntimeException("Item #" . ($idx + 1) . " ({$product['brand']} {$product['model_name']}): Selling price must be a non-negative decimal amount.");
                        }
                        $unitSalePrice = Financial::toDecimal((float)$unitPriceRaw);

                        // Lock inventory stock row
                        $stock = Database::fetchOne("
                            SELECT product_id, quantity, avg_unit_cost 
                            FROM inventory_stock 
                            WHERE product_id = :pid 
                            FOR UPDATE
                        ", [':pid' => $productId]);

                        $currentQty = $stock ? (int)$stock['quantity'] : 0;
                        $avgUnitCost = $stock ? Financial::toDecimal((float)$stock['avg_unit_cost']) : '0.00';

                        // Verify sufficient stock availability
                        if ($currentQty < $qty) {
                            throw new \RuntimeException("Insufficient stock for {$product['brand']} {$product['model_name']}. On hand: {$currentQty}, requested: {$qty}. Negative inventory is prohibited.");
                        }

                        $lineSubtotal = Financial::mul((string)$qty, $unitSalePrice);
                        $subtotalAccumulator = Financial::add($subtotalAccumulator, $lineSubtotal);
                        $lineSubtotalCost = Financial::mul((string)$qty, $avgUnitCost);

                        $validatedLines[] = [
                            'item_type'           => 'ACCESSORY',
                            'product_id'          => $productId,
                            'phone_id'            => null,
                            'quantity'            => $qty,
                            'unit_cost'           => $avgUnitCost,
                            'unit_sale_price'     => $unitSalePrice,
                            'line_subtotal_price' => $lineSubtotal,
                            'subtotal_cost'       => $lineSubtotalCost,
                            'current_stock_qty'   => $currentQty,
                            'product_label'       => "{$product['brand']} {$product['model_name']}",
                        ];
                    } elseif ($itemType === 'SERIALIZED_PHONE') {
                        $phoneId = (int)($line['phone_id'] ?? 0);
                        $unitPriceRaw = trim((string)($line['unit_sale_price'] ?? ''));

                        if ($phoneId <= 0) {
                            throw new \RuntimeException("Item #" . ($idx + 1) . ": Please select a valid serialized mobile phone.");
                        }

                        // Prevent duplicate selection within the same checkout request
                        if (in_array($phoneId, $selectedPhoneIds, true)) {
                            throw new \RuntimeException("Duplicate handset selected in cart (Phone ID #{$phoneId}). A physical device episode cannot be sold twice.");
                        }
                        $selectedPhoneIds[] = $phoneId;

                        // Lock the phone row
                        $phone = Database::fetchOne("
                            SELECT ph.*, d.imei_1, d.imei_2, pr.brand, pr.model_name 
                            FROM phones ph
                            JOIN devices d ON d.id = ph.device_id
                            JOIN products pr ON pr.id = ph.product_id
                            WHERE ph.id = :id 
                            FOR UPDATE
                        ", [':id' => $phoneId]);

                        if (!$phone) {
                            throw new \RuntimeException("Item #" . ($idx + 1) . ": Serialized phone record #{$phoneId} not found.");
                        }

                        // Verify phone is active IN_STOCK
                        if ($phone['status'] !== 'IN_STOCK') {
                            throw new \RuntimeException("Handset [IMEI: {$phone['imei_1']}] {$phone['brand']} {$phone['model_name']} is currently '{$phone['status']}' and cannot be sold.");
                        }

                        if (!is_numeric($unitPriceRaw) || (float)$unitPriceRaw < 0) {
                            throw new \RuntimeException("Item #" . ($idx + 1) . " ([IMEI: {$phone['imei_1']}] {$phone['brand']} {$phone['model_name']}): Selling price must be a non-negative decimal amount.");
                        }
                        $unitSalePrice = Financial::toDecimal((float)$unitPriceRaw);
                        $authoritativeCost = Financial::toDecimal((float)$phone['total_cost_basis']);

                        $lineSubtotal = $unitSalePrice;
                        $subtotalAccumulator = Financial::add($subtotalAccumulator, $lineSubtotal);

                        $validatedLines[] = [
                            'item_type'           => 'SERIALIZED_PHONE',
                            'product_id'          => (int)$phone['product_id'],
                            'phone_id'            => $phoneId,
                            'quantity'            => 1,
                            'unit_cost'           => $authoritativeCost,
                            'unit_sale_price'     => $unitSalePrice,
                            'line_subtotal_price' => $lineSubtotal,
                            'subtotal_cost'       => $authoritativeCost,
                            'current_stock_qty'   => 1,
                            'product_label'       => "{$phone['brand']} {$phone['model_name']} (IMEI: {$phone['imei_1']})",
                        ];
                    } else {
                        throw new \RuntimeException("Item #" . ($idx + 1) . ": Unknown item type '{$itemType}'.");
                    }
                }

                $subtotalAmount = $subtotalAccumulator;

                // 3. Discount validation & exact proportional line allocation
                if ((float)$discountAmount > (float)$subtotalAmount) {
                    throw new \RuntimeException("Discount amount (₹{$discountAmount}) cannot exceed the sale subtotal (₹{$subtotalAmount}).");
                }

                // Prepare items for proportional discount allocation
                $discountAllocationInput = [];
                foreach ($validatedLines as $k => $l) {
                    $discountAllocationInput[] = [
                        'id'       => $k,
                        'subtotal' => $l['line_subtotal_price'],
                        'cost'     => $l['subtotal_cost'],
                    ];
                }

                $allocatedDiscounts = Financial::allocateDiscountProportionally($discountAllocationInput, $discountAmount);

                $finalTotalAmount = Financial::sub($subtotalAmount, $discountAmount);
                $totalCostBasisAccumulator = '0.00';

                // Merge allocated discount and calculated profit into lines
                foreach ($allocatedDiscounts as $k => $alloc) {
                    $validatedLines[$k]['discount_allocated'] = $alloc['discount'];
                    $validatedLines[$k]['net_sale_price']    = $alloc['net_price'];
                    $validatedLines[$k]['item_profit']       = $alloc['profit'];
                    $totalCostBasisAccumulator = Financial::add($totalCostBasisAccumulator, $validatedLines[$k]['subtotal_cost']);
                }

                $totalCostBasis = $totalCostBasisAccumulator;
                // Calculate sale gross profit (loss-making sales preserved accurately with negative sign)
                $grossProfit = Financial::sub($finalTotalAmount, $totalCostBasis);

                // 4. Overpayment defense on initial payment
                if ((float)$initialPayment > (float)$finalTotalAmount) {
                    throw new \RuntimeException("Initial payment amount (₹{$initialPayment}) exceeds the authoritative sale total (₹{$finalTotalAmount}). Overpayment is prohibited.");
                }

                $paidAmount = $initialPayment;
                $dueAmount = Financial::sub($finalTotalAmount, $paidAmount);

                $paymentStatus = 'UNPAID';
                if ((float)$dueAmount <= 0.00) {
                    $paymentStatus = 'PAID';
                    $dueAmount = '0.00';
                } elseif ((float)$paidAmount > 0.00) {
                    $paymentStatus = 'PARTIAL';
                }

                // 5. Insert sales header
                Database::execute("
                    INSERT INTO sales (
                        sale_number, sale_date, sale_time, customer_name, customer_phone,
                        subtotal_amount, discount_amount, final_total_amount,
                        paid_amount, due_amount, payment_status, total_cost_basis, gross_profit,
                        notes, created_by, created_at, updated_at
                    ) VALUES (
                        :snum, :sdate, :stime, :cname, :cphone,
                        :subtotal, :discount, :final_total,
                        :paid, :due, :pstatus, :cost_basis, :profit,
                        :notes, :creator, NOW(), NOW()
                    )
                ", [
                    ':snum'        => $saleNumber,
                    ':sdate'       => $saleDate,
                    ':stime'       => $saleTime,
                    ':cname'       => $customerName !== '' ? $customerName : null,
                    ':cphone'      => $customerPhone !== '' ? $customerPhone : null,
                    ':subtotal'    => $subtotalAmount,
                    ':discount'    => $discountAmount,
                    ':final_total' => $finalTotalAmount,
                    ':paid'        => $paidAmount,
                    ':due'         => $dueAmount,
                    ':pstatus'     => $paymentStatus,
                    ':cost_basis'  => $totalCostBasis,
                    ':profit'      => $grossProfit,
                    ':notes'       => $notes !== '' ? $notes : null,
                    ':creator'     => $cashierId,
                ]);

                $saleId = (int)Database::lastInsertId();
                $createdSaleId = $saleId;

                // 6. Insert sale items & apply inventory movements
                foreach ($validatedLines as $line) {
                    Database::execute("
                        INSERT INTO sale_items (
                            sale_id, product_id, item_type, phone_id,
                            quantity, unit_cost, unit_sale_price, line_subtotal_price,
                            discount_allocated, net_sale_price, subtotal_cost, item_profit, created_at
                        ) VALUES (
                            :sid, :pid, :itype, :phid,
                            :qty, :cost, :sprice, :subtotal,
                            :disc, :net, :subcost, :profit, NOW()
                        )
                    ", [
                        ':sid'      => $saleId,
                        ':pid'      => $line['product_id'],
                        ':itype'    => $line['item_type'],
                        ':phid'     => $line['phone_id'],
                        ':qty'      => $line['quantity'],
                        ':cost'     => $line['unit_cost'],
                        ':sprice'   => $line['unit_sale_price'],
                        ':subtotal' => $line['line_subtotal_price'],
                        ':disc'     => $line['discount_allocated'],
                        ':net'      => $line['net_sale_price'],
                        ':subcost'  => $line['subtotal_cost'],
                        ':profit'   => $line['item_profit'],
                    ]);

                    $saleItemId = (int)Database::lastInsertId();

                    if ($line['item_type'] === 'ACCESSORY') {
                        $qtyDeducted = $line['quantity'];
                        $oldQty = $line['current_stock_qty'];
                        $newQty = $oldQty - $qtyDeducted;

                        // Deduct stock from inventory_stock
                        Database::execute("
                            UPDATE inventory_stock 
                            SET quantity = :new_qty, updated_at = NOW() 
                            WHERE product_id = :pid
                        ", [
                            ':new_qty' => $newQty,
                            ':pid'     => $line['product_id'],
                        ]);

                        // Schema-adaptive column resolution for stock_movements
                        static $smCols = null;
                        if ($smCols === null) {
                            try {
                                $cols = Database::fetchAll("DESCRIBE stock_movements");
                                $smCols = array_column($cols, 'Field');
                            } catch (\Throwable) {
                                try {
                                    $cols = Database::fetchAll("PRAGMA table_info(stock_movements)");
                                    $smCols = array_column($cols, 'name');
                                } catch (\Throwable) {
                                    $smCols = ['reference_table', 'notes'];
                                }
                            }
                        }
                        $refCol = in_array('reference_table', $smCols, true) ? 'reference_table' : 'reference_type';
                        $noteCol = in_array('notes', $smCols, true) ? 'notes' : 'reason';

                        // Record double-entry stock movement
                        Database::execute("
                            INSERT INTO stock_movements (
                                product_id, movement_type, quantity_change,
                                quantity_before, quantity_after, {$refCol},
                                reference_id, unit_cost, {$noteCol}, created_by, created_at
                            ) VALUES (
                                :pid, 'SALE', :change,
                                :before, :after, 'sales',
                                :ref_id, :cost, :reason, :creator, NOW()
                            )
                        ", [
                            ':pid'     => $line['product_id'],
                            ':change'  => -$qtyDeducted,
                            ':before'  => $oldQty,
                            ':after'   => $newQty,
                            ':ref_id'  => $saleId,
                            ':cost'    => $line['unit_cost'],
                            ':reason'  => "Counter sale {$saleNumber}",
                            ':creator' => $cashierId,
                        ]);
                    } elseif ($line['item_type'] === 'SERIALIZED_PHONE') {
                        // Mark physical episode as SOLD
                        Database::execute("
                            UPDATE phones 
                            SET status = 'SOLD', sold_at = NOW(), sale_item_id = :si_id, updated_at = NOW() 
                            WHERE id = :phid
                        ", [
                            ':si_id' => $saleItemId,
                            ':phid'  => $line['phone_id'],
                        ]);
                    }
                }

                // 7. Record initial payment if supplied
                if ((float)$paidAmount > 0.00) {
                    $paymentNumber = 'REC-' . date('Ymd', strtotime($saleDate)) . '-' . strtoupper(bin2hex(random_bytes(3)));

                    Database::execute("
                        INSERT INTO payments (
                            payment_number, transaction_type, reference_table,
                            reference_id, vendor_id, payment_date, amount,
                            payment_mode, transaction_ref, notes, received_by, created_at
                        ) VALUES (
                            :pnum, 'SALE_RECEIPT', 'sales',
                            :ref_id, NULL, :pdate, :amt,
                            :pmode, :txref, :notes, :rcv_by, NOW()
                        )
                    ", [
                        ':pnum'   => $paymentNumber,
                        ':ref_id' => $saleId,
                        ':pdate'  => $saleDate,
                        ':amt'    => $paidAmount,
                        ':pmode'  => $paymentMode,
                        ':txref'  => $transactionRef !== '' ? $transactionRef : null,
                        ':notes'  => 'Initial checkout settlement',
                        ':rcv_by' => $cashierId,
                    ]);

                    $paymentId = (int)Database::lastInsertId();

                    AuditLogger::log(
                        'SALE_PAYMENT_RECORDED',
                        'payments',
                        $paymentId,
                        null,
                        [
                            'sale_id'        => $saleId,
                            'sale_number'    => $saleNumber,
                            'payment_number' => $paymentNumber,
                            'amount'         => $paidAmount,
                            'payment_mode'   => $paymentMode,
                        ],
                        "Recorded initial payment of ₹{$paidAmount} for Sale {$saleNumber}"
                    );
                }

                // 8. Audit logs
                AuditLogger::log(
                    'SALE_CREATED',
                    'sales',
                    $saleId,
                    null,
                    [
                        'sale_number'        => $saleNumber,
                        'customer_name'      => $customerName,
                        'customer_phone'     => $customerPhone,
                        'subtotal_amount'    => $subtotalAmount,
                        'discount_amount'    => $discountAmount,
                        'final_total_amount' => $finalTotalAmount,
                        'paid_amount'        => $paidAmount,
                        'due_amount'         => $dueAmount,
                        'payment_status'     => $paymentStatus,
                        'items_count'        => count($validatedLines),
                    ],
                    "Processed counter sale {$saleNumber} (Total: ₹{$finalTotalAmount})"
                );

                if ((float)$discountAmount > 0.00) {
                    AuditLogger::log(
                        'DISCOUNT_APPLIED',
                        'sales',
                        $saleId,
                        null,
                        [
                            'discount_amount' => $discountAmount,
                            'subtotal_amount' => $subtotalAmount,
                        ],
                        "Authorized discount of ₹{$discountAmount} on Sale {$saleNumber}"
                    );
                }
            });

            View::flash('success', "Sale {$createdSaleNumber} recorded successfully.");
            Security::safeRedirect("/sales/{$createdSaleId}");
        } catch (\Throwable $e) {
            View::flash('danger', 'Sale creation aborted: ' . $e->getMessage());
            Security::safeRedirect('/sales/create');
        }
    }

    /**
     * View detailed internal sale record.
     */
    public function show(array $params): void
    {
        Auth::requireLogin();

        $id = (int)($params['id'] ?? 0);
        $sale = Database::fetchOne("
            SELECT s.*, u.username AS cashier_username, u.full_name AS cashier_fullname
            FROM sales s
            LEFT JOIN users u ON u.id = s.created_by
            WHERE s.id = :id
        ", [':id' => $id]);

        if (!$sale) {
            View::flash('danger', 'Sale record not found.');
            Security::safeRedirect('/sales');
            return;
        }

        // Fetch line items
        $items = Database::fetchAll("
            SELECT si.*, 
                   pr.brand, pr.model_name, pr.item_type AS product_type,
                   ph.phone_type, ph.ram, ph.storage, ph.color, ph.status AS phone_status,
                   d.imei_1, d.imei_2, d.serial_number
            FROM sale_items si
            JOIN products pr ON pr.id = si.product_id
            LEFT JOIN phones ph ON ph.id = si.phone_id
            LEFT JOIN devices d ON d.id = ph.device_id
            WHERE si.sale_id = :id
            ORDER BY si.id ASC
        ", [':id' => $id]);

        // Fetch payment settlements
        $payments = Database::fetchAll("
            SELECT p.*, u.username AS received_by_username
            FROM payments p
            LEFT JOIN users u ON u.id = p.received_by
            WHERE p.reference_table = 'sales' AND p.reference_id = :id AND p.transaction_type = 'SALE_RECEIPT'
            ORDER BY p.payment_date ASC, p.id ASC
        ", [':id' => $id]);

        $canPerformSales = Authorization::hasPermission('perform_sales') || Authorization::hasPermission('manage_sales') || Auth::isOwner();
        $canViewCosts = Authorization::hasPermission('view_purchase_costs') || Auth::isOwner();
        $canViewProfits = Authorization::hasPermission('view_profit_margins') || Auth::isOwner();

        View::render('sales/show', [
            'pageTitle'        => "Sale Record: {$sale['sale_number']}",
            'sale'             => $sale,
            'items'            => $items,
            'payments'         => $payments,
            'canPerformSales'  => $canPerformSales,
            'canViewCosts'     => $canViewCosts,
            'canViewProfits'   => $canViewProfits,
        ], 'main');
    }

    /**
     * Show form to record a subsequent customer payment against open balance due.
     */
    public function showRecordPayment(array $params): void
    {
        $this->requireSalesPermission();

        $id = (int)($params['id'] ?? 0);
        $sale = Database::fetchOne("SELECT * FROM sales WHERE id = :id", [':id' => $id]);

        if (!$sale) {
            View::flash('danger', 'Sale record not found.');
            Security::safeRedirect('/sales');
            return;
        }

        if ((float)$sale['due_amount'] <= 0.00 || $sale['payment_status'] === 'PAID') {
            View::flash('info', "Sale {$sale['sale_number']} is already fully settled. Outstanding balance is ₹0.00.");
            Security::safeRedirect("/sales/{$id}");
            return;
        }

        View::render('sales/record_payment', [
            'pageTitle' => "Record Payment: {$sale['sale_number']}",
            'sale'      => $sale,
            'formData'  => [
                'payment_date' => date('Y-m-d'),
                'amount'       => $sale['due_amount'],
                'payment_mode' => 'CASH',
            ],
        ], 'main');
    }

    /**
     * Process subsequent customer payment with overpayment defense and row-level locking.
     */
    public function recordPayment(array $params): void
    {
        $this->requireSalesPermission();

        $id = (int)($params['id'] ?? 0);

        $csrfToken = $_POST['csrf_token'] ?? null;
        if (!Security::validateCsrfToken($csrfToken)) {
            View::flash('danger', 'Security verification failed (CSRF token missing or expired).');
            Security::safeRedirect("/sales/{$id}");
            return;
        }

        $paymentDate    = trim($_POST['payment_date'] ?? date('Y-m-d'));
        $amountRaw      = trim($_POST['amount'] ?? '');
        $paymentMode    = strtoupper(trim($_POST['payment_mode'] ?? 'CASH'));
        $transactionRef = trim($_POST['transaction_ref'] ?? '');
        $notes          = trim($_POST['notes'] ?? '');

        $errors = [];

        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $paymentDate)) {
            $errors[] = 'Payment date must be in YYYY-MM-DD format.';
        }

        $allowedModes = ['CASH', 'UPI', 'CARD', 'BANK_TRANSFER', 'CHEQUE'];
        if (!in_array($paymentMode, $allowedModes, true)) {
            $errors[] = 'Please select a valid payment mode.';
        }

        if (!is_numeric($amountRaw) || (float)$amountRaw <= 0.00) {
            $errors[] = 'Payment amount must be a positive decimal number greater than 0.00.';
        }

        if (!empty($errors)) {
            View::flash('danger', implode(' ', $errors));
            Security::safeRedirect("/sales/{$id}/payments/create");
            return;
        }

        $paymentAmount = Financial::toDecimal((float)$amountRaw);
        $operatorId = Auth::id() ?? 1;

        try {
            Database::transaction(function() use (
                $id, $paymentDate, $paymentAmount, $paymentMode,
                $transactionRef, $notes, $operatorId
            ) {
                // 1. Lock sale row for update
                $sale = Database::fetchOne("
                    SELECT id, sale_number, customer_name, final_total_amount, paid_amount, due_amount, payment_status
                    FROM sales 
                    WHERE id = :id 
                    FOR UPDATE
                ", [':id' => $id]);

                if (!$sale) {
                    throw new \RuntimeException('Sale record not found.');
                }

                // 2. Authoritative paid calculation from payments table
                $currentPaid = (string)Database::fetchColumn("
                    SELECT COALESCE(SUM(amount), 0.00) 
                    FROM payments 
                    WHERE reference_table = 'sales' AND reference_id = :id AND transaction_type = 'SALE_RECEIPT'
                ", [':id' => $id]);

                $finalTotal = (string)$sale['final_total_amount'];
                $currentDue = Financial::sub($finalTotal, $currentPaid);

                // 3. Overpayment defense
                if ((float)$paymentAmount > (float)$currentDue) {
                    throw new \RuntimeException("Payment amount (₹{$paymentAmount}) exceeds the current outstanding balance due (₹{$currentDue}). Overpayment is prohibited.");
                }

                // 4. Insert payment record
                $paymentNumber = 'REC-' . date('Ymd', strtotime($paymentDate)) . '-' . strtoupper(bin2hex(random_bytes(3)));

                Database::execute("
                    INSERT INTO payments (
                        payment_number, transaction_type, reference_table,
                        reference_id, vendor_id, payment_date, amount,
                        payment_mode, transaction_ref, notes, received_by, created_at
                    ) VALUES (
                        :pnum, 'SALE_RECEIPT', 'sales',
                        :ref_id, NULL, :pdate, :amt,
                        :pmode, :txref, :notes, :rcv_by, NOW()
                    )
                ", [
                    ':pnum'   => $paymentNumber,
                    ':ref_id' => $id,
                    ':pdate'  => $paymentDate,
                    ':amt'    => $paymentAmount,
                    ':pmode'  => $paymentMode,
                    ':txref'  => $transactionRef !== '' ? $transactionRef : null,
                    ':notes'  => $notes !== '' ? $notes : null,
                    ':rcv_by' => $operatorId,
                ]);

                $paymentId = (int)Database::lastInsertId();

                // 5. Recalculate sale totals
                $newPaid = Financial::add($currentPaid, $paymentAmount);
                $newDue = Financial::sub($finalTotal, $newPaid);

                $newStatus = 'UNPAID';
                if ((float)$newDue <= 0.00) {
                    $newStatus = 'PAID';
                    $newDue = '0.00';
                } elseif ((float)$newPaid > 0.00) {
                    $newStatus = 'PARTIAL';
                }

                Database::execute("
                    UPDATE sales 
                    SET paid_amount = :paid, due_amount = :due, payment_status = :status, updated_at = NOW() 
                    WHERE id = :id
                ", [
                    ':paid'   => $newPaid,
                    ':due'    => $newDue,
                    ':status' => $newStatus,
                    ':id'     => $id,
                ]);

                // 6. Audit log
                AuditLogger::log(
                    'SALE_PAYMENT_RECORDED',
                    'payments',
                    $paymentId,
                    [
                        'previous_paid'   => $currentPaid,
                        'previous_status' => $sale['payment_status'],
                    ],
                    [
                        'payment_number' => $paymentNumber,
                        'amount'         => $paymentAmount,
                        'new_paid'       => $newPaid,
                        'new_due'        => $newDue,
                        'new_status'     => $newStatus,
                    ],
                    "Recorded payment of ₹{$paymentAmount} for Sale {$sale['sale_number']}"
                );
            });

            View::flash('success', "Payment receipt of ₹{$paymentAmount} recorded successfully.");
            Security::safeRedirect("/sales/{$id}");
        } catch (\Throwable $e) {
            View::flash('danger', 'Payment rejected: ' . $e->getMessage());
            Security::safeRedirect("/sales/{$id}/payments/create");
        }
    }

    /**
     * Permission verification helper for sales operations.
     */
    private function requireSalesPermission(): void
    {
        Auth::requireLogin();
        if (!Authorization::hasPermission('perform_sales') && !Authorization::hasPermission('manage_sales') && !Auth::isOwner()) {
            http_response_code(403);
            View::flash('danger', 'Access denied: You do not have permission to execute sales operations (perform_sales required).');
            Security::safeRedirect('/sales');
            exit;
        }
    }
}
