<?php
/**
 * Change Own Password View
 * File: views/users/change_password.php
 */
?>

<div class="card" style="max-width: 540px; margin: 0 auto;">
    <div class="card-header">
        <span class="card-title">&#128274; Change Your Account Password</span>
        <a href="/" class="btn btn-secondary btn-sm">&larr; Back to Dashboard</a>
    </div>

    <div class="card-body">
        <form method="POST" action="/profile/change-password" data-prevent-double-submit autocomplete="off">
            <?= csrf_field() ?>

            <div class="form-group">
                <label class="form-label required" for="current_password">Current Password</label>
                <input 
                    type="password" 
                    id="current_password" 
                    name="current_password" 
                    class="form-control" 
                    placeholder="Enter current password" 
                    required 
                    autofocus
                >
            </div>

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
                >
                <span class="form-help">Must be at least 8 characters long and different from current password.</span>
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
                <a href="/" class="btn btn-secondary">Cancel</a>
                <button type="submit" class="btn btn-primary">Update Password</button>
            </div>
        </form>
    </div>
</div>
