<?php
declare(strict_types=1);

namespace AMH\Controllers;

use AMH\Auth;
use AMH\Authorization;
use AMH\Database;
use AMH\Financial;
use AMH\View;
use AMH\Security;

/**
 * Executive Command Center & Operations Dashboard Controller
 * File: src/Controllers/DashboardController.php
 *
 * Implements real-time shop operational metrics, role-tailored KPI dials,
 * actionable inventory and udhar feeds, and quick workflow launchers.
 */
class DashboardController
{
    public function index(): void
    {
        Auth::requireLogin('/login');

        $userId = Auth::id();
        $userRole = Auth::role();
        $isOwner = Auth::isOwner();

        // RBAC Permissions
        $canViewFinancial = $isOwner || Authorization::hasPermission('view_financial_reports');
        $canViewCosts     = $isOwner || Authorization::hasPermission('view_purchase_costs');
        $canViewMargins   = $isOwner || Authorization::hasPermission('view_profit_margins');

        $today = date('Y-m-d');
        $currentMonth = date('Y-m');
        $thirtyDaysAgo = date('Y-m-d', strtotime('-30 days'));

        // ---------------------------------------------------------------------
        // 1. TODAY'S COUNTER OPERATIONS
        // ---------------------------------------------------------------------

        $todaySalesRow = Database::fetchOne("
            SELECT COUNT(*) AS sales_count,
                   COALESCE(SUM(final_total_amount), 0.00) AS turnover,
                   COALESCE(SUM(paid_amount), 0.00) AS paid,
                   COALESCE(SUM(gross_profit), 0.00) AS profit
            FROM sales
            WHERE sale_date = :today AND is_voided = 0
        ", [':today' => $today]) ?: ['sales_count' => 0, 'turnover' => 0, 'paid' => 0, 'profit' => 0];

        $todaySalesCount    = (int)($todaySalesRow['sales_count'] ?? 0);
        $todaySalesTurnover = (string)($todaySalesRow['turnover'] ?? '0.00');
        $todaySalesProfit   = (string)($todaySalesRow['profit'] ?? '0.00');

        // Personal sales count for currently logged-in staff member
        $staffPersonalSalesCount = (int)Database::fetchColumn("
            SELECT COUNT(*) FROM sales
            WHERE sale_date = :today AND is_voided = 0 AND created_by = :uid
        ", [':today' => $today, ':uid' => $userId]);

        // Today's completed customer repairs
        $todayRepairsRow = Database::fetchOne("
            SELECT COUNT(*) AS repair_count,
                   COALESCE(SUM(amount_charged), 0.00) AS billed,
                   COALESCE(SUM(net_profit_contribution), 0.00) AS profit
            FROM repair_records
            WHERE repair_date = :today AND repair_type = 'CUSTOMER_SERVICE'
        ", [':today' => $today]) ?: ['repair_count' => 0, 'billed' => 0, 'profit' => 0];

        $todayRepairsCount  = (int)($todayRepairsRow['repair_count'] ?? 0);
        $todayRepairsBilled = (string)($todayRepairsRow['billed'] ?? '0.00');
        $todayRepairsProfit = (string)($todayRepairsRow['profit'] ?? '0.00');

        // Today's Drawer Cash Inflow
        $todayCashInflow = (string)Database::fetchColumn("
            SELECT COALESCE(SUM(amount), 0.00) FROM payments
            WHERE payment_date = :today AND payment_mode = 'CASH'
              AND transaction_type IN ('SALE_RECEIPT', 'REPAIR_RECEIPT')
        ", [':today' => $today]);

        // Today's Drawer Cash Outflow (Purchases paid in cash + Returns refunded in cash)
        $todayCashOutflow = (string)Database::fetchColumn("
            SELECT COALESCE(SUM(amount), 0.00) FROM payments
            WHERE payment_date = :today AND payment_mode = 'CASH'
              AND transaction_type IN ('PURCHASE_PAYMENT', 'SALE_REFUND')
        ", [':today' => $today]);

        $todayNetCashInDrawer = Financial::sub($todayCashInflow, $todayCashOutflow);

        // Today's Digital Inflows (UPI + Card + Bank)
        $todayDigitalCollections = (string)Database::fetchColumn("
            SELECT COALESCE(SUM(amount), 0.00) FROM payments
            WHERE payment_date = :today AND payment_mode IN ('UPI', 'CARD', 'BANK_TRANSFER')
              AND transaction_type IN ('SALE_RECEIPT', 'REPAIR_RECEIPT')
        ", [':today' => $today]);

        // Combined daily collections
        $todayCombinedCollections = Financial::add($todayCashInflow, $todayDigitalCollections);

        // ---------------------------------------------------------------------
        // 2. MONTH-TO-DATE (MTD) FINANCIAL DIALS
        // ---------------------------------------------------------------------

        $mPrefix = $currentMonth . '%';

        $mtdSales = Database::fetchOne("
            SELECT COALESCE(SUM(final_total_amount), 0.00) AS turnover,
                   COALESCE(SUM(gross_profit), 0.00) AS profit
            FROM sales
            WHERE is_voided = 0 AND sale_date LIKE :mprefix
        ", [':mprefix' => $mPrefix]) ?: ['turnover' => '0.00', 'profit' => '0.00'];

        $mtdRepairs = Database::fetchOne("
            SELECT COALESCE(SUM(amount_charged), 0.00) AS billed,
                   COALESCE(SUM(net_profit_contribution), 0.00) AS profit
            FROM repair_records
            WHERE repair_type = 'CUSTOMER_SERVICE' AND repair_date LIKE :mprefix
        ", [':mprefix' => $mPrefix]) ?: ['billed' => '0.00', 'profit' => '0.00'];

        $mtdTurnover = Financial::add((string)$mtdSales['turnover'], (string)$mtdRepairs['billed']);
        $mtdProfit   = Financial::add((string)$mtdSales['profit'], (string)$mtdRepairs['profit']);

        // ---------------------------------------------------------------------
        // 3. SCHEMA-ADAPTIVE INVENTORY & HANDSET ASSET DIALS
        // ---------------------------------------------------------------------

        // Handsets counts
        $handsetsRow = Database::fetchOne("
            SELECT COUNT(*) AS total_in_stock,
                   SUM(CASE WHEN phone_type = 'NEW' THEN 1 ELSE 0 END) AS new_count,
                   SUM(CASE WHEN phone_type != 'NEW' THEN 1 ELSE 0 END) AS used_count,
                   COALESCE(SUM(total_cost_basis), 0.00) AS cost_basis
            FROM phones
            WHERE status = 'IN_STOCK'
        ") ?: ['total_in_stock' => 0, 'new_count' => 0, 'used_count' => 0, 'cost_basis' => '0.00'];

        $inStockHandsetsCount = (int)($handsetsRow['total_in_stock'] ?? 0);
        $newHandsetsCount     = (int)($handsetsRow['new_count'] ?? 0);
        $usedHandsetsCount    = (int)($handsetsRow['used_count'] ?? 0);
        $handsetsCostBasis    = (string)($handsetsRow['cost_basis'] ?? '0.00');

        // Dead stock alert: handsets in inventory for >30 days
        $agedHandsetsCount = (int)Database::fetchColumn("
            SELECT COUNT(*) FROM phones
            WHERE status = 'IN_STOCK' AND purchase_date <= :thirtyDaysAgo
        ", [':thirtyDaysAgo' => $thirtyDaysAgo]);

        // Resolve inventory table column names (supports both SQLite test and MySQL production schema)
        $invCols = $this->getTableColumnNames('inventory_stock');
        $qtyCol = in_array('quantity', $invCols, true) ? 'quantity' : 'current_stock';
        $costCol = in_array('avg_unit_cost', $invCols, true) ? 'avg_unit_cost' : (in_array('cost_price', $invCols, true) ? 'cost_price' : 'p.cost_price');

        $prodCols = $this->getTableColumnNames('products');
        $alertCol = in_array('min_stock_alert', $prodCols, true) ? 'p.min_stock_alert' : (in_array('reorder_level', $invCols, true) ? 'i.reorder_level' : '5');
        $pNameCol = in_array('name', $prodCols, true) ? 'p.name' : 'p.model_name';
        $pPriceCol = in_array('default_sale_price', $prodCols, true) ? 'p.default_sale_price' : 'p.selling_price';

        // Low stock and out of stock counts
        $lowStockAccessoriesCount = (int)Database::fetchColumn("
            SELECT COUNT(*) FROM inventory_stock i
            JOIN products p ON p.id = i.product_id
            WHERE i.{$qtyCol} <= {$alertCol}
        ");

        $outOfStockAccessoriesCount = (int)Database::fetchColumn("
            SELECT COUNT(*) FROM inventory_stock
            WHERE {$qtyCol} = 0
        ");

        $accessoriesCostBasis = (string)Database::fetchColumn("
            SELECT COALESCE(SUM(i.{$qtyCol} * {$costCol}), 0.00)
            FROM inventory_stock i
            JOIN products p ON p.id = i.product_id
            WHERE p.item_type != 'SERIALIZED_PHONE'
        ");

        $totalInventoryValuation = Financial::add($handsetsCostBasis, $accessoriesCostBasis);

        // ---------------------------------------------------------------------
        // 4. MARKET UDHAR (RECEIVABLES) & SUPPLIER PAYABLES (DENA)
        // ---------------------------------------------------------------------

        $salesUdhar = (string)Database::fetchColumn("
            SELECT COALESCE(SUM(due_amount), 0.00) FROM sales WHERE due_amount > 0 AND is_voided = 0
        ");
        $repairUdhar = (string)Database::fetchColumn("
            SELECT COALESCE(SUM(due_amount), 0.00) FROM repair_records WHERE due_amount > 0 AND repair_type = 'CUSTOMER_SERVICE'
        ");
        $totalMarketUdhar = Financial::add($salesUdhar, $repairUdhar);

        $vendorCols = $this->getTableColumnNames('vendors');
        if (in_array('current_balance', $vendorCols, true)) {
            $totalSupplierPayables = (string)Database::fetchColumn("
                SELECT COALESCE(SUM(current_balance), 0.00) FROM vendors WHERE current_balance > 0
            ");
        } else {
            $totalSupplierPayables = (string)Database::fetchColumn("
                SELECT COALESCE(SUM(due_amount), 0.00) FROM purchases WHERE due_amount > 0
            ");
        }

        // ---------------------------------------------------------------------
        // 5. ACTIONABLE FEEDS & STREAM DATA
        // ---------------------------------------------------------------------

        // Recent Counter Sales (Last 6 records)
        $recentSales = Database::fetchAll("
            SELECT s.id, s.sale_number, s.customer_name, s.customer_phone, s.sale_date, s.sale_time,
                   s.final_total_amount, s.paid_amount, s.due_amount, s.payment_status,
                   u.full_name AS staff_name
            FROM sales s
            JOIN users u ON u.id = s.created_by
            WHERE s.is_voided = 0
            ORDER BY s.id DESC
            LIMIT 6
        ");

        // Critical Low Stock Items Needing Procurement (Top 6)
        $lowStockItems = Database::fetchAll("
            SELECT p.id, {$pNameCol} AS name, p.brand, p.model_name, {$pPriceCol} AS selling_price,
                   i.{$qtyCol} AS current_stock, {$alertCol} AS reorder_level
            FROM inventory_stock i
            JOIN products p ON p.id = i.product_id
            WHERE i.{$qtyCol} <= {$alertCol}
            ORDER BY (CAST(i.{$qtyCol} AS SIGNED) - CAST({$alertCol} AS SIGNED)) ASC, i.{$qtyCol} ASC
            LIMIT 6
        ");

        // Aged Handsets in Stock (>30 Days) Needing Attention
        $devCols = $this->getTableColumnNames('devices');
        $hasImeiInDevices = in_array('imei_1', $devCols, true);
        $imei1Field = $hasImeiInDevices ? 'd.imei_1' : 'ph.imei_1';
        $imei2Field = $hasImeiInDevices ? 'd.imei_2' : 'ph.imei_2';

        $phoneCols = $this->getTableColumnNames('phones');
        $hasRamInPhones = in_array('ram', $phoneCols, true);
        $ramField = $hasRamInPhones ? 'ph.ram' : 'd.ram';
        $storageField = $hasRamInPhones ? 'ph.storage' : 'd.storage';
        $colorField = $hasRamInPhones ? 'ph.color' : 'd.color';

        $agedHandsets = Database::fetchAll("
            SELECT ph.id, {$imei1Field} AS imei_1, {$imei2Field} AS imei_2, ph.phone_type, ph.purchase_date,
                   ph.purchase_cost, ph.total_cost_basis, ph.expected_sale_price,
                   d.brand, d.model_name, {$ramField} AS ram, {$storageField} AS storage, {$colorField} AS color
            FROM phones ph
            JOIN devices d ON d.id = ph.device_id
            WHERE ph.status = 'IN_STOCK' AND ph.purchase_date <= :thirtyDaysAgo
            ORDER BY ph.purchase_date ASC
            LIMIT 6
        ", [':thirtyDaysAgo' => $thirtyDaysAgo]);

        $todayObj = new \DateTimeImmutable($today);
        foreach ($agedHandsets as &$ah) {
            $pDate = new \DateTimeImmutable($ah['purchase_date']);
            $ah['days_in_stock'] = $todayObj->diff($pDate)->days;
        }
        unset($ah);

        // Top Customer Udhar Balances (Top 6)
        $topUdharCustomers = Database::fetchAll("
            SELECT s.customer_name, s.customer_phone, s.due_amount, s.sale_date AS record_date, s.sale_number AS doc_number, 'SALE' AS doc_type
            FROM sales s WHERE s.due_amount > 0 AND s.is_voided = 0
            UNION ALL
            SELECT r.customer_name, r.customer_phone, r.due_amount, r.repair_date AS record_date, r.repair_number AS doc_number, 'REPAIR' AS doc_type
            FROM repair_records r WHERE r.due_amount > 0 AND r.repair_type = 'CUSTOMER_SERVICE'
            ORDER BY due_amount DESC
            LIMIT 6
        ");

        // ---------------------------------------------------------------------
        // 6. RENDER DASHBOARD
        // ---------------------------------------------------------------------

        View::render('dashboard/index', [
            'pageTitle'                  => 'Executive Command Center',
            'userRole'                   => $userRole,
            'isOwner'                    => $isOwner,
            'canViewFinancial'           => $canViewFinancial,
            'canViewCosts'               => $canViewCosts,
            'canViewMargins'             => $canViewMargins,
            'today'                      => $today,
            'currentMonth'               => $currentMonth,
            'todaySalesCount'            => $todaySalesCount,
            'todaySalesTurnover'         => $todaySalesTurnover,
            'todaySalesProfit'           => $todaySalesProfit,
            'staffPersonalSalesCount'    => $staffPersonalSalesCount,
            'todayRepairsCount'          => $todayRepairsCount,
            'todayRepairsBilled'         => $todayRepairsBilled,
            'todayRepairsProfit'         => $todayRepairsProfit,
            'todayCashInflow'            => $todayCashInflow,
            'todayCashOutflow'           => $todayCashOutflow,
            'todayNetCashInDrawer'       => $todayNetCashInDrawer,
            'todayDigitalCollections'    => $todayDigitalCollections,
            'todayCombinedCollections'   => $todayCombinedCollections,
            'mtdTurnover'                => $mtdTurnover,
            'mtdProfit'                  => $mtdProfit,
            'inStockHandsetsCount'       => $inStockHandsetsCount,
            'newHandsetsCount'           => $newHandsetsCount,
            'usedHandsetsCount'          => $usedHandsetsCount,
            'agedHandsetsCount'          => $agedHandsetsCount,
            'lowStockAccessoriesCount'   => $lowStockAccessoriesCount,
            'outOfStockAccessoriesCount' => $outOfStockAccessoriesCount,
            'totalInventoryValuation'    => $totalInventoryValuation,
            'totalMarketUdhar'           => $totalMarketUdhar,
            'totalSupplierPayables'      => $totalSupplierPayables,
            'recentSales'                => $recentSales,
            'lowStockItems'              => $lowStockItems,
            'agedHandsets'               => $agedHandsets,
            'topUdharCustomers'          => $topUdharCustomers,
        ], 'main');
    }

    /**
     * Inspect table columns dynamically (supporting SQLite and MySQL).
     */
    private function getTableColumnNames(string $tableName): array
    {
        try {
            $sqliteCols = Database::fetchAll("PRAGMA table_info({$tableName})");
            if (!empty($sqliteCols)) {
                return array_column($sqliteCols, 'name');
            }
        } catch (\Throwable $e) {
            // Not SQLite
        }

        try {
            $mysqlCols = Database::fetchAll("SHOW COLUMNS FROM `{$tableName}`");
            return array_column($mysqlCols, 'Field');
        } catch (\Throwable $e) {
            return [];
        }
    }
}
