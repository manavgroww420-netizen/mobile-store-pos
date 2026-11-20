<?php
declare(strict_types=1);

namespace AMH;

/**
 * Security & Data Sanitization Foundation
 *
 * Provides output escaping, CSRF protection, password hashing,
 * IMEI Luhn validation, and AES-256-GCM authenticated encryption for sensitive IDs.
 */
class Security
{
    /**
     * Escape HTML output against Cross-Site Scripting (XSS).
     */
    public static function e(?string $value): string
    {
        return htmlspecialchars((string)($value ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }

    /**
     * Get or create a cryptographically secure CSRF token stored in the user's session.
     */
    public static function getCsrfToken(): string
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            @session_start();
        }

        if (empty($_SESSION['_csrf_token']) || empty($_SESSION['_csrf_token_time']) || (time() - $_SESSION['_csrf_token_time'] > 7200)) {
            $_SESSION['_csrf_token'] = bin2hex(random_bytes(32));
            $_SESSION['_csrf_token_time'] = time();
        }

        return $_SESSION['_csrf_token'];
    }

    /**
     * Validate a submitted CSRF token using timing-safe comparison.
     */
    public static function validateCsrfToken(?string $token): bool
    {
        if (empty($token) || empty($_SESSION['_csrf_token'])) {
            return false;
        }

        return hash_equals($_SESSION['_csrf_token'], $token);
    }

    /**
     * Output a hidden HTML input field containing the current CSRF token.
     */
    public static function csrfField(): string
    {
        $token = self::getCsrfToken();
        return '<input type="hidden" name="csrf_token" value="' . self::e($token) . '">';
    }

    /**
     * Hash a password securely using the configured cost factor.
     */
    public static function hashPassword(string $password): string
    {
        return password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);
    }

    /**
     * Verify a plaintext password against a stored hash.
     */
    public static function verifyPassword(string $password, string $hash): bool
    {
        return password_verify($password, $hash);
    }

    /**
     * Validate that an IMEI is exactly 15 numeric digits.
     * Optionally validates the 15th Luhn check digit.
     */
    public static function validateImei(string $imei, bool $checkLuhn = false): bool
    {
        $clean = trim($imei);
        if (!preg_match('/^[0-9]{15}$/', $clean)) {
            return false;
        }

        if ($checkLuhn) {
            return self::validateLuhn($clean);
        }

        return true;
    }

    /**
     * Validate a number string using the standard Luhn algorithm.
     */
    public static function validateLuhn(string $number): bool
    {
        $sum = 0;
        $numDigits = strlen($number);
        $parity = $numDigits % 2;

        for ($i = 0; $i < $numDigits; $i++) {
            $digit = (int)$number[$i];
            if ($i % 2 === $parity) {
                $digit *= 2;
                if ($digit > 9) {
                    $digit -= 9;
                }
            }
            $sum += $digit;
        }

        return ($sum % 10 === 0);
    }

    /**
     * Validate phone number (10 to 15 digits).
     */
    public static function validatePhone(string $phone): bool
    {
        $clean = preg_replace('/[^0-9]/', '', $phone);
        return strlen($clean) >= 10 && strlen($clean) <= 15;
    }

    /**
     * Clean and sanitize general string input.
     */
    public static function sanitizeString(?string $str): string
    {
        return trim(strip_tags((string)($str ?? '')));
    }

    /**
     * Safely parse an integer from input with default fallback.
     */
    public static function sanitizeInt(mixed $val, int $default = 0): int
    {
        if (is_numeric($val)) {
            return (int)$val;
        }
        return $default;
    }

    /**
     * Mask an Aadhaar number to display only the last 4 digits (e.g., XXXXXXXX1234).
     */
    public static function maskAadhaar(?string $aadhaar): string
    {
        if (empty($aadhaar)) {
            return '';
        }
        $digits = preg_replace('/[^0-9]/', '', $aadhaar);
        if (strlen($digits) < 4) {
            return 'XXXXXXXX' . $digits;
        }
        return 'XXXXXXXX' . substr($digits, -4);
    }

    /**
     * Extract the last 4 digits of an Aadhaar number.
     */
    public static function extractAadhaarLast4(?string $aadhaar): ?string
    {
        if (empty($aadhaar)) {
            return null;
        }
        $digits = preg_replace('/[^0-9]/', '', $aadhaar);
        return strlen($digits) >= 4 ? substr($digits, -4) : null;
    }

    /**
     * Hash an Aadhaar number for non-reversible duplicate detection.
     */
    public static function hashAadhaar(string $aadhaar, string $salt = 'AMH_AADHAAR_SALT'): string
    {
        $clean = preg_replace('/[^0-9]/', '', $aadhaar);
        return hash('sha256', $salt . ':' . $clean);
    }

    /**
     * Authenticated encryption for sensitive Aadhaar numbers using AES-256-GCM.
     */
    public static function encryptAadhaar(string $aadhaar, ?string $keyHex = null): string
    {
        $config = require AMH_CONFIG_PATH . DIRECTORY_SEPARATOR . 'config.php';
        $keyHex = $keyHex ?: $config['security']['encryption_key'];
        $key = hex2bin($keyHex);

        $iv = random_bytes(12); // 96-bit IV recommended for GCM
        $tag = '';
        $ciphertext = openssl_encrypt(
            trim($aadhaar),
            'aes-256-gcm',
            $key,
            OPENSSL_RAW_DATA,
            $iv,
            $tag
        );

        // Store as base64(iv . tag . ciphertext)
        return base64_encode($iv . $tag . $ciphertext);
    }

    /**
     * Decrypt an Aadhaar number encrypted with AES-256-GCM.
     */
    public static function decryptAadhaar(string $payload, ?string $keyHex = null): ?string
    {
        try {
            $data = base64_decode($payload, true);
            if ($data === false || strlen($data) < 28) { // 12 (IV) + 16 (Tag)
                return null;
            }

            $config = require AMH_CONFIG_PATH . DIRECTORY_SEPARATOR . 'config.php';
            $keyHex = $keyHex ?: $config['security']['encryption_key'];
            $key = hex2bin($keyHex);

            $iv = substr($data, 0, 12);
            $tag = substr($data, 12, 16);
            $ciphertext = substr($data, 28);

            $plaintext = openssl_decrypt(
                $ciphertext,
                'aes-256-gcm',
                $key,
                OPENSSL_RAW_DATA,
                $iv,
                $tag
            );

            return $plaintext !== false ? $plaintext : null;
        } catch (\Throwable $e) {
            return null;
        }
    }

    /**
     * Safe internal URL redirection preventing open redirect vulnerabilities.
     */
    public static function safeRedirect(string $url): void
    {
        // Disallow external URLs (must start with '/' or relative path, no protocol)
        if (preg_match('#^(https?:)?//#i', $url)) {
            $url = '/';
        }

        if (!headers_sent()) {
            header('Location: ' . $url);
            exit;
        }

        echo '<script>window.location.href=' . json_encode($url) . ';</script>';
        echo '<noscript><meta http-equiv="refresh" content="0;url=' . self::e($url) . '"></noscript>';
        exit;
    }

    /**
     * Emit hardened HTTP security headers to protect against XSS, clickjacking, MIME-sniffing, and referrer leakage.
     */
    public static function sendSecurityHeaders(): void
    {
        if (!headers_sent()) {
            header('X-Content-Type-Options: nosniff');
            header('X-Frame-Options: SAMEORIGIN');
            header('X-XSS-Protection: 1; mode=block');
            header('Referrer-Policy: strict-origin-when-cross-origin');
        }
    }
}
