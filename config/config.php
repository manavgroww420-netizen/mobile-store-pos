<?php
declare(strict_types=1);

/**
 * Mobile Store Management System — POS & Inventory Platform
 * Central Application Configuration
 *
 * File: config/config.php
 */

// Define application base path constants
defined('AMH_BASE_PATH') or define('AMH_BASE_PATH', dirname(__DIR__));
defined('AMH_CONFIG_PATH') or define('AMH_CONFIG_PATH', __DIR__);
defined('AMH_SRC_PATH') or define('AMH_SRC_PATH', AMH_BASE_PATH . DIRECTORY_SEPARATOR . 'src');
defined('AMH_VIEWS_PATH') or define('AMH_VIEWS_PATH', AMH_BASE_PATH . DIRECTORY_SEPARATOR . 'views');
defined('AMH_PUBLIC_PATH') or define('AMH_PUBLIC_PATH', AMH_BASE_PATH . DIRECTORY_SEPARATOR . 'public');
defined('AMH_STORAGE_PATH') or define('AMH_STORAGE_PATH', AMH_BASE_PATH . DIRECTORY_SEPARATOR . 'storage');
defined('AMH_LOGS_PATH') or define('AMH_LOGS_PATH', AMH_STORAGE_PATH . DIRECTORY_SEPARATOR . 'logs');

// Lightweight Native .env Loader (Zero external dependencies)
$envFile = AMH_BASE_PATH . DIRECTORY_SEPARATOR . '.env';
if (file_exists($envFile)) {
    $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    if ($lines !== false) {
        foreach ($lines as $line) {
            $line = trim($line);
            if ($line === '' || str_starts_with($line, '#')) {
                continue;
            }
            if (str_contains($line, '=')) {
                [$k, $v] = explode('=', $line, 2);
                $k = trim($k);
                $v = trim($v);
                $v = trim($v, "\"'");
                if (!array_key_exists($k, $_SERVER) && !array_key_exists($k, $_ENV)) {
                    putenv("{$k}={$v}");
                    $_ENV[$k] = $v;
                    $_SERVER[$k] = $v;
                }
            }
        }
    }
}

// Timezone configuration (Configurable, defaults to Asia/Kolkata)
$timezone = getenv('APP_TIMEZONE') ?: 'Asia/Kolkata';
date_default_timezone_set($timezone);

return [
    'app' => [
        'name'            => getenv('SHOP_NAME') ?: (getenv('APP_NAME') ?: 'Mobile Hub POS'),
        'tagline'         => getenv('SHOP_TAGLINE') ?: 'A Complete Mobile & Electronics Store',
        'address'         => getenv('SHOP_ADDRESS') ?: 'Commercial Market, Main Road',
        'phone'           => getenv('SHOP_PHONE') ?: '9800000000',
        'email'           => getenv('SHOP_EMAIL') ?: 'contact@mobilestore.local',
        'currency'        => getenv('SHOP_CURRENCY') ?: 'INR',
        'currency_symbol' => getenv('SHOP_CURRENCY_SYMBOL') ?: '₹',
        'version'         => '1.0.0',
        'env'             => getenv('APP_ENV') ?: 'development',
        'debug'           => filter_var(getenv('APP_DEBUG') ?: true, FILTER_VALIDATE_BOOLEAN),
        'base_url'        => getenv('APP_URL') ?: '',
        'timezone'        => $timezone,
        'gstin'           => null, // Strict Zero-GST enforced sitewide
    ],

    'security' => [
        // 256-bit Hex key used for encrypting sensitive customer identification (Aadhaar).
        // Define AMH_ENCRYPTION_KEY or APP_KEY in your server environment or .env file.
        'encryption_key' => getenv('AMH_ENCRYPTION_KEY') ?: (getenv('APP_KEY') ?: 'c7f938e2195dfb4a92c3d1e678b54f9a0c4e8b2d6a1f3e5c7a9b8d0e2f4a6b8c'),
        'password_cost'  => 12,
        'csrf_lifetime'  => 7200, // 2 hours
    ],

    'session' => [
        'name'     => 'STORE_POS_SESSION',
        'lifetime' => 28800, // 8 hours (typical retail shift)
        'path'     => '/',
        'domain'   => '',
        'secure'   => isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on',
        'httponly' => true,
        'samesite' => 'Strict',
    ],

    'logging' => [
        'file'       => AMH_LOGS_PATH . DIRECTORY_SEPARATOR . 'app.log',
        'error_file' => AMH_LOGS_PATH . DIRECTORY_SEPARATOR . 'error.log',
        'max_files'  => 30,
    ],
];
