<?php
/**
 * Customer Account Statement / Passbook (Khata Statement)
 * File: views/ledgers/customer_statement.php
 */
?>
<div class="page-header d-flex justify-content-between align-items-center mb-4">
    <div>
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb mb-1">
                <li class="breadcrumb-item"><a href="/ledgers/customers">Udhar Khata</a></li>
                <li class="breadcrumb-item active"><?= htmlspecialchars($customer['name']) ?></li>
            </ol>
        </nav>
        <h1 class="h3 mb-0">Customer Account Statement <span class="badge bg-secondary font-weight-normal ms-2">Passbook</span></h1>
    </div>
    <div class="d-flex gap-2">
        <?php if ((float)$customer['total_due'] > 0): ?>
            <a href="/ledgers/customers/<?= urlencode($customer['identifier']) ?>/settle" class="btn btn-success">
                <span>&#8377;</span> Settle Balance (Record Repayment)
            </a>
        <?php endif; ?>
        <a href="/ledgers/customers" class="btn btn-outline-secondary">Back to Khata Directory</a>
    </div>
</div>

<!-- Customer Profile & Balance Banner -->
<div class="card shadow-sm mb-4 border-left-primary">
    <div class="card-body">
        <div class="row align-items-center">
            <div class="col-md-7">
                <h4 class="mb-1 text-dark"><?= htmlspecialchars($customer['name']) ?></h4>
                <div class="text-muted d-flex gap-3 align-items-center">
                    <?php if ($customer['phone'] !== ''): ?>
                        <span>&#128222; <strong>Phone:</strong> <?= htmlspecialchars($customer['phone']) ?></span>
                    <?php else: ?>
                        <span class="fst-italic text-secondary">No phone registered</span>
                    <?php endif; ?>
                    <span>&bull;</span>
                    <span><strong>Active Unpaid Bills:</strong> <?= count($openSales) + count($openRepairs) ?></span>
                </div>
            </div>
            <div class="col-md-5 text-md-end mt-3 mt-md-0">
                <div class="text-xs text-uppercase text-muted font-weight-bold">Current Net Balance Due</div>
                <div class="display-6 font-weight-bold <?= (float)$customer['total_due'] > 0 ? 'text-danger' : 'text-success' ?>">
                    ₹ <?= number_format((float)$customer['total_due'], 2) ?>
                </div>
                <?php if ((float)$customer['total_due'] <= 0.00): ?>
                    <span class="badge bg-success">ACCOUNT FULLY SETTLED</span>
                <?php else: ?>
                    <span class="badge bg-danger">OUTSTANDING PAYMENT REQUIRED</span>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- Open Unsettled Bills Summary -->
<?php if (!empty($openSales) || !empty($openRepairs)): ?>
<div class="card shadow-sm mb-4">
    <div class="card-header bg-light py-2">
        <h6 class="m-0 font-weight-bold text-dark">Open Invoices &amp; Service Charges Pending Settlement</h6>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-sm table-hover align-middle mb-0">
                <thead class="table-light text-xs text-muted">
                    <tr>
                        <th>Date</th>
                        <th>Type</th>
                        <th>Reference #</th>
                        <th class="text-end">Total Amount</th>
                        <th class="text-end">Paid Amount</th>
                        <th class="text-end font-weight-bold text-danger">Due Balance</th>
                        <th class="text-center">Status</th>
                        <th class="text-end">Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($openSales as $s): ?>
                        <tr>
                            <td><?= htmlspecialchars($s['sale_date']) ?></td>
                            <td><span class="badge bg-light text-dark border">Counter Sale</span></td>
                            <td><strong><?= htmlspecialchars($s['sale_number']) ?></strong></td>
                            <td class="text-end font-monospace">₹ <?= number_format((float)$s['final_total_amount'], 2) ?></td>
                            <td class="text-end text-success font-monospace">₹ <?= number_format((float)$s['paid_amount'], 2) ?></td>
                            <td class="text-end font-weight-bold text-danger font-monospace">₹ <?= number_format((float)$s['due_amount'], 2) ?></td>
                            <td class="text-center"><span class="badge bg-warning text-dark"><?= htmlspecialchars($s['payment_status']) ?></span></td>
                            <td class="text-end"><a href="/sales/<?= $s['id'] ?>" class="btn btn-xs btn-outline-secondary" target="_blank">View Bill</a></td>
                        </tr>
                    <?php endforeach; ?>
                    <?php foreach ($openRepairs as $r): ?>
                        <tr>
                            <td><?= htmlspecialchars($r['repair_date']) ?></td>
                            <td><span class="badge bg-light text-primary border">Customer Repair</span></td>
                            <td><strong><?= htmlspecialchars($r['repair_number']) ?></strong></td>
                            <td class="text-end font-monospace">₹ <?= number_format((float)$r['amount_charged'], 2) ?></td>
                            <td class="text-end text-success font-monospace">₹ <?= number_format((float)$r['paid_amount'], 2) ?></td>
                            <td class="text-end font-weight-bold text-danger font-monospace">₹ <?= number_format((float)$r['due_amount'], 2) ?></td>
                            <td class="text-center"><span class="badge bg-warning text-dark"><?= htmlspecialchars($r['payment_status']) ?></span></td>
                            <td class="text-end"><a href="/repairs/<?= $r['id'] ?>" class="btn btn-xs btn-outline-secondary" target="_blank">View Job</a></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- Chronological Passbook Timeline -->
<div class="card shadow-sm">
    <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
        <h6 class="m-0 font-weight-bold text-dark">Chronological Transaction Ledger (Passbook)</h6>
        <span class="text-xs text-muted">Includes purchases, repair jobs, returns, and payment installments</span>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover table-striped align-middle mb-0 font-monospace-rows">
                <thead class="table-light text-xs text-uppercase text-muted">
                    <tr>
                        <th style="width: 14%;">Date &amp; Time</th>
                        <th style="width: 12%;">Type</th>
                        <th style="width: 18%;">Reference #</th>
                        <th style="width: 26%;">Description</th>
                        <th style="width: 10%;" class="text-end text-danger">Debit (+)</th>
                        <th style="width: 10%;" class="text-end text-success">Credit (-)</th>
                        <th style="width: 10%;" class="text-end font-weight-bold">Balance</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($timeline)): ?>
                        <tr>
                            <td colspan="7" class="text-center py-4 text-muted">No transaction entries found.</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($timeline as $entry): ?>
                            <tr>
                                <td>
                                    <div class="small text-dark font-monospace"><?= htmlspecialchars($entry['date']) ?></div>
                                    <div class="text-xs text-muted"><?= htmlspecialchars($entry['time']) ?></div>
                                </td>
                                <td>
                                    <?php if ($entry['type'] === 'SALE'): ?>
                                        <span class="badge bg-primary">SALE</span>
                                    <?php elseif ($entry['type'] === 'REPAIR'): ?>
                                        <span class="badge bg-info text-dark">REPAIR</span>
                                    <?php elseif ($entry['type'] === 'PAYMENT'): ?>
                                        <span class="badge bg-success">PAYMENT</span>
                                    <?php elseif ($entry['type'] === 'RETURN_CREDIT'): ?>
                                        <span class="badge bg-warning text-dark">RETURN OFFSET</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <a href="<?= htmlspecialchars($entry['url']) ?>" class="font-weight-bold text-decoration-none">
                                        <?= htmlspecialchars($entry['ref']) ?>
                                    </a>
                                </td>
                                <td class="small text-dark">
                                    <?= htmlspecialchars($entry['description']) ?>
                                </td>
                                <td class="text-end font-monospace text-danger">
                                    <?= (float)$entry['debit'] > 0 ? ('₹ ' . number_format((float)$entry['debit'], 2)) : '—' ?>
                                </td>
                                <td class="text-end font-monospace text-success">
                                    <?= (float)$entry['credit'] > 0 ? ('₹ ' . number_format((float)$entry['credit'], 2)) : '—' ?>
                                </td>
                                <td class="text-end font-monospace font-weight-bold <?= (float)$entry['balance'] > 0 ? 'text-danger' : 'text-success' ?>">
                                    ₹ <?= number_format((float)$entry['balance'], 2) ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
