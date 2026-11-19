<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($errorTitle ?? 'System Error', ENT_QUOTES, 'UTF-8') ?> — <?= htmlspecialchars($appName ?? 'Mobile Hub POS', ENT_QUOTES, 'UTF-8') ?></title>
    <link rel="stylesheet" href="/assets/css/app.css">
    <link rel="stylesheet" href="/assets/css/components.css">
    <style>
        .error-container {
            display: flex;
            align-items: center;
            justify-content: center;
            min-height: 100vh;
            padding: 20px;
            background: #f8fafc;
        }
        .error-card {
            background: #ffffff;
            border: 1px solid var(--border-color);
            border-radius: var(--radius-md);
            padding: 32px;
            max-width: 600px;
            width: 100%;
            box-shadow: var(--shadow-md);
        }
        .error-icon {
            font-size: 2.5rem;
            color: var(--danger);
            margin-bottom: 12px;
        }
        .error-code {
            font-family: var(--font-mono);
            font-size: 0.85rem;
            background: #f1f5f9;
            padding: 4px 8px;
            border-radius: 4px;
            color: var(--text-muted);
            display: inline-block;
            margin-top: 12px;
        }
        .debug-box {
            margin-top: 20px;
            padding: 16px;
            background: #fef2f2;
            border: 1px solid #fecaca;
            border-radius: var(--radius-sm);
            font-family: var(--font-mono);
            font-size: 0.78rem;
            color: #991b1b;
            overflow-x: auto;
        }
    </style>
</head>
<body>
    <div class="error-container">
        <div class="error-card">
            <div class="error-icon">&#9888;</div>
            <h2 style="color: var(--danger); margin-bottom: 8px;"><?= htmlspecialchars($errorTitle ?? 'System Incident', ENT_QUOTES, 'UTF-8') ?></h2>
            <p style="color: var(--text-muted); line-height: 1.5;"><?= htmlspecialchars($errorMessage ?? 'An unexpected system error occurred.', ENT_QUOTES, 'UTF-8') ?></p>
            
            <div class="error-code">Reference ID: <strong><?= htmlspecialchars($referenceId ?? 'ERR-UNKNOWN', ENT_QUOTES, 'UTF-8') ?></strong></div>

            <?php if (!empty($debugDetails)): ?>
                <div class="debug-box">
                    <strong>Developer Diagnostics (Development Mode):</strong><br>
                    <strong>Exception:</strong> <?= htmlspecialchars($debugDetails['exception'] ?? '', ENT_QUOTES, 'UTF-8') ?><br>
                    <strong>File:</strong> <?= htmlspecialchars($debugDetails['file'] ?? '', ENT_QUOTES, 'UTF-8') ?> (Line <?= (int)($debugDetails['line'] ?? 0) ?>)<br>
                    <pre style="margin-top: 8px; white-space: pre-wrap;"><?= htmlspecialchars($debugDetails['trace'] ?? '', ENT_QUOTES, 'UTF-8') ?></pre>
                </div>
            <?php endif; ?>

            <div style="margin-top: 24px;">
                <a href="/" class="btn btn-primary">Return to System Dashboard</a>
            </div>
        </div>
    </div>
</body>
</html>
