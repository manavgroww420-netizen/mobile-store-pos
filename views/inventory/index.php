<?php
/**
 * Inventory Stock Ledger View
 * File: views/inventory/index.php
 */
?>

<!-- Summary Metric KPI Cards -->
<div class="grid grid-cols-4" style="margin-bottom: 20px;">
    <div class="card" style="margin-bottom: 0; padding: 16px;">
        <div style="font-size: 0.8rem; color: var(--text-muted); text-transform: uppercase; font-weight: 600;">Tracked Accessories</div>
        <div style="font-size: 1.6rem; font-weight: 700; color: var(--text-main); margin-top: 4px;">
            <?= number_format((int)($metrics['total_items'] ?? 0)) ?>
        </div>
        <div style="font-size: 0.78rem; color: var(--text-muted); margin-top: 2px;">Active catalog lines</div>
    </div>

    <div class="card" style="margin-bottom: 0; padding: 16px;">
        <div style="font-size: 0.8rem; color: var(--text-muted); text-transform: uppercase; font-weight: 600;">Total Units On-Hand</div>
        <div style="font-size: 1.6rem; font-weight: 700; color: var(--primary); margin-top: 4px;">
            <?= number_format((int)($metrics['total_units'] ?? 0)) ?>
        </div>
        <div style="font-size: 0.78rem; color: var(--text-muted); margin-top: 2px;">Physical accessory pieces</div>
    </div>

    <div class="card" style="margin-bottom: 0; padding: 16px;">
        <div style="font-size: 0.8rem; color: var(--text-muted); text-transform: uppercase; font-weight: 600;">Low Stock Warnings</div>
        <div style="font-size: 1.6rem; font-weight: 700; color: #b45309; margin-top: 4px;">
            <?= number_format((int)($metrics['low_stock_count'] ?? 0)) ?>
        </div>
        <div style="font-size: 0.78rem; color: var(--text-muted); margin-top: 2px;">Below alert threshold</div>
    </div>

    <div class="card" style="margin-bottom: 0; padding: 16px;">
        <div style="font-size: 0.8rem; color: var(--text-muted); text-transform: uppercase; font-weight: 600;">Out of Stock</div>
        <div style="font-size: 1.6rem; font-weight: 700; color: var(--danger); margin-top: 4px;">
            <?= number_format((int)($metrics['out_of_stock_count'] ?? 0)) ?>
        </div>
        <div style="font-size: 0.78rem; color: var(--text-muted); margin-top: 2px;">Needs replenishment</div>
    </div>
</div>

<!-- Search & Filter Controls -->
<div class="card" style="margin-bottom: 20px;">
    <div class="card-body" style="padding: 16px;">
        <form method="GET" action="/inventory" style="display: flex; gap: 12px; flex-wrap: wrap; align-items: flex-end;">
            <div style="flex: 2; min-width: 220px;">
                <label class="form-label" style="font-size: 0.8rem; margin-bottom: 4px;" for="q">Search Accessory or SKU</label>
                <input 
                    type="text" 
                    id="q" 
                    name="q" 
                    class="form-control" 
                    placeholder="e.g. Charger, Tempered Glass, SKU..." 
                    value="<?= e($search) ?>"
                >
            </div>

            <div style="flex: 1; min-width: 180px;">
                <label class="form-label" style="font-size: 0.8rem; margin-bottom: 4px;" for="category_id">Category</label>
                <select id="category_id" name="category_id" class="form-control">
                    <option value="">All Categories</option>
                    <?php foreach ($categories as $cat): ?>
                        <option value="<?= (int)$cat['id'] ?>" <?= $selectedCat === (int)$cat['id'] ? 'selected' : '' ?>>
                            <?= e($cat['name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div style="flex: 1; min-width: 150px;">
                <label class="form-label" style="font-size: 0.8rem; margin-bottom: 4px;" for="stock_status">Stock Status</label>
                <select id="stock_status" name="stock_status" class="form-control">
                    <option value="all" <?= $selectedStock === 'all' ? 'selected' : '' ?>>All Items</option>
                    <option value="in_stock" <?= $selectedStock === 'in_stock' ? 'selected' : '' ?>>In Stock</option>
                    <option value="low_stock" <?= $selectedStock === 'low_stock' ? 'selected' : '' ?>>Low Stock</option>
                    <option value="out_of_stock" <?= $selectedStock === 'out_of_stock' ? 'selected' : '' ?>>Out of Stock</option>
                </select>
            </div>

            <div style="display: flex; gap: 8px;">
                <button type="submit" class="btn btn-primary" style="height: 38px;">Filter</button>
                <?php if ($search !== '' || $selectedCat || $selectedStock !== 'all'): ?>
                    <a href="/inventory" class="btn btn-secondary" style="height: 38px;">Reset</a>
                <?php endif; ?>
            </div>
        </form>
    </div>
</div>

<!-- Stock Ledger Table -->
<div class="card">
    <div class="card-header">
        <div>
            <span class="card-title">&#128230; Accessory Stock Ledger</span>
            <span style="color: var(--text-muted); font-size: 0.82rem; margin-left: 8px;">
                (<?= number_format($totalRecords) ?> items matching criteria)
            </span>
        </div>
        <?php if ($canViewCost && !empty($metrics['total_valuation'])): ?>
            <div style="font-size: 0.85rem; color: var(--text-muted);">
                Total Stock Valuation: <strong style="color: var(--primary);"><?= format_currency($metrics['total_valuation']) ?></strong>
            </div>
        <?php endif; ?>
    </div>

    <div class="card-body" style="padding: 0;">
        <div class="table-responsive">
            <table class="data-table">
                <thead>
                    <tr>
                        <th style="width: 45px;">#</th>
                        <th>Product &amp; Model</th>
                        <th>Category</th>
                        <th>SKU</th>
                        <th style="text-align: center;">On-Hand Stock</th>
                        <th style="text-align: center;">Alert Level</th>
                        <th style="text-align: right;">Sale Price</th>
                        <?php if ($canViewCost): ?>
                            <th style="text-align: right;">Unit Cost</th>
                            <th style="text-align: right;">Holding Value</th>
                        <?php endif; ?>
                        <th style="text-align: center;">Last Inward</th>
                        <th style="text-align: right; width: 140px;">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($inventory)): ?>
                        <tr>
                            <td colspan="<?= $canViewCost ? '11' : '9' ?>" style="text-align: center; color: var(--text-muted); padding: 36px;">
                                No inventory stock found matching your filter criteria.
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($inventory as $idx => $item): 
                            $qty = (int)$item['stock_quantity'];
                            $alert = (int)$item['min_stock_alert'];
                        ?>
                            <tr>
                                <td style="color: var(--text-muted); font-size: 0.82rem;"><?= ($page - 1) * 25 + $idx + 1 ?></td>
                                <td>
                                    <a href="/products/<?= (int)$item['id'] ?>" style="font-weight: 600; color: var(--primary); text-decoration: none;">
                                        <?= e($item['brand']) ?> <?= e($item['model_name']) ?>
                                    </a>
                                </td>
                                <td>
                                    <span style="font-size: 0.85rem; color: var(--text-muted);"><?= e($item['category_name']) ?></span>
                                </td>
                                <td>
                                    <?php if (!empty($item['sku'])): ?>
                                        <code style="font-size: 0.8rem; background: #f1f5f9; padding: 2px 6px; border-radius: 4px;"><?= e($item['sku']) ?></code>
                                    <?php else: ?>
                                        <span style="color: var(--text-muted); font-size: 0.8rem;">—</span>
                                    <?php endif; ?>
                                </td>
                                <td style="text-align: center;">
                                    <?php if ($qty <= 0): ?>
                                        <span class="badge badge-danger">0 Units</span>
                                    <?php elseif ($qty <= $alert): ?>
                                        <span class="badge badge-warning" title="Stock at or below alert threshold"><?= $qty ?> Units</span>
                                    <?php else: ?>
                                        <span class="badge badge-success"><?= $qty ?> Units</span>
                                    <?php endif; ?>
                                </td>
                                <td style="text-align: center; font-size: 0.85rem; color: var(--text-muted);">
                                    <?= $alert ?>
                                </td>
                                <td style="text-align: right; font-weight: 500;">
                                    <?= $item['default_sale_price'] !== null ? format_currency($item['default_sale_price']) : '<span style="color:var(--text-muted)">—</span>' ?>
                                </td>
                                <?php if ($canViewCost): ?>
                                    <td style="text-align: right; font-size: 0.85rem; color: var(--text-muted);">
                                        <?= (float)$item['avg_unit_cost'] > 0 ? format_currency($item['avg_unit_cost']) : '—' ?>
                                    </td>
                                    <td style="text-align: right; font-size: 0.88rem; font-weight: 600; color: var(--text-main);">
                                        <?= format_currency($qty * (float)$item['avg_unit_cost']) ?>
                                    </td>
                                <?php endif; ?>
                                <td style="text-align: center; font-size: 0.8rem; color: var(--text-muted);">
                                    <?= !empty($item['last_restocked_at']) ? e(date('d M Y', strtotime($item['last_restocked_at']))) : '—' ?>
                                </td>
                                <td style="text-align: right;">
                                    <div style="display: inline-flex; gap: 6px; align-items: center;">
                                        <a href="/products/<?= (int)$item['id'] ?>" class="btn btn-secondary btn-sm" title="Inspect Ledger History">
                                            Ledger
                                        </a>

                                        <?php if ($canManage): ?>
                                            <a href="/inventory/<?= (int)$item['id'] ?>/adjust" class="btn btn-primary btn-sm" title="Record Controlled Adjustment">
                                                Adjust
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
        <div class="card-footer" style="display: flex; justify-content: space-between; align-items: center; padding: 12px 16px;">
            <div style="font-size: 0.85rem; color: var(--text-muted);">
                Showing page <?= $page ?> of <?= $totalPages ?>
            </div>
            <div style="display: flex; gap: 6px;">
                <?php if ($page > 1): ?>
                    <a href="/inventory?page=<?= $page - 1 ?>&q=<?= urlencode($search) ?>&category_id=<?= (int)$selectedCat ?>&stock_status=<?= urlencode($selectedStock) ?>" class="btn btn-secondary btn-sm">&larr; Previous</a>
                <?php endif; ?>
                <?php if ($page < $totalPages): ?>
                    <a href="/inventory?page=<?= $page + 1 ?>&q=<?= urlencode($search) ?>&category_id=<?= (int)$selectedCat ?>&stock_status=<?= urlencode($selectedStock) ?>" class="btn btn-secondary btn-sm">Next &rarr;</a>
                <?php endif; ?>
            </div>
        </div>
    <?php endif; ?>
</div>
