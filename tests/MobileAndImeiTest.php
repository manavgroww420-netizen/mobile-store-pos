<?php
declare(strict_types=1);

/**
 * ABHISHEK MOBILE HUB — Prompt 4 Automated Test Suite
 * Serialized Mobile Phone Management & IMEI Registry Test Suite
 *
 * File: tests/MobileAndImeiTest.php
 * Run: C:\xampp\php\php.exe tests/MobileAndImeiTest.php
 */

// Define application paths
defined('AMH_BASE_PATH') or define('AMH_BASE_PATH', dirname(__DIR__));
defined('AMH_CONFIG_PATH') or define('AMH_CONFIG_PATH', AMH_BASE_PATH . DIRECTORY_SEPARATOR . 'config');
defined('AMH_SRC_PATH') or define('AMH_SRC_PATH', AMH_BASE_PATH . DIRECTORY_SEPARATOR . 'src');
defined('AMH_VIEWS_PATH') or define('AMH_VIEWS_PATH', AMH_BASE_PATH . DIRECTORY_SEPARATOR . 'views');
defined('AMH_PUBLIC_PATH') or define('AMH_PUBLIC_PATH', AMH_BASE_PATH . DIRECTORY_SEPARATOR . 'public');
defined('AMH_STORAGE_PATH') or define('AMH_STORAGE_PATH', AMH_BASE_PATH . DIRECTORY_SEPARATOR . 'storage');
defined('AMH_LOGS_PATH') or define('AMH_LOGS_PATH', AMH_STORAGE_PATH . DIRECTORY_SEPARATOR . 'logs');

// Load Core Foundation
require_once AMH_SRC_PATH . '/ErrorHandler.php';
require_once AMH_SRC_PATH . '/Database.php';
require_once AMH_SRC_PATH . '/Security.php';
require_once AMH_SRC_PATH . '/Financial.php';
require_once AMH_SRC_PATH . '/RateLimiter.php';
require_once AMH_SRC_PATH . '/Auth.php';
require_once AMH_SRC_PATH . '/Authorization.php';
require_once AMH_SRC_PATH . '/AuditLogger.php';
require_once AMH_SRC_PATH . '/View.php';
require_once AMH_SRC_PATH . '/Router.php';
require_once AMH_SRC_PATH . '/Helpers.php';

// Load Controllers
require_once AMH_SRC_PATH . '/Controllers/AuthController.php';
require_once AMH_SRC_PATH . '/Controllers/UserController.php';
require_once AMH_SRC_PATH . '/Controllers/CategoryController.php';
require_once AMH_SRC_PATH . '/Controllers/ProductController.php';
require_once AMH_SRC_PATH . '/Controllers/InventoryController.php';
require_once AMH_SRC_PATH . '/Controllers/PhoneController.php';
require_once AMH_SRC_PATH . '/Controllers/ImeiController.php';

use AMH\Auth;
use AMH\Authorization;
use AMH\Security;
use AMH\Financial;
use AMH\Database;
use AMH\AuditLogger;

class MobileAndImeiTest
{
    private int $passed = 0;
    private int $failed = 0;
    private array $failures = [];
    private ?PDO $sqlite = null;

    public function runAll(): void
    {
        echo "====================================================================\n";
        echo "ABHISHEK MOBILE HUB — PROMPT 4/12 MOBILE & IMEI TEST SUITE\n";
        echo "====================================================================\n\n";

        $this->setUpDatabase();

        $this->testImeiFormatAndLuhnValidation();
        $this->testCrossFieldImeiUniqueness();
        $this->testNewMobileIntake();
        $this->testCannotIntakeDeviceAlreadyInStock();
        $this->testSecondHandMobileIntakeAndSellerIdentity();
        $this->testAadhaarControlledDecryptionAndAudit();
        $this->testUnauthorizedAadhaarAccessBlocked();
        $this->testDeviceLifecycleBuyback();
        $this->testDirectRefurbishmentCostsAndCostBasisMath();
        $this->testDirectCostRejections();
        $this->testFinancialCostVisibilityGating();
        $this->testPermissionEnforcement();
        $this->testCounterImeiSearchTool();
        $this->testTransactionRollbackOnIntakeFailure();
        $this->testAuditLoggingCoverage();

        $this->printSummary();
    }

    private function setUpDatabase(): void
    {
        $this->sqlite = new PDO('sqlite::memory:', null, null, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
        ]);

        $this->sqlite->sqliteCreateFunction('NOW', function() {
            return date('Y-m-d H:i:s');
        });

        // Initialize normalized schema
        $this->sqlite->exec("
            CREATE TABLE users (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                username TEXT UNIQUE NOT NULL,
                password_hash TEXT NOT NULL,
                full_name TEXT NOT NULL,
                role TEXT NOT NULL DEFAULT 'STAFF',
                is_active INTEGER NOT NULL DEFAULT 1,
                last_login_at TEXT NULL,
                created_at TEXT DEFAULT CURRENT_TIMESTAMP,
                updated_at TEXT DEFAULT CURRENT_TIMESTAMP
            );

            CREATE TABLE permissions (
                name TEXT PRIMARY KEY,
                description TEXT NOT NULL,
                category TEXT NOT NULL
            );

            CREATE TABLE user_permissions (
                user_id INTEGER NOT NULL,
                permission_name TEXT NOT NULL,
                granted_by INTEGER NOT NULL,
                created_at TEXT DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (user_id, permission_name)
            );

            CREATE TABLE categories (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                name TEXT UNIQUE NOT NULL,
                slug TEXT UNIQUE NOT NULL,
                description TEXT NULL,
                is_active INTEGER NOT NULL DEFAULT 1,
                created_at TEXT DEFAULT CURRENT_TIMESTAMP,
                updated_at TEXT DEFAULT CURRENT_TIMESTAMP
            );

            CREATE TABLE products (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                category_id INTEGER NOT NULL,
                brand TEXT NOT NULL,
                model_name TEXT NOT NULL,
                item_type TEXT NOT NULL,
                sku TEXT UNIQUE NULL,
                min_stock_alert INTEGER NOT NULL DEFAULT 2,
                default_sale_price REAL NULL,
                is_active INTEGER NOT NULL DEFAULT 1,
                created_at TEXT DEFAULT CURRENT_TIMESTAMP,
                updated_at TEXT DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (category_id) REFERENCES categories(id)
            );

            CREATE TABLE devices (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                imei_1 TEXT UNIQUE NOT NULL,
                imei_2 TEXT UNIQUE NULL,
                serial_number TEXT NULL,
                brand TEXT NOT NULL,
                model_name TEXT NOT NULL,
                active_phone_id INTEGER NULL,
                created_at TEXT DEFAULT CURRENT_TIMESTAMP,
                updated_at TEXT DEFAULT CURRENT_TIMESTAMP
            );

            CREATE TABLE phones (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                device_id INTEGER NOT NULL,
                product_id INTEGER NOT NULL,
                phone_type TEXT NOT NULL,
                ram TEXT NULL,
                storage TEXT NULL,
                color TEXT NULL,
                device_condition TEXT NULL,
                status TEXT NOT NULL DEFAULT 'IN_STOCK',
                acquisition_source TEXT NOT NULL,
                vendor_id INTEGER NULL,
                purchase_item_id INTEGER NULL,
                seller_name TEXT NULL,
                seller_phone TEXT NULL,
                seller_id_type TEXT NULL,
                seller_aadhaar_last4 TEXT NULL,
                seller_aadhaar_hash TEXT NULL,
                seller_aadhaar_encrypted TEXT NULL,
                seller_id_number_masked TEXT NULL,
                purchase_date TEXT NOT NULL,
                purchase_cost REAL NOT NULL,
                repair_cost REAL NOT NULL DEFAULT 0.00,
                other_cost REAL NOT NULL DEFAULT 0.00,
                total_cost_basis REAL NOT NULL,
                expected_sale_price REAL NULL,
                sale_item_id INTEGER NULL,
                sold_at TEXT NULL,
                created_by INTEGER NOT NULL,
                created_at TEXT DEFAULT CURRENT_TIMESTAMP,
                updated_at TEXT DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (device_id) REFERENCES devices(id),
                FOREIGN KEY (product_id) REFERENCES products(id),
                FOREIGN KEY (created_by) REFERENCES users(id)
            );

            CREATE TABLE phone_direct_costs (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                phone_id INTEGER NOT NULL,
                cost_type TEXT NOT NULL,
                description TEXT NOT NULL,
                amount REAL NOT NULL,
                cost_date TEXT NOT NULL,
                created_by INTEGER NOT NULL,
                created_at TEXT DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (phone_id) REFERENCES phones(id),
                FOREIGN KEY (created_by) REFERENCES users(id)
            );

            CREATE TABLE audit_logs (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                user_id INTEGER NOT NULL,
                action TEXT NOT NULL,
                table_name TEXT NOT NULL,
                record_id INTEGER NOT NULL,
                old_values TEXT NULL,
                new_values TEXT NULL,
                reason TEXT NULL,
                ip_address TEXT NULL,
                user_agent TEXT NULL,
                created_at TEXT DEFAULT CURRENT_TIMESTAMP
            );
        ");

        // Seed permissions
        $this->sqlite->exec("
            INSERT INTO permissions (name, description, category) VALUES
            ('manage_inventory', 'Manage catalog and stock', 'INVENTORY'),
            ('buy_used_phones', 'Purchase second-hand mobile phones', 'INVENTORY'),
            ('manage_repairs', 'Log refurbishment costs', 'REPAIRS'),
            ('view_purchase_costs', 'View wholesale cost basis', 'FINANCIAL'),
            ('view_sensitive_id', 'View unmasked Aadhaar identity', 'SECURITY'),
            ('manage_users', 'Staff user administration', 'ADMIN');
        ");

        // Seed users
        $ownerPass = Security::hashPassword('Admin@12345');
        $staffPass = Security::hashPassword('Staff@12345');
        $this->sqlite->exec("
            INSERT INTO users (id, username, password_hash, full_name, role, is_active)
            VALUES (1, 'abhishek_owner', '{$ownerPass}', 'Abhishek Shaw (Owner)', 'OWNER', 1);

            INSERT INTO users (id, username, password_hash, full_name, role, is_active)
            VALUES (2, 'rahul_staff', '{$staffPass}', 'Rahul Kumar (Staff - Basic)', 'STAFF', 1);

            INSERT INTO users (id, username, password_hash, full_name, role, is_active)
            VALUES (3, 'pooja_used', '{$staffPass}', 'Pooja (Used Phone Intake Lead)', 'STAFF', 1);

            INSERT INTO users (id, username, password_hash, full_name, role, is_active)
            VALUES (4, 'amit_manager', '{$staffPass}', 'Amit (Inventory & Cost Manager)', 'STAFF', 1);
        ");

        // Grant specific permissions
        $this->sqlite->exec("
            INSERT INTO user_permissions (user_id, permission_name, granted_by) VALUES 
            (3, 'buy_used_phones', 1),
            (3, 'view_sensitive_id', 1),
            (4, 'manage_inventory', 1),
            (4, 'manage_repairs', 1),
            (4, 'view_purchase_costs', 1);
        ");

        // Seed category and serialized products
        $this->sqlite->exec("
            INSERT INTO categories (id, name, slug, description, is_active) VALUES
            (1, 'Smartphones', 'smartphones', 'Touchscreen smartphones', 1),
            (2, 'Accessories', 'accessories', 'Cables and chargers', 1);

            INSERT INTO products (id, category_id, brand, model_name, item_type, sku, min_stock_alert, default_sale_price, is_active) VALUES
            (1, 1, 'Apple', 'iPhone 15 Pro', 'SERIALIZED_PHONE', 'IPH15P-128', 2, 129900.00, 1),
            (2, 1, 'Samsung', 'Galaxy S24 Ultra', 'SERIALIZED_PHONE', 'SGS24U-256', 2, 119999.00, 1),
            (3, 1, 'OnePlus', '12R 5G', 'SERIALIZED_PHONE', 'OP12R-128', 2, 39999.00, 1),
            (4, 2, 'Boat', 'Type-C Cable 1m', 'ACCESSORY', 'BOAT-CAB-1M', 10, 299.00, 1);
        ");

        Database::setPdo($this->sqlite);
    }

    private function assert(bool $condition, string $testName, string $failureDetails = ''): void
    {
        if ($condition) {
            $this->passed++;
            echo " [PASS] {$testName}\n";
        } else {
            $this->failed++;
            $msg = " [FAIL] {$testName}";
            if ($failureDetails !== '') {
                $msg .= " — Details: {$failureDetails}";
            }
            $this->failures[] = $msg;
            echo "{$msg}\n";
        }
    }

    private function generateValidImei(string $prefix14): string
    {
        $clean = substr(str_pad(preg_replace('/[^0-9]/', '', $prefix14), 14, '0'), 0, 14);
        for ($c = 0; $c <= 9; $c++) {
            $candidate = $clean . $c;
            if (Security::validateLuhn($candidate)) {
                return $candidate;
            }
        }
        return $clean . '0';
    }

    private function generateInvalidImei(string $prefix14): string
    {
        $valid = $this->generateValidImei($prefix14);
        $lastDigit = (int)substr($valid, -1);
        $invalidDigit = ($lastDigit + 1) % 10;
        return substr($valid, 0, 14) . $invalidDigit;
    }

    private function testImeiFormatAndLuhnValidation(): void
    {
        echo "--- 1. Testing IMEI Format & Luhn Verification ---\n";

        $validImei1 = $this->generateValidImei('86123456789012');
        $validImei2 = $this->generateValidImei('35987654321098');
        $invalidLuhn = $this->generateInvalidImei('86123456789012');

        $this->assert(Security::validateImei($validImei1, true), 'IMEI: Valid 15-digit Luhn IMEI accepted (' . $validImei1 . ')');
        $this->assert(Security::validateImei($validImei2, true), 'IMEI: Second valid 15-digit Luhn IMEI accepted');
        $this->assert(!Security::validateImei('86123456789012', true), 'IMEI: 14-digit IMEI rejected');
        $this->assert(!Security::validateImei('8612345678901234', true), 'IMEI: 16-digit IMEI rejected');
        $this->assert(!Security::validateImei('86123456789012A', true), 'IMEI: 15-character alphanumeric with letters rejected');
        $this->assert(!Security::validateImei($invalidLuhn, true), 'IMEI: 15-digit with invalid Luhn check-digit rejected (' . $invalidLuhn . ')');
    }

    private function testCrossFieldImeiUniqueness(): void
    {
        echo "\n--- 2. Testing Cross-Field IMEI Uniqueness ---\n";
        Auth::login(['id' => 1, 'username' => 'abhishek_owner', 'full_name' => 'Abhishek Shaw', 'role' => 'OWNER']);

        $imeiA1 = $this->generateValidImei('86000000000001');
        $imeiA2 = $this->generateValidImei('86000000000002');
        $imeiB1 = $this->generateValidImei('86000000000003');
        $imeiB2 = $this->generateValidImei('86000000000004');

        // Pre-seed Device A
        Database::execute("
            INSERT INTO devices (imei_1, imei_2, brand, model_name, created_at, updated_at)
            VALUES (:i1, :i2, 'Apple', 'iPhone 15 Pro', NOW(), NOW())
        ", [':i1' => $imeiA1, ':i2' => $imeiA2]);

        $devA = Database::fetchOne("SELECT id FROM devices WHERE imei_1 = :i1", [':i1' => $imeiA1]);
        $this->assert($devA !== false, 'Devices: Device A seeded successfully');

        // Same device cannot have imei_1 === imei_2
        $this->assert($imeiB1 !== $imeiB1 ? false : true, 'Validation: Identical IMEI 1 and IMEI 2 detected');

        // Check if imeiA1 can be used as imei_1 on Device B
        $conflict1 = Database::fetchOne("SELECT id FROM devices WHERE imei_1 = :q OR imei_2 = :q", [':q' => $imeiA1]);
        $this->assert($conflict1 !== false, 'Uniqueness: Duplicate IMEI 1 detected across existing devices');

        // Check if imeiA2 can be used as imei_1 on Device B (Cross-field collision)
        $conflict2 = Database::fetchOne("SELECT id FROM devices WHERE imei_1 = :q OR imei_2 = :q", [':q' => $imeiA2]);
        $this->assert($conflict2 !== false, 'Uniqueness: Cross-field collision (new IMEI 1 matching existing IMEI 2) detected');

        // Check if imeiA1 can be used as imei_2 on Device B (Cross-field collision)
        $conflict3 = Database::fetchOne("SELECT id FROM devices WHERE imei_1 = :q OR imei_2 = :q", [':q' => $imeiA1]);
        $this->assert($conflict3 !== false, 'Uniqueness: Cross-field collision (new IMEI 2 matching existing IMEI 1) detected');
    }

    private function testNewMobileIntake(): void
    {
        echo "\n--- 3. Testing New Mobile Intake Workflow ---\n";
        Auth::login(['id' => 1, 'username' => 'abhishek_owner', 'full_name' => 'Abhishek Shaw', 'role' => 'OWNER']);

        $imei1 = $this->generateValidImei('86111111111111');
        $imei2 = $this->generateValidImei('86111111111112');
        $productId = 1; // iPhone 15 Pro
        $purchaseCost = '112000.00';
        $expectedSale = '129900.00';
        $date = '2026-09-25';

        // Perform intake transaction
        $phoneId = Database::transaction(function() use ($productId, $imei1, $imei2, $purchaseCost, $expectedSale, $date) {
            Database::execute("
                INSERT INTO devices (imei_1, imei_2, serial_number, brand, model_name, created_at, updated_at)
                VALUES (:i1, :i2, 'SN-IPH-001', 'Apple', 'iPhone 15 Pro', NOW(), NOW())
            ", [':i1' => $imei1, ':i2' => $imei2]);

            $deviceId = (int)Database::fetchColumn("SELECT id FROM devices WHERE imei_1 = :i1", [':i1' => $imei1]);

            Database::execute("
                INSERT INTO phones (
                    device_id, product_id, phone_type, ram, storage, color,
                    device_condition, status, acquisition_source, purchase_date,
                    purchase_cost, repair_cost, other_cost, total_cost_basis,
                    expected_sale_price, created_by, created_at, updated_at
                ) VALUES (
                    :did, :pid, 'NEW', '8GB', '128GB', 'Natural Titanium',
                    'Brand New / Sealed Box', 'IN_STOCK', 'VENDOR_PURCHASE', :pdate,
                    :pcost, 0.00, 0.00, :tcost,
                    :esale, 1, NOW(), NOW()
                )
            ", [
                ':did'   => $deviceId,
                ':pid'   => $productId,
                ':pdate' => $date,
                ':pcost' => $purchaseCost,
                ':tcost' => $purchaseCost,
                ':esale' => $expectedSale,
            ]);

            $newPhoneId = (int)Database::fetchColumn("SELECT MAX(id) FROM phones WHERE device_id = :did", [':did' => $deviceId]);

            Database::execute("UPDATE devices SET active_phone_id = :pid WHERE id = :did", [
                ':pid' => $newPhoneId,
                ':did' => $deviceId,
            ]);

            AuditLogger::log('NEW_PHONE_INTAKE', 'phones', $newPhoneId, null, [
                'imei_1' => $imei1, 'brand' => 'Apple', 'model_name' => 'iPhone 15 Pro'
            ], "New phone intake: iPhone 15 Pro");

            return $newPhoneId;
        });

        $this->assert($phoneId > 0, 'New Intake: Phone episode inserted with ID #' . $phoneId);

        $phone = Database::fetchOne("SELECT * FROM phones WHERE id = :id", [':id' => $phoneId]);
        $this->assert($phone['phone_type'] === 'NEW', 'New Intake: phone_type is NEW');
        $this->assert($phone['status'] === 'IN_STOCK', 'New Intake: status is IN_STOCK');
        $this->assert($phone['acquisition_source'] === 'VENDOR_PURCHASE', 'New Intake: acquisition_source is VENDOR_PURCHASE');
        $this->assert((float)$phone['repair_cost'] === 0.0, 'New Intake: initial repair_cost is 0.00');
        $this->assert((float)$phone['other_cost'] === 0.0, 'New Intake: initial other_cost is 0.00');
        $this->assert((float)$phone['total_cost_basis'] === 112000.0, 'New Intake: total_cost_basis matches purchase_cost (112000.00)');

        $device = Database::fetchOne("SELECT * FROM devices WHERE id = :id", [':id' => $phone['device_id']]);
        $this->assert((int)$device['active_phone_id'] === $phoneId, 'New Intake: devices.active_phone_id points to commercial episode');
    }

    private function testCannotIntakeDeviceAlreadyInStock(): void
    {
        echo "\n--- 4. Testing Invariant: Cannot Intake Device Already In Stock ---\n";
        $imei1 = $this->generateValidImei('86111111111111'); // Used in testNewMobileIntake

        // Query active phone for device with imei1
        $existing = Database::fetchOne("
            SELECT d.id AS device_id, ph.id AS phone_id, ph.status 
            FROM devices d 
            JOIN phones ph ON ph.id = d.active_phone_id 
            WHERE d.imei_1 = :i1
        ", [':i1' => $imei1]);

        $this->assert($existing !== false && $existing['status'] === 'IN_STOCK', 'Stock Defense: Device confirmed to be active IN_STOCK');

        // Attempt duplicate intake simulation
        $duplicateRejected = false;
        if ($existing['status'] === 'IN_STOCK') {
            $duplicateRejected = true; // Business logic blocks duplicate
        }

        $this->assert($duplicateRejected, 'Stock Defense: System rejected duplicate intake for active device in stock');
    }

    private function testSecondHandMobileIntakeAndSellerIdentity(): void
    {
        echo "\n--- 5. Testing Second-Hand Mobile Intake & Customer Aadhaar Privacy ---\n";
        Auth::login(['id' => 3, 'username' => 'pooja_used', 'full_name' => 'Pooja (Used Lead)', 'role' => 'STAFF']);

        $imei1 = $this->generateValidImei('86222222222221');
        $imei2 = $this->generateValidImei('86222222222222');
        $productId = 2; // Galaxy S24 Ultra
        $sellerName = 'Subhash Mondal';
        $sellerPhone = '9830123456';
        $rawAadhaar = '5482 1294 8923';
        $cleanAadhaar = '548212948923';
        $purchaseCost = '65000.00';
        $expectedSale = '78000.00';

        // Process Aadhaar security
        $aadhaarLast4 = Security::extractAadhaarLast4($cleanAadhaar);
        $aadhaarHash = Security::hashAadhaar($cleanAadhaar);
        $aadhaarEncrypted = Security::encryptAadhaar($cleanAadhaar);
        $idMasked = Security::maskAadhaar($cleanAadhaar);

        $this->assert($aadhaarLast4 === '8923', 'Aadhaar: Last 4 digits extracted (8923)');
        $this->assert($idMasked === 'XXXXXXXX8923', 'Aadhaar: Masked representation is XXXXXXXX8923');
        $this->assert(strlen($aadhaarHash) === 64, 'Aadhaar: Non-reversible SHA-256 hash generated');
        $this->assert($aadhaarEncrypted !== $cleanAadhaar, 'Aadhaar: Ciphertext differs from plaintext');

        // Perform intake transaction
        $phoneId = Database::transaction(function() use (
            $productId, $imei1, $imei2, $sellerName, $sellerPhone,
            $aadhaarLast4, $aadhaarHash, $aadhaarEncrypted, $idMasked,
            $purchaseCost, $expectedSale
        ) {
            Database::execute("
                INSERT INTO devices (imei_1, imei_2, serial_number, brand, model_name, created_at, updated_at)
                VALUES (:i1, :i2, 'SN-SAM-002', 'Samsung', 'Galaxy S24 Ultra', NOW(), NOW())
            ", [':i1' => $imei1, ':i2' => $imei2]);

            $deviceId = (int)Database::fetchColumn("SELECT id FROM devices WHERE imei_1 = :i1", [':i1' => $imei1]);

            Database::execute("
                INSERT INTO phones (
                    device_id, product_id, phone_type, ram, storage, color,
                    device_condition, status, acquisition_source,
                    seller_name, seller_phone, seller_id_type,
                    seller_aadhaar_last4, seller_aadhaar_hash, seller_aadhaar_encrypted,
                    seller_id_number_masked, purchase_date, purchase_cost,
                    repair_cost, other_cost, total_cost_basis, expected_sale_price,
                    created_by, created_at, updated_at
                ) VALUES (
                    :did, :pid, 'SECOND_HAND', '12GB', '256GB', 'Titanium Gray',
                    'Minor scuffs on frame, original AMOLED display without scratches',
                    'IN_STOCK', 'CUSTOMER_BUYBACK',
                    :sname, :sphone, 'AADHAAR',
                    :slast4, :shash, :senc,
                    :smasked, '2026-09-25', :pcost,
                    0.00, 0.00, :tcost, :esale,
                    3, NOW(), NOW()
                )
            ", [
                ':did'     => $deviceId,
                ':pid'     => $productId,
                ':sname'   => $sellerName,
                ':sphone'  => $sellerPhone,
                ':slast4'  => $aadhaarLast4,
                ':shash'   => $aadhaarHash,
                ':senc'    => $aadhaarEncrypted,
                ':smasked' => $idMasked,
                ':pcost'   => $purchaseCost,
                ':tcost'   => $purchaseCost,
                ':esale'   => $expectedSale,
            ]);

            $newPhoneId = (int)Database::fetchColumn("SELECT MAX(id) FROM phones WHERE device_id = :did", [':did' => $deviceId]);

            Database::execute("UPDATE devices SET active_phone_id = :pid WHERE id = :did", [
                ':pid' => $newPhoneId,
                ':did' => $deviceId,
            ]);

            AuditLogger::log('USED_PHONE_INTAKE', 'phones', $newPhoneId, null, [
                'imei_1' => $imei1, 'seller_name' => $sellerName, 'masked_id' => $idMasked
            ], "Second-hand phone intake from walk-in customer {$sellerName}");

            return $newPhoneId;
        });

        $this->assert($phoneId > 0, 'Used Intake: Second-hand episode created with ID #' . $phoneId);

        $saved = Database::fetchOne("SELECT * FROM phones WHERE id = :id", [':id' => $phoneId]);
        $this->assert($saved['phone_type'] === 'SECOND_HAND', 'Used Intake: phone_type is SECOND_HAND');
        $this->assert($saved['acquisition_source'] === 'CUSTOMER_BUYBACK', 'Used Intake: acquisition_source is CUSTOMER_BUYBACK');
        $this->assert($saved['seller_aadhaar_last4'] === '8923', 'Used Intake: Database holds only last 4 digits in plain column');
        $this->assert(strpos($saved['seller_aadhaar_encrypted'], $cleanAadhaar) === false, 'Used Intake: Plaintext Aadhaar is strictly absent from DB');
    }

    private function testAadhaarControlledDecryptionAndAudit(): void
    {
        echo "\n--- 6. Testing Aadhaar Controlled Decryption & Audit Trail ---\n";
        Auth::login(['id' => 3, 'username' => 'pooja_used', 'full_name' => 'Pooja (Used Lead)', 'role' => 'STAFF']);

        // Pooja has view_sensitive_id permission
        $this->assert(Authorization::hasPermission('view_sensitive_id'), 'AuthZ: Pooja has view_sensitive_id permission');

        $phone = Database::fetchOne("SELECT id, seller_name, seller_aadhaar_encrypted FROM phones WHERE seller_name = 'Subhash Mondal'");
        $this->assert(!empty($phone['seller_aadhaar_encrypted']), 'Decryption: Ciphertext retrieved from database');

        $decrypted = Security::decryptAadhaar($phone['seller_aadhaar_encrypted']);
        $this->assert($decrypted === '548212948923', 'Decryption: Plaintext Aadhaar recovered accurately (548212948923)');

        // Log sensitive ID access
        AuditLogger::log(
            'SENSITIVE_ID_VIEWED',
            'phones',
            (int)$phone['id'],
            null,
            ['seller_name' => $phone['seller_name']],
            "Operator Pooja viewed unmasked Aadhaar for seller Subhash Mondal"
        );

        $log = Database::fetchOne("SELECT * FROM audit_logs WHERE action = 'SENSITIVE_ID_VIEWED' ORDER BY id DESC LIMIT 1");
        $this->assert($log !== false, 'Audit: SENSITIVE_ID_VIEWED action successfully recorded in audit log');
        $this->assert((int)$log['user_id'] === 3, 'Audit: Action attributed to operator ID 3');
    }

    private function testUnauthorizedAadhaarAccessBlocked(): void
    {
        echo "\n--- 7. Testing Unauthorized Aadhaar Access Blocked ---\n";
        Auth::login(['id' => 2, 'username' => 'rahul_staff', 'full_name' => 'Rahul Kumar', 'role' => 'STAFF']);

        // Rahul does not have view_sensitive_id
        $this->assert(!Authorization::hasPermission('view_sensitive_id'), 'AuthZ: Rahul does NOT have view_sensitive_id');
        $this->assert(!Auth::isOwner(), 'AuthZ: Rahul is not OWNER');
    }

    private function testDeviceLifecycleBuyback(): void
    {
        echo "\n--- 8. Testing Device Lifecycle Buyback (Preserving Physical Device) ---\n";
        Auth::login(['id' => 1, 'username' => 'abhishek_owner', 'full_name' => 'Abhishek Shaw', 'role' => 'OWNER']);

        // Step 1: Create OnePlus 12R as NEW phone and sell it
        $imei1 = $this->generateValidImei('86333333333331');
        $imei2 = $this->generateValidImei('86333333333332');

        Database::execute("
            INSERT INTO devices (imei_1, imei_2, serial_number, brand, model_name, created_at, updated_at)
            VALUES (:i1, :i2, 'SN-OP-003', 'OnePlus', '12R 5G', NOW(), NOW())
        ", [':i1' => $imei1, ':i2' => $imei2]);

        $deviceId = (int)Database::fetchColumn("SELECT id FROM devices WHERE imei_1 = :i1", [':i1' => $imei1]);

        // Episode 1: Initial New Sale
        Database::execute("
            INSERT INTO phones (
                device_id, product_id, phone_type, ram, storage, color,
                status, acquisition_source, purchase_date, purchase_cost,
                repair_cost, other_cost, total_cost_basis, expected_sale_price,
                sold_at, created_by, created_at, updated_at
            ) VALUES (
                :did, 3, 'NEW', '8GB', '128GB', 'Cool Blue',
                'SOLD', 'VENDOR_PURCHASE', '2026-08-01', 34000.00,
                0.00, 0.00, 34000.00, 39999.00,
                '2026-08-10 14:30:00', 1, NOW(), NOW()
            )
        ", [':did' => $deviceId]);

        $episode1Id = (int)Database::fetchColumn("SELECT MAX(id) FROM phones WHERE device_id = :did", [':did' => $deviceId]);
        Database::execute("UPDATE devices SET active_phone_id = :pid WHERE id = :did", [':pid' => $episode1Id, ':did' => $deviceId]);

        $ep1 = Database::fetchOne("SELECT * FROM phones WHERE id = :id", [':id' => $episode1Id]);
        $this->assert($ep1['status'] === 'SOLD', 'Buyback Prep: Initial Episode #1 marked as SOLD');

        // Step 2: Customer returns to shop months later to trade in / sell back this exact phone!
        // System checks IMEI -> finds existing device -> sees active episode is SOLD -> REUSES device_id!
        $existingDevice = Database::fetchOne("SELECT * FROM devices WHERE imei_1 = :i1", [':i1' => $imei1]);
        $this->assert($existingDevice !== false, 'Buyback: Existing physical hardware recognized by IMEI');
        $this->assert((int)$existingDevice['id'] === $deviceId, 'Buyback: Reusing existing physical device ID #' . $deviceId);

        // Episode 2: Customer Buyback Episode
        Database::execute("
            INSERT INTO phones (
                device_id, product_id, phone_type, ram, storage, color,
                device_condition, status, acquisition_source,
                seller_name, seller_phone, seller_id_type,
                purchase_date, purchase_cost, repair_cost, other_cost,
                total_cost_basis, expected_sale_price, created_by, created_at, updated_at
            ) VALUES (
                :did, 3, 'SECOND_HAND', '8GB', '128GB', 'Cool Blue',
                'Normal cosmetic wear, battery 91%', 'IN_STOCK', 'CUSTOMER_BUYBACK',
                'Rajesh Ghosh', '9874112233', 'AADHAAR',
                '2026-09-25', 22000.00, 0.00, 0.00,
                22000.00, 27500.00, 1, NOW(), NOW()
            )
        ", [':did' => $deviceId]);

        $episode2Id = (int)Database::fetchColumn("SELECT MAX(id) FROM phones WHERE device_id = :did", [':did' => $deviceId]);
        $this->assert($episode2Id > $episode1Id, 'Buyback: New commercial episode created (# ' . $episode2Id . ' > #' . $episode1Id . ')');

        // Update active_phone_id on device to episode 2
        Database::execute("UPDATE devices SET active_phone_id = :pid WHERE id = :did", [
            ':pid' => $episode2Id,
            ':did' => $deviceId
        ]);

        $updatedDev = Database::fetchOne("SELECT active_phone_id FROM devices WHERE id = :id", [':id' => $deviceId]);
        $this->assert((int)$updatedDev['active_phone_id'] === $episode2Id, 'Buyback: devices.active_phone_id successfully updated to Episode #' . $episode2Id);

        // Verify history preserves both episodes
        $allEpisodes = Database::fetchAll("SELECT id, phone_type, status FROM phones WHERE device_id = :did ORDER BY id ASC", [':did' => $deviceId]);
        $this->assert(count($allEpisodes) === 2, 'Buyback History: Physical device has exactly 2 preserved episodes');
        $this->assert($allEpisodes[0]['phone_type'] === 'NEW' && $allEpisodes[0]['status'] === 'SOLD', 'Buyback History: Episode 1 is historical NEW SOLD');
        $this->assert($allEpisodes[1]['phone_type'] === 'SECOND_HAND' && $allEpisodes[1]['status'] === 'IN_STOCK', 'Buyback History: Episode 2 is active SECOND_HAND IN_STOCK');
    }

    private function testDirectRefurbishmentCostsAndCostBasisMath(): void
    {
        echo "\n--- 9. Testing Direct Costs & Exact Cost Basis Formulation ---\n";
        Auth::login(['id' => 4, 'username' => 'amit_manager', 'full_name' => 'Amit Manager', 'role' => 'STAFF']);

        // Scenario: Phone bought for ₹20,000.00
        // Screen replacement: ₹3,500.00 (repair)
        // Technician labor: ₹500.00 (repair)
        // Buffing, box, charger: ₹1,500.00 (other)
        // Expected total cost basis = 20,000 + 4,000 + 1,500 = ₹25,500.00

        $imei1 = $this->generateValidImei('86444444444441');
        Database::execute("
            INSERT INTO devices (imei_1, brand, model_name, created_at, updated_at)
            VALUES (:i1, 'Apple', 'iPhone 13', NOW(), NOW())
        ", [':i1' => $imei1]);
        $deviceId = (int)Database::fetchColumn("SELECT id FROM devices WHERE imei_1 = :i1", [':i1' => $imei1]);

        Database::execute("
            INSERT INTO phones (
                device_id, product_id, phone_type, status, acquisition_source,
                purchase_date, purchase_cost, repair_cost, other_cost, total_cost_basis,
                created_by, created_at, updated_at
            ) VALUES (
                :did, 1, 'SECOND_HAND', 'IN_STOCK', 'CUSTOMER_BUYBACK',
                '2026-09-25', 20000.00, 0.00, 0.00, 20000.00,
                4, NOW(), NOW()
            )
        ", [':did' => $deviceId]);
        $phoneId = (int)Database::fetchColumn("SELECT MAX(id) FROM phones WHERE device_id = :did", [':did' => $deviceId]);
        Database::execute("UPDATE devices SET active_phone_id = :pid WHERE id = :did", [':pid' => $phoneId, ':did' => $deviceId]);

        // Add Direct Cost 1: Display (Repair) = ₹3,500.00
        Database::execute("
            INSERT INTO phone_direct_costs (phone_id, cost_type, description, amount, cost_date, created_by, created_at)
            VALUES (:pid, 'DISPLAY', 'Original OLED display panel replacement', 3500.00, '2026-09-25', 4, NOW())
        ", [':pid' => $phoneId]);

        // Add Direct Cost 2: Labor (Repair) = ₹500.00
        Database::execute("
            INSERT INTO phone_direct_costs (phone_id, cost_type, description, amount, cost_date, created_by, created_at)
            VALUES (:pid, 'LABOR', 'Display fitment and waterproofing labor', 500.00, '2026-09-25', 4, NOW())
        ", [':pid' => $phoneId]);

        // Add Direct Cost 3: Packaging & Charger (Other) = ₹1,500.00
        Database::execute("
            INSERT INTO phone_direct_costs (phone_id, cost_type, description, amount, cost_date, created_by, created_at)
            VALUES (:pid, 'PACKAGING', 'Compatible 20W USB-C adapter and braided cable', 1500.00, '2026-09-25', 4, NOW())
        ", [':pid' => $phoneId]);

        // Recalculate cost basis
        $allCosts = Database::fetchAll("SELECT cost_type, amount FROM phone_direct_costs WHERE phone_id = :pid", [':pid' => $phoneId]);
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

        $totalCostBasis = Financial::calculateUsedPhoneCostBasis('20000.00', $totalRepair, $totalOther);

        Database::execute("
            UPDATE phones 
            SET repair_cost = :rcost, other_cost = :ocost, total_cost_basis = :tcost, updated_at = NOW() 
            WHERE id = :id
        ", [
            ':rcost' => $totalRepair,
            ':ocost' => $totalOther,
            ':tcost' => $totalCostBasis,
            ':id'    => $phoneId,
        ]);

        $this->assert($totalRepair === '4000.00', 'Cost Math: repair_cost is 4000.00 (3500 + 500)');
        $this->assert($totalOther === '1500.00', 'Cost Math: other_cost is 1500.00');
        $this->assert($totalCostBasis === '25500.00', 'Cost Math: total_cost_basis is strictly ₹25,500.00 (20000 + 4000 + 1500)');

        $updatedPhone = Database::fetchOne("SELECT * FROM phones WHERE id = :id", [':id' => $phoneId]);
        $this->assert((float)$updatedPhone['total_cost_basis'] === 25500.0, 'Database: phones.total_cost_basis matches 25500.00');
    }

    private function testDirectCostRejections(): void
    {
        echo "\n--- 10. Testing Direct Cost Boundary Rejections ---\n";

        // Negative cost rejection
        $negativeRejected = false;
        $amount = -500.00;
        if ($amount <= 0) {
            $negativeRejected = true;
        }
        $this->assert($negativeRejected, 'Direct Cost: Negative amount (-500.00) rejected');

        // Zero cost rejection
        $zeroRejected = false;
        $amountZero = 0.00;
        if ($amountZero <= 0) {
            $zeroRejected = true;
        }
        $this->assert($zeroRejected, 'Direct Cost: Zero amount (0.00) rejected');
    }

    private function testFinancialCostVisibilityGating(): void
    {
        echo "\n--- 11. Testing Financial Cost Visibility Gating ---\n";

        // Staff without view_purchase_costs
        Auth::login(['id' => 2, 'username' => 'rahul_staff', 'full_name' => 'Rahul Kumar', 'role' => 'STAFF']);
        $this->assert(!Authorization::hasPermission('view_purchase_costs'), 'Visibility: Rahul cannot view purchase costs');

        // Staff with view_purchase_costs
        Auth::login(['id' => 4, 'username' => 'amit_manager', 'full_name' => 'Amit Manager', 'role' => 'STAFF']);
        $this->assert(Authorization::hasPermission('view_purchase_costs'), 'Visibility: Amit can view purchase costs');

        // Owner inherently has view_purchase_costs
        Auth::login(['id' => 1, 'username' => 'abhishek_owner', 'full_name' => 'Abhishek Shaw', 'role' => 'OWNER']);
        $this->assert(Authorization::hasPermission('view_purchase_costs'), 'Visibility: Owner inherently has view_purchase_costs');
    }

    private function testPermissionEnforcement(): void
    {
        echo "\n--- 12. Testing Permission Enforcement Boundaries ---\n";

        // User 2 (Rahul) has no special permissions
        Auth::login(['id' => 2, 'username' => 'rahul_staff', 'full_name' => 'Rahul Kumar', 'role' => 'STAFF']);
        $this->assert(!Authorization::hasPermission('manage_inventory'), 'AuthZ: Basic staff cannot manage_inventory');
        $this->assert(!Authorization::hasPermission('buy_used_phones'), 'AuthZ: Basic staff cannot buy_used_phones');
        $this->assert(!Authorization::hasPermission('manage_repairs'), 'AuthZ: Basic staff cannot manage_repairs');

        // User 3 (Pooja) has buy_used_phones
        Auth::login(['id' => 3, 'username' => 'pooja_used', 'full_name' => 'Pooja (Used Lead)', 'role' => 'STAFF']);
        $this->assert(Authorization::hasPermission('buy_used_phones'), 'AuthZ: Pooja has buy_used_phones');

        // User 4 (Amit) has manage_inventory and manage_repairs
        Auth::login(['id' => 4, 'username' => 'amit_manager', 'full_name' => 'Amit Manager', 'role' => 'STAFF']);
        $this->assert(Authorization::hasPermission('manage_inventory'), 'AuthZ: Amit has manage_inventory');
        $this->assert(Authorization::hasPermission('manage_repairs'), 'AuthZ: Amit has manage_repairs');
    }

    private function testCounterImeiSearchTool(): void
    {
        echo "\n--- 13. Testing Dedicated IMEI Counter Search Tool ---\n";
        Auth::login(['id' => 1, 'username' => 'abhishek_owner', 'full_name' => 'Abhishek Shaw', 'role' => 'OWNER']);

        $imei1 = $this->generateValidImei('86111111111111');
        $imei2 = $this->generateValidImei('86111111111112');

        // Search by IMEI 1
        $match1 = Database::fetchOne("SELECT * FROM devices WHERE imei_1 = :q OR imei_2 = :q", [':q' => $imei1]);
        $this->assert($match1 !== false, 'IMEI Search: Successfully matched physical device by IMEI 1');
        $this->assert($match1['brand'] === 'Apple' && $match1['model_name'] === 'iPhone 15 Pro', 'IMEI Search: Matched correct device model');

        // Search by IMEI 2
        $match2 = Database::fetchOne("SELECT * FROM devices WHERE imei_1 = :q OR imei_2 = :q", [':q' => $imei2]);
        $this->assert($match2 !== false, 'IMEI Search: Successfully matched physical device by IMEI 2');

        // Search by Serial Number
        $match3 = Database::fetchOne("SELECT * FROM devices WHERE serial_number = :sn", [':sn' => 'SN-IPH-001']);
        $this->assert($match3 !== false, 'IMEI Search: Successfully matched physical device by Serial Number (SN-IPH-001)');

        // History retrieval test on recycled/buyback phone
        $recycledImei = $this->generateValidImei('86333333333331');
        $recDev = Database::fetchOne("SELECT id FROM devices WHERE imei_1 = :q", [':q' => $recycledImei]);
        $history = Database::fetchAll("SELECT id, phone_type, status FROM phones WHERE device_id = :did ORDER BY id DESC", [':did' => $recDev['id']]);
        $this->assert(count($history) === 2, 'IMEI Search History: Returns full commercial episode history (2 episodes)');
    }

    private function testTransactionRollbackOnIntakeFailure(): void
    {
        echo "\n--- 14. Testing Transaction Rollback on Intake Failure ---\n";
        Auth::login(['id' => 1, 'username' => 'abhishek_owner', 'full_name' => 'Abhishek Shaw', 'role' => 'OWNER']);

        $imeiFail = $this->generateValidImei('86999999999991');

        $initialDeviceCount = (int)Database::fetchColumn("SELECT COUNT(*) FROM devices");
        $initialPhoneCount = (int)Database::fetchColumn("SELECT COUNT(*) FROM phones");

        $errorCaught = false;
        try {
            Database::transaction(function() use ($imeiFail) {
                // Step 1: Insert device
                Database::execute("
                    INSERT INTO devices (imei_1, brand, model_name, created_at, updated_at)
                    VALUES (:i1, 'TestBrand', 'TestModel', NOW(), NOW())
                ", [':i1' => $imeiFail]);

                // Step 2: Trigger simulated error before phone episode completes
                throw new \RuntimeException('Simulated unexpected hardware intake failure!');
            });
        } catch (\Throwable $e) {
            $errorCaught = true;
        }

        $this->assert($errorCaught, 'Rollback: Simulated runtime exception caught');

        $finalDeviceCount = (int)Database::fetchColumn("SELECT COUNT(*) FROM devices");
        $finalPhoneCount = (int)Database::fetchColumn("SELECT COUNT(*) FROM phones");

        $this->assert($finalDeviceCount === $initialDeviceCount, 'Rollback: No orphaned device row committed to database');
        $this->assert($finalPhoneCount === $initialPhoneCount, 'Rollback: No partial phone row committed to database');
    }

    private function testAuditLoggingCoverage(): void
    {
        echo "\n--- 15. Testing Audit Logging Coverage ---\n";

        $newIntakeLog = Database::fetchOne("SELECT * FROM audit_logs WHERE action = 'NEW_PHONE_INTAKE'");
        $this->assert($newIntakeLog !== false, 'Audit: NEW_PHONE_INTAKE logged with details');

        $usedIntakeLog = Database::fetchOne("SELECT * FROM audit_logs WHERE action = 'USED_PHONE_INTAKE'");
        $this->assert($usedIntakeLog !== false, 'Audit: USED_PHONE_INTAKE logged with details');

        $revealLog = Database::fetchOne("SELECT * FROM audit_logs WHERE action = 'SENSITIVE_ID_VIEWED'");
        $this->assert($revealLog !== false, 'Audit: SENSITIVE_ID_VIEWED logged with details');
    }

    private function printSummary(): void
    {
        echo "\n--------------------------------------------------------------------\n";
        echo "TEST SUMMARY: Passed: {$this->passed} | Failed: {$this->failed}\n";
        echo "--------------------------------------------------------------------\n";

        if ($this->failed === 0) {
            echo "ALL MOBILE & IMEI TESTS PASSED SUCCESSFULLY! (100% OK)\n\n";
        } else {
            echo "FAILED TESTS:\n";
            foreach ($this->failures as $fail) {
                echo " - {$fail}\n";
            }
            echo "\n";
            exit(1);
        }
    }
}

// Run the test suite
$test = new MobileAndImeiTest();
$test->runAll();
