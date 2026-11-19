<?php
/**
 * Main Application Shell Layout
 * File: views/layouts/main.php
 */
use AMH\View;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="ie=edge">
    <meta name="csrf-token" content="<?= e($csrfToken ?? '') ?>">
    <title><?= e($pageTitle ?? 'Management System') ?> — <?= e($appName ?? 'Mobile Hub POS') ?></title>
    
    <!-- Core Application Styling -->
    <link rel="stylesheet" href="/assets/css/app.css">
    <link rel="stylesheet" href="/assets/css/components.css">
</head>
<body>
    <div class="app-layout">
        <!-- Reusable Navigation Sidebar -->
        <?= View::partial('sidebar', get_defined_vars()) ?>

        <div class="app-main">
            <!-- Reusable Top Navigation Bar -->
            <?= View::partial('header', get_defined_vars()) ?>

            <!-- Main Dynamic Content Area -->
            <main class="app-content">
                <!-- System Flash Notifications -->
                <?= View::partial('alerts', get_defined_vars()) ?>

                <!-- Page Body Content -->
                <?= $content ?? '' ?>
            </main>

            <!-- Reusable Application Footer -->
            <?= View::partial('footer', get_defined_vars()) ?>
        </div>
    </div>

    <!-- Client-Side Foundation Utilities -->
    <script src="/assets/js/app.js"></script>
</body>
</html>
