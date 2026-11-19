<?php
/**
 * Edit Category View
 * File: views/categories/edit.php
 */
?>

<div class="card" style="max-width: 600px; margin: 0 auto;">
    <div class="card-header">
        <span class="card-title">&#9998; Edit Category: <?= e($category['name']) ?></span>
        <a href="/categories" class="btn btn-secondary btn-sm">&larr; Back to Categories</a>
    </div>

    <div class="card-body">
        <form method="POST" action="/categories/<?= (int)$category['id'] ?>/edit" data-prevent-double-submit autocomplete="off">
            <?= csrf_field() ?>

            <div class="form-group">
                <label class="form-label required" for="name">Category Name</label>
                <input 
                    type="text" 
                    id="name" 
                    name="name" 
                    class="form-control" 
                    value="<?= e($category['name']) ?>"
                    required 
                    maxlength="100"
                    autofocus
                >
            </div>

            <div class="form-group">
                <label class="form-label required" for="slug">URL Slug</label>
                <input 
                    type="text" 
                    id="slug" 
                    name="slug" 
                    class="form-control" 
                    value="<?= e($category['slug']) ?>"
                    required
                    maxlength="100"
                >
            </div>

            <div class="form-group">
                <label class="form-label" for="description">Description</label>
                <textarea 
                    id="description" 
                    name="description" 
                    class="form-control" 
                    rows="3" 
                    maxlength="255"
                ><?= e($category['description'] ?? '') ?></textarea>
            </div>

            <div class="form-group" style="margin-bottom: 24px;">
                <label style="display: flex; align-items: center; gap: 8px; cursor: pointer;">
                    <input type="checkbox" name="is_active" value="1" <?= ((int)$category['is_active'] === 1) ? 'checked' : '' ?>>
                    <span style="font-weight: 500; font-size: 0.9rem;">Category Active for Products and Inventory</span>
                </label>
            </div>

            <div style="display: flex; gap: 12px; justify-content: flex-end;">
                <a href="/categories" class="btn btn-secondary">Cancel</a>
                <button type="submit" class="btn btn-primary">Update Category</button>
            </div>
        </form>
    </div>
</div>
