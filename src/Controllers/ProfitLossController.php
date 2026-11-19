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
 * Authoritative Profit & Loss (P&L) Engine Controller
 *
 * Computes consolidated business performance, operating revenues (counter sales + repair charges),
 * realized cost of goods sold (COGS), return reversals, absorbed scrap loss, channel margins,
 * daily financial trajectories, and top profit-generating products.
 *
 * NOTE: Strictly internal shop management. Zero GST sitewide.
 */
class ProfitLossController
{
    /**
     * Display the Profit & Loss Statement with date range filters, metrics, channel breakdowns, and trends.
     */
    public function index(): void
    {
        Auth::requireLogin();
        $this->requireFinancialPermission();

        $filter = $this->resolveDateFilter();
        $data = $this->calculateProfitLossData($filter['date_from'], $filter['date_to']);

        View::render('profit_loss/index', array_merge($data, [
            'pageTitle'    => 'Profit & Loss Statement (P&L)',
            'preset'       => $filter['preset'],
            'dateFrom'     => $filter['date_from'],
            'dateTo'       => $filter['date_to'],
            'periodLabel'  => $filter['label'],
        ]), 'main');
    }

    /**
     * Render a clean, printable P&L Statement.
     */
    public function print(): void
    {
        Auth::requireLogin();
        $this->requireFinancialPermission();

        $filter = $this->resolveDateFilter();
        $data = $this->calculateProfitLossData($filter['date_from'], $filter['date_to']);

        View::render('profit_loss/print', array_merge($data, [
            'pageTitle'    => 'Profit & Loss Statement — ' . $filter['label'],
            'preset'       => $filter['preset'],
            'dateFrom'     => $filter['date_from'],
            'dateTo'       => $filter['date_to'],
            'periodLabel'  => $filter['label'],
        ]), 'error'); // Clean layout without sidebar
    }

    /**
     * Resolve date filters from presets or custom datepicker.
     */
    private function resolveDateFilter(): array
    {
        $preset = trim($_GET['preset'] ?? 'this_month');
        $today = date('Y-m-d');

        switch ($preset) {
            case 'today':
                $dateFrom = $today;
                $dateTo   = $today;
                $label    = 'Today (' . date('d M Y') . ')';
                break;

            case 'yesterday':
                $yesterday = date('Y-m-d', strtotime('-1 day'));
                $dateFrom  = $yesterday;
                $dateTo    = $yesterday;
                $label     = 'Yesterday (' . date('d M Y', strtotime($yesterday)) . ')';
                break;

            case 'this_week':
                // Monday to Sunday of current week
                $monday   = date('Y-m-d', strtotime('monday this week'));
                $sunday   = date('Y-m-d', strtotime('sunday this week'));
                $dateFrom = $monday;
                $dateTo   = $sunday;
                $label    = 'This Week (' . date('d M', strtotime($monday)) . ' – ' . date('d M Y', strtotime($sunday)) . ')';
                break;

            case 'last_month':
                $firstDayLastMonth = date('Y-m-01', strtotime('first day of last month'));
                $lastDayLastMonth  = date('Y-m-t', strtotime('last day of last month'));
                $dateFrom          = $firstDayLastMonth;
                $dateTo            = $lastDayLastMonth;
                $label             = date('F Y', strtotime($firstDayLastMonth)) . ' (Last Month)';
                break;

            case 'this_year':
                $dateFrom = date('Y-01-01');
                $dateTo   = date('Y-12-31');
                $label    = 'Full Year ' . date('Y');
                break;

            case 'custom':
                $dateFrom = trim($_GET['date_from'] ?? date('Y-m-01'));
                $dateTo   = trim($_GET['date_to'] ?? $today);
                if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateFrom)) {
                    $dateFrom = date('Y-m-01');
                }
                if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateTo)) {
                    $dateTo = $today;
                }
                if ($dateFrom > $dateTo) {
                    $temp = $dateFrom;
                    $dateFrom = $dateTo;
                    $dateTo = $temp;
                }
                $label = date('d M Y', strtotime($dateFrom)) . ' – ' . date('d M Y', strtotime($dateTo));
                break;

            case 'this_month':
            default:
                $preset   = 'this_month';
                $dateFrom = date('Y-m-01');
                $dateTo   = date('Y-m-t');
                $label    = date('F Y') . ' (Month-to-Date)';
                break;
        }

        return [
            'preset'    => $preset,
            'date_from' => $dateFrom,
            'date_to'   => $dateTo,
            'label'     => $label,
        ];
    }

    /**
     * Compute authoritative Profit & Loss metrics for the specified date window.
     */
    private function calculateProfitLossData(string $dateFrom, string $dateTo): array
    {
        // ---------------------------------------------------------------------
        // 1. REVENUE (TURNOVER)
        // ---------------------------------------------------------------------

        // A. Counter Sales Turnover
        $salesSummary = Database::fetchOne("
            SELECT 
                COUNT(*) AS total_sales_count,
                COALESCE(SUM(subtotal_amount), 0.00) AS subtotal_sales,
                COALESCE(SUM(discount_amount), 0.00) AS total_discounts,
                COALESCE(SUM(final_total_amount), 0.00) AS gross_sales_revenue,
                COALESCE(SUM(total_cost_basis), 0.00) AS gross_sales_cogs,
                COALESCE(SUM(gross_profit), 0.00) AS sales_nominal_profit
            FROM sales
            WHERE sale_date BETWEEN :dfrom AND :dto AND is_voided = 0
        ", [':dfrom' => $dateFrom, ':dto' => $dateTo]);

        $salesCount       = (int)($salesSummary['total_sales_count'] ?? 0);
        $grossSalesRev    = (string)($salesSummary['gross_sales_revenue'] ?? '0.00');
        $grossSalesCogs   = (string)($salesSummary['gross_sales_cogs'] ?? '0.00');
        $nominalSaleProfit = (string)($salesSummary['sales_nominal_profit'] ?? '0.00');

        // B. Customer Service Repairs Turnover
        $repairsSummary = Database::fetchOne("
            SELECT 
                COUNT(*) AS total_repairs_count,
                COALESCE(SUM(amount_charged), 0.00) AS gross_repairs_revenue,
                COALESCE(SUM(parts_cost), 0.00) AS repairs_parts_cost,
                COALESCE(SUM(labour_amount), 0.00) AS repairs_labour_cost,
                COALESCE(SUM(total_repair_cost), 0.00) AS repairs_total_cost,
                COALESCE(SUM(net_profit_contribution), 0.00) AS repairs_net_profit
            FROM repair_records
            WHERE repair_date BETWEEN :dfrom AND :dto AND repair_type = 'CUSTOMER_SERVICE'
        ", [':dfrom' => $dateFrom, ':dto' => $dateTo]);

        $repairsCount      = (int)($repairsSummary['total_repairs_count'] ?? 0);
        $grossRepairsRev   = (string)($repairsSummary['gross_repairs_revenue'] ?? '0.00');
        $repairsPartsCost  = (string)($repairsSummary['repairs_parts_cost'] ?? '0.00');
        $repairsLabourCost = (string)($repairsSummary['repairs_labour_cost'] ?? '0.00');
        $repairsTotalCost  = (string)($repairsSummary['repairs_total_cost'] ?? '0.00');
        $repairsNetProfit  = (string)($repairsSummary['repairs_net_profit'] ?? '0.00');

        // C. Customer Returns & Reversals
        $returnsSummary = Database::fetchOne("
            SELECT 
                COUNT(*) AS total_returns_count,
                COALESCE(SUM(total_refund_amount), 0.00) AS total_refund_amount,
                COALESCE(SUM(total_restocked_cogs), 0.00) AS total_restocked_cogs,
                COALESCE(SUM(total_scrapped_cogs), 0.00) AS total_scrapped_cogs,
                COALESCE(SUM(net_profit_reversed), 0.00) AS total_profit_reversed
            FROM sale_returns
            WHERE return_date BETWEEN :dfrom AND :dto
        ", [':dfrom' => $dateFrom, ':dto' => $dateTo]);

        $returnsCount      = (int)($returnsSummary['total_returns_count'] ?? 0);
        $totalRefundAmount = (string)($returnsSummary['total_refund_amount'] ?? '0.00');
        $totalRestockedCogs = (string)($returnsSummary['total_restocked_cogs'] ?? '0.00');
        $totalScrappedCogs = (string)($returnsSummary['total_scrapped_cogs'] ?? '0.00');
        $profitReversed    = (string)($returnsSummary['total_profit_reversed'] ?? '0.00');

        // ---------------------------------------------------------------------
        // 2. CONSOLIDATED REVENUE & NET TURNOVER
        // ---------------------------------------------------------------------
        // Total Inward Turnover = Sales Revenue + Repair Revenue
        $totalGrossTurnover = Financial::add($grossSalesRev, $grossRepairsRev);

        // Net Operating Revenue = Total Inward Turnover - Customer Returns
        $netOperatingRevenue = Financial::sub($totalGrossTurnover, $totalRefundAmount);
        $netSalesRevenue     = Financial::sub($grossSalesRev, $totalRefundAmount);

        // ---------------------------------------------------------------------
        // 3. COST OF GOODS SOLD (COGS) & DIRECT COSTS
        // ---------------------------------------------------------------------
        // Net Realized Sales COGS = Gross Sales COGS - Restocked COGS
        // (Restocked items return to inventory asset, so their cost is reversed from realized expense)
        $netRealizedSalesCogs = Financial::sub($grossSalesCogs, $totalRestockedCogs);

        // Effective Sales COGS = Net Realized Sales COGS + Scrapped COGS
        // (Scrapped defective returns are retained as absorbed damage cost)
        $effectiveSalesCogs = Financial::add($netRealizedSalesCogs, $totalScrappedCogs);

        // Total Operating Cost = Effective Sales COGS + Repair Direct Costs
        $totalDirectCosts = Financial::add($effectiveSalesCogs, $repairsTotalCost);

        // ---------------------------------------------------------------------
        // 4. PROFIT & MARGIN CALCULATIONS
        // ---------------------------------------------------------------------
        // Realized Sales Gross Profit = Nominal Sale Profit - Profit Reversed on Returns
        $realizedSalesProfit = Financial::sub($nominalSaleProfit, $profitReversed);

        // Total Shop Gross Profit = Realized Sales Profit + Repair Net Profit
        $totalGrossProfit = Financial::add($realizedSalesProfit, $repairsNetProfit);

        // Overall Gross Margin (%)
        $overallMarginPct = '0.00';
        if ((float)$netOperatingRevenue > 0) {
            $overallMarginPct = Financial::div(Financial::mul($totalGrossProfit, '100'), $netOperatingRevenue);
        }

        // Sales Channel Margin (%)
        $salesMarginPct = '0.00';
        if ((float)$netSalesRevenue > 0) {
            $salesMarginPct = Financial::div(Financial::mul($realizedSalesProfit, '100'), $netSalesRevenue);
        }

        // Repair Channel Margin (%)
        $repairsMarginPct = '0.00';
        if ((float)$grossRepairsRev > 0) {
            $repairsMarginPct = Financial::div(Financial::mul($repairsNetProfit, '100'), $grossRepairsRev);
        }

        // ---------------------------------------------------------------------
        // 5. CHANNEL BREAKDOWN ANALYSIS
        // ---------------------------------------------------------------------
        // A. Handsets (New vs Used)
        $handsetsData = Database::fetchOne("
            SELECT 
                COUNT(si.id) AS units_sold,
                COALESCE(SUM(si.net_sale_price), 0.00) AS turnover,
                COALESCE(SUM(si.subtotal_cost), 0.00) AS cost_basis,
                COALESCE(SUM(si.item_profit), 0.00) AS profit
            FROM sale_items si
            JOIN sales s ON s.id = si.sale_id
            WHERE s.sale_date BETWEEN :dfrom AND :dto 
              AND s.is_voided = 0 
              AND si.item_type = 'SERIALIZED_PHONE'
        ", [':dfrom' => $dateFrom, ':dto' => $dateTo]);

        $handsetsTurnover = (string)($handsetsData['turnover'] ?? '0.00');
        $handsetsCost     = (string)($handsetsData['cost_basis'] ?? '0.00');
        $handsetsProfit   = (string)($handsetsData['profit'] ?? '0.00');
        $handsetsUnits    = (int)($handsetsData['units_sold'] ?? 0);
        $handsetsMargin   = ((float)$handsetsTurnover > 0)
            ? Financial::div(Financial::mul($handsetsProfit, '100'), $handsetsTurnover)
            : '0.00';

        // B. Accessories
        $accessoriesData = Database::fetchOne("
            SELECT 
                COALESCE(SUM(si.quantity), 0) AS units_sold,
                COALESCE(SUM(si.net_sale_price), 0.00) AS turnover,
                COALESCE(SUM(si.subtotal_cost), 0.00) AS cost_basis,
                COALESCE(SUM(si.item_profit), 0.00) AS profit
            FROM sale_items si
            JOIN sales s ON s.id = si.sale_id
            WHERE s.sale_date BETWEEN :dfrom AND :dto 
              AND s.is_voided = 0 
              AND si.item_type = 'ACCESSORY'
        ", [':dfrom' => $dateFrom, ':dto' => $dateTo]);

        $accessoriesTurnover = (string)($accessoriesData['turnover'] ?? '0.00');
        $accessoriesCost     = (string)($accessoriesData['cost_basis'] ?? '0.00');
        $accessoriesProfit   = (string)($accessoriesData['profit'] ?? '0.00');
        $accessoriesUnits    = (int)($accessoriesData['units_sold'] ?? 0);
        $accessoriesMargin   = ((float)$accessoriesTurnover > 0)
            ? Financial::div(Financial::mul($accessoriesProfit, '100'), $accessoriesTurnover)
            : '0.00';

        // ---------------------------------------------------------------------
        // 6. DAILY PERFORMANCE BREAKDOWN TABLE
        // ---------------------------------------------------------------------
        $dailyRecords = Database::fetchAll("
            SELECT 
                d.txn_date,
                COALESCE(s.sales_rev, 0.00) AS sales_revenue,
                COALESCE(s.sales_cogs, 0.00) AS sales_cogs,
                COALESCE(s.sales_profit, 0.00) AS sales_profit,
                COALESCE(s.sales_count, 0) AS sales_count,
                COALESCE(r.repair_rev, 0.00) AS repair_revenue,
                COALESCE(r.repair_cost, 0.00) AS repair_cost,
                COALESCE(r.repair_profit, 0.00) AS repair_profit,
                COALESCE(r.repair_count, 0) AS repair_count,
                COALESCE(ret.refund_amt, 0.00) AS refund_amount,
                COALESCE(ret.profit_rev, 0.00) AS profit_reversed
            FROM (
                SELECT sale_date AS txn_date FROM sales WHERE sale_date BETWEEN :dfrom1 AND :dto1 AND is_voided = 0
                UNION
                SELECT repair_date AS txn_date FROM repair_records WHERE repair_date BETWEEN :dfrom2 AND :dto2 AND repair_type = 'CUSTOMER_SERVICE'
                UNION
                SELECT return_date AS txn_date FROM sale_returns WHERE return_date BETWEEN :dfrom3 AND :dto3
            ) d
            LEFT JOIN (
                SELECT 
                    sale_date,
                    COUNT(*) AS sales_count,
                    SUM(final_total_amount) AS sales_rev,
                    SUM(total_cost_basis) AS sales_cogs,
                    SUM(gross_profit) AS sales_profit
                FROM sales
                WHERE sale_date BETWEEN :dfrom4 AND :dto4 AND is_voided = 0
                GROUP BY sale_date
            ) s ON s.sale_date = d.txn_date
            LEFT JOIN (
                SELECT 
                    repair_date,
                    COUNT(*) AS repair_count,
                    SUM(amount_charged) AS repair_rev,
                    SUM(total_repair_cost) AS repair_cost,
                    SUM(net_profit_contribution) AS repair_profit
                FROM repair_records
                WHERE repair_date BETWEEN :dfrom5 AND :dto5 AND repair_type = 'CUSTOMER_SERVICE'
                GROUP BY repair_date
            ) r ON r.repair_date = d.txn_date
            LEFT JOIN (
                SELECT 
                    return_date,
                    SUM(total_refund_amount) AS refund_amt,
                    SUM(net_profit_reversed) AS profit_rev
                FROM sale_returns
                WHERE return_date BETWEEN :dfrom6 AND :dto6
                GROUP BY return_date
            ) ret ON ret.return_date = d.txn_date
            ORDER BY d.txn_date DESC
        ", [
            ':dfrom1' => $dateFrom, ':dto1' => $dateTo,
            ':dfrom2' => $dateFrom, ':dto2' => $dateTo,
            ':dfrom3' => $dateFrom, ':dto3' => $dateTo,
            ':dfrom4' => $dateFrom, ':dto4' => $dateTo,
            ':dfrom5' => $dateFrom, ':dto5' => $dateTo,
            ':dfrom6' => $dateFrom, ':dto6' => $dateTo,
        ]);

        $dailyTimeline = [];
        foreach ($dailyRecords as $day) {
            $dayRev = Financial::sub(
                Financial::add((string)$day['sales_revenue'], (string)$day['repair_revenue']),
                (string)$day['refund_amount']
            );
            $dayCost = Financial::add((string)$day['sales_cogs'], (string)$day['repair_cost']);
            $dayProfit = Financial::sub(
                Financial::add((string)$day['sales_profit'], (string)$day['repair_profit']),
                (string)$day['profit_reversed']
            );
            $dayMargin = ((float)$dayRev > 0)
                ? Financial::div(Financial::mul($dayProfit, '100'), $dayRev)
                : '0.00';

            $dailyTimeline[] = [
                'date'           => $day['txn_date'],
                'sales_count'    => (int)$day['sales_count'],
                'repairs_count'  => (int)$day['repair_count'],
                'sales_rev'      => (string)$day['sales_revenue'],
                'repairs_rev'    => (string)$day['repair_revenue'],
                'refund_amt'     => (string)$day['refund_amount'],
                'net_revenue'    => $dayRev,
                'total_cost'     => $dayCost,
                'net_profit'     => $dayProfit,
                'margin_percent' => $dayMargin,
            ];
        }

        // ---------------------------------------------------------------------
        // 7. TOP 10 PROFIT GENERATING PRODUCTS
        // ---------------------------------------------------------------------
        $topProducts = Database::fetchAll("
            SELECT 
                p.id,
                p.brand,
                p.model_name,
                p.item_type,
                c.name AS category_name,
                SUM(si.quantity) AS total_units_sold,
                SUM(si.net_sale_price) AS total_revenue,
                SUM(si.subtotal_cost) AS total_cost,
                SUM(si.item_profit) AS total_profit
            FROM sale_items si
            JOIN sales s ON s.id = si.sale_id
            JOIN products p ON p.id = si.product_id
            JOIN categories c ON c.id = p.category_id
            WHERE s.sale_date BETWEEN :dfrom AND :dto AND s.is_voided = 0
            GROUP BY p.id, p.brand, p.model_name, p.item_type, c.name
            ORDER BY total_profit DESC
            LIMIT 10
        ", [':dfrom' => $dateFrom, ':dto' => $dateTo]);

        foreach ($topProducts as &$tp) {
            $rev = (string)$tp['total_revenue'];
            $prof = (string)$tp['total_profit'];
            $tp['margin_percent'] = ((float)$rev > 0)
                ? Financial::div(Financial::mul($prof, '100'), $rev)
                : '0.00';
        }
        unset($tp);

        return [
            // High-Level Consolidated Metrics
            'netOperatingRevenue'   => $netOperatingRevenue,
            'totalGrossTurnover'    => $totalGrossTurnover,
            'totalRefundAmount'     => $totalRefundAmount,
            'effectiveSalesCogs'    => $effectiveSalesCogs,
            'repairsTotalCost'      => $repairsTotalCost,
            'totalDirectCosts'      => $totalDirectCosts,
            'totalGrossProfit'      => $totalGrossProfit,
            'overallMarginPct'      => $overallMarginPct,

            // Sales Breakdown
            'salesCount'            => $salesCount,
            'grossSalesRev'         => $grossSalesRev,
            'netSalesRevenue'       => $netSalesRevenue,
            'grossSalesCogs'        => $grossSalesCogs,
            'netRealizedSalesCogs'  => $netRealizedSalesCogs,
            'totalRestockedCogs'    => $totalRestockedCogs,
            'totalScrappedCogs'     => $totalScrappedCogs,
            'profitReversed'        => $profitReversed,
            'realizedSalesProfit'   => $realizedSalesProfit,
            'salesMarginPct'        => $salesMarginPct,

            // Repairs Breakdown
            'repairsCount'          => $repairsCount,
            'grossRepairsRev'       => $grossRepairsRev,
            'repairsPartsCost'      => $repairsPartsCost,
            'repairsLabourCost'     => $repairsLabourCost,
            'repairsNetProfit'      => $repairsNetProfit,
            'repairsMarginPct'      => $repairsMarginPct,

            // Channel Performance
            'handsets'              => [
                'units'    => $handsetsUnits,
                'turnover' => $handsetsTurnover,
                'cost'     => $handsetsCost,
                'profit'   => $handsetsProfit,
                'margin'   => $handsetsMargin,
            ],
            'accessories'           => [
                'units'    => $accessoriesUnits,
                'turnover' => $accessoriesTurnover,
                'cost'     => $accessoriesCost,
                'profit'   => $accessoriesProfit,
                'margin'   => $accessoriesMargin,
            ],

            // Daily Trend & Top Products
            'dailyTimeline'         => $dailyTimeline,
            'topProducts'           => $topProducts,
        ];
    }

    private function requireFinancialPermission(): void
    {
        if (!Auth::isOwner() && !Authorization::hasPermission('view_financial_reports') && !Authorization::hasPermission('view_profit_margins')) {
            View::flash('danger', 'You do not have permission to access financial profit and loss reports.');
            Security::safeRedirect('/');
            exit;
        }
    }
}
