<?php
/**
 * Universal Payments & Receipts Register
 * File: views/ledgers/payments.php
 */
?>
<div class="page-header d-flex justify-content-between align-items-center mb-4">
    <div>
        <h1 class="h3 mb-1">Universal Payments Register</h1>
        <p class="text-muted mb-0">Master cashbook ledger of all customer receipts, repair incomes, supplier payouts, and return refunds.</p>
    </div>
    <div class="d-flex gap-2">
        <a href="/ledgers/customers" class="btn btn-outline-primary btn-sm">Customer Khata (Udhar)</a>
        <a href="/ledgers/vendors" class="btn btn-outline-danger btn-sm">Vendor Payables (Dena)</a>
    </div>
</div>

<!-- Financial Flow Summary Cards -->
<div class="row g-3 mb-4">
    <div class="col-md-4">
        <div class="card stat-card border-left-success shadow-sm h-100">
            <div class="card-body">
                <div class="text-xs font-weight-bold text-success text-uppercase mb-1">Total Inflow Receipts (+)</div>
                <div class="h3 mb-0 font-weight-bold text-success">₹ <?= number_format((float)$totalInflow, 2) ?></div>
                <div class="small text-muted mt-1">Counter sales and repair collections</div>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card stat-card border-left-danger shadow-sm h-100">
            <div class="card-body">
                <div class="text-xs font-weight-bold text-danger text-uppercase mb-1">Total Outflow Disbursements (-)</div>
                <div class="h3 mb-0 font-weight-bold text-danger">₹ <?= number_format((float)$totalOutflow, 2) ?></div>
                <div class="small text-muted mt-1">Wholesale vendor payouts and return refunds</div>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card stat-card border-left-primary shadow-sm h-100">
            <div class="card-body">
                <div class="text-xs font-weight-bold text-primary text-uppercase mb-1">Net Cash Flow</div>
                <div class="h3 mb-0 font-weight-bold <?= (float)$netCashFlow >= 0 ? 'text-primary' : 'text-danger' ?>">
                    ₹ <?= number_format((float)$netCashFlow, 2) ?>
                </div>
                <div class="small text-muted mt-1">Filtered period cashbook balance</div>
            </div>
        </div>
    </div>
</div>

<!-- Filters Bar -->
<div class="card shadow-sm mb-4">
    <div class="card-body py-3">
        <form method="GET" action="/ledgers/payments" class="row g-2 align-items-center">
            <div class="col-md-3">
                <input type="text" name="q" class="form-control form-control-sm" placeholder="Voucher #, UTR, party name..." value="<?= htmlspecialchars($search) ?>">
            </div>
            <div class="col-md-2">
                <select name="type" class="form-select form-select-sm">
                    <option value="ALL" <?= $typeFilter === 'ALL' ? 'selected' : '' ?>>All Transaction Types</option>
                    <option value="SALE_RECEIPT" <?= $typeFilter === 'SALE_RECEIPT' ? 'selected' : '' ?>>Sale Receipts (Inflow)</option>
                    <option value="REPAIR_RECEIPT" <?= $typeFilter === 'REPAIR_RECEIPT' ? 'selected' : '' ?>>Repair Receipts (Inflow)</option>
                    <option value="PURCHASE_PAYMENT" <?= $typeFilter === 'PURCHASE_PAYMENT' ? 'selected' : '' ?>>Vendor Payments (Outflow)</option>
                    <option value="SALE_REFUND" <?= $typeFilter === 'SALE_REFUND' ? 'selected' : '' ?>>Sale Refunds (Outflow)</option>
                </select>
            </div>
            <div class="col-md-2">
                <select name="mode" class="form-select form-select-sm">
                    <option value="ALL" <?= $modeFilter === 'ALL' ? 'selected' : '' ?>>All Modes</option>
                    <option value="CASH" <?= $modeFilter === 'CASH' ? 'selected' : '' ?>>Cash</option>
                    <option value="UPI" <?= $modeFilter === 'UPI' ? 'selected' : '' ?>>UPI</option>
                    <option value="CARD" <?= $modeFilter === 'CARD' ? 'selected' : '' ?>>Card</option>
                    <option value="BANK_TRANSFER" <?= $modeFilter === 'BANK_TRANSFER' ? 'selected' : '' ?>>Bank Transfer</option>
                    <option value="CHEQUE" <?= $modeFilter === 'CHEQUE' ? 'selected' : '' ?>>Cheque</option>
                </select>
            </div>
            <div class="col-md-3 d-flex gap-1">
                <input type="date" name="date_from" class="form-control form-control-sm" value="<?= htmlspecialchars($dateFrom) ?>" title="From Date">
                <input type="date" name="date_to" class="form-control form-control-sm" value="<?= htmlspecialchars($dateTo) ?>" title="To Date">
            </div>
            <div class="col-md-2 d-flex gap-2">
                <button type="submit" class="btn btn-sm btn-primary flex-grow-1">Filter</button>
                <?php if ($search !== '' || $typeFilter !== 'ALL' || $modeFilter !== 'ALL' || $dateFrom !== '' || $dateTo !== ''): ?>
                    <a href="/ledgers/payments" class="btn btn-sm btn-outline-secondary" title="Reset Filters">&#10006;</a>
                <?php endif; ?>
            </div>
        </form>
    </div>
</div>

<!-- Transactions Table -->
<div class="card shadow-sm mb-4">
    <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
        <h6 class="m-0 font-weight-bold text-dark">Payment Transactions (<?= number_format($totalRows) ?> records)</h6>
        <span class="text-xs text-muted">Page <?= $currentPage ?> of <?= $totalPages ?></span>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover table-striped align-middle mb-0 font-monospace-rows">
                <thead class="table-light text-xs text-uppercase text-muted">
                    <tr>
                        <th style="width: 12%;">Date</th>
                        <th style="width: 16%;">Voucher #</th>
                        <th style="width: 14%;">Type</th>
                        <th style="width: 20%;">Party / Source</th>
                        <th style="width: 10%;">Mode</th>
                        <th style="width: 14%;" class="text-end">Amount</th>
                        <th style="width: 14%;">Logged By</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($payments)): ?>
                        <tr>
                            <td colspan="7" class="text-center py-5 text-muted">
                                <div style="font-size: 2rem; margin-bottom: 0.5rem;">&#128176;</div>
                                <h6>No Payment Transactions Found</h6>
                                <p class="small mb-0">No records match the selected date range or filter criteria.</p>
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($payments as $p): ?>
                            <?php 
                                $isInflow = in_array($p['transaction_type'], ['SALE_RECEIPT', 'REPAIR_RECEIPT'], true);
                            ?>
                            <tr>
                                <td class="small font-monospace"><?= htmlspecialchars($p['payment_date']) ?></td>
                                <td>
                                    <strong class="font-monospace small"><?= htmlspecialchars($p['payment_number']) ?></strong>
                                    <?php if (!empty($p['transaction_ref'])): ?>
                                        <div class="text-xs text-muted">Ref: <?= htmlspecialchars($p['transaction_ref']) ?></div>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($p['transaction_type'] === 'SALE_RECEIPT'): ?>
                                        <span class="badge bg-success">SALE RECEIPT</span>
                                    <?php elseif ($p['transaction_type'] === 'REPAIR_RECEIPT'): ?>
                                        <span class="badge bg-info text-dark">REPAIR RECEIPT</span>
                                    <?php elseif ($p['transaction_type'] === 'PURCHASE_PAYMENT'): ?>
                                        <span class="badge bg-danger">PURCHASE VOUCHER</span>
                                    <?php elseif ($p['transaction_type'] === 'SALE_REFUND'): ?>
                                        <span class="badge bg-warning text-dark">SALE REFUND</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($p['transaction_type'] === 'SALE_RECEIPT'): ?>
                                        <div class="small font-weight-bold text-dark"><?= htmlspecialchars($p['sale_customer_name'] ?? 'Walk-in Customer') ?></div>
                                        <div class="text-xs text-muted">Bill: <a href="/sales/<?= $p['reference_id'] ?>" class="text-decoration-none"><?= htmlspecialchars($p['sale_number'] ?? ('#' . $p['reference_id'])) ?></a></div>
                                    <?php elseif ($p['transaction_type'] === 'REPAIR_RECEIPT'): ?>
                                        <div class="small font-weight-bold text-dark"><?= htmlspecialchars($p['repair_customer_name'] ?? 'Walk-in') ?></div>
                                        <div class="text-xs text-muted">Job: <a href="/repairs/<?= $p['reference_id'] ?>" class="text-decoration-none"><?= htmlspecialchars($p['repair_number'] ?? ('#' . $p['reference_id'])) ?></a></div>
                                    <?php elseif ($p['transaction_type'] === 'PURCHASE_PAYMENT'): ?>
                                        <div class="small font-weight-bold text-dark"><?= htmlspecialchars($p['vendor_name'] ?? 'Supplier') ?></div>
                                        <div class="text-xs text-muted">Consignment: <a href="/purchases/<?= $p['reference_id'] ?>" class="text-decoration-none">#<?= $p['reference_id'] ?></a></div>
                                    <?php elseif ($p['transaction_type'] === 'SALE_REFUND'): ?>
                                        <div class="small font-weight-bold text-dark"><?= htmlspecialchars($p['sale_customer_name'] ?? 'Customer') ?></div>
                                        <div class="text-xs text-muted">Return: <a href="/returns/<?= $p['reference_id'] ?>" class="text-decoration-none"><?= htmlspecialchars($p['return_number'] ?? ('#' . $p['reference_id'])) ?></a></div>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <span class="badge bg-light text-dark border"><?= htmlspecialchars($p['payment_mode']) ?></span>
                                </td>
                                <td class="text-end font-monospace font-weight-bold <?= $isInflow ? 'text-success' : 'text-danger' ?>">
                                    <?= $isInflow ? '+' : '-' ?> ₹ <?= number_format((float)$p['amount'], 2) ?>
                                </td>
                                <td class="small text-muted">
                                    <?= htmlspecialchars($p['received_by_name']) ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
    <?php if ($totalPages > 1): ?>
        <div class="card-footer bg-white py-3 d-flex justify-content-between align-items-center">
            <div class="small text-muted">Showing page <?= $currentPage ?> of <?= $totalPages ?></div>
            <ul class="pagination pagination-sm mb-0">
                <li class="page-item <?= $currentPage <= 1 ? 'disabled' : '' ?>">
                    <a class="page-link" href="?page=<?= $currentPage - 1 ?>&type=<?= urlencode($typeFilter) ?>&mode=<?= urlencode($modeFilter) ?>&date_from=<?= urlencode($dateFrom) ?>&date_to=<?= urlencode($dateTo) ?>&q=<?= urlencode($search) ?>">&laquo; Prev</a>
                </li>
                <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                    <?php if ($i === 1 || $i === $totalPages || abs($i - $currentPage) <= 2): ?>
                        <li class="page-item <?= $i === $currentPage ? 'active' : '' ?>">
                            <a class="page-link" href="?page=<?= $i ?>&type=<?= urlencode($typeFilter) ?>&mode=<?= urlencode($modeFilter) ?>&date_from=<?= urlencode($dateFrom) ?>&date_to=<?= urlencode($dateTo) ?>&q=<?= urlencode($search) ?>"><?= $i ?></a>
                        </li>
                    <?php elseif ($i === 2 || $i === $totalPages - 1): ?>
                        <li class="page-item disabled"><span class="page-link">...</span></li>
                    <?php endif; ?>
                <?php endfor; ?>
                <li class="page-item <?= $currentPage >= $totalPages ? 'disabled' : '' ?>">
                    <a class="page-link" href="?page=<?= $currentPage + 1 ?>&type=<?= urlencode($typeFilter) ?>&mode=<?= urlencode($modeFilter) ?>&date_from=<?= urlencode($dateFrom) ?>&date_to=<?= urlencode($dateTo) ?>&q=<?= urlencode($search) ?>">Next &raquo;</a>
                </li>
            </ul>
        </div>
    <?php endif; ?>
</div>
