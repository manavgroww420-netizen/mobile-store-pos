<?php
/**
 * Authentication Layout Template
 * File: views/layouts/auth.php
 *
 * Clean, distraction-free container for login and authentication dialogs.
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
    <title><?= e($pageTitle ?? 'Login') ?> — <?= e($appName ?? 'Mobile Hub POS') ?></title>
    
    <!-- Core Application Styling -->
    <link rel="stylesheet" href="/assets/css/app.css">
    <link rel="stylesheet" href="/assets/css/components.css">
    <style>
        .auth-wrapper {
            display: flex;
            align-items: center;
            justify-content: center;
            min-height: 100vh;
            background: linear-gradient(135deg, #090d16 0%, #1e293b 100%);
            padding: 24px 16px;
        }
        .auth-container {
            width: 100%;
            max-width: 440px;
        }
        .auth-card {
            background: #ffffff;
            border-radius: var(--radius-lg);
            box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.3), 0 10px 10px -5px rgba(0, 0, 0, 0.2);
            overflow: hidden;
            border: 1px solid rgba(255, 255, 255, 0.1);
        }
        .auth-header {
            background: #0f172a;
            color: #ffffff;
            padding: 28px 24px;
            text-align: center;
            border-bottom: 3px solid var(--accent);
        }
        .auth-brand {
            font-size: 1.35rem;
            font-weight: 800;
            letter-spacing: 0.5px;
            text-transform: uppercase;
        }
        .auth-tagline {
            font-size: 0.8rem;
            color: var(--text-light);
            margin-top: 4px;
        }
        .auth-body {
            padding: 32px 28px;
        }
        .auth-footer {
            text-align: center;
            padding-top: 16px;
            font-size: 0.75rem;
            color: var(--text-light);
            opacity: 0.8;
        }
    </style>
</head>
<body>
    <div class="auth-wrapper">
        <div class="auth-container">
            <div class="auth-card">
                <div class="auth-header">
                    <div class="auth-brand">
                        <?= e($appName ?? 'Mobile Hub POS') ?>
                    </div>
                    <div class="auth-tagline"><?= e($appTagline ?? 'Complete Mobile & Electronics Store Management') ?></div>
                </div>

                <div class="auth-body">
                    <!-- Session Flash Alerts -->
                    <?= View::partial('alerts', get_defined_vars()) ?>

                    <!-- View Content -->
                    <?= $content ?? '' ?>
                </div>
            </div>

            <div class="auth-footer">
                <?= e($appAddress ?? 'Commercial Market, Main Road') ?> &bull; Secure Internal Access Only
            </div>
        </div>
    </div>

    <!-- Client-Side Utilities -->
    <script src="/assets/js/app.js"></script>
</body>
</html>
