<?php
/**
 * Product Detail & Stock Ledger History View
 * File: views/products/show.php
 */
$isPhone = $product['item_type'] === 'SERIALIZED_PHONE';
$qty = (int)$product['stock_quantity'];
$alert = (int)$product['min_stock_alert'];
?>

<div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
    <div>
        <h2 style="font-size: 1.35rem; font-weight: 700; color: var(--text-main); margin: 0 0 4px 0;">
            <?= e($product['brand']) ?> <?= e($product['model_name']) ?>
        </h2>
        <div style="font-size: 0.85rem; color: var(--text-muted);">
            Category: <strong style="color: var(--text-main);"><?= e($product['category_name']) ?></strong> &bull;
            Type: <strong><?= $isPhone ? 'Serialized Mobile Phone' : 'Accessory / Non-Serialized' ?></strong>
        </div>
    </div>
    <div style="display: flex; gap: 8px;">
        <a href="/products" class="btn btn-secondary btn-sm">&larr; Back to Catalog</a>
        <?php if ($canManage): ?>
            <a href="/products/<?= (int)$product['id'] ?>/edit" class="btn btn-secondary btn-sm">&#9998; Edit</a>
            <?php if (!$isPhone): ?>
                <a href="/inventory/<?= (int)$product['id'] ?>/adjust" class="btn btn-primary btn-sm">&#128230; Adjust Stock</a>
            <?php endif; ?>
        <?php endif; ?>
    </div>
</div>

<div class="grid grid-cols-3" style="margin-bottom: 24px;">
    <!-- Card 1: Catalog Details -->
    <div class="card" style="margin-bottom: 0;">
        <div class="card-header" style="padding: 12px 16px;">
            <span class="card-title" style="font-size: 0.95rem;">Catalog Attributes</span>
            <?php if ((int)$product['is_active'] === 1): ?>
                <span class="badge badge-success">Active</span>
            <?php else: ?>
                <span class="badge badge-danger">Inactive</span>
            <?php endif; ?>
        </div>
        <div class="card-body" style="padding: 16px; font-size: 0.88rem;">
            <div style="display: flex; justify-content: space-between; padding: 6px 0; border-bottom: 1px solid var(--border-color);">
                <span style="color: var(--text-muted);">SKU / Barcode:</span>
                <code><?= e($product['sku'] ?? 'Not set') ?></code>
            </div>
            <div style="display: flex; justify-content: space-between; padding: 6px 0; border-bottom: 1px solid var(--border-color);">
                <span style="color: var(--text-muted);">Default Sale Price:</span>
                <strong style="color: var(--text-main);">
                    <?= $product['default_sale_price'] !== null ? format_currency($product['default_sale_price']) : 'Not configured' ?>
                </strong>
            </div>
            <div style="display: flex; justify-content: space-between; padding: 6px 0;">
                <span style="color: var(--text-muted);">Created:</span>
                <span><?= e(date('d M Y, h:i A', strtotime($product['created_at']))) ?></span>
            </div>
        </div>
    </div>

    <!-- Card 2: Current Inventory Status -->
    <div class="card" style="margin-bottom: 0;">
        <div class="card-header" style="padding: 12px 16px;">
            <span class="card-title" style="font-size: 0.95rem;">Inventory Status</span>
            <?php if ($isPhone): ?>
                <span class="badge badge-info">IMEI Episodes</span>
            <?php else: ?>
                <?php if ($qty <= 0): ?>
                    <span class="badge badge-danger">Out of Stock</span>
                <?php elseif ($qty <= $alert): ?>
                    <span class="badge badge-warning">Low Stock</span>
                <?php else: ?>
                    <span class="badge badge-success">In Stock</span>
                <?php endif; ?>
            <?php endif; ?>
        </div>
        <div class="card-body" style="padding: 16px; font-size: 0.88rem;">
            <?php if ($isPhone): ?>
                <p style="color: var(--text-muted); font-size: 0.85rem; margin: 0 0 12px 0;">
                    Serialized phones are physically tracked by individual device IMEI. Stock is managed through the Serialized Intake &amp; Device Registry.
                </p>
                <a href="/phones/new" class="btn btn-secondary btn-sm" style="width: 100%; text-align: center;">
                    View Device Episodes &rarr;
                </a>
            <?php else: ?>
                <div style="display: flex; justify-content: space-between; padding: 6px 0; border-bottom: 1px solid var(--border-color);">
                    <span style="color: var(--text-muted);">On-Hand Quantity:</span>
                    <span style="font-size: 1.25rem; font-weight: 700; color: <?= $qty <= 0 ? 'var(--danger)' : ($qty <= $alert ? '#b45309' : 'var(--success)') ?>;">
                        <?= $qty ?> Units
                    </span>
                </div>
                <div style="display: flex; justify-content: space-between; padding: 6px 0; border-bottom: 1px solid var(--border-color);">
                    <span style="color: var(--text-muted);">Min Alert Threshold:</span>
                    <span><?= $alert ?> Units</span>
                </div>
                <div style="display: flex; justify-content: space-between; padding: 6px 0;">
                    <span style="color: var(--text-muted);">Last Restocked:</span>
                    <span><?= !empty($product['last_restocked_at']) ? e(date('d M Y', strtotime($product['last_restocked_at']))) : '—' ?></span>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Card 3: Financial & Valuation (Permission Restricted) -->
    <div class="card" style="margin-bottom: 0;">
        <div class="card-header" style="padding: 12px 16px;">
            <span class="card-title" style="font-size: 0.95rem;">Cost Valuation</span>
            <?php if ($canViewCost): ?>
                <span class="badge badge-info">&#128274; Authorized</span>
            <?php else: ?>
                <span class="badge badge-secondary">&#128274; Restricted</span>
            <?php endif; ?>
        </div>
        <div class="card-body" style="padding: 16px; font-size: 0.88rem;">
            <?php if ($canViewCost): ?>
                <div style="display: flex; justify-content: space-between; padding: 6px 0; border-bottom: 1px solid var(--border-color);">
                    <span style="color: var(--text-muted);">Weighted Avg Cost:</span>
                    <strong style="color: var(--text-main);"><?= format_currency($product['avg_unit_cost']) ?></strong>
                </div>
                <div style="display: flex; justify-content: space-between; padding: 6px 0; border-bottom: 1px solid var(--border-color);">
                    <span style="color: var(--text-muted);">Total Inventory Value:</span>
                    <strong style="color: var(--primary); font-size: 1.05rem;">
                        <?= format_currency($qty * (float)$product['avg_unit_cost']) ?>
                    </strong>
                </div>
                <div style="display: flex; justify-content: space-between; padding: 6px 0;">
                    <span style="color: var(--text-muted);">Gross Margin Est.:</span>
                    <span>
                        <?php 
                        $salePrice = (float)($product['default_sale_price'] ?? 0);
                        $cost = (float)$product['avg_unit_cost'];
                        if ($salePrice > 0 && $cost > 0) {
                            $margin = (($salePrice - $cost) / $salePrice) * 100;
                            echo round($margin, 1) . '%';
                        } else {
                            echo '—';
                        }
                        ?>
                    </span>
                </div>
            <?php else: ?>
                <div style="color: var(--text-muted); font-size: 0.84rem; text-align: center; padding: 24px 0;">
                    <div style="font-size: 1.5rem; margin-bottom: 6px;">&#128274;</div>
                    Cost prices and inventory valuation are restricted to authorized accounts.
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Stock Movement Ledger Section -->
<div class="card">
    <div class="card-header">
        <div>
            <span class="card-title">&#128220; Double-Entry Stock Movement Ledger</span>
            <div style="font-size: 0.82rem; color: var(--text-muted); margin-top: 2px;">
                Immutable historical trail of inventory adjustments, receipts, and allocations
            </div>
        </div>
    </div>

    <div class="card-body" style="padding: 0;">
        <div class="table-responsive">
            <table class="data-table">
                <thead>
                    <tr>
                        <th style="width: 140px;">Timestamp</th>
                        <th>Movement Type</th>
                        <th style="text-align: center;">Change</th>
                        <th style="text-align: center;">Before &rarr; After</th>
                        <?php if ($canViewCost): ?>
                            <th style="text-align: right;">Unit Cost</th>
                        <?php endif; ?>
                        <th>Reference / Reason</th>
                        <th>Logged By</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($movements)): ?>
                        <tr>
                            <td colspan="<?= $canViewCost ? '7' : '6' ?>" style="text-align: center; color: var(--text-muted); padding: 32px;">
                                No stock movements recorded for this item yet.
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($movements as $m): 
                            $change = (int)$m['quantity_change'];
                            $mType = $m['movement_type'];
                        ?>
                            <tr>
                                <td style="font-size: 0.82rem; color: var(--text-muted); white-space: nowrap;">
                                    <?= e(date('d M Y, H:i', strtotime($m['created_at']))) ?>
                                </td>
                                <td>
                                    <?php if ($mType === 'OPENING_STOCK'): ?>
                                        <span class="badge badge-info" style="font-size: 0.72rem;">Opening Stock</span>
                                    <?php elseif ($mType === 'ADJUSTMENT_ADD'): ?>
                                        <span class="badge badge-success" style="font-size: 0.72rem;">Adjustment (+)</span>
                                    <?php elseif ($mType === 'ADJUSTMENT_SUB'): ?>
                                        <span class="badge badge-danger" style="font-size: 0.72rem;">Adjustment (-)</span>
                                    <?php elseif ($mType === 'PURCHASE'): ?>
                                        <span class="badge badge-success" style="font-size: 0.72rem;">Purchase</span>
                                    <?php elseif ($mType === 'SALE'): ?>
                                        <span class="badge badge-secondary" style="font-size: 0.72rem;">Sale</span>
                                    <?php elseif ($mType === 'SALE_RETURN'): ?>
                                        <span class="badge badge-warning" style="font-size: 0.72rem;">Sale Return</span>
                                    <?php else: ?>
                                        <span class="badge badge-secondary" style="font-size: 0.72rem;"><?= e($mType) ?></span>
                                    <?php endif; ?>
                                </td>
                                <td style="text-align: center; font-weight: 700; font-family: var(--font-mono);">
                                    <?php if ($change > 0): ?>
                                        <span style="color: var(--success);">+<?= $change ?></span>
                                    <?php else: ?>
                                        <span style="color: var(--danger);"><?= $change ?></span>
                                    <?php endif; ?>
                                </td>
                                <td style="text-align: center; font-family: var(--font-mono); font-size: 0.85rem;">
                                    <?= (int)$m['quantity_before'] ?> &rarr; <strong><?= (int)$m['quantity_after'] ?></strong>
                                </td>
                                <?php if ($canViewCost): ?>
                                    <td style="text-align: right; font-size: 0.85rem; color: var(--text-muted);">
                                        <?= (float)$m['unit_cost'] > 0 ? format_currency($m['unit_cost']) : '—' ?>
                                    </td>
                                <?php endif; ?>
                                <td style="font-size: 0.85rem; color: var(--text-main); max-width: 250px;">
                                    <?= e($m['notes'] ?? '—') ?>
                                </td>
                                <td style="font-size: 0.82rem; color: var(--text-muted);">
                                    <?= e($m['operator_name'] ?? $m['operator_username'] ?? 'System') ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
