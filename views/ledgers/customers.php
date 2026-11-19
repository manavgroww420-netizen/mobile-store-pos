<?php
/**
 * Customer Credit Ledger (Udhar Khata)
 * File: views/ledgers/customers.php
 */
?>
<div class="page-header d-flex justify-content-between align-items-center mb-4">
    <div>
        <h1 class="h3 mb-1">Customer Credit Ledger <span class="badge badge-warning text-dark font-weight-normal ml-2">Udhar Khata</span></h1>
        <p class="text-muted mb-0">Consolidated market receivables across counter sales and customer repair jobs.</p>
    </div>
    <div class="d-flex gap-2">
        <a href="/sales" class="btn btn-outline-secondary btn-sm">Sales Records</a>
        <a href="/repairs" class="btn btn-outline-secondary btn-sm">Repair Records</a>
    </div>
</div>

<!-- Metrics Cards -->
<div class="row g-3 mb-4">
    <div class="col-md-4">
        <div class="card stat-card border-left-danger shadow-sm h-100">
            <div class="card-body">
                <div class="text-xs font-weight-bold text-danger text-uppercase mb-1">Total Market Receivable (Udhar)</div>
                <div class="h3 mb-0 font-weight-bold text-gray-800">₹ <?= number_format((float)$totalMarketUdhar, 2) ?></div>
                <div class="small text-muted mt-1">Pending collection from customers</div>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card stat-card border-left-warning shadow-sm h-100">
            <div class="card-body">
                <div class="text-xs font-weight-bold text-warning text-uppercase mb-1">Active Debtors</div>
                <div class="h3 mb-0 font-weight-bold text-gray-800"><?= number_format($totalDebtorsCount) ?></div>
                <div class="small text-muted mt-1">Customers with open unpaid balances</div>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card stat-card border-left-dark shadow-sm h-100">
            <div class="card-body">
                <div class="text-xs font-weight-bold text-secondary text-uppercase mb-1">Overdue (&gt;30 Days)</div>
                <div class="h3 mb-0 font-weight-bold text-danger">₹ <?= number_format((float)$totalOverdue30, 2) ?></div>
                <div class="small text-muted mt-1">Old debt requiring immediate recovery</div>
            </div>
        </div>
    </div>
</div>

<!-- Search & Filter Bar -->
<div class="card shadow-sm mb-4">
    <div class="card-body py-3">
        <form method="GET" action="/ledgers/customers" class="row g-2 align-items-center">
            <div class="col-md-5">
                <div class="input-group input-group-sm">
                    <span class="input-group-text">&#128269;</span>
                    <input type="text" name="q" class="form-control" placeholder="Search customer name or phone..." value="<?= htmlspecialchars($search) ?>">
                </div>
            </div>
            <div class="col-md-3">
                <select name="aging" class="form-select form-select-sm" onchange="this.form.submit()">
                    <option value="ALL" <?= $agingFilter === 'ALL' ? 'selected' : '' ?>>All Aging Periods</option>
                    <option value="0-30" <?= $agingFilter === '0-30' ? 'selected' : '' ?>>Recent (0 - 30 Days)</option>
                    <option value="31-60" <?= $agingFilter === '31-60' ? 'selected' : '' ?>>Aging (31 - 60 Days)</option>
                    <option value="60+" <?= $agingFilter === '60+' ? 'selected' : '' ?>>Critical (60+ Days)</option>
                </select>
            </div>
            <div class="col-md-2">
                <div class="input-group input-group-sm">
                    <span class="input-group-text">₹ &gt;=</span>
                    <input type="number" step="0.01" name="min_due" class="form-control" placeholder="Min Due" value="<?= $minDue > 0 ? htmlspecialchars((string)$minDue) : '' ?>">
                </div>
            </div>
            <div class="col-md-2 d-flex gap-2">
                <button type="submit" class="btn btn-sm btn-primary flex-grow-1">Filter</button>
                <?php if ($search !== '' || $agingFilter !== 'ALL' || $minDue > 0): ?>
                    <a href="/ledgers/customers" class="btn btn-sm btn-outline-secondary" title="Reset Filters">&#10006;</a>
                <?php endif; ?>
            </div>
        </form>
    </div>
</div>

<!-- Debtors Table -->
<div class="card shadow-sm">
    <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
        <h6 class="m-0 font-weight-bold text-dark">Customer Khata Directory (<?= count($customers) ?> records)</h6>
        <span class="text-xs text-muted">Sorted by outstanding balance descending</span>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover table-striped align-middle mb-0">
                <thead class="table-light text-xs text-uppercase text-muted">
                    <tr>
                        <th style="width: 25%;">Customer Name &amp; Contact</th>
                        <th style="width: 15%;" class="text-end">Balance Due (Baki)</th>
                        <th style="width: 15%;" class="text-end">Total Credit</th>
                        <th style="width: 15%;" class="text-end">Total Repaid</th>
                        <th style="width: 12%;" class="text-center">Open Bills</th>
                        <th style="width: 10%;" class="text-center">Aging</th>
                        <th style="width: 8%;" class="text-end">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($customers)): ?>
                        <tr>
                            <td colspan="7" class="text-center py-5 text-muted">
                                <div style="font-size: 2.5rem; margin-bottom: 0.5rem;">&#127881;</div>
                                <h6>No Outstanding Customer Credit Found</h6>
                                <p class="small mb-0">All customer accounts are settled, or no records match your filter criteria.</p>
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($customers as $c): ?>
                            <tr>
                                <td>
                                    <div class="font-weight-bold text-dark"><?= htmlspecialchars($c['name']) ?></div>
                                    <div class="text-xs text-muted">
                                        <?php if ($c['phone'] !== ''): ?>
                                            <span>&#128222; <?= htmlspecialchars($c['phone']) ?></span>
                                        <?php else: ?>
                                            <span class="fst-italic">No phone recorded</span>
                                        <?php endif; ?>
                                    </div>
                                </td>
                                <td class="text-end">
                                    <span class="badge bg-danger text-white font-weight-bold px-2 py-1" style="font-size: 0.95rem;">
                                        ₹ <?= number_format((float)$c['total_due'], 2) ?>
                                    </span>
                                </td>
                                <td class="text-end text-muted font-monospace">
                                    ₹ <?= number_format((float)$c['total_credit'], 2) ?>
                                </td>
                                <td class="text-end text-success font-monospace">
                                    ₹ <?= number_format((float)$c['total_paid'], 2) ?>
                                </td>
                                <td class="text-center">
                                    <span class="badge bg-secondary"><?= $c['unpaid_bills'] ?> bill(s)</span>
                                    <div class="text-xs mt-1">
                                        <?php if ($c['has_sales']): ?>
                                            <span class="badge bg-light text-dark border">Sale</span>
                                        <?php endif; ?>
                                        <?php if ($c['has_repairs']): ?>
                                            <span class="badge bg-light text-primary border">Repair</span>
                                        <?php endif; ?>
                                    </div>
                                </td>
                                <td class="text-center">
                                    <?php if ($c['days_old'] > 60): ?>
                                        <span class="badge bg-danger" title="Oldest due date: <?= $c['oldest_date'] ?>"><?= $c['days_old'] ?> days</span>
                                    <?php elseif ($c['days_old'] > 30): ?>
                                        <span class="badge bg-warning text-dark" title="Oldest due date: <?= $c['oldest_date'] ?>"><?= $c['days_old'] ?> days</span>
                                    <?php else: ?>
                                        <span class="badge bg-light text-dark border" title="Oldest due date: <?= $c['oldest_date'] ?>"><?= $c['days_old'] ?> days</span>
                                    <?php endif; ?>
                                </td>
                                <td class="text-end">
                                    <div class="btn-group btn-group-sm">
                                        <a href="/ledgers/customers/<?= urlencode($c['key']) ?>" class="btn btn-outline-primary btn-sm" title="View Statement Passbook">Statement</a>
                                        <a href="/ledgers/customers/<?= urlencode($c['key']) ?>/settle" class="btn btn-success btn-sm" title="Collect Payment">&#8377; Settle</a>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
