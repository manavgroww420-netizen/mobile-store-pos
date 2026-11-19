<?php
/**
 * Admin Password Reset View
 * File: views/users/reset_password.php
 */
?>

<div class="card" style="max-width: 550px; margin: 0 auto;">
    <div class="card-header">
        <span class="card-title">&#128273; Administrative Password Reset: <?= e($user['username']) ?></span>
        <a href="/users" class="btn btn-secondary btn-sm">&larr; Back to Users</a>
    </div>

    <div class="card-body">
        <div class="alert alert-warning" style="margin-bottom: 20px;">
            <strong>Notice:</strong> This administrative workflow will immediately replace the access key for user <strong><?= e($user['username']) ?></strong>.
        </div>

        <form method="POST" action="/users/<?= (int)$user['id'] ?>/reset-password" data-prevent-double-submit autocomplete="off">
            <?= csrf_field() ?>

            <div class="form-group">
                <label class="form-label required" for="new_password">New Password</label>
                <input 
                    type="password" 
                    id="new_password" 
                    name="new_password" 
                    class="form-control" 
                    placeholder="Minimum 8 characters" 
                    required 
                    minlength="8"
                    autofocus
                >
            </div>

            <div class="form-group">
                <label class="form-label required" for="confirm_password">Confirm New Password</label>
                <input 
                    type="password" 
                    id="confirm_password" 
                    name="confirm_password" 
                    class="form-control" 
                    placeholder="Re-type new password" 
                    required 
                    minlength="8"
                >
            </div>

            <div style="margin-top: 24px; display: flex; justify-content: flex-end; gap: 12px; border-top: 1px solid var(--border-color); padding-top: 16px;">
                <a href="/users" class="btn btn-secondary">Cancel</a>
                <button type="submit" class="btn btn-danger">Set New Password</button>
            </div>
        </form>
    </div>
</div>
