<?php
/**
 * Customer Credit Aging & Udhar Report
 * File: views/reports/credit.php
 */
?>
<div class="page-header d-flex flex-wrap justify-content-between align-items-center mb-4 gap-2">
    <div>
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb mb-1">
                <li class="breadcrumb-item"><a href="/reports">Reports Hub</a></li>
                <li class="breadcrumb-item active">Customer Credit Aging</li>
            </ol>
        </nav>
        <h1 class="h3 mb-0">Customer Credit Aging &amp; Udhar Recovery Report</h1>
    </div>
    <div class="d-flex gap-2">
        <a href="?aging=<?= urlencode($agingFilter) ?>&q=<?= urlencode($search) ?>&export=csv" class="btn btn-outline-success btn-sm">
            <span>&#128190;</span> Export CSV
        </a>
        <a href="/ledgers/customers" class="btn btn-outline-primary btn-sm">Open Udhar Khata</a>
        <a href="/reports" class="btn btn-outline-secondary btn-sm">Back to Hub</a>
    </div>
</div>

<!-- Filter Bar -->
<div class="card shadow-sm mb-4">
    <div class="card-body py-3">
        <form method="GET" action="/reports/credit" class="row g-2 align-items-center">
            <div class="col-md-5">
                <input type="text" name="q" class="form-control form-control-sm" placeholder="Customer name or phone..." value="<?= htmlspecialchars($search) ?>">
            </div>
            <div class="col-md-4">
                <select name="aging" class="form-select form-select-sm">
                    <option value="ALL" <?= $agingFilter === 'ALL' ? 'selected' : '' ?>>All Aging Schedules</option>
                    <option value="0-30" <?= $agingFilter === '0-30' ? 'selected' : '' ?>>Recent (0 - 30 Days)</option>
                    <option value="31-60" <?= $agingFilter === '31-60' ? 'selected' : '' ?>>Aging (31 - 60 Days)</option>
                    <option value="60+" <?= $agingFilter === '60+' ? 'selected' : '' ?>>Critical Recovery (60+ Days)</option>
                </select>
            </div>
            <div class="col-md-3 d-flex gap-2">
                <button type="submit" class="btn btn-sm btn-primary flex-grow-1">Filter</button>
                <a href="/reports/credit" class="btn btn-sm btn-outline-secondary" title="Reset">&#10006;</a>
            </div>
        </form>
    </div>
</div>

<!-- Summaries Row -->
<div class="row g-3 mb-4">
    <div class="col-md-4">
        <div class="card stat-card border-left-danger shadow-sm h-100">
            <div class="card-body">
                <div class="text-xs font-weight-bold text-danger text-uppercase mb-1">Total Market Udhar (Receivable)</div>
                <div class="h3 mb-0 font-weight-bold text-danger">₹ <?= number_format((float)$totalMarketUdhar, 2) ?></div>
                <div class="small text-muted mt-1">Pending collection across all debtors</div>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card stat-card border-left-warning shadow-sm h-100">
            <div class="card-body">
                <div class="text-xs font-weight-bold text-warning text-uppercase mb-1">Active Debtors</div>
                <div class="h3 mb-0 font-weight-bold text-dark"><?= number_format($debtorsCount) ?></div>
                <div class="small text-muted mt-1">Customers with open due balances</div>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card stat-card border-left-dark shadow-sm h-100">
            <div class="card-body">
                <div class="text-xs font-weight-bold text-secondary text-uppercase mb-1">Overdue (&gt;30 Days)</div>
                <div class="h3 mb-0 font-weight-bold text-danger">₹ <?= number_format((float)$totalOverdue30, 2) ?></div>
                <div class="small text-muted mt-1">Requires urgent shop follow-up</div>
            </div>
        </div>
    </div>
</div>

<!-- Debtors Table -->
<div class="card shadow-sm mb-4">
    <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
        <h6 class="m-0 font-weight-bold text-dark">Debtors Aging Schedule (<?= number_format($debtorsCount) ?> debtors)</h6>
        <span class="text-xs text-muted">Ranked by outstanding balance descending</span>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover table-striped align-middle mb-0 font-monospace-rows">
                <thead class="table-light text-xs text-uppercase text-muted">
                    <tr>
                        <th style="width: 25%;">Customer &amp; Phone</th>
                        <th style="width: 15%;" class="text-end text-danger">Due Balance (₹)</th>
                        <th style="width: 15%;" class="text-end">Total Credit (₹)</th>
                        <th style="width: 15%;" class="text-end text-success">Total Repaid (₹)</th>
                        <th style="width: 10%;" class="text-center">Open Bills</th>
                        <th style="width: 10%;">Oldest Date</th>
                        <th style="width: 10%;" class="text-center">Aging</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($customers)): ?>
                        <tr>
                            <td colspan="7" class="text-center py-5 text-muted">No outstanding customer credit matching criteria.</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($customers as $c): ?>
                            <tr>
                                <td>
                                    <div class="font-weight-bold text-dark"><?= htmlspecialchars($c['name']) ?></div>
                                    <div class="text-xs text-muted font-monospace"><?= htmlspecialchars($c['phone'] !== '' ? $c['phone'] : 'Walk-in') ?></div>
                                </td>
                                <td class="text-end font-monospace font-weight-bold text-danger" style="font-size: 1rem;">
                                    ₹ <?= number_format((float)$c['total_due'], 2) ?>
                                </td>
                                <td class="text-end font-monospace text-muted">₹ <?= number_format((float)$c['total_credit'], 2) ?></td>
                                <td class="text-end font-monospace text-success">₹ <?= number_format((float)$c['total_paid'], 2) ?></td>
                                <td class="text-center font-monospace"><span class="badge bg-light text-dark border"><?= $c['open_bills'] ?></span></td>
                                <td class="small font-monospace"><?= htmlspecialchars($c['oldest_date']) ?></td>
                                <td class="text-center">
                                    <span class="badge <?= $c['days_old'] > 60 ? 'bg-danger' : ($c['days_old'] > 30 ? 'bg-warning text-dark' : 'bg-light text-dark border') ?>">
                                        <?= $c['days_old'] ?> days
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
