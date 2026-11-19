<?php
/**
 * Serialized Mobile Phones Inventory View
 * File: views/phones/index.php
 */
use AMH\Security;
?>

<div class="page-header" style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 24px; flex-wrap: wrap; gap: 16px;">
    <div>
        <h1 style="font-size: 1.5rem; font-weight: 700; color: var(--text-main); margin-bottom: 4px;">
            <?= Security::e($pageTitle) ?>
        </h1>
        <p style="color: var(--text-muted); font-size: 0.875rem;">
            Individual serialized device tracking, physical hardware registry, and commercial ownership episodes.
        </p>
    </div>
    <div style="display: flex; gap: 10px; flex-wrap: wrap;">
        <a href="/imei" class="btn btn-outline" style="background: #fff;">
            <span>&#128269;</span> IMEI Counter Lookup
        </a>
        <?php if ($canIntakeUsed): ?>
            <a href="/phones/create-used" class="btn btn-secondary">
                <span>&#128260;</span> Buy Second-Hand Mobile
            </a>
        <?php endif; ?>
        <?php if ($canIntakeNew): ?>
            <a href="/phones/create-new" class="btn btn-primary">
                <span>&#43;</span> Intake New Mobile
            </a>
        <?php endif; ?>
    </div>
</div>

<!-- Category / Type Tabs Navigation -->
<div style="display: flex; gap: 8px; border-bottom: 2px solid var(--border-color); margin-bottom: 20px; overflow-x: auto;">
    <a href="/phones" class="nav-tab <?= ($selectedType === 'ALL') ? 'active-tab' : '' ?>" style="padding: 10px 18px; text-decoration: none; font-weight: 600; font-size: 0.9rem; color: <?= ($selectedType === 'ALL') ? 'var(--primary)' : 'var(--text-muted)' ?>; border-bottom: 3px solid <?= ($selectedType === 'ALL') ? 'var(--primary)' : 'transparent' ?>; margin-bottom: -2px;">
        All Mobiles
    </a>
    <a href="/phones/new" class="nav-tab <?= ($selectedType === 'NEW') ? 'active-tab' : '' ?>" style="padding: 10px 18px; text-decoration: none; font-weight: 600; font-size: 0.9rem; color: <?= ($selectedType === 'NEW') ? 'var(--primary)' : 'var(--text-muted)' ?>; border-bottom: 3px solid <?= ($selectedType === 'NEW') ? 'var(--primary)' : 'transparent' ?>; margin-bottom: -2px;">
        New Mobiles
    </a>
    <a href="/phones/used" class="nav-tab <?= ($selectedType === 'SECOND_HAND') ? 'active-tab' : '' ?>" style="padding: 10px 18px; text-decoration: none; font-weight: 600; font-size: 0.9rem; color: <?= ($selectedType === 'SECOND_HAND') ? 'var(--primary)' : 'var(--text-muted)' ?>; border-bottom: 3px solid <?= ($selectedType === 'SECOND_HAND') ? 'var(--primary)' : 'transparent' ?>; margin-bottom: -2px;">
        Second-Hand Mobiles
    </a>
</div>

<!-- Search & Status Filter Bar -->
<div class="card" style="margin-bottom: 20px;">
    <div class="card-body" style="padding: 16px;">
        <form method="GET" action="<?= ($selectedType === 'NEW') ? '/phones/new' : (($selectedType === 'SECOND_HAND') ? '/phones/used' : '/phones') ?>" style="display: flex; gap: 12px; flex-wrap: wrap; align-items: center;">
            <input type="hidden" name="type" value="<?= Security::e($selectedType) ?>">
            
            <div style="flex: 2; min-width: 240px;">
                <input type="text" name="q" value="<?= Security::e($search) ?>" placeholder="Search by IMEI 1, IMEI 2, brand, model, serial, or seller..." class="form-control" style="width: 100%;">
            </div>

            <div style="flex: 1; min-width: 160px;">
                <select name="status" class="form-control" style="width: 100%;">
                    <option value="ALL" <?= ($selectedStatus === 'ALL') ? 'selected' : '' ?>>All Commercial Statuses</option>
                    <option value="IN_STOCK" <?= ($selectedStatus === 'IN_STOCK') ? 'selected' : '' ?>>In Stock</option>
                    <option value="SOLD" <?= ($selectedStatus === 'SOLD') ? 'selected' : '' ?>>Sold</option>
                    <option value="RETURNED_TO_VENDOR" <?= ($selectedStatus === 'RETURNED_TO_VENDOR') ? 'selected' : '' ?>>Returned to Vendor</option>
                    <option value="SCRAPPED" <?= ($selectedStatus === 'SCRAPPED') ? 'selected' : '' ?>>Scrapped / Salvaged</option>
                </select>
            </div>

            <div style="display: flex; gap: 8px;">
                <button type="submit" class="btn btn-primary">Filter</button>
                <?php if ($search !== '' || $selectedStatus !== 'ALL'): ?>
                    <a href="<?= ($selectedType === 'NEW') ? '/phones/new' : (($selectedType === 'SECOND_HAND') ? '/phones/used' : '/phones') ?>" class="btn btn-outline">Reset</a>
                <?php endif; ?>
            </div>
        </form>
    </div>
</div>

<!-- Mobile Phones Listing Table -->
<div class="card">
    <div class="card-header" style="background: #fff;">
        <div class="card-title">
            <span>&#128241;</span> Serialized Phone Records (<?= number_format($totalItems) ?> devices found)
        </div>
    </div>
    <div class="card-body" style="padding: 0;">
        <div class="table-responsive">
            <table class="table" style="width: 100%; border-collapse: collapse;">
                <thead>
                    <tr style="background: var(--bg-main); border-bottom: 1px solid var(--border-color); text-align: left; font-size: 0.8rem; text-transform: uppercase; color: var(--text-muted);">
                        <th style="padding: 12px 16px;">Episode #</th>
                        <th style="padding: 12px 16px;">Device &amp; Specs</th>
                        <th style="padding: 12px 16px;">IMEI Numbers</th>
                        <th style="padding: 12px 16px;">Type &amp; Status</th>
                        <?php if ($canViewCost): ?>
                            <th style="padding: 12px 16px; text-align: right;">Cost Basis</th>
                        <?php endif; ?>
                        <th style="padding: 12px 16px; text-align: right;">Target Price</th>
                        <th style="padding: 12px 16px;">Intake Date</th>
                        <th style="padding: 12px 16px; text-align: center;">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($phones)): ?>
                        <tr>
                            <td colspan="<?= $canViewCost ? 8 : 7 ?>" style="padding: 40px; text-align: center; color: var(--text-muted);">
                                <div style="font-size: 2rem; margin-bottom: 8px;">&#128245;</div>
                                <div style="font-weight: 600; font-size: 1rem; margin-bottom: 4px;">No serialized mobile records found</div>
                                <div style="font-size: 0.85rem;">Adjust search filters or intake a new or second-hand phone into inventory.</div>
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($phones as $p): ?>
                            <tr style="border-bottom: 1px solid var(--border-color); font-size: 0.875rem;">
                                <td style="padding: 12px 16px; font-weight: 700; font-family: var(--font-mono); color: var(--text-muted);">
                                    #<?= (int)$p['id'] ?>
                                </td>
                                <td style="padding: 12px 16px;">
                                    <div style="font-weight: 700; color: var(--text-main);">
                                        <a href="/phones/<?= (int)$p['id'] ?>" style="color: var(--primary); text-decoration: none;">
                                            <?= Security::e($p['brand']) ?> <?= Security::e($p['model_name']) ?>
                                        </a>
                                    </div>
                                    <div style="font-size: 0.78rem; color: var(--text-muted); margin-top: 2px;">
                                        <?php if (!empty($p['color'])): ?>
                                            <span>Color: <strong><?= Security::e($p['color']) ?></strong></span>
                                        <?php endif; ?>
                                        <?php if (!empty($p['ram']) || !empty($p['storage'])): ?>
                                            <span style="margin-left: 6px;">Config: <strong><?= Security::e($p['ram']) ?><?= (!empty($p['ram']) && !empty($p['storage'])) ? ' / ' : '' ?><?= Security::e($p['storage']) ?></strong></span>
                                        <?php endif; ?>
                                    </div>
                                </td>
                                <td style="padding: 12px 16px; font-family: var(--font-mono); font-size: 0.8rem;">
                                    <div><span style="color: var(--text-muted);">IMEI 1:</span> <strong><?= Security::e($p['imei_1']) ?></strong></div>
                                    <?php if (!empty($p['imei_2'])): ?>
                                        <div style="color: var(--text-muted); margin-top: 2px;"><span>IMEI 2:</span> <?= Security::e($p['imei_2']) ?></div>
                                    <?php endif; ?>
                                </td>
                                <td style="padding: 12px 16px;">
                                    <div style="display: flex; gap: 6px; flex-direction: column; align-items: flex-start;">
                                        <?php if ($p['phone_type'] === 'NEW'): ?>
                                            <span class="badge" style="background: #eff6ff; color: #1d4ed8; border: 1px solid #bfdbfe; font-size: 0.72rem; padding: 2px 6px; border-radius: 4px; font-weight: 700;">NEW DEVICE</span>
                                        <?php else: ?>
                                            <span class="badge" style="background: #fffbeb; color: #b45309; border: 1px solid #fde68a; font-size: 0.72rem; padding: 2px 6px; border-radius: 4px; font-weight: 700;">SECOND-HAND</span>
                                        <?php endif; ?>

                                        <?php if ($p['status'] === 'IN_STOCK'): ?>
                                            <span class="badge" style="background: #ecfdf5; color: #047857; font-size: 0.72rem; padding: 2px 6px; border-radius: 4px; font-weight: 700;">IN STOCK</span>
                                        <?php elseif ($p['status'] === 'SOLD'): ?>
                                            <span class="badge" style="background: #f1f5f9; color: #475569; font-size: 0.72rem; padding: 2px 6px; border-radius: 4px; font-weight: 700;">SOLD</span>
                                        <?php elseif ($p['status'] === 'RETURNED_TO_VENDOR'): ?>
                                            <span class="badge" style="background: #fef2f2; color: #b91c1c; font-size: 0.72rem; padding: 2px 6px; border-radius: 4px; font-weight: 700;">RETURNED TO VENDOR</span>
                                        <?php else: ?>
                                            <span class="badge" style="background: #fef2f2; color: #991b1b; font-size: 0.72rem; padding: 2px 6px; border-radius: 4px; font-weight: 700;">SCRAPPED</span>
                                        <?php endif; ?>
                                    </div>
                                </td>
                                <?php if ($canViewCost): ?>
                                    <td style="padding: 12px 16px; text-align: right; font-weight: 700; font-family: var(--font-mono);">
                                        <div>₹ <?= number_format((float)$p['total_cost_basis'], 2) ?></div>
                                        <?php if ((float)$p['repair_cost'] > 0 || (float)$p['other_cost'] > 0): ?>
                                            <div style="font-size: 0.72rem; color: var(--text-muted); font-weight: normal;">
                                                (Base: ₹<?= number_format((float)$p['purchase_cost'], 2) ?> + Refurb: ₹<?= number_format((float)$p['repair_cost'] + (float)$p['other_cost'], 2) ?>)
                                            </div>
                                        <?php endif; ?>
                                    </td>
                                <?php endif; ?>
                                <td style="padding: 12px 16px; text-align: right; font-weight: 600; font-family: var(--font-mono);">
                                    <?php if ($p['expected_sale_price'] !== null): ?>
                                        ₹ <?= number_format((float)$p['expected_sale_price'], 2) ?>
                                    <?php else: ?>
                                        <span style="color: var(--text-muted); font-weight: normal;">--</span>
                                    <?php endif; ?>
                                </td>
                                <td style="padding: 12px 16px; color: var(--text-muted); font-size: 0.8rem;">
                                    <div><?= Security::e($p['purchase_date']) ?></div>
                                    <div style="font-size: 0.72rem;">By: <?= Security::e($p['operator_username'] ?? 'admin') ?></div>
                                </td>
                                <td style="padding: 12px 16px; text-align: center;">
                                    <a href="/phones/<?= (int)$p['id'] ?>" class="btn btn-outline btn-sm" style="font-size: 0.78rem; padding: 4px 10px;">
                                        Inspect
                                    </a>
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
                Page <strong><?= $currentPage ?></strong> of <strong><?= $totalPages ?></strong> (<?= number_format($totalItems) ?> total records)
            </div>
            <div style="display: flex; gap: 6px;">
                <?php if ($currentPage > 1): ?>
                    <a href="?page=<?= $currentPage - 1 ?>&type=<?= Security::e($selectedType) ?>&status=<?= Security::e($selectedStatus) ?>&q=<?= urlencode($search) ?>" class="btn btn-outline btn-sm">&laquo; Prev</a>
                <?php endif; ?>
                <?php for ($i = max(1, $currentPage - 2); $i <= min($totalPages, $currentPage + 2); $i++): ?>
                    <a href="?page=<?= $i ?>&type=<?= Security::e($selectedType) ?>&status=<?= Security::e($selectedStatus) ?>&q=<?= urlencode($search) ?>" class="btn btn-sm <?= ($i === $currentPage) ? 'btn-primary' : 'btn-outline' ?>">
                        <?= $i ?>
                    </a>
                <?php endfor; ?>
                <?php if ($currentPage < $totalPages): ?>
                    <a href="?page=<?= $currentPage + 1 ?>&type=<?= Security::e($selectedType) ?>&status=<?= Security::e($selectedStatus) ?>&q=<?= urlencode($search) ?>" class="btn btn-outline btn-sm">Next &raquo;</a>
                <?php endif; ?>
            </div>
        </div>
    <?php endif; ?>
</div>
