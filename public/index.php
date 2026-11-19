<?php
declare(strict_types=1);

// Route static files directly when running under PHP built-in CLI server
if (php_sapi_name() === 'cli-server') {
    $urlPath = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
    if ($urlPath !== '/' && file_exists(__DIR__ . $urlPath)) {
        return false;
    }
}

/**
 * MOBILE STORE POS & INVENTORY MANAGEMENT SYSTEM
 * Front Controller & Application Bootstrap
 *
 * File: public/index.php
 */

// Define application base path constants
defined('AMH_BASE_PATH') or define('AMH_BASE_PATH', dirname(__DIR__));
defined('AMH_CONFIG_PATH') or define('AMH_CONFIG_PATH', AMH_BASE_PATH . DIRECTORY_SEPARATOR . 'config');
defined('AMH_SRC_PATH') or define('AMH_SRC_PATH', AMH_BASE_PATH . DIRECTORY_SEPARATOR . 'src');
defined('AMH_VIEWS_PATH') or define('AMH_VIEWS_PATH', AMH_BASE_PATH . DIRECTORY_SEPARATOR . 'views');
defined('AMH_PUBLIC_PATH') or define('AMH_PUBLIC_PATH', __DIR__);
defined('AMH_STORAGE_PATH') or define('AMH_STORAGE_PATH', AMH_BASE_PATH . DIRECTORY_SEPARATOR . 'storage');
defined('AMH_LOGS_PATH') or define('AMH_LOGS_PATH', AMH_STORAGE_PATH . DIRECTORY_SEPARATOR . 'logs');

// Load Core Foundation Classes
require_once AMH_SRC_PATH . DIRECTORY_SEPARATOR . 'ErrorHandler.php';
require_once AMH_SRC_PATH . DIRECTORY_SEPARATOR . 'Database.php';
require_once AMH_SRC_PATH . DIRECTORY_SEPARATOR . 'Security.php';
require_once AMH_SRC_PATH . DIRECTORY_SEPARATOR . 'Financial.php';
require_once AMH_SRC_PATH . DIRECTORY_SEPARATOR . 'RateLimiter.php';
require_once AMH_SRC_PATH . DIRECTORY_SEPARATOR . 'Auth.php';
require_once AMH_SRC_PATH . DIRECTORY_SEPARATOR . 'Authorization.php';
require_once AMH_SRC_PATH . DIRECTORY_SEPARATOR . 'AuditLogger.php';
require_once AMH_SRC_PATH . DIRECTORY_SEPARATOR . 'View.php';
require_once AMH_SRC_PATH . DIRECTORY_SEPARATOR . 'Router.php';
require_once AMH_SRC_PATH . DIRECTORY_SEPARATOR . 'Helpers.php';

// Load Controllers
require_once AMH_SRC_PATH . DIRECTORY_SEPARATOR . 'Controllers' . DIRECTORY_SEPARATOR . 'AuthController.php';
require_once AMH_SRC_PATH . DIRECTORY_SEPARATOR . 'Controllers' . DIRECTORY_SEPARATOR . 'UserController.php';
require_once AMH_SRC_PATH . DIRECTORY_SEPARATOR . 'Controllers' . DIRECTORY_SEPARATOR . 'CategoryController.php';
require_once AMH_SRC_PATH . DIRECTORY_SEPARATOR . 'Controllers' . DIRECTORY_SEPARATOR . 'ProductController.php';
require_once AMH_SRC_PATH . DIRECTORY_SEPARATOR . 'Controllers' . DIRECTORY_SEPARATOR . 'InventoryController.php';
require_once AMH_SRC_PATH . DIRECTORY_SEPARATOR . 'Controllers' . DIRECTORY_SEPARATOR . 'PhoneController.php';
require_once AMH_SRC_PATH . DIRECTORY_SEPARATOR . 'Controllers' . DIRECTORY_SEPARATOR . 'ImeiController.php';
require_once AMH_SRC_PATH . DIRECTORY_SEPARATOR . 'Controllers' . DIRECTORY_SEPARATOR . 'VendorController.php';
require_once AMH_SRC_PATH . DIRECTORY_SEPARATOR . 'Controllers' . DIRECTORY_SEPARATOR . 'PurchaseController.php';
require_once AMH_SRC_PATH . DIRECTORY_SEPARATOR . 'Controllers' . DIRECTORY_SEPARATOR . 'SaleController.php';
require_once AMH_SRC_PATH . DIRECTORY_SEPARATOR . 'Controllers' . DIRECTORY_SEPARATOR . 'RepairController.php';
require_once AMH_SRC_PATH . DIRECTORY_SEPARATOR . 'Controllers' . DIRECTORY_SEPARATOR . 'ReturnController.php';
require_once AMH_SRC_PATH . DIRECTORY_SEPARATOR . 'Controllers' . DIRECTORY_SEPARATOR . 'LedgerController.php';
require_once AMH_SRC_PATH . DIRECTORY_SEPARATOR . 'Controllers' . DIRECTORY_SEPARATOR . 'ProfitLossController.php';
require_once AMH_SRC_PATH . DIRECTORY_SEPARATOR . 'Controllers' . DIRECTORY_SEPARATOR . 'ReportController.php';
require_once AMH_SRC_PATH . DIRECTORY_SEPARATOR . 'Controllers' . DIRECTORY_SEPARATOR . 'DashboardController.php';

// Load Configuration
$config = require AMH_CONFIG_PATH . DIRECTORY_SEPARATOR . 'config.php';

// Register Global Error & Exception Handlers
AMH\ErrorHandler::register(
    $config['app']['debug'] ?? true,
    $config['logging']['error_file'] ?? ''
);

// Initialize Hardened Session & Emit Hardened Security Headers
AMH\Auth::startSession();
AMH\Security::sendSecurityHeaders();

// Define Routes
use AMH\Router;
use AMH\View;
use AMH\Auth;
use AMH\Database;
use AMH\Security;
use AMH\Controllers\AuthController;
use AMH\Controllers\UserController;
use AMH\Controllers\CategoryController;
use AMH\Controllers\ProductController;
use AMH\Controllers\InventoryController;
use AMH\Controllers\PhoneController;
use AMH\Controllers\ImeiController;
use AMH\Controllers\VendorController;
use AMH\Controllers\PurchaseController;
use AMH\Controllers\SaleController;
use AMH\Controllers\RepairController;
use AMH\Controllers\ReturnController;
use AMH\Controllers\LedgerController;
use AMH\Controllers\ProfitLossController;
use AMH\Controllers\ReportController;
use AMH\Controllers\DashboardController;

// --- Authentication Routes ---
Router::get('/login', [AuthController::class, 'showLogin']);
Router::post('/login', [AuthController::class, 'login']);
Router::post('/logout', [AuthController::class, 'logout']);

// --- Protected Core Home / Executive Command Center (Prompt 12) ---
Router::get('/', [DashboardController::class, 'index']);
Router::get('/dashboard', [DashboardController::class, 'index']);

Router::get('/foundation', function() use ($config) {
    Auth::requireLogin('/login');

    $dbConnected = false;
    $dbError = null;
    $tableCount = 0;

    try {
        $pdo = Database::getConnection();
        $dbConnected = true;
        $tables = Database::fetchAll("SHOW TABLES");
        $tableCount = count($tables);
    } catch (\Throwable $e) {
        $dbError = $e->getMessage();
    }

    View::render('home/index', [
        'pageTitle'    => 'System Foundation Status',
        'dbConnected'  => $dbConnected,
        'dbError'      => $dbError,
        'tableCount'   => $tableCount,
        'phpVersion'   => PHP_VERSION,
        'serverTime'   => date('Y-m-d H:i:s T'),
        'timezone'     => date_default_timezone_get(),
        'appConfig'    => $config['app'],
    ], 'main');
});

// --- User Profile & Self Password Change ---
Router::get('/profile/change-password', [UserController::class, 'showChangePassword']);
Router::post('/profile/change-password', [UserController::class, 'changePassword']);

// --- User & Permission Management (Restricted to manage_users / Owner) ---
Router::get('/users', [UserController::class, 'index']);
Router::get('/users/create', [UserController::class, 'create']);
Router::post('/users/create', [UserController::class, 'store']);
Router::get('/users/{id}/edit', [UserController::class, 'edit']);
Router::post('/users/{id}/edit', [UserController::class, 'update']);
Router::post('/users/{id}/toggle-status', [UserController::class, 'toggleStatus']);
Router::get('/users/{id}/reset-password', [UserController::class, 'showResetPassword']);
Router::post('/users/{id}/reset-password', [UserController::class, 'resetPassword']);
Router::get('/users/{id}/permissions', [UserController::class, 'showPermissions']);
Router::post('/users/{id}/permissions', [UserController::class, 'updatePermissions']);

// --- Category Management Routes ---
Router::get('/categories', [CategoryController::class, 'index']);
Router::get('/categories/create', [CategoryController::class, 'create']);
Router::post('/categories/create', [CategoryController::class, 'store']);
Router::get('/categories/{id}/edit', [CategoryController::class, 'edit']);
Router::post('/categories/{id}/edit', [CategoryController::class, 'update']);
Router::post('/categories/{id}/toggle-status', [CategoryController::class, 'toggleStatus']);
Router::post('/categories/{id}/delete', [CategoryController::class, 'delete']);

// --- Product Catalog Management Routes ---
Router::get('/products', [ProductController::class, 'index']);
Router::get('/products/create', [ProductController::class, 'create']);
Router::post('/products/create', [ProductController::class, 'store']);
Router::get('/products/{id}', [ProductController::class, 'show']);
Router::get('/products/{id}/edit', [ProductController::class, 'edit']);
Router::post('/products/{id}/edit', [ProductController::class, 'update']);
Router::post('/products/{id}/toggle-status', [ProductController::class, 'toggleStatus']);

// --- Inventory & Stock Ledger Routes ---
Router::get('/inventory', [InventoryController::class, 'index']);
Router::get('/inventory/{id}/adjust', [InventoryController::class, 'showAdjust']);
Router::post('/inventory/{id}/adjust', [InventoryController::class, 'adjust']);

// --- Serialized Mobile Management Routes (Prompt 4) ---
Router::get('/phones', [PhoneController::class, 'index']);
Router::get('/phones/new', [PhoneController::class, 'indexNew']);
Router::get('/phones/used', [PhoneController::class, 'indexUsed']);
Router::get('/phones/create-new', [PhoneController::class, 'createNew']);
Router::post('/phones/create-new', [PhoneController::class, 'storeNew']);
Router::get('/phones/new/create', [PhoneController::class, 'createNew']);
Router::post('/phones/new/create', [PhoneController::class, 'storeNew']);
Router::get('/phones/create-used', [PhoneController::class, 'createUsed']);
Router::post('/phones/create-used', [PhoneController::class, 'storeUsed']);
Router::get('/phones/used/create', [PhoneController::class, 'createUsed']);
Router::post('/phones/used/create', [PhoneController::class, 'storeUsed']);
Router::get('/phones/{id}', [PhoneController::class, 'show']);
Router::post('/phones/{id}/reveal-id', [PhoneController::class, 'revealAadhaar']);
Router::get('/phones/{id}/costs/create', [PhoneController::class, 'showAddCost']);
Router::post('/phones/{id}/costs/create', [PhoneController::class, 'addCost']);

// --- Dedicated Counter IMEI Search Tool ---
Router::get('/imei', [ImeiController::class, 'search']);

// --- Vendor Management Routes (Prompt 5) ---
Router::get('/vendors', [VendorController::class, 'index']);
Router::get('/vendors/create', [VendorController::class, 'create']);
Router::post('/vendors/create', [VendorController::class, 'store']);
Router::get('/vendors/{id}', [VendorController::class, 'show']);
Router::get('/vendors/{id}/edit', [VendorController::class, 'edit']);
Router::post('/vendors/{id}/edit', [VendorController::class, 'update']);
Router::post('/vendors/{id}/toggle-status', [VendorController::class, 'toggleStatus']);
Router::post('/vendors/{id}/delete', [VendorController::class, 'delete']);

// --- Purchase & Consignment Procurement Routes (Prompt 5) ---
Router::get('/purchases', [PurchaseController::class, 'index']);
Router::get('/purchases/create', [PurchaseController::class, 'create']);
Router::post('/purchases/create', [PurchaseController::class, 'store']);
Router::get('/purchases/{id}', [PurchaseController::class, 'show']);
Router::get('/purchases/{id}/payments/create', [PurchaseController::class, 'showRecordPayment']);
Router::post('/purchases/{id}/payments/create', [PurchaseController::class, 'recordPayment']);

// --- Counter Sales & Customer Credit Routes (Prompt 6) ---
Router::get('/sales', [SaleController::class, 'index']);
Router::get('/sales/create', [SaleController::class, 'create']);
Router::post('/sales/create', [SaleController::class, 'store']);
Router::get('/sales/{id}', [SaleController::class, 'show']);
Router::get('/sales/{id}/payments/create', [SaleController::class, 'showRecordPayment']);
Router::post('/sales/{id}/payments/create', [SaleController::class, 'recordPayment']);
Router::get('/sales/{id}/return', [ReturnController::class, 'create']);
Router::post('/sales/{id}/return', [ReturnController::class, 'store']);
Router::post('/sales/{id}/void', [ReturnController::class, 'voidSale']);
Router::get('/returns', [ReturnController::class, 'index']);
Router::get('/returns/{id}', [ReturnController::class, 'show']);
// --- Repair Records & Phone Refurbishment Routes (Prompt 7) ---
Router::get('/repairs', [RepairController::class, 'index']);
Router::get('/repairs/customer/create', [RepairController::class, 'createCustomerRepair']);
Router::post('/repairs/customer/create', [RepairController::class, 'storeCustomerRepair']);
Router::post('/repairs/customer', [RepairController::class, 'storeCustomerRepair']);
Router::get('/repairs/refurbish/create', [RepairController::class, 'createRefurbishment']);
Router::post('/repairs/refurbish/create', [RepairController::class, 'storeRefurbishment']);
Router::post('/repairs/refurbish', [RepairController::class, 'storeRefurbishment']);
Router::get('/repairs/{id}', [RepairController::class, 'show']);
Router::get('/repairs/{id}/payments/create', [RepairController::class, 'showRecordPayment']);
Router::post('/repairs/{id}/payments/create', [RepairController::class, 'recordPayment']);
Router::post('/repairs/{id}/payments', [RepairController::class, 'recordPayment']);

// --- Customer Credit Ledger & Vendor Payables Routes (Prompt 9) ---
Router::get('/ledgers/customers', [LedgerController::class, 'customers']);
Router::get('/ledgers/customers/{identifier}', [LedgerController::class, 'customerStatement']);
Router::get('/ledgers/customers/{identifier}/settle', [LedgerController::class, 'showSettleCustomer']);
Router::post('/ledgers/customers/{identifier}/settle', [LedgerController::class, 'settleCustomer']);

Router::get('/ledgers/vendors', [LedgerController::class, 'vendors']);
Router::get('/ledgers/vendors/{id}', [LedgerController::class, 'vendorStatement']);
Router::get('/ledgers/vendors/{id}/settle', [LedgerController::class, 'showSettleVendor']);
Router::post('/ledgers/vendors/{id}/settle', [LedgerController::class, 'settleVendor']);

Router::get('/ledgers/payments', [LedgerController::class, 'payments']);
Router::get('/payments', function() {
    \AMH\Security::safeRedirect('/ledgers/payments');
});
Router::get('/credit', function() {
    \AMH\Security::safeRedirect('/ledgers/customers');
});

// --- Profit & Loss (P&L) Routes (Prompt 10) ---
Router::get('/profit-loss', [ProfitLossController::class, 'index']);
Router::get('/profit-loss/print', [ProfitLossController::class, 'print']);

// --- Management Reports & Native Export Engine Routes (Prompt 11) ---
Router::get('/reports', [ReportController::class, 'index']);
Router::get('/reports/sales', [ReportController::class, 'sales']);
Router::get('/reports/inventory', [ReportController::class, 'inventory']);
Router::get('/reports/mobiles', [ReportController::class, 'mobiles']);
Router::get('/reports/credit', [ReportController::class, 'credit']);
Router::get('/reports/purchases', [ReportController::class, 'purchases']);
Router::get('/reports/repairs', [ReportController::class, 'repairs']);
Router::get('/reports/daily-close', [ReportController::class, 'dailyClose']);

// --- Comprehensive Production Health & Diagnostic Endpoint (Prompt 12) ---
Router::get('/health', function() {
    header('Content-Type: application/json; charset=UTF-8');
    $dbOk = false;
    $dbError = null;
    $tables = [];

    try {
        Database::getConnection();
        $dbOk = true;
        // Check core tables
        $tables = Database::fetchAll("SHOW TABLES");
    } catch (\Throwable $e) {
        $dbOk = false;
        $dbError = $e->getMessage();
    }

    $storageWritable = is_writable(AMH_STORAGE_PATH);
    $logsWritable    = is_writable(AMH_LOGS_PATH);
    $phpCompatible   = version_compare(PHP_VERSION, '8.2.0', '>=');

    $isHealthy = $dbOk && $storageWritable && $logsWritable && $phpCompatible;

    echo json_encode([
        'status'           => $isHealthy ? 'healthy' : 'degraded',
        'application'      => $config['app']['name'] ?? 'Mobile Hub POS',
        'shop_location'    => $config['app']['address'] ?? '',
        'tax_policy'       => 'Zero GST Sitewide (Strictly Enforced)',
        'php_version'      => PHP_VERSION,
        'php_compatible'   => $phpCompatible,
        'database'         => $dbOk ? 'connected' : 'disconnected',
        'database_tables'  => count($tables),
        'storage_writable' => $storageWritable,
        'logs_writable'    => $logsWritable,
        'timezone'         => date_default_timezone_get(),
        'server_time'      => date('Y-m-d H:i:s T'),
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
});

// Dispatch the Request
Router::dispatch();
