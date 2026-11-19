<?php
/**
 * Customer Sales Returns Listing View
 * File: views/returns/index.php
 */
use AMH\Security;
?>

<div class="page-header" style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 24px; flex-wrap: wrap; gap: 16px;">
    <div>
        <h1 style="font-size: 1.5rem; font-weight: 700; color: var(--text-main); margin-bottom: 4px;">
            <?= Security::e($pageTitle) ?>
        </h1>
        <p style="color: var(--text-muted); font-size: 0.875rem;">
            Customer return transactions, item restocking, defective scrap recording, and profit reversals.
        </p>
    </div>
    <div style="display: flex; gap: 10px; flex-wrap: wrap;">
        <a href="/sales" class="btn btn-outline">
            <span>&#128722;</span> View Sales Records
        </a>
    </div>
</div>

<!-- Financial Summary Cards -->
<div class="grid grid-cols-4" style="gap: 16px; margin-bottom: 24px;">
    <div class="metric-card">
        <div class="metric-icon blue">&#8617;</div>
        <div class="metric-data">
            <div class="metric-label">Total Returns</div>
            <div class="metric-value"><?= number_format((int)$stats['total_returns_count']) ?></div>
            <div class="metric-sub">Processed return transactions</div>
        </div>
    </div>

    <div class="metric-card">
        <div class="metric-icon amber">&#128176;</div>
        <div class="metric-data">
            <div class="metric-label">Total Refunds Processed</div>
            <div class="metric-value" style="color: #b45309;">₹ <?= number_format((float)$stats['total_refunds'], 2) ?></div>
            <div class="metric-sub">Revenue reversed / refunded</div>
        </div>
    </div>

    <div class="metric-card">
        <div class="metric-icon green">&#128230;</div>
        <div class="metric-data">
            <div class="metric-label">Restocked Inventory COGS</div>
            <div class="metric-value" style="color: #059669;">₹ <?= number_format((float)$stats['total_restocked_cogs'], 2) ?></div>
            <div class="metric-sub">COGS returned to stock</div>
        </div>
    </div>

    <div class="metric-card">
        <div class="metric-icon red">&#9888;</div>
        <div class="metric-data">
            <div class="metric-label">Defective / Scrapped COGS</div>
            <div class="metric-value" style="color: #dc2626;">₹ <?= number_format((float)$stats['total_scrapped_cogs'], 2) ?></div>
            <div class="metric-sub">Lost / damaged cost absorbed</div>
        </div>
    </div>
</div>

<?php if ($canViewProfit): ?>
    <div class="card" style="margin-bottom: 20px; background: #fff1f2; border: 1px solid #fecdd3;">
        <div class="card-body" style="padding: 12px 20px; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 12px;">
            <div style="display: flex; align-items: center; gap: 10px;">
                <span style="font-size: 1.25rem;">&#128201;</span>
                <div>
                    <span style="font-size: 0.85rem; font-weight: 600; color: #9f1239; text-transform: uppercase; letter-spacing: 0.05em;">Net Profit Reversed</span>
                    <div style="font-size: 1.15rem; font-weight: 800; color: #be123c;">
                        - ₹ <?= number_format((float)$stats['total_profit_reversed'], 2) ?>
                    </div>
                </div>
            </div>
            <div style="font-size: 0.8rem; color: #881337;">
                Reversed from realized sales margins
            </div>
        </div>
    </div>
<?php endif; ?>

<!-- Filters Form -->
<div class="card" style="margin-bottom: 20px;">
    <div class="card-body" style="padding: 16px;">
        <form method="GET" action="/returns" style="display: flex; gap: 12px; flex-wrap: wrap; align-items: center;">
            <div style="flex: 1; min-width: 200px;">
                <input type="text" name="q" value="<?= Security::e($filters['q']) ?>" placeholder="Search return #, sale #, customer name, phone, reason..." class="form-control" style="width: 100%;">
            </div>

            <div style="display: flex; gap: 6px; align-items: center;">
                <input type="date" name="date_from" value="<?= Security::e($filters['date_from']) ?>" class="form-control" title="Date From" style="width: 140px;">
                <span style="color: var(--text-muted); font-size: 0.8rem;">to</span>
                <input type="date" name="date_to" value="<?= Security::e($filters['date_to']) ?>" class="form-control" title="Date To" style="width: 140px;">
            </div>

            <button type="submit" class="btn btn-secondary">
                <span>&#128269;</span> Filter
            </button>

            <?php if ($filters['q'] !== '' || $filters['date_from'] !== '' || $filters['date_to'] !== ''): ?>
                <a href="/returns" class="btn btn-outline" title="Reset all filters">Reset</a>
            <?php endif; ?>
        </form>
    </div>
</div>

<!-- Returns Table -->
<div class="card">
    <div class="table-responsive">
        <table class="table" style="width: 100%; text-align: left; border-collapse: collapse;">
            <thead>
                <tr style="border-bottom: 2px solid var(--border-color); background: var(--bg-surface);">
                    <th style="padding: 12px 16px;">Return #</th>
                    <th style="padding: 12px 16px;">Date &amp; Time</th>
                    <th style="padding: 12px 16px;">Original Sale #</th>
                    <th style="padding: 12px 16px;">Customer</th>
                    <th style="padding: 12px 16px;">Reason</th>
                    <th style="padding: 12px 16px; text-align: right;">Total Refund</th>
                    <?php if ($canViewCosts): ?>
                        <th style="padding: 12px 16px; text-align: right;">Restocked COGS</th>
                        <th style="padding: 12px 16px; text-align: right;">Scrapped COGS</th>
                    <?php endif; ?>
                    <?php if ($canViewProfit): ?>
                        <th style="padding: 12px 16px; text-align: right;">Profit Reversed</th>
                    <?php endif; ?>
                    <th style="padding: 12px 16px;">Mode</th>
                    <th style="padding: 12px 16px; text-align: right;">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($returns)): ?>
                    <tr>
                        <td colspan="<?= 7 + ($canViewCosts ? 2 : 0) + ($canViewProfit ? 1 : 0) ?>" style="padding: 32px; text-align: center; color: var(--text-muted);">
                            No return transactions found matching the filter criteria.
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($returns as $r): ?>
                        <tr style="border-bottom: 1px solid var(--border-color);">
                            <td style="padding: 12px 16px; font-weight: 700; font-family: var(--font-mono);">
                                <a href="/returns/<?= (int)$r['id'] ?>" style="color: var(--primary); text-decoration: none;">
                                    <?= Security::e($r['return_number']) ?>
                                </a>
                            </td>
                            <td style="padding: 12px 16px; font-size: 0.85rem; color: var(--text-muted); white-space: nowrap;">
                                <?= Security::e($r['return_date']) ?> <?= Security::e(substr($r['return_time'], 0, 5)) ?>
                            </td>
                            <td style="padding: 12px 16px; font-weight: 600;">
                                <a href="/sales/<?= (int)$r['sale_id'] ?>" style="color: var(--text-main); text-decoration: none;">
                                    <?= Security::e($r['sale_number']) ?>
                                </a>
                            </td>
                            <td style="padding: 12px 16px;">
                                <div style="font-weight: 600; color: var(--text-main);">
                                    <?= !empty($r['customer_name']) ? Security::e($r['customer_name']) : 'Walk-in Customer' ?>
                                </div>
                                <?php if (!empty($r['customer_phone'])): ?>
                                    <div style="font-size: 0.8rem; color: var(--text-muted);"><?= Security::e($r['customer_phone']) ?></div>
                                <?php endif; ?>
                            </td>
                            <td style="padding: 12px 16px; max-width: 200px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; font-size: 0.85rem;" title="<?= Security::e($r['reason']) ?>">
                                <?= Security::e($r['reason']) ?>
                            </td>
                            <td style="padding: 12px 16px; text-align: right; font-weight: 700; color: #b45309;">
                                ₹ <?= number_format((float)$r['total_refund_amount'], 2) ?>
                            </td>
                            <?php if ($canViewCosts): ?>
                                <td style="padding: 12px 16px; text-align: right; font-size: 0.85rem; color: #059669; font-weight: 600;">
                                    ₹ <?= number_format((float)$r['total_restocked_cogs'], 2) ?>
                                </td>
                                <td style="padding: 12px 16px; text-align: right; font-size: 0.85rem; color: #dc2626; font-weight: 600;">
                                    ₹ <?= number_format((float)$r['total_scrapped_cogs'], 2) ?>
                                </td>
                            <?php endif; ?>
                            <?php if ($canViewProfit): ?>
                                <td style="padding: 12px 16px; text-align: right; font-size: 0.85rem; color: #be123c; font-weight: 700;">
                                    - ₹ <?= number_format((float)$r['net_profit_reversed'], 2) ?>
                                </td>
                            <?php endif; ?>
                            <td style="padding: 12px 16px;">
                                <span class="badge" style="background: #f1f5f9; color: #475569; font-size: 0.75rem; padding: 2px 6px;">
                                    <?= Security::e($r['refund_payment_mode']) ?>
                                </span>
                            </td>
                            <td style="padding: 12px 16px; text-align: right; white-space: nowrap;">
                                <a href="/returns/<?= (int)$r['id'] ?>" class="btn btn-sm btn-outline" style="padding: 4px 8px; font-size: 0.75rem;">
                                    View
                                </a>
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
                    <a href="?page=<?= $pagination['page'] - 1 ?>&q=<?= urlencode($filters['q']) ?>&date_from=<?= urlencode($filters['date_from']) ?>&date_to=<?= urlencode($filters['date_to']) ?>" class="btn btn-sm btn-outline">Previous</a>
                <?php endif; ?>

                <?php for ($p = max(1, $pagination['page'] - 2); $p <= min($pagination['totalPages'], $pagination['page'] + 2); $p++): ?>
                    <a href="?page=<?= $p ?>&q=<?= urlencode($filters['q']) ?>&date_from=<?= urlencode($filters['date_from']) ?>&date_to=<?= urlencode($filters['date_to']) ?>" class="btn btn-sm <?= $p === $pagination['page'] ? 'btn-primary' : 'btn-outline' ?>">
                        <?= $p ?>
                    </a>
                <?php endfor; ?>

                <?php if ($pagination['page'] < $pagination['totalPages']): ?>
                    <a href="?page=<?= $pagination['page'] + 1 ?>&q=<?= urlencode($filters['q']) ?>&date_from=<?= urlencode($filters['date_from']) ?>&date_to=<?= urlencode($filters['date_to']) ?>" class="btn btn-sm btn-outline">Next</a>
                <?php endif; ?>
            </div>
        </div>
    <?php endif; ?>
</div>
