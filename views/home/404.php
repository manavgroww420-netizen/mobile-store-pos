<?php
/**
 * 404 Page Not Found View
 * File: views/home/404.php
 */
?>
<div style="display: flex; align-items: center; justify-content: center; min-height: 50vh;">
    <div class="card" style="max-width: 500px; width: 100%; text-align: center; padding: 40px 24px;">
        <div style="font-size: 3rem; color: var(--warning); margin-bottom: 12px;">&#9888;</div>
        <h2 style="font-size: 1.5rem; color: var(--text-main); margin-bottom: 8px;">Page Not Found (404)</h2>
        <p style="color: var(--text-muted); margin-bottom: 20px;">
            The requested module endpoint <code><?= e($path ?? '') ?></code> does not exist or has not yet been implemented in this phase.
        </p>
        <div>
            <a href="/" class="btn btn-primary">Return to System Status</a>
        </div>
    </div>
</div>
