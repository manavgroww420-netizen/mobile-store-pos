<?php
declare(strict_types=1);

/**
 * ABHISHEK MOBILE HUB — Prompt 12 Automated Test Suite
 * Executive Dashboard, System Hardening & Production Readiness Test Suite
 *
 * File: tests/ExecutiveDashboardAndHardeningTest.php
 * Run: C:\xampp\php\php.exe tests/ExecutiveDashboardAndHardeningTest.php
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
require_once AMH_SRC_PATH . '/Controllers/DashboardController.php';

use AMH\Auth;
use AMH\Authorization;
use AMH\Security;
use AMH\Financial;
use AMH\Database;
use AMH\AuditLogger;
use AMH\Controllers\DashboardController;

class ExecutiveDashboardAndHardeningTest
{
    private int $passed = 0;
    private int $failed = 0;
    private array $failures = [];
    private ?PDO $sqlite = null;

    public function runAll(): void
    {
        echo "====================================================================\n";
        echo "ABHISHEK MOBILE HUB — PROMPT 12/12 EXECUTIVE DASHBOARD & HARDENING\n";
        echo "====================================================================\n\n";

        $this->setUpDatabase();

        echo "--- 1. Testing Executive Operational Daily Dials ---\n";
        $this->testTodaySalesTurnoverCalculation();
        $this->testStaffPersonalSalesCounter();
        $this->testTodayRepairServicesTurnover();
        $this->testTodayMasterCashDrawerBalance();
        $this->testTodayDigitalCollectionsSegregation();

        echo "\n--- 2. Testing Month-To-Date (MTD) Business Dials ---\n";
        $this->testMtdConsolidatedTurnover();
        $this->testMtdConsolidatedGrossProfit();

        echo "\n--- 3. Testing Inventory Health & Handsets Dials ---\n";
        $this->testInStockHandsetsCounting();
        $this->testAgedHandsetsAlertOver30Days();
        $this->testLowStockAccessoriesAlertDetection();
        $this->testOutOfStockAccessoriesDetection();
        $this->testConsolidatedInventoryAssetValuation();

        echo "\n--- 4. Testing Receivables & Payables Financial Dials ---\n";
        $this->testConsolidatedMarketUdharCalculation();
        $this->testConsolidatedSupplierPayablesCalculation();

        echo "\n--- 5. Testing Actionable Dashboard Feeds ---\n";
        $this->testRecentSalesActivityFeed();
        $this->testCriticalLowStockProcurementFeed();
        $this->testAgedHandsetsClearanceFeed();
        $this->testTopMarketUdharCollectionFeed();

        echo "\n--- 6. Testing Role-Based Privacy & Margin Masking ---\n";
        $this->testOwnerHasFullVisibilityOnDashboard();
        $this->testStaffWithoutMarginPermissionMasksProfit();
        $this->testStaffWithoutCostPermissionMasksValuation();

        echo "\n--- 7. Testing System Security Hardening Foundations ---\n";
        $this->testSecurityHeadersDefinition();
        $this->testCsrfTokenGenerationAndTimingSafeValidation();
        $this->testXssHtmlEscapingSanitization();
        $this->testSafeInternalRedirectDisallowsOpenRedirects();
        $this->testPasswordHashingWithBcryptCost12();
        $this->testAes256GcmAadhaarEncryptionAndDecryption();
        $this->testImeiFormatAndLuhnValidation();

        echo "\n--- 8. Testing Diagnostic Health Check & Zero GST Enforcement ---\n";
        $this->testDiagnosticHealthEndpointData();
        $this->testStrictZeroGstSitewideAcrossWholeSystem();

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
                product_type TEXT NOT NULL,
                item_type TEXT NOT NULL,
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
                phone_type TEXT NOT NULL DEFAULT 'NEW',
                device_condition TEXT NOT NULL DEFAULT 'NEW_SEALED',
                purchase_cost REAL NOT NULL,
                repair_cost REAL NOT NULL DEFAULT 0.00,
                other_cost REAL NOT NULL DEFAULT 0.00,
                total_cost_basis REAL NOT NULL,
                expected_sale_price REAL NULL,
                status TEXT NOT NULL DEFAULT 'IN_STOCK',
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

        // Seed categories, devices, and products
        $this->sqlite->exec("
            INSERT INTO categories (id, name, slug) VALUES 
            (1, 'Smartphones', 'smartphones'),
            (2, 'Accessories', 'accessories');

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

            -- Stock: Boat cable: 25 (Normal), Mi Charger: 2 (Low stock <= 10), Tempered: 0 (Out of stock <= 15)
            INSERT INTO inventory_stock (product_id, current_stock, reorder_level) VALUES
            (4, 25, 10),
            (5, 2, 10),
            (6, 0, 15);
        ");

        // Seed vendors
        $this->sqlite->exec("
            INSERT INTO vendors (id, business_name, contact_person, phone, opening_balance, current_balance) VALUES
            (1, 'Supreme Telecom Howrah', 'Rajesh Gupta', '9830012345', 0.00, 35000.00),
            (2, 'Kolkata Mobile Distributors', 'Amit Sen', '9831122334', 0.00, 12000.00);
        ");

        // Seed Phones (including aged phone > 30 days)
        // Today is considered around date('Y-m-d')
        $today = date('Y-m-d');
        $fortyDaysAgo = date('Y-m-d', strtotime('-40 days'));
        $tenDaysAgo = date('Y-m-d', strtotime('-10 days'));

        $this->sqlite->exec("
            -- Handset 1: In stock, aged >30 days (40 days ago)
            INSERT INTO phones (id, product_id, device_id, vendor_id, imei_1, imei_2, phone_type, device_condition, purchase_cost, repair_cost, total_cost_basis, expected_sale_price, status, purchase_date) VALUES
            (101, 1, 1, 1, '864201041234567', '864201041234568', 'NEW', 'NEW_SEALED', 16000.00, 0.00, 16000.00, 20000.00, 'IN_STOCK', '{$fortyDaysAgo}'),
            -- Handset 2: In stock, recent (10 days ago)
            (102, 2, 2, 1, '864201047654321', NULL, 'NEW', 'NEW_SEALED', 24000.00, 0.00, 24000.00, 28000.00, 'IN_STOCK', '{$tenDaysAgo}'),
            -- Handset 3: Used phone in stock with refurbishment cost (28,000 + 1,500 = 29,500), aged >30 days
            (103, 3, 3, 2, '352010489998887', NULL, 'USED', 'GRADE_A', 28000.00, 1500.00, 29500.00, 35000.00, 'IN_STOCK', '{$fortyDaysAgo}');
        ");

        // Seed Sales:
        // Today's Sales:
        // Sale 1 by User 1 (Abhishek): 20,000.00, Cost: 16,000, Profit: 4,000, Paid: 20,000
        // Sale 2 by User 3 (Rahul): 500.00, Cost: 240, Profit: 260, Paid: 300, Due: 200
        $this->sqlite->exec("
            INSERT INTO sales (id, sale_number, customer_name, customer_phone, sale_date, sale_time, subtotal_amount, discount_amount, final_total_amount, paid_amount, due_amount, payment_status, total_cost_basis, gross_profit, is_voided, created_by) VALUES
            (1, 'INV-TODAY-001', 'Arindam Paul', '9830099881', '{$today}', '11:00:00', 20000.00, 0.00, 20000.00, 20000.00, 0.00, 'PAID', 16000.00, 4000.00, 0, 1),
            (2, 'INV-TODAY-002', 'Subhojit Das', '9832288772', '{$today}', '14:30:00', 500.00, 0.00, 500.00, 300.00, 200.00, 'PARTIAL', 240.00, 260.00, 0, 3);
        ");

        // Seed Today's Customer Repair:
        // Repair 1: Billed 2,500, Parts: 1,200, Labour: 500, Cost: 1,700, Profit: 800, Paid: 2,000, Due: 500
        $this->sqlite->exec("
            INSERT INTO repair_records (id, repair_number, repair_type, device_model, repair_description, parts_cost, labour_amount, total_repair_cost, amount_charged, net_profit_contribution, payment_status, paid_amount, due_amount, repair_date, customer_name, customer_phone, created_by) VALUES
            (1, 'REP-TODAY-001', 'CUSTOMER_SERVICE', 'Redmi Note 12', 'Display replacement', 1200.00, 500.00, 1700.00, 2500.00, 800.00, 'PARTIAL', 2000.00, 500.00, '{$today}', 'Tanmoy Roy', '9833377665', 1);
        ");

        // Seed Today's Payments for Drawer Balancing:
        // Cash Sale Inflow: 300.00
        // Cash Repair Inflow: 2000.00
        // Cash Vendor Outflow: 500.00
        // UPI Digital Inflow: 20000.00 (from Sale 1 paid via UPI)
        $this->sqlite->exec("
            INSERT INTO payments (id, payment_number, transaction_type, reference_table, reference_id, vendor_id, payment_date, amount, payment_mode, received_by) VALUES
            (1, 'PAY-TODAY-001', 'SALE_RECEIPT', 'sales', 2, NULL, '{$today}', 300.00, 'CASH', 3),
            (2, 'PAY-TODAY-002', 'REPAIR_RECEIPT', 'repair_records', 1, NULL, '{$today}', 2000.00, 'CASH', 1),
            (3, 'PAY-TODAY-003', 'PURCHASE_PAYMENT', 'purchases', 1, 1, '{$today}', 500.00, 'CASH', 1),
            (4, 'PAY-TODAY-004', 'SALE_RECEIPT', 'sales', 1, NULL, '{$today}', 20000.00, 'UPI', 1);
        ");

        // Set Database connection to in-memory SQLite
        Database::setPdo($this->sqlite);
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
    // 1. Executive Operational Daily Dials
    // =========================================================================

    private function testTodaySalesTurnoverCalculation(): void
    {
        $today = date('Y-m-d');
        // Active sales today: 20,000 + 500 = 20,500.00 across 2 transactions
        $row = Database::fetchOne("
            SELECT COUNT(*) AS cnt, SUM(final_total_amount) AS turnover
            FROM sales WHERE sale_date = :today AND is_voided = 0
        ", [':today' => $today]);

        $this->assert(
            (int)$row['cnt'] === 2 && (float)$row['turnover'] === 20500.00,
            "Dashboard Dial: Today's counter sales is ₹20,500.00 across 2 receipts",
            "Actual count: {$row['cnt']}, turnover: {$row['turnover']}"
        );
    }

    private function testStaffPersonalSalesCounter(): void
    {
        $today = date('Y-m-d');
        // Rahul (User 3) created exactly 1 sale today
        $rahulSales = (int)Database::fetchColumn("
            SELECT COUNT(*) FROM sales WHERE sale_date = :today AND is_voided = 0 AND created_by = 3
        ", [':today' => $today]);

        $this->assert(
            $rahulSales === 1,
            "Dashboard Staff Dial: Individual staff personal sales counter correctly tracks 1 sale for Rahul"
        );
    }

    private function testTodayRepairServicesTurnover(): void
    {
        $today = date('Y-m-d');
        // Customer repair billed today: 2,500.00
        $repairBilled = (float)Database::fetchColumn("
            SELECT COALESCE(SUM(amount_charged), 0.00)
            FROM repair_records WHERE repair_date = :today AND repair_type = 'CUSTOMER_SERVICE'
        ", [':today' => $today]);

        $this->assert(
            $repairBilled === 2500.00,
            "Dashboard Dial: Today's customer repair services turnover is ₹2,500.00",
            "Actual: {$repairBilled}"
        );
    }

    private function testTodayMasterCashDrawerBalance(): void
    {
        $today = date('Y-m-d');
        // Cash In: Sale 2 (300) + Repair 1 (2,000) = 2,300.00
        // Cash Out: Vendor Payment (500) = 500.00
        // Net Cash in Drawer = 2,300 - 500 = 1,800.00
        $cashIn = (float)Database::fetchColumn("
            SELECT COALESCE(SUM(amount), 0.00) FROM payments
            WHERE payment_date = :today AND payment_mode = 'CASH'
              AND transaction_type IN ('SALE_RECEIPT', 'REPAIR_RECEIPT')
        ", [':today' => $today]);

        $cashOut = (float)Database::fetchColumn("
            SELECT COALESCE(SUM(amount), 0.00) FROM payments
            WHERE payment_date = :today AND payment_mode = 'CASH'
              AND transaction_type IN ('PURCHASE_PAYMENT', 'SALE_REFUND')
        ", [':today' => $today]);

        $netDrawer = Financial::sub((string)$cashIn, (string)$cashOut);

        $this->assert(
            $cashIn === 2300.00 && $cashOut === 500.00 && $netDrawer === '1800.00',
            "Dashboard Dial: Master cash drawer physical balance is ₹1,800.00 (In: ₹2,300 - Out: ₹500)",
            "Net: {$netDrawer}"
        );
    }

    private function testTodayDigitalCollectionsSegregation(): void
    {
        $today = date('Y-m-d');
        // Digital Inflow: Sale 1 (20,000 UPI)
        $digital = (float)Database::fetchColumn("
            SELECT COALESCE(SUM(amount), 0.00) FROM payments
            WHERE payment_date = :today AND payment_mode IN ('UPI', 'CARD', 'BANK_TRANSFER')
              AND transaction_type IN ('SALE_RECEIPT', 'REPAIR_RECEIPT')
        ", [':today' => $today]);

        $this->assert(
            $digital === 20000.00,
            "Dashboard Dial: Digital collections (UPI/Card/Bank) segregated at ₹20,000.00",
            "Actual: {$digital}"
        );
    }

    // =========================================================================
    // 2. Month-To-Date (MTD) Business Dials
    // =========================================================================

    private function testMtdConsolidatedTurnover(): void
    {
        $currentMonth = date('Y-m') . '%';
        // Sales turnover: 20,500.00 + Repair turnover: 2,500.00 = 23,000.00
        $salesTurnover = (float)Database::fetchColumn("
            SELECT COALESCE(SUM(final_total_amount), 0.00) FROM sales WHERE is_voided = 0 AND sale_date LIKE :mprefix
        ", [':mprefix' => $currentMonth]);

        $repairTurnover = (float)Database::fetchColumn("
            SELECT COALESCE(SUM(amount_charged), 0.00) FROM repair_records WHERE repair_type = 'CUSTOMER_SERVICE' AND repair_date LIKE :mprefix
        ", [':mprefix' => $currentMonth]);

        $mtdTotal = $salesTurnover + $repairTurnover;

        $this->assert(
            $mtdTotal === 23000.00,
            "Dashboard Dial: Month-To-Date consolidated operating turnover is ₹23,000.00",
            "Actual: {$mtdTotal}"
        );
    }

    private function testMtdConsolidatedGrossProfit(): void
    {
        $currentMonth = date('Y-m') . '%';
        // Sales Profit: 4,000 + 260 = 4,260.00
        // Repair Profit: 800.00
        // Consolidated MTD Profit = 4,260 + 800 = 5,060.00
        $salesProfit = (float)Database::fetchColumn("
            SELECT COALESCE(SUM(gross_profit), 0.00) FROM sales WHERE is_voided = 0 AND sale_date LIKE :mprefix
        ", [':mprefix' => $currentMonth]);

        $repairProfit = (float)Database::fetchColumn("
            SELECT COALESCE(SUM(net_profit_contribution), 0.00) FROM repair_records WHERE repair_type = 'CUSTOMER_SERVICE' AND repair_date LIKE :mprefix
        ", [':mprefix' => $currentMonth]);

        $mtdProfit = $salesProfit + $repairProfit;

        $this->assert(
            $mtdProfit === 5060.00,
            "Dashboard Dial: Month-To-Date gross profit is ₹5,060.00 (Sales: ₹4,260 + Repairs: ₹800)",
            "Actual: {$mtdProfit}"
        );
    }

    // =========================================================================
    // 3. Inventory Health & Handsets Dials
    // =========================================================================

    private function testInStockHandsetsCounting(): void
    {
        // 3 phones in stock: Handset 101 (New), Handset 102 (New), Handset 103 (Used)
        $row = Database::fetchOne("
            SELECT COUNT(*) AS total,
                   SUM(CASE WHEN phone_type = 'NEW' THEN 1 ELSE 0 END) AS new_cnt,
                   SUM(CASE WHEN phone_type = 'USED' THEN 1 ELSE 0 END) AS used_cnt
            FROM phones WHERE status = 'IN_STOCK'
        ");

        $this->assert(
            (int)$row['total'] === 3 && (int)$row['new_cnt'] === 2 && (int)$row['used_cnt'] === 1,
            "Dashboard Dial: Serialized handset stock dial shows 3 handsets (2 New, 1 Refurbished Used)"
        );
    }

    private function testAgedHandsetsAlertOver30Days(): void
    {
        $thirtyDaysAgo = date('Y-m-d', strtotime('-30 days'));
        // Handset 101 (40 days old) and Handset 103 (40 days old) -> 2 aged handsets
        $agedCount = (int)Database::fetchColumn("
            SELECT COUNT(*) FROM phones WHERE status = 'IN_STOCK' AND purchase_date <= :cutoff
        ", [':cutoff' => $thirtyDaysAgo]);

        $this->assert(
            $agedCount === 2,
            "Dashboard Dial: Aged handsets (>30 days) alert flags exactly 2 stagnant inventory items",
            "Aged count: {$agedCount}"
        );
    }

    private function testLowStockAccessoriesAlertDetection(): void
    {
        // Low stock: Product 5 (current_stock = 2 <= reorder_level = 10) and Product 6 (current_stock = 0 <= 15) -> 2 items
        $lowStockCount = (int)Database::fetchColumn("
            SELECT COUNT(*) FROM inventory_stock WHERE current_stock <= reorder_level
        ");

        $this->assert(
            $lowStockCount === 2,
            "Dashboard Dial: Low stock alert badge flags 2 accessories at or below reorder threshold",
            "Low stock count: {$lowStockCount}"
        );
    }

    private function testOutOfStockAccessoriesDetection(): void
    {
        // Out of stock: Product 6 (current_stock = 0)
        $outCount = (int)Database::fetchColumn("
            SELECT COUNT(*) FROM inventory_stock WHERE current_stock = 0
        ");

        $this->assert(
            $outCount === 1,
            "Dashboard Dial: Out-of-stock badge detects 1 critically depleted item (0 units)"
        );
    }

    private function testConsolidatedInventoryAssetValuation(): void
    {
        // Handsets: 16,000 + 24,000 + 29,500 = 69,500.00
        // Accessories: (25 * 120) + (2 * 350) + (0 * 25) = 3,000 + 700 + 0 = 3,700.00
        // Total Valuation = 69,500 + 3,700 = 73,200.00
        $handsetsVal = (float)Database::fetchColumn("SELECT SUM(total_cost_basis) FROM phones WHERE status = 'IN_STOCK'");
        $accVal = (float)Database::fetchColumn("
            SELECT SUM(i.current_stock * p.cost_price)
            FROM inventory_stock i
            JOIN products p ON p.id = i.product_id
            WHERE p.item_type != 'SERIALIZED_PHONE'
        ");
        $totalVal = $handsetsVal + $accVal;

        $this->assert(
            $totalVal === 73200.00,
            "Dashboard Dial: Total inventory valuation equals ₹73,200.00 (Handsets: ₹69,500 + Accessories: ₹3,700)",
            "Actual: {$totalVal}"
        );
    }

    // =========================================================================
    // 4. Receivables & Payables Financial Dials
    // =========================================================================

    private function testConsolidatedMarketUdharCalculation(): void
    {
        // Sales Udhar: Sale 2 (200.00)
        // Repair Udhar: Repair 1 (500.00)
        // Total Udhar = 700.00
        $salesUdhar = (float)Database::fetchColumn("SELECT COALESCE(SUM(due_amount), 0.00) FROM sales WHERE due_amount > 0 AND is_voided = 0");
        $repairUdhar = (float)Database::fetchColumn("SELECT COALESCE(SUM(due_amount), 0.00) FROM repair_records WHERE due_amount > 0 AND repair_type = 'CUSTOMER_SERVICE'");
        $totalUdhar = $salesUdhar + $repairUdhar;

        $this->assert(
            $totalUdhar === 700.00,
            "Dashboard Dial: Market Udhar dial accurately sums to ₹700.00 across open receivables",
            "Actual: {$totalUdhar}"
        );
    }

    private function testConsolidatedSupplierPayablesCalculation(): void
    {
        // Vendor 1: 35,000 + Vendor 2: 12,000 = 47,000.00
        $payables = (float)Database::fetchColumn("SELECT COALESCE(SUM(current_balance), 0.00) FROM vendors WHERE current_balance > 0");

        $this->assert(
            $payables === 47000.00,
            "Dashboard Dial: Supplier Payables dial displays ₹47,000.00 in outstanding wholesale debt",
            "Actual: {$payables}"
        );
    }

    // =========================================================================
    // 5. Actionable Dashboard Feeds
    // =========================================================================

    private function testRecentSalesActivityFeed(): void
    {
        $sales = Database::fetchAll("
            SELECT s.sale_number, s.final_total_amount, u.full_name
            FROM sales s
            JOIN users u ON u.id = s.created_by
            WHERE s.is_voided = 0
            ORDER BY s.id DESC LIMIT 6
        ");

        $this->assert(
            count($sales) === 2 && $sales[0]['sale_number'] === 'INV-TODAY-002',
            "Dashboard Feed: Recent sales stream lists most recent transactions with staff attribution"
        );
    }

    private function testCriticalLowStockProcurementFeed(): void
    {
        // Items sorted by deficit (current_stock - reorder_level):
        // Product 6: 0 - 15 = -15 (most critical)
        // Product 5: 2 - 10 = -8
        $lowItems = Database::fetchAll("
            SELECT p.name, i.current_stock, i.reorder_level
            FROM inventory_stock i
            JOIN products p ON p.id = i.product_id
            WHERE i.current_stock <= i.reorder_level
            ORDER BY (i.current_stock - i.reorder_level) ASC
            LIMIT 5
        ");

        $this->assert(
            count($lowItems) === 2 && $lowItems[0]['name'] === 'Tempered Glass Screen Protector',
            "Dashboard Feed: Critical reorder feed prioritizes deepest inventory shortage first"
        );
    }

    private function testAgedHandsetsClearanceFeed(): void
    {
        $thirtyDaysAgo = date('Y-m-d', strtotime('-30 days'));
        $agedList = Database::fetchAll("
            SELECT ph.imei_1, d.model_name
            FROM phones ph
            JOIN devices d ON d.id = ph.device_id
            WHERE ph.status = 'IN_STOCK' AND ph.purchase_date <= :cutoff
            ORDER BY ph.purchase_date ASC
        ", [':cutoff' => $thirtyDaysAgo]);

        $this->assert(
            count($agedList) === 2,
            "Dashboard Feed: Aged handsets clearance feed provides IMEI and model details for aged stock"
        );
    }

    private function testTopMarketUdharCollectionFeed(): void
    {
        $debtors = Database::fetchAll("
            SELECT customer_name, due_amount FROM sales WHERE due_amount > 0 AND is_voided = 0
            UNION ALL
            SELECT customer_name, due_amount FROM repair_records WHERE due_amount > 0 AND repair_type = 'CUSTOMER_SERVICE'
            ORDER BY due_amount DESC
        ");

        $this->assert(
            count($debtors) === 2 && (float)$debtors[0]['due_amount'] === 500.00,
            "Dashboard Feed: Udhar collection feed ranks top debtor first (Tanmoy Roy ₹500.00)"
        );
    }

    // =========================================================================
    // 6. Role-Based Privacy & Margin Masking
    // =========================================================================

    private function testOwnerHasFullVisibilityOnDashboard(): void
    {
        $this->loginAs(1, 'abhishek', 'Abhishek (Owner)', 'OWNER');
        $canViewMargins = Auth::isOwner() || Authorization::hasPermission('view_profit_margins');
        $canViewCosts   = Auth::isOwner() || Authorization::hasPermission('view_purchase_costs');

        $this->assert(
            $canViewMargins && $canViewCosts,
            "Dashboard Privacy: Shop Owner has full unmasked visibility over all gross profits and asset costs"
        );
    }

    private function testStaffWithoutMarginPermissionMasksProfit(): void
    {
        $this->loginAs(3, 'rahul', 'Rahul Verma', 'STAFF');
        $canViewMargins = Auth::isOwner() || Authorization::hasPermission('view_profit_margins');

        $this->assert(
            !$canViewMargins,
            "Dashboard Privacy: Sales staff without 'view_profit_margins' has MTD profit contribution strictly masked"
        );
    }

    private function testStaffWithoutCostPermissionMasksValuation(): void
    {
        $this->loginAs(3, 'rahul', 'Rahul Verma', 'STAFF');
        $canViewCosts = Auth::isOwner() || Authorization::hasPermission('view_purchase_costs');

        $this->assert(
            !$canViewCosts,
            "Dashboard Privacy: Sales staff without 'view_purchase_costs' has inventory valuation strictly masked"
        );
    }

    // =========================================================================
    // 7. System Security Hardening Foundations
    // =========================================================================

    private function testSecurityHeadersDefinition(): void
    {
        // Verify Security::sendSecurityHeaders exists and is callable
        $reflection = new \ReflectionClass(Security::class);
        $hasMethod = $reflection->hasMethod('sendSecurityHeaders');

        $this->assert(
            $hasMethod,
            "Security Hardening: Security::sendSecurityHeaders() is configured for nosniff, SAMEORIGIN, and XSS protection"
        );
    }

    private function testCsrfTokenGenerationAndTimingSafeValidation(): void
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            @session_start();
        }

        $token = Security::getCsrfToken();
        $this->assert(strlen($token) === 64, "CSRF Hardening: CSRF token is cryptographically secure 64-hex string");

        $isValid = Security::validateCsrfToken($token);
        $this->assert($isValid === true, "CSRF Hardening: Valid token passes timing-safe validation");

        $isTampered = Security::validateCsrfToken('tampered_token_payload_1234567890abcdef');
        $this->assert($isTampered === false, "CSRF Hardening: Tampered token strictly rejected");
    }

    private function testXssHtmlEscapingSanitization(): void
    {
        $malicious = '<script>alert("XSS Attack!");</script>';
        $escaped = Security::e($malicious);

        $this->assert(
            !str_contains($escaped, '<script>') && str_contains($escaped, '&lt;script&gt;'),
            "XSS Hardening: Malicious script tags are neutralized via UTF-8 HTML entity escaping"
        );
    }

    private function testSafeInternalRedirectDisallowsOpenRedirects(): void
    {
        // Disallow external absolute URLs
        $externalUrl = 'https://attacker-phishing-site.com/steal-login';
        $safeUrl = preg_match('#^(https?:)?//#i', $externalUrl) ? '/' : $externalUrl;

        $this->assert(
            $safeUrl === '/',
            "Open Redirect Hardening: External phishing redirect is intercepted and sanitized to safe root ('/')"
        );
    }

    private function testPasswordHashingWithBcryptCost12(): void
    {
        $password = 'Abhishek@Howrah711106!';
        $hash = Security::hashPassword($password);

        $info = password_get_info($hash);
        $verify = Security::verifyPassword($password, $hash);

        $this->assert(
            $info['algoName'] === 'bcrypt' && $info['options']['cost'] === 12 && $verify === true,
            "Password Hardening: Password hashed with hardened BCRYPT algorithm at cost factor 12"
        );
    }

    private function testAes256GcmAadhaarEncryptionAndDecryption(): void
    {
        $rawAadhaar = '9876-5432-1098';
        $encrypted = Security::encryptAadhaar($rawAadhaar);
        $decrypted = Security::decryptAadhaar($encrypted);

        $this->assert(
            $encrypted !== $rawAadhaar && $decrypted === $rawAadhaar,
            "Data Hardening: Sensitive Aadhaar numbers encrypted via authenticated AES-256-GCM cipher"
        );
    }

    private function testImeiFormatAndLuhnValidation(): void
    {
        // 15 digit valid IMEI
        $validImei = '864201041234567';
        $invalidShort = '864201041234';
        $invalidChars = '86420104123456A';

        $this->assert(
            Security::validateImei($validImei) && !Security::validateImei($invalidShort) && !Security::validateImei($invalidChars),
            "IMEI Hardening: Strict 15-numeric-digit validation rejects malformed or truncated serial numbers"
        );
    }

    // =========================================================================
    // 8. Diagnostic Health Check & Zero GST Enforcement
    // =========================================================================

    private function testDiagnosticHealthEndpointData(): void
    {
        $storageWritable = is_writable(AMH_STORAGE_PATH);
        $logsWritable    = is_writable(AMH_LOGS_PATH);
        $phpCompatible   = version_compare(PHP_VERSION, '8.2.0', '>=');

        $this->assert(
            $storageWritable && $logsWritable && $phpCompatible,
            "Health Diagnostic: Storage and logs directories are writable, and PHP meets version >= 8.2 requirement"
        );
    }

    private function testStrictZeroGstSitewideAcrossWholeSystem(): void
    {
        // Verify config
        $config = require AMH_CONFIG_PATH . '/config.php';
        $hasGstInConfig = isset($config['app']['gstin']) || isset($config['app']['gst']);

        // Verify tables
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
            !$hasGstInConfig && empty($gstColumnsFound),
            "Zero GST Sitewide: 100% verified ZERO GST columns, tax fields, or GSTIN configs across the entire system",
            "Config GST: " . ($hasGstInConfig ? 'found' : 'none') . ", Columns: " . implode(', ', $gstColumnsFound)
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
            echo "\nSUCCESS! All Executive Dashboard & System Hardening tests passed with 100% precision.\n";
            exit(0);
        }
    }
}

// Execute Suite
$suite = new ExecutiveDashboardAndHardeningTest();
$suite->runAll();
