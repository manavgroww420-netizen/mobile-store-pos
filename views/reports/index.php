<?php
/**
 * Management Reports Hub Directory
 * File: views/reports/index.php
 */
?>
<div class="page-header d-flex justify-content-between align-items-center mb-4">
    <div>
        <h1 class="h3 mb-1">Management Reports &amp; Export Hub</h1>
        <p class="text-muted mb-0">Centralized analytics, asset valuations, aging schedules, and native CSV downloads.</p>
    </div>
    <div class="d-flex gap-2">
        <a href="/profit-loss" class="btn btn-outline-primary btn-sm">Profit &amp; Loss Statement</a>
    </div>
</div>

<!-- Snapshot Metric Cards -->
<div class="row g-3 mb-4">
    <div class="col-md-3">
        <div class="card stat-card border-left-primary shadow-sm h-100">
            <div class="card-body">
                <div class="text-xs font-weight-bold text-primary text-uppercase mb-1">This Month's Sales</div>
                <div class="h4 mb-0 font-weight-bold text-gray-800">₹ <?= number_format((float)$monthSales, 2) ?></div>
                <div class="small text-muted mt-1">Net counter sales turnover</div>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card stat-card border-left-info shadow-sm h-100">
            <div class="card-body">
                <div class="text-xs font-weight-bold text-info text-uppercase mb-1">Total Stock Asset Value</div>
                <div class="h4 mb-0 font-weight-bold text-gray-800">
                    <?php if ($canViewCosts): ?>
                        ₹ <?= number_format((float)$totalInventoryValuation, 2) ?>
                    <?php else: ?>
                        <span class="text-muted">Protected</span>
                    <?php endif; ?>
                </div>
                <div class="small text-muted mt-1">Handsets + accessories cost</div>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card stat-card border-left-danger shadow-sm h-100">
            <div class="card-body">
                <div class="text-xs font-weight-bold text-danger text-uppercase mb-1">Market Udhar (Receivable)</div>
                <div class="h4 mb-0 font-weight-bold text-danger">₹ <?= number_format((float)$totalUdhar, 2) ?></div>
                <div class="small text-muted mt-1">Pending customer collections</div>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card stat-card border-left-warning shadow-sm h-100">
            <div class="card-body">
                <div class="text-xs font-weight-bold text-warning text-uppercase mb-1">Supplier Debt (Dena)</div>
                <div class="h4 mb-0 font-weight-bold text-dark">
                    <?php if ($canViewCosts): ?>
                        ₹ <?= number_format((float)$totalSupplierDebt, 2) ?>
                    <?php else: ?>
                        <span class="text-muted">Protected</span>
                    <?php endif; ?>
                </div>
                <div class="small text-muted mt-1">Open wholesale payables</div>
            </div>
        </div>
    </div>
</div>

<!-- Report Navigation Grid -->
<div class="row g-4">
    <!-- 1. Sales & Revenue Report -->
    <div class="col-md-6 col-lg-4">
        <div class="card shadow-sm h-100 border-top border-4 border-primary">
            <div class="card-body d-flex flex-column">
                <div class="d-flex align-items-center mb-3">
                    <div class="rounded-circle bg-light p-3 text-primary me-3" style="font-size: 1.5rem;">&#128722;</div>
                    <div>
                        <h5 class="card-title mb-0">Sales &amp; Revenue</h5>
                        <small class="text-muted">Transaction turnover &amp; margins</small>
                    </div>
                </div>
                <p class="card-text text-muted small flex-grow-1">
                    Detailed sales breakdown by date range, payment modes, customer discounts, line item profit, and sales staff performance.
                </p>
                <div class="d-flex gap-2 mt-3 pt-3 border-top">
                    <a href="/reports/sales" class="btn btn-sm btn-primary flex-grow-1">View Report</a>
                    <a href="/reports/sales?export=csv" class="btn btn-sm btn-outline-secondary" title="Export CSV">&#128190; CSV</a>
                </div>
            </div>
        </div>
    </div>

    <!-- 2. Inventory Valuation Report -->
    <div class="col-md-6 col-lg-4">
        <div class="card shadow-sm h-100 border-top border-4 border-info">
            <div class="card-body d-flex flex-column">
                <div class="d-flex align-items-center mb-3">
                    <div class="rounded-circle bg-light p-3 text-info me-3" style="font-size: 1.5rem;">&#128230;</div>
                    <div>
                        <h5 class="card-title mb-0">Stock &amp; Valuation</h5>
                        <small class="text-muted">Asset values &amp; reorder alerts</small>
                    </div>
                </div>
                <p class="card-text text-muted small flex-grow-1">
                    Category-wise inventory valuations based on weighted average costs, out-of-stock items, and low-stock reorder warnings.
                </p>
                <div class="d-flex gap-2 mt-3 pt-3 border-top">
                    <a href="/reports/inventory" class="btn btn-sm btn-info text-dark flex-grow-1">View Report</a>
                    <a href="/reports/inventory?export=csv" class="btn btn-sm btn-outline-secondary" title="Export CSV">&#128190; CSV</a>
                </div>
            </div>
        </div>
    </div>

    <!-- 3. Mobile Handsets Aging Report -->
    <div class="col-md-6 col-lg-4">
        <div class="card shadow-sm h-100 border-top border-4 border-dark">
            <div class="card-body d-flex flex-column">
                <div class="d-flex align-items-center mb-3">
                    <div class="rounded-circle bg-light p-3 text-dark me-3" style="font-size: 1.5rem;">&#128241;</div>
                    <div>
                        <h5 class="card-title mb-0">Mobiles Stock &amp; Aging</h5>
                        <small class="text-muted">Serialized handset inventory</small>
                    </div>
                </div>
                <p class="card-text text-muted small flex-grow-1">
                    Full registry of in-stock new and refurbished second-hand phones, IMEI 1/2 tracking, purchase cost basis, and days in inventory.
                </p>
                <div class="d-flex gap-2 mt-3 pt-3 border-top">
                    <a href="/reports/mobiles" class="btn btn-sm btn-dark flex-grow-1">View Report</a>
                    <a href="/reports/mobiles?export=csv" class="btn btn-sm btn-outline-secondary" title="Export CSV">&#128190; CSV</a>
                </div>
            </div>
        </div>
    </div>

    <!-- 4. Customer Credit Aging Report -->
    <div class="col-md-6 col-lg-4">
        <div class="card shadow-sm h-100 border-top border-4 border-danger">
            <div class="card-body d-flex flex-column">
                <div class="d-flex align-items-center mb-3">
                    <div class="rounded-circle bg-light p-3 text-danger me-3" style="font-size: 1.5rem;">&#128214;</div>
                    <div>
                        <h5 class="card-title mb-0">Customer Credit (Udhar)</h5>
                        <small class="text-muted">Receivables aging &amp; recovery</small>
                    </div>
                </div>
                <p class="card-text text-muted small flex-grow-1">
                    Complete debtor aging schedule (0-15, 16-30, 31-60, 60+ days), open sales/repairs debt, phone numbers, and repayment tracking.
                </p>
                <div class="d-flex gap-2 mt-3 pt-3 border-top">
                    <a href="/reports/credit" class="btn btn-sm btn-danger flex-grow-1">View Report</a>
                    <a href="/reports/credit?export=csv" class="btn btn-sm btn-outline-secondary" title="Export CSV">&#128190; CSV</a>
                </div>
            </div>
        </div>
    </div>

    <!-- 5. Vendor Consignments Report -->
    <div class="col-md-6 col-lg-4">
        <div class="card shadow-sm h-100 border-top border-4 border-warning">
            <div class="card-body d-flex flex-column">
                <div class="d-flex align-items-center mb-3">
                    <div class="rounded-circle bg-light p-3 text-warning me-3" style="font-size: 1.5rem;">&#127970;</div>
                    <div>
                        <h5 class="card-title mb-0">Vendor Consignments</h5>
                        <small class="text-muted">Procurements &amp; supplier debts</small>
                    </div>
                </div>
                <p class="card-text text-muted small flex-grow-1">
                    Wholesale supplier purchases directory, consignment totals, payments made, and outstanding balance payables (*Mahajan Dena*).
                </p>
                <div class="d-flex gap-2 mt-3 pt-3 border-top">
                    <a href="/reports/purchases" class="btn btn-sm btn-warning text-dark flex-grow-1">View Report</a>
                    <a href="/reports/purchases?export=csv" class="btn btn-sm btn-outline-secondary" title="Export CSV">&#128190; CSV</a>
                </div>
            </div>
        </div>
    </div>

    <!-- 6. Repair Services & Parts Report -->
    <div class="col-md-6 col-lg-4">
        <div class="card shadow-sm h-100 border-top border-4 border-success">
            <div class="card-body d-flex flex-column">
                <div class="d-flex align-items-center mb-3">
                    <div class="rounded-circle bg-light p-3 text-success me-3" style="font-size: 1.5rem;">&#128295;</div>
                    <div>
                        <h5 class="card-title mb-0">Repair Services</h5>
                        <small class="text-muted">Customer jobs &amp; refurbishment</small>
                    </div>
                </div>
                <p class="card-text text-muted small flex-grow-1">
                    Repair turnover, parts consumed from inventory vs external purchases, technician labour costs, and net service margin contributions.
                </p>
                <div class="d-flex gap-2 mt-3 pt-3 border-top">
                    <a href="/reports/repairs" class="btn btn-sm btn-success flex-grow-1">View Report</a>
                    <a href="/reports/repairs?export=csv" class="btn btn-sm btn-outline-secondary" title="Export CSV">&#128190; CSV</a>
                </div>
            </div>
        </div>
    </div>

    <!-- 7. Daily Cash Drawer Close -->
    <div class="col-md-6 col-lg-4">
        <div class="card shadow-sm h-100 border-top border-4 border-secondary">
            <div class="card-body d-flex flex-column">
                <div class="d-flex align-items-center mb-3">
                    <div class="rounded-circle bg-light p-3 text-secondary me-3" style="font-size: 1.5rem;">&#128176;</div>
                    <div>
                        <h5 class="card-title mb-0">Daily Cash Reconciliation</h5>
                        <small class="text-muted">Cashbook &amp; drawer balancing</small>
                    </div>
                </div>
                <p class="card-text text-muted small flex-grow-1">
                    End-of-day counter closing register: cash sales receipts, repair cash, minus supplier payouts, cash in drawer, and digital totals.
                </p>
                <div class="d-flex gap-2 mt-3 pt-3 border-top">
                    <a href="/reports/daily-close" class="btn btn-sm btn-secondary flex-grow-1">Daily Close</a>
                    <a href="/reports/daily-close?export=csv" class="btn btn-sm btn-outline-secondary" title="Export CSV">&#128190; CSV</a>
                </div>
            </div>
        </div>
    </div>
</div>
