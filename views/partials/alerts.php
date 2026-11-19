<?php
/**
 * Flash Messages Partial
 * File: views/partials/alerts.php
 */
if (!empty($flashes)): ?>
    <div class="alerts-container">
        <?php foreach ($flashes as $flash): ?>
            <div class="alert alert-<?= htmlspecialchars($flash['type'], ENT_QUOTES, 'UTF-8') ?>">
                <span><?= htmlspecialchars($flash['message'], ENT_QUOTES, 'UTF-8') ?></span>
                <button type="button" class="alert-close" aria-label="Close">&times;</button>
            </div>
        <?php endforeach; ?>
    </div>
<?php endif; ?>
