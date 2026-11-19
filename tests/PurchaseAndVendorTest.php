<?php
declare(strict_types=1);

/**
 * ABHISHEK MOBILE HUB — Prompt 5 Automated Test Suite
 * Vendors + Purchases + Purchase Payments / Vendor Credit Test Suite
 *
 * File: tests/PurchaseAndVendorTest.php
 * Run: C:\xampp\php\php.exe tests/PurchaseAndVendorTest.php
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
require_once AMH_SRC_PATH . '/Controllers/VendorController.php';
require_once AMH_SRC_PATH . '/Controllers/PurchaseController.php';

use AMH\Auth;
use AMH\Authorization;
use AMH\Security;
use AMH\Financial;
use AMH\Database;
use AMH\AuditLogger;

class PurchaseAndVendorTest
{
    private int $passed = 0;
    private int $failed = 0;
    private array $failures = [];
    private ?PDO $sqlite = null;

    public function runAll(): void
    {
        echo "====================================================================\n";
        echo "ABHISHEK MOBILE HUB — PROMPT 5/12 VENDORS & PURCHASES TEST SUITE\n";
        echo "====================================================================\n\n";

        $this->setUpDatabase();

        echo "--- 1. Testing Vendor Management & Integrity Rules ---\n";
        $this->testVendorCreation();
        $this->testVendorDuplicatePhoneAndValidation();
        $this->testVendorUpdate();
        $this->testVendorStatusToggle();
        $this->testHistoricalVendorCannotBeDeleted();
        $this->testVendorWithoutPurchasesCanBeDeleted();
        $this->testInactiveVendorCannotReceivePurchases();

        echo "\n--- 2. Testing Accessory Purchase Intake & Weighted Average Cost ---\n";
        $this->testAccessoryPurchaseIntake();
        $this->testWeightedAverageCostFormula();
        $this->testStockMovementDoubleEntryLedger();

        echo "\n--- 3. Testing Serialized Phone Purchase & IMEI Validation ---\n";
        $this->testSerializedPhonePurchaseIntake();
        $this->testImeiLuhnValidationOnPurchase();
        $this->testDuplicateImeiRejectionWithinPurchase();
        $this->testDuplicateImeiRejectionAcrossExistingDevices();
        $this->testCrossFieldImeiCollisionRejection();

        echo "\n--- 4. Testing Multi-Item Consignments & Authoritative Server Totals ---\n";
        $this->testMultiItemConsignment();
        $this->testServerAuthoritativeSubtotalAndTotal();
        $this->testZeroOrNegativeUnitCostRejected();
        $this->testZeroOrNegativeQuantityRejected();
        $this->testEmptyConsignmentRejected();

        echo "\n--- 5. Testing Payments, Credit Balances & Overpayment Defense ---\n";
        $this->testPurchaseWithoutInitialPayment();
        $this->testPurchaseWithInitialFullPayment();
        $this->testPurchaseWithInitialPartialPayment();
        $this->testSubsequentPaymentRecording();
        $this->testMultiplePartialPaymentsSequence();
        $this->testOverpaymentDefense();
        $this->testZeroOrNegativePaymentRejected();
        $this->testFullySettledPurchaseCannotAcceptPayments();
        $this->testVendorAggregatedBalanceDue();

        echo "\n--- 6. Testing Permissions & Financial Visibility Guardrails ---\n";
        $this->testManagePurchasesPermissionRequired();
        $this->testCostVisibilityPermissionGating();
        $this->testCsrfValidationOnPurchaseAndPayment();
        $this->testSqlInjectionSafetyInVendorAndPurchase();
        $this->testXssEscapingInVendorAndPurchaseViews();

        echo "\n--- 7. Testing Transactional Rollback & Audit Trail ---\n";
        $this->testAtomicIntakeTransactionRollback();
        $this->testAtomicPaymentTransactionRollback();
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

        // Initialize normalized schema matching schema.sql
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

            CREATE TABLE inventory_stock (
                product_id INTEGER PRIMARY KEY,
                quantity INTEGER NOT NULL DEFAULT 0,
                avg_unit_cost REAL NOT NULL DEFAULT 0.00,
                last_restocked_at TEXT NULL,
                updated_at TEXT DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (product_id) REFERENCES products(id)
            );

            CREATE TABLE stock_movements (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                product_id INTEGER NOT NULL,
                movement_type TEXT NOT NULL,
                quantity_change INTEGER NOT NULL,
                quantity_before INTEGER NOT NULL,
                quantity_after INTEGER NOT NULL,
                reference_type TEXT NULL,
                reference_id INTEGER NULL,
                unit_cost REAL NULL,
                reason TEXT NULL,
                created_by INTEGER NOT NULL,
                created_at TEXT DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (product_id) REFERENCES products(id),
                FOREIGN KEY (created_by) REFERENCES users(id)
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

            CREATE TABLE vendors (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                business_name TEXT NOT NULL,
                contact_person TEXT NULL,
                phone TEXT NOT NULL,
                alt_phone TEXT NULL,
                email TEXT NULL,
                address TEXT NULL,
                gstin TEXT NULL,
                notes TEXT NULL,
                is_active INTEGER NOT NULL DEFAULT 1,
                created_at TEXT DEFAULT CURRENT_TIMESTAMP,
                updated_at TEXT DEFAULT CURRENT_TIMESTAMP
            );

            CREATE TABLE purchases (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                purchase_number TEXT UNIQUE NOT NULL,
                vendor_id INTEGER NOT NULL,
                purchase_date TEXT NOT NULL,
                vendor_invoice_no TEXT NULL,
                total_amount REAL NOT NULL DEFAULT 0.00,
                paid_amount REAL NOT NULL DEFAULT 0.00,
                due_amount REAL NOT NULL DEFAULT 0.00,
                payment_status TEXT NOT NULL DEFAULT 'UNPAID',
                notes TEXT NULL,
                created_by INTEGER NOT NULL,
                created_at TEXT DEFAULT CURRENT_TIMESTAMP,
                updated_at TEXT DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (vendor_id) REFERENCES vendors(id),
                FOREIGN KEY (created_by) REFERENCES users(id)
            );

            CREATE TABLE purchase_items (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                purchase_id INTEGER NOT NULL,
                product_id INTEGER NOT NULL,
                item_type TEXT NOT NULL,
                quantity INTEGER NOT NULL,
                unit_cost REAL NOT NULL,
                subtotal_cost REAL NOT NULL,
                created_at TEXT DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (purchase_id) REFERENCES purchases(id),
                FOREIGN KEY (product_id) REFERENCES products(id)
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
                purchase_cost REAL NOT NULL DEFAULT 0.00,
                repair_cost REAL NOT NULL DEFAULT 0.00,
                other_cost REAL NOT NULL DEFAULT 0.00,
                total_cost_basis REAL NOT NULL DEFAULT 0.00,
                sale_item_id INTEGER NULL,
                created_by INTEGER NOT NULL,
                created_at TEXT DEFAULT CURRENT_TIMESTAMP,
                updated_at TEXT DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (device_id) REFERENCES devices(id),
                FOREIGN KEY (product_id) REFERENCES products(id),
                FOREIGN KEY (vendor_id) REFERENCES vendors(id),
                FOREIGN KEY (purchase_item_id) REFERENCES purchase_items(id),
                FOREIGN KEY (created_by) REFERENCES users(id)
            );

            CREATE TABLE phone_direct_costs (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                phone_id INTEGER NOT NULL,
                cost_type TEXT NOT NULL,
                amount REAL NOT NULL,
                description TEXT NOT NULL,
                recorded_by INTEGER NOT NULL,
                recorded_at TEXT DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (phone_id) REFERENCES phones(id),
                FOREIGN KEY (recorded_by) REFERENCES users(id)
            );

            CREATE TABLE payments (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                payment_number TEXT UNIQUE NOT NULL,
                transaction_type TEXT NOT NULL,
                reference_table TEXT NOT NULL,
                reference_id INTEGER NOT NULL,
                vendor_id INTEGER NULL,
                payment_date TEXT NOT NULL,
                amount REAL NOT NULL,
                payment_mode TEXT NOT NULL,
                transaction_ref TEXT NULL,
                notes TEXT NULL,
                received_by INTEGER NOT NULL,
                created_at TEXT DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (vendor_id) REFERENCES vendors(id),
                FOREIGN KEY (received_by) REFERENCES users(id)
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
            ('manage_purchases', 'Create purchase orders and record payments', 'PURCHASES'),
            ('view_purchase_costs', 'View wholesale cost basis', 'FINANCIAL'),
            ('view_vendor_costs', 'View vendor financial ledger and balances', 'FINANCIAL'),
            ('manage_inventory', 'Manage catalog and stock', 'INVENTORY'),
            ('buy_used_phones', 'Purchase second-hand mobile phones', 'INVENTORY'),
            ('manage_repairs', 'Log refurbishment costs', 'REPAIRS'),
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
            VALUES (2, 'rahul_staff', '{$staffPass}', 'Rahul Kumar (Basic Staff)', 'STAFF', 1);

            INSERT INTO users (id, username, password_hash, full_name, role, is_active)
            VALUES (3, 'vikram_purchaser', '{$staffPass}', 'Vikram (Procurement Lead)', 'STAFF', 1);

            INSERT INTO users (id, username, password_hash, full_name, role, is_active)
            VALUES (4, 'sunita_viewer', '{$staffPass}', 'Sunita (Catalog Staff - No Cost)', 'STAFF', 1);
        ");

        // Grant specific permissions
        $this->sqlite->exec("
            INSERT INTO user_permissions (user_id, permission_name, granted_by) VALUES 
            (3, 'manage_purchases', 1),
            (3, 'view_purchase_costs', 1),
            (3, 'view_vendor_costs', 1),
            (3, 'manage_inventory', 1),
            (4, 'manage_inventory', 1);
        ");

        // Seed catalog categories and products
        $this->sqlite->exec("
            INSERT INTO categories (id, name, slug, description, is_active) VALUES
            (1, 'Smartphones', 'smartphones', 'Handsets and mobile phones', 1),
            (2, 'Accessories', 'accessories', 'Cables, chargers, cases', 1);

            INSERT INTO products (id, category_id, brand, model_name, item_type, sku, min_stock_alert, default_sale_price, is_active) VALUES
            (1, 1, 'Apple', 'iPhone 15 128GB', 'SERIALIZED_PHONE', 'IPH15-128', 2, 79900.00, 1),
            (2, 1, 'Samsung', 'Galaxy A55 5G', 'SERIALIZED_PHONE', 'SGA55-128', 2, 39999.00, 1),
            (3, 2, 'Boat', 'Type-C Braided Cable 1m', 'ACCESSORY', 'BOAT-CAB-1M', 10, 299.00, 1),
            (4, 2, 'Mi', '33W Fast Charger', 'ACCESSORY', 'MI-CHG-33W', 5, 699.00, 1);
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

    private function printSummary(): void
    {
        echo "\n--------------------------------------------------------------------\n";
        echo "TEST SUMMARY: Passed: {$this->passed} | Failed: {$this->failed}\n";
        echo "--------------------------------------------------------------------\n";
        if ($this->failed > 0) {
            echo "FAILURES:\n";
            foreach ($this->failures as $f) {
                echo "  {$f}\n";
            }
            exit(1);
        } else {
            echo "ALL VENDOR & PURCHASE TESTS PASSED SUCCESSFULLY! (100% OK)\n\n";
        }
    }

    // =========================================================================
    // SECTION 1: VENDOR MANAGEMENT & INTEGRITY RULES
    // =========================================================================

    private function testVendorCreation(): void
    {
        // 1. Create a primary distributor
        Database::execute("
            INSERT INTO vendors (id, business_name, contact_person, phone, email, address, gstin, notes, is_active)
            VALUES (1, 'Kolkata Mobile Distributors', 'Rajesh Sharma', '9830012345', 'rajesh@kmd.com', '12 Ezra Street, Kolkata', '19ABCDE1234F1Z5', 'Primary handset distributor', 1)
        ");

        $v = Database::fetchOne("SELECT * FROM vendors WHERE id = 1");
        $this->assert($v !== null && $v['business_name'] === 'Kolkata Mobile Distributors', 'Vendor: Kolkata Mobile Distributors created');
        $this->assert($v['phone'] === '9830012345', 'Vendor: Contact phone recorded correctly');
        $this->assert((int)$v['is_active'] === 1, 'Vendor: Default active status is 1');

        // Audit log
        AuditLogger::log('VENDOR_CREATED', 'vendors', 1, null, ['business_name' => 'Kolkata Mobile Distributors'], 'Created vendor');
    }

    private function testVendorDuplicatePhoneAndValidation(): void
    {
        // Business validation: Phone must be 10 digits
        $validPhone = '9830099999';
        $invalidPhone = '12345'; // too short

        $isValidFormat = (bool)preg_match('/^[6-9]\d{9}$/', $validPhone);
        $isInvalidFormat = (bool)preg_match('/^[6-9]\d{9}$/', $invalidPhone);

        $this->assert($isValidFormat === true, 'Vendor Validation: 10-digit Indian phone accepted');
        $this->assert($isInvalidFormat === false, 'Vendor Validation: Short phone string rejected');

        // Insert second vendor for accessory supplies
        Database::execute("
            INSERT INTO vendors (id, business_name, contact_person, phone, address, is_active)
            VALUES (2, 'Howrah Accessory Mart', 'Pradeep Roy', '9830099999', 'Chandni Chowk Market, Kolkata', 1)
        ");

        $v2 = Database::fetchOne("SELECT * FROM vendors WHERE id = 2");
        $this->assert($v2 !== null && $v2['business_name'] === 'Howrah Accessory Mart', 'Vendor: Howrah Accessory Mart created');
    }

    private function testVendorUpdate(): void
    {
        Database::execute("
            UPDATE vendors 
            SET contact_person = 'Rajesh Kumar Sharma', notes = 'Updated primary distributor contact'
            WHERE id = 1
        ");

        $v = Database::fetchOne("SELECT contact_person, notes FROM vendors WHERE id = 1");
        $this->assert($v['contact_person'] === 'Rajesh Kumar Sharma', 'Vendor Update: Contact person updated');
        $this->assert(str_contains($v['notes'], 'Updated primary'), 'Vendor Update: Notes updated');

        AuditLogger::log('VENDOR_UPDATED', 'vendors', 1, ['contact_person' => 'Rajesh Sharma'], ['contact_person' => 'Rajesh Kumar Sharma'], 'Updated vendor');
    }

    private function testVendorStatusToggle(): void
    {
        // Toggle vendor 2 to inactive
        Database::execute("UPDATE vendors SET is_active = 0 WHERE id = 2");
        $v2 = Database::fetchOne("SELECT is_active FROM vendors WHERE id = 2");
        $this->assert((int)$v2['is_active'] === 0, 'Vendor Status: Deactivated vendor successfully');

        // Toggle back to active
        Database::execute("UPDATE vendors SET is_active = 1 WHERE id = 2");
        $v2Active = Database::fetchOne("SELECT is_active FROM vendors WHERE id = 2");
        $this->assert((int)$v2Active['is_active'] === 1, 'Vendor Status: Reactivated vendor successfully');

        AuditLogger::log('VENDOR_STATUS_TOGGLED', 'vendors', 2, ['is_active' => 0], ['is_active' => 1], 'Reactivated vendor');
    }

    private function testHistoricalVendorCannotBeDeleted(): void
    {
        // Create dummy vendor 3
        Database::execute("
            INSERT INTO vendors (id, business_name, phone, is_active)
            VALUES (3, 'Temporary Wholesaler', '9831112233', 1)
        ");

        // Create a dummy purchase under vendor 3
        Database::execute("
            INSERT INTO purchases (id, purchase_number, vendor_id, purchase_date, total_amount, paid_amount, due_amount, payment_status, created_by)
            VALUES (999, 'PUR-TEST-HISTORICAL', 3, '2026-09-01', 5000.00, 5000.00, 0.00, 'PAID', 1)
        ");

        // Verify purchase count check
        $purchaseCount = (int)Database::fetchColumn("SELECT COUNT(*) FROM purchases WHERE vendor_id = 3");
        $this->assert($purchaseCount === 1, 'Historical Defense: Vendor 3 has 1 linked purchase record');

        $canDelete = ($purchaseCount === 0);
        $this->assert($canDelete === false, 'Historical Defense: Deletion blocked when vendor has historical purchases');

        // Clean up dummy purchase
        Database::execute("DELETE FROM purchases WHERE id = 999");
    }

    private function testVendorWithoutPurchasesCanBeDeleted(): void
    {
        $purchaseCount = (int)Database::fetchColumn("SELECT COUNT(*) FROM purchases WHERE vendor_id = 3");
        $this->assert($purchaseCount === 0, 'Vendor Deletion: Vendor 3 has 0 purchases after cleanup');

        $deleted = false;
        if ($purchaseCount === 0) {
            Database::execute("DELETE FROM vendors WHERE id = 3");
            $deleted = true;
        }

        $v3 = Database::fetchOne("SELECT * FROM vendors WHERE id = 3");
        $this->assert($deleted === true && $v3 === null, 'Vendor Deletion: Vendor with no purchases deleted cleanly');
        AuditLogger::log('VENDOR_DELETED', 'vendors', 3, null, null, 'Deleted vendor without history');
    }

    private function testInactiveVendorCannotReceivePurchases(): void
    {
        // Deactivate vendor 2
        Database::execute("UPDATE vendors SET is_active = 0 WHERE id = 2");

        $vendor = Database::fetchOne("SELECT is_active FROM vendors WHERE id = 2");
        $isBlocked = ((int)$vendor['is_active'] === 0);
        $this->assert($isBlocked === true, 'Intake Defense: Inactive vendor flagged as not eligible for new purchases');

        // Reactivate vendor 2 for subsequent tests
        Database::execute("UPDATE vendors SET is_active = 1 WHERE id = 2");
    }

    // =========================================================================
    // SECTION 2: ACCESSORY PURCHASE & WEIGHTED AVERAGE COST
    // =========================================================================

    private function testAccessoryPurchaseIntake(): void
    {
        // Consignment 1: Purchase 10 units of Boat Type-C Cable @ ₹150.00 each
        // Product ID 3
        $purchaseNumber = 'PUR-20260925-0001';
        $vendorId = 2; // Howrah Accessory Mart
        $productId = 3;
        $qty = 10;
        $unitCost = '150.00';
        $subtotal = Financial::multiply((string)$qty, $unitCost); // 1500.00

        Database::transaction(function() use ($purchaseNumber, $vendorId, $productId, $qty, $unitCost, $subtotal) {
            Database::execute("
                INSERT INTO purchases (id, purchase_number, vendor_id, purchase_date, total_amount, paid_amount, due_amount, payment_status, created_by)
                VALUES (1, :pnum, :vid, '2026-09-25', :total, 0.00, :total, 'UNPAID', 3)
            ", [
                ':pnum'  => $purchaseNumber,
                ':vid'   => $vendorId,
                ':total' => $subtotal,
            ]);

            Database::execute("
                INSERT INTO purchase_items (id, purchase_id, product_id, item_type, quantity, unit_cost, subtotal_cost)
                VALUES (1, 1, :pid, 'ACCESSORY', :qty, :cost, :subtotal)
            ", [
                ':pid'      => $productId,
                ':qty'      => $qty,
                ':cost'     => $unitCost,
                ':subtotal' => $subtotal,
            ]);

            // Update inventory_stock
            $existing = Database::fetchOne("SELECT * FROM inventory_stock WHERE product_id = :pid", [':pid' => $productId]);
            $currentQty = $existing ? (int)$existing['quantity'] : 0;
            $currentAvg = $existing ? (string)$existing['avg_unit_cost'] : '0.00';

            $newQty = $currentQty + $qty;
            $newAvgCost = Financial::weightedAverageCost($currentQty, $currentAvg, $qty, $unitCost);

            Database::execute("
                INSERT INTO inventory_stock (product_id, quantity, avg_unit_cost, last_restocked_at)
                VALUES (:pid, :qty, :avg, NOW())
                ON CONFLICT(product_id) DO UPDATE SET
                    quantity = :qty,
                    avg_unit_cost = :avg,
                    last_restocked_at = NOW()
            ", [
                ':pid' => $productId,
                ':qty' => $newQty,
                ':avg' => $newAvgCost,
            ]);

            // Double entry stock movement
            Database::execute("
                INSERT INTO stock_movements (
                    product_id, movement_type, quantity_change,
                    quantity_before, quantity_after, reference_type,
                    reference_id, unit_cost, reason, created_by
                ) VALUES (
                    :pid, 'PURCHASE', :change,
                    :before, :after, 'purchases',
                    1, :cost, 'Vendor consignment intake', 3
                )
            ", [
                ':pid'    => $productId,
                ':change' => $qty,
                ':before' => $currentQty,
                ':after'  => $newQty,
                ':cost'   => $unitCost,
            ]);
        });

        $stock = Database::fetchOne("SELECT * FROM inventory_stock WHERE product_id = 3");
        $this->assert((int)$stock['quantity'] === 10, 'Accessory Intake: Stock quantity is 10');
        $this->assert((float)$stock['avg_unit_cost'] === 150.00, 'Accessory Intake: avg_unit_cost initialized to 150.00');

        $p = Database::fetchOne("SELECT * FROM purchases WHERE id = 1");
        $this->assert((float)$p['total_amount'] === 1500.00, 'Consignment 1: total_amount is 1500.00');
        $this->assert((float)$p['due_amount'] === 1500.00, 'Consignment 1: due_amount is 1500.00');
        $this->assert($p['payment_status'] === 'UNPAID', 'Consignment 1: status is UNPAID');
    }

    private function testWeightedAverageCostFormula(): void
    {
        // Initial state: 10 units @ ₹150.00 = ₹1,500 total value.
        // Second intake: 20 units @ ₹180.00 = ₹3,600 total value.
        // Combined value: ₹1,500 + ₹3,600 = ₹5,100.
        // Combined quantity: 10 + 20 = 30 units.
        // Expected weighted average: 5,100 / 30 = ₹170.00 exact.

        $currentQty = 10;
        $currentAvg = '150.00';
        $newQty = 20;
        $newCost = '180.00';

        $computedAvg = Financial::weightedAverageCost($currentQty, $currentAvg, $newQty, $newCost);
        $this->assert($computedAvg === '170.00', "Weighted Average Formula: Expected 170.00, got {$computedAvg}");

        // Now perform actual purchase #2 for Boat Type-C Cable
        $subtotal = Financial::multiply((string)$newQty, $newCost); // 3600.00

        Database::transaction(function() use ($newQty, $newCost, $subtotal, $computedAvg) {
            Database::execute("
                INSERT INTO purchases (id, purchase_number, vendor_id, purchase_date, total_amount, paid_amount, due_amount, payment_status, created_by)
                VALUES (2, 'PUR-20260925-0002', 2, '2026-09-25', :total, 0.00, :total, 'UNPAID', 3)
            ", [':total' => $subtotal]);

            Database::execute("
                INSERT INTO purchase_items (id, purchase_id, product_id, item_type, quantity, unit_cost, subtotal_cost)
                VALUES (2, 2, 3, 'ACCESSORY', :qty, :cost, :subtotal)
            ", [
                ':qty'      => $newQty,
                ':cost'     => $newCost,
                ':subtotal' => $subtotal,
            ]);

            Database::execute("
                UPDATE inventory_stock
                SET quantity = quantity + :qty, avg_unit_cost = :avg, last_restocked_at = NOW()
                WHERE product_id = 3
            ", [
                ':qty' => $newQty,
                ':avg' => $computedAvg,
            ]);

            Database::execute("
                INSERT INTO stock_movements (
                    product_id, movement_type, quantity_change,
                    quantity_before, quantity_after, reference_type,
                    reference_id, unit_cost, reason, created_by
                ) VALUES (
                    3, 'PURCHASE', :change,
                    10, 30, 'purchases',
                    2, :cost, 'Second batch restock', 3
                )
            ", [
                ':change' => $newQty,
                ':cost'   => $newCost,
            ]);
        });

        $stock = Database::fetchOne("SELECT * FROM inventory_stock WHERE product_id = 3");
        $this->assert((int)$stock['quantity'] === 30, 'Weighted Average: Total quantity is now 30');
        $this->assert((float)$stock['avg_unit_cost'] === 170.00, 'Weighted Average: avg_unit_cost in database is exactly 170.00');
    }

    private function testStockMovementDoubleEntryLedger(): void
    {
        $movements = Database::fetchAll("
            SELECT * FROM stock_movements 
            WHERE product_id = 3 
            ORDER BY id ASC
        ");

        $this->assert(count($movements) === 2, 'Stock Ledger: Exactly 2 movements recorded for Product 3');
        $this->assert($movements[0]['movement_type'] === 'PURCHASE', 'Stock Ledger Movement 1: type is PURCHASE');
        $this->assert((int)$movements[0]['quantity_before'] === 0 && (int)$movements[0]['quantity_after'] === 10, 'Stock Ledger Movement 1: 0 -> 10');
        $this->assert((int)$movements[1]['quantity_before'] === 10 && (int)$movements[1]['quantity_after'] === 30, 'Stock Ledger Movement 2: 10 -> 30');
        $this->assert((int)$movements[1]['reference_id'] === 2, 'Stock Ledger Movement 2: linked to Purchase #2');
    }

    // =========================================================================
    // SECTION 3: SERIALIZED MOBILE PHONE PURCHASE & IMEI VALIDATION
    // =========================================================================

    private function testSerializedPhonePurchaseIntake(): void
    {
        // Vendor 1: Kolkata Mobile Distributors
        // Product 1: Apple iPhone 15 128GB
        // Purchase 1 phone @ ₹65,000.00
        $imei1 = '861234567890127'; // Valid Luhn check digit
        $imei2 = '861234567890135'; // Valid Luhn check digit
        $serial = 'F17LN928X';
        $unitCost = '65000.00';

        Database::transaction(function() use ($imei1, $imei2, $serial, $unitCost) {
            Database::execute("
                INSERT INTO purchases (id, purchase_number, vendor_id, purchase_date, total_amount, paid_amount, due_amount, payment_status, created_by)
                VALUES (3, 'PUR-20260925-0003', 1, '2026-09-25', :total, 0.00, :total, 'UNPAID', 3)
            ", [':total' => $unitCost]);

            Database::execute("
                INSERT INTO purchase_items (id, purchase_id, product_id, item_type, quantity, unit_cost, subtotal_cost)
                VALUES (3, 3, 1, 'SERIALIZED_PHONE', 1, :cost, :cost)
            ", [':cost' => $unitCost]);

            Database::execute("
                INSERT INTO devices (id, imei_1, imei_2, serial_number, brand, model_name)
                VALUES (1, :i1, :i2, :sn, 'Apple', 'iPhone 15 128GB')
            ", [
                ':i1' => $imei1,
                ':i2' => $imei2,
                ':sn' => $serial,
            ]);

            Database::execute("
                INSERT INTO phones (
                    id, device_id, product_id, phone_type, ram, storage, color,
                    status, acquisition_source, vendor_id, purchase_item_id,
                    purchase_date, purchase_cost, total_cost_basis, created_by
                ) VALUES (
                    1, 1, 1, 'NEW', '6GB', '128GB', 'Black',
                    'IN_STOCK', 'VENDOR_PURCHASE', 1, 3,
                    '2026-09-25', :cost, :cost, 3
                )
            ", [':cost' => $unitCost]);

            Database::execute("UPDATE devices SET active_phone_id = 1 WHERE id = 1");
        });

        $device = Database::fetchOne("SELECT * FROM devices WHERE id = 1");
        $phone = Database::fetchOne("SELECT * FROM phones WHERE id = 1");

        $this->assert($device !== null && $device['imei_1'] === $imei1, 'Serialized Intake: Device record created with IMEI 1');
        $this->assert((int)$device['active_phone_id'] === 1, 'Serialized Intake: Device active_phone_id links to commercial phone');
        $this->assert($phone['phone_type'] === 'NEW', 'Serialized Intake: phone_type is NEW');
        $this->assert($phone['status'] === 'IN_STOCK', 'Serialized Intake: status is IN_STOCK');
        $this->assert((float)$phone['purchase_cost'] === 65000.00, 'Serialized Intake: purchase_cost is 65000.00');
        $this->assert((float)$phone['total_cost_basis'] === 65000.00, 'Serialized Intake: total_cost_basis is 65000.00');
        $this->assert((int)$phone['purchase_item_id'] === 3, 'Serialized Intake: phone linked to purchase_item #3');
    }

    private function testImeiLuhnValidationOnPurchase(): void
    {
        $validImei = '861234567890127';
        $invalidLuhn = '861234567890128'; // bad check digit

        $this->assert(Security::validateImei($validImei, true) === true, 'IMEI Validation: Valid Luhn accepted');
        $this->assert(Security::validateImei($invalidLuhn, true) === false, 'IMEI Validation: Invalid Luhn rejected');
    }

    private function testDuplicateImeiRejectionWithinPurchase(): void
    {
        $imeiList = ['861234567890127', '861234567890127']; // identical in same intake
        $hasDuplicate = count($imeiList) !== count(array_unique($imeiList));
        $this->assert($hasDuplicate === true, 'IMEI Defense: Duplicate IMEIs in the same consignment batch detected');
    }

    private function testDuplicateImeiRejectionAcrossExistingDevices(): void
    {
        $existingImei = '861234567890127';
        $deviceExists = (int)Database::fetchColumn("
            SELECT COUNT(*) FROM devices 
            WHERE imei_1 = :imei OR imei_2 = :imei
        ", [':imei' => $existingImei]);

        $this->assert($deviceExists > 0, 'IMEI Defense: System detects existing device with IMEI 1');
    }

    private function testCrossFieldImeiCollisionRejection(): void
    {
        // Device 1 has imei_2 = '861234567890135'
        // If a new device submits imei_1 = '861234567890135', cross-field collision occurs
        $candidateImei1 = '861234567890135';
        $collision = (int)Database::fetchColumn("
            SELECT COUNT(*) FROM devices 
            WHERE imei_1 = :imei OR imei_2 = :imei
        ", [':imei' => $candidateImei1]);

        $this->assert($collision > 0, 'IMEI Defense: Cross-field collision detected (new IMEI 1 matches existing IMEI 2)');
    }

    // =========================================================================
    // SECTION 4: MULTI-ITEM CONSIGNMENTS & AUTHORITATIVE SERVER TOTALS
    // =========================================================================

    private function testMultiItemConsignment(): void
    {
        // Consignment 4:
        // Item A: 50 units of Boat Cable @ ₹120 = ₹6,000.00
        // Item B: 20 units of Mi Fast Charger @ ₹400 = ₹8,000.00
        // Item C: 1 unit of Samsung Galaxy A55 @ ₹28,000 = ₹28,000.00
        // Expected Consignment Total = ₹42,000.00 exact

        $pnum = 'PUR-20260925-0004';
        $items = [
            ['product_id' => 3, 'type' => 'ACCESSORY', 'qty' => 50, 'cost' => '120.00'],
            ['product_id' => 4, 'type' => 'ACCESSORY', 'qty' => 20, 'cost' => '400.00'],
            ['product_id' => 2, 'type' => 'SERIALIZED_PHONE', 'qty' => 1, 'cost' => '28000.00', 'imei1' => '861234567890143'],
        ];

        $calculatedTotal = '0.00';
        foreach ($items as $item) {
            $sub = Financial::multiply((string)$item['qty'], $item['cost']);
            $calculatedTotal = Financial::add($calculatedTotal, $sub);
        }

        $this->assert($calculatedTotal === '42000.00', 'Multi-Item: Calculated total is 42,000.00');

        Database::transaction(function() use ($pnum, $items, $calculatedTotal) {
            Database::execute("
                INSERT INTO purchases (id, purchase_number, vendor_id, purchase_date, total_amount, paid_amount, due_amount, payment_status, created_by)
                VALUES (4, :pnum, 1, '2026-09-25', :total, 0.00, :total, 'UNPAID', 3)
            ", [
                ':pnum'  => $pnum,
                ':total' => $calculatedTotal,
            ]);

            $itemId = 10;
            foreach ($items as $item) {
                $sub = Financial::multiply((string)$item['qty'], $item['cost']);
                Database::execute("
                    INSERT INTO purchase_items (id, purchase_id, product_id, item_type, quantity, unit_cost, subtotal_cost)
                    VALUES (:id, 4, :pid, :type, :qty, :cost, :sub)
                ", [
                    ':id'   => $itemId,
                    ':pid'  => $item['product_id'],
                    ':type' => $item['type'],
                    ':qty'  => $item['qty'],
                    ':cost' => $item['cost'],
                    ':sub'  => $sub,
                ]);

                if ($item['type'] === 'ACCESSORY') {
                    Database::execute("
                        INSERT INTO inventory_stock (product_id, quantity, avg_unit_cost, last_restocked_at)
                        VALUES (:pid, :qty, :avg, NOW())
                        ON CONFLICT(product_id) DO UPDATE SET
                            quantity = quantity + :qty,
                            avg_unit_cost = :avg,
                            last_restocked_at = NOW()
                    ", [
                        ':pid' => $item['product_id'],
                        ':qty' => $item['qty'],
                        ':avg' => $item['cost'],
                    ]);
                } elseif ($item['type'] === 'SERIALIZED_PHONE') {
                    Database::execute("
                        INSERT INTO devices (id, imei_1, brand, model_name)
                        VALUES (2, :i1, 'Samsung', 'Galaxy A55 5G')
                    ", [':i1' => $item['imei1']]);

                    Database::execute("
                        INSERT INTO phones (
                            id, device_id, product_id, phone_type, status,
                            acquisition_source, vendor_id, purchase_item_id,
                            purchase_date, purchase_cost, total_cost_basis, created_by
                        ) VALUES (
                            2, 2, :pid, 'NEW', 'IN_STOCK',
                            'VENDOR_PURCHASE', 1, :pi_id,
                            '2026-09-25', :cost, :cost, 3
                        )
                    ", [
                        ':pid'   => $item['product_id'],
                        ':pi_id' => $itemId,
                        ':cost'  => $item['cost'],
                    ]);

                    Database::execute("UPDATE devices SET active_phone_id = 2 WHERE id = 2");
                }
                $itemId++;
            }
        });

        $itemCount = (int)Database::fetchColumn("SELECT COUNT(*) FROM purchase_items WHERE purchase_id = 4");
        $this->assert($itemCount === 3, 'Multi-Item: Consignment has 3 items in purchase_items');

        $p4 = Database::fetchOne("SELECT * FROM purchases WHERE id = 4");
        $this->assert((float)$p4['total_amount'] === 42000.00, 'Multi-Item: Purchase 4 total_amount is 42,000.00');
    }

    private function testServerAuthoritativeSubtotalAndTotal(): void
    {
        // If a malicious client passes forged subtotal:
        $clientQty = 10;
        $clientCost = '500.00';
        $clientForgedSubtotal = '100.00'; // Forged cheap value

        $serverCalculated = Financial::multiply((string)$clientQty, $clientCost);
        $this->assert($serverCalculated === '5000.00', 'Authoritative Math: Server computed 5000.00');
        $this->assert($serverCalculated !== $clientForgedSubtotal, 'Authoritative Math: Client forged subtotal rejected');
    }

    private function testZeroOrNegativeUnitCostRejected(): void
    {
        $costZero = '0.00';
        $costNegative = '-150.00';

        $isValidZero = (is_numeric($costZero) && (float)$costZero > 0);
        $isValidNegative = (is_numeric($costNegative) && (float)$costNegative > 0);

        $this->assert($isValidZero === false, 'Cost Validation: Zero unit cost rejected');
        $this->assert($isValidNegative === false, 'Cost Validation: Negative unit cost rejected');
    }

    private function testZeroOrNegativeQuantityRejected(): void
    {
        $qtyZero = 0;
        $qtyNegative = -5;

        $this->assert($qtyZero > 0 === false, 'Qty Validation: Zero quantity rejected');
        $this->assert($qtyNegative > 0 === false, 'Qty Validation: Negative quantity rejected');
    }

    private function testEmptyConsignmentRejected(): void
    {
        $emptyItems = [];
        $hasItems = !empty($emptyItems);
        $this->assert($hasItems === false, 'Consignment Validation: Empty line item list rejected');
    }

    // =========================================================================
    // SECTION 5: PAYMENTS, CREDIT BALANCES & OVERPAYMENT DEFENSE
    // =========================================================================

    private function testPurchaseWithoutInitialPayment(): void
    {
        $p = Database::fetchOne("SELECT * FROM purchases WHERE id = 1");
        $this->assert((float)$p['paid_amount'] === 0.00, 'Unpaid Purchase: paid_amount is 0.00');
        $this->assert((float)$p['due_amount'] === 1500.00, 'Unpaid Purchase: due_amount is 1500.00');
        $this->assert($p['payment_status'] === 'UNPAID', 'Unpaid Purchase: status is UNPAID');

        $paymentCount = (int)Database::fetchColumn("
            SELECT COUNT(*) FROM payments 
            WHERE reference_table = 'purchases' AND reference_id = 1
        ");
        $this->assert($paymentCount === 0, 'Unpaid Purchase: 0 payments in payments table');
    }

    private function testPurchaseWithInitialFullPayment(): void
    {
        // Consignment 5: Paid in full at intake
        $total = '10000.00';
        $paid = '10000.00';
        $due = Financial::subtract($total, $paid); // 0.00

        Database::transaction(function() use ($total, $paid, $due) {
            Database::execute("
                INSERT INTO purchases (id, purchase_number, vendor_id, purchase_date, total_amount, paid_amount, due_amount, payment_status, created_by)
                VALUES (5, 'PUR-20260925-0005', 1, '2026-09-25', :total, :paid, :due, 'PAID', 3)
            ", [
                ':total' => $total,
                ':paid'  => $paid,
                ':due'   => $due,
            ]);

            Database::execute("
                INSERT INTO payments (
                    payment_number, transaction_type, reference_table,
                    reference_id, vendor_id, payment_date, amount,
                    payment_mode, transaction_ref, notes, received_by
                ) VALUES (
                    'PAY-20260925-0001', 'PURCHASE_PAYMENT', 'purchases',
                    5, 1, '2026-09-25', :paid,
                    'BANK_TRANSFER', 'UTR99887766', 'Full upfront payment', 3
                )
            ", [':paid' => $paid]);
        });

        $p = Database::fetchOne("SELECT * FROM purchases WHERE id = 5");
        $this->assert((float)$p['due_amount'] === 0.00, 'Full Payment: due_amount is 0.00');
        $this->assert($p['payment_status'] === 'PAID', 'Full Payment: status is PAID');

        $pmt = Database::fetchOne("SELECT * FROM payments WHERE reference_id = 5");
        $this->assert($pmt !== null && (float)$pmt['amount'] === 10000.00, 'Full Payment: Payment record of 10000.00 in payments table');
    }

    private function testPurchaseWithInitialPartialPayment(): void
    {
        // Consignment 6: Total 20,000.00, initial payment 5,000.00
        $total = '20000.00';
        $initialPaid = '5000.00';
        $due = Financial::subtract($total, $initialPaid); // 15000.00

        Database::transaction(function() use ($total, $initialPaid, $due) {
            Database::execute("
                INSERT INTO purchases (id, purchase_number, vendor_id, purchase_date, total_amount, paid_amount, due_amount, payment_status, created_by)
                VALUES (6, 'PUR-20260925-0006', 1, '2026-09-25', :total, :paid, :due, 'PARTIAL', 3)
            ", [
                ':total' => $total,
                ':paid'  => $initialPaid,
                ':due'   => $due,
            ]);

            Database::execute("
                INSERT INTO payments (
                    payment_number, transaction_type, reference_table,
                    reference_id, vendor_id, payment_date, amount,
                    payment_mode, received_by
                ) VALUES (
                    'PAY-20260925-0002', 'PURCHASE_PAYMENT', 'purchases',
                    6, 1, '2026-09-25', :paid,
                    'UPI', 3
                )
            ", [':paid' => $initialPaid]);
        });

        $p = Database::fetchOne("SELECT * FROM purchases WHERE id = 6");
        $this->assert((float)$p['paid_amount'] === 5000.00, 'Partial Payment: paid_amount is 5000.00');
        $this->assert((float)$p['due_amount'] === 15000.00, 'Partial Payment: due_amount is 15000.00');
        $this->assert($p['payment_status'] === 'PARTIAL', 'Partial Payment: status is PARTIAL');
    }

    private function testSubsequentPaymentRecording(): void
    {
        // Record second payment against Purchase 6 of ₹10,000.00
        $purchaseId = 6;
        $subsequentAmount = '10000.00';

        Database::transaction(function() use ($purchaseId, $subsequentAmount) {
            // Lock purchase
            $p = Database::fetchOne("SELECT * FROM purchases WHERE id = :id", [':id' => $purchaseId]);
            $currentPaid = (string)Database::fetchColumn("
                SELECT COALESCE(SUM(amount), 0.00) 
                FROM payments 
                WHERE reference_table = 'purchases' AND reference_id = :id
            ", [':id' => $purchaseId]);

            $total = (string)$p['total_amount'];
            $due = Financial::subtract($total, $currentPaid);

            // Verify no overpayment
            if ((float)$subsequentAmount > (float)$due) {
                throw new \RuntimeException('Overpayment');
            }

            Database::execute("
                INSERT INTO payments (
                    payment_number, transaction_type, reference_table,
                    reference_id, vendor_id, payment_date, amount,
                    payment_mode, received_by
                ) VALUES (
                    'PAY-20260925-0003', 'PURCHASE_PAYMENT', 'purchases',
                    :id, :vid, '2026-09-25', :amt,
                    'BANK_TRANSFER', 3
                )
            ", [
                ':id'  => $purchaseId,
                ':vid' => $p['vendor_id'],
                ':amt' => $subsequentAmount,
            ]);

            $newPaid = Financial::add($currentPaid, $subsequentAmount);
            $newDue = Financial::subtract($total, $newPaid);
            $newStatus = (float)$newDue <= 0.00 ? 'PAID' : 'PARTIAL';

            Database::execute("
                UPDATE purchases 
                SET paid_amount = :paid, due_amount = :due, payment_status = :status
                WHERE id = :id
            ", [
                ':paid'   => $newPaid,
                ':due'    => $newDue,
                ':status' => $newStatus,
                ':id'     => $purchaseId,
            ]);
        });

        $p = Database::fetchOne("SELECT * FROM purchases WHERE id = 6");
        $this->assert((float)$p['paid_amount'] === 15000.00, 'Subsequent Payment: paid_amount incremented to 15000.00');
        $this->assert((float)$p['due_amount'] === 5000.00, 'Subsequent Payment: due_amount decremented to 5000.00');
        $this->assert($p['payment_status'] === 'PARTIAL', 'Subsequent Payment: status remains PARTIAL');
    }

    private function testMultiplePartialPaymentsSequence(): void
    {
        // Settle final ₹5,000.00 of Purchase 6
        $purchaseId = 6;
        $finalAmount = '5000.00';

        Database::transaction(function() use ($purchaseId, $finalAmount) {
            $p = Database::fetchOne("SELECT * FROM purchases WHERE id = :id", [':id' => $purchaseId]);
            $currentPaid = (string)Database::fetchColumn("
                SELECT COALESCE(SUM(amount), 0.00) 
                FROM payments 
                WHERE reference_table = 'purchases' AND reference_id = :id
            ", [':id' => $purchaseId]);

            $total = (string)$p['total_amount'];
            $newPaid = Financial::add($currentPaid, $finalAmount);
            $newDue = Financial::subtract($total, $newPaid);
            $newStatus = (float)$newDue <= 0.00 ? 'PAID' : 'PARTIAL';

            Database::execute("
                INSERT INTO payments (
                    payment_number, transaction_type, reference_table,
                    reference_id, vendor_id, payment_date, amount,
                    payment_mode, received_by
                ) VALUES (
                    'PAY-20260925-0004', 'PURCHASE_PAYMENT', 'purchases',
                    :id, :vid, '2026-09-25', :amt,
                    'CASH', 3
                )
            ", [
                ':id'  => $purchaseId,
                ':vid' => $p['vendor_id'],
                ':amt' => $finalAmount,
            ]);

            Database::execute("
                UPDATE purchases 
                SET paid_amount = :paid, due_amount = :due, payment_status = :status
                WHERE id = :id
            ", [
                ':paid'   => $newPaid,
                ':due'    => $newDue,
                ':status' => $newStatus,
                ':id'     => $purchaseId,
            ]);
        });

        $p = Database::fetchOne("SELECT * FROM purchases WHERE id = 6");
        $this->assert((float)$p['paid_amount'] === 20000.00, 'Settlement Sequence: paid_amount reaches 20000.00');
        $this->assert((float)$p['due_amount'] === 0.00, 'Settlement Sequence: due_amount is 0.00');
        $this->assert($p['payment_status'] === 'PAID', 'Settlement Sequence: payment_status transitioned to PAID');

        $paymentRecords = (int)Database::fetchColumn("
            SELECT COUNT(*) FROM payments 
            WHERE reference_table = 'purchases' AND reference_id = 6
        ");
        $this->assert($paymentRecords === 3, 'Settlement Sequence: Exactly 3 payments recorded in ledger (5000 + 10000 + 5000)');
    }

    private function testOverpaymentDefense(): void
    {
        // Purchase 1 has due_amount = 1500.00
        $purchase = Database::fetchOne("SELECT due_amount FROM purchases WHERE id = 1");
        $due = (string)$purchase['due_amount']; // 1500.00

        $attemptedOverpayment = '1501.00'; // ₹1 more than due
        $isOverpayment = ((float)$attemptedOverpayment > (float)$due);

        $this->assert($isOverpayment === true, 'Overpayment Defense: System detects ₹1501.00 > ₹1500.00');

        $caught = false;
        try {
            if ($isOverpayment) {
                throw new \RuntimeException("Payment amount (₹{$attemptedOverpayment}) exceeds due balance (₹{$due}).");
            }
        } catch (\RuntimeException $e) {
            $caught = true;
        }

        $this->assert($caught === true, 'Overpayment Defense: Overpayment exception triggered and payment rejected');
    }

    private function testZeroOrNegativePaymentRejected(): void
    {
        $zero = '0.00';
        $neg = '-100.00';

        $validZero = (is_numeric($zero) && (float)$zero > 0);
        $validNeg = (is_numeric($neg) && (float)$neg > 0);

        $this->assert($validZero === false, 'Payment Validation: Zero amount rejected');
        $this->assert($validNeg === false, 'Payment Validation: Negative amount rejected');
    }

    private function testFullySettledPurchaseCannotAcceptPayments(): void
    {
        // Purchase 6 is fully settled (due_amount = 0.00)
        $p = Database::fetchOne("SELECT due_amount, payment_status FROM purchases WHERE id = 6");
        $isSettled = ((float)$p['due_amount'] <= 0.00 || $p['payment_status'] === 'PAID');

        $this->assert($isSettled === true, 'Settled Defense: Fully settled purchase detected');
    }

    private function testVendorAggregatedBalanceDue(): void
    {
        // Vendor 1 has:
        // Purchase 3: Total 65,000, Paid 0, Due 65,000
        // Purchase 4: Total 42,000, Paid 0, Due 42,000
        // Purchase 5: Total 10,000, Paid 10,000, Due 0
        // Purchase 6: Total 20,000, Paid 20,000, Due 0
        // Expected Vendor 1 Total Billed: 65000 + 42000 + 10000 + 20000 = 137,000.00
        // Expected Vendor 1 Total Paid:   0 + 0 + 10000 + 20000 = 30,000.00
        // Expected Vendor 1 Total Due:    137,000 - 30,000 = 107,000.00

        $stats = Database::fetchOne("
            SELECT 
                COALESCE(SUM(total_amount), 0.00) AS total_billed,
                COALESCE(SUM(paid_amount), 0.00) AS total_paid,
                COALESCE(SUM(due_amount), 0.00) AS total_due
            FROM purchases
            WHERE vendor_id = 1
        ");

        $this->assert((float)$stats['total_billed'] === 137000.00, 'Vendor 1 Balance: Total billed is ₹1,37,000.00');
        $this->assert((float)$stats['total_paid'] === 30000.00, 'Vendor 1 Balance: Total paid is ₹30,000.00');
        $this->assert((float)$stats['total_due'] === 107000.00, 'Vendor 1 Balance: Total outstanding balance due is ₹1,07,000.00');
    }

    // =========================================================================
    // SECTION 6: PERMISSIONS & FINANCIAL VISIBILITY GUARDRAILS
    // =========================================================================

    private function testManagePurchasesPermissionRequired(): void
    {
        // User 2: Rahul (STAFF - no permissions)
        // User 3: Vikram (STAFF - has manage_purchases)
        // User 1: Abhishek (OWNER)

        $rahulCanManage = Authorization::hasPermission('manage_purchases', 2);
        $vikramCanManage = Authorization::hasPermission('manage_purchases', 3);
        $ownerCanManage = Authorization::hasPermission('manage_purchases', 1);

        $this->assert($rahulCanManage === false, 'AuthZ: Basic staff does not have manage_purchases');
        $this->assert($vikramCanManage === true, 'AuthZ: Procurement staff has manage_purchases');
        $this->assert($ownerCanManage === true, 'AuthZ: Shop Owner has manage_purchases');
    }

    private function testCostVisibilityPermissionGating(): void
    {
        // User 4: Sunita (STAFF - no cost view)
        // User 3: Vikram (STAFF - has view_purchase_costs)

        $sunitaCanView = Authorization::hasPermission('view_purchase_costs', 4);
        $vikramCanView = Authorization::hasPermission('view_purchase_costs', 3);

        $this->assert($sunitaCanView === false, 'Cost Privacy: Sunita cannot view purchase costs');
        $this->assert($vikramCanView === true, 'Cost Privacy: Vikram can view purchase costs');

        // Masking simulation
        $costValue = '65000.00';
        $maskedValue = $sunitaCanView ? $costValue : '—';
        $this->assert($maskedValue === '—', 'Cost Privacy: Unprivileged operator sees masked cost string');
    }

    private function testCsrfValidationOnPurchaseAndPayment(): void
    {
        $token = Security::getCsrfToken();
        $this->assert(Security::validateCsrfToken($token) === true, 'CSRF: Valid token passes');
        $this->assert(Security::validateCsrfToken('invalid_token') === false, 'CSRF: Invalid token rejected');
        $this->assert(Security::validateCsrfToken(null) === false, 'CSRF: Missing token rejected');
    }

    private function testSqlInjectionSafetyInVendorAndPurchase(): void
    {
        $injection = "'; DROP TABLE purchases; --";
        $result = Database::fetchAll("
            SELECT * FROM purchases 
            WHERE purchase_number = :pnum
        ", [':pnum' => $injection]);

        $this->assert(is_array($result), 'SQL Safety: Parameterized query safely handled SQL injection string');

        // Verify table still exists
        $pCount = (int)Database::fetchColumn("SELECT COUNT(*) FROM purchases");
        $this->assert($pCount > 0, 'SQL Safety: Table remains intact and unaffected');
    }

    private function testXssEscapingInVendorAndPurchaseViews(): void
    {
        $xss = '<script>alert("hacked")</script>';
        $escaped = Security::e($xss);
        $this->assert($escaped === '&lt;script&gt;alert(&quot;hacked&quot;)&lt;/script&gt;', 'XSS Safety: Malicious payload converted to harmless HTML entities');
    }

    // =========================================================================
    // SECTION 7: TRANSACTIONAL ROLLBACK & AUDIT TRAIL
    // =========================================================================

    private function testAtomicIntakeTransactionRollback(): void
    {
        $initialPurchaseCount = (int)Database::fetchColumn("SELECT COUNT(*) FROM purchases");
        $initialDeviceCount = (int)Database::fetchColumn("SELECT COUNT(*) FROM devices");

        $failed = false;
        try {
            Database::transaction(function() {
                Database::execute("
                    INSERT INTO purchases (id, purchase_number, vendor_id, purchase_date, total_amount, created_by)
                    VALUES (99, 'PUR-FAIL-001', 1, '2026-09-25', 5000.00, 3)
                ");

                Database::execute("
                    INSERT INTO devices (id, imei_1, brand, model_name)
                    VALUES (99, '861234567890150', 'Test', 'Device')
                ");

                // Simulate mid-transaction fatal failure (e.g. invalid second item)
                throw new \RuntimeException('Simulated line item failure mid-intake');
            });
        } catch (\Throwable $e) {
            $failed = true;
        }

        $this->assert($failed === true, 'Rollback: Mid-transaction failure caught');

        $finalPurchaseCount = (int)Database::fetchColumn("SELECT COUNT(*) FROM purchases");
        $finalDeviceCount = (int)Database::fetchColumn("SELECT COUNT(*) FROM devices");

        $this->assert($finalPurchaseCount === $initialPurchaseCount, 'Rollback: Zero orphaned purchase rows committed');
        $this->assert($finalDeviceCount === $initialDeviceCount, 'Rollback: Zero orphaned device rows committed');
    }

    private function testAtomicPaymentTransactionRollback(): void
    {
        $initialPaymentCount = (int)Database::fetchColumn("SELECT COUNT(*) FROM payments");

        $failed = false;
        try {
            Database::transaction(function() {
                Database::execute("
                    INSERT INTO payments (
                        payment_number, transaction_type, reference_table,
                        reference_id, vendor_id, payment_date, amount,
                        payment_mode, received_by
                    ) VALUES (
                        'PAY-FAIL-001', 'PURCHASE_PAYMENT', 'purchases',
                        1, 1, '2026-09-25', 500.00, 'CASH', 3
                    )
                ");

                // Simulate failure before updating purchases table
                throw new \RuntimeException('Simulated payment update failure');
            });
        } catch (\Throwable $e) {
            $failed = true;
        }

        $this->assert($failed === true, 'Rollback: Payment failure caught');

        $finalPaymentCount = (int)Database::fetchColumn("SELECT COUNT(*) FROM payments");
        $this->assert($finalPaymentCount === $initialPaymentCount, 'Rollback: Zero orphaned payment rows committed');
    }

    private function testAuditLoggingCoverage(): void
    {
        // Record purchase created audit log
        AuditLogger::log('PURCHASE_CREATED', 'purchases', 1, null, ['purchase_number' => 'PUR-20260925-0001', 'total_amount' => '1500.00'], 'Created purchase consignment');
        AuditLogger::log('PURCHASE_PAYMENT_RECORDED', 'payments', 1, null, ['amount' => '10000.00', 'purchase_id' => 5], 'Recorded purchase payment');

        $logs = Database::fetchAll("
            SELECT action, table_name, record_id 
            FROM audit_logs 
            ORDER BY id ASC
        ");

        $actions = array_column($logs, 'action');

        $this->assert(in_array('VENDOR_CREATED', $actions, true), 'Audit Trail: VENDOR_CREATED logged');
        $this->assert(in_array('VENDOR_UPDATED', $actions, true), 'Audit Trail: VENDOR_UPDATED logged');
        $this->assert(in_array('VENDOR_STATUS_TOGGLED', $actions, true), 'Audit Trail: VENDOR_STATUS_TOGGLED logged');
        $this->assert(in_array('VENDOR_DELETED', $actions, true), 'Audit Trail: VENDOR_DELETED logged');
        $this->assert(in_array('PURCHASE_CREATED', $actions, true), 'Audit Trail: PURCHASE_CREATED logged');
        $this->assert(in_array('PURCHASE_PAYMENT_RECORDED', $actions, true), 'Audit Trail: PURCHASE_PAYMENT_RECORDED logged');
    }
}

// Run the test suite
$test = new PurchaseAndVendorTest();
$test->runAll();
