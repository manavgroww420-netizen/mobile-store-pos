<?php
/**
 * Vendor Procurement & Payables Report
 * File: views/reports/purchases.php
 */
?>
<div class="page-header d-flex flex-wrap justify-content-between align-items-center mb-4 gap-2">
    <div>
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb mb-1">
                <li class="breadcrumb-item"><a href="/reports">Reports Hub</a></li>
                <li class="breadcrumb-item active">Vendor Procurement</li>
            </ol>
        </nav>
        <h1 class="h3 mb-0">Vendor Procurement &amp; Payables Report</h1>
    </div>
    <div class="d-flex gap-2">
        <a href="?vendor_id=<?= $vendorId ?>&date_from=<?= urlencode($dateFrom) ?>&date_to=<?= urlencode($dateTo) ?>&status=<?= urlencode($status) ?>&export=csv" class="btn btn-outline-success btn-sm">
            <span>&#128190;</span> Export CSV
        </a>
        <a href="/ledgers/vendors" class="btn btn-outline-danger btn-sm">Vendor Dena Khata</a>
        <a href="/reports" class="btn btn-outline-secondary btn-sm">Back to Hub</a>
    </div>
</div>

<!-- Filter Bar -->
<div class="card shadow-sm mb-4">
    <div class="card-body py-3">
        <form method="GET" action="/reports/purchases" class="row g-2 align-items-center">
            <div class="col-md-3">
                <label class="form-label text-xs text-muted mb-1">Supplier</label>
                <select name="vendor_id" class="form-select form-select-sm">
                    <option value="0">All Vendors</option>
                    <?php foreach ($vendors as $v): ?>
                        <option value="<?= $v['id'] ?>" <?= $vendorId === (int)$v['id'] ? 'selected' : '' ?>><?= htmlspecialchars($v['business_name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label text-xs text-muted mb-1">From Date</label>
                <input type="date" name="date_from" class="form-control form-control-sm" value="<?= htmlspecialchars($dateFrom) ?>">
            </div>
            <div class="col-md-2">
                <label class="form-label text-xs text-muted mb-1">To Date</label>
                <input type="date" name="date_to" class="form-control form-control-sm" value="<?= htmlspecialchars($dateTo) ?>">
            </div>
            <div class="col-md-3">
                <label class="form-label text-xs text-muted mb-1">Payment Status</label>
                <select name="status" class="form-select form-select-sm">
                    <option value="ALL" <?= $status === 'ALL' ? 'selected' : '' ?>>All Statuses</option>
                    <option value="UNPAID" <?= $status === 'UNPAID' ? 'selected' : '' ?>>Unpaid Only</option>
                    <option value="PARTIAL" <?= $status === 'PARTIAL' ? 'selected' : '' ?>>Partially Paid</option>
                    <option value="PAID" <?= $status === 'PAID' ? 'selected' : '' ?>>Fully Paid</option>
                </select>
            </div>
            <div class="col-md-2 d-flex align-items-end gap-2">
                <button type="submit" class="btn btn-sm btn-primary flex-grow-1">Filter</button>
                <a href="/reports/purchases" class="btn btn-sm btn-outline-secondary" title="Reset">&#10006;</a>
            </div>
        </form>
    </div>
</div>

<!-- Summaries Row -->
<div class="row g-3 mb-4">
    <div class="col-md-3">
        <div class="card stat-card border-left-primary shadow-sm h-100">
            <div class="card-body">
                <div class="text-xs font-weight-bold text-primary text-uppercase mb-1">Consignments Count</div>
                <div class="h4 mb-0 font-weight-bold"><?= number_format($totalConsignments) ?></div>
                <div class="small text-muted mt-1">Wholesale intake batches</div>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card stat-card border-left-info shadow-sm h-100">
            <div class="card-body">
                <div class="text-xs font-weight-bold text-info text-uppercase mb-1">Total Procurement Cost</div>
                <div class="h4 mb-0 font-weight-bold">
                    <?php if ($canViewCosts): ?>
                        ₹ <?= number_format((float)$totalAmount, 2) ?>
                    <?php else: ?>
                        <span class="text-muted">Protected</span>
                    <?php endif; ?>
                </div>
                <div class="small text-muted mt-1">Total wholesale stock value</div>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card stat-card border-left-success shadow-sm h-100">
            <div class="card-body">
                <div class="text-xs font-weight-bold text-success text-uppercase mb-1">Settled to Suppliers</div>
                <div class="h4 mb-0 font-weight-bold text-success">
                    <?php if ($canViewCosts): ?>
                        ₹ <?= number_format((float)$totalPaid, 2) ?>
                    <?php else: ?>
                        <span class="text-muted">Protected</span>
                    <?php endif; ?>
                </div>
                <div class="small text-muted mt-1">Total payments remitted</div>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card stat-card border-left-danger shadow-sm h-100">
            <div class="card-body">
                <div class="text-xs font-weight-bold text-danger text-uppercase mb-1">Outstanding Payable (Dena)</div>
                <div class="h4 mb-0 font-weight-bold text-danger">
                    <?php if ($canViewCosts): ?>
                        ₹ <?= number_format((float)$totalDue, 2) ?>
                    <?php else: ?>
                        <span class="text-muted">Protected</span>
                    <?php endif; ?>
                </div>
                <div class="small text-muted mt-1">Pending wholesale obligations</div>
            </div>
        </div>
    </div>
</div>

<!-- Purchases Table -->
<div class="card shadow-sm mb-4">
    <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
        <h6 class="m-0 font-weight-bold text-dark">Procurement Consignments (<?= number_format($totalConsignments) ?> entries)</h6>
        <span class="text-xs text-muted">Zero GST Internal Shop Accounting</span>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover table-striped align-middle mb-0 font-monospace-rows">
                <thead class="table-light text-xs text-uppercase text-muted">
                    <tr>
                        <th style="width: 14%;">Date</th>
                        <th style="width: 18%;">Purchase #</th>
                        <th style="width: 22%;">Supplier &amp; Invoice</th>
                        <?php if ($canViewCosts): ?>
                            <th style="width: 14%;" class="text-end">Total (₹)</th>
                            <th style="width: 14%;" class="text-end">Paid (₹)</th>
                            <th style="width: 14%;" class="text-end text-danger">Due (₹)</th>
                        <?php endif; ?>
                        <th style="width: 10%;">Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($purchases)): ?>
                        <tr>
                            <td colspan="<?= $canViewCosts ? 7 : 4 ?>" class="text-center py-5 text-muted">No procurement consignments found.</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($purchases as $p): ?>
                            <tr>
                                <td class="small font-monospace"><?= htmlspecialchars($p['purchase_date']) ?></td>
                                <td><a href="/purchases/<?= $p['id'] ?>" class="font-weight-bold text-decoration-none"><?= htmlspecialchars($p['purchase_number']) ?></a></td>
                                <td>
                                    <div class="font-weight-bold text-dark"><?= htmlspecialchars($p['vendor_name']) ?></div>
                                    <div class="text-xs text-muted">Inv: <?= htmlspecialchars($p['vendor_invoice_no'] ?? 'N/A') ?></div>
                                </td>
                                <?php if ($canViewCosts): ?>
                                    <td class="text-end font-monospace">₹ <?= number_format((float)$p['total_amount'], 2) ?></td>
                                    <td class="text-end font-monospace text-success">₹ <?= number_format((float)$p['paid_amount'], 2) ?></td>
                                    <td class="text-end font-monospace font-weight-bold <?= (float)$p['due_amount'] > 0 ? 'text-danger' : 'text-muted' ?>">
                                        <?= (float)$p['due_amount'] > 0 ? ('₹ ' . number_format((float)$p['due_amount'], 2)) : '—' ?>
                                    </td>
                                <?php endif; ?>
                                <td>
                                    <span class="badge <?= $p['payment_status'] === 'PAID' ? 'bg-success' : ($p['payment_status'] === 'PARTIAL' ? 'bg-warning text-dark' : 'bg-danger') ?>">
                                        <?= htmlspecialchars($p['payment_status']) ?>
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
