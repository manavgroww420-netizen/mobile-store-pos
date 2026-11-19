<?php
/**
 * Controlled Stock Adjustment View
 * File: views/inventory/adjust.php
 */
$currentQty = (int)$product['current_quantity'];
?>

<div class="card" style="max-width: 650px; margin: 0 auto;">
    <div class="card-header">
        <div>
            <span class="card-title">&#128230; Controlled Stock Adjustment</span>
            <div style="font-size: 0.82rem; color: var(--text-muted); margin-top: 2px;">
                Record double-entry inventory correction for non-serialized stock
            </div>
        </div>
        <a href="/products/<?= (int)$product['id'] ?>" class="btn btn-secondary btn-sm">&larr; Back to Product</a>
    </div>

    <div class="card-body">
        <!-- Target Product Summary -->
        <div style="background: #f8fafc; border: 1px solid var(--border-color); border-radius: 6px; padding: 14px 16px; margin-bottom: 20px;">
            <div style="display: flex; justify-content: space-between; align-items: center;">
                <div>
                    <h3 style="margin: 0; font-size: 1.05rem; color: var(--text-main); font-weight: 700;">
                        <?= e($product['brand']) ?> <?= e($product['model_name']) ?>
                    </h3>
                    <div style="font-size: 0.8rem; color: var(--text-muted); margin-top: 2px;">
                        Category: <strong><?= e($product['category_name']) ?></strong>
                        <?php if (!empty($product['sku'])): ?>
                            &bull; SKU: <code><?= e($product['sku']) ?></code>
                        <?php endif; ?>
                    </div>
                </div>
                <div style="text-align: right;">
                    <div style="font-size: 0.75rem; color: var(--text-muted); text-transform: uppercase; font-weight: 600;">Current Stock</div>
                    <div style="font-size: 1.35rem; font-weight: 700; color: <?= $currentQty <= 0 ? 'var(--danger)' : 'var(--primary)' ?>;">
                        <?= $currentQty ?> Units
                    </div>
                </div>
            </div>
        </div>

        <form method="POST" action="/inventory/<?= (int)$product['id'] ?>/adjust" data-prevent-double-submit autocomplete="off">
            <?= csrf_field() ?>

            <div class="form-group">
                <label class="form-label required" for="adjustment_type">Adjustment Direction</label>
                <select id="adjustment_type" name="adjustment_type" class="form-control" required autofocus onchange="updateDirectionHint(this.value)">
                    <option value="ADJUSTMENT_ADD">&#10133; Stock Addition (Surplus / Found Items / Restock Correction)</option>
                    <option value="ADJUSTMENT_SUB">&#10134; Stock Deduction (Damaged Goods / Scrap / Inventory Shrinkage)</option>
                    <option value="OPENING_STOCK">&#127749; Opening Balance (Initial Stock Baseline)</option>
                </select>
                <small id="directionHint" style="color: var(--text-muted); font-size: 0.78rem; margin-top: 4px; display: block;">
                    Will increment current stock count in inventory.
                </small>
            </div>

            <div class="grid grid-cols-2">
                <div class="form-group">
                    <label class="form-label required" for="quantity">Quantity Units</label>
                    <input 
                        type="number" 
                        id="quantity" 
                        name="quantity" 
                        class="form-control" 
                        placeholder="e.g. 5" 
                        min="1" 
                        required
                    >
                    <small style="color: var(--text-muted); font-size: 0.75rem;">Positive whole integer.</small>
                </div>

                <?php if ($canViewCost): ?>
                    <div class="form-group">
                        <label class="form-label" for="unit_cost">Unit Cost Basis (₹)</label>
                        <input 
                            type="number" 
                            id="unit_cost" 
                            name="unit_cost" 
                            class="form-control" 
                            placeholder="<?= number_format((float)$product['avg_unit_cost'], 2, '.', '') ?>" 
                            step="0.01" 
                            min="0"
                            value="<?= (float)$product['avg_unit_cost'] > 0 ? (float)$product['avg_unit_cost'] : '' ?>"
                        >
                        <small style="color: var(--text-muted); font-size: 0.75rem;">Defaults to current weighted average cost.</small>
                    </div>
                <?php endif; ?>
            </div>

            <div class="form-group" style="margin-bottom: 20px;">
                <label class="form-label required" for="reason">Mandatory Business Reason</label>
                <textarea 
                    id="reason" 
                    name="reason" 
                    class="form-control" 
                    rows="3" 
                    placeholder="Explain why this inventory adjustment is taking place (e.g. Physical stock count discrepancy, Screen protector damaged during unboxing, Found unopened box)..." 
                    required 
                    minlength="3"
                    maxlength="255"
                ></textarea>
                <small style="color: var(--text-muted); font-size: 0.75rem;">This rationale will be permanently recorded in the immutable audit log and stock movement ledger.</small>
            </div>

            <div style="background: #fffbeb; border: 1px solid #fef3c7; border-radius: 6px; padding: 12px; margin-bottom: 24px; font-size: 0.82rem; color: #92400e;">
                <strong>&#9888; Ledger Invariant:</strong> Stock cannot become negative. The system executes this update inside a serialized database transaction with row-level locks.
            </div>

            <div style="display: flex; gap: 12px; justify-content: flex-end;">
                <a href="/products/<?= (int)$product['id'] ?>" class="btn btn-secondary">Cancel</a>
                <button type="submit" class="btn btn-primary">Commit Stock Movement</button>
            </div>
        </form>
    </div>
</div>

<script>
function updateDirectionHint(val) {
    const hint = document.getElementById('directionHint');
    if (!hint) return;
    if (val === 'ADJUSTMENT_ADD') {
        hint.textContent = 'Will increment current on-hand stock count in inventory.';
        hint.style.color = 'var(--success)';
    } else if (val === 'ADJUSTMENT_SUB') {
        hint.textContent = 'Will decrement current on-hand stock count. Cannot reduce stock below 0.';
        hint.style.color = 'var(--danger)';
    } else if (val === 'OPENING_STOCK') {
        hint.textContent = 'Sets or increments initial stock baseline in inventory.';
        hint.style.color = 'var(--primary)';
    }
}
</script>
