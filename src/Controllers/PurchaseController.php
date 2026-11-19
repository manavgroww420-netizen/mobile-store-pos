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
 * Purchase & Procurement Management Controller
 *
 * Handles vendor purchase orders, consignment line items,
 * dual inventory integration (accessory stock ledger + serialized mobile devices),
 * purchase payments, and vendor outstanding credit reconciliation.
 */
class PurchaseController
{
    /**
     * List purchase records with filters, search, and pagination.
     */
    public function index(): void
    {
        Auth::requireLogin();

        $vendorFilter = (int)($_GET['vendor_id'] ?? 0);
        $statusFilter = strtoupper(trim($_GET['status'] ?? 'ALL'));
        if (!in_array($statusFilter, ['ALL', 'PAID', 'PARTIAL', 'UNPAID'], true)) {
            $statusFilter = 'ALL';
        }

        $dateFrom = trim($_GET['date_from'] ?? '');
        $dateTo = trim($_GET['date_to'] ?? '');
        $search = trim($_GET['q'] ?? '');

        $page = max(1, (int)($_GET['page'] ?? 1));
        $perPage = 15;
        $offset = ($page - 1) * $perPage;

        $whereClauses = [];
        $params = [];

        if ($vendorFilter > 0) {
            $whereClauses[] = "p.vendor_id = :vid";
            $params[':vid'] = $vendorFilter;
        }

        if ($statusFilter !== 'ALL') {
            $whereClauses[] = "p.payment_status = :status";
            $params[':status'] = $statusFilter;
        }

        if ($dateFrom !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateFrom)) {
            $whereClauses[] = "p.purchase_date >= :date_from";
            $params[':date_from'] = $dateFrom;
        }

        if ($dateTo !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateTo)) {
            $whereClauses[] = "p.purchase_date <= :date_to";
            $params[':date_to'] = $dateTo;
        }

        if ($search !== '') {
            $whereClauses[] = "(
                p.purchase_number LIKE :q1 OR 
                p.vendor_invoice_no LIKE :q2 OR 
                v.business_name LIKE :q3
            )";
            $qWild = '%' . $search . '%';
            $params[':q1'] = $qWild;
            $params[':q2'] = $qWild;
            $params[':q3'] = $qWild;
        }

        $whereSql = !empty($whereClauses) ? "WHERE " . implode(" AND ", $whereClauses) : "";

        // Total count
        $countSql = "
            SELECT COUNT(*) 
            FROM purchases p
            JOIN vendors v ON v.id = p.vendor_id
            {$whereSql}
        ";
        $totalItems = (int)Database::fetchColumn($countSql, $params);
        $totalPages = max(1, (int)ceil($totalItems / $perPage));

        // Retrieve paginated purchases
        $sql = "
            SELECT p.*, v.business_name AS vendor_name, v.phone AS vendor_phone,
                   u.username AS operator_username,
                   (SELECT COUNT(*) FROM purchase_items pi WHERE pi.purchase_id = p.id) AS items_count
            FROM purchases p
            JOIN vendors v ON v.id = p.vendor_id
            LEFT JOIN users u ON u.id = p.created_by
            {$whereSql}
            ORDER BY p.purchase_date DESC, p.id DESC
            LIMIT {$perPage} OFFSET {$offset}
        ";
        $purchases = Database::fetchAll($sql, $params);

        // Fetch active vendors for filter dropdown
        $vendors = Database::fetchAll("SELECT id, business_name FROM vendors ORDER BY business_name ASC");

        // Permissions
        $canManage = Authorization::hasPermission('manage_purchases');
        $canViewCost = Authorization::hasPermission('view_purchase_costs') || Authorization::hasPermission('view_vendor_costs');

        // Mask financial values if unprivileged
        if (!$canViewCost) {
            foreach ($purchases as &$pur) {
                $pur['total_amount'] = null;
                $pur['paid_amount'] = null;
                $pur['due_amount'] = null;
            }
            unset($pur);
        }

        View::render('purchases/index', [
            'pageTitle'    => 'Purchase Consignments',
            'purchases'    => $purchases,
            'vendors'      => $vendors,
            'selectedVendor' => $vendorFilter,
            'selectedStatus' => $statusFilter,
            'dateFrom'     => $dateFrom,
            'dateTo'       => $dateTo,
            'search'       => $search,
            'currentPage'  => $page,
            'totalPages'   => $totalPages,
            'totalItems'   => $totalItems,
            'canManage'    => $canManage,
            'canViewCost'  => $canViewCost,
        ], 'main');
    }

    /**
     * Render the create purchase form.
     */
    public function create(): void
    {
        Authorization::requirePermission('manage_purchases');

        $vendors = Database::fetchAll("SELECT id, business_name, phone FROM vendors WHERE is_active = 1 ORDER BY business_name ASC");
        $products = Database::fetchAll("
            SELECT p.id, p.category_id, p.brand, p.model_name, p.item_type, p.sku, p.default_sale_price,
                   c.name AS category_name
            FROM products p
            JOIN categories c ON c.id = p.category_id
            WHERE p.is_active = 1
            ORDER BY p.item_type ASC, p.brand ASC, p.model_name ASC
        ");

        View::render('purchases/create', [
            'pageTitle'   => 'Record New Purchase',
            'vendors'     => $vendors,
            'products'    => $products,
            'formData'    => [
                'purchase_date' => date('Y-m-d'),
                'vendor_id'     => '',
                'payment_mode'  => 'BANK_TRANSFER',
            ],
            'canViewCost' => Authorization::hasPermission('view_purchase_costs'),
        ], 'main');
    }

    /**
     * Store a newly created purchase consignment in the database.
     */
    public function store(): void
    {
        Authorization::requirePermission('manage_purchases');

        $csrfToken = $_POST['csrf_token'] ?? null;
        if (!Security::validateCsrfToken($csrfToken)) {
            View::flash('danger', 'Security verification failed (CSRF token missing or expired). Please try again.');
            Security::safeRedirect('/purchases/create');
            return;
        }

        $vendorId        = (int)($_POST['vendor_id'] ?? 0);
        $purchaseDate    = trim($_POST['purchase_date'] ?? date('Y-m-d'));
        $vendorInvoiceNo = trim($_POST['vendor_invoice_no'] ?? '');
        $notes           = trim($_POST['notes'] ?? '');
        $initialPayment  = trim($_POST['initial_payment'] ?? '0.00');
        $paymentMode     = strtoupper(trim($_POST['payment_mode'] ?? 'BANK_TRANSFER'));
        $transactionRef  = trim($_POST['transaction_ref'] ?? '');

        $rawItems = $_POST['items'] ?? [];

        $errors = [];

        // 1. Validate Vendor
        $vendor = Database::fetchOne("SELECT id, business_name, is_active FROM vendors WHERE id = :id", [':id' => $vendorId]);
        if (!$vendor) {
            $errors[] = 'Please select a valid vendor from the registered directory.';
        } elseif ((int)$vendor['is_active'] !== 1) {
            $errors[] = "Vendor '{$vendor['business_name']}' is currently inactive. Inactive vendors cannot supply new purchases.";
        }

        // 2. Validate Purchase Date
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $purchaseDate)) {
            $errors[] = 'Purchase date must be in YYYY-MM-DD format.';
        }

        // 3. Validate Items Array
        if (empty($rawItems) || !is_array($rawItems)) {
            $errors[] = 'At least one purchase line item is required.';
        }

        $validatedItems = [];
        $calculatedGrandTotal = '0.00';

        foreach ($rawItems as $idx => $item) {
            $prodId = (int)($item['product_id'] ?? 0);
            $product = Database::fetchOne("
                SELECT id, brand, model_name, item_type, is_active 
                FROM products 
                WHERE id = :id
            ", [':id' => $prodId]);

            if (!$product || (int)$product['is_active'] !== 1) {
                $errors[] = "Item #" . ($idx + 1) . ": Invalid or inactive product selected.";
                continue;
            }

            $itemType = $product['item_type'];
            $unitCostRaw = trim((string)($item['unit_cost'] ?? ''));

            if (!is_numeric($unitCostRaw) || (float)$unitCostRaw < 0) {
                $errors[] = "Item #" . ($idx + 1) . " ({$product['brand']} {$product['model_name']}): Unit cost must be a non-negative decimal amount.";
                continue;
            }
            $unitCost = Financial::toDecimal((float)$unitCostRaw);

            if ($itemType === 'ACCESSORY') {
                $qty = (int)($item['quantity'] ?? 0);
                if ($qty <= 0) {
                    $errors[] = "Item #" . ($idx + 1) . " ({$product['brand']} {$product['model_name']}): Quantity must be at least 1.";
                    continue;
                }
                $subtotal = Financial::multiply((string)$qty, $unitCost);
                $calculatedGrandTotal = Financial::add($calculatedGrandTotal, $subtotal);

                $validatedItems[] = [
                    'product_id'    => $prodId,
                    'product'       => $product,
                    'item_type'     => 'ACCESSORY',
                    'quantity'      => $qty,
                    'unit_cost'     => $unitCost,
                    'subtotal_cost' => $subtotal,
                ];
            } elseif ($itemType === 'SERIALIZED_PHONE') {
                $phones = $item['phones'] ?? [];
                if (empty($phones) || !is_array($phones)) {
                    $errors[] = "Item #" . ($idx + 1) . " ({$product['brand']} {$product['model_name']}): At least one serialized phone entry with valid IMEI is required.";
                    continue;
                }

                $validatedPhones = [];
                foreach ($phones as $pIdx => $phoneData) {
                    $imei1 = trim($phoneData['imei_1'] ?? '');
                    $imei2 = trim($phoneData['imei_2'] ?? '');
                    $sn    = trim($phoneData['serial_number'] ?? '');
                    $color = trim($phoneData['color'] ?? '');
                    $ram   = trim($phoneData['ram'] ?? '');
                    $storage = trim($phoneData['storage'] ?? '');

                    if ($imei1 === '') {
                        $errors[] = "Item #" . ($idx + 1) . " Unit #" . ($pIdx + 1) . ": IMEI 1 is strictly mandatory.";
                        continue;
                    }
                    if (!Security::validateImei($imei1, true)) {
                        $errors[] = "Item #" . ($idx + 1) . " Unit #" . ($pIdx + 1) . ": IMEI 1 ({$imei1}) must be 15 numeric digits passing Luhn check.";
                        continue;
                    }

                    if ($imei2 !== '') {
                        if (!Security::validateImei($imei2, true)) {
                            $errors[] = "Item #" . ($idx + 1) . " Unit #" . ($pIdx + 1) . ": IMEI 2 ({$imei2}) must be 15 numeric digits passing Luhn check.";
                            continue;
                        }
                        if ($imei1 === $imei2) {
                            $errors[] = "Item #" . ($idx + 1) . " Unit #" . ($pIdx + 1) . ": IMEI 2 cannot be identical to IMEI 1.";
                            continue;
                        }
                    } else {
                        $imei2 = null;
                    }

                    // Check duplicate IMEI in database
                    $existingImei = Database::fetchOne("
                        SELECT id, brand, model_name 
                        FROM devices 
                        WHERE imei_1 = :i1 OR imei_2 = :i1 OR ( :i2 IS NOT NULL AND (imei_1 = :i2 OR imei_2 = :i2) )
                        LIMIT 1
                    ", [':i1' => $imei1, ':i2' => $imei2]);

                    if ($existingImei) {
                        $errors[] = "Item #" . ($idx + 1) . " Unit #" . ($pIdx + 1) . ": IMEI {$imei1} is already registered to Device #{$existingImei['id']} ({$existingImei['brand']} {$existingImei['model_name']}).";
                        continue;
                    }

                    $validatedPhones[] = [
                        'imei_1'         => $imei1,
                        'imei_2'         => $imei2,
                        'serial_number'  => $sn !== '' ? $sn : null,
                        'color'          => $color !== '' ? $color : null,
                        'ram'            => $ram !== '' ? $ram : null,
                        'storage'        => $storage !== '' ? $storage : null,
                    ];
                }

                $qty = count($validatedPhones);
                if ($qty === 0) {
                    continue;
                }

                $subtotal = Financial::multiply((string)$qty, $unitCost);
                $calculatedGrandTotal = Financial::add($calculatedGrandTotal, $subtotal);

                $validatedItems[] = [
                    'product_id'    => $prodId,
                    'product'       => $product,
                    'item_type'     => 'SERIALIZED_PHONE',
                    'quantity'      => $qty,
                    'unit_cost'     => $unitCost,
                    'subtotal_cost' => $subtotal,
                    'phones'        => $validatedPhones,
                ];
            }
        }

        // 4. Validate Initial Payment
        $initialPaymentAmount = '0.00';
        if ($initialPayment !== '' && is_numeric($initialPayment)) {
            $numPay = (float)$initialPayment;
            if ($numPay < 0) {
                $errors[] = 'Initial payment amount cannot be negative.';
            } elseif ($numPay > (float)$calculatedGrandTotal) {
                $errors[] = "Initial payment amount (₹{$initialPayment}) exceeds the authoritative purchase total (₹{$calculatedGrandTotal}). Overpayment is prohibited.";
            } else {
                $initialPaymentAmount = Financial::toDecimal($numPay);
            }
        }

        if (!empty($errors)) {
            View::flash('danger', implode(' ', $errors));
            $vendors = Database::fetchAll("SELECT id, business_name, phone FROM vendors WHERE is_active = 1 ORDER BY business_name ASC");
            $products = Database::fetchAll("SELECT id, category_id, brand, model_name, item_type, default_sale_price FROM products WHERE is_active = 1 ORDER BY brand ASC, model_name ASC");
            View::render('purchases/create', [
                'pageTitle'   => 'Record New Purchase',
                'vendors'     => $vendors,
                'products'    => $products,
                'formData'    => [
                    'vendor_id'         => $vendorId,
                    'purchase_date'     => $purchaseDate,
                    'vendor_invoice_no' => $vendorInvoiceNo,
                    'notes'             => $notes,
                    'initial_payment'   => $initialPayment,
                    'payment_mode'      => $paymentMode,
                    'transaction_ref'   => $transactionRef,
                ],
                'canViewCost' => Authorization::hasPermission('view_purchase_costs'),
            ], 'main');
            return;
        }

        $operatorId = Auth::id() ?? 1;

        // Authoritative payment calculations
        $totalAmount = $calculatedGrandTotal;
        $paidAmount = $initialPaymentAmount;
        $dueAmount = Financial::subtract($totalAmount, $paidAmount);

        $paymentStatus = 'UNPAID';
        if ((float)$paidAmount >= (float)$totalAmount && (float)$totalAmount > 0) {
            $paymentStatus = 'PAID';
        } elseif ((float)$paidAmount > 0) {
            $paymentStatus = 'PARTIAL';
        }

        // Multi-table atomic transaction
        try {
            $purchaseId = Database::transaction(function() use (
                $vendorId, $purchaseDate, $vendorInvoiceNo, $totalAmount,
                $paidAmount, $dueAmount, $paymentStatus, $notes,
                $operatorId, $validatedItems, $initialPaymentAmount,
                $paymentMode, $transactionRef
            ) {
                // Generate unique purchase number
                $purchaseNumber = 'PUR-' . date('Ymd', strtotime($purchaseDate)) . '-' . strtoupper(bin2hex(random_bytes(3)));

                // 1. Insert Purchase Header
                Database::execute("
                    INSERT INTO purchases (
                        purchase_number, vendor_id, purchase_date, vendor_invoice_no,
                        total_amount, paid_amount, due_amount, payment_status,
                        notes, created_by, created_at, updated_at
                    ) VALUES (
                        :pnum, :vid, :pdate, :invno,
                        :tot, :paid, :due, :pstatus,
                        :notes, :cby, NOW(), NOW()
                    )
                ", [
                    ':pnum'    => $purchaseNumber,
                    ':vid'     => $vendorId,
                    ':pdate'   => $purchaseDate,
                    ':invno'   => $vendorInvoiceNo !== '' ? $vendorInvoiceNo : null,
                    ':tot'     => $totalAmount,
                    ':paid'    => $paidAmount,
                    ':due'     => $dueAmount,
                    ':pstatus' => $paymentStatus,
                    ':notes'   => $notes !== '' ? $notes : null,
                    ':cby'     => $operatorId,
                ]);

                $newPurchaseId = (int)Database::lastInsertId();
                if ($newPurchaseId === 0) {
                    $newPurchaseId = (int)Database::fetchColumn("SELECT id FROM purchases WHERE purchase_number = :pnum", [':pnum' => $purchaseNumber]);
                }

                // 2. Insert Purchase Items & Integrate with Inventory / Devices
                foreach ($validatedItems as $item) {
                    Database::execute("
                        INSERT INTO purchase_items (
                            purchase_id, product_id, item_type, quantity,
                            unit_cost, subtotal_cost, created_at
                        ) VALUES (
                            :pid, :prod_id, :itype, :qty,
                            :ucost, :scost, NOW()
                        )
                    ", [
                        ':pid'     => $newPurchaseId,
                        ':prod_id' => $item['product_id'],
                        ':itype'   => $item['item_type'],
                        ':qty'     => $item['quantity'],
                        ':ucost'   => $item['unit_cost'],
                        ':scost'   => $item['subtotal_cost'],
                    ]);

                    $purchaseItemId = (int)Database::lastInsertId();
                    if ($purchaseItemId === 0) {
                        $purchaseItemId = (int)Database::fetchColumn("SELECT MAX(id) FROM purchase_items WHERE purchase_id = :pid", [':pid' => $newPurchaseId]);
                    }

                    // A. Accessory: Update Inventory Stock & Movement Ledger
                    if ($item['item_type'] === 'ACCESSORY') {
                        $prodId = $item['product_id'];
                        $qty = $item['quantity'];
                        $unitCost = $item['unit_cost'];

                        // Check current inventory record
                        $stock = Database::fetchOne("
                            SELECT quantity, avg_unit_cost 
                            FROM inventory_stock 
                            WHERE product_id = :pid
                        ", [':pid' => $prodId]);

                        $oldQty = $stock ? (int)$stock['quantity'] : 0;
                        $oldAvgCost = $stock ? (string)$stock['avg_unit_cost'] : '0.00';

                        // Calculate weighted average cost
                        $newQty = $oldQty + $qty;
                        $newAvgCost = Financial::calculateWeightedAverageCost($oldQty, $oldAvgCost, $qty, $unitCost);

                        if ($stock) {
                            Database::execute("
                                UPDATE inventory_stock 
                                SET quantity = :nqty, avg_unit_cost = :ncost, last_restocked_at = NOW(), updated_at = NOW() 
                                WHERE product_id = :pid
                            ", [
                                ':nqty'  => $newQty,
                                ':ncost' => $newAvgCost,
                                ':pid'   => $prodId,
                            ]);
                        } else {
                            Database::execute("
                                INSERT INTO inventory_stock (product_id, quantity, avg_unit_cost, last_restocked_at, updated_at)
                                VALUES (:pid, :nqty, :ncost, NOW(), NOW())
                            ", [
                                ':pid'   => $prodId,
                                ':nqty'  => $newQty,
                                ':ncost' => $newAvgCost,
                            ]);
                        }

                        // Immutable double-entry stock movement
                        Database::execute("
                            INSERT INTO stock_movements (
                                product_id, movement_type, quantity_change,
                                quantity_before, quantity_after, unit_cost,
                                reference_table, reference_id, notes,
                                created_by, created_at
                            ) VALUES (
                                :pid, 'PURCHASE', :qchange,
                                :qbefore, :qafter, :ucost,
                                'purchases', :refid, :notes,
                                :cby, NOW()
                            )
                        ", [
                            ':pid'     => $prodId,
                            ':qchange' => $qty,
                            ':qbefore' => $oldQty,
                            ':qafter'  => $newQty,
                            ':ucost'   => $unitCost,
                            ':refid'   => $newPurchaseId,
                            ':notes'   => "Vendor consignment purchase [{$purchaseNumber}]",
                            ':cby'     => $operatorId,
                        ]);
                    }
                    // B. Serialized Phone: Register Device & Phone Episode
                    elseif ($item['item_type'] === 'SERIALIZED_PHONE') {
                        $prodId = $item['product_id'];
                        $product = $item['product'];
                        $unitCost = $item['unit_cost'];

                        foreach ($item['phones'] as $phoneData) {
                            // Insert physical device
                            Database::execute("
                                INSERT INTO devices (
                                    imei_1, imei_2, serial_number, brand, model_name, created_at, updated_at
                                ) VALUES (
                                    :i1, :i2, :sn, :brand, :model, NOW(), NOW()
                                )
                            ", [
                                ':i1'    => $phoneData['imei_1'],
                                ':i2'    => $phoneData['imei_2'],
                                ':sn'    => $phoneData['serial_number'],
                                ':brand' => $product['brand'],
                                ':model' => $product['model_name'],
                            ]);

                            $deviceId = (int)Database::lastInsertId();
                            if ($deviceId === 0) {
                                $deviceId = (int)Database::fetchColumn("SELECT id FROM devices WHERE imei_1 = :i1", [':i1' => $phoneData['imei_1']]);
                            }

                            // Insert commercial phone episode
                            Database::execute("
                                INSERT INTO phones (
                                    device_id, product_id, phone_type, ram, storage, color,
                                    device_condition, status, acquisition_source, vendor_id,
                                    purchase_item_id, purchase_date, purchase_cost,
                                    repair_cost, other_cost, total_cost_basis,
                                    expected_sale_price, created_by, created_at, updated_at
                                ) VALUES (
                                    :did, :pid, 'NEW', :ram, :storage, :color,
                                    'Brand New / Sealed Box', 'IN_STOCK', 'VENDOR_PURCHASE', :vid,
                                    :pi_id, :pdate, :pcost,
                                    0.00, 0.00, :tcost,
                                    :esale, :cby, NOW(), NOW()
                                )
                            ", [
                                ':did'    => $deviceId,
                                ':pid'    => $prodId,
                                ':ram'    => $phoneData['ram'],
                                ':storage'=> $phoneData['storage'],
                                ':color'  => $phoneData['color'],
                                ':vid'    => $vendorId,
                                ':pi_id'  => $purchaseItemId,
                                ':pdate'  => $purchaseDate,
                                ':pcost'  => $unitCost,
                                ':tcost'  => $unitCost,
                                ':esale'  => $product['default_sale_price'] ?? null,
                                ':cby'    => $operatorId,
                            ]);

                            $phoneId = (int)Database::lastInsertId();
                            if ($phoneId === 0) {
                                $phoneId = (int)Database::fetchColumn("SELECT MAX(id) FROM phones WHERE device_id = :did", [':did' => $deviceId]);
                            }

                            // Point device to active phone episode
                            Database::execute("UPDATE devices SET active_phone_id = :pid WHERE id = :did", [
                                ':pid' => $phoneId,
                                ':did' => $deviceId,
                            ]);
                        }
                    }
                }

                // 3. Record Initial Payment if made
                if ((float)$initialPaymentAmount > 0) {
                    $paymentNumber = 'PAY-' . date('Ymd', strtotime($purchaseDate)) . '-' . strtoupper(bin2hex(random_bytes(3)));

                    Database::execute("
                        INSERT INTO payments (
                            payment_number, transaction_type, reference_table,
                            reference_id, vendor_id, payment_date, amount,
                            payment_mode, transaction_ref, notes, received_by, created_at
                        ) VALUES (
                            :pnum, 'PURCHASE_PAYMENT', 'purchases',
                            :ref_id, :vid, :pdate, :amt,
                            :pmode, :txref, :notes, :rcv_by, NOW()
                        )
                    ", [
                        ':pnum'   => $paymentNumber,
                        ':ref_id' => $newPurchaseId,
                        ':vid'    => $vendorId,
                        ':pdate'  => $purchaseDate,
                        ':amt'    => $initialPaymentAmount,
                        ':pmode'  => $paymentMode,
                        ':txref'  => $transactionRef !== '' ? $transactionRef : null,
                        ':notes'  => "Initial payment upon consignment delivery [{$purchaseNumber}]",
                        ':rcv_by' => $operatorId,
                    ]);

                    $paymentId = (int)Database::lastInsertId();

                    AuditLogger::log(
                        'PURCHASE_PAYMENT_RECORDED',
                        'payments',
                        $paymentId,
                        null,
                        [
                            'purchase_id' => $newPurchaseId,
                            'amount'      => $initialPaymentAmount,
                            'mode'        => $paymentMode,
                        ],
                        "Recorded initial purchase payment of ₹{$initialPaymentAmount} for {$purchaseNumber}"
                    );
                }

                // 4. Audit Log Purchase Consignment
                AuditLogger::log(
                    'PURCHASE_CREATED',
                    'purchases',
                    $newPurchaseId,
                    null,
                    [
                        'purchase_number' => $purchaseNumber,
                        'vendor_id'       => $vendorId,
                        'items_count'     => count($validatedItems),
                        'total_amount'    => $totalAmount,
                        'payment_status'  => $paymentStatus,
                    ],
                    "Created purchase consignment {$purchaseNumber} (Total: ₹{$totalAmount})"
                );

                return $newPurchaseId;
            });

            View::flash('success', "Purchase consignment #{$purchaseId} has been successfully recorded into inventory.");
            Security::safeRedirect("/purchases/{$purchaseId}");
        } catch (\Throwable $e) {
            View::flash('danger', 'System error while recording purchase: ' . $e->getMessage());
            Security::safeRedirect('/purchases/create');
        }
    }

    /**
     * Show full purchase details, items, stock movements, and payment ledger.
     */
    public function show(array $params): void
    {
        Auth::requireLogin();

        $id = (int)($params['id'] ?? 0);
        $purchase = Database::fetchOne("
            SELECT p.*, v.business_name AS vendor_name, v.phone AS vendor_phone,
                   v.contact_person, v.gstin AS vendor_gstin, v.address AS vendor_address,
                   u.username AS operator_username, u.full_name AS operator_name
            FROM purchases p
            JOIN vendors v ON v.id = p.vendor_id
            LEFT JOIN users u ON u.id = p.created_by
            WHERE p.id = :id
        ", [':id' => $id]);

        if (!$purchase) {
            View::flash('danger', 'Purchase consignment record not found.');
            Security::safeRedirect('/purchases');
            return;
        }

        // Line items
        $items = Database::fetchAll("
            SELECT pi.*, p.brand, p.model_name, p.sku, c.name AS category_name
            FROM purchase_items pi
            JOIN products p ON p.id = pi.product_id
            JOIN categories c ON c.id = p.category_id
            WHERE pi.purchase_id = :id
            ORDER BY pi.id ASC
        ", [':id' => $id]);

        // Serialized phones registered under this purchase
        $phones = Database::fetchAll("
            SELECT ph.id AS phone_id, ph.phone_type, ph.status, ph.color, ph.ram, ph.storage,
                   d.imei_1, d.imei_2, d.brand, d.model_name
            FROM phones ph
            JOIN devices d ON d.id = ph.device_id
            JOIN purchase_items pi ON pi.id = ph.purchase_item_id
            WHERE pi.purchase_id = :id
            ORDER BY ph.id ASC
        ", [':id' => $id]);

        // Payments made against this purchase
        $payments = Database::fetchAll("
            SELECT pay.*, u.username AS received_by_username
            FROM payments pay
            LEFT JOIN users u ON u.id = pay.received_by
            WHERE pay.reference_table = 'purchases' AND pay.reference_id = :id
            ORDER BY pay.payment_date ASC, pay.id ASC
        ", [':id' => $id]);

        // Permissions
        $canManage = Authorization::hasPermission('manage_purchases');
        $canViewCost = Authorization::hasPermission('view_purchase_costs') || Authorization::hasPermission('view_vendor_costs');

        // Mask financial values if unprivileged
        if (!$canViewCost) {
            $purchase['total_amount'] = null;
            $purchase['paid_amount'] = null;
            $purchase['due_amount'] = null;

            foreach ($items as &$it) {
                $it['unit_cost'] = null;
                $it['subtotal_cost'] = null;
            }
            unset($it);

            foreach ($payments as &$pay) {
                $pay['amount'] = null;
            }
            unset($pay);
        }

        View::render('purchases/show', [
            'pageTitle'   => "Purchase: {$purchase['purchase_number']}",
            'purchase'    => $purchase,
            'items'       => $items,
            'phones'      => $phones,
            'payments'    => $payments,
            'canManage'   => $canManage,
            'canViewCost' => $canViewCost,
        ], 'main');
    }

    /**
     * Render the form to record a subsequent purchase payment.
     */
    public function showRecordPayment(array $params): void
    {
        Authorization::requirePermission('manage_purchases');

        $id = (int)($params['id'] ?? 0);
        $purchase = Database::fetchOne("
            SELECT p.*, v.business_name AS vendor_name 
            FROM purchases p
            JOIN vendors v ON v.id = p.vendor_id
            WHERE p.id = :id
        ", [':id' => $id]);

        if (!$purchase) {
            View::flash('danger', 'Purchase record not found.');
            Security::safeRedirect('/purchases');
            return;
        }

        if ((float)$purchase['due_amount'] <= 0.00 || $purchase['payment_status'] === 'PAID') {
            View::flash('info', "Purchase {$purchase['purchase_number']} is already fully settled. Outstanding balance is ₹0.00.");
            Security::safeRedirect("/purchases/{$id}");
            return;
        }

        View::render('purchases/record_payment', [
            'pageTitle' => "Record Payment: {$purchase['purchase_number']}",
            'purchase'  => $purchase,
            'formData'  => [
                'payment_date' => date('Y-m-d'),
                'amount'       => $purchase['due_amount'],
                'payment_mode' => 'BANK_TRANSFER',
            ],
        ], 'main');
    }

    /**
     * Process recording a purchase payment with overpayment defense and row-locking.
     */
    public function recordPayment(array $params): void
    {
        Authorization::requirePermission('manage_purchases');

        $id = (int)($params['id'] ?? 0);

        $csrfToken = $_POST['csrf_token'] ?? null;
        if (!Security::validateCsrfToken($csrfToken)) {
            View::flash('danger', 'Security verification failed (CSRF token missing or expired).');
            Security::safeRedirect("/purchases/{$id}");
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

        if (!is_numeric($amountRaw) || (float)$amountRaw <= 0) {
            $errors[] = 'Payment amount must be a positive decimal number greater than 0.00.';
        }

        if (!empty($errors)) {
            View::flash('danger', implode(' ', $errors));
            Security::safeRedirect("/purchases/{$id}/payments/create");
            return;
        }

        $paymentAmount = Financial::toDecimal((float)$amountRaw);
        $operatorId = Auth::id() ?? 1;

        try {
            Database::transaction(function() use (
                $id, $paymentDate, $paymentAmount, $paymentMode,
                $transactionRef, $notes, $operatorId
            ) {
                // 1. Lock purchase row for update
                $purchase = Database::fetchOne("
                    SELECT id, purchase_number, vendor_id, total_amount, paid_amount, due_amount, payment_status
                    FROM purchases 
                    WHERE id = :id 
                    FOR UPDATE
                ", [':id' => $id]);

                if (!$purchase) {
                    throw new \RuntimeException('Purchase record not found.');
                }

                // 2. Authoritative paid calculation from payments table
                $currentPaid = (string)Database::fetchColumn("
                    SELECT COALESCE(SUM(amount), 0.00) 
                    FROM payments 
                    WHERE reference_table = 'purchases' AND reference_id = :id AND transaction_type = 'PURCHASE_PAYMENT'
                ", [':id' => $id]);

                $totalAmount = (string)$purchase['total_amount'];
                $currentDue = Financial::subtract($totalAmount, $currentPaid);

                // 3. Overpayment defense
                if ((float)$paymentAmount > (float)$currentDue) {
                    throw new \RuntimeException("Payment amount (₹{$paymentAmount}) exceeds the current outstanding balance (₹{$currentDue}). Overpayment is prohibited.");
                }

                // 4. Insert payment record
                $paymentNumber = 'PAY-' . date('Ymd', strtotime($paymentDate)) . '-' . strtoupper(bin2hex(random_bytes(3)));

                Database::execute("
                    INSERT INTO payments (
                        payment_number, transaction_type, reference_table,
                        reference_id, vendor_id, payment_date, amount,
                        payment_mode, transaction_ref, notes, received_by, created_at
                    ) VALUES (
                        :pnum, 'PURCHASE_PAYMENT', 'purchases',
                        :ref_id, :vid, :pdate, :amt,
                        :pmode, :txref, :notes, :rcv_by, NOW()
                    )
                ", [
                    ':pnum'   => $paymentNumber,
                    ':ref_id' => $id,
                    ':vid'    => $purchase['vendor_id'],
                    ':pdate'  => $paymentDate,
                    ':amt'    => $paymentAmount,
                    ':pmode'  => $paymentMode,
                    ':txref'  => $transactionRef !== '' ? $transactionRef : null,
                    ':notes'  => $notes !== '' ? $notes : null,
                    ':rcv_by' => $operatorId,
                ]);

                $paymentId = (int)Database::lastInsertId();

                // 5. Recalculate purchase totals
                $newPaid = Financial::add($currentPaid, $paymentAmount);
                $newDue = Financial::subtract($totalAmount, $newPaid);

                $newStatus = 'UNPAID';
                if ((float)$newDue <= 0.00) {
                    $newStatus = 'PAID';
                    $newDue = '0.00';
                } elseif ((float)$newPaid > 0.00) {
                    $newStatus = 'PARTIAL';
                }

                Database::execute("
                    UPDATE purchases 
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
                    'PURCHASE_PAYMENT_RECORDED',
                    'payments',
                    $paymentId,
                    [
                        'previous_paid'   => $currentPaid,
                        'previous_status' => $purchase['payment_status'],
                    ],
                    [
                        'payment_number' => $paymentNumber,
                        'amount'         => $paymentAmount,
                        'new_paid'       => $newPaid,
                        'new_due'        => $newDue,
                        'new_status'     => $newStatus,
                    ],
                    "Recorded payment of ₹{$paymentAmount} for Purchase {$purchase['purchase_number']}"
                );
            });

            View::flash('success', "Payment of ₹{$paymentAmount} has been recorded successfully.");
            Security::safeRedirect("/purchases/{$id}");
        } catch (\Throwable $e) {
            View::flash('danger', 'Payment rejected: ' . $e->getMessage());
            Security::safeRedirect("/purchases/{$id}/payments/create");
        }
    }
}
