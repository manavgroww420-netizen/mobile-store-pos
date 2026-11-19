<?php
declare(strict_types=1);

/**
 * Global Convenience Helper Functions
 *
 * File: src/Helpers.php
 */

use AMH\Security;
use AMH\Financial;
use AMH\Auth;
use AMH\Authorization;
use AMH\View;

if (!function_exists('config')) {
    /**
     * Retrieve a configuration value using dot notation (e.g. config('app.name')).
     */
    function config(string $key, mixed $default = null): mixed
    {
        static $cfg = null;
        if ($cfg === null) {
            $cfg = require AMH_CONFIG_PATH . DIRECTORY_SEPARATOR . 'config.php';
        }
        $parts = explode('.', $key);
        $curr = $cfg;
        foreach ($parts as $part) {
            if (!is_array($curr) || !array_key_exists($part, $curr)) {
                return $default;
            }
            $curr = $curr[$part];
        }
        return $curr;
    }
}

if (!function_exists('shop_name')) {
    /**
     * Get the active shop name from configuration.
     */
    function shop_name(): string
    {
        return (string)config('app.name', 'Mobile Hub POS');
    }
}

if (!function_exists('shop_tagline')) {
    /**
     * Get the active shop tagline from configuration.
     */
    function shop_tagline(): string
    {
        return (string)config('app.tagline', 'A Complete Mobile & Electronics Store');
    }
}

if (!function_exists('shop_address')) {
    /**
     * Get the active shop address from configuration.
     */
    function shop_address(): string
    {
        return (string)config('app.address', 'Commercial Market, Main Road');
    }
}

if (!function_exists('shop_phone')) {
    /**
     * Get the active shop phone from configuration.
     */
    function shop_phone(): string
    {
        return (string)config('app.phone', '9800000000');
    }
}

if (!function_exists('e')) {
    /**
     * Escape HTML output against XSS.
     */
    function e(?string $value): string
    {
        return Security::e($value);
    }
}

if (!function_exists('csrf_token')) {
    /**
     * Get the active session CSRF token.
     */
    function csrf_token(): string
    {
        return Security::getCsrfToken();
    }
}

if (!function_exists('csrf_field')) {
    /**
     * Output a hidden CSRF token input field.
     */
    function csrf_field(): string
    {
        return Security::csrfField();
    }
}

if (!function_exists('format_currency')) {
    /**
     * Format a number as Indian Currency (e.g., "₹ 25,500.00").
     */
    function format_currency(mixed $amount, bool $showSymbol = true): string
    {
        return Financial::format($amount, $showSymbol);
    }
}

if (!function_exists('redirect')) {
    /**
     * Safe internal redirect.
     */
    function redirect(string $url): void
    {
        Security::safeRedirect($url);
    }
}

if (!function_exists('has_permission')) {
    /**
     * Check if current user has a specific permission.
     */
    function has_permission(string $permission): bool
    {
        return Authorization::hasPermission($permission);
    }
}

if (!function_exists('require_permission')) {
    /**
     * Enforce required permission or abort with 403.
     */
    function require_permission(string $permission): void
    {
        Authorization::requirePermission($permission);
    }
}

if (!function_exists('is_owner')) {
    /**
     * Check if current user is the Owner.
     */
    function is_owner(): bool
    {
        return Auth::isOwner();
    }
}

if (!function_exists('current_user')) {
    /**
     * Get current user array.
     */
    function current_user(): ?array
    {
        return Auth::user();
    }
}

if (!function_exists('flash')) {
    /**
     * Flash message helper.
     */
    function flash(string $type, string $message): void
    {
        View::flash($type, $message);
    }
}

if (!function_exists('mask_aadhaar')) {
    /**
     * Mask Aadhaar display helper.
     */
    function mask_aadhaar(?string $aadhaar): string
    {
        return Security::maskAadhaar($aadhaar);
    }
}
