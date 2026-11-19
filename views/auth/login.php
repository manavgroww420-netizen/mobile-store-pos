<?php
/**
 * User Login View
 * File: views/auth/login.php
 */
?>

<div style="margin-bottom: 24px; text-align: center;">
    <h2 style="font-size: 1.25rem; font-weight: 700; color: var(--text-main); margin-bottom: 6px;">Sign In to Counter</h2>
    <p style="color: var(--text-muted); font-size: 0.85rem; margin: 0;">Enter your staff or owner credentials to continue</p>
</div>

<form method="POST" action="/login" data-prevent-double-submit autocomplete="off">
    <?= csrf_field() ?>

    <div class="form-group">
        <label class="form-label required" for="username">Username</label>
        <input 
            type="text" 
            id="username" 
            name="username" 
            class="form-control" 
            placeholder="e.g. admin or staff username" 
            value="<?= e($oldUsername ?? '') ?>"
            required 
            autofocus
            maxlength="50"
        >
    </div>

    <div class="form-group" style="margin-bottom: 24px;">
        <label class="form-label required" for="password">Password</label>
        <input 
            type="password" 
            id="password" 
            name="password" 
            class="form-control" 
            placeholder="••••••••••••" 
            required
            maxlength="100"
        >
    </div>

    <button type="submit" class="btn btn-primary" style="width: 100%; padding: 12px; font-size: 0.95rem;">
        Authenticate &amp; Open Terminal
    </button>
</form>
