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
 * Generic Inventory & Stock Ledger Controller
 *
 * Enforces transactional row locking, non-negative stock invariants,
 * double-entry movement reconciliation, and controlled stock adjustments.
 */
class InventoryController
{
    /**
     * Display current on-hand inventory stock and alert metrics.
     */
    public function index(): void
    {
        Auth::requireLogin();

        $search      = trim($_GET['q'] ?? '');
        $categoryId  = isset($_GET['category_id']) && $_GET['category_id'] !== '' ? (int)$_GET['category_id'] : null;
        $stockStatus = trim($_GET['stock_status'] ?? 'all');
        $page        = max(1, (int)($_GET['page'] ?? 1));
        $limit       = 25;
        $offset      = ($page - 1) * $limit;

        // Build query conditions
        $where = ["p.item_type = 'ACCESSORY'"];
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

        if ($stockStatus === 'in_stock') {
            $where[] = "COALESCE(s.quantity, 0) > p.min_stock_alert";
        } elseif ($stockStatus === 'low_stock') {
            $where[] = "(COALESCE(s.quantity, 0) > 0 AND COALESCE(s.quantity, 0) <= p.min_stock_alert)";
        } elseif ($stockStatus === 'out_of_stock') {
            $where[] = "COALESCE(s.quantity, 0) <= 0";
        }

        $whereClause = 'WHERE ' . implode(' AND ', $where);

        // Fetch overall summary metrics
        $metrics = Database::fetchOne("
            SELECT 
                COUNT(p.id) AS total_items,
                COALESCE(SUM(s.quantity), 0) AS total_units,
                COALESCE(SUM(CASE WHEN COALESCE(s.quantity, 0) > 0 AND COALESCE(s.quantity, 0) <= p.min_stock_alert THEN 1 ELSE 0 END), 0) AS low_stock_count,
                COALESCE(SUM(CASE WHEN COALESCE(s.quantity, 0) <= 0 THEN 1 ELSE 0 END), 0) AS out_of_stock_count,
                COALESCE(SUM(s.quantity * s.avg_unit_cost), 0.00) AS total_valuation
            FROM products p
            LEFT JOIN inventory_stock s ON s.product_id = p.id
            WHERE p.item_type = 'ACCESSORY' AND p.is_active = 1
        ");

        // Pagination count
        $countSql = "
            SELECT COUNT(*)
            FROM products p
            JOIN categories c ON c.id = p.category_id
            LEFT JOIN inventory_stock s ON s.product_id = p.id
            {$whereClause}
        ";
        $totalRecords = (int)Database::fetchColumn($countSql, $params);
        $totalPages = max(1, (int)ceil($totalRecords / $limit));

        // Fetch paginated inventory list
        $dataSql = "
            SELECT p.id, p.category_id, p.brand, p.model_name, p.sku,
                   p.min_stock_alert, p.default_sale_price, p.is_active,
                   c.name AS category_name,
                   COALESCE(s.quantity, 0) AS stock_quantity,
                   COALESCE(s.avg_unit_cost, 0.00) AS avg_unit_cost,
                   s.last_restocked_at
            FROM products p
            JOIN categories c ON c.id = p.category_id
            LEFT JOIN inventory_stock s ON s.product_id = p.id
            {$whereClause}
            ORDER BY 
                CASE 
                    WHEN COALESCE(s.quantity, 0) <= 0 THEN 1
                    WHEN COALESCE(s.quantity, 0) <= p.min_stock_alert THEN 2
                    ELSE 3
                END ASC,
                p.brand ASC, p.model_name ASC
            LIMIT {$limit} OFFSET {$offset}
        ";

        $inventory = Database::fetchAll($dataSql, $params);
        $categories = Database::fetchAll("SELECT id, name FROM categories WHERE is_active = 1 ORDER BY name ASC");

        View::render('inventory/index', [
            'pageTitle'       => 'Inventory Stock Ledger',
            'inventory'       => $inventory,
            'categories'      => $categories,
            'metrics'         => $metrics,
            'search'          => $search,
            'selectedCat'     => $categoryId,
            'selectedStock'   => $stockStatus,
            'page'            => $page,
            'totalPages'      => $totalPages,
            'totalRecords'    => $totalRecords,
            'canManage'       => Authorization::hasPermission('manage_inventory'),
            'canViewCost'     => Authorization::hasPermission('view_purchase_costs'),
        ], 'main');
    }

    /**
     * Render the controlled stock adjustment form.
     */
    public function showAdjust(array $params): void
    {
        Authorization::requirePermission('manage_inventory');

        $id = (int)($params['id'] ?? 0);

        $product = Database::fetchOne("
            SELECT p.id, p.brand, p.model_name, p.sku, p.item_type, p.is_active,
                   c.name AS category_name,
                   COALESCE(s.quantity, 0) AS current_quantity,
                   COALESCE(s.avg_unit_cost, 0.00) AS avg_unit_cost
            FROM products p
            JOIN categories c ON c.id = p.category_id
            LEFT JOIN inventory_stock s ON s.product_id = p.id
            WHERE p.id = :id
        ", [':id' => $id]);

        if (!$product) {
            View::flash('danger', 'Product not found in inventory.');
            Security::safeRedirect('/inventory');
            return;
        }

        if ($product['item_type'] !== 'ACCESSORY') {
            View::flash('warning', 'Direct stock adjustment is designed for non-serialized accessories. Serialized phones require device episode intake.');
            Security::safeRedirect("/products/{$id}");
            return;
        }

        View::render('inventory/adjust', [
            'pageTitle'   => "Stock Adjustment: {$product['brand']} {$product['model_name']}",
            'product'     => $product,
            'canViewCost' => Authorization::hasPermission('view_purchase_costs'),
        ], 'main');
    }

    /**
     * Process stock adjustment with atomic row-locking and reconciliation.
     */
    public function adjust(array $params): void
    {
        Authorization::requirePermission('manage_inventory');

        $id = (int)($params['id'] ?? 0);
        $product = Database::fetchOne("SELECT id, brand, model_name, item_type FROM products WHERE id = :id", [':id' => $id]);

        if (!$product) {
            View::flash('danger', 'Product not found.');
            Security::safeRedirect('/inventory');
            return;
        }

        $csrfToken = $_POST['csrf_token'] ?? null;
        if (!Security::validateCsrfToken($csrfToken)) {
            View::flash('danger', 'Security verification failed (CSRF token missing or expired).');
            Security::safeRedirect("/inventory/{$id}/adjust");
            return;
        }

        $adjType  = trim($_POST['adjustment_type'] ?? '');
        $qty      = (int)($_POST['quantity'] ?? 0);
        $reason   = trim($_POST['reason'] ?? '');
        $unitCost = isset($_POST['unit_cost']) && $_POST['unit_cost'] !== '' ? (float)$_POST['unit_cost'] : null;

        $errors = [];

        if (!in_array($adjType, ['ADJUSTMENT_ADD', 'ADJUSTMENT_SUB', 'OPENING_STOCK'], true)) {
            $errors[] = 'Invalid adjustment type selected.';
        }

        if ($qty <= 0) {
            $errors[] = 'Adjustment quantity must be an integer greater than zero.';
        }

        if (strlen($reason) < 3 || strlen($reason) > 255) {
            $errors[] = 'A mandatory explanation/reason between 3 and 255 characters must be recorded for all inventory adjustments.';
        }

        if ($unitCost !== null && $unitCost < 0) {
            $errors[] = 'Unit cost cannot be negative.';
        }

        if (!empty($errors)) {
            View::flash('danger', implode(' ', $errors));
            Security::safeRedirect("/inventory/{$id}/adjust");
            return;
        }

        $operatorId = Auth::id() ?? 1;

        try {
            Database::transaction(function() use ($id, $product, $adjType, $qty, $reason, $unitCost, $operatorId) {
                // 1. Lock the inventory row safely with FOR UPDATE to prevent concurrency race conditions
                $stock = Database::fetchOne(
                    "SELECT quantity, avg_unit_cost FROM inventory_stock WHERE product_id = :pid FOR UPDATE",
                    [':pid' => $id]
                );

                if (!$stock) {
                    Database::execute(
                        "INSERT INTO inventory_stock (product_id, quantity, avg_unit_cost, updated_at) VALUES (:pid, 0, 0.00, NOW())",
                        [':pid' => $id]
                    );
                    $currentQty = 0;
                    $currentCost = 0.00;
                } else {
                    $currentQty = (int)$stock['quantity'];
                    $currentCost = (float)$stock['avg_unit_cost'];
                }

                // 2. Calculate quantity change and validate non-negative stock invariant
                if ($adjType === 'ADJUSTMENT_ADD' || $adjType === 'OPENING_STOCK') {
                    $qtyChange = $qty;
                    $newQty = $currentQty + $qty;
                } elseif ($adjType === 'ADJUSTMENT_SUB') {
                    $qtyChange = -$qty;
                    $newQty = $currentQty - $qty;

                    // Enforce rule: stock cannot become negative
                    if ($newQty < 0) {
                        throw new \DomainException(
                            "Cannot complete adjustment: Requested deduction of {$qty} unit(s) exceeds current on-hand stock ({$currentQty}). Inventory cannot become negative."
                        );
                    }
                } else {
                    throw new \InvalidArgumentException("Unsupported adjustment type: {$adjType}");
                }

                $effectiveCost = $unitCost ?? $currentCost;

                // 3. Insert immutable record into stock_movements ledger
                Database::execute("
                    INSERT INTO stock_movements (
                        product_id, movement_type, quantity_change, quantity_before, quantity_after,
                        unit_cost, reference_table, reference_id, notes, created_by, created_at
                    ) VALUES (
                        :pid, :mtype, :qchange, :qbefore, :qafter,
                        :cost, 'inventory_stock', :ref_id, :notes, :cby, NOW()
                    )
                ", [
                    ':pid'     => $id,
                    ':mtype'   => $adjType,
                    ':qchange' => $qtyChange,
                    ':qbefore' => $currentQty,
                    ':qafter'  => $newQty,
                    ':cost'    => $effectiveCost,
                    ':ref_id'  => $id,
                    ':notes'   => $reason,
                    ':cby'     => $operatorId,
                ]);

                // 4. Update authoritative stock quantity
                Database::execute("
                    UPDATE inventory_stock
                    SET quantity = :new_qty,
                        last_restocked_at = CASE WHEN :qchange > 0 THEN NOW() ELSE last_restocked_at END,
                        updated_at = NOW()
                    WHERE product_id = :pid
                ", [
                    ':new_qty' => $newQty,
                    ':qchange' => $qtyChange,
                    ':pid'     => $id,
                ]);

                // 5. Record event in audit_logs
                AuditLogger::log(
                    'STOCK_ADJUSTMENT',
                    'inventory_stock',
                    $id,
                    ['quantity' => $currentQty],
                    ['quantity' => $newQty, 'change' => $qtyChange, 'type' => $adjType],
                    $reason,
                    $operatorId
                );
            });

            View::flash('success', "Stock for '{$product['brand']} {$product['model_name']}' adjusted successfully ({$adjType}: " . ($qtyChange > 0 ? "+{$qtyChange}" : "{$qtyChange}") . ").");
            Security::safeRedirect("/products/{$id}");

        } catch (\DomainException $e) {
            View::flash('danger', $e->getMessage());
            Security::safeRedirect("/inventory/{$id}/adjust");
        } catch (\Throwable $e) {
            View::flash('danger', 'System error during stock adjustment: ' . $e->getMessage());
            Security::safeRedirect("/inventory/{$id}/adjust");
        }
    }
}
