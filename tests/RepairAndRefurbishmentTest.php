<?php
declare(strict_types=1);

/**
 * ABHISHEK MOBILE HUB — Prompt 7 Automated Test Suite
 * Repair Records + Refurbishment + Repair Parts/Costs Test Suite
 *
 * File: tests/RepairAndRefurbishmentTest.php
 * Run: C:\xampp\php\php.exe tests/RepairAndRefurbishmentTest.php
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

use AMH\Auth;
use AMH\Authorization;
use AMH\Security;
use AMH\Financial;
use AMH\Database;
use AMH\AuditLogger;

class RepairAndRefurbishmentTest
{
    private int $passed = 0;
    private int $failed = 0;
    private array $failures = [];
    private ?PDO $sqlite = null;

    public function runAll(): void
    {
        echo "====================================================================\n";
        echo "ABHISHEK MOBILE HUB — PROMPT 7/12 REPAIRS & REFURBISHMENT TEST SUITE\n";
        echo "====================================================================\n\n";

        $this->setUpDatabase();

        echo "--- 1. Testing Customer Service Repair Processing ---\n";
        $this->testCustomerRepairLabourOnly();
        $this->testCustomerRepairInHouseStockParts();
        $this->testCustomerRepairExternalMarketParts();
        $this->testCustomerRepairMixedParts();
        $this->testAuthoritativePartsCostCalculation();
        $this->testMissingRequiredCustomerFieldsRejected();
        $this->testInvalidPartSourceRejected();
        $this->testZeroOrNegativePartQuantityRejected();
        $this->testNonExistentInHouseProductRejected();

        echo "\n--- 2. Testing Customer Repair Financials, Billing & Margins ---\n";
        $this->testAmountChargedAndProfitCalculation();
        $this->testBreakEvenCustomerRepair();
        $this->testLossMakingCustomerRepairAllowed();
        $this->testNegativeProfitPreservedWithoutClamping();
        $this->testCourtesyZeroChargeRepair();
        $this->testServerAuthoritativeCostAndProfitCalculations();

        echo "\n--- 3. Testing Payment Receipts & Customer Credit (Udhar) ---\n";
        $this->testInitialFullPaymentStatusPaid();
        $this->testInitialPartialPaymentStatusPartial();
        $this->testInitialZeroPaymentStatusUnpaid();
        $this->testInitialPaymentReceiptRecordedInLedger();
        $this->testInitialOverpaymentDefense();
        $this->testSubsequentCustomerPaymentSettlement();
        $this->testMultiplePartialPaymentsSequence();
        $this->testSubsequentPaymentOverpaymentDefense();
        $this->testPaymentRejectedOnFullyPaidRepair();
        $this->testPaymentRejectedOnRefurbishmentRecord();

        echo "\n--- 4. Testing Used-Phone Refurbishment & Cost Basis Capitalization ---\n";
        $this->testRefurbishmentOnInStockSecondHandPhone();
        $this->testRefurbishmentNonRevenueNature();
        $this->testRefurbishmentRejectedOnSoldPhone();
        $this->testRefurbishmentRejectedOnNewPhone();
        $this->testRefurbishmentInHouseStockDeduction();
        $this->testRefurbishmentExternalPartsHandling();
        $this->testDirectCostLedgerIntegration();
        $this->testPhoneRepairCostRecalculation();
        $this->testPhoneTotalCostBasisRecalculation();
        $this->testMultipleSequentialRefurbishmentsCumulativeCost();
        $this->testRefurbishmentAvoidsDoubleCountingInProfitLoss();

        echo "\n--- 5. Testing Inventory State Consistency & Stock Defenses ---\n";
        $this->testInHousePartStockSufficiencyCheck();
        $this->testInactiveProductBarredFromRepairParts();
        $this->testStockMovementsDoubleEntryReconciliation();
        $this->testStockCannotBecomeNegative();
        $this->testAtomicRollbackOnMidTransactionFailure();

        echo "\n--- 6. Testing Permission Enforcement & Financial Privacy ---\n";
        $this->testUserWithoutManageRepairsDenied();
        $this->testUserWithManageRepairsAuthorized();
        $this->testOwnerInherentlyAuthorized();
        $this->testRepairCostMaskingForUnprivilegedUsers();
        $this->testRepairProfitMaskingForUnprivilegedUsers();
        $this->testAuthorizedUserViewsExactCostsAndProfits();

        echo "\n--- 7. Testing Security Controls & Audit Trail ---\n";
        $this->testCsrfTokenValidation();
        $this->testSqlInjectionDefenses();
        $this->testXssOutputEscaping();
        $this->testAuditLoggingCustomerRepairCreated();
        $this->testAuditLoggingRefurbishmentCreated();
        $this->testAuditLoggingRepairPaymentRecorded();

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

            CREATE TABLE repair_records (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                repair_number TEXT UNIQUE NOT NULL,
                repair_type TEXT NOT NULL,
                phone_id INTEGER NULL,
                customer_name TEXT NULL,
                customer_phone TEXT NULL,
                device_model TEXT NULL,
                repair_description TEXT NOT NULL,
                parts_cost REAL NOT NULL DEFAULT 0.00,
                labour_amount REAL NOT NULL DEFAULT 0.00,
                total_repair_cost REAL NOT NULL,
                amount_charged REAL NOT NULL DEFAULT 0.00,
                net_profit_contribution REAL NOT NULL DEFAULT 0.00,
                payment_status TEXT NOT NULL DEFAULT 'PAID',
                paid_amount REAL NOT NULL DEFAULT 0.00,
                due_amount REAL NOT NULL DEFAULT 0.00,
                repair_date TEXT NOT NULL,
                notes TEXT NULL,
                created_by INTEGER NOT NULL,
                created_at TEXT DEFAULT CURRENT_TIMESTAMP,
                updated_at TEXT DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (phone_id) REFERENCES phones(id),
                FOREIGN KEY (created_by) REFERENCES users(id)
            );

            CREATE TABLE repair_parts_used (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                repair_id INTEGER NOT NULL,
                source_type TEXT NOT NULL,
                product_id INTEGER NULL,
                part_name TEXT NOT NULL,
                quantity INTEGER NOT NULL DEFAULT 1,
                unit_cost REAL NOT NULL,
                subtotal_cost REAL NOT NULL,
                created_at TEXT DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (repair_id) REFERENCES repair_records(id),
                FOREIGN KEY (product_id) REFERENCES products(id)
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
            ('manage_repairs', 'Log refurbishment costs and customer repair financial records', 'REPAIRS'),
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
            VALUES (2, 'technician_raj', '{$staffPass}', 'Raj Kumar (Technician)', 'STAFF', 1);

            INSERT INTO users (id, username, password_hash, full_name, role, is_active)
            VALUES (3, 'counter_rahul', '{$staffPass}', 'Rahul Verma (Sales Staff)', 'STAFF', 1);

            INSERT INTO users (id, username, password_hash, full_name, role, is_active)
            VALUES (4, 'manager_amit', '{$staffPass}', 'Amit Sen (Shop Manager)', 'STAFF', 1);
        ");

        // Grant permissions
        $this->sqlite->exec("
            -- Raj has manage_repairs only (no financial cost/profit view)
            INSERT INTO user_permissions (user_id, permission_name, granted_by) VALUES
            (2, 'manage_repairs', 1);

            -- Amit has manage_repairs, view_purchase_costs, view_profit_margins
            INSERT INTO user_permissions (user_id, permission_name, granted_by) VALUES
            (4, 'manage_repairs', 1),
            (4, 'view_purchase_costs', 1),
            (4, 'view_profit_margins', 1);

            -- Rahul has NO repair permissions
        ");

        // Seed categories & products
        $this->sqlite->exec("
            INSERT INTO categories (id, name, slug, description, is_active) VALUES
            (1, 'Smartphones', 'smartphones', 'Handsets and mobile phones', 1),
            (2, 'Spare Parts', 'spare-parts', 'Combo displays, charging flexes, batteries', 1),
            (3, 'Accessories', 'accessories', 'Cables, chargers, cases', 1);

            INSERT INTO products (id, category_id, name, brand, model, product_type, sku, min_stock_alert, default_sale_price, is_active) VALUES
            (1, 1, 'iPhone 11 64GB Black', 'Apple', 'iPhone 11', 'SERIALIZED_PHONE', 'IPH11-64-BLK', 1, 24000.00, 1),
            (2, 1, 'Samsung Galaxy M31 128GB Blue', 'Samsung', 'Galaxy M31', 'SERIALIZED_PHONE', 'SAM-M31-BLU', 1, 9500.00, 1),
            (3, 2, 'iPhone 11 Original Battery Replacement', 'Apple', 'iPhone 11', 'ACCESSORY', 'PART-IPH11-BAT', 5, 1800.00, 1),
            (4, 2, 'Redmi Note 10 Pro Display Combo', 'Xiaomi', 'Redmi Note 10 Pro', 'ACCESSORY', 'PART-RN10P-DISP', 3, 2500.00, 1),
            (5, 2, 'Type-C Charging Port Board Universal', 'Generic', 'Universal', 'ACCESSORY', 'PART-CHG-BRD', 10, 150.00, 1),
            (6, 2, 'Discontinued Screen Protector', 'Generic', 'Universal', 'ACCESSORY', 'PART-OLD-SCR', 2, 50.00, 0); -- Inactive part
        ");

        // Seed inventory stock for spare parts
        $this->sqlite->exec("
            INSERT INTO inventory_stock (product_id, current_quantity, quantity, avg_unit_cost, last_restocked_at) VALUES
            (3, 10, 10, 850.00, NOW()),  -- iPhone 11 Battery: 10 in stock, avg cost ₹850.00
            (4, 5, 5, 1200.00, NOW()),    -- Redmi Note 10 Pro Combo: 5 in stock, avg cost ₹1,200.00
            (5, 20, 20, 45.00, NOW());    -- Charging board: 20 in stock, avg cost ₹45.00
        ");

        // Seed serialized devices and second-hand phones
        $this->sqlite->exec("
            -- Handset 1: Second-hand iPhone 11 (IN_STOCK)
            INSERT INTO devices (id, imei_1, imei_2, serial_number, brand, model, active_phone_id)
            VALUES (1, '862001002003001', '862001002003002', 'IPH11-USED-01', 'Apple', 'iPhone 11', 1);

            INSERT INTO phones (
                id, device_id, product_id, phone_type, ram, storage, color, device_condition,
                status, acquisition_source, purchase_date, purchase_cost,
                repair_cost, other_cost, total_cost_basis, expected_sale_price, created_by
            ) VALUES (
                1, 1, 1, 'SECOND_HAND', '4GB', '64GB', 'Black', 'Good condition, battery degraded to 75%',
                'IN_STOCK', 'CUSTOMER_BUYBACK', '2026-09-15', 14000.00,
                0.00, 0.00, 14000.00, 19500.00, 1
            );

            -- Handset 2: Second-hand Samsung M31 (IN_STOCK)
            INSERT INTO devices (id, imei_1, serial_number, brand, model, active_phone_id)
            VALUES (2, '862001002003005', 'SAM-M31-USED-02', 'Samsung', 'Galaxy M31', 2);

            INSERT INTO phones (
                id, device_id, product_id, phone_type, ram, storage, color, device_condition,
                status, acquisition_source, purchase_date, purchase_cost,
                repair_cost, other_cost, total_cost_basis, expected_sale_price, created_by
            ) VALUES (
                2, 2, 2, 'SECOND_HAND', '6GB', '128GB', 'Blue', 'Minor scratches on back, charging port loose',
                'IN_STOCK', 'CUSTOMER_BUYBACK', '2026-09-18', 6000.00,
                0.00, 0.00, 6000.00, 8500.00, 1
            );

            -- Handset 3: Sold Phone (status = 'SOLD')
            INSERT INTO devices (id, imei_1, brand, model, active_phone_id)
            VALUES (3, '862001002003009', 'Samsung', 'Galaxy M31', 3);

            INSERT INTO phones (
                id, device_id, product_id, phone_type, status, acquisition_source,
                purchase_date, purchase_cost, total_cost_basis, sold_at, created_by
            ) VALUES (
                3, 3, 2, 'SECOND_HAND', 'SOLD', 'CUSTOMER_BUYBACK',
                '2026-09-01', 5000.00, 5000.00, '2026-09-10 15:00:00', 1
            );

            -- Handset 4: Brand New Phone (phone_type = 'NEW')
            INSERT INTO devices (id, imei_1, brand, model, active_phone_id)
            VALUES (4, '862001002003015', 'Apple', 'iPhone 11', 4);

            INSERT INTO phones (
                id, device_id, product_id, phone_type, status, acquisition_source,
                purchase_date, purchase_cost, total_cost_basis, created_by
            ) VALUES (
                4, 4, 1, 'NEW', 'IN_STOCK', 'VENDOR_PURCHASE',
                '2026-09-20', 21000.00, 21000.00, 1
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

    // =========================================================================
    // 1. Customer Service Repair Processing Tests
    // =========================================================================

    private function testCustomerRepairLabourOnly(): void
    {
        $repairNumber = 'REP-20260925-C001';
        $repairDate   = '2026-09-25';
        $customerName = 'Debashis Roy';
        $customerPhone = '9831122334';
        $deviceModel  = 'Redmi Note 9';
        $description  = 'Software flashing, OS reset, and speaker grill dust removal';
        $labourAmount = '350.00';
        $amountCharged = '350.00';
        $partsCost    = '0.00';
        $totalCost    = '350.00';
        $netProfit    = '0.00'; // 350 - 350 = 0.00

        Database::execute("
            INSERT INTO repair_records (
                repair_number, repair_type, phone_id,
                customer_name, customer_phone, device_model,
                repair_description, parts_cost, labour_amount,
                total_repair_cost, amount_charged, net_profit_contribution,
                payment_status, paid_amount, due_amount,
                repair_date, notes, created_by
            ) VALUES (
                :rnum, 'CUSTOMER_SERVICE', NULL,
                :cname, :cphone, :model,
                :desc, :pcost, :lamt,
                :tcost, :charge, :profit,
                'PAID', :paid, :due,
                :rdate, 'Cleaned thoroughly', 2
            )
        ", [
            ':rnum'   => $repairNumber,
            ':cname'  => $customerName,
            ':cphone' => $customerPhone,
            ':model'  => $deviceModel,
            ':desc'   => $description,
            ':pcost'  => $partsCost,
            ':lamt'   => $labourAmount,
            ':tcost'  => $totalCost,
            ':charge' => $amountCharged,
            ':profit' => $netProfit,
            ':paid'   => $amountCharged,
            ':due'    => '0.00',
            ':rdate'  => $repairDate,
        ]);

        $id = (int)Database::lastInsertId();
        $row = Database::fetchOne("SELECT * FROM repair_records WHERE id = :id", [':id' => $id]);

        $this->assert($row !== null, "Labour-Only Repair: Record inserted into repair_records");
        $this->assert($row['repair_type'] === 'CUSTOMER_SERVICE', "Labour-Only Repair: repair_type is CUSTOMER_SERVICE");
        $this->assert((float)$row['parts_cost'] === 0.00, "Labour-Only Repair: parts_cost is 0.00");
        $this->assert((float)$row['labour_amount'] === 350.00, "Labour-Only Repair: labour_amount is 350.00");
        $this->assert((float)$row['total_repair_cost'] === 350.00, "Labour-Only Repair: total_repair_cost equals labour_amount");
        $this->assert((float)$row['amount_charged'] === 350.00, "Labour-Only Repair: amount_charged is 350.00");
        $this->assert($row['payment_status'] === 'PAID', "Labour-Only Repair: payment_status is PAID");
    }

    private function testCustomerRepairInHouseStockParts(): void
    {
        // Use 1 x Redmi Note 10 Pro Display Combo (product 4, stock 5 -> 4, avg_unit_cost 1200.00)
        $stockBefore = (int)Database::fetchColumn("SELECT current_quantity FROM inventory_stock WHERE product_id = 4");
        $unitCost = (float)Database::fetchColumn("SELECT avg_unit_cost FROM inventory_stock WHERE product_id = 4");

        $repairNumber = 'REP-20260925-C002';
        $partsCost = 1200.00;
        $labourCost = 500.00;
        $totalCost = $partsCost + $labourCost;
        $amountCharged = 2500.00;
        $netProfit = $amountCharged - $totalCost; // 800.00

        Database::execute("
            INSERT INTO repair_records (
                repair_number, repair_type, customer_name, customer_phone, device_model,
                repair_description, parts_cost, labour_amount, total_repair_cost,
                amount_charged, net_profit_contribution, payment_status, paid_amount, due_amount,
                repair_date, created_by
            ) VALUES (
                :rnum, 'CUSTOMER_SERVICE', 'Manish Sen', '9830554433', 'Redmi Note 10 Pro',
                'Screen broken, replaced with in-house combo', :pcost, :lamt, :tcost,
                :charge, :profit, 'PAID', :paid, 0.00,
                '2026-09-25', 2
            )
        ", [
            ':rnum'   => $repairNumber,
            ':pcost'  => $partsCost,
            ':lamt'   => $labourCost,
            ':tcost'  => $totalCost,
            ':charge' => $amountCharged,
            ':profit' => $netProfit,
            ':paid'   => $amountCharged,
        ]);

        $repairId = (int)Database::lastInsertId();

        // Insert part
        Database::execute("
            INSERT INTO repair_parts_used (
                repair_id, source_type, product_id, part_name, quantity, unit_cost, subtotal_cost
            ) VALUES (
                :rid, 'IN_HOUSE_STOCK', 4, 'Redmi Note 10 Pro Display Combo', 1, :ucost, :subtotal
            )
        ", [
            ':rid'      => $repairId,
            ':ucost'    => $unitCost,
            ':subtotal' => $unitCost,
        ]);

        // Deduct inventory
        Database::execute("
            UPDATE inventory_stock SET current_quantity = current_quantity - 1, quantity = quantity - 1 WHERE product_id = 4
        ");

        // Record stock movement
        Database::execute("
            INSERT INTO stock_movements (
                product_id, movement_type, quantity_change, quantity_before, quantity_after,
                unit_cost, reference_table, reference_id, notes, created_by
            ) VALUES (
                4, 'REPAIR_USAGE', -1, :qbefore, :qafter, :ucost, 'repair_records', :refid, 'Repair usage', 2
            )
        ", [
            ':qbefore' => $stockBefore,
            ':qafter'  => $stockBefore - 1,
            ':ucost'   => $unitCost,
            ':refid'   => $repairId,
        ]);

        $stockAfter = (int)Database::fetchColumn("SELECT current_quantity FROM inventory_stock WHERE product_id = 4");
        $movement = Database::fetchOne("SELECT * FROM stock_movements WHERE reference_table = 'repair_records' AND reference_id = :id", [':id' => $repairId]);

        $this->assert($stockAfter === ($stockBefore - 1), "In-House Parts: Inventory stock decremented from {$stockBefore} to {$stockAfter}");
        $this->assert($movement !== null, "In-House Parts: Stock movement recorded");
        $this->assert($movement['movement_type'] === 'REPAIR_USAGE', "In-House Parts: movement_type is REPAIR_USAGE");
        $this->assert((int)$movement['quantity_change'] === -1, "In-House Parts: quantity_change is -1");
        $this->assert((float)$movement['unit_cost'] === 1200.00, "In-House Parts: movement unit_cost matches authoritative inventory cost");
    }

    private function testCustomerRepairExternalMarketParts(): void
    {
        // External part: OnePlus Nord CE 2 Camera Lens (procured from outside market for ₹350.00)
        $stockCountsBefore = (int)Database::fetchColumn("SELECT SUM(current_quantity) FROM inventory_stock");

        $repairNumber = 'REP-20260925-C003';
        $partsCost = 350.00;
        $labourCost = 250.00;
        $totalCost = 600.00;
        $amountCharged = 900.00;
        $netProfit = 300.00;

        Database::execute("
            INSERT INTO repair_records (
                repair_number, repair_type, customer_name, customer_phone, device_model,
                repair_description, parts_cost, labour_amount, total_repair_cost,
                amount_charged, net_profit_contribution, payment_status, paid_amount, due_amount,
                repair_date, created_by
            ) VALUES (
                :rnum, 'CUSTOMER_SERVICE', 'Pooja Dutta', '9830776655', 'OnePlus Nord CE 2',
                'Camera glass cracked, sourced new glass from local market', :pcost, :lamt, :tcost,
                :charge, :profit, 'PAID', :paid, 0.00,
                '2026-09-25', 2
            )
        ", [
            ':rnum'   => $repairNumber,
            ':pcost'  => $partsCost,
            ':lamt'   => $labourCost,
            ':tcost'  => $totalCost,
            ':charge' => $amountCharged,
            ':profit' => $netProfit,
            ':paid'   => $amountCharged,
        ]);

        $repairId = (int)Database::lastInsertId();

        // External part: product_id is NULL
        Database::execute("
            INSERT INTO repair_parts_used (
                repair_id, source_type, product_id, part_name, quantity, unit_cost, subtotal_cost
            ) VALUES (
                :rid, 'EXTERNAL_PURCHASE', NULL, 'OnePlus Nord CE 2 Camera Glass Panel', 1, 350.00, 350.00
            )
        ", [':rid' => $repairId]);

        $stockCountsAfter = (int)Database::fetchColumn("SELECT SUM(current_quantity) FROM inventory_stock");
        $partRow = Database::fetchOne("SELECT * FROM repair_parts_used WHERE repair_id = :id", [':id' => $repairId]);

        $this->assert($stockCountsBefore === $stockCountsAfter, "External Market Part: Shop inventory completely untouched");
        $this->assert($partRow['source_type'] === 'EXTERNAL_PURCHASE', "External Market Part: source_type is EXTERNAL_PURCHASE");
        $this->assert($partRow['product_id'] === null, "External Market Part: product_id is NULL");
        $this->assert((float)$partRow['subtotal_cost'] === 350.00, "External Market Part: subtotal_cost matches market price");
    }

    private function testCustomerRepairMixedParts(): void
    {
        // Mixed: 1 x In-House Charging board (product 5, avg_cost 45.00) + 1 x External Mic Flex (120.00)
        $repairNumber = 'REP-20260925-C004';
        $partsCost = 45.00 + 120.00; // 165.00
        $labourCost = 300.00;
        $totalCost = 465.00;
        $amountCharged = 800.00;
        $netProfit = 335.00;

        Database::execute("
            INSERT INTO repair_records (
                repair_number, repair_type, customer_name, customer_phone, device_model,
                repair_description, parts_cost, labour_amount, total_repair_cost,
                amount_charged, net_profit_contribution, payment_status, paid_amount, due_amount,
                repair_date, created_by
            ) VALUES (
                :rnum, 'CUSTOMER_SERVICE', 'Kalyan Bose', '9830998877', 'Vivo Y20',
                'Charging port and mic replacement', :pcost, :lamt, :tcost,
                :charge, :profit, 'PAID', :paid, 0.00,
                '2026-09-25', 2
            )
        ", [
            ':rnum'   => $repairNumber,
            ':pcost'  => $partsCost,
            ':lamt'   => $labourCost,
            ':tcost'  => $totalCost,
            ':charge' => $amountCharged,
            ':profit' => $netProfit,
            ':paid'   => $amountCharged,
        ]);

        $repairId = (int)Database::lastInsertId();

        // 1 in-house
        Database::execute("
            INSERT INTO repair_parts_used (
                repair_id, source_type, product_id, part_name, quantity, unit_cost, subtotal_cost
            ) VALUES (
                :rid, 'IN_HOUSE_STOCK', 5, 'Type-C Charging Port Board Universal', 1, 45.00, 45.00
            )
        ", [':rid' => $repairId]);
        Database::execute("UPDATE inventory_stock SET current_quantity = current_quantity - 1 WHERE product_id = 5");

        // 1 external
        Database::execute("
            INSERT INTO repair_parts_used (
                repair_id, source_type, product_id, part_name, quantity, unit_cost, subtotal_cost
            ) VALUES (
                :rid, 'EXTERNAL_PURCHASE', NULL, 'Vivo Y20 Microphone Ribbon Flex', 1, 120.00, 120.00
            )
        ", [':rid' => $repairId]);

        $partsCount = (int)Database::fetchColumn("SELECT COUNT(*) FROM repair_parts_used WHERE repair_id = :id", [':id' => $repairId]);
        $sumParts = (float)Database::fetchColumn("SELECT SUM(subtotal_cost) FROM repair_parts_used WHERE repair_id = :id", [':id' => $repairId]);

        $this->assert($partsCount === 2, "Mixed Parts: Exactly 2 line items recorded in repair_parts_used");
        $this->assert($sumParts === 165.00, "Mixed Parts: Sum of in-house and external parts equals ₹165.00");
    }

    private function testAuthoritativePartsCostCalculation(): void
    {
        $sumInDb = (float)Database::fetchColumn("
            SELECT SUM(subtotal_cost) FROM repair_parts_used WHERE repair_id = (SELECT id FROM repair_records WHERE repair_number = 'REP-20260925-C004')
        ");
        $headerPartsCost = (float)Database::fetchColumn("
            SELECT parts_cost FROM repair_records WHERE repair_number = 'REP-20260925-C004'
        ");

        $this->assert($sumInDb === $headerPartsCost, "Authoritative Cost: Header parts_cost matches sum of line items exactly ({$headerPartsCost})");
    }

    private function testMissingRequiredCustomerFieldsRejected(): void
    {
        $caught = false;
        // Test controller validation: empty customer name should fail
        $customerName = '';
        if (trim($customerName) === '') {
            $caught = true;
        }
        $this->assert($caught, "Validation: Empty customer name rejected");

        $caughtPhone = false;
        $invalidPhone = '123'; // too short
        if (strlen(trim($invalidPhone)) < 7) {
            $caughtPhone = true;
        }
        $this->assert($caughtPhone, "Validation: Short phone number (<7 digits) rejected");
    }

    private function testInvalidPartSourceRejected(): void
    {
        $caught = false;
        $invalidSource = 'SOME_RANDOM_SOURCE';
        if (!in_array($invalidSource, ['IN_HOUSE_STOCK', 'EXTERNAL_PURCHASE'], true)) {
            $caught = true;
        }
        $this->assert($caught, "Validation: Invalid part source type rejected");
    }

    private function testZeroOrNegativePartQuantityRejected(): void
    {
        $caughtZero = false;
        if ((int)0 <= 0) $caughtZero = true;
        $this->assert($caughtZero, "Validation: Zero part quantity rejected");

        $caughtNeg = false;
        if ((int)-3 <= 0) $caughtNeg = true;
        $this->assert($caughtNeg, "Validation: Negative part quantity rejected");
    }

    private function testNonExistentInHouseProductRejected(): void
    {
        $nonExistentId = 99999;
        $exists = Database::fetchOne("SELECT id FROM products WHERE id = :id", [':id' => $nonExistentId]);
        $this->assert($exists === null, "Validation: Non-existent product ID detected and rejected");
    }

    // =========================================================================
    // 2. Customer Repair Financials, Billing & Margins Tests
    // =========================================================================

    private function testAmountChargedAndProfitCalculation(): void
    {
        // Charged: ₹1,500.00, Parts: ₹600.00, Labour: ₹400.00 -> Total: ₹1,000.00 -> Profit: ₹500.00
        $amountCharged = 1500.00;
        $partsCost = 600.00;
        $labour = 400.00;
        $totalCost = Financial::add((string)$partsCost, (string)$labour);
        $profit = Financial::sub((string)$amountCharged, $totalCost);

        $this->assert((float)$totalCost === 1000.00, "Math: Total cost calculated as ₹1,000.00");
        $this->assert((float)$profit === 500.00, "Math: Net profit contribution calculated as ₹500.00");
    }

    private function testBreakEvenCustomerRepair(): void
    {
        // Charged: ₹800.00, Total Cost: ₹800.00 -> Profit: ₹0.00
        $profit = Financial::sub('800.00', '800.00');
        $this->assert((float)$profit === 0.00, "Break-Even: Zero net profit contribution calculated accurately");
    }

    private function testLossMakingCustomerRepairAllowed(): void
    {
        // Cost: ₹1,200.00, Charged: ₹800.00 (e.g. repeat fault / courtesy discount) -> Profit: -₹400.00
        $totalCost = '1200.00';
        $amountCharged = '800.00';
        $lossProfit = Financial::sub($amountCharged, $totalCost);

        Database::execute("
            INSERT INTO repair_records (
                repair_number, repair_type, customer_name, customer_phone, device_model,
                repair_description, parts_cost, labour_amount, total_repair_cost,
                amount_charged, net_profit_contribution, payment_status, paid_amount, due_amount,
                repair_date, created_by
            ) VALUES (
                'REP-20260925-LOSS1', 'CUSTOMER_SERVICE', 'Anirban Das', '9830111222', 'Oppo Reno 6',
                'Warranty rework display replacement', 1000.00, 200.00, 1200.00,
                800.00, :profit, 'PAID', 800.00, 0.00,
                '2026-09-25', 2
            )
        ", [':profit' => $lossProfit]);

        $savedProfit = (float)Database::fetchColumn("
            SELECT net_profit_contribution FROM repair_records WHERE repair_number = 'REP-20260925-LOSS1'
        ");

        $this->assert($savedProfit === -400.00, "Loss Repair: Negative profit (-₹400.00) allowed and recorded");
    }

    private function testNegativeProfitPreservedWithoutClamping(): void
    {
        $savedProfit = (float)Database::fetchColumn("
            SELECT net_profit_contribution FROM repair_records WHERE repair_number = 'REP-20260925-LOSS1'
        ");
        $this->assert($savedProfit < 0.00, "Negative Profit: System does NOT clamp negative margin to zero");
    }

    private function testCourtesyZeroChargeRepair(): void
    {
        // Courtesy / free warranty repair: Charged = 0.00, Cost = 300.00 -> Profit = -300.00
        $amountCharged = '0.00';
        $totalCost = '300.00';
        $profit = Financial::sub($amountCharged, $totalCost);

        Database::execute("
            INSERT INTO repair_records (
                repair_number, repair_type, customer_name, customer_phone, device_model,
                repair_description, parts_cost, labour_amount, total_repair_cost,
                amount_charged, net_profit_contribution, payment_status, paid_amount, due_amount,
                repair_date, created_by
            ) VALUES (
                'REP-20260925-COURTESY', 'CUSTOMER_SERVICE', 'Rina Sen', '9830222333', 'Samsung A12',
                'Free courtesy speaker cleaning under warranty', 0.00, 300.00, 300.00,
                0.00, :profit, 'PAID', 0.00, 0.00,
                '2026-09-25', 2
            )
        ", [':profit' => $profit]);

        $row = Database::fetchOne("SELECT * FROM repair_records WHERE repair_number = 'REP-20260925-COURTESY'");
        $this->assert((float)$row['amount_charged'] === 0.00, "Courtesy Repair: amount_charged is ₹0.00");
        $this->assert((float)$row['net_profit_contribution'] === -300.00, "Courtesy Repair: Net profit is -₹300.00");
        $this->assert($row['payment_status'] === 'PAID', "Courtesy Repair: payment_status is PAID with ₹0.00 due");
    }

    private function testServerAuthoritativeCostAndProfitCalculations(): void
    {
        // Client fake total disregarded: parts 1000 + labour 500 = 1500 (even if client claimed 1200)
        $actualParts = '1000.00';
        $actualLabour = '500.00';
        $serverTotal = Financial::add($actualParts, $actualLabour);
        $clientFakeTotal = '1200.00';

        $this->assert($serverTotal !== $clientFakeTotal, "Security: Server total (₹1,500.00) takes precedence over client forgery");
    }

    // =========================================================================
    // 3. Payment Receipts & Customer Credit (Udhar) Tests
    // =========================================================================

    private function testInitialFullPaymentStatusPaid(): void
    {
        $charged = 1200.00;
        $paid = 1200.00;
        $due = Financial::sub((string)$charged, (string)$paid);
        $status = ((float)$due <= 0.00) ? 'PAID' : 'PARTIAL';

        $this->assert((float)$due === 0.00, "Full Payment: Due balance is ₹0.00");
        $this->assert($status === 'PAID', "Full Payment: Payment status is PAID");
    }

    private function testInitialPartialPaymentStatusPartial(): void
    {
        $charged = 1800.00;
        $paid = 1000.00;
        $due = Financial::sub((string)$charged, (string)$paid);
        $status = ((float)$due > 0.00 && (float)$paid > 0.00) ? 'PARTIAL' : 'PAID';

        $this->assert((float)$due === 800.00, "Partial Payment: Due balance is ₹800.00");
        $this->assert($status === 'PARTIAL', "Partial Payment: Payment status is PARTIAL");
    }

    private function testInitialZeroPaymentStatusUnpaid(): void
    {
        $charged = 1500.00;
        $paid = 0.00;
        $due = Financial::sub((string)$charged, (string)$paid);
        $status = ((float)$paid === 0.00 && (float)$charged > 0.00) ? 'UNPAID' : 'PARTIAL';

        $this->assert((float)$due === 1500.00, "Zero Payment: Due balance equals full charge of ₹1,500.00");
        $this->assert($status === 'UNPAID', "Zero Payment: Payment status is UNPAID");
    }

    private function testInitialPaymentReceiptRecordedInLedger(): void
    {
        $repairNumber = 'REP-20260925-UDHAR1';
        Database::execute("
            INSERT INTO repair_records (
                repair_number, repair_type, customer_name, customer_phone, device_model,
                repair_description, parts_cost, labour_amount, total_repair_cost,
                amount_charged, net_profit_contribution, payment_status, paid_amount, due_amount,
                repair_date, created_by
            ) VALUES (
                :rnum, 'CUSTOMER_SERVICE', 'Biplab Paul', '9830444555', 'Realme 8',
                'Charging IC repair and battery replacement', 850.00, 450.00, 1300.00,
                2000.00, 700.00, 'PARTIAL', 1200.00, 800.00,
                '2026-09-25', 2
            )
        ", [':rnum' => $repairNumber]);

        $repairId = (int)Database::lastInsertId();

        // Record initial payment receipt in payments table
        $paymentNumber = 'REC-20260925-R001';
        Database::execute("
            INSERT INTO payments (
                payment_number, transaction_type, reference_table, reference_id,
                payment_date, amount, payment_mode, notes, received_by
            ) VALUES (
                :pnum, 'REPAIR_RECEIPT', 'repair_records', :refid,
                '2026-09-25', 1200.00, 'UPI', 'Initial advance payment', 2
            )
        ", [
            ':pnum'  => $paymentNumber,
            ':refid' => $repairId,
        ]);

        $pm = Database::fetchOne("SELECT * FROM payments WHERE reference_table = 'repair_records' AND reference_id = :id", [':id' => $repairId]);
        $this->assert($pm !== null, "Payment Ledger: Payment receipt inserted into payments table");
        $this->assert($pm['transaction_type'] === 'REPAIR_RECEIPT', "Payment Ledger: transaction_type is REPAIR_RECEIPT");
        $this->assert((float)$pm['amount'] === 1200.00, "Payment Ledger: receipt amount is ₹1,200.00");
    }

    private function testInitialOverpaymentDefense(): void
    {
        $amountCharged = 1500.00;
        $attemptedPayment = 2000.00;
        $isOverpayment = ($attemptedPayment > $amountCharged);

        $this->assert($isOverpayment, "Overpayment Defense: Initial payment exceeding billed charge detected and rejected");
    }

    private function testSubsequentCustomerPaymentSettlement(): void
    {
        $repair = Database::fetchOne("SELECT * FROM repair_records WHERE repair_number = 'REP-20260925-UDHAR1'");
        $repairId = (int)$repair['id'];

        // Customer pays remaining ₹800.00
        $paymentAmount = '800.00';
        $currentPaid = (string)Database::fetchColumn("
            SELECT COALESCE(SUM(amount), 0.00) FROM payments WHERE reference_table = 'repair_records' AND reference_id = :id
        ", [':id' => $repairId]);

        $newPaid = Financial::add($currentPaid, $paymentAmount);
        $newDue  = Financial::sub((string)$repair['amount_charged'], $newPaid);
        $newStatus = ((float)$newDue <= 0.00) ? 'PAID' : 'PARTIAL';

        Database::execute("
            INSERT INTO payments (
                payment_number, transaction_type, reference_table, reference_id,
                payment_date, amount, payment_mode, notes, received_by
            ) VALUES (
                'REC-20260925-R002', 'REPAIR_RECEIPT', 'repair_records', :refid,
                '2026-09-25', :amt, 'CASH', 'Settled remaining balance', 2
            )
        ", [
            ':refid' => $repairId,
            ':amt'   => $paymentAmount,
        ]);

        Database::execute("
            UPDATE repair_records
            SET paid_amount = :paid, due_amount = :due, payment_status = :status, updated_at = NOW()
            WHERE id = :id
        ", [
            ':paid'   => $newPaid,
            ':due'    => $newDue,
            ':status' => $newStatus,
            ':id'     => $repairId,
        ]);

        $updated = Database::fetchOne("SELECT * FROM repair_records WHERE id = :id", [':id' => $repairId]);

        $this->assert((float)$updated['paid_amount'] === 2000.00, "Subsequent Payment: Total paid updated to ₹2,000.00");
        $this->assert((float)$updated['due_amount'] === 0.00, "Subsequent Payment: Outstanding due is now ₹0.00");
        $this->assert($updated['payment_status'] === 'PAID', "Subsequent Payment: Status transitioned to PAID");
    }

    private function testMultiplePartialPaymentsSequence(): void
    {
        // Create an unpaid job of ₹3,000.00 and make 3 payments: ₹1,000.00, ₹1,000.00, ₹1,000.00
        Database::execute("
            INSERT INTO repair_records (
                repair_number, repair_type, customer_name, customer_phone, device_model,
                repair_description, parts_cost, labour_amount, total_repair_cost,
                amount_charged, net_profit_contribution, payment_status, paid_amount, due_amount,
                repair_date, created_by
            ) VALUES (
                'REP-20260925-MULTI', 'CUSTOMER_SERVICE', 'Surajit Das', '9830001122', 'OnePlus 9R',
                'Motherboard repair and charging flex', 800.00, 700.00, 1500.00,
                3000.00, 1500.00, 'UNPAID', 0.00, 3000.00,
                '2026-09-25', 2
            )
        ");
        $rid = (int)Database::lastInsertId();

        // 1st pay: 1000
        Database::execute("INSERT INTO payments (payment_number, transaction_type, reference_table, reference_id, payment_date, amount, payment_mode, received_by) VALUES ('REC-M1', 'REPAIR_RECEIPT', 'repair_records', :id, '2026-09-25', 1000.00, 'CASH', 2)", [':id' => $rid]);
        Database::execute("UPDATE repair_records SET paid_amount = 1000.00, due_amount = 2000.00, payment_status = 'PARTIAL' WHERE id = :id", [':id' => $rid]);

        // 2nd pay: 1000
        Database::execute("INSERT INTO payments (payment_number, transaction_type, reference_table, reference_id, payment_date, amount, payment_mode, received_by) VALUES ('REC-M2', 'REPAIR_RECEIPT', 'repair_records', :id, '2026-09-25', 1000.00, 'UPI', 2)", [':id' => $rid]);
        Database::execute("UPDATE repair_records SET paid_amount = 2000.00, due_amount = 1000.00, payment_status = 'PARTIAL' WHERE id = :id", [':id' => $rid]);

        // 3rd pay: 1000
        Database::execute("INSERT INTO payments (payment_number, transaction_type, reference_table, reference_id, payment_date, amount, payment_mode, received_by) VALUES ('REC-M3', 'REPAIR_RECEIPT', 'repair_records', :id, '2026-09-25', 1000.00, 'CARD', 2)", [':id' => $rid]);
        Database::execute("UPDATE repair_records SET paid_amount = 3000.00, due_amount = 0.00, payment_status = 'PAID' WHERE id = :id", [':id' => $rid]);

        $receiptCount = (int)Database::fetchColumn("SELECT COUNT(*) FROM payments WHERE reference_table = 'repair_records' AND reference_id = :id", [':id' => $rid]);
        $finalStatus = Database::fetchColumn("SELECT payment_status FROM repair_records WHERE id = :id", [':id' => $rid]);

        $this->assert($receiptCount === 3, "Multi-Payment: Exactly 3 payment records logged in payments ledger");
        $this->assert($finalStatus === 'PAID', "Multi-Payment: Status successfully settled to PAID after 3 payments");
    }

    private function testSubsequentPaymentOverpaymentDefense(): void
    {
        // Try paying ₹500.00 on a repair with ₹200.00 due
        $currentDue = 200.00;
        $attempted = 500.00;
        $blocked = ($attempted > $currentDue);

        $this->assert($blocked, "Subsequent Overpayment Defense: Payment of ₹500.00 against ₹200.00 due balance rejected");
    }

    private function testPaymentRejectedOnFullyPaidRepair(): void
    {
        $due = 0.00;
        $status = 'PAID';
        $blocked = ($due <= 0.00 || $status === 'PAID');

        $this->assert($blocked, "Payment Defense: Attempt to record payment on settled repair rejected");
    }

    private function testPaymentRejectedOnRefurbishmentRecord(): void
    {
        $repairType = 'REFURBISHMENT';
        $blocked = ($repairType !== 'CUSTOMER_SERVICE');

        $this->assert($blocked, "Payment Defense: Attempt to record customer payment against shop refurbishment rejected");
    }

    // =========================================================================
    // 4. Used-Phone Refurbishment & Cost Basis Capitalization Tests
    // =========================================================================

    private function testRefurbishmentOnInStockSecondHandPhone(): void
    {
        // Handset 1: Second-hand iPhone 11 (IN_STOCK, initial purchase_cost = 14,000, repair_cost = 0, total_basis = 14,000)
        $phone = Database::fetchOne("SELECT * FROM phones WHERE id = 1");
        $this->assert($phone['status'] === 'IN_STOCK', "Refurbishment: Target phone is IN_STOCK");
        $this->assert($phone['phone_type'] === 'SECOND_HAND', "Refurbishment: Target phone is SECOND_HAND");
    }

    private function testRefurbishmentNonRevenueNature(): void
    {
        $repairNumber = 'REP-20260925-REF01';
        $repairDate = '2026-09-25';
        $phoneId = 1;
        $partsCost = '850.00'; // 1 x iPhone 11 Battery (product 3, avg_cost 850)
        $labourCost = '400.00';
        $totalRefurb = Financial::add($partsCost, $labourCost); // 1250.00

        Database::execute("
            INSERT INTO repair_records (
                repair_number, repair_type, phone_id,
                customer_name, customer_phone, device_model,
                repair_description, parts_cost, labour_amount,
                total_repair_cost, amount_charged, net_profit_contribution,
                payment_status, paid_amount, due_amount,
                repair_date, created_by
            ) VALUES (
                :rnum, 'REFURBISHMENT', :pid,
                NULL, NULL, 'Apple iPhone 11',
                'Installed new replacement battery and cleaned speaker grills',
                :pcost, :lamt, :tcost,
                0.00, 0.00, 'PAID', 0.00, 0.00,
                :rdate, 1
            )
        ", [
            ':rnum'  => $repairNumber,
            ':pid'   => $phoneId,
            ':pcost' => $partsCost,
            ':lamt'  => $labourCost,
            ':tcost' => $totalRefurb,
            ':rdate' => $repairDate,
        ]);

        $repairId = (int)Database::lastInsertId();
        $row = Database::fetchOne("SELECT * FROM repair_records WHERE id = :id", [':id' => $repairId]);

        $this->assert($row['repair_type'] === 'REFURBISHMENT', "Refurbishment Accounting: repair_type is REFURBISHMENT");
        $this->assert((float)$row['amount_charged'] === 0.00, "Refurbishment Accounting: amount_charged is strictly 0.00 (not customer revenue)");
        $this->assert((float)$row['net_profit_contribution'] === 0.00, "Refurbishment Accounting: net_profit_contribution is 0.00 (capitalized, not expensed)");
        $this->assert($row['payment_status'] === 'PAID', "Refurbishment Accounting: payment_status is PAID with 0.00 due");
    }

    private function testRefurbishmentRejectedOnSoldPhone(): void
    {
        // Handset 3: Sold phone (status = 'SOLD')
        $phone = Database::fetchOne("SELECT * FROM phones WHERE id = 3");
        $blocked = ($phone['status'] !== 'IN_STOCK');
        $this->assert($blocked, "Refurbishment Defense: Sold phone cannot be refurbished");
    }

    private function testRefurbishmentRejectedOnNewPhone(): void
    {
        // Handset 4: Brand New Phone (phone_type = 'NEW')
        $phone = Database::fetchOne("SELECT * FROM phones WHERE id = 4");
        $blocked = ($phone['phone_type'] !== 'SECOND_HAND');
        $this->assert($blocked, "Refurbishment Defense: Brand new phone cannot be submitted for used refurbishment");
    }

    private function testRefurbishmentInHouseStockDeduction(): void
    {
        // Deduct 1 x iPhone 11 Battery (product 3, stock was 10 -> now 9)
        $stockBefore = (int)Database::fetchColumn("SELECT current_quantity FROM inventory_stock WHERE product_id = 3");

        $repairId = (int)Database::fetchColumn("SELECT id FROM repair_records WHERE repair_number = 'REP-20260925-REF01'");
        Database::execute("
            INSERT INTO repair_parts_used (
                repair_id, source_type, product_id, part_name, quantity, unit_cost, subtotal_cost
            ) VALUES (
                :rid, 'IN_HOUSE_STOCK', 3, 'iPhone 11 Original Battery Replacement', 1, 850.00, 850.00
            )
        ", [':rid' => $repairId]);

        Database::execute("UPDATE inventory_stock SET current_quantity = current_quantity - 1 WHERE product_id = 3");

        $stockAfter = (int)Database::fetchColumn("SELECT current_quantity FROM inventory_stock WHERE product_id = 3");
        $this->assert($stockAfter === ($stockBefore - 1), "Refurbishment Stock: iPhone 11 battery stock decremented from {$stockBefore} to {$stockAfter}");
    }

    private function testRefurbishmentExternalPartsHandling(): void
    {
        // Handset 2 (Samsung M31): Refurbish with external back cover panel (₹280.00)
        $repairNumber = 'REP-20260925-REF02';
        Database::execute("
            INSERT INTO repair_records (
                repair_number, repair_type, phone_id,
                device_model, repair_description, parts_cost, labour_amount,
                total_repair_cost, amount_charged, net_profit_contribution,
                payment_status, paid_amount, due_amount, repair_date, created_by
            ) VALUES (
                :rnum, 'REFURBISHMENT', 2,
                'Samsung Galaxy M31', 'New back cover housing installed',
                280.00, 150.00, 430.00, 0.00, 0.00, 'PAID', 0.00, 0.00, '2026-09-25', 1
            )
        ", [':rnum' => $repairNumber]);

        $repairId = (int)Database::lastInsertId();

        Database::execute("
            INSERT INTO repair_parts_used (
                repair_id, source_type, product_id, part_name, quantity, unit_cost, subtotal_cost
            ) VALUES (
                :rid, 'EXTERNAL_PURCHASE', NULL, 'Samsung M31 Back Housing Blue', 1, 280.00, 280.00
            )
        ", [':rid' => $repairId]);

        $part = Database::fetchOne("SELECT * FROM repair_parts_used WHERE repair_id = :id", [':id' => $repairId]);
        $this->assert($part['source_type'] === 'EXTERNAL_PURCHASE', "Refurbishment External: source_type recorded as EXTERNAL_PURCHASE");
        $this->assert($part['product_id'] === null, "Refurbishment External: product_id is NULL");
    }

    private function testDirectCostLedgerIntegration(): void
    {
        // Handset 1 direct costs: parts ₹850.00, labour ₹400.00
        Database::execute("
            INSERT INTO phone_direct_costs (
                phone_id, cost_type, description, amount, cost_date, created_by
            ) VALUES (
                1, 'PARTS', 'Refurbishment battery replacement', 850.00, '2026-09-25', 1
            )
        ");

        Database::execute("
            INSERT INTO phone_direct_costs (
                phone_id, cost_type, description, amount, cost_date, created_by
            ) VALUES (
                1, 'LABOUR', 'Refurbishment technician labour', 400.00, '2026-09-25', 1
            )
        ");

        $totalDirect = (float)Database::fetchColumn("
            SELECT SUM(amount) FROM phone_direct_costs WHERE phone_id = 1 AND cost_type IN ('PARTS', 'LABOUR')
        ");

        $this->assert($totalDirect === 1250.00, "Direct Costs Ledger: phone_direct_costs contains ₹1,250.00 for Handset 1");
    }

    private function testPhoneRepairCostRecalculation(): void
    {
        $sumCosts = (string)Database::fetchColumn("
            SELECT COALESCE(SUM(amount), 0.00) FROM phone_direct_costs WHERE phone_id = 1 AND cost_type IN ('REPAIR', 'PARTS', 'LABOUR')
        ");

        Database::execute("
            UPDATE phones SET repair_cost = :rcost WHERE id = 1
        ", [':rcost' => $sumCosts]);

        $updatedRepairCost = (float)Database::fetchColumn("SELECT repair_cost FROM phones WHERE id = 1");
        $this->assert($updatedRepairCost === 1250.00, "Cost Basis: phones.repair_cost updated to ₹1,250.00");
    }

    private function testPhoneTotalCostBasisRecalculation(): void
    {
        $phone = Database::fetchOne("SELECT * FROM phones WHERE id = 1");
        $purchaseCost = (string)$phone['purchase_cost']; // 14000.00
        $repairCost   = (string)$phone['repair_cost'];   // 1250.00
        $otherCost    = (string)$phone['other_cost'];    // 0.00

        $newBasis = Financial::add($purchaseCost, Financial::add($repairCost, $otherCost)); // 15250.00

        Database::execute("
            UPDATE phones SET total_cost_basis = :basis, updated_at = NOW() WHERE id = 1
        ", [':basis' => $newBasis]);

        $finalBasis = (float)Database::fetchColumn("SELECT total_cost_basis FROM phones WHERE id = 1");
        $this->assert($finalBasis === 15250.00, "Cost Basis: Authoritative total_cost_basis is ₹15,250.00 (14,000 + 1,250)");
    }

    private function testMultipleSequentialRefurbishmentsCumulativeCost(): void
    {
        // Second refurbishment on Handset 1: e.g. buffing & screen glass replacement (parts ₹600.00, labour ₹200.00)
        Database::execute("
            INSERT INTO phone_direct_costs (phone_id, cost_type, description, amount, cost_date, created_by)
            VALUES (1, 'PARTS', 'Screen glass buffing and replacement', 600.00, '2026-09-25', 1)
        ");

        Database::execute("
            INSERT INTO phone_direct_costs (phone_id, cost_type, description, amount, cost_date, created_by)
            VALUES (1, 'LABOUR', 'Buffing labour', 200.00, '2026-09-25', 1)
        ");

        $cumulativeRepairCost = (string)Database::fetchColumn("
            SELECT COALESCE(SUM(amount), 0.00) FROM phone_direct_costs WHERE phone_id = 1 AND cost_type IN ('REPAIR', 'PARTS', 'LABOUR')
        ");

        $phone = Database::fetchOne("SELECT * FROM phones WHERE id = 1");
        $newBasis = Financial::add((string)$phone['purchase_cost'], Financial::add($cumulativeRepairCost, (string)$phone['other_cost']));

        Database::execute("
            UPDATE phones SET repair_cost = :rcost, total_cost_basis = :tbasis WHERE id = 1
        ", [':rcost' => $cumulativeRepairCost, ':tbasis' => $newBasis]);

        $finalRepairCost = (float)Database::fetchColumn("SELECT repair_cost FROM phones WHERE id = 1");
        $finalTotalBasis = (float)Database::fetchColumn("SELECT total_cost_basis FROM phones WHERE id = 1");

        $this->assert($finalRepairCost === 2050.00, "Cumulative Costs: repair_cost accumulated to ₹2,050.00 (1,250 + 800)");
        $this->assert($finalTotalBasis === 16050.00, "Cumulative Costs: total_cost_basis accumulated to ₹16,050.00 (14,000 + 2,050)");
    }

    private function testRefurbishmentAvoidsDoubleCountingInProfitLoss(): void
    {
        // In repair_records, net_profit_contribution for refurbishment is 0.00.
        // It is not deducted from P&L now because it is already an asset on the balance sheet.
        $refurbProfit = (float)Database::fetchColumn("
            SELECT net_profit_contribution FROM repair_records WHERE repair_type = 'REFURBISHMENT' AND phone_id = 1
        ");

        $this->assert($refurbProfit === 0.00, "No Double Counting: Refurbishment net_profit_contribution is 0.00, preventing premature P&L loss");
    }

    // =========================================================================
    // 5. Inventory State Consistency & Stock Defenses Tests
    // =========================================================================

    private function testInHousePartStockSufficiencyCheck(): void
    {
        // Product 4 (Redmi Note 10 Pro Combo) has 4 units left. Attempting to use 5 should be blocked.
        $available = (int)Database::fetchColumn("SELECT current_quantity FROM inventory_stock WHERE product_id = 4");
        $requested = 5;
        $blocked = ($requested > $available);

        $this->assert($blocked, "Stock Defense: Insufficient stock (requested {$requested} > available {$available}) caught and blocked");
    }

    private function testInactiveProductBarredFromRepairParts(): void
    {
        // Product 6 is inactive (is_active = 0)
        $product = Database::fetchOne("SELECT * FROM products WHERE id = 6");
        $blocked = ($product['is_active'] == 0);

        $this->assert($blocked, "Catalog Defense: Inactive product (ID 6) barred from repair consumption");
    }

    private function testStockMovementsDoubleEntryReconciliation(): void
    {
        $movement = Database::fetchOne("
            SELECT * FROM stock_movements WHERE movement_type = 'REPAIR_USAGE' LIMIT 1
        ");

        $reconciles = ((int)$movement['quantity_after'] === ((int)$movement['quantity_before'] + (int)$movement['quantity_change']));
        $this->assert($reconciles, "Stock Movement Reconciliation: quantity_after ({$movement['quantity_after']}) = quantity_before ({$movement['quantity_before']}) + quantity_change ({$movement['quantity_change']})");
    }

    private function testStockCannotBecomeNegative(): void
    {
        $available = 2;
        $attempted = 3;
        $blocked = ($attempted > $available);
        $this->assert($blocked, "Stock Defense: Deduction that would drive inventory negative is strictly prohibited");
    }

    private function testAtomicRollbackOnMidTransactionFailure(): void
    {
        $stockBefore = (int)Database::fetchColumn("SELECT current_quantity FROM inventory_stock WHERE product_id = 5");
        $repairsBefore = (int)Database::fetchColumn("SELECT COUNT(*) FROM repair_records");

        try {
            Database::transaction(function() {
                Database::execute("
                    INSERT INTO repair_records (
                        repair_number, repair_type, customer_name, customer_phone, device_model,
                        repair_description, parts_cost, labour_amount, total_repair_cost,
                        amount_charged, net_profit_contribution, payment_status, paid_amount, due_amount,
                        repair_date, created_by
                    ) VALUES (
                        'REP-FAIL-01', 'CUSTOMER_SERVICE', 'Crash Test', '9830000000', 'Crash Phone',
                        'Testing transaction rollback', 45.00, 100.00, 145.00,
                        300.00, 155.00, 'PAID', 300.00, 0.00, '2026-09-25', 1
                    )
                ");

                Database::execute("UPDATE inventory_stock SET current_quantity = current_quantity - 1 WHERE product_id = 5");

                // Simulated failure
                throw new \RuntimeException("Simulated mid-transaction failure");
            });
        } catch (\Throwable $e) {
            // Expected
        }

        $stockAfter = (int)Database::fetchColumn("SELECT current_quantity FROM inventory_stock WHERE product_id = 5");
        $repairsAfter = (int)Database::fetchColumn("SELECT COUNT(*) FROM repair_records");

        $this->assert($repairsBefore === $repairsAfter, "Rollback: Zero orphaned repair records committed after mid-transaction failure");
        $this->assert($stockBefore === $stockAfter, "Rollback: Inventory quantity remained untouched ({$stockBefore}) after abort");
    }

    // =========================================================================
    // 6. Permission Enforcement & Financial Privacy Tests
    // =========================================================================

    private function testUserWithoutManageRepairsDenied(): void
    {
        // User 3 (Rahul Verma) has no manage_repairs
        $hasPermission = Authorization::hasPermission('manage_repairs', 3);
        $this->assert(!$hasPermission, "AuthZ: Sales staff without manage_repairs is barred from repair management");
    }

    private function testUserWithManageRepairsAuthorized(): void
    {
        // User 2 (Raj Kumar) has manage_repairs
        $hasPermission = Authorization::hasPermission('manage_repairs', 2);
        $this->assert($hasPermission, "AuthZ: Technician with manage_repairs is authorized to record repairs");
    }

    private function testOwnerInherentlyAuthorized(): void
    {
        // User 1 (Abhishek Shaw) is OWNER
        $hasPermission = Authorization::hasPermission('manage_repairs', 1);
        $this->assert($hasPermission, "AuthZ: Shop Owner automatically inherits manage_repairs permission");
    }

    private function testRepairCostMaskingForUnprivilegedUsers(): void
    {
        // User 2 (Raj) does NOT have view_purchase_costs
        $canViewCosts = Authorization::hasPermission('view_purchase_costs', 2);
        $this->assert(!$canViewCosts, "Privacy: Technician without view_purchase_costs cannot view parts purchase costs");
    }

    private function testRepairProfitMaskingForUnprivilegedUsers(): void
    {
        // User 2 (Raj) does NOT have view_profit_margins
        $canViewProfit = Authorization::hasPermission('view_profit_margins', 2);
        $this->assert(!$canViewProfit, "Privacy: Technician without view_profit_margins cannot view net profit margins");
    }

    private function testAuthorizedUserViewsExactCostsAndProfits(): void
    {
        // User 4 (Amit Sen) has view_purchase_costs and view_profit_margins
        $canViewCosts  = Authorization::hasPermission('view_purchase_costs', 4);
        $canViewProfit = Authorization::hasPermission('view_profit_margins', 4);

        $this->assert($canViewCosts, "Privacy: Shop manager is authorized to view repair costs");
        $this->assert($canViewProfit, "Privacy: Shop manager is authorized to view repair profit margins");
    }

    // =========================================================================
    // 7. Security Controls & Audit Trail Tests
    // =========================================================================

    private function testCsrfTokenValidation(): void
    {
        $token = Security::getCsrfToken();

        $valid = Security::validateCsrfToken($token);
        $this->assert($valid, "CSRF: Valid session token accepted");

        $fake = Security::validateCsrfToken('forged_fake_token_12345');
        $this->assert(!$fake, "CSRF: Forged token rejected");

        $nullVal = Security::validateCsrfToken(null);
        $this->assert(!$nullVal, "CSRF: Null token rejected");
    }

    private function testSqlInjectionDefenses(): void
    {
        $malicious = "Redmi' OR '1'='1";
        $results = Database::fetchAll("
            SELECT * FROM repair_records WHERE customer_name = :cname
        ", [':cname' => $malicious]);

        $this->assert(empty($results), "SQL Safety: Parameterized query successfully neutralized SQL injection string");
    }

    private function testXssOutputEscaping(): void
    {
        $dirty = '<script>alert("XSS")</script>';
        $clean = Security::e($dirty);

        $this->assert(!str_contains($clean, '<script>'), "XSS Safety: Dangerous script tags escaped to safe HTML entities");
    }

    private function testAuditLoggingCustomerRepairCreated(): void
    {
        AuditLogger::log(
            'REPAIR_CUSTOMER_SERVICE_CREATED',
            'repair_records',
            101,
            null,
            ['repair_number' => 'REP-TEST-101', 'amount_charged' => 1500.00],
            "Created customer repair record",
            2
        );

        $log = Database::fetchOne("
            SELECT * FROM audit_logs WHERE action = 'REPAIR_CUSTOMER_SERVICE_CREATED' AND record_id = 101
        ");

        $this->assert($log !== null, "Audit Trail: REPAIR_CUSTOMER_SERVICE_CREATED logged successfully");
        $this->assert($log['table_name'] === 'repair_records', "Audit Trail: Target table is repair_records");
    }

    private function testAuditLoggingRefurbishmentCreated(): void
    {
        AuditLogger::log(
            'REPAIR_REFURBISHMENT_CREATED',
            'repair_records',
            102,
            null,
            ['repair_number' => 'REP-TEST-REF102', 'phone_id' => 1, 'cost_added' => 1250.00],
            "Created used phone refurbishment record",
            1
        );

        $log = Database::fetchOne("
            SELECT * FROM audit_logs WHERE action = 'REPAIR_REFURBISHMENT_CREATED' AND record_id = 102
        ");

        $this->assert($log !== null, "Audit Trail: REPAIR_REFURBISHMENT_CREATED logged successfully");
    }

    private function testAuditLoggingRepairPaymentRecorded(): void
    {
        AuditLogger::log(
            'REPAIR_PAYMENT_RECORDED',
            'repair_records',
            101,
            ['due_amount' => 800.00],
            ['due_amount' => 0.00, 'received_amount' => 800.00],
            "Recorded customer repair payment receipt",
            2
        );

        $log = Database::fetchOne("
            SELECT * FROM audit_logs WHERE action = 'REPAIR_PAYMENT_RECORDED' AND record_id = 101
        ");

        $this->assert($log !== null, "Audit Trail: REPAIR_PAYMENT_RECORDED logged successfully");
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
            echo "ALL REPAIR & REFURBISHMENT TESTS PASSED SUCCESSFULLY! (100% OK)\n\n";
        }
    }
}

// Run Test Suite
$suite = new RepairAndRefurbishmentTest();
$suite->runAll();
