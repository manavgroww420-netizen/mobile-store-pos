<?php
declare(strict_types=1);

namespace AMH\Controllers;

use AMH\Auth;
use AMH\Authorization;
use AMH\Database;
use AMH\Security;
use AMH\Financial;
use AMH\View;

/**
 * Management Reports & Native Export Engine Controller
 *
 * Provides operational and financial reporting hubs:
 * - Sales & Revenue Analytics
 * - Inventory Asset Valuation & Low Stock
 * - Serialized Mobile Handsets Stock & Aging
 * - Customer Credit Aging & Udhar Khata
 * - Vendor Consignments & Payables
 * - Mobile Repair Services & Refurbishment
 * - Daily Cash Drawer & Cashbook Reconciliation
 * - Pure Native CSV streaming exports (Zero external libraries)
 *
 * NOTE: Strictly internal shop management. Zero GST sitewide.
 */
class ReportController
{
    /**
     * Reports Central Hub / Directory
     */
    public function index(): void
    {
        Auth::requireLogin();
        $this->requireReportsPermission();

        $today = date('Y-m-d');
        $monthStart = date('Y-m-01');

        // Quick Overview Stats for Report Cards
        $monthSales = (float)Database::fetchColumn("
            SELECT COALESCE(SUM(final_total_amount), 0.00) FROM sales WHERE sale_date >= :mstart AND is_voided = 0
        ", [':mstart' => $monthStart]);

        $accessoryValuation = (float)Database::fetchColumn("
            SELECT COALESCE(SUM(quantity * avg_unit_cost), 0.00) FROM inventory_stock
        ");

        $handsetValuation = (float)Database::fetchColumn("
            SELECT COALESCE(SUM(total_cost_basis), 0.00) FROM phones WHERE status = 'IN_STOCK'
        ");

        $totalInventoryValuation = $accessoryValuation + $handsetValuation;

        $totalUdhar = (float)Database::fetchColumn("
            SELECT (COALESCE(s.due, 0.00) + COALESCE(r.due, 0.00)) 
            FROM (SELECT SUM(due_amount) AS due FROM sales WHERE is_voided = 0) s
            CROSS JOIN (SELECT SUM(due_amount) AS due FROM repair_records WHERE repair_type = 'CUSTOMER_SERVICE') r
        ");

        $totalSupplierDebt = (float)Database::fetchColumn("
            SELECT COALESCE(SUM(due_amount), 0.00) FROM purchases
        ");

        $todayCashIn = (float)Database::fetchColumn("
            SELECT COALESCE(SUM(amount), 0.00) FROM payments 
            WHERE payment_date = :today AND payment_mode = 'CASH' AND transaction_type IN ('SALE_RECEIPT', 'REPAIR_RECEIPT')
        ", [':today' => $today]);

        $canViewCosts   = Auth::isOwner() || Authorization::hasPermission('view_purchase_costs');
        $canViewMargins = Auth::isOwner() || Authorization::hasPermission('view_profit_margins');

        View::render('reports/index', [
            'pageTitle'               => 'Management Reports & Export Hub',
            'monthSales'              => $monthSales,
            'totalInventoryValuation' => $totalInventoryValuation,
            'totalUdhar'              => $totalUdhar,
            'totalSupplierDebt'       => $totalSupplierDebt,
            'todayCashIn'             => $todayCashIn,
            'canViewCosts'            => $canViewCosts,
            'canViewMargins'          => $canViewMargins,
        ], 'main');
    }

    // =========================================================================
    // 1. SALES & REVENUE REPORT
    // =========================================================================

    public function sales(): void
    {
        Auth::requireLogin();
        $this->requireReportsPermission();

        $dateFrom = trim($_GET['date_from'] ?? date('Y-m-01'));
        $dateTo   = trim($_GET['date_to'] ?? date('Y-m-d'));
        $mode     = strtoupper(trim($_GET['mode'] ?? 'ALL'));
        $export   = trim($_GET['export'] ?? '');

        $where = ["s.is_voided = 0", "s.sale_date BETWEEN :dfrom AND :dto"];
        $params = [':dfrom' => $dateFrom, ':dto' => $dateTo];

        if ($mode !== 'ALL') {
            $where[] = "EXISTS (SELECT 1 FROM payments pm WHERE pm.reference_table = 'sales' AND pm.reference_id = s.id AND pm.payment_mode = :pmode)";
            $params[':pmode'] = $mode;
        }

        $whereSql = implode(' AND ', $where);

        $sales = Database::fetchAll("
            SELECT s.*, u.full_name AS operator_name,
                   (SELECT COUNT(*) FROM sale_items si WHERE si.sale_id = s.id) AS items_count
            FROM sales s
            JOIN users u ON u.id = s.created_by
            WHERE {$whereSql}
            ORDER BY s.sale_date DESC, s.id DESC
        ", $params);

        // Summaries
        $totalCount    = count($sales);
        $totalGross    = '0.00';
        $totalDiscount = '0.00';
        $totalNet      = '0.00';
        $totalPaid     = '0.00';
        $totalDue      = '0.00';
        $totalCogs     = '0.00';
        $totalProfit   = '0.00';

        foreach ($sales as $s) {
            $totalGross    = Financial::add($totalGross, (string)$s['subtotal_amount']);
            $totalDiscount = Financial::add($totalDiscount, (string)$s['discount_amount']);
            $totalNet      = Financial::add($totalNet, (string)$s['final_total_amount']);
            $totalPaid     = Financial::add($totalPaid, (string)$s['paid_amount']);
            $totalDue      = Financial::add($totalDue, (string)$s['due_amount']);
            $totalCogs     = Financial::add($totalCogs, (string)$s['total_cost_basis']);
            $totalProfit   = Financial::add($totalProfit, (string)$s['gross_profit']);
        }

        $marginPct = ((float)$totalNet > 0)
            ? Financial::div(Financial::mul($totalProfit, '100'), $totalNet)
            : '0.00';

        $canViewCosts   = Auth::isOwner() || Authorization::hasPermission('view_purchase_costs');
        $canViewMargins = Auth::isOwner() || Authorization::hasPermission('view_profit_margins');

        // CSV Export Dispatch
        if ($export === 'csv') {
            $headers = ['Sale Number', 'Date', 'Time', 'Customer Name', 'Customer Phone', 'Items Count', 'Gross Subtotal (INR)', 'Discount (INR)', 'Net Total (INR)', 'Paid (INR)', 'Due Balance (INR)', 'Payment Status'];
            if ($canViewCosts) {
                $headers[] = 'Cost Basis (INR)';
            }
            if ($canViewMargins) {
                $headers[] = 'Gross Profit (INR)';
            }
            $headers[] = 'Operator';

            $rows = [];
            foreach ($sales as $s) {
                $row = [
                    $s['sale_number'],
                    $s['sale_date'],
                    $s['sale_time'],
                    $s['customer_name'] ?? 'Walk-in Customer',
                    $s['customer_phone'] ?? 'N/A',
                    $s['items_count'],
                    number_format((float)$s['subtotal_amount'], 2, '.', ''),
                    number_format((float)$s['discount_amount'], 2, '.', ''),
                    number_format((float)$s['final_total_amount'], 2, '.', ''),
                    number_format((float)$s['paid_amount'], 2, '.', ''),
                    number_format((float)$s['due_amount'], 2, '.', ''),
                    $s['payment_status'],
                ];
                if ($canViewCosts) {
                    $row[] = number_format((float)$s['total_cost_basis'], 2, '.', '');
                }
                if ($canViewMargins) {
                    $row[] = number_format((float)$s['gross_profit'], 2, '.', '');
                }
                $row[] = $s['operator_name'];
                $rows[] = $row;
            }

            self::outputCsv("sales-report-{$dateFrom}-to-{$dateTo}.csv", $headers, $rows);
            return;
        }

        View::render('reports/sales', [
            'pageTitle'      => 'Sales & Revenue Report',
            'sales'          => $sales,
            'dateFrom'       => $dateFrom,
            'dateTo'         => $dateTo,
            'mode'           => $mode,
            'totalCount'     => $totalCount,
            'totalGross'     => $totalGross,
            'totalDiscount'  => $totalDiscount,
            'totalNet'       => $totalNet,
            'totalPaid'      => $totalPaid,
            'totalDue'       => $totalDue,
            'totalCogs'      => $totalCogs,
            'totalProfit'    => $totalProfit,
            'marginPct'      => $marginPct,
            'canViewCosts'   => $canViewCosts,
            'canViewMargins' => $canViewMargins,
        ], 'main');
    }

    // =========================================================================
    // 2. INVENTORY VALUATION & LOW STOCK REPORT
    // =========================================================================

    public function inventory(): void
    {
        Auth::requireLogin();
        $this->requireReportsPermission();

        $categoryId = (int)($_GET['category_id'] ?? 0);
        $typeFilter = strtoupper(trim($_GET['type'] ?? 'ALL')); // ALL, SERIALIZED_PHONE, ACCESSORY
        $stockFilter= strtoupper(trim($_GET['stock'] ?? 'ALL')); // ALL, LOW, OUT, IN_STOCK
        $export     = trim($_GET['export'] ?? '');

        $where = ["p.is_active = 1"];
        $params = [];

        if ($categoryId > 0) {
            $where[] = "p.category_id = :cid";
            $params[':cid'] = $categoryId;
        }

        if ($typeFilter !== 'ALL') {
            $where[] = "p.item_type = :itype";
            $params[':itype'] = $typeFilter;
        }

        $whereSql = implode(' AND ', $where);

        $products = Database::fetchAll("
            SELECT p.*, c.name AS category_name,
                   COALESCE(i.quantity, 0) AS current_quantity,
                   COALESCE(i.avg_unit_cost, 0.00) AS avg_unit_cost,
                   (
                       CASE 
                           WHEN p.item_type = 'SERIALIZED_PHONE' THEN (
                               SELECT COUNT(*) FROM phones ph WHERE ph.product_id = p.id AND ph.status = 'IN_STOCK'
                           )
                           ELSE COALESCE(i.quantity, 0)
                       END
                   ) AS actual_stock_level,
                   (
                       CASE 
                           WHEN p.item_type = 'SERIALIZED_PHONE' THEN (
                               SELECT COALESCE(SUM(ph.total_cost_basis), 0.00) FROM phones ph WHERE ph.product_id = p.id AND ph.status = 'IN_STOCK'
                           )
                           ELSE (COALESCE(i.quantity, 0) * COALESCE(i.avg_unit_cost, 0.00))
                       END
                   ) AS asset_valuation
            FROM products p
            JOIN categories c ON c.id = p.category_id
            LEFT JOIN inventory_stock i ON i.product_id = p.id
            WHERE {$whereSql}
            ORDER BY c.name ASC, p.brand ASC, p.model_name ASC
        ", $params);

        // Filter by stock status if requested
        if ($stockFilter === 'LOW') {
            $products = array_filter($products, fn($p) => $p['actual_stock_level'] > 0 && $p['actual_stock_level'] <= $p['min_stock_alert']);
        } elseif ($stockFilter === 'OUT') {
            $products = array_filter($products, fn($p) => (int)$p['actual_stock_level'] <= 0);
        } elseif ($stockFilter === 'IN_STOCK') {
            $products = array_filter($products, fn($p) => (int)$p['actual_stock_level'] > 0);
        }

        $categories = Database::fetchAll("SELECT id, name FROM categories WHERE is_active = 1 ORDER BY name ASC");

        // Summaries
        $totalItemsCount     = count($products);
        $totalStockUnits     = 0;
        $totalAssetValuation = '0.00';
        $lowStockCount       = 0;

        foreach ($products as $p) {
            $totalStockUnits += (int)$p['actual_stock_level'];
            $totalAssetValuation = Financial::add($totalAssetValuation, (string)$p['asset_valuation']);
            if ((int)$p['actual_stock_level'] <= (int)$p['min_stock_alert']) {
                $lowStockCount++;
            }
        }

        $canViewCosts = Auth::isOwner() || Authorization::hasPermission('view_purchase_costs');

        // CSV Export
        if ($export === 'csv') {
            $headers = ['SKU', 'Brand', 'Model Name', 'Category', 'Item Type', 'Current Stock', 'Min Alert', 'Status'];
            if ($canViewCosts) {
                $headers[] = 'Avg Unit Cost (INR)';
                $headers[] = 'Asset Valuation (INR)';
            }

            $rows = [];
            foreach ($products as $p) {
                $status = ($p['actual_stock_level'] <= 0) ? 'OUT_OF_STOCK' : (($p['actual_stock_level'] <= $p['min_stock_alert']) ? 'LOW_STOCK' : 'IN_STOCK');
                $row = [
                    $p['sku'] ?? 'N/A',
                    $p['brand'],
                    $p['model_name'],
                    $p['category_name'],
                    $p['item_type'],
                    $p['actual_stock_level'],
                    $p['min_stock_alert'],
                    $status,
                ];
                if ($canViewCosts) {
                    $row[] = number_format((float)$p['avg_unit_cost'], 2, '.', '');
                    $row[] = number_format((float)$p['asset_valuation'], 2, '.', '');
                }
                $rows[] = $row;
            }

            self::outputCsv("inventory-valuation-" . date('Ymd') . ".csv", $headers, $rows);
            return;
        }

        View::render('reports/inventory', [
            'pageTitle'           => 'Inventory Valuation & Low Stock Report',
            'products'            => $products,
            'categories'          => $categories,
            'categoryId'          => $categoryId,
            'typeFilter'          => $typeFilter,
            'stockFilter'         => $stockFilter,
            'totalItemsCount'     => $totalItemsCount,
            'totalStockUnits'     => $totalStockUnits,
            'totalAssetValuation' => $totalAssetValuation,
            'lowStockCount'       => $lowStockCount,
            'canViewCosts'        => $canViewCosts,
        ], 'main');
    }

    // =========================================================================
    // 3. SERIALIZED MOBILE HANDSETS INVENTORY & AGING REPORT
    // =========================================================================

    public function mobiles(): void
    {
        Auth::requireLogin();
        $this->requireReportsPermission();

        $statusFilter = strtoupper(trim($_GET['status'] ?? 'IN_STOCK')); // IN_STOCK, SOLD, ALL
        $typeFilter   = strtoupper(trim($_GET['type'] ?? 'ALL')); // ALL, NEW, SECOND_HAND
        $search       = trim($_GET['q'] ?? '');
        $export       = trim($_GET['export'] ?? '');

        $where = [];
        $params = [];

        if ($statusFilter !== 'ALL') {
            $where[] = "ph.status = :stat";
            $params[':stat'] = $statusFilter;
        }

        if ($typeFilter !== 'ALL') {
            $where[] = "ph.phone_type = :ptype";
            $params[':ptype'] = $typeFilter;
        }

        if ($search !== '') {
            $where[] = "(d.imei_1 LIKE :q1 OR d.imei_2 LIKE :q2 OR pr.brand LIKE :q3 OR pr.model_name LIKE :q4)";
            $qWild = '%' . $search . '%';
            $params[':q1'] = $qWild;
            $params[':q2'] = $qWild;
            $params[':q3'] = $qWild;
            $params[':q4'] = $qWild;
        }

        $whereSql = !empty($where) ? ('WHERE ' . implode(' AND ', $where)) : '';

        $phones = Database::fetchAll("
            SELECT ph.*, d.imei_1, d.imei_2, d.serial_number,
                   pr.brand, pr.model_name,
                   v.business_name AS vendor_name
            FROM phones ph
            JOIN devices d ON d.id = ph.device_id
            JOIN products pr ON pr.id = ph.product_id
            LEFT JOIN vendors v ON v.id = ph.vendor_id
            {$whereSql}
            ORDER BY ph.purchase_date ASC, ph.id ASC
        ", $params);

        $today = new \DateTimeImmutable(date('Y-m-d'));
        $totalHandsets = count($phones);
        $totalCostBasis = '0.00';
        $newCount = 0;
        $usedCount = 0;
        $aged30Count = 0;

        foreach ($phones as &$ph) {
            $pDate = new \DateTimeImmutable($ph['purchase_date']);
            $ph['days_in_inventory'] = $today->diff($pDate)->days;

            if ($ph['phone_type'] === 'NEW') {
                $newCount++;
            } else {
                $usedCount++;
            }

            if ($ph['status'] === 'IN_STOCK' && $ph['days_in_inventory'] > 30) {
                $aged30Count++;
            }

            $totalCostBasis = Financial::add($totalCostBasis, (string)$ph['total_cost_basis']);
        }
        unset($ph);

        $canViewCosts = Auth::isOwner() || Authorization::hasPermission('view_purchase_costs');

        // CSV Export
        if ($export === 'csv') {
            $headers = ['IMEI 1', 'IMEI 2', 'Brand', 'Model', 'Type', 'Specs', 'Condition', 'Purchase Date', 'Days in Stock', 'Status'];
            if ($canViewCosts) {
                $headers[] = 'Purchase Cost (INR)';
                $headers[] = 'Repair Cost (INR)';
                $headers[] = 'Total Cost Basis (INR)';
            }
            $headers[] = 'Expected Sale Price (INR)';

            $rows = [];
            foreach ($phones as $ph) {
                $specs = trim(($ph['ram'] ?? '') . '/' . ($ph['storage'] ?? '') . ' ' . ($ph['color'] ?? ''));
                $row = [
                    $ph['imei_1'],
                    $ph['imei_2'] ?? 'N/A',
                    $ph['brand'],
                    $ph['model_name'],
                    $ph['phone_type'],
                    $specs !== '' ? $specs : 'N/A',
                    $ph['device_condition'] ?? 'Standard',
                    $ph['purchase_date'],
                    $ph['days_in_inventory'],
                    $ph['status'],
                ];
                if ($canViewCosts) {
                    $row[] = number_format((float)$ph['purchase_cost'], 2, '.', '');
                    $row[] = number_format((float)$ph['repair_cost'], 2, '.', '');
                    $row[] = number_format((float)$ph['total_cost_basis'], 2, '.', '');
                }
                $row[] = number_format((float)($ph['expected_sale_price'] ?? 0.00), 2, '.', '');
                $rows[] = $row;
            }

            self::outputCsv("mobile-handsets-stock-" . date('Ymd') . ".csv", $headers, $rows);
            return;
        }

        View::render('reports/mobiles', [
            'pageTitle'      => 'Mobile Handsets Inventory & Aging Report',
            'phones'         => $phones,
            'statusFilter'   => $statusFilter,
            'typeFilter'     => $typeFilter,
            'search'         => $search,
            'totalHandsets'  => $totalHandsets,
            'newCount'       => $newCount,
            'usedCount'      => $usedCount,
            'aged30Count'    => $aged30Count,
            'totalCostBasis' => $totalCostBasis,
            'canViewCosts'   => $canViewCosts,
        ], 'main');
    }

    // =========================================================================
    // 4. CUSTOMER CREDIT & UDHAR RECOVERY REPORT
    // =========================================================================

    public function credit(): void
    {
        Auth::requireLogin();
        $this->requireReportsPermission();

        $agingFilter = strtoupper(trim($_GET['aging'] ?? 'ALL'));
        $search      = trim($_GET['q'] ?? '');
        $export      = trim($_GET['export'] ?? '');

        // Gather all open debt rows
        $salesRows = Database::fetchAll("
            SELECT customer_phone, customer_name, due_amount, final_total_amount AS total_amount, paid_amount, sale_date AS record_date
            FROM sales WHERE due_amount > 0 AND is_voided = 0
        ");

        $repairRows = Database::fetchAll("
            SELECT customer_phone, customer_name, due_amount, amount_charged AS total_amount, paid_amount, repair_date AS record_date
            FROM repair_records WHERE due_amount > 0 AND repair_type = 'CUSTOMER_SERVICE'
        ");

        $customers = [];
        $today = new \DateTimeImmutable(date('Y-m-d'));
        $totalMarketUdhar = '0.00';
        $totalOverdue30   = '0.00';

        foreach (array_merge($salesRows, $repairRows) as $row) {
            $phone = trim($row['customer_phone'] ?? '');
            $name  = trim($row['customer_name'] ?? '');
            if ($name === '' && $phone === '') $name = 'Walk-in Customer';
            $key = $phone !== '' ? $phone : 'name:' . strtolower($name);

            if (!isset($customers[$key])) {
                $customers[$key] = [
                    'name'         => $name !== '' ? $name : 'Unnamed Customer',
                    'phone'        => $phone,
                    'total_credit' => '0.00',
                    'total_paid'   => '0.00',
                    'total_due'    => '0.00',
                    'open_bills'   => 0,
                    'oldest_date'  => $row['record_date'],
                    'days_old'     => 0,
                ];
            }

            $c = &$customers[$key];
            if ($name !== '' && $c['name'] === 'Unnamed Customer') $c['name'] = $name;
            $c['total_credit'] = Financial::add($c['total_credit'], (string)$row['total_amount']);
            $c['total_paid']   = Financial::add($c['total_paid'], (string)$row['paid_amount']);
            $c['total_due']    = Financial::add($c['total_due'], (string)$row['due_amount']);
            $c['open_bills']++;
            if (strtotime($row['record_date']) < strtotime($c['oldest_date'])) {
                $c['oldest_date'] = $row['record_date'];
            }
            unset($c);
        }

        foreach ($customers as &$c) {
            $recDate = new \DateTimeImmutable($c['oldest_date']);
            $c['days_old'] = $today->diff($recDate)->days;
            $totalMarketUdhar = Financial::add($totalMarketUdhar, $c['total_due']);
            if ($c['days_old'] > 30) {
                $totalOverdue30 = Financial::add($totalOverdue30, $c['total_due']);
            }
        }
        unset($c);

        if ($search !== '') {
            $sLower = strtolower($search);
            $customers = array_filter($customers, fn($c) => str_contains(strtolower($c['name']), $sLower) || str_contains($c['phone'], $sLower));
        }

        if ($agingFilter === '0-30') {
            $customers = array_filter($customers, fn($c) => $c['days_old'] <= 30);
        } elseif ($agingFilter === '31-60') {
            $customers = array_filter($customers, fn($c) => $c['days_old'] > 30 && $c['days_old'] <= 60);
        } elseif ($agingFilter === '60+') {
            $customers = array_filter($customers, fn($c) => $c['days_old'] > 60);
        }

        usort($customers, fn($a, $b) => (float)$b['total_due'] <=> (float)$a['total_due']);

        // CSV Export
        if ($export === 'csv') {
            $headers = ['Customer Name', 'Phone Number', 'Open Bills Count', 'Total Credit (INR)', 'Total Repaid (INR)', 'Outstanding Due (INR)', 'Oldest Bill Date', 'Aging Days'];
            $rows = [];
            foreach ($customers as $c) {
                $rows[] = [
                    $c['name'],
                    $c['phone'] !== '' ? $c['phone'] : 'N/A',
                    $c['open_bills'],
                    number_format((float)$c['total_credit'], 2, '.', ''),
                    number_format((float)$c['total_paid'], 2, '.', ''),
                    number_format((float)$c['total_due'], 2, '.', ''),
                    $c['oldest_date'],
                    $c['days_old'],
                ];
            }
            self::outputCsv("customer-credit-aging-" . date('Ymd') . ".csv", $headers, $rows);
            return;
        }

        View::render('reports/credit', [
            'pageTitle'        => 'Customer Credit Aging & Udhar Report',
            'customers'        => $customers,
            'agingFilter'      => $agingFilter,
            'search'           => $search,
            'totalMarketUdhar' => $totalMarketUdhar,
            'totalOverdue30'   => $totalOverdue30,
            'debtorsCount'     => count($customers),
        ], 'main');
    }

    // =========================================================================
    // 5. VENDOR PAYABLES & PROCUREMENT REPORT
    // =========================================================================

    public function purchases(): void
    {
        Auth::requireLogin();
        $this->requireReportsPermission();

        $vendorId = (int)($_GET['vendor_id'] ?? 0);
        $dateFrom = trim($_GET['date_from'] ?? date('Y-01-01'));
        $dateTo   = trim($_GET['date_to'] ?? date('Y-m-d'));
        $status   = strtoupper(trim($_GET['status'] ?? 'ALL'));
        $export   = trim($_GET['export'] ?? '');

        $where = ["p.purchase_date BETWEEN :dfrom AND :dto"];
        $params = [':dfrom' => $dateFrom, ':dto' => $dateTo];

        if ($vendorId > 0) {
            $where[] = "p.vendor_id = :vid";
            $params[':vid'] = $vendorId;
        }

        if ($status !== 'ALL') {
            $where[] = "p.payment_status = :stat";
            $params[':stat'] = $status;
        }

        $whereSql = implode(' AND ', $where);

        $purchases = Database::fetchAll("
            SELECT p.*, v.business_name AS vendor_name, v.phone AS vendor_phone,
                   u.full_name AS operator_name
            FROM purchases p
            JOIN vendors v ON v.id = p.vendor_id
            JOIN users u ON u.id = p.created_by
            WHERE {$whereSql}
            ORDER BY p.purchase_date DESC, p.id DESC
        ", $params);

        $vendors = Database::fetchAll("SELECT id, business_name FROM vendors ORDER BY business_name ASC");

        $totalConsignments = count($purchases);
        $totalAmount       = '0.00';
        $totalPaid         = '0.00';
        $totalDue          = '0.00';

        foreach ($purchases as $p) {
            $totalAmount = Financial::add($totalAmount, (string)$p['total_amount']);
            $totalPaid   = Financial::add($totalPaid, (string)$p['paid_amount']);
            $totalDue    = Financial::add($totalDue, (string)$p['due_amount']);
        }

        $canViewCosts = Auth::isOwner() || Authorization::hasPermission('view_purchase_costs') || Authorization::hasPermission('view_vendor_costs');

        // CSV Export
        if ($export === 'csv') {
            $headers = ['Purchase Number', 'Purchase Date', 'Vendor Name', 'Vendor Phone', 'Supplier Invoice #', 'Status'];
            if ($canViewCosts) {
                $headers[] = 'Total Amount (INR)';
                $headers[] = 'Paid Amount (INR)';
                $headers[] = 'Balance Due (INR)';
            }
            $headers[] = 'Created By';

            $rows = [];
            foreach ($purchases as $p) {
                $row = [
                    $p['purchase_number'],
                    $p['purchase_date'],
                    $p['vendor_name'],
                    $p['vendor_phone'],
                    $p['vendor_invoice_no'] ?? 'N/A',
                    $p['payment_status'],
                ];
                if ($canViewCosts) {
                    $row[] = number_format((float)$p['total_amount'], 2, '.', '');
                    $row[] = number_format((float)$p['paid_amount'], 2, '.', '');
                    $row[] = number_format((float)$p['due_amount'], 2, '.', '');
                }
                $row[] = $p['operator_name'];
                $rows[] = $row;
            }

            self::outputCsv("vendor-procurement-report-" . date('Ymd') . ".csv", $headers, $rows);
            return;
        }

        View::render('reports/purchases', [
            'pageTitle'         => 'Vendor Procurement & Payables Report',
            'purchases'         => $purchases,
            'vendors'           => $vendors,
            'vendorId'          => $vendorId,
            'dateFrom'          => $dateFrom,
            'dateTo'            => $dateTo,
            'status'            => $status,
            'totalConsignments' => $totalConsignments,
            'totalAmount'       => $totalAmount,
            'totalPaid'         => $totalPaid,
            'totalDue'          => $totalDue,
            'canViewCosts'      => $canViewCosts,
        ], 'main');
    }

    // =========================================================================
    // 6. REPAIR SERVICES & REFURBISHMENT REPORT
    // =========================================================================

    public function repairs(): void
    {
        Auth::requireLogin();
        $this->requireReportsPermission();

        $dateFrom   = trim($_GET['date_from'] ?? date('Y-m-01'));
        $dateTo     = trim($_GET['date_to'] ?? date('Y-m-d'));
        $typeFilter = strtoupper(trim($_GET['type'] ?? 'ALL')); // ALL, CUSTOMER_SERVICE, REFURBISHMENT
        $export     = trim($_GET['export'] ?? '');

        $where = ["r.repair_date BETWEEN :dfrom AND :dto"];
        $params = [':dfrom' => $dateFrom, ':dto' => $dateTo];

        if ($typeFilter !== 'ALL') {
            $where[] = "r.repair_type = :rtype";
            $params[':rtype'] = $typeFilter;
        }

        $whereSql = implode(' AND ', $where);

        $repairs = Database::fetchAll("
            SELECT r.*, u.full_name AS operator_name,
                   d.imei_1, pr.brand, pr.model_name AS phone_model_name
            FROM repair_records r
            JOIN users u ON u.id = r.created_by
            LEFT JOIN phones ph ON ph.id = r.phone_id
            LEFT JOIN devices d ON d.id = ph.device_id
            LEFT JOIN products pr ON pr.id = ph.product_id
            WHERE {$whereSql}
            ORDER BY r.repair_date DESC, r.id DESC
        ", $params);

        $totalJobs      = count($repairs);
        $totalBilled    = '0.00';
        $totalPartsCost = '0.00';
        $totalLabour    = '0.00';
        $totalCosts     = '0.00';
        $totalProfit    = '0.00';

        foreach ($repairs as $r) {
            $totalBilled    = Financial::add($totalBilled, (string)$r['amount_charged']);
            $totalPartsCost = Financial::add($totalPartsCost, (string)$r['parts_cost']);
            $totalLabour    = Financial::add($totalLabour, (string)$r['labour_amount']);
            $totalCosts     = Financial::add($totalCosts, (string)$r['total_repair_cost']);
            $totalProfit    = Financial::add($totalProfit, (string)$r['net_profit_contribution']);
        }

        $canViewCosts   = Auth::isOwner() || Authorization::hasPermission('view_purchase_costs');
        $canViewMargins = Auth::isOwner() || Authorization::hasPermission('view_profit_margins');

        // CSV Export
        if ($export === 'csv') {
            $headers = ['Repair Number', 'Date', 'Type', 'Device Model', 'Customer', 'Description', 'Amount Charged (INR)'];
            if ($canViewCosts) {
                $headers[] = 'Parts Cost (INR)';
                $headers[] = 'Labour Amount (INR)';
                $headers[] = 'Total Cost (INR)';
            }
            if ($canViewMargins) {
                $headers[] = 'Net Profit (INR)';
            }
            $headers[] = 'Payment Status';
            $headers[] = 'Technician / Staff';

            $rows = [];
            foreach ($repairs as $r) {
                $row = [
                    $r['repair_number'],
                    $r['repair_date'],
                    $r['repair_type'],
                    $r['device_model'] ?? ($r['brand'] . ' ' . $r['phone_model_name']),
                    $r['customer_name'] ?? 'Shop Handset',
                    $r['repair_description'],
                    number_format((float)$r['amount_charged'], 2, '.', ''),
                ];
                if ($canViewCosts) {
                    $row[] = number_format((float)$r['parts_cost'], 2, '.', '');
                    $row[] = number_format((float)$r['labour_amount'], 2, '.', '');
                    $row[] = number_format((float)$r['total_repair_cost'], 2, '.', '');
                }
                if ($canViewMargins) {
                    $row[] = number_format((float)$r['net_profit_contribution'], 2, '.', '');
                }
                $row[] = $r['payment_status'];
                $row[] = $r['operator_name'];
                $rows[] = $row;
            }

            self::outputCsv("repair-services-report-{$dateFrom}-to-{$dateTo}.csv", $headers, $rows);
            return;
        }

        View::render('reports/repairs', [
            'pageTitle'      => 'Mobile Repair Services & Refurbishment Report',
            'repairs'        => $repairs,
            'dateFrom'       => $dateFrom,
            'dateTo'         => $dateTo,
            'typeFilter'     => $typeFilter,
            'totalJobs'      => $totalJobs,
            'totalBilled'    => $totalBilled,
            'totalPartsCost' => $totalPartsCost,
            'totalLabour'    => $totalLabour,
            'totalCosts'     => $totalCosts,
            'totalProfit'    => $totalProfit,
            'canViewCosts'   => $canViewCosts,
            'canViewMargins' => $canViewMargins,
        ], 'main');
    }

    // =========================================================================
    // 7. DAILY CASH RECONCILIATION & CASH DRAWER CLOSE REPORT
    // =========================================================================

    public function dailyClose(): void
    {
        Auth::requireLogin();
        $this->requireReportsPermission();

        $selectedDate = trim($_GET['date'] ?? date('Y-m-d'));
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $selectedDate)) {
            $selectedDate = date('Y-m-d');
        }

        $export = trim($_GET['export'] ?? '');

        // Fetch all payment movements for that day
        $payments = Database::fetchAll("
            SELECT p.*, u.full_name AS staff_name,
                   s.sale_number, r.repair_number, pur.purchase_number, sr.return_number,
                   v.business_name AS vendor_name,
                   s.customer_name AS sale_customer,
                   r.customer_name AS repair_customer
            FROM payments p
            JOIN users u ON u.id = p.received_by
            LEFT JOIN sales s ON (s.id = p.reference_id AND p.reference_table = 'sales')
            LEFT JOIN repair_records r ON (r.id = p.reference_id AND p.reference_table = 'repair_records')
            LEFT JOIN purchases pur ON (pur.id = p.reference_id AND p.reference_table = 'purchases')
            LEFT JOIN sale_returns sr ON (sr.id = p.reference_id AND p.reference_table = 'sale_returns')
            LEFT JOIN vendors v ON v.id = p.vendor_id
            WHERE p.payment_date = :pdate
            ORDER BY p.id ASC
        ", [':pdate' => $selectedDate]);

        // Inflow / Outflow Breakdown by Mode
        $cashInflow  = '0.00';
        $cashOutflow = '0.00';
        $upiTotal    = '0.00';
        $cardTotal   = '0.00';
        $bankTotal   = '0.00';

        $cashSalesCount = 0;
        $cashRepairsCount = 0;

        foreach ($payments as $p) {
            $amt = (string)$p['amount'];
            $isCash = ($p['payment_mode'] === 'CASH');

            if ($p['transaction_type'] === 'SALE_RECEIPT') {
                if ($isCash) {
                    $cashInflow = Financial::add($cashInflow, $amt);
                    $cashSalesCount++;
                }
            } elseif ($p['transaction_type'] === 'REPAIR_RECEIPT') {
                if ($isCash) {
                    $cashInflow = Financial::add($cashInflow, $amt);
                    $cashRepairsCount++;
                }
            } elseif ($p['transaction_type'] === 'PURCHASE_PAYMENT') {
                if ($isCash) {
                    $cashOutflow = Financial::add($cashOutflow, $amt);
                }
            } elseif ($p['transaction_type'] === 'SALE_REFUND') {
                if ($isCash) {
                    $cashOutflow = Financial::add($cashOutflow, $amt);
                }
            }

            // Digital Inflows
            if ($p['payment_mode'] === 'UPI' && in_array($p['transaction_type'], ['SALE_RECEIPT', 'REPAIR_RECEIPT'], true)) {
                $upiTotal = Financial::add($upiTotal, $amt);
            } elseif ($p['payment_mode'] === 'CARD' && in_array($p['transaction_type'], ['SALE_RECEIPT', 'REPAIR_RECEIPT'], true)) {
                $cardTotal = Financial::add($cardTotal, $amt);
            } elseif ($p['payment_mode'] === 'BANK_TRANSFER' && in_array($p['transaction_type'], ['SALE_RECEIPT', 'REPAIR_RECEIPT'], true)) {
                $bankTotal = Financial::add($bankTotal, $amt);
            }
        }

        $netCashInDrawer = Financial::sub($cashInflow, $cashOutflow);
        $totalDigitalInflow = Financial::add(Financial::add($upiTotal, $cardTotal), $bankTotal);
        $totalDailyCollections = Financial::add($cashInflow, $totalDigitalInflow);

        // CSV Export
        if ($export === 'csv') {
            $headers = ['Voucher Number', 'Date', 'Transaction Type', 'Party', 'Mode', 'Reference #', 'Inflow/Outflow', 'Amount (INR)', 'Staff Member'];
            $rows = [];
            foreach ($payments as $p) {
                $isInflow = in_array($p['transaction_type'], ['SALE_RECEIPT', 'REPAIR_RECEIPT'], true);
                $party = $p['sale_customer'] ?? ($p['repair_customer'] ?? ($p['vendor_name'] ?? 'Walk-in'));
                $refNo = $p['sale_number'] ?? ($p['repair_number'] ?? ($p['purchase_number'] ?? ($p['return_number'] ?? 'N/A')));
                $rows[] = [
                    $p['payment_number'],
                    $p['payment_date'],
                    $p['transaction_type'],
                    $party,
                    $p['payment_mode'],
                    $refNo,
                    $isInflow ? 'INFLOW (+)' : 'OUTFLOW (-)',
                    number_format((float)$p['amount'], 2, '.', ''),
                    $p['staff_name'],
                ];
            }
            self::outputCsv("daily-cash-reconciliation-{$selectedDate}.csv", $headers, $rows);
            return;
        }

        View::render('reports/daily_close', [
            'pageTitle'              => "Daily Cash Reconciliation — " . date('d M Y', strtotime($selectedDate)),
            'selectedDate'           => $selectedDate,
            'payments'               => $payments,
            'cashInflow'             => $cashInflow,
            'cashOutflow'            => $cashOutflow,
            'netCashInDrawer'        => $netCashInDrawer,
            'upiTotal'               => $upiTotal,
            'cardTotal'              => $cardTotal,
            'bankTotal'              => $bankTotal,
            'totalDigitalInflow'     => $totalDigitalInflow,
            'totalDailyCollections'  => $totalDailyCollections,
            'cashSalesCount'         => $cashSalesCount,
            'cashRepairsCount'       => $cashRepairsCount,
        ], 'main');
    }

    // =========================================================================
    // NATIVE CSV STREAMING HELPER
    // =========================================================================

    /**
     * Stream clean CSV to browser output with UTF-8 BOM.
     */
    public static function outputCsv(string $filename, array $headers, array $rows): void
    {
        if (ob_get_level() > 0) {
            ob_end_clean();
        }

        header('Content-Type: text/csv; charset=UTF-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Pragma: no-cache');
        header('Expires: 0');

        $fp = fopen('php://output', 'w');
        // UTF-8 BOM for Excel
        fwrite($fp, "\xEF\xBB\xBF");

        fputcsv($fp, $headers);
        foreach ($rows as $row) {
            fputcsv($fp, $row);
        }

        fclose($fp);
        exit;
    }

    private function requireReportsPermission(): void
    {
        if (!Auth::isOwner() && !Authorization::hasPermission('view_financial_reports')) {
            View::flash('danger', 'You do not have permission to access management reports.');
            Security::safeRedirect('/');
            exit;
        }
    }
}
