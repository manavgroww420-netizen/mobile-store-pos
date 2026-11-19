<?php
/**
 * Category Directory View
 * File: views/categories/index.php
 */
?>

<div class="card">
    <div class="card-header">
        <div>
            <span class="card-title">&#128194; Product Categories Master</span>
            <div style="font-size: 0.82rem; color: var(--text-muted); margin-top: 2px;">
                Manage dynamic product and accessory classifications
            </div>
        </div>
        <div>
            <?php if ($canManage): ?>
                <a href="/categories/create" class="btn btn-primary btn-sm">&#10133; New Category</a>
            <?php endif; ?>
        </div>
    </div>

    <div class="card-body" style="padding: 0;">
        <div class="table-responsive">
            <table class="data-table">
                <thead>
                    <tr>
                        <th style="width: 50px;">#</th>
                        <th>Category Name</th>
                        <th>URL Slug</th>
                        <th>Description</th>
                        <th style="text-align: center;">Assigned Products</th>
                        <th style="text-align: center;">Status</th>
                        <?php if ($canManage): ?>
                            <th style="text-align: right; width: 180px;">Actions</th>
                        <?php endif; ?>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($categories)): ?>
                        <tr>
                            <td colspan="<?= $canManage ? '7' : '6' ?>" style="text-align: center; color: var(--text-muted); padding: 32px;">
                                No product categories configured yet.
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($categories as $index => $c): ?>
                            <tr>
                                <td style="color: var(--text-muted); font-size: 0.85rem;"><?= $index + 1 ?></td>
                                <td>
                                    <strong style="color: var(--text-main); font-size: 0.92rem;"><?= e($c['name']) ?></strong>
                                </td>
                                <td>
                                    <code style="font-size: 0.8rem; background: #f1f5f9; padding: 2px 6px; border-radius: 4px;"><?= e($c['slug']) ?></code>
                                </td>
                                <td style="color: var(--text-muted); font-size: 0.85rem; max-width: 280px;">
                                    <?= e($c['description'] ?? '—') ?>
                                </td>
                                <td style="text-align: center;">
                                    <a href="/products?category_id=<?= (int)$c['id'] ?>" class="badge badge-info" title="View catalog products in this category">
                                        <?= (int)$c['product_count'] ?> Products
                                    </a>
                                </td>
                                <td style="text-align: center;">
                                    <?php if ((int)$c['is_active'] === 1): ?>
                                        <span class="badge badge-success">Active</span>
                                    <?php else: ?>
                                        <span class="badge badge-danger">Inactive</span>
                                    <?php endif; ?>
                                </td>
                                <?php if ($canManage): ?>
                                    <td style="text-align: right;">
                                        <div style="display: inline-flex; gap: 6px; align-items: center;">
                                            <a href="/categories/<?= (int)$c['id'] ?>/edit" class="btn btn-secondary btn-sm" title="Edit Category Details">
                                                Edit
                                            </a>

                                            <form method="POST" action="/categories/<?= (int)$c['id'] ?>/toggle-status" style="display: inline;" onsubmit="return confirm('Toggle active status for <?= e(addslashes($c['name'])) ?>?');">
                                                <?= csrf_field() ?>
                                                <?php if ((int)$c['is_active'] === 1): ?>
                                                    <button type="submit" class="btn btn-danger btn-sm" title="Deactivate Category">Disable</button>
                                                <?php else: ?>
                                                    <button type="submit" class="btn btn-success btn-sm" title="Activate Category">Enable</button>
                                                <?php endif; ?>
                                            </form>

                                            <?php if ((int)$c['product_count'] === 0): ?>
                                                <form method="POST" action="/categories/<?= (int)$c['id'] ?>/delete" style="display: inline;" onsubmit="return confirm('Delete empty category <?= e(addslashes($c['name'])) ?>? This cannot be undone.');">
                                                    <?= csrf_field() ?>
                                                    <button type="submit" class="btn btn-secondary btn-sm" style="color: var(--danger);" title="Delete Empty Category">&#128465;</button>
                                                </form>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                <?php endif; ?>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
