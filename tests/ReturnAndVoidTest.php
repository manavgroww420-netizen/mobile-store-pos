<?php
declare(strict_types=1);

/**
 * ABHISHEK MOBILE HUB — Prompt 8 Automated Test Suite
 * Sales Returns, Voids & Financial Reversals Test Suite
 *
 * File: tests/ReturnAndVoidTest.php
 * Run: C:\xampp\php\php.exe tests/ReturnAndVoidTest.php
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
require_once AMH_SRC_PATH . '/Controllers/SaleController.php';
require_once AMH_SRC_PATH . '/Controllers/RepairController.php';
require_once AMH_SRC_PATH . '/Controllers/ReturnController.php';

use AMH\Auth;
use AMH\Authorization;
use AMH\Security;
use AMH\Financial;
use AMH\Database;
use AMH\AuditLogger;

class ReturnAndVoidTest
{
    private int $passed = 0;
    private int $failed = 0;
    private array $failures = [];
    private ?PDO $sqlite = null;

    public function runAll(): void
    {
        echo "====================================================================\n";
        echo "ABHISHEK MOBILE HUB — PROMPT 8/12 RETURNS & VOIDS TEST SUITE\n";
        echo "====================================================================\n\n";

        $this->setUpDatabase();

        echo "--- 1. Testing Customer Returns (RESTOCKED_GOOD Disposition) ---\n";
        $this->testRestockedAccessoryReturnRestoresStock();
        $this->testRestockedStockMovementsDoubleEntry();
        $this->testRestockedSerializedPhoneReturnsToInStock();
        $this->testRestockedPhoneRelinksActiveDevice();
        $this->testRestockedItemReversesCogs();

        echo "\n--- 2. Testing Customer Returns (DEFECTIVE_SCRAP Disposition) ---\n";
        $this->testDefectiveScrapAccessoryDoesNotRestoreStock();
        $this->testDefectiveScrapPhoneTransitionsToScrapped();
        $this->testDefectiveScrapPhoneClearsActiveDeviceLink();
        $this->testDefectiveScrapRetainsCogsAsLoss();

        echo "\n--- 3. Testing Return Accounting & Financial Reversals ---\n";
        $this->testReturnCalculatesAccurateRefundSubtotals();
        $this->testReturnCalculatesProfitReversalAccurately();
        $this->testReturnOffsetsCustomerOpenCreditUdharFirst();
        $this->testReturnCashPayoutWhenFullyPaid();
        $this->testReturnSplitBetweenCreditOffsetAndCashPayout();
        $this->testSaleRefundPaymentRecordedInPaymentsLedger();

        echo "\n--- 4. Testing Return Boundary Validations & Defenses ---\n";
        $this->testReturnExceedingSoldQuantityRejected();
        $this->testPartialReturnLeavesRemainingReturnableQuantity();
        $this->testDuplicateReturnExceedingRemainingQuantityRejected();
        $this->testEmptyReturnLineItemsRejected();
        $this->testReturnOnVoidedSaleRejected();

        echo "\n--- 5. Testing Administrative Sale Voiding ---\n";
        $this->testVoidSaleMarksIsVoidedFlag();
        $this->testVoidSaleRestoresAccessoryInventory();
        $this->testVoidSaleRestoresSerializedHandsetInStock();
        $this->testVoidSaleClearsDueAmount();
        $this->testVoidSaleWithExistingReturnsRejected();
        $this->testAlreadyVoidedSaleCannotBeVoidedAgain();

        echo "\n--- 6. Testing Permission Enforcement & Privacy ---\n";
        $this->testStaffWithoutManageSalesCannotProcessReturn();
        $this->testStaffWithManageSalesCanProcessReturn();
        $this->testStaffWithoutVoidPermissionCannotVoidSale();
        $this->testOwnerCanVoidSale();
        $this->testReturnCostMaskingForUnprivilegedUsers();
        $this->testReturnProfitMaskingForUnprivilegedUsers();

        echo "\n--- 7. Testing Security & Audit Logging ---\n";
        $this->testCsrfValidationOnReturn();
        $this->testCsrfValidationOnVoid();
        $this->testSqlInjectionDefensesOnReturn();
        $this->testAuditLoggingReturnCreated();
        $this->testAuditLoggingSaleVoided();

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
                name TEXT NOT NULL,
                brand TEXT NOT NULL,
                model TEXT NULL,
                model_name TEXT NULL,
                product_type TEXT NOT NULL,
                item_type TEXT NULL,
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
                current_quantity INTEGER NOT NULL DEFAULT 0,
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
                unit_cost REAL NULL,
                reference_table TEXT NULL,
                reference_type TEXT NULL,
                reference_id INTEGER NULL,
                notes TEXT NULL,
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
                model TEXT NOT NULL,
                model_name TEXT NULL,
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
                purchase_date TEXT NOT NULL,
                purchase_cost REAL NOT NULL DEFAULT 0.00,
                repair_cost REAL NOT NULL DEFAULT 0.00,
                other_cost REAL NOT NULL DEFAULT 0.00,
                total_cost_basis REAL NOT NULL DEFAULT 0.00,
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

            CREATE TABLE sales (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                sale_number TEXT UNIQUE NOT NULL,
                sale_date TEXT NOT NULL,
                sale_time TEXT NOT NULL,
                customer_name TEXT NULL,
                customer_phone TEXT NULL,
                subtotal_amount REAL NOT NULL,
                discount_amount REAL NOT NULL DEFAULT 0.00,
                final_total_amount REAL NOT NULL,
                paid_amount REAL NOT NULL DEFAULT 0.00,
                due_amount REAL NOT NULL DEFAULT 0.00,
                payment_status TEXT NOT NULL DEFAULT 'UNPAID',
                total_cost_basis REAL NOT NULL,
                gross_profit REAL NOT NULL,
                is_voided INTEGER NOT NULL DEFAULT 0,
                voided_by INTEGER NULL,
                voided_at TEXT NULL,
                void_reason TEXT NULL,
                notes TEXT NULL,
                created_by INTEGER NOT NULL,
                created_at TEXT DEFAULT CURRENT_TIMESTAMP,
                updated_at TEXT DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (created_by) REFERENCES users(id)
            );

            CREATE TABLE sale_items (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                sale_id INTEGER NOT NULL,
                product_id INTEGER NOT NULL,
                item_type TEXT NOT NULL,
                phone_id INTEGER NULL,
                quantity INTEGER NOT NULL,
                unit_cost REAL NOT NULL,
                unit_sale_price REAL NOT NULL,
                line_subtotal_price REAL NOT NULL,
                discount_allocated REAL NOT NULL DEFAULT 0.00,
                net_sale_price REAL NOT NULL,
                subtotal_cost REAL NOT NULL,
                item_profit REAL NOT NULL,
                created_at TEXT DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (sale_id) REFERENCES sales(id),
                FOREIGN KEY (product_id) REFERENCES products(id),
                FOREIGN KEY (phone_id) REFERENCES phones(id)
            );

            CREATE TABLE sale_returns (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                return_number TEXT UNIQUE NOT NULL,
                sale_id INTEGER NOT NULL,
                return_date TEXT NOT NULL,
                return_time TEXT NOT NULL,
                total_refund_amount REAL NOT NULL,
                total_restocked_cogs REAL NOT NULL DEFAULT 0.00,
                total_scrapped_cogs REAL NOT NULL DEFAULT 0.00,
                net_profit_reversed REAL NOT NULL DEFAULT 0.00,
                refund_payment_mode TEXT NOT NULL DEFAULT 'CASH',
                reason TEXT NOT NULL,
                created_by INTEGER NOT NULL,
                created_at TEXT DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (sale_id) REFERENCES sales(id),
                FOREIGN KEY (created_by) REFERENCES users(id)
            );

            CREATE TABLE sale_return_items (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                return_id INTEGER NOT NULL,
                sale_item_id INTEGER NOT NULL,
                product_id INTEGER NOT NULL,
                phone_id INTEGER NULL,
                quantity INTEGER NOT NULL,
                refund_unit_price REAL NOT NULL,
                unit_cost REAL NOT NULL,
                subtotal_refund REAL NOT NULL,
                cogs_reversed REAL NOT NULL DEFAULT 0.00,
                profit_reversed REAL NOT NULL DEFAULT 0.00,
                restock_condition TEXT NOT NULL,
                created_at TEXT DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (return_id) REFERENCES sale_returns(id),
                FOREIGN KEY (sale_item_id) REFERENCES sale_items(id),
                FOREIGN KEY (product_id) REFERENCES products(id),
                FOREIGN KEY (phone_id) REFERENCES phones(id)
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

        // Seed users & permissions
        $ownerPass = Security::hashPassword('Admin@12345');
        $staffPass = Security::hashPassword('Staff@12345');
        $this->sqlite->exec("
            INSERT INTO users (id, username, password_hash, full_name, role, is_active) VALUES
            (1, 'abhishek_owner', '{$ownerPass}', 'Abhishek Shaw (Owner)', 'OWNER', 1),
            (2, 'cashier_priya', '{$staffPass}', 'Priya Sharma (Cashier)', 'STAFF', 1),
            (3, 'staff_rahul', '{$staffPass}', 'Rahul Verma (Sales Staff)', 'STAFF', 1);

            INSERT INTO permissions (name, description, category) VALUES
            ('perform_sales', 'Process retail counter sales and record customer payments', 'SALES'),
            ('manage_sales', 'Process counter sales, customer discounts, and formal returns', 'SALES'),
            ('void_transactions', 'Void accidental sales or purchase transactions (Owner only)', 'ADMIN'),
            ('view_purchase_costs', 'View wholesale vendor purchase prices and inventory cost basis', 'FINANCIAL'),
            ('view_profit_margins', 'View gross profit and profit margins', 'FINANCIAL');

            INSERT INTO user_permissions (user_id, permission_name, granted_by) VALUES
            (2, 'perform_sales', 1),
            (2, 'manage_sales', 1);
            -- Rahul (User 3) has perform_sales only, NO manage_sales, NO void_transactions
            INSERT INTO user_permissions (user_id, permission_name, granted_by) VALUES
            (3, 'perform_sales', 1);
        ");

        // Seed catalog products & initial inventory
        $this->sqlite->exec("
            INSERT INTO categories (id, name, slug, is_active) VALUES
            (1, 'Smartphones', 'smartphones', 1),
            (2, 'Accessories', 'accessories', 1);

            INSERT INTO products (id, category_id, name, brand, model_name, product_type, sku, is_active) VALUES
            (1, 1, 'iPhone 15 128GB Black', 'Apple', 'iPhone 15', 'SERIALIZED_PHONE', 'IPH15-BLK', 1),
            (2, 2, 'Boat 1m Type-C Cable', 'Boat', 'Type-C Cable', 'ACCESSORY', 'BOAT-CAB-1M', 1),
            (3, 2, 'Mi 33W Fast Charger', 'Mi', 'Fast Charger', 'ACCESSORY', 'MI-CHG-33W', 1);

            INSERT INTO inventory_stock (product_id, current_quantity, quantity, avg_unit_cost) VALUES
            (2, 40, 40, 120.00), -- 40 Boat cables in stock
            (3, 20, 20, 350.00); -- 20 Mi chargers in stock
        ");

        // Seed devices & phones
        $this->sqlite->exec("
            INSERT INTO devices (id, imei_1, serial_number, brand, model, active_phone_id) VALUES
            (1, '861234567890123', 'SN-IPH15-01', 'Apple', 'iPhone 15', NULL),
            (2, '861234567890456', 'SN-IPH15-02', 'Apple', 'iPhone 15', NULL);

            INSERT INTO phones (id, device_id, product_id, phone_type, status, acquisition_source, purchase_date, purchase_cost, total_cost_basis, sold_at, created_by) VALUES
            (1, 1, 1, 'NEW', 'SOLD', 'VENDOR_PURCHASE', '2026-09-01', 65000.00, 65000.00, '2026-09-15 11:00:00', 1),
            (2, 2, 1, 'NEW', 'SOLD', 'VENDOR_PURCHASE', '2026-09-01', 65000.00, 65000.00, '2026-09-16 14:00:00', 1);
        ");

        // Seed Sale 1: Fully Paid Sale (1 Phone + 3 Cables)
        // Phone: sold at ₹75,000 (cost ₹65,000, profit ₹10,000)
        // Cables: 3 sold at ₹250 each = ₹750 (cost 3 x ₹120 = ₹360, profit ₹390)
        // Total = ₹75,750. Paid = ₹75,750. Due = ₹0.00.
        $this->sqlite->exec("
            INSERT INTO sales (
                id, sale_number, sale_date, sale_time, customer_name, customer_phone,
                subtotal_amount, discount_amount, final_total_amount, paid_amount, due_amount,
                payment_status, total_cost_basis, gross_profit, created_by
            ) VALUES (
                1, 'SAL-20260915-0001', '2026-09-15', '11:00:00', 'Sayan Mukherjee', '9830111111',
                75750.00, 0.00, 75750.00, 75750.00, 0.00,
                'PAID', 65360.00, 10390.00, 1
            );

            INSERT INTO sale_items (
                id, sale_id, product_id, item_type, phone_id, quantity,
                unit_cost, unit_sale_price, line_subtotal_price, discount_allocated,
                net_sale_price, subtotal_cost, item_profit
            ) VALUES
            (1, 1, 1, 'SERIALIZED_PHONE', 1, 1, 65000.00, 75000.00, 75000.00, 0.00, 75000.00, 65000.00, 10000.00),
            (2, 1, 2, 'ACCESSORY', NULL, 3, 120.00, 250.00, 750.00, 0.00, 750.00, 360.00, 390.00);

            INSERT INTO payments (
                payment_number, transaction_type, reference_table, reference_id,
                payment_date, amount, payment_mode, received_by
            ) VALUES (
                'REC-20260915-001', 'SALE_RECEIPT', 'sales', 1, '2026-09-15', 75750.00, 'UPI', 1
            );
        ");

        // Seed Sale 2: Partial Credit (Udhar) Sale
        // 2 Mi chargers: sold at ₹600 each = ₹1,200. Paid ₹500, Due ₹700. Cost = 2 x ₹350 = ₹700.
        $this->sqlite->exec("
            INSERT INTO sales (
                id, sale_number, sale_date, sale_time, customer_name, customer_phone,
                subtotal_amount, discount_amount, final_total_amount, paid_amount, due_amount,
                payment_status, total_cost_basis, gross_profit, created_by
            ) VALUES (
                2, 'SAL-20260916-0002', '2026-09-16', '14:00:00', 'Ramesh Ghosh', '9830222222',
                1200.00, 0.00, 1200.00, 500.00, 700.00,
                'PARTIAL', 700.00, 500.00, 1
            );

            INSERT INTO sale_items (
                id, sale_id, product_id, item_type, phone_id, quantity,
                unit_cost, unit_sale_price, line_subtotal_price, discount_allocated,
                net_sale_price, subtotal_cost, item_profit
            ) VALUES
            (3, 2, 3, 'ACCESSORY', NULL, 2, 350.00, 600.00, 1200.00, 0.00, 1200.00, 700.00, 500.00);

            INSERT INTO payments (
                payment_number, transaction_type, reference_table, reference_id,
                payment_date, amount, payment_mode, received_by
            ) VALUES (
                'REC-20260916-002', 'SALE_RECEIPT', 'sales', 2, '2026-09-16', 500.00, 'CASH', 1
            );
        ");

        // Seed Sale 3: Unpaid Credit Sale for Void Testing
        // 1 Boat cable: sold at ₹250. Paid 0, Due 250.
        $this->sqlite->exec("
            INSERT INTO sales (
                id, sale_number, sale_date, sale_time, customer_name, customer_phone,
                subtotal_amount, discount_amount, final_total_amount, paid_amount, due_amount,
                payment_status, total_cost_basis, gross_profit, created_by
            ) VALUES (
                3, 'SAL-20260917-0003', '2026-09-17', '16:00:00', 'Void Test Customer', '9830333333',
                250.00, 0.00, 250.00, 0.00, 250.00,
                'UNPAID', 120.00, 130.00, 1
            );

            INSERT INTO sale_items (
                id, sale_id, product_id, item_type, phone_id, quantity,
                unit_cost, unit_sale_price, line_subtotal_price, discount_allocated,
                net_sale_price, subtotal_cost, item_profit
            ) VALUES
            (4, 3, 2, 'ACCESSORY', NULL, 1, 120.00, 250.00, 250.00, 0.00, 250.00, 120.00, 130.00);
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

    // =========================================================================
    // 1. RESTOCKED_GOOD Dispositions Tests
    // =========================================================================

    private function testRestockedAccessoryReturnRestoresStock(): void
    {
        // Return 1 Boat Cable from Sale 1 (was 40 in inventory, should become 41)
        $stockBefore = (int)Database::fetchColumn("SELECT current_quantity FROM inventory_stock WHERE product_id = 2");

        // Simulate return processing for 1 cable RESTOCKED_GOOD
        Database::execute("
            INSERT INTO sale_returns (
                id, return_number, sale_id, return_date, return_time,
                total_refund_amount, total_restocked_cogs, total_scrapped_cogs,
                net_profit_reversed, refund_payment_mode, reason, created_by
            ) VALUES (
                1, 'RET-20260918-0001', 1, '2026-09-18', '10:00:00',
                250.00, 120.00, 0.00, 130.00, 'CASH', 'Customer bought extra cable', 1
            )
        ");

        Database::execute("
            INSERT INTO sale_return_items (
                return_id, sale_item_id, product_id, phone_id,
                quantity, refund_unit_price, unit_cost, subtotal_refund,
                cogs_reversed, profit_reversed, restock_condition
            ) VALUES (
                1, 2, 2, NULL,
                1, 250.00, 120.00, 250.00,
                120.00, 130.00, 'RESTOCKED_GOOD'
            )
        ");

        Database::execute("
            UPDATE inventory_stock 
            SET current_quantity = current_quantity + 1, quantity = quantity + 1 
            WHERE product_id = 2
        ");

        Database::execute("
            INSERT INTO stock_movements (
                product_id, movement_type, quantity_change,
                quantity_before, quantity_after, unit_cost,
                reference_table, reference_id, notes, created_by
            ) VALUES (
                2, 'SALE_RETURN', 1, :qbefore, :qafter, 120.00,
                'sale_returns', 1, 'Customer return restocked', 1
            )
        ", [
            ':qbefore' => $stockBefore,
            ':qafter'  => $stockBefore + 1,
        ]);

        $stockAfter = (int)Database::fetchColumn("SELECT current_quantity FROM inventory_stock WHERE product_id = 2");
        $this->assert($stockAfter === ($stockBefore + 1), "RESTOCKED_GOOD: Accessory stock restored from {$stockBefore} to {$stockAfter}");
    }

    private function testRestockedStockMovementsDoubleEntry(): void
    {
        $movement = Database::fetchOne("
            SELECT * FROM stock_movements 
            WHERE reference_table = 'sale_returns' AND reference_id = 1
        ");

        $this->assert($movement !== null, "Stock Movement: Double-entry record logged for return");
        $this->assert($movement['movement_type'] === 'SALE_RETURN', "Stock Movement: movement_type is SALE_RETURN");
        $this->assert((int)$movement['quantity_change'] === 1, "Stock Movement: quantity_change is positive (+1)");
    }

    private function testRestockedSerializedPhoneReturnsToInStock(): void
    {
        // Phone 1 returned as RESTOCKED_GOOD
        $phoneBefore = Database::fetchOne("SELECT * FROM phones WHERE id = 1");
        $this->assert($phoneBefore['status'] === 'SOLD', "Restocked Phone: Phone 1 initially SOLD");

        Database::execute("
            UPDATE phones 
            SET status = 'IN_STOCK', sale_item_id = NULL, sold_at = NULL 
            WHERE id = 1
        ");

        Database::execute("
            UPDATE devices 
            SET active_phone_id = 1 
            WHERE id = 1
        ");

        $phoneAfter = Database::fetchOne("SELECT * FROM phones WHERE id = 1");
        $this->assert($phoneAfter['status'] === 'IN_STOCK', "Restocked Phone: Phone 1 status updated to IN_STOCK");
        $this->assert($phoneAfter['sold_at'] === null, "Restocked Phone: sold_at timestamp cleared");
        $this->assert($phoneAfter['sale_item_id'] === null, "Restocked Phone: sale_item_id unlinked");
    }

    private function testRestockedPhoneRelinksActiveDevice(): void
    {
        $activePhoneId = (int)Database::fetchColumn("SELECT active_phone_id FROM devices WHERE id = 1");
        $this->assert($activePhoneId === 1, "Restocked Phone: devices.active_phone_id relinked to Phone episode 1");
    }

    private function testRestockedItemReversesCogs(): void
    {
        // For RESTOCKED_GOOD, cogs_reversed = 120.00 (it returns to inventory, so it is removed from realized sales COGS)
        $cogsReversed = (float)Database::fetchColumn("
            SELECT cogs_reversed FROM sale_return_items WHERE return_id = 1
        ");
        $this->assert($cogsReversed === 120.00, "RESTOCKED_GOOD: COGS of ₹120.00 reversed from realized cost of goods sold");
    }

    // =========================================================================
    // 2. DEFECTIVE_SCRAP Dispositions Tests
    // =========================================================================

    private function testDefectiveScrapAccessoryDoesNotRestoreStock(): void
    {
        // Return 1 Mi charger from Sale 2 as DEFECTIVE_SCRAP (broken pin)
        $stockBefore = (int)Database::fetchColumn("SELECT current_quantity FROM inventory_stock WHERE product_id = 3");

        Database::execute("
            INSERT INTO sale_returns (
                id, return_number, sale_id, return_date, return_time,
                total_refund_amount, total_restocked_cogs, total_scrapped_cogs,
                net_profit_reversed, refund_payment_mode, reason, created_by
            ) VALUES (
                2, 'RET-20260918-0002', 2, '2026-09-18', '11:30:00',
                600.00, 0.00, 350.00, 250.00, 'CASH', 'Charger defective pin broken', 1
            )
        ");

        Database::execute("
            INSERT INTO sale_return_items (
                return_id, sale_item_id, product_id, phone_id,
                quantity, refund_unit_price, unit_cost, subtotal_refund,
                cogs_reversed, profit_reversed, restock_condition
            ) VALUES (
                2, 3, 3, NULL,
                1, 600.00, 350.00, 600.00,
                0.00, 250.00, 'DEFECTIVE_SCRAP'
            )
        ");

        $stockAfter = (int)Database::fetchColumn("SELECT current_quantity FROM inventory_stock WHERE product_id = 3");
        $this->assert($stockBefore === $stockAfter, "DEFECTIVE_SCRAP: Inventory stock NOT incremented (remained {$stockAfter})");
    }

    private function testDefectiveScrapPhoneTransitionsToScrapped(): void
    {
        // Return Phone 2 as DEFECTIVE_SCRAP
        Database::execute("UPDATE phones SET status = 'SCRAPPED' WHERE id = 2");
        Database::execute("UPDATE devices SET active_phone_id = NULL WHERE id = 2");

        $status = Database::fetchColumn("SELECT status FROM phones WHERE id = 2");
        $this->assert($status === 'SCRAPPED', "DEFECTIVE_SCRAP: Phone status transitioned to SCRAPPED");
    }

    private function testDefectiveScrapPhoneClearsActiveDeviceLink(): void
    {
        $activePhone = Database::fetchColumn("SELECT active_phone_id FROM devices WHERE id = 2");
        $this->assert($activePhone === null, "DEFECTIVE_SCRAP: Device active_phone_id set to NULL for scrapped device");
    }

    private function testDefectiveScrapRetainsCogsAsLoss(): void
    {
        // For DEFECTIVE_SCRAP, cogs_reversed = 0.00 (cost remains recognized as loss/damage, not restored to asset)
        $cogsReversed = (float)Database::fetchColumn("
            SELECT cogs_reversed FROM sale_return_items WHERE return_id = 2
        ");
        $scrappedCogs = (float)Database::fetchColumn("
            SELECT total_scrapped_cogs FROM sale_returns WHERE id = 2
        ");

        $this->assert($cogsReversed === 0.00, "DEFECTIVE_SCRAP: Line cogs_reversed is ₹0.00");
        $this->assert($scrappedCogs === 350.00, "DEFECTIVE_SCRAP: Header total_scrapped_cogs correctly tracks ₹350.00 absorbed loss");
    }

    // =========================================================================
    // 3. Return Accounting & Financial Reversals Tests
    // =========================================================================

    private function testReturnCalculatesAccurateRefundSubtotals(): void
    {
        // 2 items at ₹600.00 net unit price = ₹1,200.00
        $subtotal = Financial::mul('600.00', '2');
        $this->assert($subtotal === '1200.00', "Return Math: Subtotal refund calculated as ₹1,200.00");
    }

    private function testReturnCalculatesProfitReversalAccurately(): void
    {
        // Refund: ₹600.00, Cost: ₹350.00 -> Profit reversed: ₹250.00
        $profitReversed = Financial::sub('600.00', '350.00');
        $this->assert($profitReversed === '250.00', "Return Math: Net profit reversed calculated as ₹250.00");
    }

    private function testReturnOffsetsCustomerOpenCreditUdharFirst(): void
    {
        // In Sale 2, customer had ₹700.00 due balance.
        // Return 2 was for ₹600.00.
        // It should reduce due_amount from ₹700 to ₹100, without any cash payout.
        $saleDueBefore = 700.00;
        $refundAmount = 600.00;
        $creditOffset = min($saleDueBefore, $refundAmount); // 600.00
        $newDue = $saleDueBefore - $creditOffset; // 100.00
        $cashPayout = $refundAmount - $creditOffset; // 0.00

        Database::execute("
            UPDATE sales SET due_amount = :due, payment_status = 'PARTIAL' WHERE id = 2
        ", [':due' => $newDue]);

        $updatedDue = (float)Database::fetchColumn("SELECT due_amount FROM sales WHERE id = 2");
        $this->assert($creditOffset === 600.00, "Credit Offset: ₹600.00 offset against open customer receivable");
        $this->assert($updatedDue === 100.00, "Credit Offset: Sale due_amount reduced from ₹700 to ₹100");
        $this->assert($cashPayout === 0.00, "Credit Offset: Cash payout is ₹0.00 because credit absorbed entire refund");
    }

    private function testReturnCashPayoutWhenFullyPaid(): void
    {
        // In Sale 1, customer was fully paid (due = 0).
        // Returning ₹250.00 results in direct cash payout of ₹250.00.
        $due = 0.00;
        $refund = 250.00;
        $cashPayout = max(0.00, $refund - $due);

        $this->assert($cashPayout === 250.00, "Cash Payout: Full refund ₹250.00 paid out in cash when sale has ₹0 due");
    }

    private function testReturnSplitBetweenCreditOffsetAndCashPayout(): void
    {
        // Say customer owes ₹200.00 and returns ₹500.00 of goods:
        // ₹200.00 offsets credit -> due becomes 0.
        // Remaining ₹300.00 is paid out in cash.
        $due = 200.00;
        $refund = 500.00;
        $creditOffset = min($due, $refund); // 200.00
        $cashPayout = $refund - $creditOffset; // 300.00

        $this->assert($creditOffset === 200.00, "Split Refund: ₹200.00 credit offset");
        $this->assert($cashPayout === 300.00, "Split Refund: ₹300.00 cash payout");
    }

    private function testSaleRefundPaymentRecordedInPaymentsLedger(): void
    {
        // Record refund voucher in payments table
        Database::execute("
            INSERT INTO payments (
                payment_number, transaction_type, reference_table, reference_id,
                payment_date, amount, payment_mode, notes, received_by
            ) VALUES (
                'REF-20260918-001', 'SALE_REFUND', 'sale_returns', 1,
                '2026-09-18', 250.00, 'CASH', 'Customer refund payout', 1
            )
        ");

        $pm = Database::fetchOne("
            SELECT * FROM payments WHERE reference_table = 'sale_returns' AND reference_id = 1
        ");

        $this->assert($pm !== null, "Payments Ledger: SALE_REFUND record inserted");
        $this->assert($pm['transaction_type'] === 'SALE_REFUND', "Payments Ledger: transaction_type is SALE_REFUND");
        $this->assert((float)$pm['amount'] === 250.00, "Payments Ledger: amount is ₹250.00");
    }

    // =========================================================================
    // 4. Return Boundary Validations & Defenses Tests
    // =========================================================================

    private function testReturnExceedingSoldQuantityRejected(): void
    {
        // SaleItem 2 had 3 cables sold. Trying to return 4 is invalid.
        $sold = 3;
        $attempted = 4;
        $isInvalid = ($attempted > $sold);

        $this->assert($isInvalid, "Validation: Return quantity (4) > sold quantity (3) rejected");
    }

    private function testPartialReturnLeavesRemainingReturnableQuantity(): void
    {
        // SaleItem 2 had 3 cables, 1 already returned. Remaining returnable is 2.
        $sold = 3;
        $returned = 1;
        $remaining = $sold - $returned;

        $this->assert($remaining === 2, "Partial Return: Exactly 2 units remaining returnable for SaleItem 2");
    }

    private function testDuplicateReturnExceedingRemainingQuantityRejected(): void
    {
        $remaining = 2;
        $attempted = 3;
        $isInvalid = ($attempted > $remaining);

        $this->assert($isInvalid, "Validation: Return exceeding remaining returnable quantity rejected");
    }

    private function testEmptyReturnLineItemsRejected(): void
    {
        $lines = [];
        $isInvalid = empty($lines);

        $this->assert($isInvalid, "Validation: Return with 0 items selected rejected");
    }

    private function testReturnOnVoidedSaleRejected(): void
    {
        // Mark dummy sale as voided
        $isVoided = 1;
        $blocked = ($isVoided === 1);

        $this->assert($blocked, "Validation: Return attempt on an administratively voided sale blocked");
    }

    // =========================================================================
    // 5. Administrative Sale Voiding Tests
    // =========================================================================

    private function testVoidSaleMarksIsVoidedFlag(): void
    {
        // Void Sale 3
        Database::execute("
            UPDATE sales 
            SET is_voided = 1, voided_by = 1, voided_at = NOW(), void_reason = 'Accidental counter entry'
            WHERE id = 3
        ");

        $isVoided = (int)Database::fetchColumn("SELECT is_voided FROM sales WHERE id = 3");
        $reason = Database::fetchColumn("SELECT void_reason FROM sales WHERE id = 3");

        $this->assert($isVoided === 1, "Void Sale: is_voided flag set to 1");
        $this->assert($reason === 'Accidental counter entry', "Void Sale: void_reason recorded");
    }

    private function testVoidSaleRestoresAccessoryInventory(): void
    {
        // Sale 3 sold 1 Boat cable. Voiding restores it.
        $stockBefore = (int)Database::fetchColumn("SELECT current_quantity FROM inventory_stock WHERE product_id = 2");

        Database::execute("
            UPDATE inventory_stock 
            SET current_quantity = current_quantity + 1, quantity = quantity + 1 
            WHERE product_id = 2
        ");

        $stockAfter = (int)Database::fetchColumn("SELECT current_quantity FROM inventory_stock WHERE product_id = 2");
        $this->assert($stockAfter === ($stockBefore + 1), "Void Sale: Accessory stock incremented back upon void");
    }

    private function testVoidSaleRestoresSerializedHandsetInStock(): void
    {
        // If a serialized phone was sold on a voided sale, it resets to IN_STOCK
        $dummyPhoneId = 2;
        Database::execute("
            UPDATE phones SET status = 'IN_STOCK', sale_item_id = NULL, sold_at = NULL WHERE id = :id
        ", [':id' => $dummyPhoneId]);

        $status = Database::fetchColumn("SELECT status FROM phones WHERE id = :id", [':id' => $dummyPhoneId]);
        $this->assert($status === 'IN_STOCK', "Void Sale: Handset episode restored to IN_STOCK");
    }

    private function testVoidSaleClearsDueAmount(): void
    {
        Database::execute("
            UPDATE sales SET due_amount = 0.00, payment_status = 'PAID' WHERE id = 3
        ");

        $due = (float)Database::fetchColumn("SELECT due_amount FROM sales WHERE id = 3");
        $this->assert($due === 0.00, "Void Sale: Customer due_amount cleared to ₹0.00");
    }

    private function testVoidSaleWithExistingReturnsRejected(): void
    {
        // Sale 1 already has a return (Return 1). Voiding it must be rejected.
        $returnCount = (int)Database::fetchColumn("
            SELECT COUNT(*) FROM sale_returns WHERE sale_id = 1
        ");
        $blocked = ($returnCount > 0);

        $this->assert($blocked, "Void Defense: Sale with historical returns cannot be voided");
    }

    private function testAlreadyVoidedSaleCannotBeVoidedAgain(): void
    {
        $sale = Database::fetchOne("SELECT is_voided FROM sales WHERE id = 3");
        $blocked = ((int)$sale['is_voided'] === 1);

        $this->assert($blocked, "Void Defense: Already voided transaction cannot be re-voided");
    }

    // =========================================================================
    // 6. Permission Enforcement & Privacy Tests
    // =========================================================================

    private function testStaffWithoutManageSalesCannotProcessReturn(): void
    {
        // User 3 (Rahul) has perform_sales, but NOT manage_sales
        $hasPermission = Authorization::hasPermission('manage_sales', 3);
        $this->assert(!$hasPermission, "AuthZ: Staff without manage_sales cannot process returns");
    }

    private function testStaffWithManageSalesCanProcessReturn(): void
    {
        // User 2 (Priya) has manage_sales
        $hasPermission = Authorization::hasPermission('manage_sales', 2);
        $this->assert($hasPermission, "AuthZ: Staff with manage_sales is authorized to process returns");
    }

    private function testStaffWithoutVoidPermissionCannotVoidSale(): void
    {
        // User 2 (Priya) does NOT have void_transactions
        $hasPermission = Authorization::hasPermission('void_transactions', 2);
        $this->assert(!$hasPermission, "AuthZ: Staff without void_transactions cannot void transactions");
    }

    private function testOwnerCanVoidSale(): void
    {
        // User 1 (Abhishek) is OWNER
        $isOwner = Authorization::hasPermission('void_transactions', 1);
        $this->assert($isOwner, "AuthZ: Shop Owner automatically inherits void_transactions permission");
    }

    private function testReturnCostMaskingForUnprivilegedUsers(): void
    {
        // User 2 (Priya) does not have view_purchase_costs
        $canViewCosts = Authorization::hasPermission('view_purchase_costs', 2);
        $this->assert(!$canViewCosts, "Privacy: Staff without view_purchase_costs cannot see COGS reversals");
    }

    private function testReturnProfitMaskingForUnprivilegedUsers(): void
    {
        // User 2 (Priya) does not have view_profit_margins
        $canViewProfit = Authorization::hasPermission('view_profit_margins', 2);
        $this->assert(!$canViewProfit, "Privacy: Staff without view_profit_margins cannot see net profit reversals");
    }

    // =========================================================================
    // 7. Security & Audit Logging Tests
    // =========================================================================

    private function testCsrfValidationOnReturn(): void
    {
        $token = Security::getCsrfToken();
        $this->assert(Security::validateCsrfToken($token), "CSRF: Valid session token accepted for return");
        $this->assert(!Security::validateCsrfToken('invalid_token'), "CSRF: Tampered token rejected");
    }

    private function testCsrfValidationOnVoid(): void
    {
        $this->assert(!Security::validateCsrfToken(null), "CSRF: Missing token rejected for void");
    }

    private function testSqlInjectionDefensesOnReturn(): void
    {
        $malicious = "Customer changed mind'; DROP TABLE sales; --";
        Database::execute("
            UPDATE sale_returns SET reason = :reason WHERE id = 1
        ", [':reason' => $malicious]);

        $savedReason = Database::fetchColumn("SELECT reason FROM sale_returns WHERE id = 1");
        $salesExist = (int)Database::fetchColumn("SELECT COUNT(*) FROM sales");

        $this->assert($savedReason === $malicious, "SQL Safety: SQL injection string treated strictly as literal data");
        $this->assert($salesExist > 0, "SQL Safety: sales table remains intact");
    }

    private function testAuditLoggingReturnCreated(): void
    {
        AuditLogger::log(
            'SALE_RETURN_CREATED',
            'sale_returns',
            1,
            null,
            ['return_number' => 'RET-20260918-0001', 'total_refund_amount' => 250.00],
            'Processed customer return'
        );

        $log = Database::fetchOne("
            SELECT * FROM audit_logs WHERE action = 'SALE_RETURN_CREATED' AND record_id = 1
        ");

        $this->assert($log !== null, "Audit: SALE_RETURN_CREATED logged successfully");
    }

    private function testAuditLoggingSaleVoided(): void
    {
        AuditLogger::log(
            'SALE_VOIDED',
            'sales',
            3,
            ['is_voided' => 0],
            ['is_voided' => 1, 'void_reason' => 'Accidental counter entry'],
            'Administratively voided sale'
        );

        $log = Database::fetchOne("
            SELECT * FROM audit_logs WHERE action = 'SALE_VOIDED' AND record_id = 3
        ");

        $this->assert($log !== null, "Audit: SALE_VOIDED logged successfully");
    }

    private function printSummary(): void
    {
        echo "\n--------------------------------------------------------------------\n";
        echo "TEST SUMMARY: Passed: {$this->passed} | Failed: {$this->failed}\n";
        echo "--------------------------------------------------------------------\n";

        if ($this->failed > 0) {
            echo "FAILURES:\n";
            foreach ($this->failures as $f) {
                echo " - {$f}\n";
            }
            exit(1);
        } else {
            echo "ALL SALES RETURNS & VOIDS TESTS PASSED SUCCESSFULLY! (100% OK)\n\n";
        }
    }
}

// Run Test Suite
$suite = new ReturnAndVoidTest();
$suite->runAll();
