<?php
/**
 * Purchase Consignments Listing View
 * File: views/purchases/index.php
 */
use AMH\Security;
?>

<div class="page-header" style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 24px; flex-wrap: wrap; gap: 16px;">
    <div>
        <h1 style="font-size: 1.5rem; font-weight: 700; color: var(--text-main); margin-bottom: 4px;">
            <?= Security::e($pageTitle) ?>
        </h1>
        <p style="color: var(--text-muted); font-size: 0.875rem;">
            Consignment procurement records, vendor delivery challans, and wholesale payments.
        </p>
    </div>
    <?php if ($canManage): ?>
        <div>
            <a href="/purchases/create" class="btn btn-primary">
                <span>&#43;</span> Record New Purchase
            </a>
        </div>
    <?php endif; ?>
</div>

<!-- Filters Bar -->
<div class="card" style="margin-bottom: 20px;">
    <div class="card-body" style="padding: 16px;">
        <form method="GET" action="/purchases" style="display: flex; gap: 12px; flex-wrap: wrap; align-items: center;">
            <div style="flex: 2; min-width: 200px;">
                <input type="text" name="q" value="<?= Security::e($search) ?>" placeholder="Search by Purchase #, Invoice ref, or Vendor..." class="form-control" style="width: 100%;">
            </div>

            <div style="flex: 1; min-width: 160px;">
                <select name="vendor_id" class="form-control" style="width: 100%;">
                    <option value="">All Vendors</option>
                    <?php foreach ($vendors as $v): ?>
                        <option value="<?= (int)$v['id'] ?>" <?= ($selectedVendor === (int)$v['id']) ? 'selected' : '' ?>>
                            <?= Security::e($v['business_name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div style="flex: 1; min-width: 140px;">
                <select name="status" class="form-control" style="width: 100%;">
                    <option value="ALL" <?= ($selectedStatus === 'ALL') ? 'selected' : '' ?>>All Payments</option>
                    <option value="UNPAID" <?= ($selectedStatus === 'UNPAID') ? 'selected' : '' ?>>Unpaid</option>
                    <option value="PARTIAL" <?= ($selectedStatus === 'PARTIAL') ? 'selected' : '' ?>>Partially Paid</option>
                    <option value="PAID" <?= ($selectedStatus === 'PAID') ? 'selected' : '' ?>>Fully Paid</option>
                </select>
            </div>

            <div style="display: flex; gap: 6px; align-items: center;">
                <input type="date" name="date_from" value="<?= Security::e($dateFrom) ?>" class="form-control" style="font-size: 0.8rem; padding: 6px 10px;" title="From Date">
                <span style="color: var(--text-muted);">&ndash;</span>
                <input type="date" name="date_to" value="<?= Security::e($dateTo) ?>" class="form-control" style="font-size: 0.8rem; padding: 6px 10px;" title="To Date">
            </div>

            <div style="display: flex; gap: 8px;">
                <button type="submit" class="btn btn-primary">Filter</button>
                <?php if ($search !== '' || $selectedVendor > 0 || $selectedStatus !== 'ALL' || $dateFrom !== '' || $dateTo !== ''): ?>
                    <a href="/purchases" class="btn btn-outline">Reset</a>
                <?php endif; ?>
            </div>
        </form>
    </div>
</div>

<!-- Purchases Listing Table -->
<div class="card">
    <div class="card-header" style="background: #fff;">
        <div class="card-title">
            <span>&#128229;</span> Vendor Purchase Consignments (<?= number_format($totalItems) ?> records found)
        </div>
    </div>
    <div class="card-body" style="padding: 0;">
        <div class="table-responsive">
            <table class="table" style="width: 100%; border-collapse: collapse; font-size: 0.875rem;">
                <thead>
                    <tr style="background: var(--bg-main); border-bottom: 1px solid var(--border-color); text-align: left; font-size: 0.78rem; text-transform: uppercase; color: var(--text-muted);">
                        <th style="padding: 12px 16px;">Purchase #</th>
                        <th style="padding: 12px 16px;">Vendor Supplier</th>
                        <th style="padding: 12px 16px;">Date</th>
                        <th style="padding: 12px 16px;">Invoice / Challan</th>
                        <th style="padding: 12px 16px; text-align: center;">Items</th>
                        <th style="padding: 12px 16px;">Payment Status</th>
                        <?php if ($canViewCost): ?>
                            <th style="padding: 12px 16px; text-align: right;">Total Amount</th>
                            <th style="padding: 12px 16px; text-align: right;">Paid</th>
                            <th style="padding: 12px 16px; text-align: right;">Balance Due</th>
                        <?php endif; ?>
                        <th style="padding: 12px 16px; text-align: center;">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($purchases)): ?>
                        <tr>
                            <td colspan="<?= $canViewCost ? 10 : 7 ?>" style="padding: 40px; text-align: center; color: var(--text-muted);">
                                <div style="font-size: 2rem; margin-bottom: 8px;">&#128229;</div>
                                <div style="font-weight: 600; font-size: 1rem; margin-bottom: 4px;">No purchase records found</div>
                                <div style="font-size: 0.85rem;">Adjust search filters or log a new purchase consignment.</div>
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($purchases as $p): ?>
                            <tr style="border-bottom: 1px solid var(--border-color);">
                                <td style="padding: 12px 16px; font-weight: 700; font-family: var(--font-mono);">
                                    <a href="/purchases/<?= (int)$p['id'] ?>" style="color: var(--primary); text-decoration: none;">
                                        <?= Security::e($p['purchase_number']) ?>
                                    </a>
                                </td>
                                <td style="padding: 12px 16px;">
                                    <div style="font-weight: 600; color: var(--text-main);">
                                        <a href="/vendors/<?= (int)$p['vendor_id'] ?>" style="color: var(--text-main); text-decoration: none;">
                                            <?= Security::e($p['vendor_name']) ?>
                                        </a>
                                    </div>
                                    <div style="font-size: 0.75rem; color: var(--text-muted); font-family: var(--font-mono);">
                                        <?= Security::e($p['vendor_phone']) ?>
                                    </div>
                                </td>
                                <td style="padding: 12px 16px; font-family: var(--font-mono); color: var(--text-muted); font-size: 0.82rem;">
                                    <?= Security::e($p['purchase_date']) ?>
                                </td>
                                <td style="padding: 12px 16px; font-family: var(--font-mono);">
                                    <?= !empty($p['vendor_invoice_no']) ? Security::e($p['vendor_invoice_no']) : '<span style="color: var(--text-muted);">--</span>' ?>
                                </td>
                                <td style="padding: 12px 16px; text-align: center; font-weight: 600;">
                                    <span class="badge" style="background: #f1f5f9; color: #334155; font-size: 0.75rem; padding: 2px 6px; border-radius: 4px;">
                                        <?= (int)$p['items_count'] ?> lines
                                    </span>
                                </td>
                                <td style="padding: 12px 16px;">
                                    <?php if ($p['payment_status'] === 'PAID'): ?>
                                        <span class="badge" style="background: #ecfdf5; color: #047857; font-size: 0.72rem; padding: 2px 6px; border-radius: 4px; font-weight: 700;">PAID</span>
                                    <?php elseif ($p['payment_status'] === 'PARTIAL'): ?>
                                        <span class="badge" style="background: #fffbeb; color: #b45309; font-size: 0.72rem; padding: 2px 6px; border-radius: 4px; font-weight: 700;">PARTIAL</span>
                                    <?php else: ?>
                                        <span class="badge" style="background: #fef2f2; color: #b91c1c; font-size: 0.72rem; padding: 2px 6px; border-radius: 4px; font-weight: 700;">UNPAID</span>
                                    <?php endif; ?>
                                </td>
                                <?php if ($canViewCost): ?>
                                    <td style="padding: 12px 16px; text-align: right; font-weight: 700; font-family: var(--font-mono);">
                                        ₹ <?= number_format((float)$p['total_amount'], 2) ?>
                                    </td>
                                    <td style="padding: 12px 16px; text-align: right; font-family: var(--font-mono); color: #059669;">
                                        ₹ <?= number_format((float)$p['paid_amount'], 2) ?>
                                    </td>
                                    <td style="padding: 12px 16px; text-align: right; font-weight: 700; font-family: var(--font-mono); color: <?= (float)$p['due_amount'] > 0 ? '#dc2626' : '#059669' ?>;">
                                        ₹ <?= number_format((float)$p['due_amount'], 2) ?>
                                    </td>
                                <?php endif; ?>
                                <td style="padding: 12px 16px; text-align: center;">
                                    <div style="display: flex; gap: 6px; justify-content: center;">
                                        <a href="/purchases/<?= (int)$p['id'] ?>" class="btn btn-outline btn-sm" style="font-size: 0.75rem; padding: 3px 8px;">
                                            Inspect
                                        </a>
                                        <?php if ($canManage && (float)$p['due_amount'] > 0): ?>
                                            <a href="/purchases/<?= (int)$p['id'] ?>/payments/create" class="btn btn-secondary btn-sm" style="font-size: 0.75rem; padding: 3px 8px;">
                                                Pay Due
                                            </a>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
    <?php if ($totalPages > 1): ?>
        <div class="card-footer" style="display: flex; justify-content: space-between; align-items: center; padding: 12px 20px;">
            <div style="font-size: 0.85rem; color: var(--text-muted);">
                Page <strong><?= $currentPage ?></strong> of <strong><?= $totalPages ?></strong> (<?= number_format($totalItems) ?> purchases)
            </div>
            <div style="display: flex; gap: 6px;">
                <?php if ($currentPage > 1): ?>
                    <a href="?page=<?= $currentPage - 1 ?>&vendor_id=<?= $selectedVendor ?>&status=<?= Security::e($selectedStatus) ?>&date_from=<?= urlencode($dateFrom) ?>&date_to=<?= urlencode($dateTo) ?>&q=<?= urlencode($search) ?>" class="btn btn-outline btn-sm">&laquo; Prev</a>
                <?php endif; ?>
                <?php for ($i = max(1, $currentPage - 2); $i <= min($totalPages, $currentPage + 2); $i++): ?>
                    <a href="?page=<?= $i ?>&vendor_id=<?= $selectedVendor ?>&status=<?= Security::e($selectedStatus) ?>&date_from=<?= urlencode($dateFrom) ?>&date_to=<?= urlencode($dateTo) ?>&q=<?= urlencode($search) ?>" class="btn btn-sm <?= ($i === $currentPage) ? 'btn-primary' : 'btn-outline' ?>">
                        <?= $i ?>
                    </a>
                <?php endfor; ?>
                <?php if ($currentPage < $totalPages): ?>
                    <a href="?page=<?= $currentPage + 1 ?>&vendor_id=<?= $selectedVendor ?>&status=<?= Security::e($selectedStatus) ?>&date_from=<?= urlencode($dateFrom) ?>&date_to=<?= urlencode($dateTo) ?>&q=<?= urlencode($search) ?>" class="btn btn-outline btn-sm">Next &raquo;</a>
                <?php endif; ?>
            </div>
        </div>
    <?php endif; ?>
</div>
