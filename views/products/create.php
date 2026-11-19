<?php
/**
 * Create Product View
 * File: views/products/create.php
 */
?>

<div class="card" style="max-width: 750px; margin: 0 auto;">
    <div class="card-header">
        <div>
            <span class="card-title">&#10133; Add Product to Catalog</span>
            <div style="font-size: 0.82rem; color: var(--text-muted); margin-top: 2px;">
                Enter mobile phone model or accessory specifications
            </div>
        </div>
        <a href="/products" class="btn btn-secondary btn-sm">&larr; Back to Catalog</a>
    </div>

    <div class="card-body">
        <form method="POST" action="/products/create" data-prevent-double-submit autocomplete="off">
            <?= csrf_field() ?>

            <div class="grid grid-cols-2">
                <div class="form-group">
                    <label class="form-label required" for="category_id">Product Category</label>
                    <select id="category_id" name="category_id" class="form-control" required autofocus>
                        <option value="">— Select Category —</option>
                        <?php foreach ($categories as $cat): ?>
                            <option value="<?= (int)$cat['id'] ?>" <?= ((int)($formData['category_id'] ?? 0) === (int)$cat['id']) ? 'selected' : '' ?>>
                                <?= e($cat['name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-group">
                    <label class="form-label required" for="item_type">Item Classification</label>
                    <select id="item_type" name="item_type" class="form-control" required onchange="toggleStockSection(this.value)">
                        <option value="ACCESSORY" <?= (($formData['item_type'] ?? 'ACCESSORY') === 'ACCESSORY') ? 'selected' : '' ?>>
                            Non-Serialized Accessory (Bulk Quantity)
                        </option>
                        <option value="SERIALIZED_PHONE" <?= (($formData['item_type'] ?? '') === 'SERIALIZED_PHONE') ? 'selected' : '' ?>>
                            Serialized Mobile Phone (Tracked by IMEI)
                        </option>
                    </select>
                </div>
            </div>

            <div class="grid grid-cols-2">
                <div class="form-group">
                    <label class="form-label required" for="brand">Brand / Manufacturer</label>
                    <input 
                        type="text" 
                        id="brand" 
                        name="brand" 
                        class="form-control" 
                        placeholder="e.g. Samsung, Apple, Boat, OnePlus, Mi" 
                        value="<?= e($formData['brand'] ?? '') ?>"
                        required 
                        maxlength="100"
                    >
                </div>

                <div class="form-group">
                    <label class="form-label required" for="model_name">Model / Product Name</label>
                    <input 
                        type="text" 
                        id="model_name" 
                        name="model_name" 
                        class="form-control" 
                        placeholder="e.g. Galaxy S23 5G 128GB, Airdopes 141, 20W Type-C" 
                        value="<?= e($formData['model_name'] ?? '') ?>"
                        required 
                        maxlength="150"
                    >
                </div>
            </div>

            <div class="grid grid-cols-3">
                <div class="form-group">
                    <label class="form-label" for="sku">SKU / Item Barcode</label>
                    <input 
                        type="text" 
                        id="sku" 
                        name="sku" 
                        class="form-control" 
                        placeholder="e.g. SAM-S23-128, CHG-20W-TYPC" 
                        value="<?= e($formData['sku'] ?? '') ?>"
                        maxlength="50"
                    >
                    <small style="color: var(--text-muted); font-size: 0.75rem;">Unique barcode/SKU code.</small>
                </div>

                <div class="form-group">
                    <label class="form-label" for="default_sale_price">Default Sale Price (₹)</label>
                    <input 
                        type="number" 
                        id="default_sale_price" 
                        name="default_sale_price" 
                        class="form-control" 
                        placeholder="0.00" 
                        step="0.01" 
                        min="0"
                        value="<?= e((string)($formData['default_sale_price'] ?? '')) ?>"
                    >
                </div>

                <div class="form-group">
                    <label class="form-label" for="min_stock_alert">Low Stock Alert Level</label>
                    <input 
                        type="number" 
                        id="min_stock_alert" 
                        name="min_stock_alert" 
                        class="form-control" 
                        value="<?= e((string)($formData['min_stock_alert'] ?? 2)) ?>"
                        min="0"
                    >
                    <small style="color: var(--text-muted); font-size: 0.75rem;">Warns when stock falls to or below this level.</small>
                </div>
            </div>

            <!-- Initial Opening Stock (Visible only for non-serialized accessories) -->
            <div id="openingStockSection" style="background: #f8fafc; border: 1px solid var(--border-color); border-radius: 6px; padding: 16px; margin: 16px 0;">
                <div style="font-weight: 600; font-size: 0.88rem; color: var(--text-main); margin-bottom: 8px;">
                    &#128230; Initial Stock Intake (Optional)
                </div>
                <div class="grid grid-cols-2">
                    <div class="form-group" style="margin-bottom: 0;">
                        <label class="form-label" for="opening_stock_qty">Opening Stock Units</label>
                        <input 
                            type="number" 
                            id="opening_stock_qty" 
                            name="opening_stock_qty" 
                            class="form-control" 
                            placeholder="0" 
                            min="0"
                            value="<?= e((string)($formData['opening_stock_qty'] ?? 0)) ?>"
                        >
                        <small style="color: var(--text-muted); font-size: 0.75rem;">Recorded in double-entry stock ledger as OPENING_STOCK.</small>
                    </div>

                    <?php if ($canViewCost): ?>
                        <div class="form-group" style="margin-bottom: 0;">
                            <label class="form-label" for="opening_unit_cost">Purchase Cost Per Unit (₹)</label>
                            <input 
                                type="number" 
                                id="opening_unit_cost" 
                                name="opening_unit_cost" 
                                class="form-control" 
                                placeholder="0.00" 
                                step="0.01" 
                                min="0"
                                value="<?= e((string)($formData['opening_unit_cost'] ?? '0.00')) ?>"
                            >
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <div class="form-group" style="margin-bottom: 24px;">
                <label style="display: flex; align-items: center; gap: 8px; cursor: pointer;">
                    <input type="checkbox" name="is_active" value="1" <?= (!isset($formData['is_active']) || $formData['is_active'] == 1) ? 'checked' : '' ?>>
                    <span style="font-weight: 500; font-size: 0.9rem;">Product Active in Shop Catalog</span>
                </label>
            </div>

            <div style="display: flex; gap: 12px; justify-content: flex-end;">
                <a href="/products" class="btn btn-secondary">Cancel</a>
                <button type="submit" class="btn btn-primary">Save Product</button>
            </div>
        </form>
    </div>
</div>

<script>
function toggleStockSection(val) {
    const sec = document.getElementById('openingStockSection');
    if (sec) {
        sec.style.display = (val === 'SERIALIZED_PHONE') ? 'none' : 'block';
    }
}
</script>
