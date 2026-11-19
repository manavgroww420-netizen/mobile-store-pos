<?php
/**
 * Vendor Payables Ledger (Mahajan Dena Khata)
 * File: views/ledgers/vendors.php
 */
?>
<div class="page-header d-flex justify-content-between align-items-center mb-4">
    <div>
        <h1 class="h3 mb-1">Vendor Payables Ledger <span class="badge badge-danger text-white font-weight-normal ml-2">Mahajan Dena Khata</span></h1>
        <p class="text-muted mb-0">Consolidated wholesale debts, consignment balances, and supplier payables.</p>
    </div>
    <div class="d-flex gap-2">
        <a href="/vendors" class="btn btn-outline-secondary btn-sm">Vendors Master</a>
        <a href="/purchases" class="btn btn-outline-secondary btn-sm">Purchase Records</a>
    </div>
</div>

<!-- Metrics Cards -->
<div class="row g-3 mb-4">
    <div class="col-md-4">
        <div class="card stat-card border-left-danger shadow-sm h-100">
            <div class="card-body">
                <div class="text-xs font-weight-bold text-danger text-uppercase mb-1">Total Market Payable (Kool Dena)</div>
                <div class="h3 mb-0 font-weight-bold text-gray-800">₹ <?= number_format((float)$totalMarketDena, 2) ?></div>
                <div class="small text-muted mt-1">Pending payment to wholesale suppliers</div>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card stat-card border-left-warning shadow-sm h-100">
            <div class="card-body">
                <div class="text-xs font-weight-bold text-warning text-uppercase mb-1">Creditor Vendors</div>
                <div class="h3 mb-0 font-weight-bold text-gray-800"><?= number_format($totalCreditorsCount) ?></div>
                <div class="small text-muted mt-1">Suppliers with active open balances</div>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card stat-card border-left-dark shadow-sm h-100">
            <div class="card-body">
                <div class="text-xs font-weight-bold text-secondary text-uppercase mb-1">Overdue Payables (&gt;30 Days)</div>
                <div class="h3 mb-0 font-weight-bold text-danger">₹ <?= number_format((float)$totalOverdue30, 2) ?></div>
                <div class="small text-muted mt-1">Aged supplier debt requiring settlement</div>
            </div>
        </div>
    </div>
</div>

<!-- Search & Filter Bar -->
<div class="card shadow-sm mb-4">
    <div class="card-body py-3">
        <form method="GET" action="/ledgers/vendors" class="row g-2 align-items-center">
            <div class="col-md-6">
                <div class="input-group input-group-sm">
                    <span class="input-group-text">&#128269;</span>
                    <input type="text" name="q" class="form-control" placeholder="Search supplier business name, contact, or phone..." value="<?= htmlspecialchars($search) ?>">
                </div>
            </div>
            <div class="col-md-4">
                <select name="aging" class="form-select form-select-sm" onchange="this.form.submit()">
                    <option value="ALL" <?= $agingFilter === 'ALL' ? 'selected' : '' ?>>All Aging Periods</option>
                    <option value="0-30" <?= $agingFilter === '0-30' ? 'selected' : '' ?>>Recent (0 - 30 Days)</option>
                    <option value="31-60" <?= $agingFilter === '31-60' ? 'selected' : '' ?>>Aging (31 - 60 Days)</option>
                    <option value="60+" <?= $agingFilter === '60+' ? 'selected' : '' ?>>Overdue (60+ Days)</option>
                </select>
            </div>
            <div class="col-md-2 d-flex gap-2">
                <button type="submit" class="btn btn-sm btn-primary flex-grow-1">Filter</button>
                <?php if ($search !== '' || $agingFilter !== 'ALL'): ?>
                    <a href="/ledgers/vendors" class="btn btn-sm btn-outline-secondary" title="Reset Filters">&#10006;</a>
                <?php endif; ?>
            </div>
        </form>
    </div>
</div>

<!-- Creditors Table -->
<div class="card shadow-sm">
    <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
        <h6 class="m-0 font-weight-bold text-dark">Supplier Payables Directory (<?= count($vendors) ?> records)</h6>
        <span class="text-xs text-muted">Sorted by outstanding balance descending</span>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover table-striped align-middle mb-0">
                <thead class="table-light text-xs text-uppercase text-muted">
                    <tr>
                        <th style="width: 25%;">Vendor / Supplier Details</th>
                        <th style="width: 15%;" class="text-end">Balance Due (Dena)</th>
                        <th style="width: 15%;" class="text-end">Total Consigned</th>
                        <th style="width: 15%;" class="text-end">Total Paid</th>
                        <th style="width: 10%;" class="text-center">Open Consignments</th>
                        <th style="width: 10%;" class="text-center">Aging</th>
                        <th style="width: 10%;" class="text-end">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($vendors)): ?>
                        <tr>
                            <td colspan="7" class="text-center py-5 text-muted">
                                <div style="font-size: 2.5rem; margin-bottom: 0.5rem;">&#127881;</div>
                                <h6>No Outstanding Vendor Payables</h6>
                                <p class="small mb-0">All wholesale consignment invoices are fully settled.</p>
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($vendors as $v): ?>
                            <tr>
                                <td>
                                    <div class="font-weight-bold text-dark"><?= htmlspecialchars($v['business_name']) ?></div>
                                    <div class="text-xs text-muted">
                                        <?php if (!empty($v['contact_person'])): ?>
                                            <span><?= htmlspecialchars($v['contact_person']) ?> &bull; </span>
                                        <?php endif; ?>
                                        <span>&#128222; <?= htmlspecialchars($v['phone'] ?? 'N/A') ?></span>
                                    </div>
                                </td>
                                <td class="text-end">
                                    <span class="badge bg-danger text-white font-weight-bold px-2 py-1" style="font-size: 0.95rem;">
                                        ₹ <?= number_format((float)$v['total_due'], 2) ?>
                                    </span>
                                </td>
                                <td class="text-end text-muted font-monospace">
                                    ₹ <?= number_format((float)$v['total_purchased'], 2) ?>
                                </td>
                                <td class="text-end text-success font-monospace">
                                    ₹ <?= number_format((float)$v['total_paid'], 2) ?>
                                </td>
                                <td class="text-center">
                                    <span class="badge bg-secondary"><?= $v['open_purchases_count'] ?> consignment(s)</span>
                                </td>
                                <td class="text-center">
                                    <?php if ($v['days_old'] > 60): ?>
                                        <span class="badge bg-danger" title="Oldest consignment date: <?= $v['oldest_purchase_date'] ?>"><?= $v['days_old'] ?> days</span>
                                    <?php elseif ($v['days_old'] > 30): ?>
                                        <span class="badge bg-warning text-dark" title="Oldest consignment date: <?= $v['oldest_purchase_date'] ?>"><?= $v['days_old'] ?> days</span>
                                    <?php else: ?>
                                        <span class="badge bg-light text-dark border" title="Oldest consignment date: <?= $v['oldest_purchase_date'] ?>"><?= $v['days_old'] ?> days</span>
                                    <?php endif; ?>
                                </td>
                                <td class="text-end">
                                    <div class="btn-group btn-group-sm">
                                        <a href="/ledgers/vendors/<?= $v['id'] ?>" class="btn btn-outline-primary btn-sm" title="View Statement">Statement</a>
                                        <a href="/ledgers/vendors/<?= $v['id'] ?>/settle" class="btn btn-danger btn-sm" title="Record Payment">&#8377; Pay</a>
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
