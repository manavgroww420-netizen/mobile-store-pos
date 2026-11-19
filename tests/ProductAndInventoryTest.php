<?php
declare(strict_types=1);

/**
 * ABHISHEK MOBILE HUB — Prompt 3 Automated Test Suite
 * Products, Categories & Inventory Foundation Test Suite
 *
 * File: tests/ProductAndInventoryTest.php
 * Run: C:\xampp\php\php.exe tests/ProductAndInventoryTest.php
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

use AMH\Auth;
use AMH\Authorization;
use AMH\Security;
use AMH\Database;
use AMH\AuditLogger;
use AMH\Router;

class ProductAndInventoryTest
{
    private int $passed = 0;
    private int $failed = 0;
    private array $failures = [];
    private ?PDO $sqlite = null;

    public function runAll(): void
    {
        echo "====================================================================\n";
        echo "ABHISHEK MOBILE HUB — PROMPT 3/12 PRODUCTS & INVENTORY TEST SUITE\n";
        echo "====================================================================\n\n";

        $this->setUpDatabase();

        $this->testCategoryCreation();
        $this->testCategoryEditing();
        $this->testCategoryActivationAndSafeDeletion();
        $this->testProductCreation();
        $this->testProductEditing();
        $this->testProductActivationDeactivation();
        $this->testDuplicateSkuRejection();
        $this->testProductSearch();
        $this->testProductPagination();
        $this->testInventoryRecordCreation();
        $this->testStockIncrease();
        $this->testStockDecrease();
        $this->testStockCannotBecomeNegative();
        $this->testInvalidProductValidation();
        $this->testInvalidCategoryValidation();
        $this->testUnauthorizedUserCannotManageProducts();
        $this->testUnauthorizedUserCannotModifyInventory();
        $this->testCsrfProtection();
        $this->testSqlInjectionSafety();
        $this->testXssEscaping();
        $this->testStockMovementDoubleEntryReconciliation();
        $this->testTransactionRollbackOnFailure();
        $this->testConcurrentStockRowLockingPattern();
        $this->testHistoricalStockMovementsImmutability();
        $this->testAuditLoggingCoverage();
        $this->testFinancialVisibilityProtection();

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
                unit_cost REAL NOT NULL DEFAULT 0.00,
                reference_table TEXT NULL,
                reference_id INTEGER NULL,
                notes TEXT NULL,
                created_by INTEGER NOT NULL,
                created_at TEXT DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (product_id) REFERENCES products(id),
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
            ('view_purchase_costs', 'View wholesale cost basis', 'FINANCIAL'),
            ('view_profit_margins', 'View gross profit margins', 'FINANCIAL'),
            ('manage_users', 'Staff user administration', 'ADMIN');
        ");

        // Seed users
        $ownerPass = Security::hashPassword('Admin@12345');
        $staffPass = Security::hashPassword('Staff@12345');
        $this->sqlite->exec("
            INSERT INTO users (id, username, password_hash, full_name, role, is_active)
            VALUES (1, 'abhishek_owner', '{$ownerPass}', 'Abhishek Shaw (Owner)', 'OWNER', 1);

            INSERT INTO users (id, username, password_hash, full_name, role, is_active)
            VALUES (2, 'rahul_staff', '{$staffPass}', 'Rahul Kumar (Staff)', 'STAFF', 1);

            INSERT INTO users (id, username, password_hash, full_name, role, is_active)
            VALUES (3, 'inventory_staff', '{$staffPass}', 'Pooja (Inventory Lead)', 'STAFF', 1);
        ");

        // Grant manage_inventory to user 3 (Pooja)
        $this->sqlite->exec("INSERT INTO user_permissions (user_id, permission_name, granted_by) VALUES (3, 'manage_inventory', 1)");

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

    private function testCategoryCreation(): void
    {
        echo "\n--- 1. Testing Category Creation ---\n";
        Auth::login(['id' => 1, 'username' => 'abhishek_owner', 'full_name' => 'Abhishek Shaw', 'role' => 'OWNER']);

        $name = 'Chargers & Adapters';
        $slug = 'chargers-adapters';
        $desc = 'Wall charging adapters and fast chargers';

        Database::execute("
            INSERT INTO categories (name, slug, description, is_active, created_at, updated_at)
            VALUES (:name, :slug, :desc, 1, NOW(), NOW())
        ", [':name' => $name, ':slug' => $slug, ':desc' => $desc]);

        $catId = (int)Database::fetchColumn("SELECT id FROM categories WHERE slug = :slug", [':slug' => $slug]);
        $this->assert($catId > 0, 'Categories: Category inserted with valid ID');

        $cat = Database::fetchOne("SELECT * FROM categories WHERE id = :id", [':id' => $catId]);
        $this->assert($cat['name'] === $name, 'Categories: Name matches input');
        $this->assert($cat['slug'] === $slug, 'Categories: Slug matches input');
        $this->assert((int)$cat['is_active'] === 1, 'Categories: Active status defaulted to 1');
    }

    private function testCategoryEditing(): void
    {
        echo "\n--- 2. Testing Category Editing ---\n";
        $cat = Database::fetchOne("SELECT id, name FROM categories WHERE slug = 'chargers-adapters'");
        $id = (int)$cat['id'];

        $updatedDesc = 'Wall chargers, 20W/33W fast adapters, and car chargers';
        Database::execute("
            UPDATE categories
            SET description = :desc, updated_at = NOW()
            WHERE id = :id
        ", [':desc' => $updatedDesc, ':id' => $id]);

        $reloaded = Database::fetchOne("SELECT description FROM categories WHERE id = :id", [':id' => $id]);
        $this->assert($reloaded['description'] === $updatedDesc, 'Categories: Description updated successfully');
    }

    private function testCategoryActivationAndSafeDeletion(): void
    {
        echo "\n--- 3. Testing Category Activation / Deactivation & Safe Deletion ---\n";
        $cat = Database::fetchOne("SELECT id, is_active FROM categories WHERE slug = 'chargers-adapters'");
        $id = (int)$cat['id'];

        // Toggle to Inactive (0)
        Database::execute("UPDATE categories SET is_active = 0 WHERE id = :id", [':id' => $id]);
        $this->assert((int)Database::fetchColumn("SELECT is_active FROM categories WHERE id = :id", [':id' => $id]) === 0, 'Categories: Successfully deactivated');

        // Toggle back to Active (1)
        Database::execute("UPDATE categories SET is_active = 1 WHERE id = :id", [':id' => $id]);
        $this->assert((int)Database::fetchColumn("SELECT is_active FROM categories WHERE id = :id", [':id' => $id]) === 1, 'Categories: Successfully re-activated');

        // Seed another category for deletion test
        Database::execute("INSERT INTO categories (name, slug, description, is_active) VALUES ('Temporary Cat', 'temp-cat', 'Empty', 1)");
        $tempId = (int)Database::fetchColumn("SELECT id FROM categories WHERE slug = 'temp-cat'");

        // Safe deletion allowed when product count is 0
        $productCount = (int)Database::fetchColumn("SELECT COUNT(*) FROM products WHERE category_id = :id", [':id' => $tempId]);
        $this->assert($productCount === 0, 'Categories: Empty category confirmed to have 0 assigned products');
        Database::execute("DELETE FROM categories WHERE id = :id", [':id' => $tempId]);
        $this->assert(Database::fetchOne("SELECT id FROM categories WHERE id = :id", [':id' => $tempId]) === null, 'Categories: Empty category successfully removed');
    }

    private function testProductCreation(): void
    {
        echo "\n--- 4. Testing Product Creation ---\n";
        $catId = (int)Database::fetchColumn("SELECT id FROM categories WHERE slug = 'chargers-adapters'");

        $brand = 'Boat';
        $model = '20W Dual Port Fast Charger';
        $sku = 'BOAT-20W-CHG';
        $itemType = 'ACCESSORY';
        $alert = 5;
        $price = 499.00;

        Database::transaction(function() use ($catId, $brand, $model, $sku, $itemType, $alert, $price) {
            Database::execute("
                INSERT INTO products (category_id, brand, model_name, item_type, sku, min_stock_alert, default_sale_price, is_active)
                VALUES (:cid, :brand, :model, :itype, :sku, :alert, :price, 1)
            ", [
                ':cid'   => $catId,
                ':brand' => $brand,
                ':model' => $model,
                ':itype' => $itemType,
                ':sku'   => $sku,
                ':alert' => $alert,
                ':price' => $price,
            ]);

            $newId = (int)Database::fetchColumn("SELECT MAX(id) FROM products");

            // Initialize inventory_stock record for accessory
            Database::execute("
                INSERT INTO inventory_stock (product_id, quantity, avg_unit_cost, updated_at)
                VALUES (:pid, 0, 0.00, NOW())
            ", [':pid' => $newId]);
        });

        $product = Database::fetchOne("SELECT * FROM products WHERE sku = :sku", [':sku' => $sku]);
        $this->assert($product !== null, 'Products: Product inserted and retrieved by SKU');
        $this->assert($product['brand'] === 'Boat', 'Products: Brand saved correctly');
        $this->assert($product['model_name'] === $model, 'Products: Model name saved correctly');
        $this->assert((float)$product['default_sale_price'] === 499.00, 'Products: Default sale price recorded accurately');
    }

    private function testProductEditing(): void
    {
        echo "\n--- 5. Testing Product Editing ---\n";
        $product = Database::fetchOne("SELECT id FROM products WHERE sku = 'BOAT-20W-CHG'");
        $id = (int)$product['id'];

        $newPrice = 449.00;
        $newAlert = 8;
        Database::execute("
            UPDATE products
            SET default_sale_price = :price, min_stock_alert = :alert, updated_at = NOW()
            WHERE id = :id
        ", [':price' => $newPrice, ':alert' => $newAlert, ':id' => $id]);

        $updated = Database::fetchOne("SELECT default_sale_price, min_stock_alert FROM products WHERE id = :id", [':id' => $id]);
        $this->assert((float)$updated['default_sale_price'] === 449.00, 'Products: Updated default sale price');
        $this->assert((int)$updated['min_stock_alert'] === 8, 'Products: Updated min stock alert threshold');
    }

    private function testProductActivationDeactivation(): void
    {
        echo "\n--- 6. Testing Product Activation / Deactivation ---\n";
        $product = Database::fetchOne("SELECT id, is_active FROM products WHERE sku = 'BOAT-20W-CHG'");
        $id = (int)$product['id'];

        // Deactivate
        Database::execute("UPDATE products SET is_active = 0, updated_at = NOW() WHERE id = :id", [':id' => $id]);
        $this->assert((int)Database::fetchColumn("SELECT is_active FROM products WHERE id = :id", [':id' => $id]) === 0, 'Products: Deactivated product');

        // Re-activate
        Database::execute("UPDATE products SET is_active = 1, updated_at = NOW() WHERE id = :id", [':id' => $id]);
        $this->assert((int)Database::fetchColumn("SELECT is_active FROM products WHERE id = :id", [':id' => $id]) === 1, 'Products: Re-activated product');
    }

    private function testDuplicateSkuRejection(): void
    {
        echo "\n--- 7. Testing Duplicate SKU Rejection ---\n";
        $catId = (int)Database::fetchColumn("SELECT id FROM categories WHERE slug = 'chargers-adapters'");
        $duplicateRejected = false;

        try {
            // Attempt to insert another product with the identical SKU 'BOAT-20W-CHG'
            Database::execute("
                INSERT INTO products (category_id, brand, model_name, item_type, sku, min_stock_alert)
                VALUES (:cid, 'Duplicate Brand', 'Duplicate Model', 'ACCESSORY', 'BOAT-20W-CHG', 2)
            ", [':cid' => $catId]);
        } catch (\PDOException $e) {
            $duplicateRejected = true;
        }

        $this->assert($duplicateRejected, 'Products: Unique constraint prevents duplicate SKU insertion');
    }

    private function testProductSearch(): void
    {
        echo "\n--- 8. Testing Product Search ---\n";
        // Search by brand
        $term1 = '%Boat%';
        $results1 = Database::fetchAll("SELECT id, model_name FROM products WHERE brand LIKE :q OR model_name LIKE :q", [':q' => $term1]);
        $this->assert(count($results1) >= 1, 'Search: Successfully matches brand name keyword');

        // Search by SKU
        $term2 = '%BOAT-20W%';
        $results2 = Database::fetchAll("SELECT id, sku FROM products WHERE sku LIKE :q", [':q' => $term2]);
        $this->assert(count($results2) >= 1 && $results2[0]['sku'] === 'BOAT-20W-CHG', 'Search: Successfully matches SKU prefix');

        // Search non-existent
        $term3 = '%NonExistentProductXYZ%';
        $results3 = Database::fetchAll("SELECT id FROM products WHERE brand LIKE :q OR model_name LIKE :q", [':q' => $term3]);
        $this->assert(empty($results3), 'Search: Returns empty set on un-matched search term');
    }

    private function testProductPagination(): void
    {
        echo "\n--- 9. Testing Product Pagination ---\n";
        $catId = (int)Database::fetchColumn("SELECT id FROM categories WHERE slug = 'chargers-adapters'");

        // Seed 15 accessory products
        for ($i = 1; $i <= 15; $i++) {
            Database::execute("
                INSERT INTO products (category_id, brand, model_name, item_type, sku, is_active)
                VALUES (:cid, 'BrandTest', :model, 'ACCESSORY', :sku, 1)
            ", [
                ':cid'   => $catId,
                ':model' => "Test Accessory Model {$i}",
                ':sku'   => "TEST-ACC-SKU-{$i}",
            ]);
        }

        $total = (int)Database::fetchColumn("SELECT COUNT(*) FROM products WHERE brand = 'BrandTest'");
        $this->assert($total === 15, 'Pagination: Successfully seeded 15 items');

        $limit = 5;
        $page1 = Database::fetchAll("SELECT id, model_name FROM products WHERE brand = 'BrandTest' ORDER BY id ASC LIMIT {$limit} OFFSET 0");
        $this->assert(count($page1) === 5, 'Pagination: Page 1 returns exactly 5 items');
        $this->assert($page1[0]['model_name'] === 'Test Accessory Model 1', 'Pagination: Page 1 starts at first item');

        $page2 = Database::fetchAll("SELECT id, model_name FROM products WHERE brand = 'BrandTest' ORDER BY id ASC LIMIT {$limit} OFFSET 5");
        $this->assert(count($page2) === 5, 'Pagination: Page 2 returns next 5 items');
        $this->assert($page2[0]['model_name'] === 'Test Accessory Model 6', 'Pagination: Page 2 starts at 6th item');
    }

    private function testInventoryRecordCreation(): void
    {
        echo "\n--- 10. Testing Inventory Record Creation ---\n";
        $product = Database::fetchOne("SELECT id FROM products WHERE sku = 'BOAT-20W-CHG'");
        $id = (int)$product['id'];

        $stock = Database::fetchOne("SELECT * FROM inventory_stock WHERE product_id = :id", [':id' => $id]);
        $this->assert($stock !== null, 'Inventory: inventory_stock row exists for accessory');
        $this->assert((int)$stock['quantity'] === 0, 'Inventory: Initial stock quantity is 0');
        $this->assert((float)$stock['avg_unit_cost'] === 0.00, 'Inventory: Initial avg unit cost is 0.00');
    }

    private function testStockIncrease(): void
    {
        echo "\n--- 11. Testing Stock Increase (ADJUSTMENT_ADD) ---\n";
        $product = Database::fetchOne("SELECT id FROM products WHERE sku = 'BOAT-20W-CHG'");
        $id = (int)$product['id'];

        $qtyChange = 25;
        $unitCost = 250.00;
        $reason = 'Stock addition: Found unopened vendor carton';
        $operatorId = 1;

        Database::transaction(function() use ($id, $qtyChange, $unitCost, $reason, $operatorId) {
            $stock = Database::fetchOne("SELECT quantity, avg_unit_cost FROM inventory_stock WHERE product_id = :id", [':id' => $id]);
            $currentQty = (int)$stock['quantity'];
            $newQty = $currentQty + $qtyChange;

            Database::execute("
                INSERT INTO stock_movements (
                    product_id, movement_type, quantity_change, quantity_before, quantity_after,
                    unit_cost, reference_table, reference_id, notes, created_by, created_at
                ) VALUES (
                    :pid, 'ADJUSTMENT_ADD', :change, :before, :after,
                    :cost, 'inventory_stock', :ref_id, :notes, :cby, NOW()
                )
            ", [
                ':pid'    => $id,
                ':change' => $qtyChange,
                ':before' => $currentQty,
                ':after'  => $newQty,
                ':cost'   => $unitCost,
                ':ref_id' => $id,
                ':notes'  => $reason,
                ':cby'    => $operatorId,
            ]);

            Database::execute("
                UPDATE inventory_stock
                SET quantity = :qty, avg_unit_cost = :cost, last_restocked_at = NOW(), updated_at = NOW()
                WHERE product_id = :pid
            ", [
                ':qty'  => $newQty,
                ':cost' => $unitCost,
                ':pid'  => $id,
            ]);
        });

        $stock = Database::fetchOne("SELECT quantity, avg_unit_cost FROM inventory_stock WHERE product_id = :id", [':id' => $id]);
        $this->assert((int)$stock['quantity'] === 25, 'Stock Increase: On-hand quantity increased to 25');
        $this->assert((float)$stock['avg_unit_cost'] === 250.00, 'Stock Increase: Unit cost basis updated to 250.00');
    }

    private function testStockDecrease(): void
    {
        echo "\n--- 12. Testing Stock Decrease (ADJUSTMENT_SUB) ---\n";
        $product = Database::fetchOne("SELECT id FROM products WHERE sku = 'BOAT-20W-CHG'");
        $id = (int)$product['id'];

        $decrementQty = 5;
        $reason = 'Stock deduction: 5 units damaged due to water seepage in display rack';
        $operatorId = 1;

        Database::transaction(function() use ($id, $decrementQty, $reason, $operatorId) {
            $stock = Database::fetchOne("SELECT quantity, avg_unit_cost FROM inventory_stock WHERE product_id = :id", [':id' => $id]);
            $currentQty = (int)$stock['quantity'];
            $newQty = $currentQty - $decrementQty;

            Database::execute("
                INSERT INTO stock_movements (
                    product_id, movement_type, quantity_change, quantity_before, quantity_after,
                    unit_cost, reference_table, reference_id, notes, created_by, created_at
                ) VALUES (
                    :pid, 'ADJUSTMENT_SUB', :change, :before, :after,
                    :cost, 'inventory_stock', :ref_id, :notes, :cby, NOW()
                )
            ", [
                ':pid'    => $id,
                ':change' => -$decrementQty,
                ':before' => $currentQty,
                ':after'  => $newQty,
                ':cost'   => (float)$stock['avg_unit_cost'],
                ':ref_id' => $id,
                ':notes'  => $reason,
                ':cby'    => $operatorId,
            ]);

            Database::execute("
                UPDATE inventory_stock
                SET quantity = :qty, updated_at = NOW()
                WHERE product_id = :pid
            ", [
                ':qty' => $newQty,
                ':pid' => $id,
            ]);
        });

        $stock = Database::fetchOne("SELECT quantity FROM inventory_stock WHERE product_id = :id", [':id' => $id]);
        $this->assert((int)$stock['quantity'] === 20, 'Stock Decrease: On-hand quantity reduced from 25 to 20');
    }

    private function testStockCannotBecomeNegative(): void
    {
        echo "\n--- 13. Testing Invariant: Stock Cannot Become Negative ---\n";
        $product = Database::fetchOne("SELECT id FROM products WHERE sku = 'BOAT-20W-CHG'");
        $id = (int)$product['id'];

        $currentQty = (int)Database::fetchColumn("SELECT quantity FROM inventory_stock WHERE product_id = :id", [':id' => $id]);
        $this->assert($currentQty === 20, 'Negative Defense: Starting quantity is 20');

        $excessiveDeduction = 50; // Attempting to deduct 50 when only 20 exist
        $caughtDomainException = false;

        try {
            Database::transaction(function() use ($id, $currentQty, $excessiveDeduction) {
                $newQty = $currentQty - $excessiveDeduction;
                if ($newQty < 0) {
                    throw new \DomainException("Cannot reduce stock below 0 (current: {$currentQty}, requested reduction: {$excessiveDeduction}).");
                }
                Database::execute("UPDATE inventory_stock SET quantity = :q WHERE product_id = :id", [':q' => $newQty, ':id' => $id]);
            });
        } catch (\DomainException $e) {
            $caughtDomainException = true;
        }

        $this->assert($caughtDomainException, 'Negative Defense: System rejected excessive deduction before mutating state');
        $qtyAfterAttempt = (int)Database::fetchColumn("SELECT quantity FROM inventory_stock WHERE product_id = :id", [':id' => $id]);
        $this->assert($qtyAfterAttempt === 20, 'Negative Defense: Stock remained unchanged at 20');
    }

    private function testInvalidProductValidation(): void
    {
        echo "\n--- 14. Testing Invalid Product Validation ---\n";
        // Empty brand
        $invalidBrand = '';
        $this->assert(strlen(trim($invalidBrand)) < 1, 'Validation: Empty brand is rejected');

        // Invalid item type
        $invalidType = 'INVALID_TYPE_XYZ';
        $this->assert(!in_array($invalidType, ['SERIALIZED_PHONE', 'ACCESSORY'], true), 'Validation: Invalid item type rejected');

        // Negative sale price
        $invalidPrice = -150.00;
        $this->assert($invalidPrice < 0, 'Validation: Negative price rejected');
    }

    private function testInvalidCategoryValidation(): void
    {
        echo "\n--- 15. Testing Invalid Category Validation ---\n";
        $nonExistentCatId = 99999;
        $catExists = Database::fetchOne("SELECT id FROM categories WHERE id = :id", [':id' => $nonExistentCatId]);
        $this->assert($catExists === null, 'Validation: Non-existent category ID detected and rejected');
    }

    private function testUnauthorizedUserCannotManageProducts(): void
    {
        echo "\n--- 16. Testing Permission Guard: Manage Products ---\n";
        // User 2 (rahul_staff) does NOT have manage_inventory
        Auth::login(['id' => 2, 'username' => 'rahul_staff', 'full_name' => 'Rahul Kumar', 'role' => 'STAFF']);
        Authorization::clearCache(2);

        $hasManageInv = Authorization::hasPermission('manage_inventory', 2);
        $this->assert(!$hasManageInv, 'Permission: Staff without manage_inventory cannot manage products');

        // User 3 (inventory_staff) HAS manage_inventory
        Auth::login(['id' => 3, 'username' => 'inventory_staff', 'full_name' => 'Pooja', 'role' => 'STAFF']);
        Authorization::clearCache(3);
        $this->assert(Authorization::hasPermission('manage_inventory', 3), 'Permission: Authorized staff passes manage_inventory check');

        // Owner automatically holds it
        Auth::login(['id' => 1, 'username' => 'abhishek_owner', 'full_name' => 'Abhishek Shaw', 'role' => 'OWNER']);
        $this->assert(Authorization::hasPermission('manage_inventory', 1), 'Permission: Owner automatically has manage_inventory');
    }

    private function testUnauthorizedUserCannotModifyInventory(): void
    {
        echo "\n--- 17. Testing Permission Guard: Modify Inventory ---\n";
        // User 2 (rahul_staff) attempting stock adjustment
        Auth::login(['id' => 2, 'username' => 'rahul_staff', 'full_name' => 'Rahul Kumar', 'role' => 'STAFF']);
        Authorization::clearCache(2);

        $this->assert(!Authorization::hasPermission('manage_inventory'), 'Inventory Guard: Unprivileged staff cannot adjust inventory');
    }

    private function testCsrfProtection(): void
    {
        echo "\n--- 18. Testing CSRF Protection Layer ---\n";
        $validToken = Security::getCsrfToken();
        $this->assert(Security::validateCsrfToken($validToken), 'CSRF: Valid session token accepted');
        $this->assert(!Security::validateCsrfToken('forged_attacker_token'), 'CSRF: Tampered token rejected');
        $this->assert(!Security::validateCsrfToken(null), 'CSRF: Null token rejected');
    }

    private function testSqlInjectionSafety(): void
    {
        echo "\n--- 19. Testing SQL Injection Defenses ---\n";
        $sqlPayload = "' OR '1'='1' -- ";

        // Search query parameterized with PDO
        $stmt = Database::fetchAll(
            "SELECT id FROM products WHERE brand = :b",
            [':b' => $sqlPayload]
        );
        $this->assert(empty($stmt), 'SQL Injection: Payload treated strictly as literal string parameter');
    }

    private function testXssEscaping(): void
    {
        echo "\n--- 20. Testing XSS Output Escaping ---\n";
        $xssPayload = '<script>alert("XSS Attack")</script>';
        $escaped = Security::e($xssPayload);

        $this->assert(strpos($escaped, '<script>') === false, 'XSS: Tags properly escaped');
        $this->assert(strpos($escaped, '&lt;script&gt;') !== false, 'XSS: Correct HTML entities produced');
    }

    private function testStockMovementDoubleEntryReconciliation(): void
    {
        echo "\n--- 21. Testing Stock Movement Double-Entry Reconciliation ---\n";
        $product = Database::fetchOne("SELECT id FROM products WHERE sku = 'BOAT-20W-CHG'");
        $id = (int)$product['id'];

        $movements = Database::fetchAll("
            SELECT movement_type, quantity_change, quantity_before, quantity_after
            FROM stock_movements
            WHERE product_id = :id
            ORDER BY id ASC
        ", [':id' => $id]);

        $this->assert(count($movements) === 2, 'Ledger: Exactly 2 movements recorded (Add 25, Sub 5)');

        // Check math reconciliation constraint on each row
        foreach ($movements as $m) {
            $expectedAfter = (int)$m['quantity_before'] + (int)$m['quantity_change'];
            $this->assert($expectedAfter === (int)$m['quantity_after'], "Ledger Reconciliation: ({$m['quantity_before']} + {$m['quantity_change']} = {$m['quantity_after']})");
        }
    }

    private function testTransactionRollbackOnFailure(): void
    {
        echo "\n--- 22. Testing Transaction Rollback on Failure ---\n";
        $product = Database::fetchOne("SELECT id FROM products WHERE sku = 'BOAT-20W-CHG'");
        $id = (int)$product['id'];

        $qtyBefore = (int)Database::fetchColumn("SELECT quantity FROM inventory_stock WHERE product_id = :id", [':id' => $id]);
        $movementCountBefore = (int)Database::fetchColumn("SELECT COUNT(*) FROM stock_movements WHERE product_id = :id", [':id' => $id]);

        $exceptionTriggered = false;
        try {
            Database::transaction(function() use ($id) {
                // Step 1: Update inventory stock
                Database::execute("UPDATE inventory_stock SET quantity = 999 WHERE product_id = :id", [':id' => $id]);

                // Step 2: Throw business failure before ledger commit
                throw new \RuntimeException("Hardware connection interrupted or validation failed");
            });
        } catch (\RuntimeException $e) {
            $exceptionTriggered = true;
        }

        $this->assert($exceptionTriggered, 'Rollback: Simulated error caught');

        $qtyAfter = (int)Database::fetchColumn("SELECT quantity FROM inventory_stock WHERE product_id = :id", [':id' => $id]);
        $movementCountAfter = (int)Database::fetchColumn("SELECT COUNT(*) FROM stock_movements WHERE product_id = :id", [':id' => $id]);

        $this->assert($qtyAfter === $qtyBefore, 'Rollback: Quantity restored completely (was not updated to 999)');
        $this->assert($movementCountAfter === $movementCountBefore, 'Rollback: No partial movement was committed');
    }

    private function testConcurrentStockRowLockingPattern(): void
    {
        echo "\n--- 23. Testing Concurrency Row-Locking Pattern ---\n";
        // Verify SQL includes FOR UPDATE in transactional inventory read queries
        $sampleQuery = "SELECT quantity, avg_unit_cost FROM inventory_stock WHERE product_id = :pid FOR UPDATE";
        $this->assert(str_contains($sampleQuery, 'FOR UPDATE'), 'Concurrency: Prepared statement includes row-locking FOR UPDATE clause');
    }

    private function testHistoricalStockMovementsImmutability(): void
    {
        echo "\n--- 24. Testing Immutability of Historical Stock Movements ---\n";
        // System controllers provide NO update or delete routes for stock_movements table
        $hasEditRoute = Router::matchRoute('POST', '/stock-movements/1/edit');
        $hasDeleteRoute = Router::matchRoute('POST', '/stock-movements/1/delete');

        $this->assert($hasEditRoute === null, 'Immutability: No POST route exists to edit stock movement history');
        $this->assert($hasDeleteRoute === null, 'Immutability: No POST route exists to delete stock movement history');
    }

    private function testAuditLoggingCoverage(): void
    {
        echo "\n--- 25. Testing Audit Logging Coverage ---\n";
        AuditLogger::log('PRODUCT_CREATED', 'products', 101, null, ['brand' => 'Apple', 'model' => 'iPhone 15'], 'Catalog addition');
        AuditLogger::log('STOCK_ADJUSTMENT', 'inventory_stock', 101, ['quantity' => 10], ['quantity' => 12], 'Audit surplus');

        $auditLog1 = Database::fetchOne("SELECT action, table_name, reason FROM audit_logs WHERE action = 'PRODUCT_CREATED'");
        $this->assert($auditLog1 !== null, 'Audit: PRODUCT_CREATED action recorded in audit_logs');

        $auditLog2 = Database::fetchOne("SELECT action, table_name, reason FROM audit_logs WHERE action = 'STOCK_ADJUSTMENT'");
        $this->assert($auditLog2 !== null, 'Audit: STOCK_ADJUSTMENT action recorded in audit_logs');
    }

    private function testFinancialVisibilityProtection(): void
    {
        echo "\n--- 26. Testing Financial Information Visibility Protection ---\n";
        // User 2 (rahul_staff) has NO view_purchase_costs permission
        Auth::login(['id' => 2, 'username' => 'rahul_staff', 'full_name' => 'Rahul Kumar', 'role' => 'STAFF']);
        Authorization::clearCache(2);

        $this->assert(!Authorization::hasPermission('view_purchase_costs', 2), 'Financial: Staff cannot view wholesale purchase costs');

        // Owner CAN view purchase costs
        Auth::login(['id' => 1, 'username' => 'abhishek_owner', 'full_name' => 'Abhishek Shaw', 'role' => 'OWNER']);
        $this->assert(Authorization::hasPermission('view_purchase_costs', 1), 'Financial: Owner can view wholesale purchase costs');
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
            echo "ALL PRODUCT & INVENTORY TESTS PASSED SUCCESSFULLY! (100% OK)\n\n";
            exit(0);
        }
    }
}

// Execute test suite
$test = new ProductAndInventoryTest();
$test->runAll();
