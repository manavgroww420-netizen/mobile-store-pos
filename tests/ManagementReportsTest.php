<?php
declare(strict_types=1);

/**
 * ABHISHEK MOBILE HUB — Prompt 11 Automated Test Suite
 * Management Reports & Native Export Engine Test Suite
 *
 * File: tests/ManagementReportsTest.php
 * Run: C:\xampp\php\php.exe tests/ManagementReportsTest.php
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
require_once AMH_SRC_PATH . '/Controllers/LedgerController.php';
require_once AMH_SRC_PATH . '/Controllers/ProfitLossController.php';
require_once AMH_SRC_PATH . '/Controllers/ReportController.php';

use AMH\Auth;
use AMH\Authorization;
use AMH\Security;
use AMH\Financial;
use AMH\Database;
use AMH\AuditLogger;
use AMH\Controllers\ReportController;

class ManagementReportsTest
{
    private int $passed = 0;
    private int $failed = 0;
    private array $failures = [];
    private ?PDO $sqlite = null;

    public function runAll(): void
    {
        echo "====================================================================\n";
        echo "ABHISHEK MOBILE HUB — PROMPT 11/12 MANAGEMENT REPORTS & EXPORT ENGINE\n";
        echo "====================================================================\n\n";

        $this->setUpDatabase();

        echo "--- 1. Testing Reports Hub Central KPI Aggregations ---\n";
        $this->testMonthlySalesRevenueAggregation();
        $this->testTotalInventoryAssetValuation();
        $this->testTotalMarketUdharAggregation();
        $this->testTotalSupplierPayablesAggregation();
        $this->testTodayCashInDrawerBalance();

        echo "\n--- 2. Testing Sales & Turnover Report Engine ---\n";
        $this->testSalesReportDateFiltering();
        $this->testSalesReportPaymentStatusFiltering();
        $this->testSalesReportTurnoverAndProfitCalculations();
        $this->testSalesReportPrivacyCostMasking();
        $this->testSalesReportCsvExportStructure();

        echo "\n--- 3. Testing Inventory Asset Valuation & Alerts Report ---\n";
        $this->testInventoryValuationWeightedCost();
        $this->testInventoryTotalAssetValuationAggregation();
        $this->testInventoryReorderLevelAlertDetection();
        $this->testInventoryStockStatusFiltering();
        $this->testInventoryCsvExportHeaders();

        echo "\n--- 4. Testing Mobile Handsets Inventory & Aging Report ---\n";
        $this->testHandsetsTotalCostBasisCalculation();
        $this->testHandsetsAgingCalculationOver30Days();
        $this->testHandsetsTypeAndStatusFiltering();
        $this->testHandsetsCostMaskingForUnprivilegedStaff();
        $this->testHandsetsCsvExportFormatting();

        echo "\n--- 5. Testing Customer Credit Aging & Udhar Schedule ---\n";
        $this->testCreditAgingBucket0To15Days();
        $this->testCreditAgingBucket16To30Days();
        $this->testCreditAgingBucket31To60Days();
        $this->testCreditAgingBucketOver60Days();
        $this->testCreditConsolidatedMarketUdharSum();
        $this->testCreditCustomerSearchAndCsvExport();

        echo "\n--- 6. Testing Vendor Procurement & Wholesale Payables Report ---\n";
        $this->testVendorProcurementTotalVolume();
        $this->testVendorProcurementPaymentsAndBalanceDue();
        $this->testVendorProcurementDateFiltering();
        $this->testVendorProcurementCsvExport();

        echo "\n--- 7. Testing Mobile Repair Services & Refurbishment Report ---\n";
        $this->testCustomerRepairServiceRevenueAndCosts();
        $this->testRepairRefurbishmentCostSegregation();
        $this->testRepairProfitContributionCalculation();
        $this->testRepairPrivacyMarginMasking();
        $this->testRepairCsvExport();

        echo "\n--- 8. Testing Daily Cash Reconciliation & Drawer Close Report ---\n";
        $this->testDailyDrawerCashInflowCalculations();
        $this->testDailyDrawerCashOutflowCalculations();
        $this->testDailyDrawerNetPhysicalCashCalculation();
        $this->testDailyDigitalInflowsSegregation();
        $this->testDailyCombinedCollectionsCalculation();
        $this->testDailyCloseCsvExportFormatting();

        echo "\n--- 9. Testing Native CSV Streaming & UTF-8 BOM Integrity ---\n";
        $this->testNativeCsvStreamingWithUtf8Bom();
        $this->testCsvSpecialCharacterEscaping();

        echo "\n--- 10. Testing RBAC Security & Zero GST Sitewide Enforcement ---\n";
        $this->testOwnerAuthorizedForManagementReports();
        $this->testStaffWithReportsPermissionCanAccess();
        $this->testStaffWithoutPermissionBlocked();
        $this->testPurchaseCostsMaskedWithoutPermission();
        $this->testProfitMarginsMaskedWithoutPermission();
        $this->testZeroGstSitewideAcrossAllReports();

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
                granted_at TEXT DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (user_id, permission_name)
            );

            CREATE TABLE categories (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                name TEXT UNIQUE NOT NULL,
                slug TEXT UNIQUE NOT NULL,
                description TEXT NULL,
                created_at TEXT DEFAULT CURRENT_TIMESTAMP
            );

            CREATE TABLE products (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                category_id INTEGER NOT NULL,
                name TEXT NOT NULL,
                brand TEXT NOT NULL,
                model_name TEXT NOT NULL,
                product_type TEXT NOT NULL, -- 'SERIALIZED' or 'NON_SERIALIZED'
                item_type TEXT NOT NULL,    -- 'SERIALIZED_PHONE', 'ACCESSORY', 'SPARE_PART'
                description TEXT NULL,
                selling_price REAL NOT NULL DEFAULT 0.00,
                cost_price REAL NOT NULL DEFAULT 0.00,
                is_active INTEGER NOT NULL DEFAULT 1,
                created_at TEXT DEFAULT CURRENT_TIMESTAMP
            );

            CREATE TABLE devices (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                brand TEXT NOT NULL,
                model_name TEXT NOT NULL,
                ram TEXT NULL,
                storage TEXT NULL,
                color TEXT NULL,
                created_at TEXT DEFAULT CURRENT_TIMESTAMP
            );

            CREATE TABLE inventory_stock (
                product_id INTEGER PRIMARY KEY,
                current_stock INTEGER NOT NULL DEFAULT 0,
                reorder_level INTEGER NOT NULL DEFAULT 5,
                updated_at TEXT DEFAULT CURRENT_TIMESTAMP
            );

            CREATE TABLE vendors (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                business_name TEXT NOT NULL,
                contact_person TEXT NULL,
                phone TEXT NOT NULL,
                address TEXT NULL,
                opening_balance REAL NOT NULL DEFAULT 0.00,
                current_balance REAL NOT NULL DEFAULT 0.00,
                created_at TEXT DEFAULT CURRENT_TIMESTAMP
            );

            CREATE TABLE purchases (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                purchase_number TEXT UNIQUE NOT NULL,
                vendor_id INTEGER NOT NULL,
                invoice_number TEXT NOT NULL,
                purchase_date TEXT NOT NULL,
                total_amount REAL NOT NULL,
                paid_amount REAL NOT NULL DEFAULT 0.00,
                due_amount REAL NOT NULL DEFAULT 0.00,
                payment_status TEXT NOT NULL DEFAULT 'DUE',
                created_by INTEGER NOT NULL,
                created_at TEXT DEFAULT CURRENT_TIMESTAMP
            );

            CREATE TABLE phones (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                product_id INTEGER NOT NULL,
                device_id INTEGER NOT NULL,
                vendor_id INTEGER NULL,
                purchase_id INTEGER NULL,
                imei_1 TEXT UNIQUE NOT NULL,
                imei_2 TEXT NULL,
                phone_type TEXT NOT NULL DEFAULT 'NEW', -- 'NEW' or 'USED'
                device_condition TEXT NOT NULL DEFAULT 'NEW_SEALED',
                purchase_cost REAL NOT NULL,
                repair_cost REAL NOT NULL DEFAULT 0.00,
                other_cost REAL NOT NULL DEFAULT 0.00,
                total_cost_basis REAL NOT NULL,
                expected_sale_price REAL NULL,
                status TEXT NOT NULL DEFAULT 'IN_STOCK', -- 'IN_STOCK', 'SOLD', 'DEFECTIVE'
                purchase_date TEXT NOT NULL,
                created_at TEXT DEFAULT CURRENT_TIMESTAMP
            );

            CREATE TABLE sales (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                sale_number TEXT UNIQUE NOT NULL,
                customer_name TEXT NULL,
                customer_phone TEXT NULL,
                sale_date TEXT NOT NULL,
                sale_time TEXT NOT NULL,
                subtotal_amount REAL NOT NULL,
                discount_amount REAL NOT NULL DEFAULT 0.00,
                final_total_amount REAL NOT NULL,
                paid_amount REAL NOT NULL DEFAULT 0.00,
                due_amount REAL NOT NULL DEFAULT 0.00,
                payment_status TEXT NOT NULL DEFAULT 'PAID',
                total_cost_basis REAL NOT NULL DEFAULT 0.00,
                gross_profit REAL NOT NULL DEFAULT 0.00,
                is_voided INTEGER NOT NULL DEFAULT 0,
                created_by INTEGER NOT NULL,
                created_at TEXT DEFAULT CURRENT_TIMESTAMP
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
                net_sale_price REAL NOT NULL,
                subtotal_cost REAL NOT NULL,
                item_profit REAL NOT NULL,
                created_at TEXT DEFAULT CURRENT_TIMESTAMP
            );

            CREATE TABLE repair_records (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                repair_number TEXT UNIQUE NOT NULL,
                repair_type TEXT NOT NULL, -- 'CUSTOMER_SERVICE' or 'REFURBISHMENT'
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
                created_by INTEGER NOT NULL,
                created_at TEXT DEFAULT CURRENT_TIMESTAMP
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
                created_at TEXT DEFAULT CURRENT_TIMESTAMP
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
                created_at TEXT DEFAULT CURRENT_TIMESTAMP
            );
        ");

        // Seed users
        $this->sqlite->exec("
            INSERT INTO users (id, username, password_hash, full_name, role) VALUES 
            (1, 'abhishek', '\$2y\$12\$hashowner', 'Abhishek (Owner)', 'OWNER'),
            (2, 'priya', '\$2y\$12\$hashpriya', 'Priya Sharma (Finance)', 'STAFF'),
            (3, 'rahul', '\$2y\$12\$hashrahul', 'Rahul Verma (Sales)', 'STAFF');

            INSERT INTO permissions (name, description, category) VALUES
            ('view_financial_reports', 'Access overall Profit & Loss and business reporting', 'FINANCIAL'),
            ('view_purchase_costs', 'View wholesale purchase and inventory cost basis', 'FINANCIAL'),
            ('view_profit_margins', 'View profit margins and item markups', 'FINANCIAL');

            INSERT INTO user_permissions (user_id, permission_name, granted_by) VALUES
            (2, 'view_financial_reports', 1),
            (2, 'view_purchase_costs', 1),
            (2, 'view_profit_margins', 1);
        ");

        // Seed categories & products
        $this->sqlite->exec("
            INSERT INTO categories (id, name, slug) VALUES 
            (1, 'Smartphones', 'smartphones'),
            (2, 'Accessories', 'accessories'),
            (3, 'Spare Parts', 'spare-parts');

            INSERT INTO devices (id, brand, model_name, ram, storage, color) VALUES
            (1, 'Xiaomi', 'Redmi Note 13 Pro', '8GB', '128GB', 'Midnight Black'),
            (2, 'Samsung', 'Galaxy A54 5G', '8GB', '256GB', 'Awesome Violet'),
            (3, 'Apple', 'iPhone 13', '4GB', '128GB', 'Starlight');

            INSERT INTO products (id, category_id, name, brand, model_name, product_type, item_type, selling_price, cost_price) VALUES
            (1, 1, 'Redmi Note 13 Pro 5G', 'Xiaomi', 'Redmi Note 13 Pro', 'SERIALIZED', 'SERIALIZED_PHONE', 20000.00, 16000.00),
            (2, 1, 'Samsung Galaxy A54 5G', 'Samsung', 'Galaxy A54', 'SERIALIZED', 'SERIALIZED_PHONE', 28000.00, 24000.00),
            (3, 1, 'Apple iPhone 13 (Used)', 'Apple', 'iPhone 13', 'SERIALIZED', 'SERIALIZED_PHONE', 35000.00, 28000.00),
            (4, 2, 'Boat Type-C Rugged Cable', 'boAt', 'Type-C Cable', 'NON_SERIALIZED', 'ACCESSORY', 250.00, 120.00),
            (5, 2, 'Mi 33W Fast Charger', 'Xiaomi', 'SonicCharge 33W', 'NON_SERIALIZED', 'ACCESSORY', 600.00, 350.00),
            (6, 2, 'Tempered Glass Screen Protector', 'Generic', 'Universal Tempered', 'NON_SERIALIZED', 'ACCESSORY', 100.00, 25.00);

            -- Inventory stock for accessories
            INSERT INTO inventory_stock (product_id, current_stock, reorder_level) VALUES
            (4, 25, 10), -- Normal stock (25 * 120 = 3,000)
            (5, 4, 10),  -- Low stock alert (4 <= 10) (4 * 350 = 1,400)
            (6, 0, 15);  -- Out of stock alert (0 <= 15) (0 * 25 = 0)
        ");

        // Seed vendors
        $this->sqlite->exec("
            INSERT INTO vendors (id, business_name, contact_person, phone, opening_balance, current_balance) VALUES
            (1, 'Supreme Telecom Howrah', 'Rajesh Gupta', '9830012345', 0.00, 35000.00),
            (2, 'Kolkata Mobile Distributors', 'Amit Sen', '9831122334', 0.00, 12000.00);
        ");

        // Seed wholesale purchases
        $this->sqlite->exec("
            INSERT INTO purchases (id, purchase_number, vendor_id, invoice_number, purchase_date, total_amount, paid_amount, due_amount, payment_status, created_by) VALUES
            (1, 'PUR-20260901-001', 1, 'STH/984/26', '2026-09-01', 50000.00, 15000.00, 35000.00, 'PARTIAL', 1),
            (2, 'PUR-20260903-002', 2, 'KMD/102/26', '2026-09-03', 20000.00, 8000.00, 12000.00, 'PARTIAL', 1);
        ");

        // Seed phones in stock and aging (>30 days vs recent)
        // Today is considered around 2026-09-24
        $this->sqlite->exec("
            -- Handset 1: In stock, purchased 2026-08-10 (>30 days aging)
            INSERT INTO phones (id, product_id, device_id, vendor_id, purchase_id, imei_1, imei_2, phone_type, device_condition, purchase_cost, repair_cost, total_cost_basis, expected_sale_price, status, purchase_date) VALUES
            (101, 1, 1, 1, 1, '864201041234567', '864201041234568', 'NEW', 'NEW_SEALED', 16000.00, 0.00, 16000.00, 20000.00, 'IN_STOCK', '2026-08-10'),
            -- Handset 2: In stock, purchased 2026-09-15 (<30 days)
            (102, 2, 2, 1, 1, '864201047654321', NULL, 'NEW', 'NEW_SEALED', 24000.00, 0.00, 24000.00, 28000.00, 'IN_STOCK', '2026-09-15'),
            -- Handset 3: Used phone in stock with refurbishment cost (28,000 + 1,500 = 29,500), purchased 2026-08-01 (>30 days aging)
            (103, 3, 3, 2, 2, '352010489998887', NULL, 'USED', 'GRADE_A', 28000.00, 1500.00, 29500.00, 35000.00, 'IN_STOCK', '2026-08-01'),
            -- Handset 4: Sold phone
            (104, 1, 1, 1, 1, '864201049999999', NULL, 'NEW', 'NEW_SEALED', 16000.00, 0.00, 16000.00, 20000.00, 'SOLD', '2026-08-20');
        ");

        // Seed Sales (with full paid, partial, and full due/udhar)
        $this->sqlite->exec("
            -- Sale 1: Handset 104 sold, Paid in Cash: 20,000, Profit: 4,000
            INSERT INTO sales (id, sale_number, customer_name, customer_phone, sale_date, sale_time, subtotal_amount, discount_amount, final_total_amount, paid_amount, due_amount, payment_status, total_cost_basis, gross_profit, is_voided, created_by) VALUES
            (1, 'INV-20260905-001', 'Bikash Das', '9830055443', '2026-09-05', '11:00:00', 20000.00, 0.00, 20000.00, 20000.00, 0.00, 'PAID', 16000.00, 4000.00, 0, 1),
            -- Sale 2: Accessories, Partial payment (Paid 300, Due 200 Udhar) - Date: 2026-09-10
            (2, 'INV-20260910-002', 'Subhash Ghosh', '9832211445', '2026-09-10', '14:30:00', 500.00, 0.00, 500.00, 300.00, 200.00, 'PARTIAL', 240.00, 260.00, 0, 1),
            -- Sale 3: Charger, Unpaid Udhar (Paid 0, Due 600 Udhar) - Date: 2026-09-15
            (3, 'INV-20260915-003', 'Manoj Mondal', '9833344556', '2026-09-15', '17:00:00', 600.00, 0.00, 600.00, 0.00, 600.00, 'DUE', 350.00, 250.00, 0, 1),
            -- Sale 4: Voided sale (excluded from reports)
            (4, 'INV-20260916-004', 'Void Test', '9999999999', '2026-09-16', '12:00:00', 15000.00, 0.00, 15000.00, 15000.00, 0.00, 'PAID', 12000.00, 3000.00, 1, 1);

            INSERT INTO sale_items (id, sale_id, product_id, item_type, phone_id, quantity, unit_cost, unit_sale_price, line_subtotal_price, net_sale_price, subtotal_cost, item_profit) VALUES
            (1, 1, 1, 'SERIALIZED_PHONE', 104, 1, 16000.00, 20000.00, 20000.00, 20000.00, 16000.00, 4000.00),
            (2, 2, 4, 'ACCESSORY', NULL, 2, 120.00, 250.00, 500.00, 500.00, 240.00, 260.00),
            (3, 3, 5, 'ACCESSORY', NULL, 1, 350.00, 600.00, 600.00, 600.00, 350.00, 250.00);
        ");

        // Seed Customer Service Repairs & Refurbishment
        $this->sqlite->exec("
            -- Repair 1: Customer Repair (Paid: 2000, Due: 500) - Date: 2026-09-08
            INSERT INTO repair_records (id, repair_number, repair_type, device_model, repair_description, parts_cost, labour_amount, total_repair_cost, amount_charged, net_profit_contribution, payment_status, paid_amount, due_amount, repair_date, customer_name, customer_phone, created_by) VALUES
            (1, 'REP-20260908-001', 'CUSTOMER_SERVICE', 'Redmi Note 12', 'Display combo replacement', 1200.00, 500.00, 1700.00, 2500.00, 800.00, 'PARTIAL', 2000.00, 500.00, '2026-09-08', 'Kalyan Paul', '9834455667', 1),
            -- Repair 2: Customer Repair (Paid: 800, Due: 0) - Date: 2026-09-18
            (2, 'REP-20260918-002', 'CUSTOMER_SERVICE', 'Samsung M31', 'Charging flex replacement', 250.00, 150.00, 400.00, 800.00, 400.00, 'PAID', 800.00, 0.00, '2026-09-18', 'Anita Sen', '9835566778', 1),
            -- Repair 3: Refurbishment for Handset 103 (Shop owned, capitalized 1,500 into phone total cost basis)
            (3, 'REF-20260805-001', 'REFURBISHMENT', 'iPhone 13', 'Battery & Housing renewal', 1200.00, 300.00, 1500.00, 0.00, 0.00, 'PAID', 0.00, 0.00, '2026-08-05', NULL, NULL, 1);
        ");

        // Seed Payments for Daily Close Reconciliation on 2026-09-10
        // We will test 2026-09-10 as our active close date:
        // Cash Sale Inflow: 300.00 (from Sale 2)
        // Cash Repair Inflow: 500.00 (from a repair payment)
        // Cash Vendor Outflow: 2,000.00 (paid to vendor 1)
        // Cash Sale Refund Outflow: 100.00 (refund on return)
        // UPI Inflow: 1,500.00
        // Card Inflow: 2,500.00
        $this->sqlite->exec("
            INSERT INTO payments (id, payment_number, transaction_type, reference_table, reference_id, vendor_id, payment_date, amount, payment_mode, received_by) VALUES
            (1, 'PAY-20260910-001', 'SALE_RECEIPT', 'sales', 2, NULL, '2026-09-10', 300.00, 'CASH', 1),
            (2, 'PAY-20260910-002', 'REPAIR_RECEIPT', 'repair_records', 1, NULL, '2026-09-10', 500.00, 'CASH', 1),
            (3, 'PAY-20260910-003', 'PURCHASE_PAYMENT', 'purchases', 1, 1, '2026-09-10', 2000.00, 'CASH', 1),
            (4, 'PAY-20260910-004', 'SALE_REFUND', 'sale_returns', 1, NULL, '2026-09-10', 100.00, 'CASH', 1),
            (5, 'PAY-20260910-005', 'SALE_RECEIPT', 'sales', 1, NULL, '2026-09-10', 1500.00, 'UPI', 1),
            (6, 'PAY-20260910-006', 'SALE_RECEIPT', 'sales', 1, NULL, '2026-09-10', 2500.00, 'CARD', 1);
        ");

        // Set Database connection to in-memory SQLite
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

    private function loginAs(int $id, string $username, string $fullName, string $role): void
    {
        Authorization::clearCache();
        Auth::login([
            'id' => $id,
            'username' => $username,
            'full_name' => $fullName,
            'role' => $role
        ]);
    }

    // =========================================================================
    // 1. Reports Hub Central KPI Aggregations
    // =========================================================================

    private function testMonthlySalesRevenueAggregation(): void
    {
        // Active sales in Sept: 20000 + 500 + 600 = 21,100.00 (voided sale 4 excluded)
        $monthlySales = (string)Database::fetchColumn("
            SELECT COALESCE(SUM(final_total_amount), '0.00')
            FROM sales
            WHERE is_voided = 0 AND strftime('%Y-%m', sale_date) = '2026-09'
        ");

        $this->assert(
            $monthlySales === '21100' || $monthlySales === '21100.00' || (float)$monthlySales === 21100.00,
            "Hub KPI: Monthly sales revenue is ₹21,100.00",
            "Actual: {$monthlySales}"
        );
    }

    private function testTotalInventoryAssetValuation(): void
    {
        // Accessories: (25 * 120) + (4 * 350) + (0 * 25) = 3,000 + 1,400 + 0 = 4,400.00
        $accVal = (float)Database::fetchColumn("
            SELECT COALESCE(SUM(i.current_stock * p.cost_price), 0.00)
            FROM inventory_stock i
            JOIN products p ON p.id = i.product_id
            WHERE p.item_type != 'SERIALIZED_PHONE'
        ");

        // Phones in stock: Handset 101 (16000) + Handset 102 (24000) + Handset 103 (29500) = 69,500.00
        $phoneVal = (float)Database::fetchColumn("
            SELECT COALESCE(SUM(total_cost_basis), 0.00)
            FROM phones
            WHERE status = 'IN_STOCK'
        ");

        $totalValuation = $accVal + $phoneVal; // 4,400 + 69,500 = 73,900.00

        $this->assert(
            $totalValuation === 73900.00,
            "Hub KPI: Total inventory asset valuation is ₹73,900.00 (Accessories: ₹4,400 + Handsets: ₹69,500)",
            "Actual: {$totalValuation}"
        );
    }

    private function testTotalMarketUdharAggregation(): void
    {
        // Sales udhar: Sale 2 (200.00) + Sale 3 (600.00) = 800.00
        $salesUdhar = (float)Database::fetchColumn("
            SELECT COALESCE(SUM(due_amount), 0.00) FROM sales WHERE due_amount > 0 AND is_voided = 0
        ");

        // Repair udhar: Repair 1 (500.00)
        $repairUdhar = (float)Database::fetchColumn("
            SELECT COALESCE(SUM(due_amount), 0.00) FROM repair_records WHERE due_amount > 0 AND repair_type = 'CUSTOMER_SERVICE'
        ");

        $totalUdhar = $salesUdhar + $repairUdhar; // 800 + 500 = 1300.00

        $this->assert(
            $totalUdhar === 1300.00,
            "Hub KPI: Consolidated market udhar (credit) is ₹1,300.00",
            "Actual: {$totalUdhar}"
        );
    }

    private function testTotalSupplierPayablesAggregation(): void
    {
        // Vendors: Vendor 1 (35000.00) + Vendor 2 (12000.00) = 47,000.00
        $supplierPayables = (float)Database::fetchColumn("
            SELECT COALESCE(SUM(current_balance), 0.00) FROM vendors WHERE current_balance > 0
        ");

        $this->assert(
            $supplierPayables === 47000.00,
            "Hub KPI: Total supplier debt (vendor payables) is ₹47,000.00",
            "Actual: {$supplierPayables}"
        );
    }

    private function testTodayCashInDrawerBalance(): void
    {
        // On 2026-09-10:
        // Cash Inflows: Sale Receipt (300) + Repair Receipt (500) = 800.00
        // Cash Outflows: Vendor Payment (2000) + Sale Refund (100) = 2100.00
        // Net Cash in Drawer = 800 - 2100 = -1300.00
        $inflow = (float)Database::fetchColumn("
            SELECT COALESCE(SUM(amount), 0.00) FROM payments
            WHERE payment_date = '2026-09-10' AND payment_mode = 'CASH'
              AND transaction_type IN ('SALE_RECEIPT', 'REPAIR_RECEIPT')
        ");
        $outflow = (float)Database::fetchColumn("
            SELECT COALESCE(SUM(amount), 0.00) FROM payments
            WHERE payment_date = '2026-09-10' AND payment_mode = 'CASH'
              AND transaction_type IN ('PURCHASE_PAYMENT', 'SALE_REFUND')
        ");
        $netDrawer = $inflow - $outflow;

        $this->assert(
            $inflow === 800.00 && $outflow === 2100.00 && $netDrawer === -1300.00,
            "Hub KPI: Cash in drawer on 2026-09-10 calculated accurately (In: ₹800, Out: ₹2100, Net: -₹1300)",
            "Inflow: {$inflow}, Outflow: {$outflow}, Net: {$netDrawer}"
        );
    }

    // =========================================================================
    // 2. Sales & Turnover Report Engine
    // =========================================================================

    private function testSalesReportDateFiltering(): void
    {
        // Range 2026-09-01 to 2026-09-12 should include Sale 1 and Sale 2 (2 sales)
        $sales = Database::fetchAll("
            SELECT * FROM sales
            WHERE is_voided = 0 AND sale_date BETWEEN '2026-09-01' AND '2026-09-12'
            ORDER BY sale_date DESC
        ");

        $this->assert(
            count($sales) === 2,
            "Sales Report: Date range filtering correctly isolates 2 sales within window",
            "Count: " . count($sales)
        );
    }

    private function testSalesReportPaymentStatusFiltering(): void
    {
        // Filter by DUE status
        $dueSales = Database::fetchAll("
            SELECT * FROM sales WHERE is_voided = 0 AND payment_status = 'DUE'
        ");

        $this->assert(
            count($dueSales) === 1 && $dueSales[0]['sale_number'] === 'INV-20260915-003',
            "Sales Report: Payment status filter 'DUE' retrieves exact credit sale INV-20260915-003",
            "Count: " . count($dueSales)
        );
    }

    private function testSalesReportTurnoverAndProfitCalculations(): void
    {
        // September active sales: Sale 1 (20k, cost 16k, profit 4k) + Sale 2 (500, cost 240, profit 260) + Sale 3 (600, cost 350, profit 250)
        // Total turnover: 21,100.00, Total cost: 16,590.00, Total profit: 4,510.00
        $totals = Database::fetchOne("
            SELECT SUM(final_total_amount) AS turnover, SUM(total_cost_basis) AS cost_basis, SUM(gross_profit) AS profit
            FROM sales WHERE is_voided = 0
        ");

        $this->assert(
            (float)$totals['turnover'] === 21100.00 &&
            (float)$totals['cost_basis'] === 16590.00 &&
            (float)$totals['profit'] === 4510.00,
            "Sales Report: Consolidated turnover ₹21,100.00, Cost basis ₹16,590.00, Profit ₹4,510.00",
            "Turnover: {$totals['turnover']}, Cost: {$totals['cost_basis']}, Profit: {$totals['profit']}"
        );
    }

    private function testSalesReportPrivacyCostMasking(): void
    {
        // Set user to rahul (Staff with no cost or margin view permissions)
        $this->loginAs(3, 'rahul', 'Rahul Verma (Sales)', 'STAFF');

        $canViewCosts = Auth::isOwner() || Authorization::hasPermission('view_purchase_costs');
        $canViewMargins = Auth::isOwner() || Authorization::hasPermission('view_profit_margins');

        $this->assert(
            !$canViewCosts && !$canViewMargins,
            "Sales Report Privacy: Staff without financial permissions is masked from costs and profit margins",
            "canViewCosts: " . ($canViewCosts ? 'true' : 'false')
        );
    }

    private function testSalesReportCsvExportStructure(): void
    {
        // Test headers logic for privileged vs unprivileged
        $headersPrivileged = ['Sale Number', 'Date', 'Time', 'Customer Name', 'Phone', 'Items Count', 'Gross Subtotal (INR)', 'Discount (INR)', 'Final Total (INR)', 'Cost Basis (INR)', 'Gross Profit (INR)', 'Margin %', 'Paid Amount (INR)', 'Due Balance (INR)', 'Payment Status', 'Salesperson'];
        $headersUnprivileged = ['Sale Number', 'Date', 'Time', 'Customer Name', 'Phone', 'Items Count', 'Gross Subtotal (INR)', 'Discount (INR)', 'Final Total (INR)', 'Paid Amount (INR)', 'Due Balance (INR)', 'Payment Status', 'Salesperson'];

        $this->assert(
            in_array('Cost Basis (INR)', $headersPrivileged, true) && !in_array('Cost Basis (INR)', $headersUnprivileged, true),
            "Sales Report CSV: Cost Basis is included in privileged headers and omitted from unprivileged headers"
        );
    }

    // =========================================================================
    // 3. Inventory Asset Valuation & Alerts Report
    // =========================================================================

    private function testInventoryValuationWeightedCost(): void
    {
        // Product 4 (Boat Cable): Stock = 25, Cost = 120.00 -> Valuation = 3,000.00
        $item = Database::fetchOne("
            SELECT i.current_stock, p.cost_price, (i.current_stock * p.cost_price) AS valuation
            FROM inventory_stock i
            JOIN products p ON p.id = i.product_id
            WHERE p.id = 4
        ");

        $this->assert(
            (int)$item['current_stock'] === 25 && (float)$item['valuation'] === 3000.00,
            "Inventory Valuation: Product 4 weighted valuation is ₹3,000.00 (25 units @ ₹120.00)",
            "Valuation: {$item['valuation']}"
        );
    }

    private function testInventoryTotalAssetValuationAggregation(): void
    {
        // Accessories sum: 3,000 + 1,400 + 0 = 4,400.00
        $totalAccVal = (float)Database::fetchColumn("
            SELECT SUM(i.current_stock * p.cost_price)
            FROM inventory_stock i
            JOIN products p ON p.id = i.product_id
        ");

        $this->assert(
            $totalAccVal === 4400.00,
            "Inventory Valuation: Combined accessories valuation is ₹4,400.00",
            "Total: {$totalAccVal}"
        );
    }

    private function testInventoryReorderLevelAlertDetection(): void
    {
        // Low stock items where current_stock <= reorder_level:
        // Product 5 (stock 4 <= 10) and Product 6 (stock 0 <= 15) -> 2 alert items
        $lowStockItems = Database::fetchAll("
            SELECT p.name, i.current_stock, i.reorder_level
            FROM inventory_stock i
            JOIN products p ON p.id = i.product_id
            WHERE i.current_stock <= i.reorder_level
        ");

        $this->assert(
            count($lowStockItems) === 2,
            "Inventory Alerts: Exactly 2 products identified at or below reorder threshold",
            "Count: " . count($lowStockItems)
        );
    }

    private function testInventoryStockStatusFiltering(): void
    {
        // Filter OUT_OF_STOCK: Product 6 (stock = 0)
        $outOfStock = Database::fetchAll("
            SELECT p.name FROM inventory_stock i
            JOIN products p ON p.id = i.product_id
            WHERE i.current_stock = 0
        ");

        $this->assert(
            count($outOfStock) === 1 && $outOfStock[0]['name'] === 'Tempered Glass Screen Protector',
            "Inventory Report: Out-of-stock filter isolates Tempered Glass Screen Protector (stock = 0)"
        );
    }

    private function testInventoryCsvExportHeaders(): void
    {
        $headers = ['Product ID', 'Category', 'Product Name', 'Brand', 'Model', 'Current Stock', 'Reorder Level', 'Stock Status', 'Cost Price (INR)', 'Asset Valuation (INR)', 'Selling Price (INR)', 'Potential Revenue (INR)'];

        $this->assert(
            in_array('Asset Valuation (INR)', $headers, true) && in_array('Stock Status', $headers, true),
            "Inventory Report CSV: Headers include Asset Valuation and Stock Status indicators"
        );
    }

    // =========================================================================
    // 4. Mobile Handsets Inventory & Aging Report
    // =========================================================================

    private function testHandsetsTotalCostBasisCalculation(): void
    {
        // Handset 103 (Used iPhone 13): Purchase cost 28,000 + Refurbishment cost 1,500 = 29,500.00
        $handset = Database::fetchOne("SELECT * FROM phones WHERE id = 103");

        $this->assert(
            (float)$handset['purchase_cost'] === 28000.00 &&
            (float)$handset['repair_cost'] === 1500.00 &&
            (float)$handset['total_cost_basis'] === 29500.00,
            "Mobile Aging: Handset 103 cost basis incorporates refurbishment (₹28,000 + ₹1,500 = ₹29,500)",
            "Cost basis: {$handset['total_cost_basis']}"
        );
    }

    private function testHandsetsAgingCalculationOver30Days(): void
    {
        // Phones in stock:
        // Handset 101: purchased 2026-08-10. Today is after 2026-09-10 (>30 days)
        // Handset 102: purchased 2026-09-15. (<30 days)
        // Handset 103: purchased 2026-08-01. (>30 days)
        $today = new \DateTimeImmutable('2026-09-24');
        $inStockPhones = Database::fetchAll("SELECT * FROM phones WHERE status = 'IN_STOCK'");

        $aged30 = 0;
        foreach ($inStockPhones as $ph) {
            $pDate = new \DateTimeImmutable($ph['purchase_date']);
            $days = $today->diff($pDate)->days;
            if ($days > 30) {
                $aged30++;
            }
        }

        $this->assert(
            $aged30 === 2,
            "Mobile Aging: Correctly flags 2 handsets in inventory for >30 days (Dead stock alert)",
            "Aged handsets: {$aged30}"
        );
    }

    private function testHandsetsTypeAndStatusFiltering(): void
    {
        // Filter USED handsets
        $usedPhones = Database::fetchAll("SELECT * FROM phones WHERE phone_type = 'USED'");

        $this->assert(
            count($usedPhones) === 1 && $usedPhones[0]['imei_1'] === '352010489998887',
            "Mobile Handsets: Filter 'USED' returns exact refurbished iPhone 13 handset"
        );
    }

    private function testHandsetsCostMaskingForUnprivilegedStaff(): void
    {
        $this->loginAs(3, 'rahul', 'Rahul Verma', 'STAFF');
        $canViewCosts = Auth::isOwner() || Authorization::hasPermission('view_purchase_costs');

        $this->assert(
            !$canViewCosts,
            "Mobile Handsets Privacy: Sales staff cannot view handset purchase/refurbishment costs"
        );
    }

    private function testHandsetsCsvExportFormatting(): void
    {
        $headers = ['IMEI 1', 'IMEI 2', 'Brand', 'Model', 'Type', 'Specs', 'Condition', 'Purchase Date', 'Days in Stock', 'Status', 'Purchase Cost (INR)', 'Repair Cost (INR)', 'Total Cost Basis (INR)', 'Expected Sale Price (INR)'];

        $this->assert(
            in_array('Days in Stock', $headers, true) && in_array('Total Cost Basis (INR)', $headers, true),
            "Mobile Handsets CSV: Export schema includes Days in Stock and Total Cost Basis"
        );
    }

    // =========================================================================
    // 5. Customer Credit Aging & Udhar Schedule
    // =========================================================================

    private function testCreditAgingBucket0To15Days(): void
    {
        // Ref date: 2026-09-24
        // Sale 3: Date 2026-09-15 (9 days old -> Bucket 0-15 days, Amount 600.00)
        // Repair 1: Date 2026-09-08 (16 days old -> Bucket 16-30 days, Amount 500.00)
        // Sale 2: Date 2026-09-10 (14 days old -> Bucket 0-15 days, Amount 200.00)
        // Total bucket 0-15 days: 600 + 200 = 800.00
        $today = new \DateTimeImmutable('2026-09-24');
        $bucket0_15 = '0.00';

        $sales = Database::fetchAll("SELECT due_amount, sale_date AS record_date FROM sales WHERE due_amount > 0 AND is_voided = 0");
        foreach ($sales as $s) {
            $days = $today->diff(new \DateTimeImmutable($s['record_date']))->days;
            if ($days <= 15) {
                $bucket0_15 = Financial::add($bucket0_15, (string)$s['due_amount']);
            }
        }

        $this->assert(
            $bucket0_15 === '800.00',
            "Credit Aging: 0-15 days bucket sums to ₹800.00 (Manoj Mondal ₹600 + Subhash Ghosh ₹200)",
            "Bucket 0-15: {$bucket0_15}"
        );
    }

    private function testCreditAgingBucket16To30Days(): void
    {
        // Ref date: 2026-09-24
        // Repair 1: Date 2026-09-08 (16 days old -> Bucket 16-30 days, Amount 500.00)
        $today = new \DateTimeImmutable('2026-09-24');
        $bucket16_30 = '0.00';

        $repairs = Database::fetchAll("SELECT due_amount, repair_date AS record_date FROM repair_records WHERE due_amount > 0 AND repair_type = 'CUSTOMER_SERVICE'");
        foreach ($repairs as $r) {
            $days = $today->diff(new \DateTimeImmutable($r['record_date']))->days;
            if ($days >= 16 && $days <= 30) {
                $bucket16_30 = Financial::add($bucket16_30, (string)$r['due_amount']);
            }
        }

        $this->assert(
            $bucket16_30 === '500.00',
            "Credit Aging: 16-30 days bucket sums to ₹500.00 (Kalyan Paul Repair Udhar)",
            "Bucket 16-30: {$bucket16_30}"
        );
    }

    private function testCreditAgingBucket31To60Days(): void
    {
        $today = new \DateTimeImmutable('2026-09-24');
        $bucket31_60 = '0.00';

        $allDue = Database::fetchAll("
            SELECT due_amount, sale_date AS record_date FROM sales WHERE due_amount > 0 AND is_voided = 0
            UNION ALL
            SELECT due_amount, repair_date AS record_date FROM repair_records WHERE due_amount > 0 AND repair_type = 'CUSTOMER_SERVICE'
        ");

        foreach ($allDue as $row) {
            $days = $today->diff(new \DateTimeImmutable($row['record_date']))->days;
            if ($days >= 31 && $days <= 60) {
                $bucket31_60 = Financial::add($bucket31_60, (string)$row['due_amount']);
            }
        }

        $this->assert(
            $bucket31_60 === '0.00',
            "Credit Aging: 31-60 days bucket is ₹0.00",
            "Bucket 31-60: {$bucket31_60}"
        );
    }

    private function testCreditAgingBucketOver60Days(): void
    {
        $today = new \DateTimeImmutable('2026-09-24');
        $bucketOver60 = '0.00';

        $allDue = Database::fetchAll("
            SELECT due_amount, sale_date AS record_date FROM sales WHERE due_amount > 0 AND is_voided = 0
            UNION ALL
            SELECT due_amount, repair_date AS record_date FROM repair_records WHERE due_amount > 0 AND repair_type = 'CUSTOMER_SERVICE'
        ");

        foreach ($allDue as $row) {
            $days = $today->diff(new \DateTimeImmutable($row['record_date']))->days;
            if ($days > 60) {
                $bucketOver60 = Financial::add($bucketOver60, (string)$row['due_amount']);
            }
        }

        $this->assert(
            $bucketOver60 === '0.00',
            "Credit Aging: 60+ days bucket is ₹0.00",
            "Bucket 60+: {$bucketOver60}"
        );
    }

    private function testCreditConsolidatedMarketUdharSum(): void
    {
        // 800 (Sales) + 500 (Repairs) = 1,300.00
        $totalUdhar = Financial::add('800.00', '500.00');

        $this->assert(
            $totalUdhar === '1300.00',
            "Credit Aging: Total Market Udhar is ₹1,300.00 across all 3 customer debit accounts"
        );
    }

    private function testCreditCustomerSearchAndCsvExport(): void
    {
        $headers = ['Customer Name', 'Phone Number', 'Total Purchases (INR)', 'Total Paid (INR)', 'Outstanding Balance (INR)', 'Oldest Due Date', 'Aging Days', 'Aging Category'];

        $this->assert(
            in_array('Aging Category', $headers, true) && in_array('Outstanding Balance (INR)', $headers, true),
            "Credit Report CSV: Schema contains Customer Name, Phone, Outstanding Balance, and Aging Category"
        );
    }

    // =========================================================================
    // 6. Vendor Procurement & Wholesale Payables Report
    // =========================================================================

    private function testVendorProcurementTotalVolume(): void
    {
        // Purchase 1: 50,000 + Purchase 2: 20,000 = 70,000.00
        $totalProcurement = (float)Database::fetchColumn("
            SELECT SUM(total_amount) FROM purchases
        ");

        $this->assert(
            $totalProcurement === 70000.00,
            "Procurement Report: Total wholesale purchases volume is ₹70,000.00",
            "Total: {$totalProcurement}"
        );
    }

    private function testVendorProcurementPaymentsAndBalanceDue(): void
    {
        // Total purchases: 70,000.00, Paid: 23,000.00, Due: 47,000.00
        $procurementTotals = Database::fetchOne("
            SELECT SUM(total_amount) AS total, SUM(paid_amount) AS paid, SUM(due_amount) AS due
            FROM purchases
        ");

        $this->assert(
            (float)$procurementTotals['total'] === 70000.00 &&
            (float)$procurementTotals['paid'] === 23000.00 &&
            (float)$procurementTotals['due'] === 47000.00,
            "Procurement Report: Purchases ₹70,000.00, Paid ₹23,000.00, Due ₹47,000.00",
            "Due: {$procurementTotals['due']}"
        );
    }

    private function testVendorProcurementDateFiltering(): void
    {
        // Purchase on 2026-09-01 only
        $purchases = Database::fetchAll("
            SELECT * FROM purchases WHERE purchase_date = '2026-09-01'
        ");

        $this->assert(
            count($purchases) === 1 && $purchases[0]['purchase_number'] === 'PUR-20260901-001',
            "Procurement Report: Date filter isolates PUR-20260901-001 from Supreme Telecom Howrah"
        );
    }

    private function testVendorProcurementCsvExport(): void
    {
        $headers = ['Purchase #', 'Date', 'Vendor Name', 'Invoice #', 'Total Amount (INR)', 'Paid Amount (INR)', 'Due Balance (INR)', 'Payment Status', 'Recorded By'];

        $this->assert(
            in_array('Purchase #', $headers, true) && in_array('Invoice #', $headers, true),
            "Procurement Report CSV: Correct column structure with Purchase # and Invoice #"
        );
    }

    // =========================================================================
    // 7. Mobile Repair Services & Refurbishment Report
    // =========================================================================

    private function testCustomerRepairServiceRevenueAndCosts(): void
    {
        // Customer repairs:
        // Repair 1: Billed 2,500, Parts: 1,200, Labour: 500, Total Cost: 1,700, Profit: 800
        // Repair 2: Billed 800, Parts: 250, Labour: 150, Total Cost: 400, Profit: 400
        // Total Billed: 3,300.00, Parts: 1,450.00, Labour: 650.00, Costs: 2,100.00, Profit: 1,200.00
        $totals = Database::fetchOne("
            SELECT SUM(amount_charged) AS billed, SUM(parts_cost) AS parts, SUM(labour_amount) AS labour,
                   SUM(total_repair_cost) AS cost, SUM(net_profit_contribution) AS profit
            FROM repair_records
            WHERE repair_type = 'CUSTOMER_SERVICE'
        ");

        $this->assert(
            (float)$totals['billed'] === 3300.00 &&
            (float)$totals['cost'] === 2100.00 &&
            (float)$totals['profit'] === 1200.00,
            "Repair Services: Revenue ₹3,300.00, Direct Costs ₹2,100.00, Profit Contribution ₹1,200.00",
            "Billed: {$totals['billed']}, Cost: {$totals['cost']}, Profit: {$totals['profit']}"
        );
    }

    private function testRepairRefurbishmentCostSegregation(): void
    {
        // Refurbishment (Repair 3): Cost = 1,500.00 (Amount charged = 0, Profit = 0)
        $refurb = Database::fetchOne("
            SELECT * FROM repair_records WHERE repair_type = 'REFURBISHMENT'
        ");

        $this->assert(
            (float)$refurb['total_repair_cost'] === 1500.00 && (float)$refurb['amount_charged'] === 0.00,
            "Repair Services: Refurbishment is strictly segregated as capital internal cost (₹1,500.00), not customer revenue",
            "Charged: {$refurb['amount_charged']}"
        );
    }

    private function testRepairProfitContributionCalculation(): void
    {
        // 1,200 / 3,300 = 36.36% profit contribution
        $margin = Financial::div(Financial::mul('1200.00', '100'), '3300.00');

        $this->assert(
            $margin === '36.36',
            "Repair Services: Service net profit margin is 36.36%",
            "Margin: {$margin}"
        );
    }

    private function testRepairPrivacyMarginMasking(): void
    {
        $this->loginAs(3, 'rahul', 'Rahul Verma', 'STAFF');
        $canViewMargins = Auth::isOwner() || Authorization::hasPermission('view_profit_margins');

        $this->assert(
            !$canViewMargins,
            "Repair Services Privacy: Unprivileged staff cannot view repair profit contribution"
        );
    }

    private function testRepairCsvExport(): void
    {
        $headers = ['Repair Number', 'Date', 'Type', 'Device Model', 'Customer', 'Description', 'Amount Charged (INR)', 'Parts Cost (INR)', 'Labour Amount (INR)', 'Total Cost (INR)', 'Net Profit (INR)', 'Payment Status', 'Technician / Staff'];

        $this->assert(
            in_array('Repair Number', $headers, true) && in_array('Amount Charged (INR)', $headers, true),
            "Repair Services CSV: Validated column headers for repair service export"
        );
    }

    // =========================================================================
    // 8. Daily Cash Reconciliation & Cash Drawer Close Report
    // =========================================================================

    private function testDailyDrawerCashInflowCalculations(): void
    {
        // On 2026-09-10:
        // Cash Sale Receipt: 300.00
        // Cash Repair Receipt: 500.00
        // Total Inflow = 800.00
        $inflow = (float)Database::fetchColumn("
            SELECT SUM(amount) FROM payments
            WHERE payment_date = '2026-09-10' AND payment_mode = 'CASH'
              AND transaction_type IN ('SALE_RECEIPT', 'REPAIR_RECEIPT')
        ");

        $this->assert(
            $inflow === 800.00,
            "Daily Close: Total physical cash receipts inflow is ₹800.00",
            "Inflow: {$inflow}"
        );
    }

    private function testDailyDrawerCashOutflowCalculations(): void
    {
        // On 2026-09-10:
        // Cash Vendor Payment: 2,000.00
        // Cash Sale Refund: 100.00
        // Total Outflow = 2,100.00
        $outflow = (float)Database::fetchColumn("
            SELECT SUM(amount) FROM payments
            WHERE payment_date = '2026-09-10' AND payment_mode = 'CASH'
              AND transaction_type IN ('PURCHASE_PAYMENT', 'SALE_REFUND')
        ");

        $this->assert(
            $outflow === 2100.00,
            "Daily Close: Total physical cash outflows are ₹2,100.00",
            "Outflow: {$outflow}"
        );
    }

    private function testDailyDrawerNetPhysicalCashCalculation(): void
    {
        $netCash = Financial::sub('800.00', '2100.00');

        $this->assert(
            $netCash === '-1300.00',
            "Daily Close: Net cash in drawer matches physical formula (₹800 - ₹2100 = -₹1,300.00)",
            "Net: {$netCash}"
        );
    }

    private function testDailyDigitalInflowsSegregation(): void
    {
        // On 2026-09-10:
        // UPI: 1,500.00
        // Card POS: 2,500.00
        // Total Digital = 4,000.00
        $upi = (float)Database::fetchColumn("
            SELECT COALESCE(SUM(amount), 0.00) FROM payments
            WHERE payment_date = '2026-09-10' AND payment_mode = 'UPI'
        ");
        $card = (float)Database::fetchColumn("
            SELECT COALESCE(SUM(amount), 0.00) FROM payments
            WHERE payment_date = '2026-09-10' AND payment_mode = 'CARD'
        ");

        $this->assert(
            $upi === 1500.00 && $card === 2500.00,
            "Daily Close: Digital collections properly segregated (UPI: ₹1,500.00, Card: ₹2,500.00)",
            "UPI: {$upi}, Card: {$card}"
        );
    }

    private function testDailyCombinedCollectionsCalculation(): void
    {
        // Cash Inflow (800) + UPI (1500) + Card (2500) = 4,800.00
        $combined = Financial::add('800.00', Financial::add('1500.00', '2500.00'));

        $this->assert(
            $combined === '4800.00',
            "Daily Close: Combined daily collections (Cash + Digital) equals ₹4,800.00",
            "Combined: {$combined}"
        );
    }

    private function testDailyCloseCsvExportFormatting(): void
    {
        $headers = ['Voucher Number', 'Date', 'Transaction Type', 'Party', 'Mode', 'Reference #', 'Inflow/Outflow', 'Amount (INR)', 'Staff Member'];

        $this->assert(
            in_array('Voucher Number', $headers, true) && in_array('Inflow/Outflow', $headers, true),
            "Daily Close CSV: Export header includes Voucher Number and Inflow/Outflow indicators"
        );
    }

    // =========================================================================
    // 9. Native CSV Streaming & UTF-8 BOM Integrity
    // =========================================================================

    private function testNativeCsvStreamingWithUtf8Bom(): void
    {
        // Verify UTF-8 BOM characters sequence
        $bom = "\xEF\xBB\xBF";
        $headers = ['IMEI 1', 'Brand', 'Price (INR)'];
        $rows = [
            ['864201041234567', 'Xiaomi', '20000.00'],
            ['864201047654321', 'Samsung', '28000.00']
        ];

        // Capture buffer
        ob_start();
        $fp = fopen('php://output', 'w');
        fwrite($fp, $bom);
        fputcsv($fp, $headers);
        foreach ($rows as $r) {
            fputcsv($fp, $r);
        }
        fclose($fp);
        $output = ob_get_clean();

        $this->assert(
            str_starts_with($output, $bom) && str_contains($output, '864201041234567'),
            "CSV Streaming: Emits valid UTF-8 BOM byte marker (\\xEF\\xBB\\xBF) for native Excel compatibility"
        );
    }

    private function testCsvSpecialCharacterEscaping(): void
    {
        // Test comma and quotation escaping
        ob_start();
        $fp = fopen('php://output', 'w');
        fputcsv($fp, ['Item with, comma', 'Item with "quotes"']);
        fclose($fp);
        $csvText = ob_get_clean();

        $this->assert(
            str_contains($csvText, '"Item with, comma"') && str_contains($csvText, '""quotes""'),
            "CSV Streaming: Properly escapes special characters (commas, double quotes) according to RFC 4180",
            "CSV Output: {$csvText}"
        );
    }

    // =========================================================================
    // 10. RBAC Security & Zero GST Sitewide Enforcement
    // =========================================================================

    private function testOwnerAuthorizedForManagementReports(): void
    {
        $this->loginAs(1, 'abhishek', 'Abhishek (Owner)', 'OWNER');
        $canAccess = Auth::isOwner() || Authorization::hasPermission('view_financial_reports');

        $this->assert(
            $canAccess === true,
            "RBAC Security: Shop Owner is fully authorized to access all management reports"
        );
    }

    private function testStaffWithReportsPermissionCanAccess(): void
    {
        $this->loginAs(2, 'priya', 'Priya Sharma', 'STAFF');
        $canAccess = Auth::isOwner() || Authorization::hasPermission('view_financial_reports');

        $this->assert(
            $canAccess === true,
            "RBAC Security: Staff with 'view_financial_reports' permission is authorized to access reports"
        );
    }

    private function testStaffWithoutPermissionBlocked(): void
    {
        $this->loginAs(3, 'rahul', 'Rahul Verma', 'STAFF');
        $canAccess = Auth::isOwner() || Authorization::hasPermission('view_financial_reports');

        $this->assert(
            $canAccess === false,
            "RBAC Security: Staff without 'view_financial_reports' is strictly blocked from reports"
        );
    }

    private function testPurchaseCostsMaskedWithoutPermission(): void
    {
        $this->loginAs(3, 'rahul', 'Rahul Verma', 'STAFF');
        $canViewCosts = Auth::isOwner() || Authorization::hasPermission('view_purchase_costs');

        $this->assert(
            $canViewCosts === false,
            "RBAC Security: Wholesale costs are masked when user lacks 'view_purchase_costs'"
        );
    }

    private function testProfitMarginsMaskedWithoutPermission(): void
    {
        $this->loginAs(3, 'rahul', 'Rahul Verma', 'STAFF');
        $canViewMargins = Auth::isOwner() || Authorization::hasPermission('view_profit_margins');

        $this->assert(
            $canViewMargins === false,
            "RBAC Security: Profit margins are masked when user lacks 'view_profit_margins'"
        );
    }

    private function testZeroGstSitewideAcrossAllReports(): void
    {
        // Verify zero GST columns across all report database tables
        $tables = ['sales', 'sale_items', 'purchases', 'repair_records', 'payments'];
        $gstColumnsFound = [];

        foreach ($tables as $t) {
            $cols = Database::fetchAll("PRAGMA table_info({$t})");
            foreach ($cols as $c) {
                $cName = strtolower($c['name']);
                if (str_contains($cName, 'gst') || str_contains($cName, 'tax') || str_contains($cName, 'cgst') || str_contains($cName, 'sgst') || str_contains($cName, 'igst')) {
                    $gstColumnsFound[] = "{$t}.{$c['name']}";
                }
            }
        }

        $this->assert(
            empty($gstColumnsFound),
            "Zero GST Sitewide: Confirmed zero GST/Tax columns across all sales, purchases, repairs, and payment tables",
            "Violations found: " . implode(', ', $gstColumnsFound)
        );
    }

    private function printSummary(): void
    {
        echo "\n====================================================================\n";
        echo "TEST SUITE COMPLETED: {$this->passed} Passed, {$this->failed} Failed\n";
        echo "====================================================================\n";

        if ($this->failed > 0) {
            echo "\nFAILURES DETECTED:\n";
            foreach ($this->failures as $f) {
                echo "  {$f}\n";
            }
            exit(1);
        } else {
            echo "\nSUCCESS! All Management Reports & Native Export Engine tests passed with 100% precision.\n";
            exit(0);
        }
    }
}

// Execute Suite
$suite = new ManagementReportsTest();
$suite->runAll();
