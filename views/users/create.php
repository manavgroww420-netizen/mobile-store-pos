<?php
/**
 * Create User View
 * File: views/users/create.php
 */
?>

<div class="card" style="max-width: 650px; margin: 0 auto;">
    <div class="card-header">
        <span class="card-title">&#10133; Create New User Account</span>
        <a href="/users" class="btn btn-secondary btn-sm">&larr; Back to Users</a>
    </div>

    <div class="card-body">
        <form method="POST" action="/users/create" data-prevent-double-submit autocomplete="off">
            <?= csrf_field() ?>

            <div class="grid grid-cols-2">
                <div class="form-group">
                    <label class="form-label required" for="username">Username</label>
                    <input 
                        type="text" 
                        id="username" 
                        name="username" 
                        class="form-control" 
                        placeholder="e.g. rahul_sales" 
                        value="<?= e($formData['username'] ?? '') ?>"
                        required 
                        maxlength="50"
                        autofocus
                    >
                    <span class="form-help">Lowercase letters, numbers, dots, and underscores only (3-50 chars).</span>
                </div>

                <div class="form-group">
                    <label class="form-label required" for="full_name">Full Name</label>
                    <input 
                        type="text" 
                        id="full_name" 
                        name="full_name" 
                        class="form-control" 
                        placeholder="e.g. Rahul Sen" 
                        value="<?= e($formData['full_name'] ?? '') ?>"
                        required 
                        maxlength="100"
                    >
                </div>
            </div>

            <div class="form-group">
                <label class="form-label required" for="role">User Role</label>
                <select id="role" name="role" class="form-select" required>
                    <option value="STAFF" <?= (($formData['role'] ?? 'STAFF') === 'STAFF') ? 'selected' : '' ?>>
                        STAFF (Restricted counter terminal operator — permissions configurable)
                    </option>
                    <?php if ($isOwner): ?>
                        <option value="OWNER" <?= (($formData['role'] ?? '') === 'OWNER') ? 'selected' : '' ?>>
                            OWNER (Full administrative access, all permissions automatically active)
                        </option>
                    <?php endif; ?>
                </select>
            </div>

            <div class="grid grid-cols-2">
                <div class="form-group">
                    <label class="form-label required" for="password">Initial Password</label>
                    <input 
                        type="password" 
                        id="password" 
                        name="password" 
                        class="form-control" 
                        placeholder="Minimum 8 characters" 
                        required 
                        minlength="8"
                    >
                </div>

                <div class="form-group">
                    <label class="form-label required" for="password_confirm">Confirm Password</label>
                    <input 
                        type="password" 
                        id="password_confirm" 
                        name="password_confirm" 
                        class="form-control" 
                        placeholder="Re-type password" 
                        required 
                        minlength="8"
                    >
                </div>
            </div>

            <div class="form-group" style="margin-top: 8px;">
                <label style="display: flex; align-items: center; gap: 8px; cursor: pointer; font-weight: 500;">
                    <input type="checkbox" name="is_active" value="1" <?= (isset($formData['is_active']) ? ($formData['is_active'] ? 'checked' : '') : 'checked') ?>>
                    <span>Account Active (User can log in immediately upon creation)</span>
                </label>
            </div>

            <div style="margin-top: 24px; display: flex; justify-content: flex-end; gap: 12px; border-top: 1px solid var(--border-color); padding-top: 16px;">
                <a href="/users" class="btn btn-secondary">Cancel</a>
                <button type="submit" class="btn btn-primary">Create User Account</button>
            </div>
        </form>
    </div>
</div>
