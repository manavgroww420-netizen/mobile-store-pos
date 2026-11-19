<?php
/**
 * Vendor Directory Listing View
 * File: views/vendors/index.php
 */
use AMH\Security;
?>

<div class="page-header" style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 24px; flex-wrap: wrap; gap: 16px;">
    <div>
        <h1 style="font-size: 1.5rem; font-weight: 700; color: var(--text-main); margin-bottom: 4px;">
            <?= Security::e($pageTitle) ?>
        </h1>
        <p style="color: var(--text-muted); font-size: 0.875rem;">
            Registered mobile wholesale distributors, spare parts dealers, and accessory suppliers.
        </p>
    </div>
    <?php if ($canManage): ?>
        <div>
            <a href="/vendors/create" class="btn btn-primary">
                <span>&#43;</span> Register New Vendor
            </a>
        </div>
    <?php endif; ?>
</div>

<!-- Search & Status Filter Bar -->
<div class="card" style="margin-bottom: 20px;">
    <div class="card-body" style="padding: 16px;">
        <form method="GET" action="/vendors" style="display: flex; gap: 12px; flex-wrap: wrap; align-items: center;">
            <div style="flex: 2; min-width: 240px;">
                <input type="text" name="q" value="<?= Security::e($search) ?>" placeholder="Search by business name, contact person, phone, or GSTIN..." class="form-control" style="width: 100%;">
            </div>

            <div style="flex: 1; min-width: 160px;">
                <select name="status" class="form-control" style="width: 100%;">
                    <option value="ALL" <?= ($statusFilter === 'ALL') ? 'selected' : '' ?>>All Statuses</option>
                    <option value="ACTIVE" <?= ($statusFilter === 'ACTIVE') ? 'selected' : '' ?>>Active Only</option>
                    <option value="INACTIVE" <?= ($statusFilter === 'INACTIVE') ? 'selected' : '' ?>>Inactive Only</option>
                </select>
            </div>

            <div style="display: flex; gap: 8px;">
                <button type="submit" class="btn btn-primary">Filter</button>
                <?php if ($search !== '' || $statusFilter !== 'ALL'): ?>
                    <a href="/vendors" class="btn btn-outline">Reset</a>
                <?php endif; ?>
            </div>
        </form>
    </div>
</div>

<!-- Vendors Table -->
<div class="card">
    <div class="card-header" style="background: #fff;">
        <div class="card-title">
            <span>&#127970;</span> Vendor Directory (<?= number_format($totalItems) ?> registered suppliers)
        </div>
    </div>
    <div class="card-body" style="padding: 0;">
        <div class="table-responsive">
            <table class="table" style="width: 100%; border-collapse: collapse; font-size: 0.875rem;">
                <thead>
                    <tr style="background: var(--bg-main); border-bottom: 1px solid var(--border-color); text-align: left; font-size: 0.78rem; text-transform: uppercase; color: var(--text-muted);">
                        <th style="padding: 12px 16px;">Business Name</th>
                        <th style="padding: 12px 16px;">Contact Person</th>
                        <th style="padding: 12px 16px;">Phone &amp; Alt</th>
                        <th style="padding: 12px 16px;">Status</th>
                        <th style="padding: 12px 16px; text-align: center;">Orders</th>
                        <?php if ($canViewCost): ?>
                            <th style="padding: 12px 16px; text-align: right;">Total Purchased</th>
                            <th style="padding: 12px 16px; text-align: right;">Balance Due</th>
                        <?php endif; ?>
                        <th style="padding: 12px 16px; text-align: center;">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($vendors)): ?>
                        <tr>
                            <td colspan="<?= $canViewCost ? 8 : 6 ?>" style="padding: 40px; text-align: center; color: var(--text-muted);">
                                <div style="font-size: 2rem; margin-bottom: 8px;">&#127970;</div>
                                <div style="font-weight: 600; font-size: 1rem; margin-bottom: 4px;">No vendors found</div>
                                <div style="font-size: 0.85rem;">Adjust search filters or register a new vendor.</div>
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($vendors as $v): ?>
                            <tr style="border-bottom: 1px solid var(--border-color);">
                                <td style="padding: 12px 16px;">
                                    <div style="font-weight: 700; color: var(--text-main);">
                                        <a href="/vendors/<?= (int)$v['id'] ?>" style="color: var(--primary); text-decoration: none;">
                                            <?= Security::e($v['business_name']) ?>
                                        </a>
                                    </div>
                                    <?php if (!empty($v['gstin'])): ?>
                                        <div style="font-size: 0.75rem; color: var(--text-muted); font-family: var(--font-mono);">
                                            GSTIN: <?= Security::e($v['gstin']) ?>
                                        </div>
                                    <?php endif; ?>
                                </td>
                                <td style="padding: 12px 16px;">
                                    <?= !empty($v['contact_person']) ? Security::e($v['contact_person']) : '<span style="color: var(--text-muted);">--</span>' ?>
                                </td>
                                <td style="padding: 12px 16px; font-family: var(--font-mono); font-size: 0.82rem;">
                                    <div><strong><?= Security::e($v['phone']) ?></strong></div>
                                    <?php if (!empty($v['alt_phone'])): ?>
                                        <div style="color: var(--text-muted); font-size: 0.75rem;">Alt: <?= Security::e($v['alt_phone']) ?></div>
                                    <?php endif; ?>
                                </td>
                                <td style="padding: 12px 16px;">
                                    <?php if ((int)$v['is_active'] === 1): ?>
                                        <span class="badge" style="background: #ecfdf5; color: #047857; font-size: 0.72rem; padding: 2px 6px; border-radius: 4px; font-weight: 700;">ACTIVE</span>
                                    <?php else: ?>
                                        <span class="badge" style="background: #f1f5f9; color: #64748b; font-size: 0.72rem; padding: 2px 6px; border-radius: 4px; font-weight: 700;">INACTIVE</span>
                                    <?php endif; ?>
                                </td>
                                <td style="padding: 12px 16px; text-align: center; font-weight: 600;">
                                    <?= (int)$v['total_purchases_count'] ?>
                                </td>
                                <?php if ($canViewCost): ?>
                                    <td style="padding: 12px 16px; text-align: right; font-weight: 700; font-family: var(--font-mono);">
                                        ₹ <?= number_format((float)$v['total_purchased_amount'], 2) ?>
                                    </td>
                                    <td style="padding: 12px 16px; text-align: right; font-weight: 700; font-family: var(--font-mono); color: <?= (float)$v['total_due_amount'] > 0 ? '#dc2626' : '#059669' ?>;">
                                        ₹ <?= number_format((float)$v['total_due_amount'], 2) ?>
                                    </td>
                                <?php endif; ?>
                                <td style="padding: 12px 16px; text-align: center;">
                                    <div style="display: flex; gap: 6px; justify-content: center;">
                                        <a href="/vendors/<?= (int)$v['id'] ?>" class="btn btn-outline btn-sm" style="font-size: 0.75rem; padding: 3px 8px;">
                                            Profile
                                        </a>
                                        <?php if ($canManage): ?>
                                            <a href="/vendors/<?= (int)$v['id'] ?>/edit" class="btn btn-outline btn-sm" style="font-size: 0.75rem; padding: 3px 8px;">
                                                Edit
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
                Page <strong><?= $currentPage ?></strong> of <strong><?= $totalPages ?></strong> (<?= number_format($totalItems) ?> vendors)
            </div>
            <div style="display: flex; gap: 6px;">
                <?php if ($currentPage > 1): ?>
                    <a href="?page=<?= $currentPage - 1 ?>&status=<?= Security::e($statusFilter) ?>&q=<?= urlencode($search) ?>" class="btn btn-outline btn-sm">&laquo; Prev</a>
                <?php endif; ?>
                <?php for ($i = max(1, $currentPage - 2); $i <= min($totalPages, $currentPage + 2); $i++): ?>
                    <a href="?page=<?= $i ?>&status=<?= Security::e($statusFilter) ?>&q=<?= urlencode($search) ?>" class="btn btn-sm <?= ($i === $currentPage) ? 'btn-primary' : 'btn-outline' ?>">
                        <?= $i ?>
                    </a>
                <?php endfor; ?>
                <?php if ($currentPage < $totalPages): ?>
                    <a href="?page=<?= $currentPage + 1 ?>&status=<?= Security::e($statusFilter) ?>&q=<?= urlencode($search) ?>" class="btn btn-outline btn-sm">Next &raquo;</a>
                <?php endif; ?>
            </div>
        </div>
    <?php endif; ?>
</div>
