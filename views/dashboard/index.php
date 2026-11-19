<?php
/**
 * Executive Command Center & Operations Dashboard
 * File: views/dashboard/index.php
 */
?>
<!-- Shop Header & Status Banner -->
<div class="card shadow-sm mb-4 border-left-primary">
    <div class="card-body py-3 d-flex flex-wrap justify-content-between align-items-center gap-3">
        <div>
            <div class="d-flex align-items-center gap-2 mb-1">
                <h1 class="h3 mb-0 font-weight-bold text-gray-900"><?= htmlspecialchars($appName ?? 'Mobile Hub POS') ?></h1>
                <span class="badge bg-dark font-monospace text-xs"><?= htmlspecialchars($appTagline ?? 'Store Terminal') ?></span>
                <?php if ($isOwner): ?>
                    <span class="badge bg-primary text-xs">Owner Node</span>
                <?php else: ?>
                    <span class="badge bg-secondary text-xs"><?= htmlspecialchars($userRole) ?></span>
                <?php endif; ?>
            </div>
            <div class="text-muted small">
                <span>&#128205;</span> <strong><?= htmlspecialchars($appAddress ?? 'Retail Counter') ?></strong> &bull;
                <span>&#128337;</span> <?= date('l, d F Y') ?> &bull; Internal Business Control Node
            </div>
        </div>
        <div class="d-flex align-items-center gap-2">
            <span class="badge bg-success-subtle text-success border border-success px-3 py-2 text-sm font-weight-bold">
                &#9679; Core Engine Online (Zero GST)
            </span>
        </div>
    </div>
</div>

<!-- Quick Workflow Action Launcher -->
<div class="card shadow-sm mb-4">
    <div class="card-header bg-white py-2 d-flex justify-content-between align-items-center">
        <span class="text-xs font-weight-bold text-uppercase text-muted">Quick Workflow Launchers</span>
        <span class="text-xs text-muted">One-click operational triggers</span>
    </div>
    <div class="card-body py-3">
        <div class="d-flex flex-wrap gap-2">
            <a href="/sales/create" class="btn btn-primary btn-sm px-3 shadow-sm">
                <span>&#128722;</span> <strong>+ New Counter Sale</strong>
            </a>
            <a href="/phones/search" class="btn btn-outline-primary btn-sm px-3">
                <span>&#128269;</span> Check / Lookup IMEI
            </a>
            <a href="/repairs/customer/create" class="btn btn-outline-info btn-sm px-3">
                <span>&#128295;</span> + New Repair Job
            </a>
            <a href="/repairs/refurbish/create" class="btn btn-outline-secondary btn-sm px-3">
                <span>&#9881;</span> Refurbish Used Phone
            </a>
            <a href="/purchases/create" class="btn btn-outline-dark btn-sm px-3">
                <span>&#128230;</span> Receive Wholesale Stock
            </a>
            <a href="/reports/daily-close" class="btn btn-outline-success btn-sm px-3">
                <span>&#128176;</span> Daily Cash Close
            </a>
            <a href="/ledgers/customers" class="btn btn-outline-warning btn-sm px-3 text-dark">
                <span>&#128211;</span> Customer Udhar Ledgers
            </a>
            <?php if ($canViewFinancial): ?>
                <a href="/reports" class="btn btn-outline-secondary btn-sm px-3">
                    <span>&#128202;</span> Management Reports Hub
                </a>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Primary Financial & Counter Dials (Row 1) -->
<div class="row g-3 mb-4">
    <!-- Today's Counter Sales Turnover -->
    <div class="col-md-3">
        <div class="card stat-card border-left-primary shadow-sm h-100">
            <div class="card-body">
                <div class="text-xs font-weight-bold text-primary text-uppercase mb-1">Today's Sales Turnover</div>
                <div class="h4 mb-0 font-weight-bold text-gray-900">₹ <?= number_format((float)$todaySalesTurnover, 2) ?></div>
                <div class="small text-muted mt-1">
                    <?= $todaySalesCount ?> receipts
                    <?php if (!$isOwner): ?>
                        &bull; Your sales: <strong><?= $staffPersonalSalesCount ?></strong>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Today's Cash in Drawer -->
    <div class="col-md-3">
        <div class="card stat-card border-left-success shadow-sm h-100">
            <div class="card-body">
                <div class="text-xs font-weight-bold text-success text-uppercase mb-1">Physical Cash In Drawer</div>
                <div class="h4 mb-0 font-weight-bold text-success">₹ <?= number_format((float)$todayNetCashInDrawer, 2) ?></div>
                <div class="small text-muted mt-1">
                    In: ₹ <?= number_format((float)$todayCashInflow, 2) ?> &bull; Out: ₹ <?= number_format((float)$todayCashOutflow, 2) ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Digital Collections Today -->
    <div class="col-md-3">
        <div class="card stat-card border-left-info shadow-sm h-100">
            <div class="card-body">
                <div class="text-xs font-weight-bold text-info text-uppercase mb-1">Today's Digital Collections</div>
                <div class="h4 mb-0 font-weight-bold text-gray-900">₹ <?= number_format((float)$todayDigitalCollections, 2) ?></div>
                <div class="small text-muted mt-1">
                    UPI / POS / Bank transfers
                </div>
            </div>
        </div>
    </div>

    <!-- Month-to-Date Turnover & Profit -->
    <div class="col-md-3">
        <div class="card stat-card border-left-dark shadow-sm h-100">
            <div class="card-body">
                <div class="text-xs font-weight-bold text-dark text-uppercase mb-1">Month-To-Date (MTD) Turnover</div>
                <div class="h4 mb-0 font-weight-bold text-gray-900">₹ <?= number_format((float)$mtdTurnover, 2) ?></div>
                <div class="small text-muted mt-1">
                    <?php if ($canViewMargins): ?>
                        MTD Gross Profit: <strong class="text-success">₹ <?= number_format((float)$mtdProfit, 2) ?></strong>
                    <?php else: ?>
                        Sales &amp; repair turnover
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Secondary Operational Dials (Row 2) -->
<div class="row g-3 mb-4">
    <!-- Handsets in Stock -->
    <div class="col-md-3">
        <div class="card stat-card border-left-secondary shadow-sm h-100">
            <div class="card-body">
                <div class="text-xs font-weight-bold text-secondary text-uppercase mb-1">Handsets In Stock</div>
                <div class="h4 mb-0 font-weight-bold text-gray-900"><?= number_format($inStockHandsetsCount) ?></div>
                <div class="small text-muted mt-1">
                    New: <strong><?= $newHandsetsCount ?></strong> &bull; Used: <strong><?= $usedHandsetsCount ?></strong>
                </div>
            </div>
        </div>
    </div>

    <!-- Aged Handsets (>30 Days) -->
    <div class="col-md-3">
        <div class="card stat-card <?= $agedHandsetsCount > 0 ? 'border-left-danger' : 'border-left-secondary' ?> shadow-sm h-100">
            <div class="card-body">
                <div class="text-xs font-weight-bold <?= $agedHandsetsCount > 0 ? 'text-danger' : 'text-secondary' ?> text-uppercase mb-1">
                    Aged Handsets (&gt;30 Days)
                </div>
                <div class="h4 mb-0 font-weight-bold <?= $agedHandsetsCount > 0 ? 'text-danger' : 'text-gray-900' ?>">
                    <?= number_format($agedHandsetsCount) ?>
                </div>
                <div class="small text-muted mt-1">
                    <?= $agedHandsetsCount > 0 ? 'Requires priority sale / clearance' : 'No aged inventory' ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Low Stock Accessories Alert -->
    <div class="col-md-3">
        <div class="card stat-card <?= $lowStockAccessoriesCount > 0 ? 'border-left-warning' : 'border-left-secondary' ?> shadow-sm h-100">
            <div class="card-body">
                <div class="text-xs font-weight-bold <?= $lowStockAccessoriesCount > 0 ? 'text-warning' : 'text-secondary' ?> text-uppercase mb-1">
                    Low Stock Alerts
                </div>
                <div class="h4 mb-0 font-weight-bold <?= $lowStockAccessoriesCount > 0 ? 'text-warning text-dark' : 'text-gray-900' ?>">
                    <?= number_format($lowStockAccessoriesCount) ?>
                </div>
                <div class="small text-muted mt-1">
                    <?= $outOfStockAccessoriesCount > 0 ? "<strong>{$outOfStockAccessoriesCount}</strong> out-of-stock items" : 'At or below reorder level' ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Market Udhar vs Supplier Payables -->
    <div class="col-md-3">
        <div class="card stat-card border-left-warning shadow-sm h-100">
            <div class="card-body">
                <div class="text-xs font-weight-bold text-warning text-uppercase mb-1">Outstanding Udhar &amp; Dena</div>
                <div class="h5 mb-0 font-weight-bold text-gray-900">
                    Udhar: <span class="text-danger">₹ <?= number_format((float)$totalMarketUdhar, 2) ?></span>
                </div>
                <div class="small text-muted mt-1">
                    Supplier Dena: <strong>₹ <?= number_format((float)$totalSupplierPayables, 2) ?></strong>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Actionable Split View Feeds -->
<div class="row g-4 mb-4">
    <!-- Left Column: Recent Counter Sales -->
    <div class="col-lg-7">
        <div class="card shadow-sm h-100">
            <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
                <h6 class="m-0 font-weight-bold text-primary">Recent Counter Sales Activity</h6>
                <a href="/sales" class="btn btn-sm btn-outline-primary py-0 px-2 text-xs">View All Sales</a>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>Sale #</th>
                                <th>Customer</th>
                                <th>Date / Time</th>
                                <th>Status</th>
                                <th class="text-end">Amount (₹)</th>
                                <th>Staff</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($recentSales)): ?>
                                <tr>
                                    <td colspan="6" class="text-center py-4 text-muted">
                                        No sales records recorded yet. Start by clicking <strong>+ New Counter Sale</strong>.
                                    </td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($recentSales as $s): ?>
                                    <tr>
                                        <td>
                                            <a href="/sales/<?= (int)$s['id'] ?>" class="font-weight-bold text-dark font-monospace text-decoration-none">
                                                <?= htmlspecialchars($s['sale_number']) ?>
                                            </a>
                                        </td>
                                        <td>
                                            <div class="font-weight-bold"><?= htmlspecialchars($s['customer_name'] ?? 'Walk-in Customer') ?></div>
                                            <div class="text-muted small"><?= htmlspecialchars($s['customer_phone'] ?? '—') ?></div>
                                        </td>
                                        <td class="small text-muted">
                                            <?= htmlspecialchars($s['sale_date']) ?><br>
                                            <span class="text-xs"><?= htmlspecialchars($s['sale_time']) ?></span>
                                        </td>
                                        <td>
                                            <?php if ($s['payment_status'] === 'PAID'): ?>
                                                <span class="badge bg-success">PAID</span>
                                            <?php elseif ($s['payment_status'] === 'PARTIAL'): ?>
                                                <span class="badge bg-warning text-dark">PARTIAL</span>
                                            <?php else: ?>
                                                <span class="badge bg-danger">DUE (UDHAR)</span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="text-end font-weight-bold text-gray-900">
                                            ₹ <?= number_format((float)$s['final_total_amount'], 2) ?>
                                        </td>
                                        <td><span class="small text-muted"><?= htmlspecialchars($s['staff_name']) ?></span></td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <!-- Right Column: Operational Alerts & Actions -->
    <div class="col-lg-5">
        <!-- Critical Low Stock Alerts -->
        <div class="card shadow-sm mb-4">
            <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
                <h6 class="m-0 font-weight-bold text-warning">Critical Low Stock Reorder Alerts</h6>
                <a href="/reports/inventory?stock=LOW_STOCK" class="btn btn-sm btn-outline-warning text-dark py-0 px-2 text-xs">Inventory Report</a>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-sm table-hover align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>Product</th>
                                <th class="text-center">Stock</th>
                                <th class="text-center">Reorder</th>
                                <th class="text-end">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($lowStockItems)): ?>
                                <tr>
                                    <td colspan="4" class="text-center py-3 text-muted small">
                                        All inventory accessories are above reorder thresholds.
                                    </td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($lowStockItems as $l): ?>
                                    <tr>
                                        <td>
                                            <div class="font-weight-bold small"><?= htmlspecialchars($l['name']) ?></div>
                                            <div class="text-muted text-xs"><?= htmlspecialchars($l['brand']) ?> &bull; ₹ <?= number_format((float)$l['selling_price'], 2) ?></div>
                                        </td>
                                        <td class="text-center">
                                            <span class="badge <?= (int)$l['current_stock'] === 0 ? 'bg-danger' : 'bg-warning text-dark' ?>">
                                                <?= (int)$l['current_stock'] ?>
                                            </span>
                                        </td>
                                        <td class="text-center small text-muted"><?= (int)$l['reorder_level'] ?></td>
                                        <td class="text-end">
                                            <a href="/purchases/create" class="btn btn-xs btn-outline-dark py-0 px-2 text-xs" title="Procure Stock">Order</a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- Aged Serialized Handsets Alert -->
        <?php if (!empty($agedHandsets)): ?>
            <div class="card shadow-sm mb-4 border-left-danger">
                <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
                    <h6 class="m-0 font-weight-bold text-danger">Aged Handsets (&gt;30 Days in Stock)</h6>
                    <a href="/reports/mobiles" class="btn btn-sm btn-outline-danger py-0 px-2 text-xs">View Handsets</a>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-sm table-hover align-middle mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>Model &amp; IMEI</th>
                                    <th>Type</th>
                                    <th class="text-center">Days</th>
                                    <th class="text-end">Expected (₹)</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($agedHandsets as $ah): ?>
                                    <tr>
                                        <td>
                                            <div class="font-weight-bold small"><?= htmlspecialchars($ah['brand'] . ' ' . $ah['model_name']) ?></div>
                                            <div class="text-muted text-xs font-monospace"><?= htmlspecialchars($ah['imei_1']) ?></div>
                                        </td>
                                        <td>
                                            <span class="badge <?= $ah['phone_type'] === 'NEW' ? 'bg-info' : 'bg-secondary' ?> text-xs">
                                                <?= htmlspecialchars($ah['phone_type']) ?>
                                            </span>
                                        </td>
                                        <td class="text-center">
                                            <span class="badge bg-danger text-white"><?= $ah['days_in_stock'] ?> d</span>
                                        </td>
                                        <td class="text-end font-weight-bold small">
                                            ₹ <?= number_format((float)($ah['expected_sale_price'] ?? 0.00), 2) ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        <?php endif; ?>

        <!-- Top Market Udhar (Receivables) -->
        <?php if (!empty($topUdharCustomers)): ?>
            <div class="card shadow-sm">
                <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
                    <h6 class="m-0 font-weight-bold text-warning">Market Udhar Follow-up</h6>
                    <a href="/ledgers/customers" class="btn btn-sm btn-outline-warning text-dark py-0 px-2 text-xs">Udhar Ledger</a>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-sm table-hover align-middle mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>Customer</th>
                                    <th>Ref Doc</th>
                                    <th class="text-end">Due (₹)</th>
                                    <th class="text-end">Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($topUdharCustomers as $u): ?>
                                    <tr>
                                        <td>
                                            <div class="font-weight-bold small"><?= htmlspecialchars($u['customer_name'] ?? 'Customer') ?></div>
                                            <div class="text-muted text-xs"><?= htmlspecialchars($u['customer_phone'] ?? '—') ?></div>
                                        </td>
                                        <td class="small font-monospace text-muted"><?= htmlspecialchars($u['doc_number']) ?></td>
                                        <td class="text-end font-weight-bold text-danger">
                                            ₹ <?= number_format((float)$u['due_amount'], 2) ?>
                                        </td>
                                        <td class="text-end">
                                            <a href="/ledgers/customers/<?= urlencode($u['customer_phone'] ?: $u['customer_name']) ?>/settle" class="btn btn-xs btn-outline-success py-0 px-2 text-xs">Settle</a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        <?php endif; ?>
    </div>
</div>
