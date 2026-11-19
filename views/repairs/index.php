<?php
/**
 * Repair Records Listing View
 * File: views/repairs/index.php
 */
use AMH\Security;
use AMH\Financial;
?>

<div class="page-header" style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 24px; flex-wrap: wrap; gap: 16px;">
    <div>
        <h1 style="font-size: 1.5rem; font-weight: 700; color: var(--text-main); margin-bottom: 4px;">
            <?= Security::e($pageTitle) ?>
        </h1>
        <p style="color: var(--text-muted); font-size: 0.875rem;">
            Customer service jobs, used-phone refurbishment capitalization, and repair accounts.
        </p>
    </div>
    <div style="display: flex; gap: 10px; flex-wrap: wrap;">
        <?php if (!empty($filters['credit_only'])): ?>
            <a href="/repairs" class="btn btn-outline">
                <span>&#128295;</span> View All Repairs
            </a>
        <?php else: ?>
            <a href="/repairs?credit_only=1" class="btn btn-outline" style="border-color: #f59e0b; color: #b45309;">
                <span>&#9888;</span> View Customer Due Only
            </a>
        <?php endif; ?>

        <?php if ($canManage): ?>
            <a href="/repairs/customer/create" class="btn btn-primary">
                <span>&#43;</span> New Customer Repair
            </a>
            <a href="/repairs/refurbish/create" class="btn btn-secondary">
                <span>&#128260;</span> Used Phone Refurbish
            </a>
        <?php endif; ?>
    </div>
</div>

<!-- Financial Summary Cards -->
<div class="grid grid-cols-4" style="gap: 16px; margin-bottom: 24px;">
    <div class="metric-card">
        <div class="metric-icon blue">&#128295;</div>
        <div class="metric-data">
            <div class="metric-label">Total Jobs</div>
            <div class="metric-value"><?= number_format((int)$stats['total_repairs_count']) ?></div>
            <div class="metric-sub">Repairs &amp; refurbishments</div>
        </div>
    </div>

    <div class="metric-card">
        <div class="metric-icon green">&#128176;</div>
        <div class="metric-data">
            <div class="metric-label">Service Revenue</div>
            <div class="metric-value">₹ <?= number_format((float)$stats['total_revenue'], 2) ?></div>
            <div class="metric-sub">Billed to customers</div>
        </div>
    </div>

    <div class="metric-card">
        <div class="metric-icon green">&#9989;</div>
        <div class="metric-data">
            <div class="metric-label">Receipts Settled</div>
            <div class="metric-value">₹ <?= number_format((float)$stats['total_paid'], 2) ?></div>
            <div class="metric-sub">Customer payments collected</div>
        </div>
    </div>

    <div class="metric-card">
        <div class="metric-icon amber">&#9888;</div>
        <div class="metric-data">
            <div class="metric-label">Customer Balance Due</div>
            <div class="metric-value" style="color: <?= (float)$stats['total_due'] > 0 ? '#dc2626' : '#059669' ?>;">
                ₹ <?= number_format((float)$stats['total_due'], 2) ?>
            </div>
            <div class="metric-sub">Pending customer payment</div>
        </div>
    </div>
</div>

<?php if ($canViewCosts || $canViewProfit): ?>
    <div class="grid grid-cols-2" style="gap: 16px; margin-bottom: 20px;">
        <?php if ($canViewCosts): ?>
            <div class="card" style="background: #f8fafc; border: 1px solid #e2e8f0;">
                <div class="card-body" style="padding: 14px 20px; display: flex; justify-content: space-between; align-items: center;">
                    <div>
                        <div style="font-size: 0.8rem; font-weight: 600; color: #64748b; text-transform: uppercase;">Repair Costs Incurred</div>
                        <div style="font-size: 1.25rem; font-weight: 700; color: #1e293b;">
                            ₹ <?= number_format((float)$stats['total_cost'], 2) ?>
                        </div>
                    </div>
                    <div style="font-size: 0.85rem; color: #64748b; text-align: right;">
                        Parts: ₹ <?= number_format((float)$stats['total_parts_cost'], 2) ?><br>
                        Labour: ₹ <?= number_format((float)$stats['total_labour_cost'], 2) ?>
                    </div>
                </div>
            </div>
        <?php endif; ?>

        <?php if ($canViewProfit): ?>
            <div class="card" style="background: #f0fdf4; border: 1px solid #bbf7d0;">
                <div class="card-body" style="padding: 14px 20px; display: flex; justify-content: space-between; align-items: center;">
                    <div>
                        <div style="font-size: 0.8rem; font-weight: 600; color: #166534; text-transform: uppercase;">Customer Service Net Profit</div>
                        <div style="font-size: 1.25rem; font-weight: 800; color: <?= (float)$stats['total_net_profit'] >= 0 ? '#15803d' : '#b91c1c' ?>;">
                            ₹ <?= number_format((float)$stats['total_net_profit'], 2) ?>
                        </div>
                    </div>
                    <div style="font-size: 0.85rem; color: #166534; text-align: right;">
                        Service Margin: <?= (float)$stats['total_revenue'] > 0 ? number_format(((float)$stats['total_net_profit'] / (float)$stats['total_revenue']) * 100, 1) . '%' : '0.0%' ?>
                    </div>
                </div>
            </div>
        <?php endif; ?>
    </div>
<?php endif; ?>

<!-- Filters Form -->
<div class="card" style="margin-bottom: 20px;">
    <div class="card-body" style="padding: 16px;">
        <form method="GET" action="/repairs" style="display: flex; gap: 12px; flex-wrap: wrap; align-items: center;">
            <div style="flex: 1; min-width: 200px;">
                <input type="text" name="q" value="<?= Security::e($filters['q']) ?>" placeholder="Search repair #, customer, phone, model..." class="form-control" style="width: 100%;">
            </div>

            <div style="width: 180px;">
                <select name="type" class="form-control">
                    <option value="ALL" <?= $filters['type'] === 'ALL' ? 'selected' : '' ?>>All Repair Types</option>
                    <option value="CUSTOMER_SERVICE" <?= $filters['type'] === 'CUSTOMER_SERVICE' ? 'selected' : '' ?>>Customer Service</option>
                    <option value="REFURBISHMENT" <?= $filters['type'] === 'REFURBISHMENT' ? 'selected' : '' ?>>Used Phone Refurbish</option>
                </select>
            </div>

            <div style="width: 150px;">
                <select name="status" class="form-control">
                    <option value="ALL" <?= $filters['status'] === 'ALL' ? 'selected' : '' ?>>All Statuses</option>
                    <option value="PAID" <?= $filters['status'] === 'PAID' ? 'selected' : '' ?>>Paid (Settled)</option>
                    <option value="PARTIAL" <?= $filters['status'] === 'PARTIAL' ? 'selected' : '' ?>>Partial (Due)</option>
                    <option value="UNPAID" <?= $filters['status'] === 'UNPAID' ? 'selected' : '' ?>>Unpaid</option>
                </select>
            </div>

            <div style="display: flex; gap: 6px; align-items: center;">
                <input type="date" name="date_from" value="<?= Security::e($filters['date_from']) ?>" class="form-control" title="Date From" style="width: 140px;">
                <span style="color: var(--text-muted); font-size: 0.8rem;">to</span>
                <input type="date" name="date_to" value="<?= Security::e($filters['date_to']) ?>" class="form-control" title="Date To" style="width: 140px;">
            </div>

            <button type="submit" class="btn btn-secondary">
                <span>&#128269;</span> Filter
            </button>

            <?php if ($filters['type'] !== 'ALL' || $filters['status'] !== 'ALL' || $filters['q'] !== '' || $filters['date_from'] !== '' || $filters['date_to'] !== '' || !empty($filters['credit_only'])): ?>
                <a href="/repairs" class="btn btn-outline" title="Reset all filters">Reset</a>
            <?php endif; ?>
        </form>
    </div>
</div>

<!-- Repairs Table -->
<div class="card">
    <div class="table-responsive">
        <table class="table" style="width: 100%; text-align: left; border-collapse: collapse;">
            <thead>
                <tr style="border-bottom: 2px solid var(--border-color); background: var(--bg-surface);">
                    <th style="padding: 12px 16px;">Repair #</th>
                    <th style="padding: 12px 16px;">Type</th>
                    <th style="padding: 12px 16px;">Date</th>
                    <th style="padding: 12px 16px;">Customer / Target Handset</th>
                    <th style="padding: 12px 16px;">Description</th>
                    <?php if ($canViewCosts): ?>
                        <th style="padding: 12px 16px; text-align: right;">Total Cost</th>
                    <?php endif; ?>
                    <th style="padding: 12px 16px; text-align: right;">Charged</th>
                    <th style="padding: 12px 16px; text-align: right;">Paid</th>
                    <th style="padding: 12px 16px; text-align: right;">Due</th>
                    <?php if ($canViewProfit): ?>
                        <th style="padding: 12px 16px; text-align: right;">Profit</th>
                    <?php endif; ?>
                    <th style="padding: 12px 16px; text-align: center;">Status</th>
                    <th style="padding: 12px 16px; text-align: right;">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($repairs)): ?>
                    <tr>
                        <td colspan="<?= 7 + ($canViewCosts ? 1 : 0) + ($canViewProfit ? 1 : 0) ?>" style="padding: 32px; text-align: center; color: var(--text-muted);">
                            No repair records found matching the filter criteria.
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($repairs as $r): ?>
                        <tr style="border-bottom: 1px solid var(--border-color);">
                            <td style="padding: 12px 16px; font-weight: 600;">
                                <a href="/repairs/<?= (int)$r['id'] ?>" style="color: var(--primary); text-decoration: none;">
                                    <?= Security::e($r['repair_number']) ?>
                                </a>
                            </td>
                            <td style="padding: 12px 16px;">
                                <?php if ($r['repair_type'] === 'CUSTOMER_SERVICE'): ?>
                                    <span class="badge" style="background: #e0e7ff; color: #3730a3; padding: 4px 8px; border-radius: 4px; font-size: 0.75rem; font-weight: 600;">
                                        CUSTOMER SERVICE
                                    </span>
                                <?php else: ?>
                                    <span class="badge" style="background: #fef3c7; color: #92400e; padding: 4px 8px; border-radius: 4px; font-size: 0.75rem; font-weight: 600;">
                                        REFURBISHMENT
                                    </span>
                                <?php endif; ?>
                            </td>
                            <td style="padding: 12px 16px; color: var(--text-muted); font-size: 0.85rem; white-space: nowrap;">
                                <?= Security::e($r['repair_date']) ?>
                            </td>
                            <td style="padding: 12px 16px;">
                                <?php if ($r['repair_type'] === 'CUSTOMER_SERVICE'): ?>
                                    <div style="font-weight: 600; color: var(--text-main);"><?= Security::e($r['customer_name'] ?? '—') ?></div>
                                    <div style="font-size: 0.8rem; color: var(--text-muted);"><?= Security::e($r['customer_phone'] ?? '—') ?> &bull; <?= Security::e($r['device_model'] ?? '—') ?></div>
                                <?php else: ?>
                                    <div style="font-weight: 600; color: var(--text-main);"><?= Security::e($r['device_model'] ?? 'Shop Handset') ?></div>
                                    <div style="font-size: 0.8rem; color: var(--text-muted);">
                                        Phone #<?= (int)$r['phone_id'] ?>
                                        <?php if (!empty($r['phone_imei'])): ?>
                                            &bull; IMEI: <?= Security::e($r['phone_imei']) ?>
                                        <?php endif; ?>
                                    </div>
                                <?php endif; ?>
                            </td>
                            <td style="padding: 12px 16px; max-width: 220px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; font-size: 0.85rem;" title="<?= Security::e($r['repair_description']) ?>">
                                <?= Security::e($r['repair_description']) ?>
                            </td>
                            <?php if ($canViewCosts): ?>
                                <td style="padding: 12px 16px; text-align: right; font-size: 0.9rem; color: var(--text-muted);">
                                    ₹ <?= number_format((float)$r['total_repair_cost'], 2) ?>
                                </td>
                            <?php endif; ?>
                            <td style="padding: 12px 16px; text-align: right; font-weight: 600;">
                                <?php if ($r['repair_type'] === 'CUSTOMER_SERVICE'): ?>
                                    ₹ <?= number_format((float)$r['amount_charged'], 2) ?>
                                <?php else: ?>
                                    <span style="color: var(--text-muted); font-weight: normal; font-size: 0.85rem;">[Capitalized]</span>
                                <?php endif; ?>
                            </td>
                            <td style="padding: 12px 16px; text-align: right; color: #059669; font-weight: 600;">
                                ₹ <?= number_format((float)$r['paid_amount'], 2) ?>
                            </td>
                            <td style="padding: 12px 16px; text-align: right; font-weight: 600; color: <?= (float)$r['due_amount'] > 0 ? '#dc2626' : '#059669' ?>;">
                                ₹ <?= number_format((float)$r['due_amount'], 2) ?>
                            </td>
                            <?php if ($canViewProfit): ?>
                                <td style="padding: 12px 16px; text-align: right; font-weight: 600; color: <?= (float)$r['net_profit_contribution'] >= 0 ? '#15803d' : '#b91c1c' ?>;">
                                    <?php if ($r['repair_type'] === 'CUSTOMER_SERVICE'): ?>
                                        ₹ <?= number_format((float)$r['net_profit_contribution'], 2) ?>
                                    <?php else: ?>
                                        <span style="color: var(--text-muted); font-weight: normal; font-size: 0.85rem;">₹ 0.00</span>
                                    <?php endif; ?>
                                </td>
                            <?php endif; ?>
                            <td style="padding: 12px 16px; text-align: center;">
                                <?php if ($r['payment_status'] === 'PAID'): ?>
                                    <span class="badge" style="background: #dcfce7; color: #15803d; padding: 4px 8px; border-radius: 4px; font-size: 0.75rem; font-weight: 600;">PAID</span>
                                <?php elseif ($r['payment_status'] === 'PARTIAL'): ?>
                                    <span class="badge" style="background: #fef3c7; color: #b45309; padding: 4px 8px; border-radius: 4px; font-size: 0.75rem; font-weight: 600;">PARTIAL</span>
                                <?php else: ?>
                                    <span class="badge" style="background: #fee2e2; color: #b91c1c; padding: 4px 8px; border-radius: 4px; font-size: 0.75rem; font-weight: 600;">UNPAID</span>
                                <?php endif; ?>
                            </td>
                            <td style="padding: 12px 16px; text-align: right; white-space: nowrap;">
                                <a href="/repairs/<?= (int)$r['id'] ?>" class="btn btn-sm btn-outline" style="padding: 4px 8px; font-size: 0.75rem;">
                                    View
                                </a>
                                <?php if ($r['repair_type'] === 'CUSTOMER_SERVICE' && (float)$r['due_amount'] > 0.00 && $canManage): ?>
                                    <a href="/repairs/<?= (int)$r['id'] ?>/payments/create" class="btn btn-sm btn-primary" style="padding: 4px 8px; font-size: 0.75rem; margin-left: 4px;">
                                        Pay
                                    </a>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <!-- Pagination Controls -->
    <?php if ($pagination['totalPages'] > 1): ?>
        <div style="padding: 16px; display: flex; justify-content: space-between; align-items: center; border-top: 1px solid var(--border-color); flex-wrap: wrap; gap: 8px;">
            <div style="font-size: 0.85rem; color: var(--text-muted);">
                Showing page <?= $pagination['page'] ?> of <?= $pagination['totalPages'] ?> (<?= $pagination['totalItems'] ?> total records)
            </div>
            <div style="display: flex; gap: 6px;">
                <?php if ($pagination['page'] > 1): ?>
                    <a href="?page=<?= $pagination['page'] - 1 ?>&type=<?= urlencode($filters['type']) ?>&status=<?= urlencode($filters['status']) ?>&q=<?= urlencode($filters['q']) ?>&date_from=<?= urlencode($filters['date_from']) ?>&date_to=<?= urlencode($filters['date_to']) ?><?= !empty($filters['credit_only']) ? '&credit_only=1' : '' ?>" class="btn btn-sm btn-outline">Previous</a>
                <?php endif; ?>

                <?php for ($p = max(1, $pagination['page'] - 2); $p <= min($pagination['totalPages'], $pagination['page'] + 2); $p++): ?>
                    <a href="?page=<?= $p ?>&type=<?= urlencode($filters['type']) ?>&status=<?= urlencode($filters['status']) ?>&q=<?= urlencode($filters['q']) ?>&date_from=<?= urlencode($filters['date_from']) ?>&date_to=<?= urlencode($filters['date_to']) ?><?= !empty($filters['credit_only']) ? '&credit_only=1' : '' ?>" class="btn btn-sm <?= $p === $pagination['page'] ? 'btn-primary' : 'btn-outline' ?>">
                        <?= $p ?>
                    </a>
                <?php endfor; ?>

                <?php if ($pagination['page'] < $pagination['totalPages']): ?>
                    <a href="?page=<?= $pagination['page'] + 1 ?>&type=<?= urlencode($filters['type']) ?>&status=<?= urlencode($filters['status']) ?>&q=<?= urlencode($filters['q']) ?>&date_from=<?= urlencode($filters['date_from']) ?>&date_to=<?= urlencode($filters['date_to']) ?><?= !empty($filters['credit_only']) ? '&credit_only=1' : '' ?>" class="btn btn-sm btn-outline">Next</a>
                <?php endif; ?>
            </div>
        </div>
    <?php endif; ?>
</div>
