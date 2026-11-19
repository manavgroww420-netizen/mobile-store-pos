<?php
declare(strict_types=1);

namespace AMH;

/**
 * Authorization & Server-Enforced Permission Foundation
 *
 * Implements strict, server-side permission checking against the database.
 * The Owner automatically inherits all system permissions.
 */
class Authorization
{
    private static array $permissionCache = [];

    /**
     * Check if a user possesses a specific permission.
     *
     * @param string $permission Permission name defined in the permissions catalog.
     * @param int|null $userId Optional user ID; defaults to current logged-in user.
     * @return bool True if permitted, false otherwise.
     */
    public static function hasPermission(string $permission, ?int $userId = null): bool
    {
        if ($userId === null) {
            if (!Auth::check()) {
                return false;
            }
            // The Owner has full, unrestricted access to all modules and sensitive data
            if (Auth::isOwner()) {
                return true;
            }
            $userId = Auth::id();
        } else {
            // Check if specified user is an OWNER
            try {
                $role = Database::fetchColumn(
                    "SELECT role FROM users WHERE id = :id",
                    [':id' => $userId]
                );
                if ($role === 'OWNER') {
                    return true;
                }
            } catch (\Throwable $e) {
                return false;
            }
        }

        if ($userId === null) {
            return false;
        }

        // Check memoized cache
        if (isset(self::$permissionCache[$userId])) {
            return in_array($permission, self::$permissionCache[$userId], true);
        }

        // Load permissions for user from user_permissions table
        try {
            $rows = Database::fetchAll(
                "SELECT permission_name FROM user_permissions WHERE user_id = :uid",
                [':uid' => $userId]
            );

            self::$permissionCache[$userId] = array_column($rows, 'permission_name');
            return in_array($permission, self::$permissionCache[$userId], true);
        } catch (\Throwable $e) {
            error_log('Authorization permission check error: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Enforce that the current user has the required permission, otherwise abort with 403.
     */
    public static function requirePermission(string $permission): void
    {
        Auth::requireLogin();

        if (!self::hasPermission($permission)) {
            http_response_code(403);
            header('Content-Type: text/html; charset=UTF-8');

            $isAjax = (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest')
                   || (isset($_SERVER['HTTP_ACCEPT']) && strpos($_SERVER['HTTP_ACCEPT'], 'application/json') !== false);

            if ($isAjax) {
                header('Content-Type: application/json; charset=UTF-8');
                echo json_encode([
                    'success' => false,
                    'error'   => 'Access Denied: You do not have permission (' . $permission . ') to perform this action.'
                ]);
                exit;
            }

            echo '<!DOCTYPE html><html><head><meta charset="UTF-8"><title>403 Forbidden — Access Denied</title>';
            echo '<style>body{font-family:-apple-system,sans-serif;background:#f8fafc;color:#0f172a;display:flex;align-items:center;justify-content:center;height:100vh;margin:0;}';
            echo '.box{background:#fff;border:1px solid #e2e8f0;border-radius:8px;padding:32px;max-width:480px;text-align:center;box-shadow:0 4px 6px rgba(0,0,0,0.05);}';
            echo 'h2{color:#b91c1c;margin:0 0 12px;}p{color:#64748b;margin:0 0 20px;}a{color:#2563eb;text-decoration:none;font-weight:600;}</style></head><body>';
            echo '<div class="box"><h2>Access Denied (403)</h2><p>You do not have permission (<code>' . Security::e($permission) . '</code>) to access this resource or perform this action.</p><a href="/">Return to Dashboard</a></div></body></html>';
            exit;
        }
    }

    /**
     * Retrieve all granted permissions for a user.
     */
    public static function getUserPermissions(int $userId): array
    {
        if (isset(self::$permissionCache[$userId])) {
            return self::$permissionCache[$userId];
        }

        try {
            $rows = Database::fetchAll(
                "SELECT permission_name FROM user_permissions WHERE user_id = :uid",
                [':uid' => $userId]
            );
            self::$permissionCache[$userId] = array_column($rows, 'permission_name');
            return self::$permissionCache[$userId];
        } catch (\Throwable $e) {
            return [];
        }
    }

    /**
     * Clear cached permissions (e.g. after permissions update in Prompt 2).
     */
    public static function clearCache(?int $userId = null): void
    {
        if ($userId !== null) {
            unset(self::$permissionCache[$userId]);
        } else {
            self::$permissionCache = [];
        }
    }
}
