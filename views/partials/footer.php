<?php
/**
 * Application Footer Partial
 * File: views/partials/footer.php
 */
?>
<footer class="app-footer">
    <div>
        <strong><?= e($appName ?? 'Mobile Hub POS') ?></strong> &bull; 
        <span>Internal Business Management System</span> &bull; 
        <span><?= e($appAddress ?? 'Retail Counter') ?></span>
    </div>
    <div>
        <span>PHP <?= e(PHP_VERSION) ?></span> &bull; 
        <span>Server Time: <?= date('d M Y, h:i A') ?> IST</span>
    </div>
</footer>
