<?php
/**
 * Daily Cash Reconciliation & Cash Drawer Close Report
 * File: views/reports/daily_close.php
 */
?>
<div class="page-header d-flex flex-wrap justify-content-between align-items-center mb-4 gap-2">
    <div>
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb mb-1">
                <li class="breadcrumb-item"><a href="/reports">Reports Hub</a></li>
                <li class="breadcrumb-item active">Daily Cash Reconciliation</li>
            </ol>
        </nav>
        <h1 class="h3 mb-0">Daily Cash Reconciliation &amp; Drawer Register</h1>
        <div class="text-muted small mt-1">
            Reconciliation register for <strong><?= date('l, d F Y', strtotime($selectedDate)) ?></strong>
        </div>
    </div>
    <div class="d-flex gap-2">
        <button type="button" onclick="window.print()" class="btn btn-outline-secondary btn-sm">
            <span>&#128438;</span> Print Register
        </button>
        <a href="?date=<?= urlencode($selectedDate) ?>&export=csv" class="btn btn-outline-success btn-sm">
            <span>&#128190;</span> Export CSV
        </a>
        <a href="/reports" class="btn btn-outline-secondary btn-sm">Back to Hub</a>
    </div>
</div>

<!-- Date Selector Bar -->
<div class="card shadow-sm mb-4">
    <div class="card-body py-3">
        <form method="GET" action="/reports/daily-close" class="row g-2 align-items-center">
            <div class="col-auto">
                <label class="form-label text-xs text-muted mb-0 font-weight-bold">Select Date:</label>
            </div>
            <div class="col-md-3">
                <input type="date" name="date" class="form-control form-control-sm" value="<?= htmlspecialchars($selectedDate) ?>">
            </div>
            <div class="col-auto d-flex gap-2">
                <button type="submit" class="btn btn-sm btn-primary">Load Register</button>
                <a href="/reports/daily-close?date=<?= date('Y-m-d') ?>" class="btn btn-sm btn-outline-secondary">Today</a>
                <a href="/reports/daily-close?date=<?= date('Y-m-d', strtotime('-1 day')) ?>" class="btn btn-sm btn-outline-secondary">Yesterday</a>
            </div>
        </form>
    </div>
</div>

<!-- Master Cash Drawer Balancing Cards -->
<div class="card shadow-sm mb-4 border-left-primary">
    <div class="card-header bg-white py-3">
        <h6 class="m-0 font-weight-bold text-primary">Master Cash Drawer Physical Reconciliation (CASH ONLY)</h6>
    </div>
    <div class="card-body">
        <div class="row g-3">
            <div class="col-md-3">
                <div class="p-3 bg-light rounded border">
                    <div class="text-xs font-weight-bold text-success text-uppercase mb-1">Cash Inflows (+)</div>
                    <div class="h4 mb-0 font-weight-bold text-success">₹ <?= number_format((float)$cashInflow, 2) ?></div>
                    <div class="small text-muted mt-1">
                        Sales: <?= $cashSalesCount ?> &bull; Repairs: <?= $cashRepairsCount ?>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="p-3 bg-light rounded border">
                    <div class="text-xs font-weight-bold text-danger text-uppercase mb-1">Cash Outflows (-)</div>
                    <div class="h4 mb-0 font-weight-bold text-danger">₹ <?= number_format((float)$cashOutflow, 2) ?></div>
                    <div class="small text-muted mt-1">Vendor payables &amp; refunds</div>
                </div>
            </div>
            <div class="col-md-6">
                <div class="p-3 bg-light rounded border border-primary">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <div class="text-xs font-weight-bold text-primary text-uppercase mb-1">Calculated Physical Cash In Drawer (=)</div>
                            <div class="h3 mb-0 font-weight-bold text-primary">₹ <?= number_format((float)$netCashInDrawer, 2) ?></div>
                            <div class="small text-muted mt-1">Must match exact currency notes &amp; coins in shop till</div>
                        </div>
                        <div class="text-end">
                            <span class="badge bg-primary px-3 py-2 text-sm">Target Balance</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Digital Collections & Combined Turnover -->
<div class="row g-3 mb-4">
    <div class="col-md-3">
        <div class="card stat-card border-left-info shadow-sm h-100">
            <div class="card-body">
                <div class="text-xs font-weight-bold text-info text-uppercase mb-1">UPI Inflows (QR / PhonePe / GPay)</div>
                <div class="h5 mb-0 font-weight-bold">₹ <?= number_format((float)$upiTotal, 2) ?></div>
                <div class="small text-muted mt-1">Credited directly to bank account</div>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card stat-card border-left-warning shadow-sm h-100">
            <div class="card-body">
                <div class="text-xs font-weight-bold text-warning text-uppercase mb-1">Card POS Terminal</div>
                <div class="h5 mb-0 font-weight-bold">₹ <?= number_format((float)$cardTotal, 2) ?></div>
                <div class="small text-muted mt-1">Credit / Debit card swipes</div>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card stat-card border-left-secondary shadow-sm h-100">
            <div class="card-body">
                <div class="text-xs font-weight-bold text-secondary text-uppercase mb-1">Bank Transfers (NEFT / IMPS)</div>
                <div class="h5 mb-0 font-weight-bold">₹ <?= number_format((float)$bankTotal, 2) ?></div>
                <div class="small text-muted mt-1">Direct account transfers</div>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card stat-card border-left-success shadow-sm h-100">
            <div class="card-body">
                <div class="text-xs font-weight-bold text-success text-uppercase mb-1">Combined Daily Collections</div>
                <div class="h5 mb-0 font-weight-bold text-success">₹ <?= number_format((float)$totalDailyCollections, 2) ?></div>
                <div class="small text-muted mt-1">Cash Inflows + Digital Inflows</div>
            </div>
        </div>
    </div>
</div>

<!-- Detailed Register of Payment Movements -->
<div class="card shadow-sm mb-4">
    <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
        <h6 class="m-0 font-weight-bold text-primary">All Payment Transactions (<?= count($payments) ?> Records)</h6>
        <span class="text-muted small">Sequential payment log</span>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Voucher #</th>
                        <th>Transaction Type</th>
                        <th>Source / Party</th>
                        <th>Reference #</th>
                        <th>Payment Mode</th>
                        <th class="text-center">Flow</th>
                        <th class="text-end">Amount (₹)</th>
                        <th>Staff / Handled By</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($payments)): ?>
                        <tr>
                            <td colspan="8" class="text-center py-4 text-muted">
                                No payment transactions recorded for <?= htmlspecialchars($selectedDate) ?>.
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($payments as $p): ?>
                            <?php
                                $isInflow = in_array($p['transaction_type'], ['SALE_RECEIPT', 'REPAIR_RECEIPT'], true);
                                $party = $p['sale_customer'] ?? ($p['repair_customer'] ?? ($p['vendor_name'] ?? 'Walk-in Customer'));
                                $refNo = $p['sale_number'] ?? ($p['repair_number'] ?? ($p['purchase_number'] ?? ($p['return_number'] ?? 'N/A')));
                            ?>
                            <tr>
                                <td class="font-weight-bold text-dark font-monospace"><?= htmlspecialchars($p['payment_number']) ?></td>
                                <td>
                                    <?php if ($p['transaction_type'] === 'SALE_RECEIPT'): ?>
                                        <span class="badge bg-success text-white">Sale Receipt</span>
                                    <?php elseif ($p['transaction_type'] === 'REPAIR_RECEIPT'): ?>
                                        <span class="badge bg-info text-white">Repair Receipt</span>
                                    <?php elseif ($p['transaction_type'] === 'PURCHASE_PAYMENT'): ?>
                                        <span class="badge bg-danger text-white">Vendor Payment</span>
                                    <?php elseif ($p['transaction_type'] === 'SALE_REFUND'): ?>
                                        <span class="badge bg-warning text-dark">Customer Refund</span>
                                    <?php else: ?>
                                        <span class="badge bg-secondary"><?= htmlspecialchars($p['transaction_type']) ?></span>
                                    <?php endif; ?>
                                </td>
                                <td><?= htmlspecialchars($party) ?></td>
                                <td class="font-monospace text-muted small"><?= htmlspecialchars($refNo) ?></td>
                                <td>
                                    <span class="badge <?= $p['payment_mode'] === 'CASH' ? 'bg-dark' : 'bg-light text-dark border' ?>">
                                        <?= htmlspecialchars($p['payment_mode']) ?>
                                    </span>
                                </td>
                                <td class="text-center">
                                    <?php if ($isInflow): ?>
                                        <span class="badge bg-success-subtle text-success border border-success px-2 py-1">INFLOW (+)</span>
                                    <?php else: ?>
                                        <span class="badge bg-danger-subtle text-danger border border-danger px-2 py-1">OUTFLOW (-)</span>
                                    <?php endif; ?>
                                </td>
                                <td class="text-end font-weight-bold <?= $isInflow ? 'text-success' : 'text-danger' ?>">
                                    <?= $isInflow ? '+' : '-' ?> ₹ <?= number_format((float)$p['amount'], 2) ?>
                                </td>
                                <td><span class="small text-muted"><?= htmlspecialchars($p['staff_name']) ?></span></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
                <?php if (!empty($payments)): ?>
                    <tfoot class="table-light font-weight-bold">
                        <tr>
                            <td colspan="6" class="text-end">Total Cash In Drawer for the Day:</td>
                            <td class="text-end text-primary h6 mb-0">₹ <?= number_format((float)$netCashInDrawer, 2) ?></td>
                            <td></td>
                        </tr>
                    </tfoot>
                <?php endif; ?>
            </table>
        </div>
    </div>
</div>
