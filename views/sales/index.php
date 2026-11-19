<?php
/**
 * Sales Records Listing View
 * File: views/sales/index.php
 */
use AMH\Security;
?>

<div class="page-header" style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 24px; flex-wrap: wrap; gap: 16px;">
    <div>
        <h1 style="font-size: 1.5rem; font-weight: 700; color: var(--text-main); margin-bottom: 4px;">
            <?= Security::e($pageTitle) ?>
        </h1>
        <p style="color: var(--text-muted); font-size: 0.875rem;">
            Internal retail sales, customer credit (udhar) tracking, and payment receipts.
        </p>
    </div>
    <div style="display: flex; gap: 10px; flex-wrap: wrap;">
        <?php if ($creditOnly): ?>
            <a href="/sales" class="btn btn-outline">
                <span>&#128722;</span> View All Sales
            </a>
        <?php else: ?>
            <a href="/sales?credit_only=1" class="btn btn-outline" style="border-color: #f59e0b; color: #b45309;">
                <span>&#9888;</span> View Customer Credit Only
            </a>
        <?php endif; ?>

        <?php if ($canPerformSales): ?>
            <a href="/sales/create" class="btn btn-primary">
                <span>&#43;</span> New Counter Sale
            </a>
        <?php endif; ?>
    </div>
</div>

<!-- Financial Summary Cards -->
<div class="grid grid-cols-4" style="gap: 16px; margin-bottom: 24px;">
    <div class="metric-card">
        <div class="metric-icon blue">&#128722;</div>
        <div class="metric-data">
            <div class="metric-label">Total Transactions</div>
            <div class="metric-value"><?= number_format((int)$stats['total_sales_count']) ?></div>
            <div class="metric-sub">Counter checkouts</div>
        </div>
    </div>

    <div class="metric-card">
        <div class="metric-icon green">&#128176;</div>
        <div class="metric-data">
            <div class="metric-label">Total Revenue Billed</div>
            <div class="metric-value">₹ <?= number_format((float)$stats['total_revenue'], 2) ?></div>
            <div class="metric-sub">Net sales total</div>
        </div>
    </div>

    <div class="metric-card">
        <div class="metric-icon green">&#9989;</div>
        <div class="metric-data">
            <div class="metric-label">Settled Payments</div>
            <div class="metric-value">₹ <?= number_format((float)$stats['total_paid'], 2) ?></div>
            <div class="metric-sub">Collected cash/UPI/cards</div>
        </div>
    </div>

    <div class="metric-card">
        <div class="metric-icon amber">&#9888;</div>
        <div class="metric-data">
            <div class="metric-label">Customer Credit Due (Udhar)</div>
            <div class="metric-value" style="color: <?= (float)$stats['total_due'] > 0 ? '#dc2626' : '#059669' ?>;">
                ₹ <?= number_format((float)$stats['total_due'], 2) ?>
            </div>
            <div class="metric-sub">Outstanding balance</div>
        </div>
    </div>
</div>

<?php if ($canViewProfits): ?>
    <div class="card" style="margin-bottom: 20px; background: #f0fdf4; border: 1px solid #bbf7d0;">
        <div class="card-body" style="padding: 12px 20px; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 12px;">
            <div style="display: flex; align-items: center; gap: 10px;">
                <span style="font-size: 1.25rem;">&#128200;</span>
                <div>
                    <span style="font-size: 0.85rem; font-weight: 600; color: #166534; text-transform: uppercase; letter-spacing: 0.05em;">Realized Gross Profit (Filtered Set)</span>
                    <div style="font-size: 1.15rem; font-weight: 800; color: <?= (float)$stats['total_gross_profit'] >= 0 ? '#15803d' : '#b91c1c' ?>;">
                        ₹ <?= number_format((float)$stats['total_gross_profit'], 2) ?>
                    </div>
                </div>
            </div>
            <div style="font-size: 0.8rem; color: #166534;">
                Net Margin: <?= (float)$stats['total_revenue'] > 0 ? number_format(((float)$stats['total_gross_profit'] / (float)$stats['total_revenue']) * 100, 1) . '%' : '0.0%' ?>
            </div>
        </div>
    </div>
<?php endif; ?>

<!-- Filters Bar -->
<div class="card" style="margin-bottom: 20px;">
    <div class="card-body" style="padding: 16px;">
        <form method="GET" action="/sales" style="display: flex; gap: 12px; flex-wrap: wrap; align-items: center;">
            <?php if ($creditOnly): ?>
                <input type="hidden" name="credit_only" value="1">
            <?php endif; ?>

            <div style="flex: 2; min-width: 220px;">
                <input type="text" name="q" value="<?= Security::e($search) ?>" placeholder="Search Sale #, Customer Name, or Phone..." class="form-control" style="width: 100%;">
            </div>

            <?php if (!$creditOnly): ?>
                <div style="flex: 1; min-width: 150px;">
                    <select name="status" class="form-control" style="width: 100%;">
                        <option value="ALL" <?= ($statusFilter === 'ALL') ? 'selected' : '' ?>>All Statuses</option>
                        <option value="PAID" <?= ($statusFilter === 'PAID') ? 'selected' : '' ?>>Fully Settled</option>
                        <option value="PARTIAL" <?= ($statusFilter === 'PARTIAL') ? 'selected' : '' ?>>Partial Credit</option>
                        <option value="UNPAID" <?= ($statusFilter === 'UNPAID') ? 'selected' : '' ?>>Unpaid Credit</option>
                    </select>
                </div>
            <?php endif; ?>

            <div style="display: flex; gap: 6px; align-items: center;">
                <input type="date" name="date_from" value="<?= Security::e($dateFrom) ?>" class="form-control" style="font-size: 0.8rem; padding: 6px 10px;" title="From Date">
                <span style="color: var(--text-muted);">&ndash;</span>
                <input type="date" name="date_to" value="<?= Security::e($dateTo) ?>" class="form-control" style="font-size: 0.8rem; padding: 6px 10px;" title="To Date">
            </div>

            <div style="display: flex; gap: 8px;">
                <button type="submit" class="btn btn-primary">Filter</button>
                <?php if ($search !== '' || $statusFilter !== 'ALL' || $dateFrom !== '' || $dateTo !== '' || $creditOnly): ?>
                    <a href="/sales" class="btn btn-outline">Reset</a>
                <?php endif; ?>
            </div>
        </form>
    </div>
</div>

<!-- Sales Listing Table -->
<div class="card">
    <div class="card-header" style="background: #fff;">
        <div class="card-title">
            <span>&#128722;</span> Sale Transactions (<?= number_format($totalItems) ?> records found)
        </div>
    </div>
    <div class="card-body" style="padding: 0;">
        <div class="table-responsive">
            <table class="table" style="width: 100%; border-collapse: collapse; font-size: 0.875rem;">
                <thead>
                    <tr style="background: #f8fafc; border-bottom: 2px solid var(--border-color); text-align: left;">
                        <th style="padding: 12px 16px;">Sale Number</th>
                        <th style="padding: 12px 16px;">Date &amp; Time</th>
                        <th style="padding: 12px 16px;">Customer Details</th>
                        <th style="padding: 12px 16px; text-align: center;">Items</th>
                        <th style="padding: 12px 16px; text-align: right;">Total Amount</th>
                        <th style="padding: 12px 16px; text-align: right;">Paid Amount</th>
                        <th style="padding: 12px 16px; text-align: right;">Balance Due</th>
                        <th style="padding: 12px 16px; text-align: center;">Payment Status</th>
                        <?php if ($canViewProfits): ?>
                            <th style="padding: 12px 16px; text-align: right;">Gross Profit</th>
                        <?php endif; ?>
                        <th style="padding: 12px 16px; text-align: right;">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($sales)): ?>
                        <tr>
                            <td colspan="<?= $canViewProfits ? '10' : '9' ?>" style="text-align: center; padding: 40px; color: var(--text-muted);">
                                <div>&#128722;</div>
                                <div style="font-weight: 600; margin-top: 8px;">No sales records found</div>
                                <p style="font-size: 0.8rem; margin: 4px 0 0 0;">Use the counter sale entry to process purchases and record receipts.</p>
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($sales as $s): ?>
                            <tr style="border-bottom: 1px solid var(--border-color);">
                                <td style="padding: 12px 16px; font-weight: 700;">
                                    <a href="/sales/<?= (int)$s['id'] ?>" style="color: var(--primary); text-decoration: none; font-family: var(--font-mono);">
                                        <?= Security::e($s['sale_number']) ?>
                                    </a>
                                </td>

                                <td style="padding: 12px 16px; color: var(--text-muted); font-size: 0.8rem;">
                                    <div><?= Security::e($s['sale_date']) ?></div>
                                    <div style="font-family: var(--font-mono); font-size: 0.75rem;"><?= Security::e(substr($s['sale_time'], 0, 5)) ?></div>
                                </td>

                                <td style="padding: 12px 16px;">
                                    <?php if (!empty($s['customer_name'])): ?>
                                        <div style="font-weight: 600; color: var(--text-main);"><?= Security::e($s['customer_name']) ?></div>
                                    <?php else: ?>
                                        <span style="color: var(--text-muted); font-style: italic;">Walk-in Customer</span>
                                    <?php endif; ?>

                                    <?php if (!empty($s['customer_phone'])): ?>
                                        <div style="font-size: 0.75rem; color: var(--text-muted); font-family: var(--font-mono);">
                                            &#128222; <?= Security::e($s['customer_phone']) ?>
                                        </div>
                                    <?php endif; ?>
                                </td>

                                <td style="padding: 12px 16px; text-align: center; font-weight: 600;">
                                    <span class="badge" style="background: #f1f5f9; color: #475569; font-size: 0.75rem; padding: 2px 8px;">
                                        <?= (int)$s['items_count'] ?> <?= (int)$s['items_count'] === 1 ? 'item' : 'items' ?>
                                    </span>
                                </td>

                                <td style="padding: 12px 16px; text-align: right; font-weight: 700; color: var(--text-main);">
                                    ₹ <?= number_format((float)$s['final_total_amount'], 2) ?>
                                    <?php if ((float)$s['discount_amount'] > 0): ?>
                                        <div style="font-size: 0.7rem; color: #059669; font-weight: 500;">
                                            -₹ <?= number_format((float)$s['discount_amount'], 2) ?> off
                                        </div>
                                    <?php endif; ?>
                                </td>

                                <td style="padding: 12px 16px; text-align: right; font-weight: 600; color: #059669;">
                                    ₹ <?= number_format((float)$s['paid_amount'], 2) ?>
                                </td>

                                <td style="padding: 12px 16px; text-align: right; font-weight: 700; color: <?= (float)$s['due_amount'] > 0 ? '#dc2626' : '#059669' ?>;">
                                    ₹ <?= number_format((float)$s['due_amount'], 2) ?>
                                </td>

                                <td style="padding: 12px 16px; text-align: center;">
                                    <?php if ($s['payment_status'] === 'PAID'): ?>
                                        <span class="badge" style="background: #ecfdf5; color: #047857; font-size: 0.75rem; padding: 3px 8px; border-radius: 4px; font-weight: 700;">PAID</span>
                                    <?php elseif ($s['payment_status'] === 'PARTIAL'): ?>
                                        <span class="badge" style="background: #fffbeb; color: #b45309; font-size: 0.75rem; padding: 3px 8px; border-radius: 4px; font-weight: 700;">PARTIAL</span>
                                    <?php else: ?>
                                        <span class="badge" style="background: #fef2f2; color: #b91c1c; font-size: 0.75rem; padding: 3px 8px; border-radius: 4px; font-weight: 700;">UNPAID</span>
                                    <?php endif; ?>
                                </td>

                                <?php if ($canViewProfits): ?>
                                    <td style="padding: 12px 16px; text-align: right; font-weight: 700; color: <?= (float)$s['gross_profit'] >= 0 ? '#15803d' : '#dc2626' ?>;">
                                        ₹ <?= number_format((float)$s['gross_profit'], 2) ?>
                                    </td>
                                <?php endif; ?>

                                <td style="padding: 12px 16px; text-align: right;">
                                    <div style="display: flex; gap: 6px; justify-content: flex-end;">
                                        <a href="/sales/<?= (int)$s['id'] ?>" class="btn btn-outline btn-sm" title="View details">
                                            View
                                        </a>
                                        <?php if ($canPerformSales && (float)$s['due_amount'] > 0): ?>
                                            <a href="/sales/<?= (int)$s['id'] ?>/payments/create" class="btn btn-secondary btn-sm" title="Record payment settlement" style="padding: 4px 8px;">
                                                Pay
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
</div>

<!-- Pagination Controls -->
<?php if ($totalPages > 1): ?>
    <div style="display: flex; justify-content: space-between; align-items: center; margin-top: 20px; flex-wrap: wrap; gap: 12px;">
        <div style="font-size: 0.85rem; color: var(--text-muted);">
            Showing page <?= $currentPage ?> of <?= $totalPages ?> (<?= number_format($totalItems) ?> total sales)
        </div>
        <div style="display: flex; gap: 6px;">
            <?php if ($currentPage > 1): ?>
                <a href="?page=<?= $currentPage - 1 ?>&q=<?= urlencode($search) ?>&status=<?= urlencode($statusFilter) ?>&date_from=<?= urlencode($dateFrom) ?>&date_to=<?= urlencode($dateTo) ?>&credit_only=<?= $creditOnly ? '1' : '0' ?>" class="btn btn-outline btn-sm">&larr; Previous</a>
            <?php endif; ?>

            <?php for ($p = max(1, $currentPage - 2); $p <= min($totalPages, $currentPage + 2); $p++): ?>
                <a href="?page=<?= $p ?>&q=<?= urlencode($search) ?>&status=<?= urlencode($statusFilter) ?>&date_from=<?= urlencode($dateFrom) ?>&date_to=<?= urlencode($dateTo) ?>&credit_only=<?= $creditOnly ? '1' : '0' ?>" class="btn btn-sm <?= $p === $currentPage ? 'btn-primary' : 'btn-outline' ?>">
                    <?= $p ?>
                </a>
            <?php endfor; ?>

            <?php if ($currentPage < $totalPages): ?>
                <a href="?page=<?= $currentPage + 1 ?>&q=<?= urlencode($search) ?>&status=<?= urlencode($statusFilter) ?>&date_from=<?= urlencode($dateFrom) ?>&date_to=<?= urlencode($dateTo) ?>&credit_only=<?= $creditOnly ? '1' : '0' ?>" class="btn btn-outline btn-sm">Next &rarr;</a>
            <?php endif; ?>
        </div>
    </div>
<?php endif; ?>
