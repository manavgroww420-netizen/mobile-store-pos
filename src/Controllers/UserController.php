<?php
declare(strict_types=1);

namespace AMH\Controllers;

use AMH\Auth;
use AMH\Authorization;
use AMH\Database;
use AMH\Security;
use AMH\View;
use AMH\AuditLogger;

/**
 * User & Permission Management Controller
 *
 * Provides administrative staff creation, profile edits, status toggles,
 * password resets, and granular permission assignments.
 */
class UserController
{
    /**
     * Display directory of system users.
     */
    public function index(): void
    {
        Authorization::requirePermission('manage_users');

        $users = Database::fetchAll("
            SELECT u.id, u.username, u.full_name, u.role, u.is_active, u.last_login_at, u.created_at,
                   (SELECT COUNT(*) FROM user_permissions up WHERE up.user_id = u.id) as permission_count
            FROM users u
            ORDER BY (u.role = 'OWNER') DESC, u.username ASC
        ");

        View::render('users/index', [
            'pageTitle' => 'User & Staff Management',
            'users'     => $users,
        ], 'main');
    }

    /**
     * Render the create user form.
     */
    public function create(): void
    {
        Authorization::requirePermission('manage_users');

        View::render('users/create', [
            'pageTitle' => 'Create New User Account',
            'isOwner'   => Auth::isOwner(),
        ], 'main');
    }

    /**
     * Store a newly created user account.
     */
    public function store(): void
    {
        Authorization::requirePermission('manage_users');

        $csrfToken = $_POST['csrf_token'] ?? null;
        if (!Security::validateCsrfToken($csrfToken)) {
            View::flash('danger', 'Security verification failed (CSRF token missing or expired). Please try again.');
            Security::safeRedirect('/users/create');
            return;
        }

        $username = strtolower(trim($_POST['username'] ?? ''));
        $fullName = trim($_POST['full_name'] ?? '');
        $role = strtoupper(trim($_POST['role'] ?? 'STAFF'));
        $password = (string)($_POST['password'] ?? '');
        $passwordConfirm = (string)($_POST['password_confirm'] ?? '');
        $isActive = isset($_POST['is_active']) ? 1 : 0;

        // Validation
        $errors = [];

        if (!preg_match('/^[a-z0-9_\.]{3,50}$/', $username)) {
            $errors[] = 'Username must be 3-50 characters long and contain only lowercase letters, numbers, dots, or underscores.';
        }

        if (strlen($fullName) < 2 || strlen($fullName) > 100) {
            $errors[] = 'Full name must be between 2 and 100 characters.';
        }

        if (!in_array($role, ['OWNER', 'STAFF'], true)) {
            $errors[] = 'Invalid user role selected.';
        }

        // Non-owners cannot create an OWNER account
        if ($role === 'OWNER' && !Auth::isOwner()) {
            $errors[] = 'Only an existing Owner can create new Owner accounts.';
        }

        if (strlen($password) < 8) {
            $errors[] = 'Password must be at least 8 characters long.';
        }

        if ($password !== $passwordConfirm) {
            $errors[] = 'Password confirmation does not match.';
        }

        // Check username uniqueness
        $existing = Database::fetchOne("SELECT id FROM users WHERE username = :u LIMIT 1", [':u' => $username]);
        if ($existing) {
            $errors[] = "The username '{$username}' is already in use.";
        }

        if (!empty($errors)) {
            View::flash('danger', implode(' ', $errors));
            View::render('users/create', [
                'pageTitle' => 'Create New User Account',
                'formData'  => [
                    'username'  => $username,
                    'full_name' => $fullName,
                    'role'      => $role,
                    'is_active' => $isActive,
                ],
                'isOwner'   => Auth::isOwner(),
            ], 'main');
            return;
        }

        // Hash password securely
        $hash = Security::hashPassword($password);

        Database::transaction(function() use ($username, $hash, $fullName, $role, $isActive) {
            Database::execute("
                INSERT INTO users (username, password_hash, full_name, role, is_active, created_at)
                VALUES (:u, :p, :fn, :r, :a, NOW())
            ", [
                ':u'  => $username,
                ':p'  => $hash,
                ':fn' => $fullName,
                ':r'  => $role,
                ':a'  => $isActive,
            ]);

            $newId = Database::lastInsertId();

            AuditLogger::log(
                'USER_CREATED',
                'users',
                (int)$newId,
                null,
                ['username' => $username, 'role' => $role, 'is_active' => $isActive],
                "Created user account {$username}"
            );
        });

        View::flash('success', "User account '{$username}' has been successfully created.");
        Security::safeRedirect('/users');
    }

    /**
     * Render the edit user form.
     */
    public function edit(array $params): void
    {
        Authorization::requirePermission('manage_users');

        $id = (int)($params['id'] ?? 0);
        $user = Database::fetchOne("SELECT id, username, full_name, role, is_active FROM users WHERE id = :id", [':id' => $id]);

        if (!$user) {
            View::flash('danger', 'User account not found.');
            Security::safeRedirect('/users');
            return;
        }

        // Non-owners cannot edit an Owner account
        if ($user['role'] === 'OWNER' && !Auth::isOwner()) {
            View::flash('danger', 'You do not have permission to edit an Owner account.');
            Security::safeRedirect('/users');
            return;
        }

        View::render('users/edit', [
            'pageTitle' => "Edit User: {$user['username']}",
            'user'      => $user,
            'isOwner'   => Auth::isOwner(),
        ], 'main');
    }

    /**
     * Process updates to user profile and role.
     */
    public function update(array $params): void
    {
        Authorization::requirePermission('manage_users');

        $id = (int)($params['id'] ?? 0);
        $user = Database::fetchOne("SELECT id, username, full_name, role, is_active FROM users WHERE id = :id", [':id' => $id]);

        if (!$user) {
            View::flash('danger', 'User account not found.');
            Security::safeRedirect('/users');
            return;
        }

        if ($user['role'] === 'OWNER' && !Auth::isOwner()) {
            View::flash('danger', 'You do not have permission to modify an Owner account.');
            Security::safeRedirect('/users');
            return;
        }

        $csrfToken = $_POST['csrf_token'] ?? null;
        if (!Security::validateCsrfToken($csrfToken)) {
            View::flash('danger', 'Security verification failed (CSRF token missing or expired). Please try again.');
            Security::safeRedirect("/users/{$id}/edit");
            return;
        }

        $fullName = trim($_POST['full_name'] ?? '');
        $role = strtoupper(trim($_POST['role'] ?? $user['role']));
        $isActive = isset($_POST['is_active']) ? 1 : 0;

        $errors = [];
        if (strlen($fullName) < 2 || strlen($fullName) > 100) {
            $errors[] = 'Full name must be between 2 and 100 characters.';
        }

        // Anti-self-lockout: Prevent demoting or deactivating the last active OWNER
        if ($user['role'] === 'OWNER' && ($role !== 'OWNER' || $isActive === 0)) {
            $activeOwnerCount = (int)Database::fetchColumn("SELECT COUNT(*) FROM users WHERE role = 'OWNER' AND is_active = 1");
            if ($activeOwnerCount <= 1) {
                $errors[] = 'Cannot demote or deactivate the last active Owner account. System requires at least one active Owner.';
            }
        }

        if (!empty($errors)) {
            View::flash('danger', implode(' ', $errors));
            Security::safeRedirect("/users/{$id}/edit");
            return;
        }

        Database::transaction(function() use ($id, $fullName, $role, $isActive, $user) {
            Database::execute("
                UPDATE users
                SET full_name = :fn, role = :r, is_active = :a, updated_at = NOW()
                WHERE id = :id
            ", [
                ':fn' => $fullName,
                ':r'  => $role,
                ':a'  => $isActive,
                ':id' => $id,
            ]);

            AuditLogger::log(
                'USER_UPDATED',
                'users',
                $id,
                ['full_name' => $user['full_name'], 'role' => $user['role'], 'is_active' => $user['is_active']],
                ['full_name' => $fullName, 'role' => $role, 'is_active' => $isActive],
                "Updated user account {$user['username']}"
            );
        });

        View::flash('success', "User '{$user['username']}' updated successfully.");
        Security::safeRedirect('/users');
    }

    /**
     * Toggle a user's active/inactive status.
     */
    public function toggleStatus(array $params): void
    {
        Authorization::requirePermission('manage_users');

        $csrfToken = $_POST['csrf_token'] ?? null;
        if (!Security::validateCsrfToken($csrfToken)) {
            View::flash('danger', 'Security verification failed (CSRF token missing or expired).');
            Security::safeRedirect('/users');
            return;
        }

        $id = (int)($params['id'] ?? 0);
        $user = Database::fetchOne("SELECT id, username, role, is_active FROM users WHERE id = :id", [':id' => $id]);

        if (!$user) {
            View::flash('danger', 'User account not found.');
            Security::safeRedirect('/users');
            return;
        }

        if ($user['role'] === 'OWNER' && !Auth::isOwner()) {
            View::flash('danger', 'You cannot alter the status of an Owner account.');
            Security::safeRedirect('/users');
            return;
        }

        $newStatus = ((int)$user['is_active'] === 1) ? 0 : 1;

        // Anti-self-lockout check
        if ($user['role'] === 'OWNER' && $newStatus === 0) {
            $activeOwnerCount = (int)Database::fetchColumn("SELECT COUNT(*) FROM users WHERE role = 'OWNER' AND is_active = 1");
            if ($activeOwnerCount <= 1) {
                View::flash('danger', 'Cannot deactivate the last active Owner account.');
                Security::safeRedirect('/users');
                return;
            }
        }

        Database::execute("UPDATE users SET is_active = :a, updated_at = NOW() WHERE id = :id", [
            ':a'  => $newStatus,
            ':id' => $id,
        ]);

        AuditLogger::log(
            'USER_STATUS_TOGGLED',
            'users',
            $id,
            ['is_active' => $user['is_active']],
            ['is_active' => $newStatus],
            "Changed status of {$user['username']} to " . ($newStatus ? 'ACTIVE' : 'DEACTIVATED')
        );

        $msg = $newStatus ? "User '{$user['username']}' has been activated." : "User '{$user['username']}' has been deactivated.";
        View::flash('success', $msg);
        Security::safeRedirect('/users');
    }

    /**
     * Render the admin password reset form.
     */
    public function showResetPassword(array $params): void
    {
        Authorization::requirePermission('manage_users');

        $id = (int)($params['id'] ?? 0);
        $user = Database::fetchOne("SELECT id, username, full_name, role FROM users WHERE id = :id", [':id' => $id]);

        if (!$user) {
            View::flash('danger', 'User account not found.');
            Security::safeRedirect('/users');
            return;
        }

        if ($user['role'] === 'OWNER' && !Auth::isOwner()) {
            View::flash('danger', 'Only an Owner can reset an Owner account password.');
            Security::safeRedirect('/users');
            return;
        }

        View::render('users/reset_password', [
            'pageTitle' => "Reset Password: {$user['username']}",
            'user'      => $user,
        ], 'main');
    }

    /**
     * Process administrative password reset.
     */
    public function resetPassword(array $params): void
    {
        Authorization::requirePermission('manage_users');

        $id = (int)($params['id'] ?? 0);
        $user = Database::fetchOne("SELECT id, username, role FROM users WHERE id = :id", [':id' => $id]);

        if (!$user) {
            View::flash('danger', 'User account not found.');
            Security::safeRedirect('/users');
            return;
        }

        if ($user['role'] === 'OWNER' && !Auth::isOwner()) {
            View::flash('danger', 'Only an Owner can reset an Owner account password.');
            Security::safeRedirect('/users');
            return;
        }

        $csrfToken = $_POST['csrf_token'] ?? null;
        if (!Security::validateCsrfToken($csrfToken)) {
            View::flash('danger', 'Security verification failed (CSRF token missing or expired).');
            Security::safeRedirect("/users/{$id}/reset-password");
            return;
        }

        $newPassword = (string)($_POST['new_password'] ?? '');
        $confirmPassword = (string)($_POST['confirm_password'] ?? '');

        if (strlen($newPassword) < 8) {
            View::flash('danger', 'New password must be at least 8 characters long.');
            Security::safeRedirect("/users/{$id}/reset-password");
            return;
        }

        if ($newPassword !== $confirmPassword) {
            View::flash('danger', 'Password confirmation does not match.');
            Security::safeRedirect("/users/{$id}/reset-password");
            return;
        }

        $hash = Security::hashPassword($newPassword);

        Database::execute("UPDATE users SET password_hash = :p, updated_at = NOW() WHERE id = :id", [
            ':p'  => $hash,
            ':id' => $id,
        ]);

        AuditLogger::log(
            'PASSWORD_RESET_ADMIN',
            'users',
            $id,
            null,
            null,
            "Administrator reset password for user {$user['username']}"
        );

        View::flash('success', "Password for '{$user['username']}' has been reset successfully.");
        Security::safeRedirect('/users');
    }

    /**
     * Render the permission assignment form for a staff member.
     */
    public function showPermissions(array $params): void
    {
        Authorization::requirePermission('manage_users');

        $id = (int)($params['id'] ?? 0);
        $user = Database::fetchOne("SELECT id, username, full_name, role FROM users WHERE id = :id", [':id' => $id]);

        if (!$user) {
            View::flash('danger', 'User account not found.');
            Security::safeRedirect('/users');
            return;
        }

        // Fetch all catalog permissions grouped by category
        $allPermissions = Database::fetchAll("SELECT name, description, category FROM permissions ORDER BY category ASC, name ASC");
        $grouped = [];
        foreach ($allPermissions as $p) {
            $grouped[$p['category']][] = $p;
        }

        // Fetch user's currently assigned permissions
        $assigned = Authorization::getUserPermissions($id);

        View::render('users/permissions', [
            'pageTitle'   => "Permissions: {$user['username']}",
            'user'        => $user,
            'groupedPerms'=> $grouped,
            'assigned'    => $assigned,
        ], 'main');
    }

    /**
     * Update granted permissions for a staff user.
     */
    public function updatePermissions(array $params): void
    {
        Authorization::requirePermission('manage_users');

        $id = (int)($params['id'] ?? 0);
        $user = Database::fetchOne("SELECT id, username, role FROM users WHERE id = :id", [':id' => $id]);

        if (!$user) {
            View::flash('danger', 'User account not found.');
            Security::safeRedirect('/users');
            return;
        }

        if ($user['role'] === 'OWNER') {
            View::flash('info', 'Owner accounts automatically hold full system permissions; explicit assignment is not required.');
            Security::safeRedirect('/users');
            return;
        }

        $csrfToken = $_POST['csrf_token'] ?? null;
        if (!Security::validateCsrfToken($csrfToken)) {
            View::flash('danger', 'Security verification failed (CSRF token missing or expired).');
            Security::safeRedirect("/users/{$id}/permissions");
            return;
        }

        // Submitted permission list
        $submitted = (array)($_POST['permissions'] ?? []);

        // Validate that every submitted permission is real in the permissions table
        $validPermissions = array_column(
            Database::fetchAll("SELECT name FROM permissions"),
            'name'
        );

        $filtered = array_intersect($submitted, $validPermissions);

        Database::transaction(function() use ($id, $filtered, $user) {
            // Remove previous permissions
            Database::execute("DELETE FROM user_permissions WHERE user_id = :id", [':id' => $id]);

            // Insert new permissions
            $grantedBy = Auth::id() ?? 1;
            foreach ($filtered as $perm) {
                Database::execute("
                    INSERT INTO user_permissions (user_id, permission_name, granted_by, created_at)
                    VALUES (:uid, :pname, :gby, NOW())
                ", [
                    ':uid'   => $id,
                    ':pname' => $perm,
                    ':gby'   => $grantedBy,
                ]);
            }

            AuditLogger::log(
                'PERMISSIONS_UPDATED',
                'users',
                $id,
                null,
                ['permissions' => $filtered],
                "Updated permissions for staff user {$user['username']}"
            );
        });

        // Clear memoized permission cache
        Authorization::clearCache($id);

        View::flash('success', "Permissions updated successfully for '{$user['username']}'.");
        Security::safeRedirect('/users');
    }

    /**
     * Render the change-own-password screen.
     */
    public function showChangePassword(): void
    {
        Auth::requireLogin();

        View::render('users/change_password', [
            'pageTitle' => 'Change Your Password',
        ], 'main');
    }

    /**
     * Process changing one's own password (requires current password).
     */
    public function changePassword(): void
    {
        Auth::requireLogin();

        $csrfToken = $_POST['csrf_token'] ?? null;
        if (!Security::validateCsrfToken($csrfToken)) {
            View::flash('danger', 'Security verification failed (CSRF token missing or expired).');
            Security::safeRedirect('/profile/change-password');
            return;
        }

        $userId = Auth::id();
        $currentPassword = (string)($_POST['current_password'] ?? '');
        $newPassword = (string)($_POST['new_password'] ?? '');
        $confirmPassword = (string)($_POST['confirm_password'] ?? '');

        // Fetch stored hash
        $storedHash = Database::fetchColumn("SELECT password_hash FROM users WHERE id = :id", [':id' => $userId]);

        if (!Security::verifyPassword($currentPassword, (string)$storedHash)) {
            View::flash('danger', 'Your current password was entered incorrectly.');
            Security::safeRedirect('/profile/change-password');
            return;
        }

        if (strlen($newPassword) < 8) {
            View::flash('danger', 'New password must be at least 8 characters long.');
            Security::safeRedirect('/profile/change-password');
            return;
        }

        if ($newPassword !== $confirmPassword) {
            View::flash('danger', 'New password confirmation does not match.');
            Security::safeRedirect('/profile/change-password');
            return;
        }

        if ($newPassword === $currentPassword) {
            View::flash('warning', 'New password must be different from your current password.');
            Security::safeRedirect('/profile/change-password');
            return;
        }

        $newHash = Security::hashPassword($newPassword);

        Database::execute("UPDATE users SET password_hash = :p, updated_at = NOW() WHERE id = :id", [
            ':p'  => $newHash,
            ':id' => $userId,
        ]);

        AuditLogger::log(
            'PASSWORD_CHANGED',
            'users',
            $userId,
            null,
            null,
            'User successfully changed their own password'
        );

        View::flash('success', 'Your password has been changed successfully.');
        Security::safeRedirect('/');
    }
}
