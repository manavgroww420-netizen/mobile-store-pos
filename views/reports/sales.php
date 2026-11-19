<?php
/**
 * Sales & Revenue Detailed Report
 * File: views/reports/sales.php
 */
?>
<div class="page-header d-flex flex-wrap justify-content-between align-items-center mb-4 gap-2">
    <div>
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb mb-1">
                <li class="breadcrumb-item"><a href="/reports">Reports Hub</a></li>
                <li class="breadcrumb-item active">Sales &amp; Revenue</li>
            </ol>
        </nav>
        <h1 class="h3 mb-0">Sales &amp; Revenue Report</h1>
    </div>
    <div class="d-flex gap-2">
        <a href="?date_from=<?= urlencode($dateFrom) ?>&date_to=<?= urlencode($dateTo) ?>&mode=<?= urlencode($mode) ?>&export=csv" class="btn btn-outline-success btn-sm">
            <span>&#128190;</span> Export CSV
        </a>
        <a href="/reports" class="btn btn-outline-secondary btn-sm">Back to Hub</a>
    </div>
</div>

<!-- Filter Bar -->
<div class="card shadow-sm mb-4">
    <div class="card-body py-3">
        <form method="GET" action="/reports/sales" class="row g-2 align-items-center">
            <div class="col-md-3">
                <label class="form-label text-xs text-muted mb-1">From Date</label>
                <input type="date" name="date_from" class="form-control form-control-sm" value="<?= htmlspecialchars($dateFrom) ?>">
            </div>
            <div class="col-md-3">
                <label class="form-label text-xs text-muted mb-1">To Date</label>
                <input type="date" name="date_to" class="form-control form-control-sm" value="<?= htmlspecialchars($dateTo) ?>">
            </div>
            <div class="col-md-3">
                <label class="form-label text-xs text-muted mb-1">Payment Mode</label>
                <select name="mode" class="form-select form-select-sm">
                    <option value="ALL" <?= $mode === 'ALL' ? 'selected' : '' ?>>All Modes</option>
                    <option value="CASH" <?= $mode === 'CASH' ? 'selected' : '' ?>>Cash</option>
                    <option value="UPI" <?= $mode === 'UPI' ? 'selected' : '' ?>>UPI</option>
                    <option value="CARD" <?= $mode === 'CARD' ? 'selected' : '' ?>>Card</option>
                </select>
            </div>
            <div class="col-md-3 d-flex align-items-end gap-2">
                <button type="submit" class="btn btn-sm btn-primary flex-grow-1">Apply Filters</button>
                <a href="/reports/sales" class="btn btn-sm btn-outline-secondary" title="Reset">&#10006;</a>
            </div>
        </form>
    </div>
</div>

<!-- Summaries Row -->
<div class="row g-3 mb-4">
    <div class="col-md-3">
        <div class="card stat-card border-left-primary shadow-sm h-100">
            <div class="card-body">
                <div class="text-xs font-weight-bold text-primary text-uppercase mb-1">Total Counter Sales</div>
                <div class="h4 mb-0 font-weight-bold"><?= number_format($totalCount) ?></div>
                <div class="small text-muted mt-1">Gross Subtotal: ₹ <?= number_format((float)$totalGross, 2) ?></div>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card stat-card border-left-success shadow-sm h-100">
            <div class="card-body">
                <div class="text-xs font-weight-bold text-success text-uppercase mb-1">Net Sales Turnover</div>
                <div class="h4 mb-0 font-weight-bold text-success">₹ <?= number_format((float)$totalNet, 2) ?></div>
                <div class="small text-muted mt-1">Discounts Given: ₹ <?= number_format((float)$totalDiscount, 2) ?></div>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card stat-card border-left-danger shadow-sm h-100">
            <div class="card-body">
                <div class="text-xs font-weight-bold text-danger text-uppercase mb-1">Uncollected Udhar (Due)</div>
                <div class="h4 mb-0 font-weight-bold text-danger">₹ <?= number_format((float)$totalDue, 2) ?></div>
                <div class="small text-muted mt-1">Collected: ₹ <?= number_format((float)$totalPaid, 2) ?></div>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card stat-card border-left-dark shadow-sm h-100">
            <div class="card-body">
                <div class="text-xs font-weight-bold text-dark text-uppercase mb-1">Gross Margin</div>
                <div class="h4 mb-0 font-weight-bold">
                    <?php if ($canViewMargins): ?>
                        ₹ <?= number_format((float)$totalProfit, 2) ?> <span class="badge bg-secondary font-weight-normal ms-1" style="font-size: 0.75rem;"><?= number_format((float)$marginPct, 1) ?>%</span>
                    <?php else: ?>
                        <span class="text-muted">Protected</span>
                    <?php endif; ?>
                </div>
                <div class="small text-muted mt-1">Realized counter margin</div>
            </div>
        </div>
    </div>
</div>

<!-- Sales Transactions Table -->
<div class="card shadow-sm mb-4">
    <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
        <h6 class="m-0 font-weight-bold text-dark">Sales Records (<?= number_format($totalCount) ?> entries)</h6>
        <span class="text-xs text-muted">Zero GST Internal Shop Invoicing</span>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover table-striped align-middle mb-0 font-monospace-rows">
                <thead class="table-light text-xs text-uppercase text-muted">
                    <tr>
                        <th style="width: 14%;">Date &amp; Time</th>
                        <th style="width: 16%;">Sale #</th>
                        <th style="width: 18%;">Customer</th>
                        <th style="width: 10%;" class="text-center">Items</th>
                        <th style="width: 14%;" class="text-end">Net Total</th>
                        <th style="width: 12%;" class="text-end">Due</th>
                        <?php if ($canViewMargins): ?>
                            <th style="width: 14%;" class="text-end">Profit</th>
                        <?php endif; ?>
                        <th style="width: 12%;">Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($sales)): ?>
                        <tr>
                            <td colspan="<?= $canViewMargins ? 8 : 7 ?>" class="text-center py-5 text-muted">No sales found for the selected period.</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($sales as $s): ?>
                            <tr>
                                <td class="small font-monospace"><?= htmlspecialchars($s['sale_date']) ?> <span class="text-muted"><?= htmlspecialchars($s['sale_time']) ?></span></td>
                                <td><a href="/sales/<?= $s['id'] ?>" class="font-weight-bold text-decoration-none"><?= htmlspecialchars($s['sale_number']) ?></a></td>
                                <td>
                                    <div class="small font-weight-bold text-dark"><?= htmlspecialchars($s['customer_name'] ?? 'Walk-in Customer') ?></div>
                                    <div class="text-xs text-muted"><?= htmlspecialchars($s['customer_phone'] ?? 'N/A') ?></div>
                                </td>
                                <td class="text-center"><span class="badge bg-light text-dark border"><?= $s['items_count'] ?> item(s)</span></td>
                                <td class="text-end font-monospace font-weight-bold">₹ <?= number_format((float)$s['final_total_amount'], 2) ?></td>
                                <td class="text-end font-monospace <?= (float)$s['due_amount'] > 0 ? 'text-danger font-weight-bold' : 'text-muted' ?>">
                                    <?= (float)$s['due_amount'] > 0 ? ('₹ ' . number_format((float)$s['due_amount'], 2)) : '—' ?>
                                </td>
                                <?php if ($canViewMargins): ?>
                                    <td class="text-end font-monospace text-success font-weight-bold">₹ <?= number_format((float)$s['gross_profit'], 2) ?></td>
                                <?php endif; ?>
                                <td>
                                    <span class="badge <?= $s['payment_status'] === 'PAID' ? 'bg-success' : ($s['payment_status'] === 'PARTIAL' ? 'bg-warning text-dark' : 'bg-danger') ?>">
                                        <?= htmlspecialchars($s['payment_status']) ?>
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
