<?php
/**
 * Mobile Repair Services & Refurbishment Report
 * File: views/reports/repairs.php
 */
?>
<div class="page-header d-flex flex-wrap justify-content-between align-items-center mb-4 gap-2">
    <div>
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb mb-1">
                <li class="breadcrumb-item"><a href="/reports">Reports Hub</a></li>
                <li class="breadcrumb-item active">Repair Services</li>
            </ol>
        </nav>
        <h1 class="h3 mb-0">Repair Services &amp; Refurbishment Report</h1>
    </div>
    <div class="d-flex gap-2">
        <a href="?date_from=<?= urlencode($dateFrom) ?>&date_to=<?= urlencode($dateTo) ?>&type=<?= urlencode($typeFilter) ?>&export=csv" class="btn btn-outline-success btn-sm">
            <span>&#128190;</span> Export CSV
        </a>
        <a href="/repairs" class="btn btn-outline-primary btn-sm">Repair Records</a>
        <a href="/reports" class="btn btn-outline-secondary btn-sm">Back to Hub</a>
    </div>
</div>

<!-- Filter Bar -->
<div class="card shadow-sm mb-4">
    <div class="card-body py-3">
        <form method="GET" action="/reports/repairs" class="row g-2 align-items-center">
            <div class="col-md-3">
                <label class="form-label text-xs text-muted mb-1">From Date</label>
                <input type="date" name="date_from" class="form-control form-control-sm" value="<?= htmlspecialchars($dateFrom) ?>">
            </div>
            <div class="col-md-3">
                <label class="form-label text-xs text-muted mb-1">To Date</label>
                <input type="date" name="date_to" class="form-control form-control-sm" value="<?= htmlspecialchars($dateTo) ?>">
            </div>
            <div class="col-md-3">
                <label class="form-label text-xs text-muted mb-1">Repair Type</label>
                <select name="type" class="form-select form-select-sm">
                    <option value="ALL" <?= $typeFilter === 'ALL' ? 'selected' : '' ?>>All Repair Types</option>
                    <option value="CUSTOMER_SERVICE" <?= $typeFilter === 'CUSTOMER_SERVICE' ? 'selected' : '' ?>>Customer Service Repairs</option>
                    <option value="REFURBISHMENT" <?= $typeFilter === 'REFURBISHMENT' ? 'selected' : '' ?>>Used Phone Refurbishment</option>
                </select>
            </div>
            <div class="col-md-3 d-flex align-items-end gap-2">
                <button type="submit" class="btn btn-sm btn-primary flex-grow-1">Filter</button>
                <a href="/reports/repairs" class="btn btn-sm btn-outline-secondary" title="Reset">&#10006;</a>
            </div>
        </form>
    </div>
</div>

<!-- Summaries Row -->
<div class="row g-3 mb-4">
    <div class="col-md-3">
        <div class="card stat-card border-left-primary shadow-sm h-100">
            <div class="card-body">
                <div class="text-xs font-weight-bold text-primary text-uppercase mb-1">Total Repair Jobs</div>
                <div class="h4 mb-0 font-weight-bold"><?= number_format($totalJobs) ?></div>
                <div class="small text-muted mt-1">Completed service records</div>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card stat-card border-left-success shadow-sm h-100">
            <div class="card-body">
                <div class="text-xs font-weight-bold text-success text-uppercase mb-1">Service Charges Billed</div>
                <div class="h4 mb-0 font-weight-bold text-success">₹ <?= number_format((float)$totalBilled, 2) ?></div>
                <div class="small text-muted mt-1">Customer repair revenue</div>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card stat-card border-left-secondary shadow-sm h-100">
            <div class="card-body">
                <div class="text-xs font-weight-bold text-secondary text-uppercase mb-1">Direct Costs (Parts + Labour)</div>
                <div class="h4 mb-0 font-weight-bold text-gray-800">
                    <?php if ($canViewCosts): ?>
                        ₹ <?= number_format((float)$totalCosts, 2) ?>
                    <?php else: ?>
                        <span class="text-muted">Protected</span>
                    <?php endif; ?>
                </div>
                <div class="small text-muted mt-1">
                    <?php if ($canViewCosts): ?>
                        Parts: ₹ <?= number_format((float)$totalPartsCost, 2) ?> &bull; Labour: ₹ <?= number_format((float)$totalLabour, 2) ?>
                    <?php else: ?>
                        Direct service expenses
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card stat-card border-left-dark shadow-sm h-100">
            <div class="card-body">
                <div class="text-xs font-weight-bold text-dark text-uppercase mb-1">Net Margin Contribution</div>
                <div class="h4 mb-0 font-weight-bold text-success">
                    <?php if ($canViewMargins): ?>
                        ₹ <?= number_format((float)$totalProfit, 2) ?>
                    <?php else: ?>
                        <span class="text-muted">Protected</span>
                    <?php endif; ?>
                </div>
                <div class="small text-muted mt-1">Net realized service margin</div>
            </div>
        </div>
    </div>
</div>

<!-- Repairs Table -->
<div class="card shadow-sm mb-4">
    <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
        <h6 class="m-0 font-weight-bold text-dark">Repair Service Jobs (<?= number_format($totalJobs) ?> records)</h6>
        <span class="text-xs text-muted">Pure Financial Service Records</span>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover table-striped align-middle mb-0 font-monospace-rows">
                <thead class="table-light text-xs text-uppercase text-muted">
                    <tr>
                        <th style="width: 14%;">Date</th>
                        <th style="width: 16%;">Repair #</th>
                        <th style="width: 12%;">Type</th>
                        <th style="width: 20%;">Device &amp; Customer</th>
                        <th style="width: 12%;" class="text-end">Billed (₹)</th>
                        <?php if ($canViewCosts): ?>
                            <th style="width: 12%;" class="text-end">Cost (₹)</th>
                        <?php endif; ?>
                        <?php if ($canViewMargins): ?>
                            <th style="width: 14%;" class="text-end text-success">Profit (₹)</th>
                        <?php endif; ?>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($repairs)): ?>
                        <tr>
                            <td colspan="<?= ($canViewCosts ? 1 : 0) + ($canViewMargins ? 1 : 0) + 5 ?>" class="text-center py-5 text-muted">No repair records found for the selected period.</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($repairs as $r): ?>
                            <tr>
                                <td class="small font-monospace"><?= htmlspecialchars($r['repair_date']) ?></td>
                                <td><a href="/repairs/<?= $r['id'] ?>" class="font-weight-bold text-decoration-none"><?= htmlspecialchars($r['repair_number']) ?></a></td>
                                <td>
                                    <span class="badge <?= $r['repair_type'] === 'CUSTOMER_SERVICE' ? 'bg-primary' : 'bg-warning text-dark' ?>">
                                        <?= $r['repair_type'] === 'CUSTOMER_SERVICE' ? 'Customer' : 'Refurbish' ?>
                                    </span>
                                </td>
                                <td>
                                    <div class="font-weight-bold text-dark"><?= htmlspecialchars($r['device_model'] ?? ($r['brand'] . ' ' . $r['phone_model_name'])) ?></div>
                                    <div class="text-xs text-muted"><?= htmlspecialchars($r['customer_name'] ?? 'Shop Handset') ?></div>
                                </td>
                                <td class="text-end font-monospace font-weight-bold">₹ <?= number_format((float)$r['amount_charged'], 2) ?></td>
                                <?php if ($canViewCosts): ?>
                                    <td class="text-end font-monospace text-muted">₹ <?= number_format((float)$r['total_repair_cost'], 2) ?></td>
                                <?php endif; ?>
                                <?php if ($canViewMargins): ?>
                                    <td class="text-end font-monospace font-weight-bold text-success">₹ <?= number_format((float)$r['net_profit_contribution'], 2) ?></td>
                                <?php endif; ?>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
