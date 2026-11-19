<?php
/**
 * User Permission Assignment View
 * File: views/users/permissions.php
 */
?>

<div class="card" style="max-width: 800px; margin: 0 auto;">
    <div class="card-header">
        <span class="card-title">&#128272; Configure Permissions: <?= e($user['username']) ?> (<?= e($user['full_name']) ?>)</span>
        <a href="/users" class="btn btn-secondary btn-sm">&larr; Back to Users</a>
    </div>

    <div class="card-body">
        <p style="color: var(--text-muted); margin-bottom: 20px;">
            Assign granular capabilities to this staff member. Unchecked capabilities are strictly blocked server-side.
        </p>

        <form method="POST" action="/users/<?= (int)$user['id'] ?>/permissions" data-prevent-double-submit>
            <?= csrf_field() ?>

            <div style="display: flex; justify-content: flex-end; gap: 8px; margin-bottom: 16px;">
                <button type="button" class="btn btn-secondary btn-sm" onclick="toggleAllCheckboxes(true);">Select All</button>
                <button type="button" class="btn btn-secondary btn-sm" onclick="toggleAllCheckboxes(false);">Clear All</button>
            </div>

            <?php foreach ($groupedPerms as $category => $perms): ?>
                <div class="card" style="border: 1px solid var(--border-color); margin-bottom: 16px;">
                    <div class="card-header" style="background: #f8fafc; padding: 10px 16px;">
                        <strong style="text-transform: uppercase; font-size: 0.8rem; color: var(--text-main); letter-spacing: 0.5px;">
                            <?= e($category) ?> PERMISSIONS
                        </strong>
                    </div>
                    <div class="card-body" style="padding: 12px 16px;">
                        <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(320px, 1fr)); gap: 12px;">
                            <?php foreach ($perms as $p): ?>
                                <?php $isChecked = in_array($p['name'], $assigned, true); ?>
                                <label style="display: flex; align-items: flex-start; gap: 10px; cursor: pointer; padding: 6px; border-radius: var(--radius-sm); border: 1px solid <?= $isChecked ? '#bfdbfe' : '#f1f5f9' ?>; background: <?= $isChecked ? '#eff6ff' : '#ffffff' ?>;">
                                    <input 
                                        type="checkbox" 
                                        name="permissions[]" 
                                        value="<?= e($p['name']) ?>" 
                                        class="perm-checkbox"
                                        <?= $isChecked ? 'checked' : '' ?>
                                        style="margin-top: 3px;"
                                    >
                                    <div>
                                        <div style="font-weight: 600; font-size: 0.85rem; color: var(--text-main);">
                                            <?= e($p['name']) ?>
                                        </div>
                                        <div style="font-size: 0.75rem; color: var(--text-muted); line-height: 1.3;">
                                            <?= e($p['description']) ?>
                                        </div>
                                    </div>
                                </label>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>

            <div style="margin-top: 24px; display: flex; justify-content: flex-end; gap: 12px; border-top: 1px solid var(--border-color); padding-top: 16px;">
                <a href="/users" class="btn btn-secondary">Cancel</a>
                <button type="submit" class="btn btn-primary">Save Permissions</button>
            </div>
        </form>
    </div>
</div>

<script>
function toggleAllCheckboxes(checked) {
    document.querySelectorAll('.perm-checkbox').forEach(cb => {
        cb.checked = checked;
        const parent = cb.closest('label');
        if (parent) {
            parent.style.background = checked ? '#eff6ff' : '#ffffff';
            parent.style.borderColor = checked ? '#bfdbfe' : '#f1f5f9';
        }
    });
}
document.querySelectorAll('.perm-checkbox').forEach(cb => {
    cb.addEventListener('change', function() {
        const parent = this.closest('label');
        if (parent) {
            parent.style.background = this.checked ? '#eff6ff' : '#ffffff';
            parent.style.borderColor = this.checked ? '#bfdbfe' : '#f1f5f9';
        }
    });
});
</script>
