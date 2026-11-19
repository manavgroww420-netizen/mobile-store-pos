<?php
/**
 * Profit & Loss Statement (P&L) Dashboard
 * File: views/profit_loss/index.php
 */
?>
<div class="page-header d-flex flex-wrap justify-content-between align-items-center mb-4 gap-2">
    <div>
        <h1 class="h3 mb-1">Profit &amp; Loss Statement <span class="badge bg-primary font-weight-normal ms-2">P&amp;L</span></h1>
        <p class="text-muted mb-0">Consolidated financial performance, realized cost basis, and channel profit contributions.</p>
    </div>
    <div class="d-flex gap-2">
        <a href="/profit-loss/print?preset=<?= urlencode($preset) ?>&date_from=<?= urlencode($dateFrom) ?>&date_to=<?= urlencode($dateTo) ?>" target="_blank" class="btn btn-outline-dark btn-sm">
            <span>&#128438;</span> Print Statement
        </a>
    </div>
</div>

<!-- Preset Filter Bar -->
<div class="card shadow-sm mb-4">
    <div class="card-body py-3">
        <div class="d-flex flex-wrap justify-content-between align-items-center gap-2">
            <!-- Preset Buttons -->
            <div class="btn-group btn-group-sm flex-wrap" role="group">
                <a href="/profit-loss?preset=today" class="btn <?= $preset === 'today' ? 'btn-primary' : 'btn-outline-secondary' ?>">Today</a>
                <a href="/profit-loss?preset=yesterday" class="btn <?= $preset === 'yesterday' ? 'btn-primary' : 'btn-outline-secondary' ?>">Yesterday</a>
                <a href="/profit-loss?preset=this_week" class="btn <?= $preset === 'this_week' ? 'btn-primary' : 'btn-outline-secondary' ?>">This Week</a>
                <a href="/profit-loss?preset=this_month" class="btn <?= $preset === 'this_month' ? 'btn-primary' : 'btn-outline-secondary' ?>">This Month</a>
                <a href="/profit-loss?preset=last_month" class="btn <?= $preset === 'last_month' ? 'btn-primary' : 'btn-outline-secondary' ?>">Last Month</a>
                <a href="/profit-loss?preset=this_year" class="btn <?= $preset === 'this_year' ? 'btn-primary' : 'btn-outline-secondary' ?>">This Year</a>
            </div>

            <!-- Custom Date Range Form -->
            <form method="GET" action="/profit-loss" class="d-flex align-items-center gap-1">
                <input type="hidden" name="preset" value="custom">
                <input type="date" name="date_from" class="form-control form-control-sm" value="<?= htmlspecialchars($dateFrom) ?>" title="Start Date">
                <span class="text-muted small">to</span>
                <input type="date" name="date_to" class="form-control form-control-sm" value="<?= htmlspecialchars($dateTo) ?>" title="End Date">
                <button type="submit" class="btn btn-sm btn-dark">Apply</button>
            </form>
        </div>
        <div class="text-xs text-muted mt-2">
            <strong>Active Statement Window:</strong> <?= htmlspecialchars($periodLabel) ?> (<?= htmlspecialchars($dateFrom) ?> to <?= htmlspecialchars($dateTo) ?>)
        </div>
    </div>
</div>

<!-- 4 Key Executive Performance Cards -->
<div class="row g-3 mb-4">
    <div class="col-md-3">
        <div class="card stat-card border-left-primary shadow-sm h-100">
            <div class="card-body">
                <div class="text-xs font-weight-bold text-primary text-uppercase mb-1">Net Operating Revenue</div>
                <div class="h3 mb-0 font-weight-bold text-gray-800">₹ <?= number_format((float)$netOperatingRevenue, 2) ?></div>
                <div class="small text-muted mt-1">Turnover net of customer returns</div>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card stat-card border-left-secondary shadow-sm h-100">
            <div class="card-body">
                <div class="text-xs font-weight-bold text-secondary text-uppercase mb-1">Total Direct Costs (COGS)</div>
                <div class="h3 mb-0 font-weight-bold text-gray-800">₹ <?= number_format((float)$totalDirectCosts, 2) ?></div>
                <div class="small text-muted mt-1">Realized stock COGS + repair expenses</div>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card stat-card border-left-success shadow-sm h-100">
            <div class="card-body">
                <div class="text-xs font-weight-bold text-success text-uppercase mb-1">Total Gross Profit</div>
                <div class="h3 mb-0 font-weight-bold <?= (float)$totalGrossProfit >= 0 ? 'text-success' : 'text-danger' ?>">
                    ₹ <?= number_format((float)$totalGrossProfit, 2) ?>
                </div>
                <div class="small text-muted mt-1">Sales gross margin + repair margins</div>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card stat-card border-left-dark shadow-sm h-100">
            <div class="card-body">
                <div class="text-xs font-weight-bold text-dark text-uppercase mb-1">Gross Margin (%)</div>
                <div class="h3 mb-0 font-weight-bold text-dark"><?= number_format((float)$overallMarginPct, 2) ?>%</div>
                <div class="small text-muted mt-1">Overall return on operating turnover</div>
            </div>
        </div>
    </div>
</div>

<!-- Channel Performance Stream Matrix -->
<div class="row g-3 mb-4">
    <!-- Channel 1: Mobile Handsets -->
    <div class="col-md-4">
        <div class="card shadow-sm h-100 border-top border-4 border-primary">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center mb-2">
                    <h6 class="font-weight-bold text-dark mb-0">&#128241; Serialized Mobiles</h6>
                    <span class="badge bg-light text-dark border"><?= $handsets['units'] ?> units sold</span>
                </div>
                <div class="d-flex justify-content-between py-1 border-bottom small">
                    <span class="text-muted">Turnover Revenue:</span>
                    <strong class="font-monospace">₹ <?= number_format((float)$handsets['turnover'], 2) ?></strong>
                </div>
                <div class="d-flex justify-content-between py-1 border-bottom small">
                    <span class="text-muted">Cost Basis (COGS):</span>
                    <span class="font-monospace text-muted">₹ <?= number_format((float)$handsets['cost'], 2) ?></span>
                </div>
                <div class="d-flex justify-content-between py-1 border-bottom small">
                    <span class="text-muted">Profit Contribution:</span>
                    <strong class="font-monospace text-success">₹ <?= number_format((float)$handsets['profit'], 2) ?></strong>
                </div>
                <div class="d-flex justify-content-between pt-2 small">
                    <span class="text-muted">Channel Margin:</span>
                    <span class="badge bg-primary font-monospace"><?= number_format((float)$handsets['margin'], 2) ?>%</span>
                </div>
            </div>
        </div>
    </div>

    <!-- Channel 2: Accessories & Spares -->
    <div class="col-md-4">
        <div class="card shadow-sm h-100 border-top border-4 border-info">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center mb-2">
                    <h6 class="font-weight-bold text-dark mb-0">&#128230; Accessories &amp; Stock Parts</h6>
                    <span class="badge bg-light text-dark border"><?= $accessories['units'] ?> items sold</span>
                </div>
                <div class="d-flex justify-content-between py-1 border-bottom small">
                    <span class="text-muted">Turnover Revenue:</span>
                    <strong class="font-monospace">₹ <?= number_format((float)$accessories['turnover'], 2) ?></strong>
                </div>
                <div class="d-flex justify-content-between py-1 border-bottom small">
                    <span class="text-muted">Cost Basis (COGS):</span>
                    <span class="font-monospace text-muted">₹ <?= number_format((float)$accessories['cost'], 2) ?></span>
                </div>
                <div class="d-flex justify-content-between py-1 border-bottom small">
                    <span class="text-muted">Profit Contribution:</span>
                    <strong class="font-monospace text-success">₹ <?= number_format((float)$accessories['profit'], 2) ?></strong>
                </div>
                <div class="d-flex justify-content-between pt-2 small">
                    <span class="text-muted">Channel Margin:</span>
                    <span class="badge bg-info text-dark font-monospace"><?= number_format((float)$accessories['margin'], 2) ?>%</span>
                </div>
            </div>
        </div>
    </div>

    <!-- Channel 3: Customer Repair Services -->
    <div class="col-md-4">
        <div class="card shadow-sm h-100 border-top border-4 border-warning">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center mb-2">
                    <h6 class="font-weight-bold text-dark mb-0">&#128295; Mobile Repair Services</h6>
                    <span class="badge bg-light text-dark border"><?= $repairsCount ?> jobs completed</span>
                </div>
                <div class="d-flex justify-content-between py-1 border-bottom small">
                    <span class="text-muted">Repair Charges Billed:</span>
                    <strong class="font-monospace">₹ <?= number_format((float)$grossRepairsRev, 2) ?></strong>
                </div>
                <div class="d-flex justify-content-between py-1 border-bottom small">
                    <span class="text-muted">Direct Parts &amp; Labour:</span>
                    <span class="font-monospace text-muted">₹ <?= number_format((float)$repairsTotalCost, 2) ?></span>
                </div>
                <div class="d-flex justify-content-between py-1 border-bottom small">
                    <span class="text-muted">Net Profit Contribution:</span>
                    <strong class="font-monospace text-success">₹ <?= number_format((float)$repairsNetProfit, 2) ?></strong>
                </div>
                <div class="d-flex justify-content-between pt-2 small">
                    <span class="text-muted">Channel Margin:</span>
                    <span class="badge bg-warning text-dark font-monospace"><?= number_format((float)$repairsMarginPct, 2) ?>%</span>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Detailed Standard Accounting P&L Statement -->
<div class="card shadow-sm mb-4">
    <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
        <h6 class="m-0 font-weight-bold text-dark">Authoritative Financial Statement (<?= htmlspecialchars($periodLabel) ?>)</h6>
        <span class="badge bg-light text-dark border">Zero GST Internal Shop Accounting</span>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table align-middle mb-0 font-monospace-rows">
                <thead class="table-light text-xs text-uppercase text-muted">
                    <tr>
                        <th style="width: 60%;">Financial Item / Account Line</th>
                        <th style="width: 20%;" class="text-end">Subtotal (₹)</th>
                        <th style="width: 20%;" class="text-end font-weight-bold">Amount (₹)</th>
                    </tr>
                </thead>
                <tbody>
                    <!-- 1. OPERATING REVENUE -->
                    <tr class="table-light">
                        <td colspan="3" class="font-weight-bold text-uppercase text-dark text-xs">
                            1. Operating Revenue &amp; Inward Turnover
                        </td>
                    </tr>
                    <tr>
                        <td class="ps-4">Gross Counter Sales (<?= $salesCount ?> transaction records)</td>
                        <td class="text-end font-monospace">₹ <?= number_format((float)$grossSalesRev, 2) ?></td>
                        <td></td>
                    </tr>
                    <tr>
                        <td class="ps-4">Customer Repair Service Charges (<?= $repairsCount ?> repair jobs)</td>
                        <td class="text-end font-monospace">₹ <?= number_format((float)$grossRepairsRev, 2) ?></td>
                        <td></td>
                    </tr>
                    <tr class="fw-bold bg-light">
                        <td class="ps-4">Total Gross Inward Turnover</td>
                        <td></td>
                        <td class="text-end font-monospace font-weight-bold">₹ <?= number_format((float)$totalGrossTurnover, 2) ?></td>
                    </tr>
                    <tr>
                        <td class="ps-4 text-danger">Less: Customer Sales Returns &amp; Cash Refunds (<?= $returnsSummary['total_returns_count'] ?? 0 ?> return episodes)</td>
                        <td class="text-end font-monospace text-danger">- ₹ <?= number_format((float)$totalRefundAmount, 2) ?></td>
                        <td></td>
                    </tr>
                    <tr class="table-primary fw-bold">
                        <td class="ps-3 text-primary font-weight-bold">NET OPERATING REVENUE (TURNOVER)</td>
                        <td></td>
                        <td class="text-end font-monospace text-primary font-weight-bold" style="font-size: 1.05rem;">
                            ₹ <?= number_format((float)$netOperatingRevenue, 2) ?>
                        </td>
                    </tr>

                    <!-- 2. DIRECT COSTS / COGS -->
                    <tr class="table-light">
                        <td colspan="3" class="font-weight-bold text-uppercase text-dark text-xs pt-3">
                            2. Realized Cost of Goods Sold (COGS) &amp; Operating Expenses
                        </td>
                    </tr>
                    <tr>
                        <td class="ps-4">Gross Realized Sales Inventory Cost Basis</td>
                        <td class="text-end font-monospace">₹ <?= number_format((float)$grossSalesCogs, 2) ?></td>
                        <td></td>
                    </tr>
                    <tr>
                        <td class="ps-4 text-success">Less: Restocked Inventory Cost Reversals (returned to assets)</td>
                        <td class="text-end font-monospace text-success">- ₹ <?= number_format((float)$totalRestockedCogs, 2) ?></td>
                        <td></td>
                    </tr>
                    <tr>
                        <td class="ps-4 text-danger">Plus: Scrapped / Defective Customer Returns (absorbed loss)</td>
                        <td class="text-end font-monospace text-danger">+ ₹ <?= number_format((float)$totalScrappedCogs, 2) ?></td>
                        <td></td>
                    </tr>
                    <tr class="bg-light fw-bold">
                        <td class="ps-4">Net Realized Counter Sales COGS</td>
                        <td></td>
                        <td class="text-end font-monospace font-weight-bold">₹ <?= number_format((float)$effectiveSalesCogs, 2) ?></td>
                    </tr>
                    <tr>
                        <td class="ps-4">Customer Repair Parts Incurred (In-house Stock &amp; External Procurements)</td>
                        <td class="text-end font-monospace">₹ <?= number_format((float)$repairsPartsCost, 2) ?></td>
                        <td></td>
                    </tr>
                    <tr>
                        <td class="ps-4">Customer Repair Labour &amp; Technician Service Expenses</td>
                        <td class="text-end font-monospace">₹ <?= number_format((float)$repairsLabourCost, 2) ?></td>
                        <td></td>
                    </tr>
                    <tr class="bg-light fw-bold">
                        <td class="ps-4">Total Customer Repair Direct Costs</td>
                        <td></td>
                        <td class="text-end font-monospace font-weight-bold">₹ <?= number_format((float)$repairsTotalCost, 2) ?></td>
                    </tr>
                    <tr class="table-secondary fw-bold">
                        <td class="ps-3 font-weight-bold text-dark">TOTAL DIRECT OPERATING COSTS (COGS)</td>
                        <td></td>
                        <td class="text-end font-monospace font-weight-bold" style="font-size: 1.05rem;">
                            ₹ <?= number_format((float)$totalDirectCosts, 2) ?>
                        </td>
                    </tr>

                    <!-- 3. PROFIT SUMMARY -->
                    <tr class="table-light">
                        <td colspan="3" class="font-weight-bold text-uppercase text-dark text-xs pt-3">
                            3. Consolidated Profit &amp; Margin Summary
                        </td>
                    </tr>
                    <tr>
                        <td class="ps-4">Realized Counter Sales Gross Margin (Net Sales Rev - Sales COGS - Profit Reversed)</td>
                        <td class="text-end font-monospace">₹ <?= number_format((float)$realizedSalesProfit, 2) ?></td>
                        <td></td>
                    </tr>
                    <tr>
                        <td class="ps-4">Customer Repair Service Contribution (Repair Rev - Repair Costs)</td>
                        <td class="text-end font-monospace">₹ <?= number_format((float)$repairsNetProfit, 2) ?></td>
                        <td></td>
                    </tr>
                    <tr class="table-success fw-bold" style="font-size: 1.15rem;">
                        <td class="ps-3 text-success font-weight-bold">CONSOLIDATED SHOP GROSS PROFIT</td>
                        <td></td>
                        <td class="text-end font-monospace text-success font-weight-bold">
                            ₹ <?= number_format((float)$totalGrossProfit, 2) ?>
                        </td>
                    </tr>
                    <tr class="table-light">
                        <td class="ps-3 font-weight-bold text-muted">Overall Operating Gross Margin Percentage</td>
                        <td></td>
                        <td class="text-end font-monospace font-weight-bold text-dark">
                            <?= number_format((float)$overallMarginPct, 2) ?>%
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Daily Performance Breakdown Table -->
<div class="card shadow-sm mb-4">
    <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
        <h6 class="m-0 font-weight-bold text-dark">Daily Financial Trajectory (<?= count($dailyTimeline) ?> active days)</h6>
        <span class="text-xs text-muted">Chronological performance breakdown</span>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover table-striped align-middle mb-0 font-monospace-rows">
                <thead class="table-light text-xs text-uppercase text-muted">
                    <tr>
                        <th style="width: 14%;">Date</th>
                        <th style="width: 14%;" class="text-center">Activity</th>
                        <th style="width: 18%;" class="text-end">Net Revenue</th>
                        <th style="width: 18%;" class="text-end">COGS / Cost</th>
                        <th style="width: 18%;" class="text-end">Net Profit</th>
                        <th style="width: 18%;" class="text-end">Margin %</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($dailyTimeline)): ?>
                        <tr>
                            <td colspan="6" class="text-center py-4 text-muted">No transactions recorded in the selected date window.</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($dailyTimeline as $row): ?>
                            <tr>
                                <td class="font-monospace small font-weight-bold"><?= htmlspecialchars($row['date']) ?></td>
                                <td class="text-center small">
                                    <span class="badge bg-light text-dark border"><?= $row['sales_count'] ?> sales</span>
                                    <?php if ($row['repairs_count'] > 0): ?>
                                        <span class="badge bg-light text-warning border"><?= $row['repairs_count'] ?> repairs</span>
                                    <?php endif; ?>
                                </td>
                                <td class="text-end font-monospace">₹ <?= number_format((float)$row['net_revenue'], 2) ?></td>
                                <td class="text-end font-monospace text-muted">₹ <?= number_format((float)$row['total_cost'], 2) ?></td>
                                <td class="text-end font-monospace font-weight-bold <?= (float)$row['net_profit'] >= 0 ? 'text-success' : 'text-danger' ?>">
                                    ₹ <?= number_format((float)$row['net_profit'], 2) ?>
                                </td>
                                <td class="text-end font-monospace">
                                    <span class="badge <?= (float)$row['margin_percent'] >= 15 ? 'bg-success' : 'bg-secondary' ?>">
                                        <?= number_format((float)$row['margin_percent'], 2) ?>%
                                    </span>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Top 10 Profit Contributing Products -->
<div class="card shadow-sm mb-4">
    <div class="card-header bg-white py-3">
        <h6 class="m-0 font-weight-bold text-dark">Top 10 Profit Contributing Products &amp; Handsets</h6>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover table-striped align-middle mb-0">
                <thead class="table-light text-xs text-uppercase text-muted">
                    <tr>
                        <th>Product / Handset Model</th>
                        <th>Category</th>
                        <th class="text-center">Units Sold</th>
                        <th class="text-end">Total Revenue</th>
                        <th class="text-end">Total Cost</th>
                        <th class="text-end font-weight-bold text-success">Total Profit</th>
                        <th class="text-end">Margin %</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($topProducts)): ?>
                        <tr>
                            <td colspan="7" class="text-center py-4 text-muted">No sales items recorded for this date window.</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($topProducts as $tp): ?>
                            <tr>
                                <td>
                                    <strong><?= htmlspecialchars($tp['brand'] . ' ' . $tp['model_name']) ?></strong>
                                    <span class="badge <?= $tp['item_type'] === 'SERIALIZED_PHONE' ? 'bg-primary' : 'bg-info text-dark' ?> ms-1" style="font-size: 0.7rem;">
                                        <?= $tp['item_type'] === 'SERIALIZED_PHONE' ? 'Handset' : 'Accessory' ?>
                                    </span>
                                </td>
                                <td class="small text-muted"><?= htmlspecialchars($tp['category_name']) ?></td>
                                <td class="text-center font-monospace"><?= $tp['total_units_sold'] ?></td>
                                <td class="text-end font-monospace">₹ <?= number_format((float)$tp['total_revenue'], 2) ?></td>
                                <td class="text-end font-monospace text-muted">₹ <?= number_format((float)$tp['total_cost'], 2) ?></td>
                                <td class="text-end font-monospace font-weight-bold text-success">₹ <?= number_format((float)$tp['total_profit'], 2) ?></td>
                                <td class="text-end font-monospace">
                                    <span class="badge bg-light text-dark border"><?= number_format((float)$tp['margin_percent'], 2) ?>%</span>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
