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
 * Repair Records & Phone Refurbishment Controller
 *
 * Implements financial and inventory management for shop repairs:
 * 1. Customer Service Repairs (service fees, dual-source parts, customer receivables, payments).
 * 2. Shop-Owned Used Phone Refurbishments (capitalized into phone_direct_costs and phone total_cost_basis).
 *
 * NOTE: Strictly a business/financial ledger. Does NOT contain technician queues, attendance, or job tracking.
 */
class RepairController
{
    /**
     * Require repair management permission or Owner role.
     */
    private function requireRepairsPermission(): void
    {
        Auth::requireLogin();
        if (!Auth::isOwner() && !Authorization::hasPermission('manage_repairs')) {
            View::flash('danger', 'Unauthorized: You lack the permission to manage repair records.');
            Security::safeRedirect('/dashboard');
            exit;
        }
    }

    private static function getDeviceModelCol(): string
    {
        static $col = null;
        if ($col === null) {
            try {
                $cols = Database::fetchAll("DESCRIBE devices");
                $fields = array_column($cols, 'Field');
                $col = in_array('model_name', $fields, true) ? 'd.model_name' : 'd.model';
            } catch (\Throwable) {
                try {
                    $cols = Database::fetchAll("PRAGMA table_info(devices)");
                    $fields = array_column($cols, 'name');
                    $col = in_array('model_name', $fields, true) && !in_array('model', $fields, true) ? 'd.model_name' : (in_array('model', $fields, true) ? 'd.model' : 'd.model_name');
                } catch (\Throwable) {
                    $col = 'd.model_name';
                }
            }
        }
        return $col;
    }

    private static function getProductNameCol(string $prefix = 'p'): string
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
        return $hasModelName ? "{$prefix}.model_name" : "{$prefix}.name";
    }

    private static function getProductTypeCol(string $prefix = 'p'): string
    {
        static $hasItemType = null;
        if ($hasItemType === null) {
            try {
                $cols = Database::fetchAll("DESCRIBE products");
                $fields = array_column($cols, 'Field');
                $hasItemType = in_array('item_type', $fields, true) && !in_array('product_type', $fields, true);
            } catch (\Throwable) {
                try {
                    $cols = Database::fetchAll("PRAGMA table_info(products)");
                    $fields = array_column($cols, 'name');
                    $hasItemType = in_array('item_type', $fields, true) && !in_array('product_type', $fields, true);
                } catch (\Throwable) {
                    $hasItemType = true;
                }
            }
        }
        return $hasItemType ? "{$prefix}.item_type" : "{$prefix}.product_type";
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
                SET quantity = :new_qty, current_quantity = :new_qty, updated_at = NOW()
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
     * List repair records with search, type filter, status filter, customer credit filter, and pagination.
     */
    public function index(): void
    {
        Auth::requireLogin();

        $typeFilter   = strtoupper(trim($_GET['type'] ?? 'ALL'));
        if (!in_array($typeFilter, ['ALL', 'CUSTOMER_SERVICE', 'REFURBISHMENT'], true)) {
            $typeFilter = 'ALL';
        }

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

        if ($typeFilter !== 'ALL') {
            $whereClauses[] = "r.repair_type = :type";
            $params[':type'] = $typeFilter;
        }

        if ($creditOnly) {
            $whereClauses[] = "r.repair_type = 'CUSTOMER_SERVICE' AND r.due_amount > 0.00";
        } elseif ($statusFilter !== 'ALL') {
            $whereClauses[] = "r.payment_status = :status";
            $params[':status'] = $statusFilter;
        }

        if ($dateFrom !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateFrom)) {
            $whereClauses[] = "r.repair_date >= :date_from";
            $params[':date_from'] = $dateFrom;
        }

        if ($dateTo !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateTo)) {
            $whereClauses[] = "r.repair_date <= :date_to";
            $params[':date_to'] = $dateTo;
        }

        if ($search !== '') {
            $whereClauses[] = "(
                r.repair_number LIKE :q1 OR 
                r.customer_name LIKE :q2 OR 
                r.customer_phone LIKE :q3 OR
                r.device_model LIKE :q4 OR
                r.repair_description LIKE :q5
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
        $countSql = "SELECT COUNT(*) FROM repair_records r {$whereSql}";
        $totalItems = (int)Database::fetchColumn($countSql, $params);
        $totalPages = max(1, (int)ceil($totalItems / $perPage));

        // Aggregate financial stats for summary cards
        $statsSql = "
            SELECT 
                COUNT(*) AS total_repairs_count,
                COALESCE(SUM(CASE WHEN r.repair_type = 'CUSTOMER_SERVICE' THEN r.amount_charged ELSE 0.00 END), 0.00) AS total_revenue,
                COALESCE(SUM(r.paid_amount), 0.00) AS total_paid,
                COALESCE(SUM(CASE WHEN r.repair_type = 'CUSTOMER_SERVICE' THEN r.due_amount ELSE 0.00 END), 0.00) AS total_due,
                COALESCE(SUM(r.parts_cost), 0.00) AS total_parts_cost,
                COALESCE(SUM(r.labour_amount), 0.00) AS total_labour_cost,
                COALESCE(SUM(r.total_repair_cost), 0.00) AS total_cost,
                COALESCE(SUM(CASE WHEN r.repair_type = 'CUSTOMER_SERVICE' THEN r.net_profit_contribution ELSE 0.00 END), 0.00) AS total_net_profit
            FROM repair_records r 
            {$whereSql}
        ";
        $stats = Database::fetchOne($statsSql, $params) ?: [
            'total_repairs_count' => 0,
            'total_revenue'       => '0.00',
            'total_paid'          => '0.00',
            'total_due'           => '0.00',
            'total_parts_cost'    => '0.00',
            'total_labour_cost'   => '0.00',
            'total_cost'          => '0.00',
            'total_net_profit'    => '0.00',
        ];

        // Fetch records for current page
        $dModelCol = self::getDeviceModelCol();
        $prNameCol = self::getProductNameCol('pr');

        $sql = "
            SELECT 
                r.*,
                u.full_name AS created_by_name,
                d.brand AS phone_brand,
                {$dModelCol} AS phone_model,
                d.imei_1 AS phone_imei,
                {$prNameCol} AS phone_product_name
            FROM repair_records r
            JOIN users u ON r.created_by = u.id
            LEFT JOIN phones p ON r.phone_id = p.id
            LEFT JOIN devices d ON p.device_id = d.id
            LEFT JOIN products pr ON p.product_id = pr.id
            {$whereSql}
            ORDER BY r.repair_date DESC, r.id DESC
            LIMIT {$perPage} OFFSET {$offset}
        ";
        $repairs = Database::fetchAll($sql, $params);

        $canViewCosts  = Auth::isOwner() || Authorization::hasPermission('view_purchase_costs');
        $canViewProfit = Auth::isOwner() || Authorization::hasPermission('view_profit_margins');
        $canManage     = Auth::isOwner() || Authorization::hasPermission('manage_repairs');

        View::render('repairs/index', [
            'pageTitle'     => 'Repair & Refurbishment Records',
            'repairs'       => $repairs,
            'stats'         => $stats,
            'canViewCosts'  => $canViewCosts,
            'canViewProfit' => $canViewProfit,
            'canManage'     => $canManage,
            'filters'       => [
                'type'        => $typeFilter,
                'status'      => $statusFilter,
                'credit_only' => $creditOnly,
                'date_from'   => $dateFrom,
                'date_to'     => $dateTo,
                'q'           => $search,
            ],
            'pagination'    => [
                'page'        => $page,
                'perPage'     => $perPage,
                'totalItems'  => $totalItems,
                'totalPages'  => $totalPages,
            ],
        ], 'main');
    }

    /**
     * Show form to record a new Customer Service Repair.
     */
    public function createCustomerRepair(): void
    {
        $this->requireRepairsPermission();

        $pNameCol = self::getProductNameCol('p');
        $pTypeCol = self::getProductTypeCol('p');
        $qtyCol   = self::getInvQtyCol();

        // Fetch in-stock catalog accessories / spare parts for parts dropdown
        $stockParts = Database::fetchAll("
            SELECT 
                p.id, {$pNameCol} AS name, p.sku, c.name AS category_name,
                s.{$qtyCol} AS current_quantity, s.avg_unit_cost
            FROM products p
            JOIN categories c ON p.category_id = c.id
            JOIN inventory_stock s ON p.id = s.product_id
            WHERE p.is_active = 1 AND {$pTypeCol} = 'ACCESSORY' AND s.{$qtyCol} > 0
            ORDER BY {$pNameCol} ASC
        ");

        $canViewCosts = Auth::isOwner() || Authorization::hasPermission('view_purchase_costs');

        View::render('repairs/create_customer', [
            'pageTitle'    => 'New Customer Repair Record',
            'stockParts'   => $stockParts,
            'canViewCosts' => $canViewCosts,
            'formData'     => [
                'repair_date'    => date('Y-m-d'),
                'customer_name'  => '',
                'customer_phone' => '',
                'device_model'   => '',
                'repair_description' => '',
                'labour_amount'  => '0.00',
                'amount_charged' => '',
                'initial_payment'=> '0.00',
                'payment_mode'   => 'CASH',
                'transaction_ref'=> '',
                'notes'          => '',
            ],
        ], 'main');
    }

    /**
     * Store customer service repair record with parts deduction and initial payment receipt.
     */
    public function storeCustomerRepair(): void
    {
        $this->requireRepairsPermission();

        $csrfToken = $_POST['csrf_token'] ?? null;
        if (!Security::validateCsrfToken($csrfToken)) {
            View::flash('danger', 'Security verification failed (CSRF token missing or expired).');
            Security::safeRedirect('/repairs/customer/create');
            return;
        }

        $repairDate     = trim($_POST['repair_date'] ?? date('Y-m-d'));
        $customerName   = trim($_POST['customer_name'] ?? '');
        $customerPhone  = trim($_POST['customer_phone'] ?? '');
        $deviceModel    = trim($_POST['device_model'] ?? '');
        $description    = trim($_POST['repair_description'] ?? '');
        $labourAmountRaw = trim($_POST['labour_amount'] ?? '0.00');
        $amountChargedRaw = trim($_POST['amount_charged'] ?? '0.00');
        $initialPaymentRaw = trim($_POST['initial_payment'] ?? '0.00');
        $paymentMode    = strtoupper(trim($_POST['payment_mode'] ?? 'CASH'));
        $transactionRef = trim($_POST['transaction_ref'] ?? '');
        $notes          = trim($_POST['notes'] ?? '');

        $errors = [];

        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $repairDate)) {
            $errors[] = 'Valid repair date (YYYY-MM-DD) is required.';
        }
        if ($customerName === '') {
            $errors[] = 'Customer full name is required.';
        }
        if ($customerPhone === '' || strlen($customerPhone) < 7 || strlen($customerPhone) > 20) {
            $errors[] = 'Valid customer phone number (7–20 digits) is required.';
        }
        if ($deviceModel === '') {
            $errors[] = 'Device model (e.g. Redmi Note 10 Pro) is required.';
        }
        if ($description === '') {
            $errors[] = 'Repair description / fault summary is required.';
        }

        if (!is_numeric($labourAmountRaw) || (float)$labourAmountRaw < 0.00) {
            $errors[] = 'Labour / service charge cost must be a non-negative decimal amount.';
        }
        if (!is_numeric($amountChargedRaw) || (float)$amountChargedRaw < 0.00) {
            $errors[] = 'Amount charged to customer must be a non-negative decimal amount.';
        }
        if (!is_numeric($initialPaymentRaw) || (float)$initialPaymentRaw < 0.00) {
            $errors[] = 'Initial payment must be a non-negative decimal amount.';
        }

        $labourAmount   = Financial::toDecimal((float)$labourAmountRaw);
        $amountCharged  = Financial::toDecimal((float)$amountChargedRaw);
        $initialPayment = Financial::toDecimal((float)$initialPaymentRaw);

        // Overpayment defense: initial payment cannot exceed amount charged
        if ((float)$initialPayment > (float)$amountCharged) {
            $errors[] = "Initial payment (₹{$initialPayment}) cannot exceed amount charged (₹{$amountCharged}).";
        }

        $allowedModes = ['CASH', 'UPI', 'CARD', 'BANK_TRANSFER', 'CHEQUE'];
        if ((float)$initialPayment > 0.00 && !in_array($paymentMode, $allowedModes, true)) {
            $errors[] = 'Please select a valid payment mode for the initial payment.';
        }

        // Process parts lines
        $partsSources = $_POST['part_source'] ?? [];
        $partsProducts = $_POST['part_product_id'] ?? [];
        $partsNames    = $_POST['part_name'] ?? [];
        $partsQtys     = $_POST['part_qty'] ?? [];
        $partsCosts    = $_POST['part_unit_cost'] ?? [];

        $parsedParts = [];
        $partCount = is_array($partsSources) ? count($partsSources) : 0;

        for ($i = 0; $i < $partCount; $i++) {
            $src = strtoupper(trim($partsSources[$i] ?? ''));
            if ($src === '') {
                continue;
            }

            if (!in_array($src, ['IN_HOUSE_STOCK', 'EXTERNAL_PURCHASE'], true)) {
                $errors[] = "Invalid part source type specified on line " . ($i + 1) . ".";
                continue;
            }

            $qty = (int)($partsQtys[$i] ?? 0);
            if ($qty <= 0) {
                $errors[] = "Quantity must be at least 1 on part line " . ($i + 1) . ".";
                continue;
            }

            if ($src === 'IN_HOUSE_STOCK') {
                $productId = (int)($partsProducts[$i] ?? 0);
                if ($productId <= 0) {
                    $errors[] = "Please select a catalog product for in-house part on line " . ($i + 1) . ".";
                    continue;
                }
                $parsedParts[] = [
                    'source_type' => 'IN_HOUSE_STOCK',
                    'product_id'  => $productId,
                    'part_name'   => '', // Will resolve from catalog
                    'quantity'    => $qty,
                    'unit_cost'   => '0.00', // Will resolve authoritatively from inventory_stock
                ];
            } else {
                $partName = trim($partsNames[$i] ?? '');
                $unitCostRaw = trim($partsCosts[$i] ?? '0.00');
                if ($partName === '') {
                    $errors[] = "Part name / description is required for external purchase on line " . ($i + 1) . ".";
                    continue;
                }
                if (!is_numeric($unitCostRaw) || (float)$unitCostRaw < 0.00) {
                    $errors[] = "Valid unit cost is required for external part on line " . ($i + 1) . ".";
                    continue;
                }
                $parsedParts[] = [
                    'source_type' => 'EXTERNAL_PURCHASE',
                    'product_id'  => null,
                    'part_name'   => $partName,
                    'quantity'    => $qty,
                    'unit_cost'   => Financial::toDecimal((float)$unitCostRaw),
                ];
            }
        }

        if (!empty($errors)) {
            View::flash('danger', implode(' ', $errors));
            Security::safeRedirect('/repairs/customer/create');
            return;
        }

        $userId = Auth::id() ?? 1;

        try {
            $repairId = 0;
            Database::transaction(function() use (
                $repairDate, $customerName, $customerPhone, $deviceModel,
                $description, $labourAmount, $amountCharged, $initialPayment,
                $paymentMode, $transactionRef, $notes, $parsedParts, $userId, &$repairId
            ) {
                // 1. Authoritative in-house parts verification and inventory row locking
                $totalPartsCost = '0.00';
                $processedParts = [];
                $qtyCol   = self::getInvQtyCol();
                $pNameCol = self::getProductNameCol('p');

                foreach ($parsedParts as $part) {
                    if ($part['source_type'] === 'IN_HOUSE_STOCK') {
                        $stockRow = Database::fetchOne("
                            SELECT s.product_id, s.{$qtyCol} AS current_quantity, s.avg_unit_cost, {$pNameCol} AS product_name
                            FROM inventory_stock s
                            JOIN products p ON s.product_id = p.id
                            WHERE s.product_id = :pid
                            FOR UPDATE
                        ", [':pid' => $part['product_id']]);

                        if (!$stockRow) {
                            throw new \RuntimeException("Selected catalog part (ID #{$part['product_id']}) not found in inventory.");
                        }

                        if ((int)$stockRow['current_quantity'] < $part['quantity']) {
                            throw new \RuntimeException("Insufficient inventory for '{$stockRow['product_name']}'. Required: {$part['quantity']}, Available: {$stockRow['current_quantity']}.");
                        }

                        $unitCost = (string)$stockRow['avg_unit_cost'];
                        $subtotal = Financial::mul($unitCost, (string)$part['quantity']);
                        $totalPartsCost = Financial::add($totalPartsCost, $subtotal);

                        $processedParts[] = [
                            'source_type'   => 'IN_HOUSE_STOCK',
                            'product_id'    => $part['product_id'],
                            'part_name'     => $stockRow['product_name'],
                            'quantity'      => $part['quantity'],
                            'unit_cost'     => $unitCost,
                            'subtotal_cost' => $subtotal,
                            'stock_before'  => (int)$stockRow['current_quantity'],
                        ];
                    } else {
                        $unitCost = (string)$part['unit_cost'];
                        $subtotal = Financial::mul($unitCost, (string)$part['quantity']);
                        $totalPartsCost = Financial::add($totalPartsCost, $subtotal);

                        $processedParts[] = [
                            'source_type'   => 'EXTERNAL_PURCHASE',
                            'product_id'    => null,
                            'part_name'     => $part['part_name'],
                            'quantity'      => $part['quantity'],
                            'unit_cost'     => $unitCost,
                            'subtotal_cost' => $subtotal,
                        ];
                    }
                }

                // 2. Authoritative calculations
                $totalRepairCost = Financial::add($totalPartsCost, $labourAmount);
                $netProfit = Financial::sub($amountCharged, $totalRepairCost);
                $dueAmount = Financial::sub($amountCharged, $initialPayment);

                $paymentStatus = 'PAID';
                if ((float)$dueAmount > 0.00) {
                    $paymentStatus = ((float)$initialPayment > 0.00) ? 'PARTIAL' : 'UNPAID';
                }

                // 3. Generate unique repair record number
                $dateCode = date('Ymd', strtotime($repairDate));
                $repairNumber = 'REP-' . $dateCode . '-' . strtoupper(bin2hex(random_bytes(2)));

                // 4. Insert repair record
                Database::execute("
                    INSERT INTO repair_records (
                        repair_number, repair_type, phone_id,
                        customer_name, customer_phone, device_model,
                        repair_description, parts_cost, labour_amount,
                        total_repair_cost, amount_charged, net_profit_contribution,
                        payment_status, paid_amount, due_amount,
                        repair_date, notes, created_by, created_at, updated_at
                    ) VALUES (
                        :rnum, 'CUSTOMER_SERVICE', NULL,
                        :cname, :cphone, :model,
                        :desc, :pcost, :lamt,
                        :tcost, :acharge, :profit,
                        :pstatus, :paid, :due,
                        :rdate, :notes, :cby, NOW(), NOW()
                    )
                ", [
                    ':rnum'    => $repairNumber,
                    ':cname'   => $customerName,
                    ':cphone'  => $customerPhone,
                    ':model'   => $deviceModel,
                    ':desc'    => $description,
                    ':pcost'   => $totalPartsCost,
                    ':lamt'    => $labourAmount,
                    ':tcost'   => $totalRepairCost,
                    ':acharge' => $amountCharged,
                    ':profit'  => $netProfit,
                    ':pstatus' => $paymentStatus,
                    ':paid'    => $initialPayment,
                    ':due'     => $dueAmount,
                    ':rdate'   => $repairDate,
                    ':notes'   => $notes !== '' ? $notes : null,
                    ':cby'     => $userId,
                ]);

                $repairId = (int)Database::lastInsertId();

                // 5. Insert parts ledger and execute stock deductions for in-house parts
                foreach ($processedParts as $p) {
                    Database::execute("
                        INSERT INTO repair_parts_used (
                            repair_id, source_type, product_id,
                            part_name, quantity, unit_cost,
                            subtotal_cost, created_at
                        ) VALUES (
                            :rid, :stype, :pid,
                            :pname, :qty, :ucost,
                            :subtotal, NOW()
                        )
                    ", [
                        ':rid'      => $repairId,
                        ':stype'    => $p['source_type'],
                        ':pid'      => $p['product_id'],
                        ':pname'    => $p['part_name'],
                        ':qty'      => $p['quantity'],
                        ':ucost'    => $p['unit_cost'],
                        ':subtotal' => $p['subtotal_cost'],
                    ]);

                    if ($p['source_type'] === 'IN_HOUSE_STOCK') {
                        $newStock = $p['stock_before'] - $p['quantity'];

                        // Deduct inventory
                        self::updateInvStock((int)$p['product_id'], $newStock);

                        // Double-entry stock movement
                        Database::execute("
                            INSERT INTO stock_movements (
                                product_id, movement_type, quantity_change,
                                quantity_before, quantity_after, unit_cost,
                                reference_table, reference_id, notes,
                                created_by, created_at
                            ) VALUES (
                                :pid, 'REPAIR_USAGE', :qchange,
                                :qbefore, :qafter, :ucost,
                                'repair_records', :refid, :notes,
                                :cby, NOW()
                            )
                        ", [
                            ':pid'     => $p['product_id'],
                            ':qchange' => -$p['quantity'],
                            ':qbefore' => $p['stock_before'],
                            ':qafter'  => $newStock,
                            ':ucost'   => $p['unit_cost'],
                            ':refid'   => $repairId,
                            ':notes'   => "Parts consumed in repair {$repairNumber}",
                            ':cby'     => $userId,
                        ]);
                    }
                }

                // 6. Record initial payment receipt if provided
                if ((float)$initialPayment > 0.00) {
                    $paymentNumber = 'REC-' . $dateCode . '-' . strtoupper(bin2hex(random_bytes(3)));
                    Database::execute("
                        INSERT INTO payments (
                            payment_number, transaction_type, reference_table,
                            reference_id, vendor_id, payment_date, amount,
                            payment_mode, transaction_ref, notes, received_by, created_at
                        ) VALUES (
                            :pnum, 'REPAIR_RECEIPT', 'repair_records',
                            :refid, NULL, :pdate, :amt,
                            :pmode, :txref, :pnotes, :rcv_by, NOW()
                        )
                    ", [
                        ':pnum'   => $paymentNumber,
                        ':refid'  => $repairId,
                        ':pdate'  => $repairDate,
                        ':amt'    => $initialPayment,
                        ':pmode'  => $paymentMode,
                        ':txref'  => $transactionRef !== '' ? $transactionRef : null,
                        ':pnotes' => "Initial receipt for customer repair {$repairNumber}",
                        ':rcv_by' => $userId,
                    ]);
                }

                // 7. Audit log
                AuditLogger::log(
                    'REPAIR_CUSTOMER_SERVICE_CREATED',
                    'repair_records',
                    $repairId,
                    null,
                    [
                        'repair_number'   => $repairNumber,
                        'customer_name'   => $customerName,
                        'device_model'    => $deviceModel,
                        'amount_charged'  => $amountCharged,
                        'parts_cost'      => $totalPartsCost,
                        'labour_amount'   => $labourAmount,
                        'total_cost'      => $totalRepairCost,
                        'net_profit'      => $netProfit,
                        'initial_payment' => $initialPayment,
                        'due_amount'      => $dueAmount,
                    ],
                    "Created customer repair record {$repairNumber}",
                    $userId
                );
            });

            View::flash('success', "Customer repair record created successfully.");
            Security::safeRedirect("/repairs/{$repairId}");
        } catch (\Throwable $e) {
            View::flash('danger', 'Failed to create customer repair: ' . $e->getMessage());
            Security::safeRedirect('/repairs/customer/create');
        }
    }

    /**
     * Show form to record a Shop-Owned Used Phone Refurbishment.
     */
    public function createRefurbishment(): void
    {
        $this->requireRepairsPermission();

        $dModelCol = self::getDeviceModelCol();
        $prNameCol = self::getProductNameCol('pr');
        $pNameCol  = self::getProductNameCol('p');
        $pTypeCol  = self::getProductTypeCol('p');
        $qtyCol    = self::getInvQtyCol();

        // Fetch eligible in-stock second-hand phones
        $phones = Database::fetchAll("
            SELECT 
                p.id, p.purchase_cost, p.repair_cost, p.other_cost, p.total_cost_basis,
                p.device_condition, p.ram, p.storage, p.color,
                d.brand, {$dModelCol} AS model, d.imei_1, d.imei_2,
                {$prNameCol} AS product_name
            FROM phones p
            JOIN devices d ON p.device_id = d.id
            JOIN products pr ON p.product_id = pr.id
            WHERE p.status = 'IN_STOCK' AND p.phone_type = 'SECOND_HAND'
            ORDER BY p.id DESC
        ");

        // Fetch in-stock catalog accessories / spare parts for parts dropdown
        $stockParts = Database::fetchAll("
            SELECT 
                p.id, {$pNameCol} AS name, p.sku, c.name AS category_name,
                s.{$qtyCol} AS current_quantity, s.avg_unit_cost
            FROM products p
            JOIN categories c ON p.category_id = c.id
            JOIN inventory_stock s ON p.id = s.product_id
            WHERE p.is_active = 1 AND {$pTypeCol} = 'ACCESSORY' AND s.{$qtyCol} > 0
            ORDER BY {$pNameCol} ASC
        ");

        $canViewCosts = Auth::isOwner() || Authorization::hasPermission('view_purchase_costs');

        View::render('repairs/create_refurbish', [
            'pageTitle'    => 'New Used-Phone Refurbishment Record',
            'phones'       => $phones,
            'stockParts'   => $stockParts,
            'canViewCosts' => $canViewCosts,
            'formData'     => [
                'repair_date'        => date('Y-m-d'),
                'phone_id'           => (int)($_GET['phone_id'] ?? 0),
                'repair_description' => '',
                'labour_amount'      => '0.00',
                'notes'              => '',
            ],
        ], 'main');
    }

    /**
     * Store refurbishment record, deduct parts, log direct costs, and recalculate phone total cost basis.
     */
    public function storeRefurbishment(): void
    {
        $this->requireRepairsPermission();

        $csrfToken = $_POST['csrf_token'] ?? null;
        if (!Security::validateCsrfToken($csrfToken)) {
            View::flash('danger', 'Security verification failed (CSRF token missing or expired).');
            Security::safeRedirect('/repairs/refurbish/create');
            return;
        }

        $phoneIdRaw      = trim($_POST['phone_id'] ?? '0');
        $repairDate      = trim($_POST['repair_date'] ?? date('Y-m-d'));
        $description     = trim($_POST['repair_description'] ?? '');
        $labourAmountRaw = trim($_POST['labour_amount'] ?? '0.00');
        $notes           = trim($_POST['notes'] ?? '');

        $errors = [];

        $phoneId = (int)$phoneIdRaw;
        if ($phoneId <= 0) {
            $errors[] = 'Please select a valid second-hand phone handset.';
        }

        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $repairDate)) {
            $errors[] = 'Valid repair date (YYYY-MM-DD) is required.';
        }

        if ($description === '') {
            $errors[] = 'Refurbishment description / work summary is required.';
        }

        if (!is_numeric($labourAmountRaw) || (float)$labourAmountRaw < 0.00) {
            $errors[] = 'Refurbishment labour / servicing cost must be a non-negative decimal amount.';
        }

        $labourAmount = Financial::toDecimal((float)$labourAmountRaw);

        // Process parts lines
        $partsSources  = $_POST['part_source'] ?? [];
        $partsProducts = $_POST['part_product_id'] ?? [];
        $partsNames    = $_POST['part_name'] ?? [];
        $partsQtys     = $_POST['part_qty'] ?? [];
        $partsCosts    = $_POST['part_unit_cost'] ?? [];

        $parsedParts = [];
        $partCount = is_array($partsSources) ? count($partsSources) : 0;

        for ($i = 0; $i < $partCount; $i++) {
            $src = strtoupper(trim($partsSources[$i] ?? ''));
            if ($src === '') {
                continue;
            }

            if (!in_array($src, ['IN_HOUSE_STOCK', 'EXTERNAL_PURCHASE'], true)) {
                $errors[] = "Invalid part source type specified on line " . ($i + 1) . ".";
                continue;
            }

            $qty = (int)($partsQtys[$i] ?? 0);
            if ($qty <= 0) {
                $errors[] = "Quantity must be at least 1 on part line " . ($i + 1) . ".";
                continue;
            }

            if ($src === 'IN_HOUSE_STOCK') {
                $productId = (int)($partsProducts[$i] ?? 0);
                if ($productId <= 0) {
                    $errors[] = "Please select a catalog product for in-house part on line " . ($i + 1) . ".";
                    continue;
                }
                $parsedParts[] = [
                    'source_type' => 'IN_HOUSE_STOCK',
                    'product_id'  => $productId,
                    'part_name'   => '',
                    'quantity'    => $qty,
                    'unit_cost'   => '0.00',
                ];
            } else {
                $partName = trim($partsNames[$i] ?? '');
                $unitCostRaw = trim($partsCosts[$i] ?? '0.00');
                if ($partName === '') {
                    $errors[] = "Part name / description is required for external purchase on line " . ($i + 1) . ".";
                    continue;
                }
                if (!is_numeric($unitCostRaw) || (float)$unitCostRaw < 0.00) {
                    $errors[] = "Valid unit cost is required for external part on line " . ($i + 1) . ".";
                    continue;
                }
                $parsedParts[] = [
                    'source_type' => 'EXTERNAL_PURCHASE',
                    'product_id'  => null,
                    'part_name'   => $partName,
                    'quantity'    => $qty,
                    'unit_cost'   => Financial::toDecimal((float)$unitCostRaw),
                ];
            }
        }

        if (!empty($errors)) {
            View::flash('danger', implode(' ', $errors));
            Security::safeRedirect('/repairs/refurbish/create');
            return;
        }

        $userId = Auth::id() ?? 1;

        try {
            $repairId = 0;
            Database::transaction(function() use (
                $phoneId, $repairDate, $description, $labourAmount,
                $notes, $parsedParts, $userId, &$repairId
            ) {
                // 1. Lock phone row for update and verify state
                $dModelCol = self::getDeviceModelCol();
                $phone = Database::fetchOne("
                    SELECT p.*, d.brand, {$dModelCol} AS model, d.imei_1
                    FROM phones p
                    JOIN devices d ON p.device_id = d.id
                    WHERE p.id = :id
                    FOR UPDATE
                ", [':id' => $phoneId]);

                if (!$phone) {
                    throw new \RuntimeException('Selected phone record not found.');
                }
                if ($phone['phone_type'] !== 'SECOND_HAND') {
                    throw new \RuntimeException('Refurbishment can only be logged for second-hand handsets.');
                }
                if ($phone['status'] !== 'IN_STOCK') {
                    throw new \RuntimeException("Phone episode is not IN_STOCK (Current status: {$phone['status']}). Refurbishment is restricted to available inventory.");
                }

                // 2. Authoritative in-house parts verification and inventory row locking
                $totalPartsCost = '0.00';
                $processedParts = [];
                $qtyCol   = self::getInvQtyCol();
                $pNameCol = self::getProductNameCol('p');

                foreach ($parsedParts as $part) {
                    if ($part['source_type'] === 'IN_HOUSE_STOCK') {
                        $stockRow = Database::fetchOne("
                            SELECT s.product_id, s.{$qtyCol} AS current_quantity, s.avg_unit_cost, {$pNameCol} AS product_name
                            FROM inventory_stock s
                            JOIN products p ON s.product_id = p.id
                            WHERE s.product_id = :pid
                            FOR UPDATE
                        ", [':pid' => $part['product_id']]);

                        if (!$stockRow) {
                            throw new \RuntimeException("Selected catalog part (ID #{$part['product_id']}) not found in inventory.");
                        }

                        if ((int)$stockRow['current_quantity'] < $part['quantity']) {
                            throw new \RuntimeException("Insufficient inventory for '{$stockRow['product_name']}'. Required: {$part['quantity']}, Available: {$stockRow['current_quantity']}.");
                        }

                        $unitCost = (string)$stockRow['avg_unit_cost'];
                        $subtotal = Financial::mul($unitCost, (string)$part['quantity']);
                        $totalPartsCost = Financial::add($totalPartsCost, $subtotal);

                        $processedParts[] = [
                            'source_type'   => 'IN_HOUSE_STOCK',
                            'product_id'    => $part['product_id'],
                            'part_name'     => $stockRow['product_name'],
                            'quantity'      => $part['quantity'],
                            'unit_cost'     => $unitCost,
                            'subtotal_cost' => $subtotal,
                            'stock_before'  => (int)$stockRow['current_quantity'],
                        ];
                    } else {
                        $unitCost = (string)$part['unit_cost'];
                        $subtotal = Financial::mul($unitCost, (string)$part['quantity']);
                        $totalPartsCost = Financial::add($totalPartsCost, $subtotal);

                        $processedParts[] = [
                            'source_type'   => 'EXTERNAL_PURCHASE',
                            'product_id'    => null,
                            'part_name'     => $part['part_name'],
                            'quantity'      => $part['quantity'],
                            'unit_cost'     => $unitCost,
                            'subtotal_cost' => $subtotal,
                        ];
                    }
                }

                // 3. Refurbishment financials: Capitalized into phone cost basis (amount_charged = 0.00, profit = 0.00)
                $totalRepairCost = Financial::add($totalPartsCost, $labourAmount);
                $deviceModelString = $phone['brand'] . ' ' . $phone['model'];

                $dateCode = date('Ymd', strtotime($repairDate));
                $repairNumber = 'REP-' . $dateCode . '-' . strtoupper(bin2hex(random_bytes(2)));

                // 4. Insert repair record
                Database::execute("
                    INSERT INTO repair_records (
                        repair_number, repair_type, phone_id,
                        customer_name, customer_phone, device_model,
                        repair_description, parts_cost, labour_amount,
                        total_repair_cost, amount_charged, net_profit_contribution,
                        payment_status, paid_amount, due_amount,
                        repair_date, notes, created_by, created_at, updated_at
                    ) VALUES (
                        :rnum, 'REFURBISHMENT', :pid,
                        NULL, NULL, :model,
                        :desc, :pcost, :lamt,
                        :tcost, 0.00, 0.00,
                        'PAID', 0.00, 0.00,
                        :rdate, :notes, :cby, NOW(), NOW()
                    )
                ", [
                    ':rnum'   => $repairNumber,
                    ':pid'    => $phoneId,
                    ':model'  => $deviceModelString,
                    ':desc'   => $description,
                    ':pcost'  => $totalPartsCost,
                    ':lamt'   => $labourAmount,
                    ':tcost'  => $totalRepairCost,
                    ':rdate'  => $repairDate,
                    ':notes'  => $notes !== '' ? $notes : null,
                    ':cby'    => $userId,
                ]);

                $repairId = (int)Database::lastInsertId();

                // 5. Insert parts ledger & deduct in-house parts from inventory
                foreach ($processedParts as $p) {
                    Database::execute("
                        INSERT INTO repair_parts_used (
                            repair_id, source_type, product_id,
                            part_name, quantity, unit_cost,
                            subtotal_cost, created_at
                        ) VALUES (
                            :rid, :stype, :pid,
                            :pname, :qty, :ucost,
                            :subtotal, NOW()
                        )
                    ", [
                        ':rid'      => $repairId,
                        ':stype'    => $p['source_type'],
                        ':pid'      => $p['product_id'],
                        ':pname'    => $p['part_name'],
                        ':qty'      => $p['quantity'],
                        ':ucost'    => $p['unit_cost'],
                        ':subtotal' => $p['subtotal_cost'],
                    ]);

                    if ($p['source_type'] === 'IN_HOUSE_STOCK') {
                        $newStock = $p['stock_before'] - $p['quantity'];

                        // Deduct inventory
                        self::updateInvStock((int)$p['product_id'], $newStock);

                        // Double-entry stock movement
                        Database::execute("
                            INSERT INTO stock_movements (
                                product_id, movement_type, quantity_change,
                                quantity_before, quantity_after, unit_cost,
                                reference_table, reference_id, notes,
                                created_by, created_at
                            ) VALUES (
                                :pid, 'REPAIR_USAGE', :qchange,
                                :qbefore, :qafter, :ucost,
                                'repair_records', :refid, :notes,
                                :cby, NOW()
                            )
                        ", [
                            ':pid'     => $p['product_id'],
                            ':qchange' => -$p['quantity'],
                            ':qbefore' => $p['stock_before'],
                            ':qafter'  => $newStock,
                            ':ucost'   => $p['unit_cost'],
                            ':refid'   => $repairId,
                            ':notes'   => "Parts consumed in refurbishment {$repairNumber}",
                            ':cby'     => $userId,
                        ]);
                    }
                }

                // 6. Direct Cost Ledger Integration into phone_direct_costs
                if ((float)$totalPartsCost > 0.00) {
                    Database::execute("
                        INSERT INTO phone_direct_costs (
                            phone_id, cost_type, description,
                            amount, cost_date, created_by, created_at
                        ) VALUES (
                            :pid, 'PARTS', :desc,
                            :amt, :cdate, :cby, NOW()
                        )
                    ", [
                        ':pid'   => $phoneId,
                        ':desc'  => "Refurbishment #{$repairNumber} parts: {$description}",
                        ':amt'   => $totalPartsCost,
                        ':cdate' => $repairDate,
                        ':cby'   => $userId,
                    ]);
                }

                if ((float)$labourAmount > 0.00) {
                    Database::execute("
                        INSERT INTO phone_direct_costs (
                            phone_id, cost_type, description,
                            amount, cost_date, created_by, created_at
                        ) VALUES (
                            :pid, 'LABOUR', :desc,
                            :amt, :cdate, :cby, NOW()
                        )
                    ", [
                        ':pid'   => $phoneId,
                        ':desc'  => "Refurbishment #{$repairNumber} labour: {$description}",
                        ':amt'   => $labourAmount,
                        ':cdate' => $repairDate,
                        ':cby'   => $userId,
                    ]);
                }

                // 7. Recalculate phone's total repair_cost and authoritative total_cost_basis
                $recalcRepairCost = (string)Database::fetchColumn("
                    SELECT COALESCE(SUM(amount), 0.00)
                    FROM phone_direct_costs
                    WHERE phone_id = :pid AND cost_type IN ('REPAIR', 'PARTS', 'LABOUR')
                ", [':pid' => $phoneId]);

                $purchaseCost = (string)$phone['purchase_cost'];
                $otherCost    = (string)$phone['other_cost'];

                $newTotalCostBasis = Financial::add($purchaseCost, Financial::add($recalcRepairCost, $otherCost));

                Database::execute("
                    UPDATE phones
                    SET repair_cost = :rcost, total_cost_basis = :tbasis, updated_at = NOW()
                    WHERE id = :pid
                ", [
                    ':rcost'  => $recalcRepairCost,
                    ':tbasis' => $newTotalCostBasis,
                    ':pid'    => $phoneId,
                ]);

                // 8. Audit log
                AuditLogger::log(
                    'REPAIR_REFURBISHMENT_CREATED',
                    'repair_records',
                    $repairId,
                    null,
                    [
                        'repair_number'     => $repairNumber,
                        'phone_id'          => $phoneId,
                        'parts_cost'        => $totalPartsCost,
                        'labour_amount'     => $labourAmount,
                        'total_repair_cost' => $totalRepairCost,
                        'old_cost_basis'    => $phone['total_cost_basis'],
                        'new_cost_basis'    => $newTotalCostBasis,
                    ],
                    "Completed phone refurbishment {$repairNumber}, updated handset #{$phoneId} cost basis to ₹{$newTotalCostBasis}",
                    $userId
                );
            });

            View::flash('success', "Used phone refurbishment record created. Handset cost basis updated.");
            Security::safeRedirect("/repairs/{$repairId}");
        } catch (\Throwable $e) {
            View::flash('danger', 'Failed to create refurbishment record: ' . $e->getMessage());
            Security::safeRedirect('/repairs/refurbish/create');
        }
    }

    /**
     * Show repair details with parts ledger, payments history, and cost basis tracking.
     */
    public function show(array $params): void
    {
        Auth::requireLogin();

        $id = (int)($params['id'] ?? 0);
        $dModelCol = self::getDeviceModelCol();
        $prNameCol = self::getProductNameCol('pr');
        $pNameCol  = self::getProductNameCol('p');

        $repair = Database::fetchOne("
            SELECT 
                r.*,
                u.full_name AS created_by_name,
                p.phone_type, p.purchase_cost AS phone_purchase_cost,
                p.repair_cost AS phone_repair_cost, p.other_cost AS phone_other_cost,
                p.total_cost_basis AS phone_total_cost_basis, p.status AS phone_status,
                d.brand AS phone_brand, {$dModelCol} AS phone_model,
                d.imei_1 AS phone_imei, d.imei_2 AS phone_imei_2,
                {$prNameCol} AS phone_product_name
            FROM repair_records r
            JOIN users u ON r.created_by = u.id
            LEFT JOIN phones p ON r.phone_id = p.id
            LEFT JOIN devices d ON p.device_id = d.id
            LEFT JOIN products pr ON p.product_id = pr.id
            WHERE r.id = :id
        ", [':id' => $id]);

        if (!$repair) {
            View::flash('danger', 'Repair record not found.');
            Security::safeRedirect('/repairs');
            return;
        }

        // Fetch parts used
        $parts = Database::fetchAll("
            SELECT 
                rpu.*,
                {$pNameCol} AS catalog_product_name,
                p.sku AS product_sku
            FROM repair_parts_used rpu
            LEFT JOIN products p ON rpu.product_id = p.id
            WHERE rpu.repair_id = :id
            ORDER BY rpu.id ASC
        ", [':id' => $id]);

        // Fetch payments ledger for this repair
        $payments = Database::fetchAll("
            SELECT 
                pm.*,
                u.full_name AS received_by_name
            FROM payments pm
            JOIN users u ON pm.received_by = u.id
            WHERE pm.reference_table = 'repair_records' AND pm.reference_id = :id
            ORDER BY pm.payment_date ASC, pm.id ASC
        ", [':id' => $id]);

        $canViewCosts  = Auth::isOwner() || Authorization::hasPermission('view_purchase_costs');
        $canViewProfit = Auth::isOwner() || Authorization::hasPermission('view_profit_margins');
        $canManage     = Auth::isOwner() || Authorization::hasPermission('manage_repairs');

        View::render('repairs/show', [
            'pageTitle'     => "Repair: {$repair['repair_number']}",
            'repair'        => $repair,
            'parts'         => $parts,
            'payments'      => $payments,
            'canViewCosts'  => $canViewCosts,
            'canViewProfit' => $canViewProfit,
            'canManage'     => $canManage,
        ], 'main');
    }

    /**
     * Show form to record a subsequent payment against customer repair due balance.
     */
    public function showRecordPayment(array $params): void
    {
        $this->requireRepairsPermission();

        $id = (int)($params['id'] ?? 0);
        $repair = Database::fetchOne("
            SELECT * FROM repair_records WHERE id = :id
        ", [':id' => $id]);

        if (!$repair) {
            View::flash('danger', 'Repair record not found.');
            Security::safeRedirect('/repairs');
            return;
        }

        if ($repair['repair_type'] !== 'CUSTOMER_SERVICE') {
            View::flash('danger', 'Payments can only be recorded against customer service repairs.');
            Security::safeRedirect("/repairs/{$id}");
            return;
        }

        if ((float)$repair['due_amount'] <= 0.00 || $repair['payment_status'] === 'PAID') {
            View::flash('info', "Repair {$repair['repair_number']} is already fully settled. Outstanding balance is ₹0.00.");
            Security::safeRedirect("/repairs/{$id}");
            return;
        }

        View::render('repairs/record_payment', [
            'pageTitle' => "Record Payment: {$repair['repair_number']}",
            'repair'    => $repair,
            'formData'  => [
                'payment_date' => date('Y-m-d'),
                'amount'       => $repair['due_amount'],
                'payment_mode' => 'CASH',
            ],
        ], 'main');
    }

    /**
     * Process subsequent customer payment with overpayment defense and row-level locking.
     */
    public function recordPayment(array $params): void
    {
        $this->requireRepairsPermission();

        $id = (int)($params['id'] ?? 0);

        $csrfToken = $_POST['csrf_token'] ?? null;
        if (!Security::validateCsrfToken($csrfToken)) {
            View::flash('danger', 'Security verification failed (CSRF token missing or expired).');
            Security::safeRedirect("/repairs/{$id}");
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
            Security::safeRedirect("/repairs/{$id}/payments/create");
            return;
        }

        $paymentAmount = Financial::toDecimal((float)$amountRaw);
        $operatorId = Auth::id() ?? 1;

        try {
            Database::transaction(function() use (
                $id, $paymentDate, $paymentAmount, $paymentMode,
                $transactionRef, $notes, $operatorId
            ) {
                // 1. Lock repair row for update
                $repair = Database::fetchOne("
                    SELECT id, repair_number, repair_type, customer_name,
                           amount_charged, paid_amount, due_amount, payment_status
                    FROM repair_records
                    WHERE id = :id
                    FOR UPDATE
                ", [':id' => $id]);

                if (!$repair) {
                    throw new \RuntimeException('Repair record not found.');
                }

                if ($repair['repair_type'] !== 'CUSTOMER_SERVICE') {
                    throw new \RuntimeException('Payments can only be recorded for customer service repairs.');
                }

                // 2. Authoritative paid calculation from payments table
                $currentPaid = (string)Database::fetchColumn("
                    SELECT COALESCE(SUM(amount), 0.00)
                    FROM payments
                    WHERE reference_table = 'repair_records' AND reference_id = :id AND transaction_type = 'REPAIR_RECEIPT'
                ", [':id' => $id]);

                $amountCharged = (string)$repair['amount_charged'];
                $currentDue = Financial::sub($amountCharged, $currentPaid);

                // 3. Overpayment defense
                if ((float)$paymentAmount > (float)$currentDue) {
                    throw new \RuntimeException("Payment amount (₹{$paymentAmount}) exceeds current outstanding balance due (₹{$currentDue}). Overpayment is prohibited.");
                }

                // 4. Insert payment record
                $paymentNumber = 'REC-' . date('Ymd', strtotime($paymentDate)) . '-' . strtoupper(bin2hex(random_bytes(3)));

                Database::execute("
                    INSERT INTO payments (
                        payment_number, transaction_type, reference_table,
                        reference_id, vendor_id, payment_date, amount,
                        payment_mode, transaction_ref, notes, received_by, created_at
                    ) VALUES (
                        :pnum, 'REPAIR_RECEIPT', 'repair_records',
                        :refid, NULL, :pdate, :amt,
                        :pmode, :txref, :pnotes, :rcv_by, NOW()
                    )
                ", [
                    ':pnum'   => $paymentNumber,
                    ':refid'  => $id,
                    ':pdate'  => $paymentDate,
                    ':amt'    => $paymentAmount,
                    ':pmode'  => $paymentMode,
                    ':txref'  => $transactionRef !== '' ? $transactionRef : null,
                    ':pnotes' => $notes !== '' ? $notes : null,
                    ':rcv_by' => $operatorId,
                ]);

                $newPaid = Financial::add($currentPaid, $paymentAmount);
                $newDue  = Financial::sub($amountCharged, $newPaid);
                $newStatus = ((float)$newDue <= 0.00) ? 'PAID' : 'PARTIAL';

                // 5. Update repair record
                Database::execute("
                    UPDATE repair_records
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
                    'REPAIR_PAYMENT_RECORDED',
                    'repair_records',
                    $id,
                    ['paid_amount' => $currentPaid, 'due_amount' => $currentDue, 'payment_status' => $repair['payment_status']],
                    ['paid_amount' => $newPaid, 'due_amount' => $newDue, 'payment_status' => $newStatus, 'received_amount' => $paymentAmount],
                    "Recorded customer payment of ₹{$paymentAmount} ({$paymentMode}) against repair {$repair['repair_number']}",
                    $operatorId
                );
            });

            View::flash('success', "Payment of ₹{$paymentAmount} recorded successfully against repair.");
            Security::safeRedirect("/repairs/{$id}");
        } catch (\Throwable $e) {
            View::flash('danger', 'Failed to record payment: ' . $e->getMessage());
            Security::safeRedirect("/repairs/{$id}/payments/create");
        }
    }
}
