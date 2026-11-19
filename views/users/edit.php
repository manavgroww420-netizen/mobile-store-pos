<?php
/**
 * Edit User View
 * File: views/users/edit.php
 */
?>

<div class="card" style="max-width: 650px; margin: 0 auto;">
    <div class="card-header">
        <span class="card-title">&#9998; Edit User Account: <?= e($user['username']) ?></span>
        <a href="/users" class="btn btn-secondary btn-sm">&larr; Back to Users</a>
    </div>

    <div class="card-body">
        <form method="POST" action="/users/<?= (int)$user['id'] ?>/edit" data-prevent-double-submit autocomplete="off">
            <?= csrf_field() ?>

            <div class="form-group">
                <label class="form-label" for="username">Username (Immutable)</label>
                <input 
                    type="text" 
                    id="username" 
                    class="form-control" 
                    value="<?= e($user['username']) ?>" 
                    disabled 
                    style="background: #f1f5f9; cursor: not-allowed;"
                >
                <span class="form-help">System usernames are fixed hardware and audit trail references.</span>
            </div>

            <div class="form-group">
                <label class="form-label required" for="full_name">Full Name</label>
                <input 
                    type="text" 
                    id="full_name" 
                    name="full_name" 
                    class="form-control" 
                    value="<?= e($user['full_name']) ?>" 
                    required 
                    maxlength="100"
                >
            </div>

            <div class="form-group">
                <label class="form-label required" for="role">User Role</label>
                <?php if ($isOwner): ?>
                    <select id="role" name="role" class="form-select" required>
                        <option value="STAFF" <?= ($user['role'] === 'STAFF') ? 'selected' : '' ?>>
                            STAFF (Restricted counter terminal operator)
                        </option>
                        <option value="OWNER" <?= ($user['role'] === 'OWNER') ? 'selected' : '' ?>>
                            OWNER (Full administrative access)
                        </option>
                    </select>
                <?php else: ?>
                    <input type="text" class="form-control" value="<?= e($user['role']) ?>" disabled style="background: #f1f5f9;">
                    <input type="hidden" name="role" value="<?= e($user['role']) ?>">
                <?php endif; ?>
            </div>

            <div class="form-group" style="margin-top: 16px;">
                <label style="display: flex; align-items: center; gap: 8px; cursor: pointer; font-weight: 500;">
                    <input type="checkbox" name="is_active" value="1" <?= ((int)$user['is_active'] === 1) ? 'checked' : '' ?>>
                    <span>Account Active (Check to enable login; uncheck to immediately suspend access)</span>
                </label>
            </div>

            <div style="margin-top: 24px; display: flex; justify-content: flex-end; gap: 12px; border-top: 1px solid var(--border-color); padding-top: 16px;">
                <a href="/users" class="btn btn-secondary">Cancel</a>
                <button type="submit" class="btn btn-primary">Save Changes</button>
            </div>
        </form>
    </div>
</div>
