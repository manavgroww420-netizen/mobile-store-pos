<?php
/**
 * Inventory Valuation & Low Stock Report
 * File: views/reports/inventory.php
 */
?>
<div class="page-header d-flex flex-wrap justify-content-between align-items-center mb-4 gap-2">
    <div>
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb mb-1">
                <li class="breadcrumb-item"><a href="/reports">Reports Hub</a></li>
                <li class="breadcrumb-item active">Inventory Valuation</li>
            </ol>
        </nav>
        <h1 class="h3 mb-0">Inventory Valuation &amp; Low Stock Report</h1>
    </div>
    <div class="d-flex gap-2">
        <a href="?category_id=<?= $categoryId ?>&type=<?= urlencode($typeFilter) ?>&stock=<?= urlencode($stockFilter) ?>&export=csv" class="btn btn-outline-success btn-sm">
            <span>&#128190;</span> Export CSV
        </a>
        <a href="/reports" class="btn btn-outline-secondary btn-sm">Back to Hub</a>
    </div>
</div>

<!-- Filter Bar -->
<div class="card shadow-sm mb-4">
    <div class="card-body py-3">
        <form method="GET" action="/reports/inventory" class="row g-2 align-items-center">
            <div class="col-md-3">
                <label class="form-label text-xs text-muted mb-1">Category</label>
                <select name="category_id" class="form-select form-select-sm">
                    <option value="0">All Categories</option>
                    <?php foreach ($categories as $cat): ?>
                        <option value="<?= $cat['id'] ?>" <?= $categoryId === (int)$cat['id'] ? 'selected' : '' ?>><?= htmlspecialchars($cat['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label text-xs text-muted mb-1">Product Type</label>
                <select name="type" class="form-select form-select-sm">
                    <option value="ALL" <?= $typeFilter === 'ALL' ? 'selected' : '' ?>>All Types</option>
                    <option value="SERIALIZED_PHONE" <?= $typeFilter === 'SERIALIZED_PHONE' ? 'selected' : '' ?>>Serialized Mobiles</option>
                    <option value="ACCESSORY" <?= $typeFilter === 'ACCESSORY' ? 'selected' : '' ?>>Accessories &amp; Spares</option>
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label text-xs text-muted mb-1">Stock Status</label>
                <select name="stock" class="form-select form-select-sm">
                    <option value="ALL" <?= $stockFilter === 'ALL' ? 'selected' : '' ?>>All Stock Levels</option>
                    <option value="LOW" <?= $stockFilter === 'LOW' ? 'selected' : '' ?>>Low Stock Warning (&lt;= Alert)</option>
                    <option value="OUT" <?= $stockFilter === 'OUT' ? 'selected' : '' ?>>Out of Stock (0 units)</option>
                    <option value="IN_STOCK" <?= $stockFilter === 'IN_STOCK' ? 'selected' : '' ?>>In Stock Only</option>
                </select>
            </div>
            <div class="col-md-3 d-flex align-items-end gap-2">
                <button type="submit" class="btn btn-sm btn-primary flex-grow-1">Apply Filters</button>
                <a href="/reports/inventory" class="btn btn-sm btn-outline-secondary" title="Reset">&#10006;</a>
            </div>
        </form>
    </div>
</div>

<!-- Summaries Row -->
<div class="row g-3 mb-4">
    <div class="col-md-3">
        <div class="card stat-card border-left-primary shadow-sm h-100">
            <div class="card-body">
                <div class="text-xs font-weight-bold text-primary text-uppercase mb-1">Catalog Products</div>
                <div class="h4 mb-0 font-weight-bold"><?= number_format($totalItemsCount) ?></div>
                <div class="small text-muted mt-1">Unique SKUs &amp; phone models</div>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card stat-card border-left-info shadow-sm h-100">
            <div class="card-body">
                <div class="text-xs font-weight-bold text-info text-uppercase mb-1">Total Stock Units</div>
                <div class="h4 mb-0 font-weight-bold text-info"><?= number_format($totalStockUnits) ?></div>
                <div class="small text-muted mt-1">Combined handsets &amp; accessories</div>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card stat-card border-left-danger shadow-sm h-100">
            <div class="card-body">
                <div class="text-xs font-weight-bold text-danger text-uppercase mb-1">Low Stock Alerts</div>
                <div class="h4 mb-0 font-weight-bold text-danger"><?= number_format($lowStockCount) ?></div>
                <div class="small text-muted mt-1">Items at or below reorder threshold</div>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card stat-card border-left-success shadow-sm h-100">
            <div class="card-body">
                <div class="text-xs font-weight-bold text-success text-uppercase mb-1">Inventory Asset Valuation</div>
                <div class="h4 mb-0 font-weight-bold text-success">
                    <?php if ($canViewCosts): ?>
                        ₹ <?= number_format((float)$totalAssetValuation, 2) ?>
                    <?php else: ?>
                        <span class="text-muted">Protected</span>
                    <?php endif; ?>
                </div>
                <div class="small text-muted mt-1">Realized asset cost basis</div>
            </div>
        </div>
    </div>
</div>

<!-- Products Table -->
<div class="card shadow-sm mb-4">
    <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
        <h6 class="m-0 font-weight-bold text-dark">Inventory Items &amp; Valuations (<?= number_format($totalItemsCount) ?> entries)</h6>
        <span class="text-xs text-muted">Valued at Weighted-Average Cost Basis</span>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover table-striped align-middle mb-0">
                <thead class="table-light text-xs text-uppercase text-muted">
                    <tr>
                        <th style="width: 25%;">Product / Model</th>
                        <th style="width: 15%;">Category</th>
                        <th style="width: 10%;">Type</th>
                        <th style="width: 10%;" class="text-center">Stock Level</th>
                        <th style="width: 10%;" class="text-center">Min Alert</th>
                        <?php if ($canViewCosts): ?>
                            <th style="width: 15%;" class="text-end">Avg Cost (₹)</th>
                            <th style="width: 15%;" class="text-end">Asset Value (₹)</th>
                        <?php endif; ?>
                        <th style="width: 10%;" class="text-center">Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($products)): ?>
                        <tr>
                            <td colspan="<?= $canViewCosts ? 8 : 6 ?>" class="text-center py-5 text-muted">No products found matching filters.</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($products as $p): ?>
                            <?php 
                                $isOut = ((int)$p['actual_stock_level'] <= 0);
                                $isLow = (!$isOut && (int)$p['actual_stock_level'] <= (int)$p['min_stock_alert']);
                            ?>
                            <tr>
                                <td>
                                    <div class="font-weight-bold text-dark"><?= htmlspecialchars($p['brand'] . ' ' . $p['model_name']) ?></div>
                                    <div class="text-xs text-muted font-monospace"><?= htmlspecialchars($p['sku'] ?? 'NO-SKU') ?></div>
                                </td>
                                <td class="small"><?= htmlspecialchars($p['category_name']) ?></td>
                                <td>
                                    <span class="badge <?= $p['item_type'] === 'SERIALIZED_PHONE' ? 'bg-primary' : 'bg-info text-dark' ?>" style="font-size: 0.7rem;">
                                        <?= $p['item_type'] === 'SERIALIZED_PHONE' ? 'Handset' : 'Accessory' ?>
                                    </span>
                                </td>
                                <td class="text-center font-monospace font-weight-bold <?= $isOut ? 'text-danger' : ($isLow ? 'text-warning' : 'text-dark') ?>">
                                    <?= $p['actual_stock_level'] ?>
                                </td>
                                <td class="text-center font-monospace text-muted small"><?= $p['min_stock_alert'] ?></td>
                                <?php if ($canViewCosts): ?>
                                    <td class="text-end font-monospace">₹ <?= number_format((float)$p['avg_unit_cost'], 2) ?></td>
                                    <td class="text-end font-monospace font-weight-bold">₹ <?= number_format((float)$p['asset_valuation'], 2) ?></td>
                                <?php endif; ?>
                                <td class="text-center">
                                    <?php if ($isOut): ?>
                                        <span class="badge bg-danger">OUT OF STOCK</span>
                                    <?php elseif ($isLow): ?>
                                        <span class="badge bg-warning text-dark">REORDER ALERT</span>
                                    <?php else: ?>
                                        <span class="badge bg-success">IN STOCK</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
