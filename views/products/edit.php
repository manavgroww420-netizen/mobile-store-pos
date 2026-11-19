<?php
/**
 * Edit Product View
 * File: views/products/edit.php
 */
?>

<div class="card" style="max-width: 750px; margin: 0 auto;">
    <div class="card-header">
        <div>
            <span class="card-title">&#9998; Edit Product: <?= e($product['brand']) ?> <?= e($product['model_name']) ?></span>
            <div style="font-size: 0.82rem; color: var(--text-muted); margin-top: 2px;">
                Update master catalog attributes
            </div>
        </div>
        <a href="/products/<?= (int)$product['id'] ?>" class="btn btn-secondary btn-sm">&larr; Back to Details</a>
    </div>

    <div class="card-body">
        <form method="POST" action="/products/<?= (int)$product['id'] ?>/edit" data-prevent-double-submit autocomplete="off">
            <?= csrf_field() ?>

            <div class="grid grid-cols-2">
                <div class="form-group">
                    <label class="form-label required" for="category_id">Product Category</label>
                    <select id="category_id" name="category_id" class="form-control" required autofocus>
                        <?php foreach ($categories as $cat): ?>
                            <option value="<?= (int)$cat['id'] ?>" <?= ((int)$product['category_id'] === (int)$cat['id']) ? 'selected' : '' ?>>
                                <?= e($cat['name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-group">
                    <label class="form-label required" for="item_type">Item Classification</label>
                    <select id="item_type" name="item_type" class="form-control" required>
                        <option value="ACCESSORY" <?= ($product['item_type'] === 'ACCESSORY') ? 'selected' : '' ?>>
                            Non-Serialized Accessory (Bulk Quantity)
                        </option>
                        <option value="SERIALIZED_PHONE" <?= ($product['item_type'] === 'SERIALIZED_PHONE') ? 'selected' : '' ?>>
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
                        value="<?= e($product['brand']) ?>"
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
                        value="<?= e($product['model_name']) ?>"
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
                        value="<?= e($product['sku'] ?? '') ?>"
                        maxlength="50"
                    >
                </div>

                <div class="form-group">
                    <label class="form-label" for="default_sale_price">Default Sale Price (₹)</label>
                    <input 
                        type="number" 
                        id="default_sale_price" 
                        name="default_sale_price" 
                        class="form-control" 
                        step="0.01" 
                        min="0"
                        value="<?= e((string)($product['default_sale_price'] ?? '')) ?>"
                    >
                </div>

                <div class="form-group">
                    <label class="form-label" for="min_stock_alert">Low Stock Alert Level</label>
                    <input 
                        type="number" 
                        id="min_stock_alert" 
                        name="min_stock_alert" 
                        class="form-control" 
                        value="<?= e((string)$product['min_stock_alert']) ?>"
                        min="0"
                    >
                </div>
            </div>

            <div class="form-group" style="margin-bottom: 24px;">
                <label style="display: flex; align-items: center; gap: 8px; cursor: pointer;">
                    <input type="checkbox" name="is_active" value="1" <?= ((int)$product['is_active'] === 1) ? 'checked' : '' ?>>
                    <span style="font-weight: 500; font-size: 0.9rem;">Product Active in Shop Catalog</span>
                </label>
            </div>

            <div style="display: flex; gap: 12px; justify-content: flex-end;">
                <a href="/products/<?= (int)$product['id'] ?>" class="btn btn-secondary">Cancel</a>
                <button type="submit" class="btn btn-primary">Update Product</button>
            </div>
        </form>
    </div>
</div>
