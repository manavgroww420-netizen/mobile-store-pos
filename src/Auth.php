<?php
declare(strict_types=1);

namespace AMH;

/**
 * Authentication & Session Management Foundation
 *
 * Handles secure session initialization, session regeneration, credential verification,
 * and user state checks.
 */
class Auth
{
    private const SESSION_KEY_USER_ID   = 'amh_auth_user_id';
    private const SESSION_KEY_USERNAME  = 'amh_auth_username';
    private const SESSION_KEY_FULL_NAME = 'amh_auth_full_name';
    private const SESSION_KEY_ROLE      = 'amh_auth_role';
    private const SESSION_KEY_LOGGED_IN = 'amh_auth_logged_in';
    private const SESSION_KEY_LOGIN_AT  = 'amh_auth_login_time';

    private static ?array $cachedUser = null;

    /**
     * Start and configure the secure PHP session.
     */
    public static function startSession(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            return;
        }

        $config = require AMH_CONFIG_PATH . DIRECTORY_SEPARATOR . 'config.php';
        $sess = $config['session'] ?? [];

        if (!headers_sent()) {
            ini_set('session.use_strict_mode', '1');
            ini_set('session.use_only_cookies', '1');
            ini_set('session.cookie_httponly', '1');

            session_name($sess['name'] ?? 'AMH_SESSION');

            session_set_cookie_params([
                'lifetime' => $sess['lifetime'] ?? 28800,
                'path'     => $sess['path'] ?? '/',
                'domain'   => $sess['domain'] ?? '',
                'secure'   => $sess['secure'] ?? false,
                'httponly' => true,
                'samesite' => $sess['samesite'] ?? 'Strict',
            ]);
        }

        if (session_status() !== PHP_SESSION_ACTIVE) {
            @session_start();
        }
    }

    /**
     * Authenticate a user by username and password against the database.
     *
     * @return array|false Returns the user record on success, or false on failure.
     */
    public static function attempt(string $username, string $password): array|false
    {
        $cleanUser = trim($username);
        if (empty($cleanUser) || empty($password)) {
            return false;
        }

        try {
            $user = Database::fetchOne(
                "SELECT id, username, password_hash, full_name, role, is_active FROM users WHERE username = :u LIMIT 1",
                [':u' => $cleanUser]
            );

            if (!$user) {
                return false;
            }

            // Reject inactive / suspended users immediately
            if ((int)$user['is_active'] !== 1) {
                return false;
            }

            // Verify password hash
            if (!Security::verifyPassword($password, $user['password_hash'])) {
                return false;
            }

            // Successful authentication -> complete login
            self::login($user);

            return $user;
        } catch (\Throwable $e) {
            error_log('Auth attempt error: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Set up user session and regenerate session ID to prevent session fixation.
     */
    public static function login(array $user): void
    {
        self::startSession();

        // Regenerate session ID if session is active
        if (session_status() === PHP_SESSION_ACTIVE) {
            @session_regenerate_id(true);
        }

        $_SESSION[self::SESSION_KEY_LOGGED_IN] = true;
        $_SESSION[self::SESSION_KEY_USER_ID]   = (int)$user['id'];
        $_SESSION[self::SESSION_KEY_USERNAME]  = (string)$user['username'];
        $_SESSION[self::SESSION_KEY_FULL_NAME] = (string)$user['full_name'];
        $_SESSION[self::SESSION_KEY_ROLE]      = (string)$user['role'];
        $_SESSION[self::SESSION_KEY_LOGIN_AT]  = time();

        // Refresh CSRF token for the authenticated session
        $_SESSION['_csrf_token'] = bin2hex(random_bytes(32));
        $_SESSION['_csrf_token_time'] = time();

        self::$cachedUser = $user;

        // Record last login in database
        try {
            Database::execute(
                "UPDATE users SET last_login_at = NOW() WHERE id = :id",
                [':id' => $user['id']]
            );
        } catch (\Throwable $e) {
            // Non-critical, do not halt login
            error_log('Failed to update last_login_at: ' . $e->getMessage());
        }
    }

    /**
     * Terminate the user session and clear credentials.
     */
    public static function logout(): void
    {
        self::startSession();

        $_SESSION = [];
        self::$cachedUser = null;

        if (!headers_sent() && ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();
            setcookie(
                session_name(),
                '',
                time() - 42000,
                $params['path'],
                $params['domain'],
                $params['secure'],
                $params['httponly']
            );
        }

        if (session_status() === PHP_SESSION_ACTIVE) {
            session_destroy();
            @session_start();
            session_regenerate_id(true);
        }
    }

    /**
     * Check if a user is currently authenticated and active.
     */
    public static function check(): bool
    {
        self::startSession();
        return !empty($_SESSION[self::SESSION_KEY_LOGGED_IN]) && !empty($_SESSION[self::SESSION_KEY_USER_ID]);
    }

    /**
     * Get the authenticated user ID.
     */
    public static function id(): ?int
    {
        self::startSession();
        return isset($_SESSION[self::SESSION_KEY_USER_ID]) ? (int)$_SESSION[self::SESSION_KEY_USER_ID] : null;
    }

    /**
     * Get the authenticated user's username.
     */
    public static function username(): ?string
    {
        self::startSession();
        return $_SESSION[self::SESSION_KEY_USERNAME] ?? null;
    }

    /**
     * Get the authenticated user's full name.
     */
    public static function fullName(): ?string
    {
        self::startSession();
        return $_SESSION[self::SESSION_KEY_FULL_NAME] ?? null;
    }

    /**
     * Get the authenticated user's role ('OWNER' or 'STAFF').
     */
    public static function role(): ?string
    {
        self::startSession();
        return $_SESSION[self::SESSION_KEY_ROLE] ?? null;
    }

    /**
     * Check if the authenticated user is the shop Owner.
     */
    public static function isOwner(): bool
    {
        return self::role() === 'OWNER';
    }

    /**
     * Check if the authenticated user is a Staff member.
     */
    public static function isStaff(): bool
    {
        return self::role() === 'STAFF';
    }

    /**
     * Get the current user record.
     */
    public static function user(): ?array
    {
        if (!self::check()) {
            return null;
        }

        if (self::$cachedUser !== null) {
            return self::$cachedUser;
        }

        $userId = self::id();
        try {
            self::$cachedUser = Database::fetchOne(
                "SELECT id, username, full_name, role, is_active, created_at FROM users WHERE id = :id",
                [':id' => $userId]
            );
            return self::$cachedUser;
        } catch (\Throwable $e) {
            return [
                'id'        => $userId,
                'username'  => self::username(),
                'full_name' => self::fullName(),
                'role'      => self::role(),
            ];
        }
    }

    /**
     * Store the intended destination URL before redirecting to login.
     */
    public static function setIntendedUrl(string $url): void
    {
        self::startSession();
        // Disallow external or malformed URLs (prevent open redirects)
        if (str_starts_with($url, '/') && !str_starts_with($url, '//')) {
            $_SESSION['auth_intended_url'] = $url;
        }
    }

    /**
     * Retrieve and clear the intended destination URL.
     */
    public static function getIntendedUrl(string $default = '/'): string
    {
        self::startSession();
        $url = $_SESSION['auth_intended_url'] ?? $default;
        unset($_SESSION['auth_intended_url']);

        if (!str_starts_with($url, '/') || str_starts_with($url, '//')) {
            return $default;
        }

        return $url;
    }

    /**
     * Guard that redirects unauthenticated requests, safely preserving the intended destination.
     */
    public static function requireLogin(string $redirectUrl = '/login'): void
    {
        if (!self::check()) {
            $uri = $_SERVER['REQUEST_URI'] ?? '/';
            if ($uri !== '/login' && $uri !== '/logout') {
                self::setIntendedUrl($uri);
            }
            Security::safeRedirect($redirectUrl);
        }
    }

    /**
     * Guard that enforces Owner role.
     */
    public static function requireOwner(): void
    {
        self::requireLogin();
        if (!self::isOwner()) {
            http_response_code(403);
            die('Access Denied: Owner privileges required for this operation.');
        }
    }
}
