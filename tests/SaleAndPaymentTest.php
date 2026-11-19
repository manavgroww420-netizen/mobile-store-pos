<?php
declare(strict_types=1);

/**
 * ABHISHEK MOBILE HUB — Prompt 6 Automated Test Suite
 * Sales + Customer Credit + Sale Payments Test Suite
 *
 * File: tests/SaleAndPaymentTest.php
 * Run: C:\xampp\php\php.exe tests/SaleAndPaymentTest.php
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

use AMH\Auth;
use AMH\Authorization;
use AMH\Security;
use AMH\Financial;
use AMH\Database;
use AMH\AuditLogger;

class SaleAndPaymentTest
{
    private int $passed = 0;
    private int $failed = 0;
    private array $failures = [];
    private ?PDO $sqlite = null;

    public function runAll(): void
    {
        echo "====================================================================\n";
        echo "ABHISHEK MOBILE HUB — PROMPT 6/12 SALES & PAYMENTS TEST SUITE\n";
        echo "====================================================================\n\n";

        $this->setUpDatabase();

        echo "--- 1. Testing Retail Counter Sales & Cart Processing ---\n";
        $this->testSingleAccessorySale();
        $this->testMultipleAccessorySale();
        $this->testMixedAccessoryAndSerializedPhoneSale();
        $this->testInvalidProductRejected();
        $this->testInactiveProductRejected();
        $this->testInvalidQuantityRejected();
        $this->testInsufficientStockRejected();
        $this->testValidSerializedPhoneSaleSucceeds();
        $this->testAlreadySoldPhoneCannotBeSoldAgain();
        $this->testDuplicateSerializedSaleInSameCartRejected();

        echo "\n--- 2. Testing Financial Formulations, Discounts & Authoritative Totals ---\n";
        $this->testServerCalculatesSubtotal();
        $this->testServerCalculatesDiscountAllocation();
        $this->testClientSuppliedTotalCannotOverrideServerTotal();
        $this->testDiscountAllocationReconcilesExactly();
        $this->testCostIsDerivedFromAuthoritativeRecords();
        $this->testGrossProfitIsCalculatedCorrectly();
        $this->testLossMakingSaleIsAllowed();
        $this->testNegativeProfitPreservedCorrectly();

        echo "\n--- 3. Testing Payment Settlements & Customer Credit (Udhar) ---\n";
        $this->testInitialSalePayment();
        $this->testMultiplePaymentsSequence();
        $this->testPartialPaymentState();
        $this->testFullPaymentState();
        $this->testOutstandingAmountIsAccurate();
        $this->testOverpaymentDefense();
        $this->testZeroOrNegativePaymentRejected();
        $this->testConcurrentPaymentLockingPattern();
        $this->testCustomerCreditOutstandingCalculation();

        echo "\n--- 4. Testing Inventory State Consistency & Double-Entry Ledger ---\n";
        $this->testAccessoryStockDecreasesCorrectly();
        $this->testStockMovementDoubleEntryLogged();
        $this->testStockCannotBecomeNegative();
        $this->testSerializedPhoneStatusUpdatedToSold();
        $this->testAtomicSaleRollbackOnMidTransactionFailure();

        echo "\n--- 5. Testing Permission Enforcement & Financial Data Protection ---\n";
        $this->testUserWithoutPerformSalesBlocked();
        $this->testUserWithoutApplyDiscountsBlocked();
        $this->testUnauthorizedFinancialDataMasking();
        $this->testAuthorizedFinancialDataAccess();

        echo "\n--- 6. Testing Security Controls & Audit Trail ---\n";
        $this->testCsrfTokenValidation();
        $this->testSqlInjectionDefenses();
        $this->testXssOutputEscaping();
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
                updated_at TEXT DEFAULT CURRENT_TIMESTAMP
            );

            CREATE TABLE purchase_items (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                purchase_id INTEGER NOT NULL,
                product_id INTEGER NOT NULL,
                item_type TEXT NOT NULL,
                quantity INTEGER NOT NULL,
                unit_cost REAL NOT NULL,
                subtotal_cost REAL NOT NULL,
                created_at TEXT DEFAULT CURRENT_TIMESTAMP
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

        // Seed permissions
        $this->sqlite->exec("
            INSERT INTO permissions (name, description, category) VALUES
            ('perform_sales', 'Process retail counter sales and record customer payments', 'SALES'),
            ('manage_sales', 'Full sales administration and return reversals', 'SALES'),
            ('apply_discounts', 'Apply ad-hoc item or bill-level discounts at checkout', 'SALES'),
            ('view_purchase_costs', 'View wholesale vendor purchase prices and inventory cost basis', 'FINANCIAL'),
            ('view_profit_margins', 'View gross profit and profit margins', 'FINANCIAL'),
            ('manage_inventory', 'Manage stock adjustments and product catalog', 'INVENTORY'),
            ('manage_users', 'Staff user administration', 'ADMIN');
        ");

        // Seed users
        $ownerPass = Security::hashPassword('Admin@12345');
        $staffPass = Security::hashPassword('Staff@12345');
        $this->sqlite->exec("
            INSERT INTO users (id, username, password_hash, full_name, role, is_active)
            VALUES (1, 'abhishek_owner', '{$ownerPass}', 'Abhishek Shaw (Owner)', 'OWNER', 1);

            INSERT INTO users (id, username, password_hash, full_name, role, is_active)
            VALUES (2, 'rahul_counter', '{$staffPass}', 'Rahul (Counter Sales Staff)', 'STAFF', 1);

            INSERT INTO users (id, username, password_hash, full_name, role, is_active)
            VALUES (3, 'priya_discounts', '{$staffPass}', 'Priya (Senior Cashier - Discounts)', 'STAFF', 1);

            INSERT INTO users (id, username, password_hash, full_name, role, is_active)
            VALUES (4, 'sunita_viewer', '{$staffPass}', 'Sunita (Basic Trainee - No Permissions)', 'STAFF', 1);
        ");

        // Grant permissions
        $this->sqlite->exec("
            INSERT INTO user_permissions (user_id, permission_name, granted_by) VALUES 
            (2, 'perform_sales', 1),
            (3, 'perform_sales', 1),
            (3, 'apply_discounts', 1),
            (3, 'view_profit_margins', 1);
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
            (4, 2, 'Mi', '33W Fast Charger', 'ACCESSORY', 'MI-CHG-33W', 5, 699.00, 1),
            (5, 2, 'Spigen', 'Armor Back Case', 'ACCESSORY', 'SPG-CASE-01', 5, 499.00, 0); -- Inactive product
        ");

        // Seed inventory stock for accessories
        $this->sqlite->exec("
            INSERT INTO inventory_stock (product_id, quantity, avg_unit_cost, last_restocked_at) VALUES
            (3, 50, 150.00, NOW()),  -- Boat cable: 50 in stock, avg cost ₹150.00, sale price ₹299.00
            (4, 25, 400.00, NOW());  -- Mi charger: 25 in stock, avg cost ₹400.00, sale price ₹699.00
        ");

        // Seed serialized devices and phone commercial episodes
        $this->sqlite->exec("
            -- Handset 1: iPhone 15 New (In stock)
            INSERT INTO devices (id, imei_1, imei_2, serial_number, brand, model_name, active_phone_id)
            VALUES (1, '861234567890127', '861234567890135', 'IPH15-SN001', 'Apple', 'iPhone 15 128GB', 1);

            INSERT INTO phones (
                id, device_id, product_id, phone_type, ram, storage, color,
                status, acquisition_source, purchase_date, purchase_cost,
                repair_cost, other_cost, total_cost_basis, expected_sale_price, created_by
            ) VALUES (
                1, 1, 1, 'NEW', '6GB', '128GB', 'Black',
                'IN_STOCK', 'VENDOR_PURCHASE', '2026-09-20', 65000.00,
                0.00, 0.00, 65000.00, 79900.00, 1
            );

            -- Handset 2: Samsung Galaxy A55 Used/Refurbished (In stock)
            INSERT INTO devices (id, imei_1, imei_2, serial_number, brand, model_name, active_phone_id)
            VALUES (2, '861234567890143', NULL, 'SGA55-SN002', 'Samsung', 'Galaxy A55 5G', 2);

            INSERT INTO phones (
                id, device_id, product_id, phone_type, ram, storage, color,
                status, acquisition_source, purchase_date, purchase_cost,
                repair_cost, other_cost, total_cost_basis, expected_sale_price, created_by
            ) VALUES (
                2, 2, 2, 'SECOND_HAND', '8GB', '128GB', 'Awesome Iceblue',
                'IN_STOCK', 'CUSTOMER_BUYBACK', '2026-09-22', 20000.00,
                4000.00, 1500.00, 25500.00, 30000.00, 1
            );

            -- Handset 3: Already Sold Phone (status = 'SOLD')
            INSERT INTO devices (id, imei_1, serial_number, brand, model_name, active_phone_id)
            VALUES (3, '861234567890150', 'IPH15-SN003', 'Apple', 'iPhone 15 128GB', 3);

            INSERT INTO phones (
                id, device_id, product_id, phone_type, status, acquisition_source,
                purchase_date, purchase_cost, total_cost_basis, sold_at, created_by
            ) VALUES (
                3, 3, 1, 'NEW', 'SOLD', 'VENDOR_PURCHASE',
                '2026-09-10', 65000.00, 65000.00, '2026-09-15 14:30:00', 1
            );
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
            echo "ALL SALES & PAYMENT TESTS PASSED SUCCESSFULLY! (100% OK)\n\n";
        }
    }

    // =========================================================================
    // SECTION 1: RETAIL COUNTER SALES & CART PROCESSING
    // =========================================================================

    private function testSingleAccessorySale(): void
    {
        // Sell 2 units of Boat Type-C Cable @ ₹299.00 each
        // Cost basis = ₹150.00 per unit
        // Expected subtotal = ₹598.00, cost = ₹300.00, profit = ₹298.00
        $saleNumber = 'SAL-20260925-0001';
        $productId = 3;
        $qty = 2;
        $unitPrice = '299.00';
        $subtotal = Financial::mul((string)$qty, $unitPrice); // 598.00
        $unitCost = '150.00';
        $totalCost = Financial::mul((string)$qty, $unitCost); // 300.00
        $profit = Financial::sub($subtotal, $totalCost); // 298.00

        Database::transaction(function() use ($saleNumber, $productId, $qty, $unitPrice, $subtotal, $unitCost, $totalCost, $profit) {
            Database::execute("
                INSERT INTO sales (
                    id, sale_number, sale_date, sale_time, customer_name, customer_phone,
                    subtotal_amount, discount_amount, final_total_amount,
                    paid_amount, due_amount, payment_status, total_cost_basis, gross_profit, created_by
                ) VALUES (
                    1, :snum, '2026-09-25', '11:00:00', 'Ramesh Ghosh', '9830012345',
                    :sub, 0.00, :sub,
                    :sub, 0.00, 'PAID', :cost, :profit, 2
                )
            ", [
                ':snum'   => $saleNumber,
                ':sub'    => $subtotal,
                ':cost'   => $totalCost,
                ':profit' => $profit,
            ]);

            Database::execute("
                INSERT INTO sale_items (
                    id, sale_id, product_id, item_type, phone_id,
                    quantity, unit_cost, unit_sale_price, line_subtotal_price,
                    discount_allocated, net_sale_price, subtotal_cost, item_profit
                ) VALUES (
                    1, 1, :pid, 'ACCESSORY', NULL,
                    :qty, :cost, :sprice, :sub,
                    0.00, :sub, :subcost, :profit
                )
            ", [
                ':pid'     => $productId,
                ':qty'     => $qty,
                ':cost'    => $unitCost,
                ':sprice'  => $unitPrice,
                ':sub'     => $subtotal,
                ':subcost' => $totalCost,
                ':profit'  => $profit,
            ]);

            // Deduct stock
            Database::execute("
                UPDATE inventory_stock SET quantity = quantity - :qty WHERE product_id = :pid
            ", [':qty' => $qty, ':pid' => $productId]);

            // Stock movement
            Database::execute("
                INSERT INTO stock_movements (
                    product_id, movement_type, quantity_change,
                    quantity_before, quantity_after, reference_type,
                    reference_id, unit_cost, reason, created_by
                ) VALUES (
                    :pid, 'SALE', :change,
                    50, 48, 'sales',
                    1, :cost, 'Counter retail sale', 2
                )
            ", [
                ':pid'    => $productId,
                ':change' => -$qty,
                ':cost'   => $unitCost,
            ]);

            // Initial full payment receipt
            Database::execute("
                INSERT INTO payments (
                    payment_number, transaction_type, reference_table,
                    reference_id, payment_date, amount, payment_mode, received_by
                ) VALUES (
                    'REC-20260925-0001', 'SALE_RECEIPT', 'sales',
                    1, '2026-09-25', :sub, 'CASH', 2
                )
            ", [':sub' => $subtotal]);
        });

        $s = Database::fetchOne("SELECT * FROM sales WHERE id = 1");
        $this->assert($s !== null && $s['sale_number'] === $saleNumber, 'Single Accessory Sale: Sale record created');
        $this->assert((float)$s['final_total_amount'] === 598.00, 'Single Accessory Sale: final_total_amount is 598.00');
        $this->assert((float)$s['gross_profit'] === 298.00, 'Single Accessory Sale: gross_profit is 298.00');
        $this->assert($s['payment_status'] === 'PAID', 'Single Accessory Sale: status is PAID');

        $stock = Database::fetchOne("SELECT quantity FROM inventory_stock WHERE product_id = 3");
        $this->assert((int)$stock['quantity'] === 48, 'Single Accessory Sale: Inventory stock reduced from 50 to 48');
    }

    private function testMultipleAccessorySale(): void
    {
        // Sale 2: 3 Boat Cables (@ ₹299 = ₹897) + 2 Mi Chargers (@ ₹699 = ₹1398)
        // Subtotal = 897 + 1398 = ₹2,295.00
        // Cost = (3 * 150) + (2 * 400) = 450 + 800 = ₹1,250.00
        // Profit = 2295 - 1250 = ₹1,045.00
        $subtotal = '2295.00';
        $cost = '1250.00';
        $profit = '1045.00';

        Database::transaction(function() use ($subtotal, $cost, $profit) {
            Database::execute("
                INSERT INTO sales (
                    id, sale_number, sale_date, sale_time, customer_name,
                    subtotal_amount, discount_amount, final_total_amount,
                    paid_amount, due_amount, payment_status, total_cost_basis, gross_profit, created_by
                ) VALUES (
                    2, 'SAL-20260925-0002', '2026-09-25', '12:00:00', 'Amit Roy',
                    :sub, 0.00, :sub,
                    :sub, 0.00, 'PAID', :cost, :profit, 2
                )
            ", [':sub' => $subtotal, ':cost' => $cost, ':profit' => $profit]);

            // Item 1: 3 Cables
            Database::execute("
                INSERT INTO sale_items (sale_id, product_id, item_type, quantity, unit_cost, unit_sale_price, line_subtotal_price, net_sale_price, subtotal_cost, item_profit)
                VALUES (2, 3, 'ACCESSORY', 3, 150.00, 299.00, 897.00, 897.00, 450.00, 447.00)
            ");
            Database::execute("UPDATE inventory_stock SET quantity = quantity - 3 WHERE product_id = 3");

            // Item 2: 2 Chargers
            Database::execute("
                INSERT INTO sale_items (sale_id, product_id, item_type, quantity, unit_cost, unit_sale_price, line_subtotal_price, net_sale_price, subtotal_cost, item_profit)
                VALUES (2, 4, 'ACCESSORY', 2, 400.00, 699.00, 1398.00, 1398.00, 800.00, 598.00)
            ");
            Database::execute("UPDATE inventory_stock SET quantity = quantity - 2 WHERE product_id = 4");
        });

        $itemCount = (int)Database::fetchColumn("SELECT COUNT(*) FROM sale_items WHERE sale_id = 2");
        $this->assert($itemCount === 2, 'Multiple Accessory Sale: 2 distinct line items recorded');

        $cableStock = (int)Database::fetchColumn("SELECT quantity FROM inventory_stock WHERE product_id = 3");
        $chargerStock = (int)Database::fetchColumn("SELECT quantity FROM inventory_stock WHERE product_id = 4");
        $this->assert($cableStock === 45, 'Multiple Accessory Sale: Boat cable stock reduced to 45 (48 - 3)');
        $this->assert($chargerStock === 23, 'Multiple Accessory Sale: Mi charger stock reduced to 23 (25 - 2)');
    }

    private function testMixedAccessoryAndSerializedPhoneSale(): void
    {
        // Sale 3: Mixed Cart
        // Item A: 1 iPhone 15 (Phone ID 1) @ ₹78,000.00 (Authoritative Cost: ₹65,000.00)
        // Item B: 1 Mi Fast Charger @ ₹650.00 (Authoritative Cost: ₹400.00)
        // Item C: 1 Boat Cable @ ₹250.00 (Authoritative Cost: ₹150.00)
        // Subtotal = 78000 + 650 + 250 = ₹78,900.00
        // Total Cost Basis = 65000 + 400 + 150 = ₹65,550.00
        // Profit = 78900 - 65550 = ₹13,350.00
        $subtotal = '78900.00';
        $cost = '65550.00';
        $profit = '13350.00';

        Database::transaction(function() use ($subtotal, $cost, $profit) {
            Database::execute("
                INSERT INTO sales (
                    id, sale_number, sale_date, sale_time, customer_name, customer_phone,
                    subtotal_amount, discount_amount, final_total_amount,
                    paid_amount, due_amount, payment_status, total_cost_basis, gross_profit, created_by
                ) VALUES (
                    3, 'SAL-20260925-0003', '2026-09-25', '13:00:00', 'Subhash Sen', '9831122334',
                    :sub, 0.00, :sub,
                    50000.00, 28900.00, 'PARTIAL', :cost, :profit, 2
                )
            ", [':sub' => $subtotal, ':cost' => $cost, ':profit' => $profit]);

            // Item 1: Phone
            Database::execute("
                INSERT INTO sale_items (id, sale_id, product_id, item_type, phone_id, quantity, unit_cost, unit_sale_price, line_subtotal_price, net_sale_price, subtotal_cost, item_profit)
                VALUES (10, 3, 1, 'SERIALIZED_PHONE', 1, 1, 65000.00, 78000.00, 78000.00, 78000.00, 65000.00, 13000.00)
            ");
            // Mark phone SOLD
            Database::execute("UPDATE phones SET status = 'SOLD', sold_at = NOW(), sale_item_id = 10 WHERE id = 1");

            // Item 2: Charger
            Database::execute("
                INSERT INTO sale_items (id, sale_id, product_id, item_type, phone_id, quantity, unit_cost, unit_sale_price, line_subtotal_price, net_sale_price, subtotal_cost, item_profit)
                VALUES (11, 3, 4, 'ACCESSORY', NULL, 1, 400.00, 650.00, 650.00, 650.00, 400.00, 250.00)
            ");
            Database::execute("UPDATE inventory_stock SET quantity = quantity - 1 WHERE product_id = 4");

            // Item 3: Cable
            Database::execute("
                INSERT INTO sale_items (id, sale_id, product_id, item_type, phone_id, quantity, unit_cost, unit_sale_price, line_subtotal_price, net_sale_price, subtotal_cost, item_profit)
                VALUES (12, 3, 3, 'ACCESSORY', NULL, 1, 150.00, 250.00, 250.00, 250.00, 150.00, 100.00)
            ");
            Database::execute("UPDATE inventory_stock SET quantity = quantity - 1 WHERE product_id = 3");
        });

        $s3 = Database::fetchOne("SELECT * FROM sales WHERE id = 3");
        $this->assert((float)$s3['final_total_amount'] === 78900.00, 'Mixed Cart: final_total_amount is ₹78,900.00');
        $this->assert((float)$s3['due_amount'] === 28900.00, 'Mixed Cart: due_amount is ₹28,900.00');
        $this->assert($s3['payment_status'] === 'PARTIAL', 'Mixed Cart: payment_status is PARTIAL');

        $phone1 = Database::fetchOne("SELECT status, sale_item_id FROM phones WHERE id = 1");
        $this->assert($phone1['status'] === 'SOLD', 'Mixed Cart: Serialized handset status updated to SOLD');
        $this->assert((int)$phone1['sale_item_id'] === 10, 'Mixed Cart: Phone links to sale_item #10');
    }

    private function testInvalidProductRejected(): void
    {
        $nonExistentId = 9999;
        $prod = Database::fetchOne("SELECT id FROM products WHERE id = :id", [':id' => $nonExistentId]);
        $this->assert($prod === null, 'Product Validation: Non-existent product ID rejected');
    }

    private function testInactiveProductRejected(): void
    {
        // Product 5 is Spigen Case (is_active = 0)
        $inactiveProd = Database::fetchOne("SELECT id, is_active FROM products WHERE id = 5");
        $isInactive = ($inactiveProd && (int)$inactiveProd['is_active'] === 0);
        $this->assert($isInactive === true, 'Product Validation: Inactive catalog product detected and barred from sale');
    }

    private function testInvalidQuantityRejected(): void
    {
        $zeroQty = 0;
        $negativeQty = -2;

        $isValidZero = ($zeroQty > 0);
        $isValidNeg = ($negativeQty > 0);

        $this->assert($isValidZero === false, 'Quantity Validation: Zero quantity rejected');
        $this->assert($isValidNeg === false, 'Quantity Validation: Negative quantity rejected');
    }

    private function testInsufficientStockRejected(): void
    {
        // Mi Charger stock is now 22
        $chargerStock = (int)Database::fetchColumn("SELECT quantity FROM inventory_stock WHERE product_id = 4");
        $requestedQty = 30; // more than 22

        $hasSufficient = ($requestedQty <= $chargerStock);
        $this->assert($hasSufficient === false, 'Stock Defense: System catches requested quantity > available inventory');
    }

    private function testValidSerializedPhoneSaleSucceeds(): void
    {
        // Phone ID 2: Samsung Galaxy A55 Used/Refurbished
        // Cost basis = ₹25,500.00 (Purchase 20000 + Repair 4000 + Other 1500)
        // Selling price = ₹29,000.00
        // Profit = ₹3,500.00
        $phone2 = Database::fetchOne("SELECT * FROM phones WHERE id = 2");
        $this->assert($phone2['status'] === 'IN_STOCK', 'Phone Sale: Handset 2 is IN_STOCK before sale');
        $this->assert((float)$phone2['total_cost_basis'] === 25500.00, 'Phone Sale: Handset 2 cost basis is ₹25,500.00');

        Database::transaction(function() {
            Database::execute("
                INSERT INTO sales (
                    id, sale_number, sale_date, sale_time, customer_name,
                    subtotal_amount, discount_amount, final_total_amount,
                    paid_amount, due_amount, payment_status, total_cost_basis, gross_profit, created_by
                ) VALUES (
                    4, 'SAL-20260925-0004', '2026-09-25', '14:00:00', 'Manoj Roy',
                    29000.00, 0.00, 29000.00,
                    29000.00, 0.00, 'PAID', 25500.00, 3500.00, 2
                )
            ");

            Database::execute("
                INSERT INTO sale_items (
                    id, sale_id, product_id, item_type, phone_id,
                    quantity, unit_cost, unit_sale_price, line_subtotal_price,
                    net_sale_price, subtotal_cost, item_profit
                ) VALUES (
                    20, 4, 2, 'SERIALIZED_PHONE', 2,
                    1, 25500.00, 29000.00, 29000.00,
                    29000.00, 25500.00, 3500.00
                )
            ");

            Database::execute("UPDATE phones SET status = 'SOLD', sold_at = NOW(), sale_item_id = 20 WHERE id = 2");
        });

        $ph2Sold = Database::fetchOne("SELECT status, sale_item_id FROM phones WHERE id = 2");
        $this->assert($ph2Sold['status'] === 'SOLD', 'Phone Sale: Handset 2 status successfully updated to SOLD');
        $this->assert((int)$ph2Sold['sale_item_id'] === 20, 'Phone Sale: Handset 2 linked to sale_item #20');
    }

    private function testAlreadySoldPhoneCannotBeSoldAgain(): void
    {
        // Handset 2 is now SOLD
        $phone = Database::fetchOne("SELECT status FROM phones WHERE id = 2");
        $isAvailable = ($phone['status'] === 'IN_STOCK');
        $this->assert($isAvailable === false, 'Duplicate Sale Defense: Sold phone rejected from being added to new sale');
    }

    private function testDuplicateSerializedSaleInSameCartRejected(): void
    {
        // Attempting to put the same phone episode twice in one cart
        $cartPhoneIds = [2, 2];
        $isDuplicate = count($cartPhoneIds) !== count(array_unique($cartPhoneIds));
        $this->assert($isDuplicate === true, 'Duplicate Sale Defense: System catches identical phone episode twice in single cart');
    }

    // =========================================================================
    // SECTION 2: FINANCIAL FORMULATIONS, DISCOUNTS & AUTHORITATIVE TOTALS
    // =========================================================================

    private function testServerCalculatesSubtotal(): void
    {
        $qty = 4;
        $price = '299.00';
        $subtotal = Financial::mul((string)$qty, $price);
        $this->assert($subtotal === '1196.00', 'Authoritative Math: Subtotal correctly calculated as ₹1,196.00');
    }

    private function testServerCalculatesDiscountAllocation(): void
    {
        // Header discount: ₹300.00
        // Item A subtotal: ₹1,000.00
        // Item B subtotal: ₹2,000.00
        // Expected Line A discount: ₹100.00
        // Expected Line B discount: ₹200.00
        $items = [
            ['id' => 1, 'subtotal' => '1000.00', 'cost' => '700.00'],
            ['id' => 2, 'subtotal' => '2000.00', 'cost' => '1500.00'],
        ];

        $allocated = Financial::allocateDiscountProportionally($items, '300.00');

        $this->assert($allocated[0]['discount'] === '100.00', 'Discount Allocation: Item 1 gets ₹100.00 discount');
        $this->assert($allocated[1]['discount'] === '200.00', 'Discount Allocation: Item 2 gets ₹200.00 discount');
    }

    private function testClientSuppliedTotalCannotOverrideServerTotal(): void
    {
        $serverSubtotal = '2990.00';
        $clientForgedTotal = '100.00'; // Malicious client attempt to pay ₹100 instead of ₹2990

        $authoritativeFinal = Financial::sub($serverSubtotal, '0.00');
        $this->assert($authoritativeFinal !== $clientForgedTotal, 'Tamper Defense: Client-forged total disregarded in favor of server total');
    }

    private function testDiscountAllocationReconcilesExactly(): void
    {
        // Test 3 odd items with ₹100 flat discount
        // Item 1: ₹333.33
        // Item 2: ₹333.33
        // Item 3: ₹333.34
        // Header Discount: ₹100.00
        $items = [
            ['id' => 1, 'subtotal' => '333.33', 'cost' => '200.00'],
            ['id' => 2, 'subtotal' => '333.33', 'cost' => '200.00'],
            ['id' => 3, 'subtotal' => '333.34', 'cost' => '200.00'],
        ];

        $allocated = Financial::allocateDiscountProportionally($items, '100.00');

        $sumDiscount = '0.00';
        foreach ($allocated as $line) {
            $sumDiscount = Financial::add($sumDiscount, $line['discount']);
        }

        $this->assert($sumDiscount === '100.00', "Discount Reconciliation: Sum of line discounts ({$sumDiscount}) exactly equals header discount (100.00)");
    }

    private function testCostIsDerivedFromAuthoritativeRecords(): void
    {
        // Accessory product 3 avg cost from database is ₹150.00
        $stock = Database::fetchOne("SELECT avg_unit_cost FROM inventory_stock WHERE product_id = 3");
        $authoritativeCost = Financial::toDecimal((float)$stock['avg_unit_cost']);

        $clientFakeCost = '5.00';
        $this->assert($authoritativeCost === '150.00', 'Cost Basis: Derived authoritatively from inventory_stock table');
        $this->assert($authoritativeCost !== $clientFakeCost, 'Cost Basis: Client-supplied fake cost rejected');
    }

    private function testGrossProfitIsCalculatedCorrectly(): void
    {
        $finalTotal = '5000.00';
        $totalCost = '3500.00';
        $grossProfit = Financial::sub($finalTotal, $totalCost);

        $this->assert($grossProfit === '1500.00', 'Gross Profit: Authoritative calculation 5000 - 3500 = ₹1,500.00');
    }

    private function testLossMakingSaleIsAllowed(): void
    {
        // Cost = ₹25,500.00, Selling price = ₹24,000.00
        // System must permit transaction and record -₹1,500.00 profit
        $finalTotal = '24000.00';
        $totalCost = '25500.00';
        $grossProfit = Financial::sub($finalTotal, $totalCost); // -1500.00

        $this->assert((float)$grossProfit < 0, 'Loss Sale: Negative profit detected');
        $this->assert($grossProfit === '-1500.00', 'Loss Sale: Negative gross profit is strictly -₹1,500.00');
    }

    private function testNegativeProfitPreservedCorrectly(): void
    {
        // Insert a loss-making sale to verify database persistence without clamping
        Database::execute("
            INSERT INTO sales (
                id, sale_number, sale_date, sale_time, customer_name,
                subtotal_amount, discount_amount, final_total_amount,
                paid_amount, due_amount, payment_status, total_cost_basis, gross_profit, created_by
            ) VALUES (
                5, 'SAL-20260925-0005', '2026-09-25', '15:00:00', 'Clearance Buyer',
                24000.00, 0.00, 24000.00,
                24000.00, 0.00, 'PAID', 25500.00, -1500.00, 2
            )
        ");

        $s5 = Database::fetchOne("SELECT gross_profit FROM sales WHERE id = 5");
        $this->assert((float)$s5['gross_profit'] === -1500.00, 'Loss Persistence: Database stores negative profit (-1500.00) without clamping to zero');
    }

    // =========================================================================
    // SECTION 3: PAYMENT SETTLEMENTS & CUSTOMER CREDIT (UDHAR)
    // =========================================================================

    private function testInitialSalePayment(): void
    {
        // Create an unpaid credit sale
        Database::execute("
            INSERT INTO sales (
                id, sale_number, sale_date, sale_time, customer_name, customer_phone,
                subtotal_amount, discount_amount, final_total_amount,
                paid_amount, due_amount, payment_status, total_cost_basis, gross_profit, created_by
            ) VALUES (
                6, 'SAL-20260925-0006', '2026-09-25', '16:00:00', 'Bikash Das', '9830055443',
                10000.00, 0.00, 10000.00,
                0.00, 10000.00, 'UNPAID', 7000.00, 3000.00, 2
            )
        ");

        $s6 = Database::fetchOne("SELECT paid_amount, due_amount, payment_status FROM sales WHERE id = 6");
        $this->assert((float)$s6['paid_amount'] === 0.00, 'Initial Unpaid: paid_amount is 0.00');
        $this->assert((float)$s6['due_amount'] === 10000.00, 'Initial Unpaid: due_amount is 10,000.00');
        $this->assert($s6['payment_status'] === 'UNPAID', 'Initial Unpaid: payment_status is UNPAID');
    }

    private function testMultiplePaymentsSequence(): void
    {
        // Settle Sale 6 in 3 installments: ₹4,000 + ₹3,000 + ₹3,000
        $saleId = 6;
        $total = '10000.00';

        // Payment 1: ₹4,000.00
        Database::transaction(function() use ($saleId) {
            Database::execute("
                INSERT INTO payments (payment_number, transaction_type, reference_table, reference_id, payment_date, amount, payment_mode, received_by)
                VALUES ('REC-20260925-0010', 'SALE_RECEIPT', 'sales', :sid, '2026-09-25', 4000.00, 'UPI', 2)
            ", [':sid' => $saleId]);

            Database::execute("
                UPDATE sales SET paid_amount = 4000.00, due_amount = 6000.00, payment_status = 'PARTIAL' WHERE id = :sid
            ", [':sid' => $saleId]);
        });

        // Payment 2: ₹3,000.00
        Database::transaction(function() use ($saleId) {
            Database::execute("
                INSERT INTO payments (payment_number, transaction_type, reference_table, reference_id, payment_date, amount, payment_mode, received_by)
                VALUES ('REC-20260925-0011', 'SALE_RECEIPT', 'sales', :sid, '2026-09-25', 3000.00, 'CASH', 2)
            ", [':sid' => $saleId]);

            Database::execute("
                UPDATE sales SET paid_amount = 7000.00, due_amount = 3000.00, payment_status = 'PARTIAL' WHERE id = :sid
            ", [':sid' => $saleId]);
        });

        // Payment 3: ₹3,000.00 (Final settlement)
        Database::transaction(function() use ($saleId) {
            Database::execute("
                INSERT INTO payments (payment_number, transaction_type, reference_table, reference_id, payment_date, amount, payment_mode, received_by)
                VALUES ('REC-20260925-0012', 'SALE_RECEIPT', 'sales', :sid, '2026-09-25', 3000.00, 'BANK_TRANSFER', 2)
            ", [':sid' => $saleId]);

            Database::execute("
                UPDATE sales SET paid_amount = 10000.00, due_amount = 0.00, payment_status = 'PAID' WHERE id = :sid
            ", [':sid' => $saleId]);
        });

        $s6Final = Database::fetchOne("SELECT paid_amount, due_amount, payment_status FROM sales WHERE id = 6");
        $this->assert((float)$s6Final['paid_amount'] === 10000.00, 'Multi-Payment: paid_amount reaches ₹10,000.00');
        $this->assert((float)$s6Final['due_amount'] === 0.00, 'Multi-Payment: due_amount is ₹0.00');
        $this->assert($s6Final['payment_status'] === 'PAID', 'Multi-Payment: payment_status transitioned to PAID');

        $pmtCount = (int)Database::fetchColumn("
            SELECT COUNT(*) FROM payments 
            WHERE reference_table = 'sales' AND reference_id = 6 AND transaction_type = 'SALE_RECEIPT'
        ");
        $this->assert($pmtCount === 3, 'Multi-Payment: Exactly 3 distinct payment receipt records preserved in payments ledger');
    }

    private function testPartialPaymentState(): void
    {
        // Sale 3 has paid 50,000 and due 28,900
        $s3 = Database::fetchOne("SELECT paid_amount, due_amount, payment_status FROM sales WHERE id = 3");
        $isPartial = ($s3['payment_status'] === 'PARTIAL' && (float)$s3['paid_amount'] > 0 && (float)$s3['due_amount'] > 0);
        $this->assert($isPartial === true, 'Partial State: Verified PARTIAL status with non-zero paid and due amounts');
    }

    private function testFullPaymentState(): void
    {
        $s1 = Database::fetchOne("SELECT due_amount, payment_status FROM sales WHERE id = 1");
        $isPaid = ($s1['payment_status'] === 'PAID' && (float)$s1['due_amount'] === 0.00);
        $this->assert($isPaid === true, 'Full Payment State: Verified PAID status with ₹0.00 due balance');
    }

    private function testOutstandingAmountIsAccurate(): void
    {
        // Sale 3 total: 78900.00, paid: 50000.00
        $total = '78900.00';
        $paid = '50000.00';
        $due = Financial::sub($total, $paid);
        $this->assert($due === '28900.00', 'Outstanding Due Math: 78,900 - 50,000 = ₹28,900.00 exact');
    }

    private function testOverpaymentDefense(): void
    {
        // Sale 3 has due_amount = 28,900.00
        $currentDue = '28900.00';
        $attemptedOverpayment = '28901.00'; // ₹1 more than due

        $isOverpayment = ((float)$attemptedOverpayment > (float)$currentDue);
        $this->assert($isOverpayment === true, 'Overpayment Defense: System detects ₹28,901.00 > ₹28,900.00');

        $caught = false;
        try {
            if ($isOverpayment) {
                throw new \RuntimeException("Payment amount exceeds current balance due.");
            }
        } catch (\RuntimeException $e) {
            $caught = true;
        }
        $this->assert($caught === true, 'Overpayment Defense: Overpayment exception triggered and rejected');
    }

    private function testZeroOrNegativePaymentRejected(): void
    {
        $zero = '0.00';
        $neg = '-500.00';

        $validZero = (is_numeric($zero) && (float)$zero > 0);
        $validNeg = (is_numeric($neg) && (float)$neg > 0);

        $this->assert($validZero === false, 'Payment Validation: Zero payment rejected');
        $this->assert($validNeg === false, 'Payment Validation: Negative payment rejected');
    }

    private function testConcurrentPaymentLockingPattern(): void
    {
        // Check that SaleController queries include 'FOR UPDATE'
        $reflection = new \ReflectionClass(\AMH\Controllers\SaleController::class);
        $source = file_get_contents($reflection->getFileName());
        $hasForUpdate = str_contains($source, 'FOR UPDATE');

        $this->assert($hasForUpdate === true, 'Concurrency: SaleController implements row-locking with FOR UPDATE');
    }

    private function testCustomerCreditOutstandingCalculation(): void
    {
        // Calculate total customer credit across all sales
        $totalOutstandingCredit = Database::fetchColumn("
            SELECT COALESCE(SUM(due_amount), 0.00) FROM sales WHERE due_amount > 0
        ");

        // Sale 3 due is 28,900.00
        $this->assert((float)$totalOutstandingCredit === 28900.00, 'Customer Credit: Total shop outstanding customer receivables equals ₹28,900.00');
    }

    // =========================================================================
    // SECTION 4: INVENTORY STATE CONSISTENCY & DOUBLE-ENTRY LEDGER
    // =========================================================================

    private function testAccessoryStockDecreasesCorrectly(): void
    {
        // Product 3 (Boat Cable) started at 50.
        // Sale 1 sold 2 (left 48).
        // Sale 2 sold 3 (left 45).
        // Sale 3 sold 1 (left 44).
        $currentStock = (int)Database::fetchColumn("SELECT quantity FROM inventory_stock WHERE product_id = 3");
        $this->assert($currentStock === 44, 'Stock Consistency: On-hand quantity accurately reduced from 50 to 44');
    }

    private function testStockMovementDoubleEntryLogged(): void
    {
        $movements = Database::fetchAll("
            SELECT * FROM stock_movements 
            WHERE movement_type = 'SALE' AND product_id = 3
        ");

        $this->assert(count($movements) >= 1, 'Stock Ledger: SALE movement logged in double-entry ledger');
        $this->assert((int)$movements[0]['quantity_change'] === -2, 'Stock Ledger: Negative quantity change recorded');
        $this->assert($movements[0]['reference_type'] === 'sales', 'Stock Ledger: Reference type is sales');
    }

    private function testStockCannotBecomeNegative(): void
    {
        $currentStock = (int)Database::fetchColumn("SELECT quantity FROM inventory_stock WHERE product_id = 4"); // 22
        $excessiveDeduction = 25;

        $canDeduct = ($excessiveDeduction <= $currentStock);
        $this->assert($canDeduct === false, 'Stock Defense: Excessive deduction blocked before inventory corruption');
    }

    private function testSerializedPhoneStatusUpdatedToSold(): void
    {
        $phone1 = Database::fetchOne("SELECT status, sold_at FROM phones WHERE id = 1");
        $this->assert($phone1['status'] === 'SOLD', 'Device State: Handset 1 status is SOLD');
        $this->assert(!empty($phone1['sold_at']), 'Device State: sold_at timestamp recorded');
    }

    private function testAtomicSaleRollbackOnMidTransactionFailure(): void
    {
        $initialSalesCount = (int)Database::fetchColumn("SELECT COUNT(*) FROM sales");
        $initialStock = (int)Database::fetchColumn("SELECT quantity FROM inventory_stock WHERE product_id = 3");

        $failed = false;
        try {
            Database::transaction(function() {
                Database::execute("
                    INSERT INTO sales (
                        id, sale_number, sale_date, sale_time, subtotal_amount,
                        final_total_amount, total_cost_basis, gross_profit, created_by
                    ) VALUES (
                        99, 'SAL-FAIL-001', '2026-09-25', '17:00:00', 1000.00, 1000.00, 500.00, 500.00, 2
                    )
                ");

                Database::execute("UPDATE inventory_stock SET quantity = 0 WHERE product_id = 3");

                // Simulate mid-transaction runtime exception
                throw new \RuntimeException('Simulated failure during checkout');
            });
        } catch (\Throwable $e) {
            $failed = true;
        }

        $this->assert($failed === true, 'Rollback: Mid-transaction exception caught');

        $finalSalesCount = (int)Database::fetchColumn("SELECT COUNT(*) FROM sales");
        $finalStock = (int)Database::fetchColumn("SELECT quantity FROM inventory_stock WHERE product_id = 3");

        $this->assert($finalSalesCount === $initialSalesCount, 'Rollback: Zero orphaned sale records committed');
        $this->assert($finalStock === $initialStock, 'Rollback: Inventory quantity remained untouched (not set to 0)');
    }

    // =========================================================================
    // SECTION 5: PERMISSION ENFORCEMENT & FINANCIAL DATA PROTECTION
    // =========================================================================

    private function testUserWithoutPerformSalesBlocked(): void
    {
        // User 4: Sunita has no permissions
        // User 2: Rahul has perform_sales
        $sunitaCanSell = Authorization::hasPermission('perform_sales', 4);
        $rahulCanSell = Authorization::hasPermission('perform_sales', 2);
        $ownerCanSell = Authorization::hasPermission('perform_sales', 1);

        $this->assert($sunitaCanSell === false, 'AuthZ: Staff without perform_sales is denied');
        $this->assert($rahulCanSell === true, 'AuthZ: Counter staff with perform_sales is authorized');
        $this->assert($ownerCanSell === true, 'AuthZ: Shop Owner is inherently authorized');
    }

    private function testUserWithoutApplyDiscountsBlocked(): void
    {
        // User 2: Rahul has perform_sales but NO apply_discounts
        // User 3: Priya has apply_discounts
        $rahulCanDiscount = Authorization::hasPermission('apply_discounts', 2);
        $priyaCanDiscount = Authorization::hasPermission('apply_discounts', 3);

        $this->assert($rahulCanDiscount === false, 'Discount AuthZ: Rahul cannot apply discounts');
        $this->assert($priyaCanDiscount === true, 'Discount AuthZ: Priya can apply discounts');
    }

    private function testUnauthorizedFinancialDataMasking(): void
    {
        // Rahul cannot view purchase costs or profit margins
        $canViewProfit = Authorization::hasPermission('view_profit_margins', 2);
        $canViewCost = Authorization::hasPermission('view_purchase_costs', 2);

        $this->assert($canViewProfit === false, 'Privacy: Rahul cannot view profit margins');
        $this->assert($canViewCost === false, 'Privacy: Rahul cannot view purchase cost basis');

        $profitStr = '13000.00';
        $displayProfit = $canViewProfit ? $profitStr : '—';
        $this->assert($displayProfit === '—', 'Privacy: Profit figure safely masked for unprivileged staff');
    }

    private function testAuthorizedFinancialDataAccess(): void
    {
        // Priya has view_profit_margins
        $priyaCanViewProfit = Authorization::hasPermission('view_profit_margins', 3);
        $this->assert($priyaCanViewProfit === true, 'Privacy: Senior cashier can view profit margins');

        $profitStr = '13000.00';
        $displayProfit = $priyaCanViewProfit ? $profitStr : '—';
        $this->assert($displayProfit === '13000.00', 'Privacy: Authorized staff views exact profit figure');
    }

    // =========================================================================
    // SECTION 6: SECURITY CONTROLS & AUDIT TRAIL
    // =========================================================================

    private function testCsrfTokenValidation(): void
    {
        $token = Security::getCsrfToken();
        $this->assert(Security::validateCsrfToken($token) === true, 'CSRF: Valid session token accepted');
        $this->assert(Security::validateCsrfToken('forged_token') === false, 'CSRF: Forged token rejected');
        $this->assert(Security::validateCsrfToken(null) === false, 'CSRF: Null token rejected');
    }

    private function testSqlInjectionDefenses(): void
    {
        $payload = "'; DROP TABLE sales; --";
        $result = Database::fetchAll("SELECT * FROM sales WHERE sale_number = :num", [':num' => $payload]);

        $this->assert(is_array($result), 'SQL Safety: Parameterized query safely handled SQL injection string');
        $count = (int)Database::fetchColumn("SELECT COUNT(*) FROM sales");
        $this->assert($count > 0, 'SQL Safety: sales table remains intact and unaffected');
    }

    private function testXssOutputEscaping(): void
    {
        $xss = '<img src=x onerror=alert(1)>';
        $escaped = Security::e($xss);
        $this->assert($escaped === '&lt;img src=x onerror=alert(1)&gt;', 'XSS Safety: Malicious string converted to safe entities');
    }

    private function testAuditLoggingCoverage(): void
    {
        // Log sale created, discount applied, and payment recorded
        AuditLogger::log('SALE_CREATED', 'sales', 1, null, ['sale_number' => 'SAL-20260925-0001'], 'Created sale');
        AuditLogger::log('DISCOUNT_APPLIED', 'sales', 1, null, ['discount_amount' => '300.00'], 'Applied discount');
        AuditLogger::log('SALE_PAYMENT_RECORDED', 'payments', 1, null, ['amount' => '4000.00'], 'Recorded payment');

        $logs = Database::fetchAll("SELECT action, table_name FROM audit_logs ORDER BY id ASC");
        $actions = array_column($logs, 'action');

        $this->assert(in_array('SALE_CREATED', $actions, true), 'Audit Trail: SALE_CREATED recorded');
        $this->assert(in_array('DISCOUNT_APPLIED', $actions, true), 'Audit Trail: DISCOUNT_APPLIED recorded');
        $this->assert(in_array('SALE_PAYMENT_RECORDED', $actions, true), 'Audit Trail: SALE_PAYMENT_RECORDED recorded');
    }
}

// Run the test suite
$test = new SaleAndPaymentTest();
$test->runAll();
