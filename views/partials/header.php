<?php
/**
 * Application Topbar Header Partial
 * File: views/partials/header.php
 */
use AMH\Auth;
?>
<header class="app-topbar">
    <div class="topbar-left">
        <h1 class="page-title"><?= e($pageTitle ?? 'Internal Management') ?></h1>
    </div>
    <div class="topbar-right">
        <?php if (Auth::check()): ?>
            <div style="display: flex; align-items: center; gap: 12px;">
                <div class="user-badge">
                    <div class="user-avatar">
                        <?= strtoupper(substr(e($currentUser['full_name'] ?? 'U'), 0, 1)) ?>
                    </div>
                    <span class="user-name"><?= e($currentUser['full_name'] ?? 'User') ?></span>
                    <span class="user-role"><?= e($currentUser['role'] ?? 'STAFF') ?></span>
                </div>

                <a href="/profile/change-password" class="btn btn-secondary btn-sm" title="Change Account Password">
                    &#128273; Key
                </a>

                <form method="POST" action="/logout" style="margin: 0;" onsubmit="return confirm('Sign out of system?');">
                    <?= csrf_field() ?>
                    <button type="submit" class="btn btn-danger btn-sm" title="Sign Out">
                        Sign Out
                    </button>
                </form>
            </div>
        <?php else: ?>
            <div>
                <a href="/login" class="btn btn-primary btn-sm">Sign In</a>
            </div>
        <?php endif; ?>
    </div>
</header>
