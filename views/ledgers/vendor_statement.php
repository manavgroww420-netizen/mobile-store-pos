<?php
/**
 * Vendor Supplier Ledger Statement
 * File: views/ledgers/vendor_statement.php
 */
?>
<div class="page-header d-flex justify-content-between align-items-center mb-4">
    <div>
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb mb-1">
                <li class="breadcrumb-item"><a href="/ledgers/vendors">Vendor Payables</a></li>
                <li class="breadcrumb-item active"><?= htmlspecialchars($vendor['business_name']) ?></li>
            </ol>
        </nav>
        <h1 class="h3 mb-0">Supplier Account Statement</h1>
    </div>
    <div class="d-flex gap-2">
        <?php if ((float)$summary['total_due'] > 0): ?>
            <a href="/ledgers/vendors/<?= $vendor['id'] ?>/settle" class="btn btn-danger">
                <span>&#8377;</span> Record Supplier Payment
            </a>
        <?php endif; ?>
        <a href="/ledgers/vendors" class="btn btn-outline-secondary">Back to Payables</a>
    </div>
</div>

<!-- Vendor Profile & Balance Banner -->
<div class="card shadow-sm mb-4 border-left-danger">
    <div class="card-body">
        <div class="row align-items-center">
            <div class="col-md-7">
                <h4 class="mb-1 text-dark"><?= htmlspecialchars($vendor['business_name']) ?></h4>
                <div class="text-muted d-flex gap-3 align-items-center small">
                    <?php if (!empty($vendor['contact_person'])): ?>
                        <span><strong>Contact:</strong> <?= htmlspecialchars($vendor['contact_person']) ?></span>
                    <?php endif; ?>
                    <?php if (!empty($vendor['phone'])): ?>
                        <span>&#128222; <?= htmlspecialchars($vendor['phone']) ?></span>
                    <?php endif; ?>
                    <?php if (!empty($vendor['gstin'])): ?>
                        <span><strong>GSTIN:</strong> <?= htmlspecialchars($vendor['gstin']) ?></span>
                    <?php endif; ?>
                </div>
            </div>
            <div class="col-md-5 text-md-end mt-3 mt-md-0">
                <div class="text-xs text-uppercase text-muted font-weight-bold">Current Net Payable Due</div>
                <div class="display-6 font-weight-bold <?= (float)$summary['total_due'] > 0 ? 'text-danger' : 'text-success' ?>">
                    ₹ <?= number_format((float)$summary['total_due'], 2) ?>
                </div>
                <?php if ((float)$summary['total_due'] <= 0.00): ?>
                    <span class="badge bg-success">ACCOUNT FULLY SETTLED</span>
                <?php else: ?>
                    <span class="badge bg-danger">OUTSTANDING LIABILITY</span>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- Open Unsettled Consignments -->
<?php if (!empty($openPurchases)): ?>
<div class="card shadow-sm mb-4">
    <div class="card-header bg-light py-2">
        <h6 class="m-0 font-weight-bold text-dark">Open Purchase Consignments Pending Settlement</h6>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-sm table-hover align-middle mb-0">
                <thead class="table-light text-xs text-muted">
                    <tr>
                        <th>Date</th>
                        <th>Purchase #</th>
                        <th>Supplier Inv #</th>
                        <th class="text-end">Total Amount</th>
                        <th class="text-end">Paid Amount</th>
                        <th class="text-end font-weight-bold text-danger">Due Balance</th>
                        <th class="text-center">Status</th>
                        <th class="text-end">Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($openPurchases as $p): ?>
                        <tr>
                            <td><?= htmlspecialchars($p['purchase_date']) ?></td>
                            <td><strong><?= htmlspecialchars($p['purchase_number']) ?></strong></td>
                            <td><?= !empty($p['vendor_invoice_no']) ? htmlspecialchars($p['vendor_invoice_no']) : '<span class="text-muted">—</span>' ?></td>
                            <td class="text-end font-monospace">₹ <?= number_format((float)$p['total_amount'], 2) ?></td>
                            <td class="text-end text-success font-monospace">₹ <?= number_format((float)$p['paid_amount'], 2) ?></td>
                            <td class="text-end font-weight-bold text-danger font-monospace">₹ <?= number_format((float)$p['due_amount'], 2) ?></td>
                            <td class="text-center"><span class="badge bg-warning text-dark"><?= htmlspecialchars($p['payment_status']) ?></span></td>
                            <td class="text-end"><a href="/purchases/<?= $p['id'] ?>" class="btn btn-xs btn-outline-secondary" target="_blank">View Consignment</a></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- Chronological Supplier Ledger Timeline -->
<div class="card shadow-sm">
    <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
        <h6 class="m-0 font-weight-bold text-dark">Chronological Supplier Ledger Timeline</h6>
        <span class="text-xs text-muted">Procurements (Credit) and Payment Vouchers (Debit)</span>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover table-striped align-middle mb-0">
                <thead class="table-light text-xs text-uppercase text-muted">
                    <tr>
                        <th style="width: 14%;">Date</th>
                        <th style="width: 12%;">Type</th>
                        <th style="width: 20%;">Reference #</th>
                        <th style="width: 26%;">Description</th>
                        <th style="width: 14%;" class="text-end text-danger">Consignment (+)</th>
                        <th style="width: 14%;" class="text-end text-success">Payment (-)</th>
                        <th style="width: 14%;" class="text-end font-weight-bold">Balance Due</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($timeline)): ?>
                        <tr>
                            <td colspan="7" class="text-center py-4 text-muted">No consignment or payment transactions found for this vendor.</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($timeline as $entry): ?>
                            <tr>
                                <td class="font-monospace small"><?= htmlspecialchars($entry['date']) ?></td>
                                <td>
                                    <?php if ($entry['type'] === 'PURCHASE'): ?>
                                        <span class="badge bg-secondary">PURCHASE</span>
                                    <?php elseif ($entry['type'] === 'PAYMENT'): ?>
                                        <span class="badge bg-success">PAYMENT</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <a href="<?= htmlspecialchars($entry['url']) ?>" class="font-weight-bold text-decoration-none">
                                        <?= htmlspecialchars($entry['ref']) ?>
                                    </a>
                                </td>
                                <td class="small text-dark"><?= htmlspecialchars($entry['description']) ?></td>
                                <td class="text-end font-monospace text-danger">
                                    <?= (float)$entry['credit'] > 0 ? ('₹ ' . number_format((float)$entry['credit'], 2)) : '—' ?>
                                </td>
                                <td class="text-end font-monospace text-success">
                                    <?= (float)$entry['debit'] > 0 ? ('₹ ' . number_format((float)$entry['debit'], 2)) : '—' ?>
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
