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
 * Serialized Mobile Phone Management Controller
 *
 * Handles new and second-hand mobile intake, physical device tracking,
 * commercial ownership episodes, IMEI validation, Aadhaar security,
 * and direct refurbishment cost accounting.
 */
class PhoneController
{
    /**
     * List serialized mobile inventory with filtering and pagination.
     */
    public function index(array|string $filterType = 'ALL'): void
    {
        Auth::requireLogin();

        $selectedType = is_array($filterType) ? 'ALL' : $filterType;
        $selectedType = strtoupper(trim($_GET['type'] ?? $selectedType));
        if (!in_array($selectedType, ['ALL', 'NEW', 'SECOND_HAND'], true)) {
            $selectedType = 'ALL';
        }

        $status = strtoupper(trim($_GET['status'] ?? 'ALL'));
        if (!in_array($status, ['ALL', 'IN_STOCK', 'SOLD', 'RETURNED_TO_VENDOR', 'SCRAPPED'], true)) {
            $status = 'ALL';
        }

        $search = trim($_GET['q'] ?? '');
        $page = max(1, (int)($_GET['page'] ?? 1));
        $perPage = 15;
        $offset = ($page - 1) * $perPage;

        // Build query conditions
        $whereClauses = [];
        $params = [];

        if ($selectedType !== 'ALL') {
            $whereClauses[] = "ph.phone_type = :type";
            $params[':type'] = $selectedType;
        }

        if ($status !== 'ALL') {
            $whereClauses[] = "ph.status = :status";
            $params[':status'] = $status;
        }

        if ($search !== '') {
            $whereClauses[] = "(
                d.imei_1 LIKE :q1 OR 
                d.imei_2 LIKE :q2 OR 
                d.serial_number LIKE :q3 OR 
                d.brand LIKE :q4 OR 
                d.model_name LIKE :q5 OR 
                ph.seller_name LIKE :q6 OR 
                ph.seller_phone LIKE :q7
            )";
            $qWild = '%' . $search . '%';
            $params[':q1'] = $qWild;
            $params[':q2'] = $qWild;
            $params[':q3'] = $qWild;
            $params[':q4'] = $qWild;
            $params[':q5'] = $qWild;
            $params[':q6'] = $qWild;
            $params[':q7'] = $qWild;
        }

        $whereSql = !empty($whereClauses) ? "WHERE " . implode(" AND ", $whereClauses) : "";

        // Total count for pagination
        $countSql = "
            SELECT COUNT(*) 
            FROM phones ph
            JOIN devices d ON d.id = ph.device_id
            JOIN products p ON p.id = ph.product_id
            {$whereSql}
        ";
        $totalItems = (int)Database::fetchColumn($countSql, $params);
        $totalPages = max(1, (int)ceil($totalItems / $perPage));

        // Retrieve paginated records
        $sql = "
            SELECT ph.id, ph.device_id, ph.product_id, ph.phone_type, ph.ram, ph.storage,
                   ph.color, ph.device_condition, ph.status, ph.acquisition_source,
                   ph.seller_name, ph.seller_phone, ph.seller_id_number_masked,
                   ph.purchase_date, ph.purchase_cost, ph.repair_cost, ph.other_cost,
                   ph.total_cost_basis, ph.expected_sale_price, ph.created_at,
                   d.imei_1, d.imei_2, d.serial_number, d.brand, d.model_name,
                   d.active_phone_id,
                   u.username AS operator_username
            FROM phones ph
            JOIN devices d ON d.id = ph.device_id
            JOIN products p ON p.id = ph.product_id
            LEFT JOIN users u ON u.id = ph.created_by
            {$whereSql}
            ORDER BY ph.id DESC
            LIMIT {$perPage} OFFSET {$offset}
        ";
        $phones = Database::fetchAll($sql, $params);

        // Permissions
        $canViewCost = Authorization::hasPermission('view_purchase_costs');
        $canIntakeNew = Authorization::hasPermission('manage_inventory');
        $canIntakeUsed = Authorization::hasPermission('buy_used_phones') || Authorization::hasPermission('manage_inventory');

        // Mask costs if user does not have permission
        if (!$canViewCost) {
            foreach ($phones as &$p) {
                $p['purchase_cost'] = null;
                $p['repair_cost'] = null;
                $p['other_cost'] = null;
                $p['total_cost_basis'] = null;
            }
            unset($p);
        }

        View::render('phones/index', [
            'pageTitle'     => $selectedType === 'NEW' ? 'New Mobiles Inventory' : ($selectedType === 'SECOND_HAND' ? 'Second-Hand Mobiles' : 'All Mobile Phones'),
            'phones'        => $phones,
            'selectedType'  => $selectedType,
            'selectedStatus'=> $status,
            'search'        => $search,
            'currentPage'   => $page,
            'totalPages'    => $totalPages,
            'totalItems'    => $totalItems,
            'canViewCost'   => $canViewCost,
            'canIntakeNew'  => $canIntakeNew,
            'canIntakeUsed' => $canIntakeUsed,
        ], 'main');
    }

    /**
     * Dedicated entry point for New Mobiles tab.
     */
    public function indexNew(): void
    {
        $this->index('NEW');
    }

    /**
     * Dedicated entry point for Second-Hand Mobiles tab.
     */
    public function indexUsed(): void
    {
        $this->index('SECOND_HAND');
    }

    /**
     * Render the intake form for a new mobile phone.
     */
    public function createNew(): void
    {
        Authorization::requirePermission('manage_inventory');

        $products = Database::fetchAll("
            SELECT id, brand, model_name, default_sale_price 
            FROM products 
            WHERE item_type = 'SERIALIZED_PHONE' AND is_active = 1 
            ORDER BY brand ASC, model_name ASC
        ");

        View::render('phones/create_new', [
            'pageTitle'   => 'New Mobile Intake',
            'products'    => $products,
            'formData'    => [
                'purchase_date' => date('Y-m-d'),
                'ram'           => '',
                'storage'       => '',
                'color'         => '',
            ],
            'canViewCost' => Authorization::hasPermission('view_purchase_costs'),
        ], 'main');
    }

    /**
     * Process new mobile intake.
     */
    public function storeNew(): void
    {
        Authorization::requirePermission('manage_inventory');

        $csrfToken = $_POST['csrf_token'] ?? null;
        if (!Security::validateCsrfToken($csrfToken)) {
            View::flash('danger', 'Security verification failed (CSRF token missing or expired). Please try again.');
            Security::safeRedirect('/phones/create-new');
            return;
        }

        $productId         = (int)($_POST['product_id'] ?? 0);
        $imei1             = trim($_POST['imei_1'] ?? '');
        $imei2             = trim($_POST['imei_2'] ?? '');
        $serialNumber      = trim($_POST['serial_number'] ?? '');
        $ram               = trim($_POST['ram'] ?? '');
        $storage           = trim($_POST['storage'] ?? '');
        $color             = trim($_POST['color'] ?? '');
        $purchaseDate      = trim($_POST['purchase_date'] ?? date('Y-m-d'));
        $purchaseCostRaw   = trim($_POST['purchase_cost'] ?? '');
        $expectedSalePrice = isset($_POST['expected_sale_price']) && $_POST['expected_sale_price'] !== '' ? trim($_POST['expected_sale_price']) : null;

        $errors = [];

        // Validate product
        $product = Database::fetchOne("
            SELECT id, brand, model_name, item_type 
            FROM products 
            WHERE id = :id AND is_active = 1
        ", [':id' => $productId]);

        if (!$product || $product['item_type'] !== 'SERIALIZED_PHONE') {
            $errors[] = 'Please select a valid serialized mobile phone model from the catalog.';
        }

        // Validate IMEI 1
        if ($imei1 === '') {
            $errors[] = 'IMEI 1 is strictly mandatory.';
        } elseif (!Security::validateImei($imei1, true)) {
            $errors[] = 'IMEI 1 must be exactly 15 numeric digits and pass Luhn check-digit verification.';
        }

        // Validate IMEI 2 (optional)
        if ($imei2 !== '') {
            if (!Security::validateImei($imei2, true)) {
                $errors[] = 'IMEI 2 must be exactly 15 numeric digits and pass Luhn check-digit verification.';
            } elseif ($imei1 === $imei2) {
                $errors[] = 'IMEI 2 cannot be identical to IMEI 1.';
            }
        } else {
            $imei2 = null;
        }

        // Validate purchase date
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $purchaseDate)) {
            $errors[] = 'Purchase date must be in YYYY-MM-DD format.';
        }

        // Validate financial cost
        if (!is_numeric($purchaseCostRaw) || (float)$purchaseCostRaw < 0) {
            $errors[] = 'Purchase cost must be a non-negative decimal amount.';
        } else {
            $purchaseCost = Financial::format((string)(float)$purchaseCostRaw);
        }

        // Validate expected sale price
        $formattedExpectedSale = null;
        if ($expectedSalePrice !== null) {
            if (!is_numeric($expectedSalePrice) || (float)$expectedSalePrice < 0) {
                $errors[] = 'Expected sale price cannot be negative.';
            } else {
                $formattedExpectedSale = Financial::format((string)(float)$expectedSalePrice);
            }
        }

        // Cross-field IMEI uniqueness checks
        if (empty($errors)) {
            $deviceConflict = $this->checkImeiConflicts($imei1, $imei2);
            if ($deviceConflict !== null) {
                $errors[] = $deviceConflict;
            }
        }

        if (!empty($errors)) {
            View::flash('danger', implode(' ', $errors));
            $products = Database::fetchAll("
                SELECT id, brand, model_name, default_sale_price 
                FROM products 
                WHERE item_type = 'SERIALIZED_PHONE' AND is_active = 1 
                ORDER BY brand ASC, model_name ASC
            ");
            View::render('phones/create_new', [
                'pageTitle'   => 'New Mobile Intake',
                'products'    => $products,
                'formData'    => [
                    'product_id'          => $productId,
                    'imei_1'              => $imei1,
                    'imei_2'              => $imei2,
                    'serial_number'       => $serialNumber,
                    'ram'                 => $ram,
                    'storage'             => $storage,
                    'color'               => $color,
                    'purchase_date'       => $purchaseDate,
                    'purchase_cost'       => $purchaseCostRaw,
                    'expected_sale_price' => $expectedSalePrice,
                ],
                'canViewCost' => Authorization::hasPermission('view_purchase_costs'),
            ], 'main');
            return;
        }

        $operatorId = Auth::id() ?? 1;

        // Perform atomic intake transaction
        try {
            $phoneId = Database::transaction(function() use (
                $product, $productId, $imei1, $imei2, $serialNumber,
                $ram, $storage, $color, $purchaseDate, $purchaseCost,
                $formattedExpectedSale, $operatorId
            ) {
                // 1. Insert physical device record
                Database::execute("
                    INSERT INTO devices (imei_1, imei_2, serial_number, brand, model_name, created_at, updated_at)
                    VALUES (:i1, :i2, :sn, :brand, :model, NOW(), NOW())
                ", [
                    ':i1'    => $imei1,
                    ':i2'    => $imei2,
                    ':sn'    => $serialNumber !== '' ? $serialNumber : null,
                    ':brand' => $product['brand'],
                    ':model' => $product['model_name'],
                ]);

                $deviceId = (int)Database::lastInsertId();
                if ($deviceId === 0) {
                    $deviceId = (int)Database::fetchColumn("SELECT id FROM devices WHERE imei_1 = :i1", [':i1' => $imei1]);
                }

                // 2. Insert commercial phone episode
                Database::execute("
                    INSERT INTO phones (
                        device_id, product_id, phone_type, ram, storage, color,
                        device_condition, status, acquisition_source,
                        purchase_date, purchase_cost, repair_cost, other_cost,
                        total_cost_basis, expected_sale_price, created_by,
                        created_at, updated_at
                    ) VALUES (
                        :dev_id, :prod_id, 'NEW', :ram, :storage, :color,
                        'Brand New / Sealed Box', 'IN_STOCK', 'VENDOR_PURCHASE',
                        :pdate, :pcost, 0.00, 0.00,
                        :tcost, :expected_sale, :cby,
                        NOW(), NOW()
                    )
                ", [
                    ':dev_id'        => $deviceId,
                    ':prod_id'       => $productId,
                    ':ram'           => $ram !== '' ? $ram : null,
                    ':storage'       => $storage !== '' ? $storage : null,
                    ':color'         => $color !== '' ? $color : null,
                    ':pdate'         => $purchaseDate,
                    ':pcost'         => $purchaseCost,
                    ':tcost'         => $purchaseCost,
                    ':expected_sale' => $formattedExpectedSale,
                    ':cby'           => $operatorId,
                ]);

                $newPhoneId = (int)Database::lastInsertId();
                if ($newPhoneId === 0) {
                    $newPhoneId = (int)Database::fetchColumn("SELECT MAX(id) FROM phones WHERE device_id = :did", [':did' => $deviceId]);
                }

                // 3. Link active phone episode to device
                Database::execute("
                    UPDATE devices 
                    SET active_phone_id = :pid, updated_at = NOW() 
                    WHERE id = :did
                ", [
                    ':pid' => $newPhoneId,
                    ':did' => $deviceId,
                ]);

                // 4. Audit Log
                AuditLogger::log(
                    'NEW_PHONE_INTAKE',
                    'phones',
                    $newPhoneId,
                    null,
                    [
                        'device_id'   => $deviceId,
                        'product_id'  => $productId,
                        'brand'       => $product['brand'],
                        'model_name'  => $product['model_name'],
                        'imei_1'      => $imei1,
                        'imei_2'      => $imei2,
                        'phone_type'  => 'NEW',
                    ],
                    "Intake new mobile: {$product['brand']} {$product['model_name']} (IMEI: {$imei1})"
                );

                return $newPhoneId;
            });

            View::flash('success', "New mobile phone [{$product['brand']} {$product['model_name']}] successfully received into stock (Episode #{$phoneId}).");
            Security::safeRedirect("/phones/{$phoneId}");
        } catch (\Throwable $e) {
            View::flash('danger', 'System error while recording mobile intake: ' . $e->getMessage());
            Security::safeRedirect('/phones/create-new');
        }
    }

    /**
     * Render the intake form for a second-hand mobile phone.
     */
    public function createUsed(): void
    {
        $hasPerm = Authorization::hasPermission('buy_used_phones') || Authorization::hasPermission('manage_inventory');
        if (!$hasPerm) {
            Authorization::requirePermission('buy_used_phones');
        }

        $products = Database::fetchAll("
            SELECT id, brand, model_name, default_sale_price 
            FROM products 
            WHERE item_type = 'SERIALIZED_PHONE' AND is_active = 1 
            ORDER BY brand ASC, model_name ASC
        ");

        View::render('phones/create_used', [
            'pageTitle'   => 'Second-Hand Mobile Intake',
            'products'    => $products,
            'formData'    => [
                'purchase_date'  => date('Y-m-d'),
                'seller_id_type' => 'AADHAAR',
            ],
            'canViewCost' => Authorization::hasPermission('view_purchase_costs'),
        ], 'main');
    }

    /**
     * Process second-hand mobile intake with seller identity & Aadhaar privacy.
     */
    public function storeUsed(): void
    {
        $hasPerm = Authorization::hasPermission('buy_used_phones') || Authorization::hasPermission('manage_inventory');
        if (!$hasPerm) {
            Authorization::requirePermission('buy_used_phones');
        }

        $csrfToken = $_POST['csrf_token'] ?? null;
        if (!Security::validateCsrfToken($csrfToken)) {
            View::flash('danger', 'Security verification failed (CSRF token missing or expired). Please try again.');
            Security::safeRedirect('/phones/create-used');
            return;
        }

        $productId         = (int)($_POST['product_id'] ?? 0);
        $imei1             = trim($_POST['imei_1'] ?? '');
        $imei2             = trim($_POST['imei_2'] ?? '');
        $serialNumber      = trim($_POST['serial_number'] ?? '');
        $ram               = trim($_POST['ram'] ?? '');
        $storage           = trim($_POST['storage'] ?? '');
        $color             = trim($_POST['color'] ?? '');
        $deviceCondition   = trim($_POST['device_condition'] ?? '');
        $sellerName        = trim($_POST['seller_name'] ?? '');
        $sellerPhone       = trim($_POST['seller_phone'] ?? '');
        $sellerIdType      = strtoupper(trim($_POST['seller_id_type'] ?? 'AADHAAR'));
        $rawIdNumber       = trim($_POST['seller_id_number'] ?? '');
        $purchaseDate      = trim($_POST['purchase_date'] ?? date('Y-m-d'));
        $purchaseCostRaw   = trim($_POST['purchase_cost'] ?? '');
        $expectedSalePrice = isset($_POST['expected_sale_price']) && $_POST['expected_sale_price'] !== '' ? trim($_POST['expected_sale_price']) : null;

        $errors = [];

        // Validate product
        $product = Database::fetchOne("
            SELECT id, brand, model_name, item_type 
            FROM products 
            WHERE id = :id AND is_active = 1
        ", [':id' => $productId]);

        if (!$product || $product['item_type'] !== 'SERIALIZED_PHONE') {
            $errors[] = 'Please select a valid serialized mobile phone model from the catalog.';
        }

        // Validate IMEI 1
        if ($imei1 === '') {
            $errors[] = 'IMEI 1 is strictly mandatory.';
        } elseif (!Security::validateImei($imei1, true)) {
            $errors[] = 'IMEI 1 must be exactly 15 numeric digits and pass Luhn check-digit verification.';
        }

        // Validate IMEI 2 (optional)
        if ($imei2 !== '') {
            if (!Security::validateImei($imei2, true)) {
                $errors[] = 'IMEI 2 must be exactly 15 numeric digits and pass Luhn check-digit verification.';
            } elseif ($imei1 === $imei2) {
                $errors[] = 'IMEI 2 cannot be identical to IMEI 1.';
            }
        } else {
            $imei2 = null;
        }

        // Validate device condition
        if ($deviceCondition === '') {
            $errors[] = 'Device condition / cosmetic assessment description is required.';
        }

        // Validate seller information
        if ($sellerName === '' || strlen($sellerName) > 100) {
            $errors[] = 'Seller full name is required (up to 100 characters).';
        }

        if (!Security::validatePhone($sellerPhone)) {
            $errors[] = 'Valid seller contact phone number (10 to 15 digits) is required.';
        }

        // Validate Seller ID / Aadhaar
        $cleanIdDigits = preg_replace('/[^0-9]/', '', $rawIdNumber);
        $aadhaarLast4 = null;
        $aadhaarHash = null;
        $aadhaarEncrypted = null;
        $idNumberMasked = null;

        if ($sellerIdType === 'AADHAAR') {
            if (strlen($cleanIdDigits) !== 12) {
                $errors[] = 'Aadhaar number must contain exactly 12 numeric digits.';
            } else {
                $aadhaarLast4 = substr($cleanIdDigits, -4);
                $aadhaarHash = Security::hashAadhaar($cleanIdDigits);
                $aadhaarEncrypted = Security::encryptAadhaar($cleanIdDigits);
                $idNumberMasked = Security::maskAadhaar($cleanIdDigits);
            }
        } else {
            if ($rawIdNumber === '') {
                $errors[] = 'Seller identification document number is required.';
            } else {
                $idNumberMasked = Security::maskAadhaar($rawIdNumber);
            }
        }

        // Validate purchase date
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $purchaseDate)) {
            $errors[] = 'Purchase date must be in YYYY-MM-DD format.';
        }

        // Validate financial cost
        if (!is_numeric($purchaseCostRaw) || (float)$purchaseCostRaw < 0) {
            $errors[] = 'Purchase cost must be a non-negative decimal amount.';
        } else {
            $purchaseCost = Financial::format((string)(float)$purchaseCostRaw);
        }

        // Validate expected sale price
        $formattedExpectedSale = null;
        if ($expectedSalePrice !== null) {
            if (!is_numeric($expectedSalePrice) || (float)$expectedSalePrice < 0) {
                $errors[] = 'Expected sale price cannot be negative.';
            } else {
                $formattedExpectedSale = Financial::format((string)(float)$expectedSalePrice);
            }
        }

        // Check IMEI existence & handling for second-hand phone
        $existingDevice = null;
        if (empty($errors)) {
            // Find if this physical device already exists
            $existingDevice = $this->findExistingDevice($imei1, $imei2);

            if ($existingDevice) {
                // If it already has an active phone in IN_STOCK status, reject!
                if (!empty($existingDevice['active_phone_id'])) {
                    $activePhone = Database::fetchOne("
                        SELECT id, status 
                        FROM phones 
                        WHERE id = :id
                    ", [':id' => $existingDevice['active_phone_id']]);

                    if ($activePhone && $activePhone['status'] === 'IN_STOCK') {
                        $errors[] = "Device with IMEI {$imei1} is already active in stock (Episode #{$activePhone['id']}). Duplicate intake is prohibited.";
                    }
                }
            } else {
                // Check if individual IMEIs conflict across different devices
                $deviceConflict = $this->checkImeiConflicts($imei1, $imei2);
                if ($deviceConflict !== null) {
                    $errors[] = $deviceConflict;
                }
            }
        }

        if (!empty($errors)) {
            View::flash('danger', implode(' ', $errors));
            $products = Database::fetchAll("
                SELECT id, brand, model_name, default_sale_price 
                FROM products 
                WHERE item_type = 'SERIALIZED_PHONE' AND is_active = 1 
                ORDER BY brand ASC, model_name ASC
            ");
            View::render('phones/create_used', [
                'pageTitle'   => 'Second-Hand Mobile Intake',
                'products'    => $products,
                'formData'    => [
                    'product_id'          => $productId,
                    'imei_1'              => $imei1,
                    'imei_2'              => $imei2,
                    'serial_number'       => $serialNumber,
                    'ram'                 => $ram,
                    'storage'             => $storage,
                    'color'               => $color,
                    'device_condition'    => $deviceCondition,
                    'seller_name'         => $sellerName,
                    'seller_phone'        => $sellerPhone,
                    'seller_id_type'      => $sellerIdType,
                    'seller_id_number'    => '', // NEVER retain plaintext Aadhaar in view
                    'purchase_date'       => $purchaseDate,
                    'purchase_cost'       => $purchaseCostRaw,
                    'expected_sale_price' => $expectedSalePrice,
                ],
                'canViewCost' => Authorization::hasPermission('view_purchase_costs'),
            ], 'main');
            return;
        }

        $operatorId = Auth::id() ?? 1;

        // Perform atomic second-hand intake transaction
        try {
            $phoneId = Database::transaction(function() use (
                $product, $productId, $existingDevice, $imei1, $imei2,
                $serialNumber, $ram, $storage, $color, $deviceCondition,
                $sellerName, $sellerPhone, $sellerIdType, $aadhaarLast4,
                $aadhaarHash, $aadhaarEncrypted, $idNumberMasked,
                $purchaseDate, $purchaseCost, $formattedExpectedSale, $operatorId
            ) {
                // 1. Resolve or create physical device record
                if ($existingDevice) {
                    $deviceId = (int)$existingDevice['id'];
                    // Update optional specs if not set
                    Database::execute("
                        UPDATE devices 
                        SET brand = :b, model_name = :m, updated_at = NOW() 
                        WHERE id = :id
                    ", [
                        ':b'  => $product['brand'],
                        ':m'  => $product['model_name'],
                        ':id' => $deviceId,
                    ]);
                } else {
                    Database::execute("
                        INSERT INTO devices (imei_1, imei_2, serial_number, brand, model_name, created_at, updated_at)
                        VALUES (:i1, :i2, :sn, :brand, :model, NOW(), NOW())
                    ", [
                        ':i1'    => $imei1,
                        ':i2'    => $imei2,
                        ':sn'    => $serialNumber !== '' ? $serialNumber : null,
                        ':brand' => $product['brand'],
                        ':model' => $product['model_name'],
                    ]);

                    $deviceId = (int)Database::lastInsertId();
                    if ($deviceId === 0) {
                        $deviceId = (int)Database::fetchColumn("SELECT id FROM devices WHERE imei_1 = :i1", [':i1' => $imei1]);
                    }
                }

                // 2. Insert new commercial phone episode
                Database::execute("
                    INSERT INTO phones (
                        device_id, product_id, phone_type, ram, storage, color,
                        device_condition, status, acquisition_source,
                        seller_name, seller_phone, seller_id_type,
                        seller_aadhaar_last4, seller_aadhaar_hash, seller_aadhaar_encrypted,
                        seller_id_number_masked, purchase_date, purchase_cost,
                        repair_cost, other_cost, total_cost_basis,
                        expected_sale_price, created_by, created_at, updated_at
                    ) VALUES (
                        :dev_id, :prod_id, 'SECOND_HAND', :ram, :storage, :color,
                        :cond, 'IN_STOCK', 'CUSTOMER_BUYBACK',
                        :sname, :sphone, :sid_type,
                        :slast4, :shash, :senc,
                        :smasked, :pdate, :pcost,
                        0.00, 0.00, :tcost,
                        :expected_sale, :cby, NOW(), NOW()
                    )
                ", [
                    ':dev_id'        => $deviceId,
                    ':prod_id'       => $productId,
                    ':ram'           => $ram !== '' ? $ram : null,
                    ':storage'       => $storage !== '' ? $storage : null,
                    ':color'         => $color !== '' ? $color : null,
                    ':cond'          => $deviceCondition,
                    ':sname'         => $sellerName,
                    ':sphone'        => $sellerPhone,
                    ':sid_type'      => $sellerIdType,
                    ':slast4'        => $aadhaarLast4,
                    ':shash'         => $aadhaarHash,
                    ':senc'          => $aadhaarEncrypted,
                    ':smasked'       => $idNumberMasked,
                    ':pdate'         => $purchaseDate,
                    ':pcost'         => $purchaseCost,
                    ':tcost'         => $purchaseCost,
                    ':expected_sale' => $formattedExpectedSale,
                    ':cby'           => $operatorId,
                ]);

                $newPhoneId = (int)Database::lastInsertId();
                if ($newPhoneId === 0) {
                    $newPhoneId = (int)Database::fetchColumn("SELECT MAX(id) FROM phones WHERE device_id = :did", [':did' => $deviceId]);
                }

                // 3. Point physical device's active episode to newly created episode
                Database::execute("
                    UPDATE devices 
                    SET active_phone_id = :pid, updated_at = NOW() 
                    WHERE id = :did
                ", [
                    ':pid' => $newPhoneId,
                    ':did' => $deviceId,
                ]);

                // 4. Audit Log (Plaintext Aadhaar is strictly excluded)
                AuditLogger::log(
                    'USED_PHONE_INTAKE',
                    'phones',
                    $newPhoneId,
                    null,
                    [
                        'device_id'       => $deviceId,
                        'product_id'      => $productId,
                        'brand'           => $product['brand'],
                        'model_name'      => $product['model_name'],
                        'imei_1'          => $imei1,
                        'imei_2'          => $imei2,
                        'phone_type'      => 'SECOND_HAND',
                        'seller_name'     => $sellerName,
                        'seller_phone'    => $sellerPhone,
                        'seller_id_type'  => $sellerIdType,
                        'seller_id_masked'=> $idNumberMasked,
                    ],
                    "Intake second-hand mobile from walk-in seller {$sellerName}: {$product['brand']} {$product['model_name']} (IMEI: {$imei1})"
                );

                return $newPhoneId;
            });

            View::flash('success', "Second-hand mobile phone [{$product['brand']} {$product['model_name']}] successfully purchased and logged into inventory (Episode #{$phoneId}).");
            Security::safeRedirect("/phones/{$phoneId}");
        } catch (\Throwable $e) {
            View::flash('danger', 'System error while recording second-hand phone intake: ' . $e->getMessage());
            Security::safeRedirect('/phones/create-used');
        }
    }

    /**
     * Show full commercial episode detail for a serialized phone.
     */
    public function show(array $params): void
    {
        Auth::requireLogin();

        $id = (int)($params['id'] ?? 0);
        $phone = Database::fetchOne("
            SELECT ph.*, 
                   d.imei_1, d.imei_2, d.serial_number, d.brand, d.model_name,
                   d.active_phone_id,
                   p.sku, c.name AS category_name,
                   u.username AS operator_username, u.full_name AS operator_name
            FROM phones ph
            JOIN devices d ON d.id = ph.device_id
            JOIN products p ON p.id = ph.product_id
            JOIN categories c ON c.id = p.category_id
            LEFT JOIN users u ON u.id = ph.created_by
            WHERE ph.id = :id
        ", [':id' => $id]);

        if (!$phone) {
            View::flash('danger', 'Mobile phone record not found.');
            Security::safeRedirect('/phones');
            return;
        }

        // Direct costs ledger
        $directCosts = Database::fetchAll("
            SELECT pdc.*, u.username AS added_by_username
            FROM phone_direct_costs pdc
            LEFT JOIN users u ON u.id = pdc.created_by
            WHERE pdc.phone_id = :pid
            ORDER BY pdc.cost_date DESC, pdc.id DESC
        ", [':pid' => $id]);

        // Commercial history of the physical device (past episodes)
        $episodes = Database::fetchAll("
            SELECT ph.id, ph.phone_type, ph.status, ph.acquisition_source,
                   ph.purchase_date, ph.sold_at, ph.purchase_cost, ph.total_cost_basis,
                   ph.expected_sale_price, ph.seller_name,
                   u.username AS operator_username
            FROM phones ph
            LEFT JOIN users u ON u.id = ph.created_by
            WHERE ph.device_id = :did
            ORDER BY ph.id DESC
        ", [':did' => $phone['device_id']]);

        // Permissions
        $canViewCost = Authorization::hasPermission('view_purchase_costs');
        $canViewSensitiveId = Authorization::hasPermission('view_sensitive_id');
        $canAddCost = Authorization::hasPermission('manage_repairs') || Authorization::hasPermission('manage_inventory') || Auth::isOwner();

        // Server-side cost suppression if unprivileged
        if (!$canViewCost) {
            $phone['purchase_cost'] = null;
            $phone['repair_cost'] = null;
            $phone['other_cost'] = null;
            $phone['total_cost_basis'] = null;

            foreach ($directCosts as &$dc) {
                $dc['amount'] = null;
            }
            unset($dc);

            foreach ($episodes as &$ep) {
                $ep['purchase_cost'] = null;
                $ep['total_cost_basis'] = null;
            }
            unset($ep);
        }

        View::render('phones/show', [
            'pageTitle'          => "{$phone['brand']} {$phone['model_name']} (#{$phone['id']})",
            'phone'              => $phone,
            'directCosts'        => $directCosts,
            'episodes'           => $episodes,
            'canViewCost'        => $canViewCost,
            'canViewSensitiveId' => $canViewSensitiveId,
            'canAddCost'         => $canAddCost,
        ], 'main');
    }

    /**
     * Controlled decryption endpoint for sensitive Aadhaar ID.
     */
    public function revealAadhaar(array $params): void
    {
        Authorization::requirePermission('view_sensitive_id');

        $id = (int)($params['id'] ?? 0);
        $phone = Database::fetchOne("
            SELECT id, seller_name, seller_phone, seller_aadhaar_encrypted, seller_id_number_masked
            FROM phones 
            WHERE id = :id
        ", [':id' => $id]);

        if (!$phone) {
            $this->jsonResponse(['success' => false, 'error' => 'Phone record not found.'], 404);
            return;
        }

        $csrfToken = $_POST['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? null;
        if (!Security::validateCsrfToken($csrfToken)) {
            $this->jsonResponse(['success' => false, 'error' => 'CSRF verification failed.'], 403);
            return;
        }

        if (empty($phone['seller_aadhaar_encrypted'])) {
            $this->jsonResponse(['success' => false, 'error' => 'No encrypted Aadhaar payload exists for this record.'], 400);
            return;
        }

        $decrypted = Security::decryptAadhaar($phone['seller_aadhaar_encrypted']);
        if ($decrypted === null) {
            $this->jsonResponse(['success' => false, 'error' => 'Decryption failed: cryptographic payload corrupted or invalid key.'], 500);
            return;
        }

        // Format Aadhaar with spaces (XXXX XXXX XXXX)
        $formatted = trim(chunk_split($decrypted, 4, ' '));

        // Audit Log sensitive ID access
        AuditLogger::log(
            'SENSITIVE_ID_VIEWED',
            'phones',
            $id,
            null,
            ['seller_name' => $phone['seller_name'], 'action' => 'REVEAL_AADHAAR'],
            "Operator revealed Aadhaar number for seller {$phone['seller_name']} on Phone Episode #{$id}"
        );

        $this->jsonResponse([
            'success'   => true,
            'aadhaar'   => $formatted,
            'timestamp' => date('Y-m-d H:i:s'),
        ]);
    }

    /**
     * Render the form to add a direct cost to a phone episode.
     */
    public function showAddCost(array $params): void
    {
        $hasPerm = Authorization::hasPermission('manage_repairs') || Authorization::hasPermission('manage_inventory') || Auth::isOwner();
        if (!$hasPerm) {
            Authorization::requirePermission('manage_repairs');
        }

        $id = (int)($params['id'] ?? 0);
        $phone = Database::fetchOne("
            SELECT ph.id, ph.status, ph.total_cost_basis, ph.repair_cost, ph.other_cost,
                   d.brand, d.model_name, d.imei_1
            FROM phones ph
            JOIN devices d ON d.id = ph.device_id
            WHERE ph.id = :id
        ", [':id' => $id]);

        if (!$phone) {
            View::flash('danger', 'Phone record not found.');
            Security::safeRedirect('/phones');
            return;
        }

        if ($phone['status'] !== 'IN_STOCK') {
            View::flash('danger', "Direct costs can only be added to phones that are currently IN_STOCK (Current status: {$phone['status']}).");
            Security::safeRedirect("/phones/{$id}");
            return;
        }

        View::render('phones/add_cost', [
            'pageTitle'   => "Add Direct Cost: {$phone['brand']} {$phone['model_name']}",
            'phone'       => $phone,
            'formData'    => [
                'cost_date' => date('Y-m-d'),
                'cost_type' => 'DISPLAY',
            ],
            'canViewCost' => Authorization::hasPermission('view_purchase_costs'),
        ], 'main');
    }

    /**
     * Process adding a direct cost and atomically update the phone's cost basis.
     */
    public function addCost(array $params): void
    {
        $hasPerm = Authorization::hasPermission('manage_repairs') || Authorization::hasPermission('manage_inventory') || Auth::isOwner();
        if (!$hasPerm) {
            Authorization::requirePermission('manage_repairs');
        }

        $id = (int)($params['id'] ?? 0);
        $phone = Database::fetchOne("
            SELECT ph.id, ph.status, ph.purchase_cost, ph.repair_cost, ph.other_cost, ph.total_cost_basis,
                   d.brand, d.model_name, d.imei_1
            FROM phones ph
            JOIN devices d ON d.id = ph.device_id
            WHERE ph.id = :id
        ", [':id' => $id]);

        if (!$phone) {
            View::flash('danger', 'Phone record not found.');
            Security::safeRedirect('/phones');
            return;
        }

        if ($phone['status'] !== 'IN_STOCK') {
            View::flash('danger', "Direct costs can only be added to phones that are currently IN_STOCK.");
            Security::safeRedirect("/phones/{$id}");
            return;
        }

        $csrfToken = $_POST['csrf_token'] ?? null;
        if (!Security::validateCsrfToken($csrfToken)) {
            View::flash('danger', 'Security verification failed (CSRF token missing or expired). Please try again.');
            Security::safeRedirect("/phones/{$id}/costs/create");
            return;
        }

        $costType    = strtoupper(trim($_POST['cost_type'] ?? ''));
        $description = trim($_POST['description'] ?? '');
        $amountRaw   = trim($_POST['amount'] ?? '');
        $costDate    = trim($_POST['cost_date'] ?? date('Y-m-d'));

        $errors = [];

        // Valid cost types
        $allowedTypes = [
            'DISPLAY', 'BATTERY', 'PARTS', 'LABOR', 'REPAIR_OTHER', 'REPAIR',
            'CLEANING', 'PACKAGING', 'LOGISTICS', 'OTHER'
        ];

        if (!in_array($costType, $allowedTypes, true)) {
            $errors[] = 'Please select a valid cost classification.';
        }

        if ($description === '' || strlen($description) > 255) {
            $errors[] = 'A descriptive reason or invoice note (up to 255 characters) is required.';
        }

        if (!is_numeric($amountRaw) || (float)$amountRaw <= 0) {
            $errors[] = 'Cost amount must be a positive decimal number greater than 0.00.';
        } else {
            $amount = Financial::format((string)(float)$amountRaw);
        }

        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $costDate)) {
            $errors[] = 'Cost date must be in YYYY-MM-DD format.';
        }

        if (!empty($errors)) {
            View::flash('danger', implode(' ', $errors));
            View::render('phones/add_cost', [
                'pageTitle'   => "Add Direct Cost: {$phone['brand']} {$phone['model_name']}",
                'phone'       => $phone,
                'formData'    => [
                    'cost_type'   => $costType,
                    'description' => $description,
                    'amount'      => $amountRaw,
                    'cost_date'   => $costDate,
                ],
                'canViewCost' => Authorization::hasPermission('view_purchase_costs'),
            ], 'main');
            return;
        }

        $operatorId = Auth::id() ?? 1;

        // Atomic transaction: Insert direct cost & recalculate cost basis
        try {
            Database::transaction(function() use ($phone, $id, $costType, $description, $amount, $costDate, $operatorId) {
                // 1. Insert direct cost record
                Database::execute("
                    INSERT INTO phone_direct_costs (phone_id, cost_type, description, amount, cost_date, created_by, created_at)
                    VALUES (:pid, :ctype, :desc, :amt, :cdate, :cby, NOW())
                ", [
                    ':pid'   => $id,
                    ':ctype' => $costType,
                    ':desc'  => $description,
                    ':amt'   => $amount,
                    ':cdate' => $costDate,
                    ':cby'   => $operatorId,
                ]);

                $costId = (int)Database::lastInsertId();

                // 2. Fetch all direct costs for this phone to recalculate repair_cost vs other_cost accurately
                $allCosts = Database::fetchAll("SELECT cost_type, amount FROM phone_direct_costs WHERE phone_id = :pid", [':pid' => $id]);

                $totalRepair = '0.00';
                $totalOther = '0.00';
                $repairTypes = ['DISPLAY', 'BATTERY', 'PARTS', 'LABOR', 'REPAIR_OTHER', 'REPAIR'];

                foreach ($allCosts as $c) {
                    if (in_array($c['cost_type'], $repairTypes, true)) {
                        $totalRepair = Financial::add($totalRepair, (string)$c['amount']);
                    } else {
                        $totalOther = Financial::add($totalOther, (string)$c['amount']);
                    }
                }

                // 3. Formulate total cost basis: purchase_cost + repair_cost + other_cost
                $totalCostBasis = Financial::calculateUsedPhoneCostBasis(
                    (string)$phone['purchase_cost'],
                    $totalRepair,
                    $totalOther
                );

                // 4. Update phones row
                Database::execute("
                    UPDATE phones 
                    SET repair_cost = :rcost, 
                        other_cost = :ocost, 
                        total_cost_basis = :tcost,
                        updated_at = NOW()
                    WHERE id = :id
                ", [
                    ':rcost' => $totalRepair,
                    ':ocost' => $totalOther,
                    ':tcost' => $totalCostBasis,
                    ':id'    => $id,
                ]);

                // 5. Audit Log
                AuditLogger::log(
                    'PHONE_COST_ADDED',
                    'phone_direct_costs',
                    $costId,
                    null,
                    [
                        'phone_id'         => $id,
                        'cost_type'        => $costType,
                        'amount'           => $amount,
                        'total_cost_basis' => $totalCostBasis,
                    ],
                    "Added {$costType} cost of ₹{$amount} to Phone Episode #{$id} ({$description})"
                );
            });

            View::flash('success', "Direct cost of ₹{$amount} successfully logged. Phone cost basis updated.");
            Security::safeRedirect("/phones/{$id}");
        } catch (\Throwable $e) {
            View::flash('danger', 'System error while adding direct cost: ' . $e->getMessage());
            Security::safeRedirect("/phones/{$id}/costs/create");
        }
    }

    /**
     * Check if an IMEI (1 or 2) collides with an existing device record.
     * Returns conflict description string if conflict exists, null otherwise.
     */
    private function checkImeiConflicts(string $imei1, ?string $imei2): ?string
    {
        // 1. Check IMEI 1 in devices (imei_1 or imei_2)
        $conflict1 = Database::fetchOne("
            SELECT id, brand, model_name, imei_1, imei_2 
            FROM devices 
            WHERE imei_1 = :i1 OR imei_2 = :i1 
            LIMIT 1
        ", [':i1' => $imei1]);

        if ($conflict1) {
            return "IMEI 1 ({$imei1}) is already registered to Device #{$conflict1['id']} ({$conflict1['brand']} {$conflict1['model_name']}).";
        }

        // 2. Check IMEI 2 in devices (imei_1 or imei_2)
        if ($imei2 !== null) {
            $conflict2 = Database::fetchOne("
                SELECT id, brand, model_name, imei_1, imei_2 
                FROM devices 
                WHERE imei_1 = :i2 OR imei_2 = :i2 
                LIMIT 1
            ", [':i2' => $imei2]);

            if ($conflict2) {
                return "IMEI 2 ({$imei2}) is already registered to Device #{$conflict2['id']} ({$conflict2['brand']} {$conflict2['model_name']}).";
            }
        }

        return null;
    }

    /**
     * Find existing physical device matching either IMEI 1 or IMEI 2.
     */
    private function findExistingDevice(string $imei1, ?string $imei2): ?array
    {
        if ($imei2 !== null) {
            return Database::fetchOne("
                SELECT * 
                FROM devices 
                WHERE imei_1 = :i1 OR imei_2 = :i1 OR imei_1 = :i2 OR imei_2 = :i2 
                LIMIT 1
            ", [':i1' => $imei1, ':i2' => $imei2]);
        }

        return Database::fetchOne("
            SELECT * 
            FROM devices 
            WHERE imei_1 = :i1 OR imei_2 = :i1 
            LIMIT 1
        ", [':i1' => $imei1]);
    }

    /**
     * Helper to output JSON response.
     */
    private function jsonResponse(array $data, int $statusCode = 200): void
    {
        http_response_code($statusCode);
        header('Content-Type: application/json; charset=UTF-8');
        echo json_encode($data, JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
        exit;
    }
}
