<?php
/**
 * Master Product Catalog Index View
 * File: views/products/index.php
 */
?>

<div class="card" style="margin-bottom: 20px;">
    <div class="card-body" style="padding: 16px;">
        <form method="GET" action="/products" style="display: flex; gap: 12px; flex-wrap: wrap; align-items: flex-end;">
            <div style="flex: 2; min-width: 220px;">
                <label class="form-label" style="font-size: 0.8rem; margin-bottom: 4px;" for="q">Search Model, Brand, or SKU</label>
                <input 
                    type="text" 
                    id="q" 
                    name="q" 
                    class="form-control" 
                    placeholder="e.g. Galaxy S23, Boat Airdopes, SKU..." 
                    value="<?= e($search) ?>"
                >
            </div>

            <div style="flex: 1; min-width: 160px;">
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
                <label class="form-label" style="font-size: 0.8rem; margin-bottom: 4px;" for="item_type">Type</label>
                <select id="item_type" name="item_type" class="form-control">
                    <option value="ALL" <?= $selectedType === 'ALL' ? 'selected' : '' ?>>All Item Types</option>
                    <option value="ACCESSORY" <?= $selectedType === 'ACCESSORY' ? 'selected' : '' ?>>Accessories</option>
                    <option value="SERIALIZED_PHONE" <?= $selectedType === 'SERIALIZED_PHONE' ? 'selected' : '' ?>>Phones (Serialized)</option>
                </select>
            </div>

            <div style="flex: 1; min-width: 140px;">
                <label class="form-label" style="font-size: 0.8rem; margin-bottom: 4px;" for="stock_status">Stock Status</label>
                <select id="stock_status" name="stock_status" class="form-control">
                    <option value="all" <?= $selectedStock === 'all' ? 'selected' : '' ?>>All Stock</option>
                    <option value="in_stock" <?= $selectedStock === 'in_stock' ? 'selected' : '' ?>>In Stock</option>
                    <option value="low_stock" <?= $selectedStock === 'low_stock' ? 'selected' : '' ?>>Low Stock</option>
                    <option value="out_of_stock" <?= $selectedStock === 'out_of_stock' ? 'selected' : '' ?>>Out of Stock</option>
                </select>
            </div>

            <div style="display: flex; gap: 8px;">
                <button type="submit" class="btn btn-primary" style="height: 38px;">Filter</button>
                <?php if ($search !== '' || $selectedCat || $selectedType !== 'ALL' || $selectedStock !== 'all'): ?>
                    <a href="/products" class="btn btn-secondary" style="height: 38px;" title="Reset filters">Reset</a>
                <?php endif; ?>
            </div>
        </form>
    </div>
</div>

<div class="card">
    <div class="card-header">
        <div>
            <span class="card-title">&#128196; Master Product Catalog</span>
            <span style="color: var(--text-muted); font-size: 0.82rem; margin-left: 8px;">
                (<?= number_format($totalRecords) ?> total products matching criteria)
            </span>
        </div>
        <div>
            <?php if ($canManage): ?>
                <a href="/products/create" class="btn btn-primary btn-sm">&#10133; Add New Product</a>
            <?php endif; ?>
        </div>
    </div>

    <div class="card-body" style="padding: 0;">
        <div class="table-responsive">
            <table class="data-table">
                <thead>
                    <tr>
                        <th style="width: 45px;">#</th>
                        <th>Brand &amp; Model Name</th>
                        <th>Category</th>
                        <th>Type</th>
                        <th>SKU</th>
                        <th style="text-align: right;">Sale Price</th>
                        <?php if ($canViewCost): ?>
                            <th style="text-align: right;">Avg Cost</th>
                        <?php endif; ?>
                        <th style="text-align: center;">Stock</th>
                        <th style="text-align: center;">Status</th>
                        <th style="text-align: right; width: 140px;">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($products)): ?>
                        <tr>
                            <td colspan="<?= $canViewCost ? '10' : '9' ?>" style="text-align: center; color: var(--text-muted); padding: 36px;">
                                No products found matching your filter criteria.
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($products as $idx => $p): 
                            $qty = (int)$p['stock_quantity'];
                            $alert = (int)$p['min_stock_alert'];
                            $isPhone = $p['item_type'] === 'SERIALIZED_PHONE';
                        ?>
                            <tr>
                                <td style="color: var(--text-muted); font-size: 0.82rem;"><?= ($page - 1) * 20 + $idx + 1 ?></td>
                                <td>
                                    <a href="/products/<?= (int)$p['id'] ?>" style="font-weight: 600; color: var(--primary); font-size: 0.92rem; text-decoration: none;">
                                        <?= e($p['brand']) ?> <?= e($p['model_name']) ?>
                                    </a>
                                </td>
                                <td>
                                    <span style="font-size: 0.85rem; color: var(--text-muted);"><?= e($p['category_name']) ?></span>
                                </td>
                                <td>
                                    <?php if ($isPhone): ?>
                                        <span class="badge badge-info" style="font-size: 0.72rem;">Phone</span>
                                    <?php else: ?>
                                        <span class="badge badge-secondary" style="font-size: 0.72rem;">Accessory</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if (!empty($p['sku'])): ?>
                                        <code style="font-size: 0.8rem; background: #f1f5f9; padding: 2px 6px; border-radius: 4px;"><?= e($p['sku']) ?></code>
                                    <?php else: ?>
                                        <span style="color: var(--text-muted); font-size: 0.8rem;">—</span>
                                    <?php endif; ?>
                                </td>
                                <td style="text-align: right; font-weight: 500;">
                                    <?= $p['default_sale_price'] !== null ? format_currency($p['default_sale_price']) : '<span style="color:var(--text-muted)">—</span>' ?>
                                </td>
                                <?php if ($canViewCost): ?>
                                    <td style="text-align: right; color: var(--text-muted); font-size: 0.88rem;">
                                        <?= !$isPhone && (float)$p['avg_unit_cost'] > 0 ? format_currency($p['avg_unit_cost']) : '—' ?>
                                    </td>
                                <?php endif; ?>
                                <td style="text-align: center;">
                                    <?php if ($isPhone): ?>
                                        <span class="badge badge-secondary" title="Stock managed via IMEI device registry">Serialized</span>
                                    <?php else: ?>
                                        <?php if ($qty <= 0): ?>
                                            <span class="badge badge-danger">Out of Stock (0)</span>
                                        <?php elseif ($qty <= $alert): ?>
                                            <span class="badge badge-warning" title="Low stock threshold: <?= $alert ?>">Low (<?= $qty ?>)</span>
                                        <?php else: ?>
                                            <span class="badge badge-success"><?= $qty ?> In Stock</span>
                                        <?php endif; ?>
                                    <?php endif; ?>
                                </td>
                                <td style="text-align: center;">
                                    <?php if ((int)$p['is_active'] === 1): ?>
                                        <span class="badge badge-success">Active</span>
                                    <?php else: ?>
                                        <span class="badge badge-danger">Inactive</span>
                                    <?php endif; ?>
                                </td>
                                <td style="text-align: right;">
                                    <div style="display: inline-flex; gap: 6px; align-items: center;">
                                        <a href="/products/<?= (int)$p['id'] ?>" class="btn btn-secondary btn-sm" title="View Product Details">
                                            View
                                        </a>

                                        <?php if ($canManage): ?>
                                            <a href="/products/<?= (int)$p['id'] ?>/edit" class="btn btn-secondary btn-sm" title="Edit Catalog Details">
                                                Edit
                                            </a>

                                            <?php if (!$isPhone): ?>
                                                <a href="/inventory/<?= (int)$p['id'] ?>/adjust" class="btn btn-secondary btn-sm" style="color: var(--primary);" title="Adjust Stock">
                                                    Stock
                                                </a>
                                            <?php endif; ?>
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
                    <a href="/products?page=<?= $page - 1 ?>&q=<?= urlencode($search) ?>&category_id=<?= (int)$selectedCat ?>&item_type=<?= urlencode($selectedType) ?>&stock_status=<?= urlencode($selectedStock) ?>" class="btn btn-secondary btn-sm">&larr; Previous</a>
                <?php endif; ?>
                <?php if ($page < $totalPages): ?>
                    <a href="/products?page=<?= $page + 1 ?>&q=<?= urlencode($search) ?>&category_id=<?= (int)$selectedCat ?>&item_type=<?= urlencode($selectedType) ?>&stock_status=<?= urlencode($selectedStock) ?>" class="btn btn-secondary btn-sm">Next &rarr;</a>
                <?php endif; ?>
            </div>
        </div>
    <?php endif; ?>
</div>
