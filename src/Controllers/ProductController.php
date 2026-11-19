<?php
declare(strict_types=1);

namespace AMH\Controllers;

use AMH\Auth;
use AMH\Authorization;
use AMH\Database;
use AMH\Security;
use AMH\View;
use AMH\AuditLogger;

/**
 * Master Product Catalog Controller
 *
 * Manages serialized mobile models and accessory catalog records.
 * Supports counter search, category filtering, stock initialization, and detail ledger inspection.
 */
class ProductController
{
    /**
     * Display searchable, filterable, and paginated product catalog.
     */
    public function index(): void
    {
        Auth::requireLogin();

        $search       = trim($_GET['q'] ?? '');
        $categoryId   = isset($_GET['category_id']) && $_GET['category_id'] !== '' ? (int)$_GET['category_id'] : null;
        $itemType     = trim($_GET['item_type'] ?? 'ALL');
        $status       = trim($_GET['status'] ?? 'all');
        $stockStatus  = trim($_GET['stock_status'] ?? 'all');
        $page         = max(1, (int)($_GET['page'] ?? 1));
        $limit        = 20;
        $offset       = ($page - 1) * $limit;

        // Build parameterized query
        $where = [];
        $params = [];

        if ($search !== '') {
            $where[] = "(p.brand LIKE :q1 OR p.model_name LIKE :q2 OR p.sku LIKE :q3)";
            $params[':q1'] = '%' . $search . '%';
            $params[':q2'] = '%' . $search . '%';
            $params[':q3'] = '%' . $search . '%';
        }

        if ($categoryId !== null && $categoryId > 0) {
            $where[] = "p.category_id = :cat_id";
            $params[':cat_id'] = $categoryId;
        }

        if (in_array($itemType, ['SERIALIZED_PHONE', 'ACCESSORY'], true)) {
            $where[] = "p.item_type = :item_type";
            $params[':item_type'] = $itemType;
        }

        if ($status === 'active') {
            $where[] = "p.is_active = 1";
        } elseif ($status === 'inactive') {
            $where[] = "p.is_active = 0";
        }

        if ($stockStatus === 'in_stock') {
            $where[] = "COALESCE(s.quantity, 0) > p.min_stock_alert";
        } elseif ($stockStatus === 'low_stock') {
            $where[] = "(COALESCE(s.quantity, 0) > 0 AND COALESCE(s.quantity, 0) <= p.min_stock_alert)";
        } elseif ($stockStatus === 'out_of_stock') {
            $where[] = "COALESCE(s.quantity, 0) <= 0";
        }

        $whereClause = !empty($where) ? 'WHERE ' . implode(' AND ', $where) : '';

        // Get total count for pagination
        $countSql = "
            SELECT COUNT(*)
            FROM products p
            JOIN categories c ON c.id = p.category_id
            LEFT JOIN inventory_stock s ON s.product_id = p.id
            {$whereClause}
        ";
        $totalRecords = (int)Database::fetchColumn($countSql, $params);
        $totalPages = max(1, (int)ceil($totalRecords / $limit));

        // Fetch paginated products with category and stock info
        $dataSql = "
            SELECT p.id, p.category_id, p.brand, p.model_name, p.item_type, p.sku,
                   p.min_stock_alert, p.default_sale_price, p.is_active, p.created_at,
                   c.name AS category_name,
                   COALESCE(s.quantity, 0) AS stock_quantity,
                   COALESCE(s.avg_unit_cost, 0.00) AS avg_unit_cost,
                   s.last_restocked_at
            FROM products p
            JOIN categories c ON c.id = p.category_id
            LEFT JOIN inventory_stock s ON s.product_id = p.id
            {$whereClause}
            ORDER BY p.brand ASC, p.model_name ASC
            LIMIT {$limit} OFFSET {$offset}
        ";

        $products = Database::fetchAll($dataSql, $params);

        // Fetch categories for filter dropdown
        $categories = Database::fetchAll("SELECT id, name FROM categories WHERE is_active = 1 ORDER BY name ASC");

        View::render('products/index', [
            'pageTitle'       => 'Product Catalog',
            'products'        => $products,
            'categories'      => $categories,
            'search'          => $search,
            'selectedCat'     => $categoryId,
            'selectedType'    => $itemType,
            'selectedStatus'  => $status,
            'selectedStock'   => $stockStatus,
            'page'            => $page,
            'totalPages'      => $totalPages,
            'totalRecords'    => $totalRecords,
            'canManage'       => Authorization::hasPermission('manage_inventory'),
            'canViewCost'     => Authorization::hasPermission('view_purchase_costs'),
        ], 'main');
    }

    /**
     * Render the create product form.
     */
    public function create(): void
    {
        Authorization::requirePermission('manage_inventory');

        $categories = Database::fetchAll("SELECT id, name FROM categories WHERE is_active = 1 ORDER BY name ASC");

        View::render('products/create', [
            'pageTitle'   => 'Add New Product',
            'categories'  => $categories,
            'formData'    => [
                'min_stock_alert' => 2,
                'item_type'       => 'ACCESSORY',
                'is_active'       => 1,
            ],
            'canViewCost' => Authorization::hasPermission('view_purchase_costs'),
        ], 'main');
    }

    /**
     * Store a newly created product in the catalog.
     */
    public function store(): void
    {
        Authorization::requirePermission('manage_inventory');

        $csrfToken = $_POST['csrf_token'] ?? null;
        if (!Security::validateCsrfToken($csrfToken)) {
            View::flash('danger', 'Security verification failed (CSRF token missing or expired). Please try again.');
            Security::safeRedirect('/products/create');
            return;
        }

        $categoryId      = (int)($_POST['category_id'] ?? 0);
        $brand           = trim($_POST['brand'] ?? '');
        $modelName       = trim($_POST['model_name'] ?? '');
        $itemType        = strtoupper(trim($_POST['item_type'] ?? 'ACCESSORY'));
        $sku             = trim($_POST['sku'] ?? '');
        $minStockAlert   = max(0, (int)($_POST['min_stock_alert'] ?? 2));
        $defaultSalePrice= isset($_POST['default_sale_price']) && $_POST['default_sale_price'] !== '' ? (float)$_POST['default_sale_price'] : null;
        $isActive        = isset($_POST['is_active']) ? 1 : 0;

        // Opening stock optional inputs
        $openingStockQty = max(0, (int)($_POST['opening_stock_qty'] ?? 0));
        $openingUnitCost = max(0.0, (float)($_POST['opening_unit_cost'] ?? 0.00));

        $errors = [];

        // Validate Category
        $catExists = Database::fetchOne("SELECT id, name FROM categories WHERE id = :id", [':id' => $categoryId]);
        if (!$catExists) {
            $errors[] = 'Please select a valid product category.';
        }

        if (strlen($brand) < 1 || strlen($brand) > 100) {
            $errors[] = 'Brand must be between 1 and 100 characters.';
        }

        if (strlen($modelName) < 1 || strlen($modelName) > 150) {
            $errors[] = 'Model name must be between 1 and 150 characters.';
        }

        if (!in_array($itemType, ['SERIALIZED_PHONE', 'ACCESSORY'], true)) {
            $errors[] = 'Invalid item type selected.';
        }

        // Validate SKU if provided
        $cleanSku = null;
        if ($sku !== '') {
            $cleanSku = strtoupper(preg_replace('/[^a-zA-Z0-9_\-\.]/', '', $sku));
            if (strlen($cleanSku) < 2 || strlen($cleanSku) > 50) {
                $errors[] = 'SKU must be between 2 and 50 alphanumeric characters.';
            } else {
                $existingSku = Database::fetchOne("SELECT id FROM products WHERE sku = :sku LIMIT 1", [':sku' => $cleanSku]);
                if ($existingSku) {
                    $errors[] = "The SKU '{$cleanSku}' is already registered to another product.";
                }
            }
        }

        if ($defaultSalePrice !== null && $defaultSalePrice < 0) {
            $errors[] = 'Default sale price cannot be negative.';
        }

        if (!empty($errors)) {
            View::flash('danger', implode(' ', $errors));
            $categories = Database::fetchAll("SELECT id, name FROM categories WHERE is_active = 1 ORDER BY name ASC");
            View::render('products/create', [
                'pageTitle'   => 'Add New Product',
                'categories'  => $categories,
                'formData'    => [
                    'category_id'        => $categoryId,
                    'brand'              => $brand,
                    'model_name'         => $modelName,
                    'item_type'          => $itemType,
                    'sku'                => $sku,
                    'min_stock_alert'    => $minStockAlert,
                    'default_sale_price' => $defaultSalePrice,
                    'is_active'          => $isActive,
                    'opening_stock_qty'  => $openingStockQty,
                    'opening_unit_cost'  => $openingUnitCost,
                ],
                'canViewCost' => Authorization::hasPermission('view_purchase_costs'),
            ], 'main');
            return;
        }

        $operatorId = Auth::id() ?? 1;

        $newProductId = Database::transaction(function() use (
            $categoryId, $brand, $modelName, $itemType, $cleanSku,
            $minStockAlert, $defaultSalePrice, $isActive, $openingStockQty,
            $openingUnitCost, $operatorId
        ) {
            Database::execute("
                INSERT INTO products (
                    category_id, brand, model_name, item_type, sku,
                    min_stock_alert, default_sale_price, is_active,
                    created_at, updated_at
                ) VALUES (
                    :cid, :brand, :model, :itype, :sku,
                    :alert, :price, :act,
                    NOW(), NOW()
                )
            ", [
                ':cid'   => $categoryId,
                ':brand' => $brand,
                ':model' => $modelName,
                ':itype' => $itemType,
                ':sku'   => $cleanSku,
                ':alert' => $minStockAlert,
                ':price' => $defaultSalePrice,
                ':act'   => $isActive,
            ]);

            $productId = (int)Database::fetchColumn("SELECT LAST_INSERT_ID()");
            if ($productId === 0) {
                // Fallback for drivers where LAST_INSERT_ID() query needs explicit column
                $productId = (int)Database::fetchColumn("SELECT MAX(id) FROM products");
            }

            // If item is an accessory, establish its inventory stock record
            if ($itemType === 'ACCESSORY') {
                Database::execute("
                    INSERT INTO inventory_stock (product_id, quantity, avg_unit_cost, last_restocked_at, updated_at)
                    VALUES (:pid, :qty, :cost, " . ($openingStockQty > 0 ? "NOW()" : "NULL") . ", NOW())
                ", [
                    ':pid'  => $productId,
                    ':qty'  => $openingStockQty,
                    ':cost' => $openingUnitCost,
                ]);

                // If opening stock quantity is provided, record it in stock_movements ledger
                if ($openingStockQty > 0) {
                    Database::execute("
                        INSERT INTO stock_movements (
                            product_id, movement_type, quantity_change, quantity_before, quantity_after,
                            unit_cost, reference_table, reference_id, notes, created_by, created_at
                        ) VALUES (
                            :pid, 'OPENING_STOCK', :qchange, 0, :qafter,
                            :cost, 'inventory_stock', :ref_id, 'Opening stock recorded at catalog creation', :cby, NOW()
                        )
                    ", [
                        ':pid'     => $productId,
                        ':qchange' => $openingStockQty,
                        ':qafter'  => $openingStockQty,
                        ':cost'    => $openingUnitCost,
                        ':ref_id'  => $productId,
                        ':cby'     => $operatorId,
                    ]);
                }
            }

            AuditLogger::log(
                'PRODUCT_CREATED',
                'products',
                $productId,
                null,
                [
                    'brand'      => $brand,
                    'model_name' => $modelName,
                    'sku'        => $cleanSku,
                    'item_type'  => $itemType,
                    'stock'      => $openingStockQty,
                ],
                "Created product '{$brand} {$modelName}'",
                $operatorId
            );

            return $productId;
        });

        View::flash('success', "Product '{$brand} {$modelName}' added to catalog successfully.");
        Security::safeRedirect("/products/{$newProductId}");
    }

    /**
     * Show product details and complete stock movement ledger.
     */
    public function show(array $params): void
    {
        Auth::requireLogin();

        $id = (int)($params['id'] ?? 0);

        $product = Database::fetchOne("
            SELECT p.id, p.category_id, p.brand, p.model_name, p.item_type, p.sku,
                   p.min_stock_alert, p.default_sale_price, p.is_active, p.created_at, p.updated_at,
                   c.name AS category_name,
                   COALESCE(s.quantity, 0) AS stock_quantity,
                   COALESCE(s.avg_unit_cost, 0.00) AS avg_unit_cost,
                   s.last_restocked_at
            FROM products p
            JOIN categories c ON c.id = p.category_id
            LEFT JOIN inventory_stock s ON s.product_id = p.id
            WHERE p.id = :id
        ", [':id' => $id]);

        if (!$product) {
            View::flash('danger', 'Product not found.');
            Security::safeRedirect('/products');
            return;
        }

        // Fetch recent stock movements for this product
        $movements = Database::fetchAll("
            SELECT sm.id, sm.movement_type, sm.quantity_change, sm.quantity_before, sm.quantity_after,
                   sm.unit_cost, sm.reference_table, sm.reference_id, sm.notes, sm.created_at,
                   u.username AS operator_username, u.full_name AS operator_name
            FROM stock_movements sm
            LEFT JOIN users u ON u.id = sm.created_by
            WHERE sm.product_id = :pid
            ORDER BY sm.id DESC
            LIMIT 50
        ", [':pid' => $id]);

        View::render('products/show', [
            'pageTitle'   => "{$product['brand']} {$product['model_name']}",
            'product'     => $product,
            'movements'   => $movements,
            'canManage'   => Authorization::hasPermission('manage_inventory'),
            'canViewCost' => Authorization::hasPermission('view_purchase_costs'),
        ], 'main');
    }

    /**
     * Render the edit product form.
     */
    public function edit(array $params): void
    {
        Authorization::requirePermission('manage_inventory');

        $id = (int)($params['id'] ?? 0);
        $product = Database::fetchOne("
            SELECT id, category_id, brand, model_name, item_type, sku,
                   min_stock_alert, default_sale_price, is_active
            FROM products
            WHERE id = :id
        ", [':id' => $id]);

        if (!$product) {
            View::flash('danger', 'Product not found.');
            Security::safeRedirect('/products');
            return;
        }

        $categories = Database::fetchAll("SELECT id, name FROM categories WHERE is_active = 1 ORDER BY name ASC");

        View::render('products/edit', [
            'pageTitle'  => "Edit Product: {$product['brand']} {$product['model_name']}",
            'product'    => $product,
            'categories' => $categories,
        ], 'main');
    }

    /**
     * Process product update.
     */
    public function update(array $params): void
    {
        Authorization::requirePermission('manage_inventory');

        $id = (int)($params['id'] ?? 0);
        $product = Database::fetchOne("SELECT * FROM products WHERE id = :id", [':id' => $id]);

        if (!$product) {
            View::flash('danger', 'Product not found.');
            Security::safeRedirect('/products');
            return;
        }

        $csrfToken = $_POST['csrf_token'] ?? null;
        if (!Security::validateCsrfToken($csrfToken)) {
            View::flash('danger', 'Security verification failed (CSRF token missing or expired). Please try again.');
            Security::safeRedirect("/products/{$id}/edit");
            return;
        }

        $categoryId       = (int)($_POST['category_id'] ?? 0);
        $brand            = trim($_POST['brand'] ?? '');
        $modelName        = trim($_POST['model_name'] ?? '');
        $itemType         = strtoupper(trim($_POST['item_type'] ?? $product['item_type']));
        $sku              = trim($_POST['sku'] ?? '');
        $minStockAlert    = max(0, (int)($_POST['min_stock_alert'] ?? 2));
        $defaultSalePrice = isset($_POST['default_sale_price']) && $_POST['default_sale_price'] !== '' ? (float)$_POST['default_sale_price'] : null;
        $isActive         = isset($_POST['is_active']) ? 1 : 0;

        $errors = [];

        $catExists = Database::fetchOne("SELECT id FROM categories WHERE id = :id", [':id' => $categoryId]);
        if (!$catExists) {
            $errors[] = 'Please select a valid product category.';
        }

        if (strlen($brand) < 1 || strlen($brand) > 100) {
            $errors[] = 'Brand must be between 1 and 100 characters.';
        }

        if (strlen($modelName) < 1 || strlen($modelName) > 150) {
            $errors[] = 'Model name must be between 1 and 150 characters.';
        }

        if (!in_array($itemType, ['SERIALIZED_PHONE', 'ACCESSORY'], true)) {
            $errors[] = 'Invalid item type selected.';
        }

        $cleanSku = null;
        if ($sku !== '') {
            $cleanSku = strtoupper(preg_replace('/[^a-zA-Z0-9_\-\.]/', '', $sku));
            if (strlen($cleanSku) < 2 || strlen($cleanSku) > 50) {
                $errors[] = 'SKU must be between 2 and 50 alphanumeric characters.';
            } else {
                $existingSku = Database::fetchOne(
                    "SELECT id FROM products WHERE sku = :sku AND id != :id LIMIT 1",
                    [':sku' => $cleanSku, ':id' => $id]
                );
                if ($existingSku) {
                    $errors[] = "The SKU '{$cleanSku}' is already registered to another product.";
                }
            }
        }

        if ($defaultSalePrice !== null && $defaultSalePrice < 0) {
            $errors[] = 'Default sale price cannot be negative.';
        }

        if (!empty($errors)) {
            View::flash('danger', implode(' ', $errors));
            $categories = Database::fetchAll("SELECT id, name FROM categories WHERE is_active = 1 ORDER BY name ASC");
            View::render('products/edit', [
                'pageTitle'  => "Edit Product: {$product['brand']} {$product['model_name']}",
                'product'    => [
                    'id'                 => $id,
                    'category_id'        => $categoryId,
                    'brand'              => $brand,
                    'model_name'         => $modelName,
                    'item_type'          => $itemType,
                    'sku'                => $sku,
                    'min_stock_alert'    => $minStockAlert,
                    'default_sale_price' => $defaultSalePrice,
                    'is_active'          => $isActive,
                ],
                'categories' => $categories,
            ], 'main');
            return;
        }

        $operatorId = Auth::id() ?? 1;

        Database::transaction(function() use (
            $id, $categoryId, $brand, $modelName, $itemType, $cleanSku,
            $minStockAlert, $defaultSalePrice, $isActive, $product, $operatorId
        ) {
            Database::execute("
                UPDATE products
                SET category_id = :cid, brand = :brand, model_name = :model,
                    item_type = :itype, sku = :sku, min_stock_alert = :alert,
                    default_sale_price = :price, is_active = :act, updated_at = NOW()
                WHERE id = :id
            ", [
                ':cid'   => $categoryId,
                ':brand' => $brand,
                ':model' => $modelName,
                ':itype' => $itemType,
                ':sku'   => $cleanSku,
                ':alert' => $minStockAlert,
                ':price' => $defaultSalePrice,
                ':act'   => $isActive,
                ':id'    => $id,
            ]);

            // Ensure inventory_stock row exists if accessory
            if ($itemType === 'ACCESSORY') {
                $stockRow = Database::fetchOne("SELECT product_id FROM inventory_stock WHERE product_id = :id", [':id' => $id]);
                if (!$stockRow) {
                    Database::execute("
                        INSERT INTO inventory_stock (product_id, quantity, avg_unit_cost, updated_at)
                        VALUES (:id, 0, 0.00, NOW())
                    ", [':id' => $id]);
                }
            }

            AuditLogger::log(
                'PRODUCT_UPDATED',
                'products',
                $id,
                [
                    'brand'      => $product['brand'],
                    'model_name' => $product['model_name'],
                    'sku'        => $product['sku'],
                    'item_type'  => $product['item_type'],
                    'is_active'  => $product['is_active'],
                ],
                [
                    'brand'      => $brand,
                    'model_name' => $modelName,
                    'sku'        => $cleanSku,
                    'item_type'  => $itemType,
                    'is_active'  => $isActive,
                ],
                "Updated product '{$brand} {$modelName}'",
                $operatorId
            );
        });

        View::flash('success', "Product '{$brand} {$modelName}' updated successfully.");
        Security::safeRedirect("/products/{$id}");
    }

    /**
     * Toggle active/inactive status of a product without deleting historical records.
     */
    public function toggleStatus(array $params): void
    {
        Authorization::requirePermission('manage_inventory');

        $csrfToken = $_POST['csrf_token'] ?? null;
        if (!Security::validateCsrfToken($csrfToken)) {
            View::flash('danger', 'Security verification failed (CSRF token missing or expired).');
            Security::safeRedirect('/products');
            return;
        }

        $id = (int)($params['id'] ?? 0);
        $product = Database::fetchOne("SELECT id, brand, model_name, is_active FROM products WHERE id = :id", [':id' => $id]);

        if (!$product) {
            View::flash('danger', 'Product not found.');
            Security::safeRedirect('/products');
            return;
        }

        $newStatus = ((int)$product['is_active'] === 1) ? 0 : 1;
        $operatorId = Auth::id() ?? 1;

        Database::execute("UPDATE products SET is_active = :act, updated_at = NOW() WHERE id = :id", [
            ':act' => $newStatus,
            ':id'  => $id,
        ]);

        AuditLogger::log(
            'PRODUCT_STATUS_TOGGLED',
            'products',
            $id,
            ['is_active' => $product['is_active']],
            ['is_active' => $newStatus],
            "Toggled product '{$product['brand']} {$product['model_name']}' to " . ($newStatus ? 'Active' : 'Inactive'),
            $operatorId
        );

        $actionText = $newStatus ? 'activated' : 'deactivated';
        View::flash('success', "Product '{$product['brand']} {$product['model_name']}' has been {$actionText}.");
        Security::safeRedirect('/products');
    }
}
