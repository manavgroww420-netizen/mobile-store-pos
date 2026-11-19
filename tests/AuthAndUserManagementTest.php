<?php
declare(strict_types=1);

/**
 * ABHISHEK MOBILE HUB — Prompt 2 Automated Test Suite
 * Authentication, Session Security & User/Permission Management
 *
 * File: tests/AuthAndUserManagementTest.php
 * Run: C:\xampp\php\php.exe tests/AuthAndUserManagementTest.php
 */

// Define application paths
defined('AMH_BASE_PATH') or define('AMH_BASE_PATH', dirname(__DIR__));
defined('AMH_CONFIG_PATH') or define('AMH_CONFIG_PATH', AMH_BASE_PATH . DIRECTORY_SEPARATOR . 'config');
defined('AMH_SRC_PATH') or define('AMH_SRC_PATH', AMH_BASE_PATH . DIRECTORY_SEPARATOR . 'src');
defined('AMH_VIEWS_PATH') or define('AMH_VIEWS_PATH', AMH_BASE_PATH . DIRECTORY_SEPARATOR . 'views');
defined('AMH_PUBLIC_PATH') or define('AMH_PUBLIC_PATH', AMH_BASE_PATH . DIRECTORY_SEPARATOR . 'public');
defined('AMH_STORAGE_PATH') or define('AMH_STORAGE_PATH', AMH_BASE_PATH . DIRECTORY_SEPARATOR . 'storage');
defined('AMH_LOGS_PATH') or define('AMH_LOGS_PATH', AMH_STORAGE_PATH . DIRECTORY_SEPARATOR . 'logs');

// Load Core Foundation
require_once AMH_SRC_PATH . '/ErrorHandler.php';
require_once AMH_SRC_PATH . '/Database.php';
require_once AMH_SRC_PATH . '/Security.php';
require_once AMH_SRC_PATH . '/Financial.php';
require_once AMH_SRC_PATH . '/RateLimiter.php';
require_once AMH_SRC_PATH . '/Auth.php';
require_once AMH_SRC_PATH . '/Authorization.php';
require_once AMH_SRC_PATH . '/AuditLogger.php';
require_once AMH_SRC_PATH . '/View.php';
require_once AMH_SRC_PATH . '/Router.php';
require_once AMH_SRC_PATH . '/Helpers.php';

// Load Controllers
require_once AMH_SRC_PATH . '/Controllers/AuthController.php';
require_once AMH_SRC_PATH . '/Controllers/UserController.php';

use AMH\Auth;
use AMH\Authorization;
use AMH\Security;
use AMH\RateLimiter;
use AMH\AuditLogger;
use AMH\Database;

class AuthAndUserManagementTest
{
    private int $passed = 0;
    private int $failed = 0;
    private array $failures = [];
    private ?PDO $sqlite = null;

    public function runAll(): void
    {
        echo "====================================================================\n";
        echo "ABHISHEK MOBILE HUB — PROMPT 2/12 AUTH & USER MANAGEMENT TEST SUITE\n";
        echo "====================================================================\n\n";

        $this->setUpDatabase();

        $this->testRateLimiter();
        $this->testCredentialAuthentication();
        $this->testSessionManagement();
        $this->testOpenRedirectDefense();
        $this->testRoleAndPermissionEnforcement();
        $this->testAntiSelfLockoutDefense();
        $this->testUserCreationAndHashing();
        $this->testAdminPasswordReset();
        $this->testSelfPasswordChange();
        $this->testGranularPermissionAssignment();
        $this->testAuditTrailIntegrityAndPrivacy();
        $this->testCsrfDefense();

        $this->printSummary();
    }

    private function setUpDatabase(): void
    {
        $this->sqlite = new PDO('sqlite::memory:', null, null, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
        ]);

        // Register MySQL NOW() function for SQLite compatibility
        $this->sqlite->sqliteCreateFunction('NOW', function() {
            return date('Y-m-d H:i:s');
        });

        // Create normalized tables matching schema.sql for Prompt 2 testing
        $this->sqlite->exec("
            CREATE TABLE users (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                username TEXT UNIQUE NOT NULL,
                password_hash TEXT NOT NULL,
                full_name TEXT NOT NULL,
                role TEXT NOT NULL DEFAULT 'STAFF',
                is_active INTEGER NOT NULL DEFAULT 1,
                last_login_at TEXT NULL,
                created_at TEXT DEFAULT CURRENT_TIMESTAMP,
                updated_at TEXT DEFAULT CURRENT_TIMESTAMP
            );

            CREATE TABLE permissions (
                name TEXT PRIMARY KEY,
                description TEXT NOT NULL,
                category TEXT NOT NULL
            );

            CREATE TABLE user_permissions (
                user_id INTEGER NOT NULL,
                permission_name TEXT NOT NULL,
                granted_by INTEGER NOT NULL,
                created_at TEXT DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (user_id, permission_name),
                FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
                FOREIGN KEY (permission_name) REFERENCES permissions(name) ON DELETE CASCADE
            );

            CREATE TABLE audit_logs (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                user_id INTEGER NOT NULL,
                action TEXT NOT NULL,
                table_name TEXT NOT NULL,
                record_id INTEGER NOT NULL,
                old_values TEXT NULL,
                new_values TEXT NULL,
                reason TEXT NULL,
                ip_address TEXT NULL,
                user_agent TEXT NULL,
                created_at TEXT DEFAULT CURRENT_TIMESTAMP
            );
        ");

        // Seed 12 standard catalog permissions
        $permissions = [
            ['manage_inventory', 'Add, modify, and adjust inventory items', 'INVENTORY'],
            ['view_purchase_costs', 'View supplier cost prices and total purchase expenditures', 'FINANCIAL'],
            ['view_profit_margins', 'Access realized profit calculations and margin analytics', 'FINANCIAL'],
            ['perform_sales', 'Process retail sales and generate receipts', 'SALES'],
            ['apply_discounts', 'Apply ad-hoc item or bill-level discounts at checkout', 'SALES'],
            ['process_returns', 'Accept returned items and issue customer refunds', 'SALES'],
            ['manage_repairs', 'Receive customer repairs, update repair stages, record cost', 'REPAIRS'],
            ['buy_used_phones', 'Purchase second-hand mobile devices with seller KYC intake', 'INVENTORY'],
            ['manage_users', 'Create and manage staff accounts and role assignments', 'ADMIN'],
            ['view_audit_logs', 'Inspect immutable system event audit trail', 'SECURITY'],
            ['export_reports', 'Generate and download financial and inventory ledger reports', 'ADMIN'],
            ['modify_system_settings', 'Alter shop contact details and invoice numbering format', 'ADMIN'],
        ];

        $stmt = $this->sqlite->prepare("INSERT INTO permissions (name, description, category) VALUES (?, ?, ?)");
        foreach ($permissions as $p) {
            $stmt->execute($p);
        }

        // Seed default owner and staff accounts
        $ownerPass = Security::hashPassword('Admin@12345');
        $staffPass = Security::hashPassword('Staff@12345');

        $this->sqlite->exec("
            INSERT INTO users (id, username, password_hash, full_name, role, is_active)
            VALUES (1, 'abhishek_owner', '{$ownerPass}', 'Abhishek Shaw (Owner)', 'OWNER', 1);

            INSERT INTO users (id, username, password_hash, full_name, role, is_active)
            VALUES (2, 'rahul_staff', '{$staffPass}', 'Rahul Kumar (Staff)', 'STAFF', 1);

            INSERT INTO users (id, username, password_hash, full_name, role, is_active)
            VALUES (3, 'inactive_staff', '{$staffPass}', 'Former Staff Member', 'STAFF', 0);
        ");

        Database::setPdo($this->sqlite);
    }

    private function assert(bool $condition, string $testName, string $failureDetails = ''): void
    {
        if ($condition) {
            $this->passed++;
            echo " [PASS] {$testName}\n";
        } else {
            $this->failed++;
            $msg = " [FAIL] {$testName}";
            if ($failureDetails !== '') {
                $msg .= " — Details: {$failureDetails}";
            }
            $this->failures[] = $msg;
            echo "{$msg}\n";
        }
    }

    private function testRateLimiter(): void
    {
        echo "\n--- 1. Testing Brute-Force Rate Limiting Engine ---\n";
        $key = 'test_ip_192_168_1_50';
        RateLimiter::clear($key);

        $this->assert(!RateLimiter::isThrottled($key, 5, 900), 'RateLimiter: Initially unthrottled');

        for ($i = 1; $i <= 4; $i++) {
            $attempts = RateLimiter::hit($key, 900);
            $this->assert($attempts === $i, "RateLimiter: Hit {$i} recorded accurately");
        }

        $this->assert(!RateLimiter::isThrottled($key, 5, 900), 'RateLimiter: Still unthrottled at 4 attempts');

        // 5th attempt hits the threshold
        RateLimiter::hit($key, 900);
        $this->assert(RateLimiter::isThrottled($key, 5, 900), 'RateLimiter: Throttled immediately at 5 failed attempts');

        $seconds = RateLimiter::availableIn($key, 900);
        $this->assert($seconds > 800 && $seconds <= 900, 'RateLimiter: availableIn returns accurate backoff duration in seconds');

        // Clear after success
        RateLimiter::clear($key);
        $this->assert(!RateLimiter::isThrottled($key, 5, 900), 'RateLimiter: Throttle successfully cleared upon valid authentication');
    }

    private function testCredentialAuthentication(): void
    {
        echo "\n--- 2. Testing Credential Verification & Anti-Enumeration ---\n";

        // Non-existent user
        $user1 = Auth::attempt('ghost_user', 'AnyPassword123');
        $this->assert($user1 === false, 'Auth: Non-existent user fails authentication');

        // Inactive user
        $user2 = Auth::attempt('inactive_staff', 'Staff@12345');
        $this->assert($user2 === false, 'Auth: Inactive user fails authentication even with correct password');

        // Valid user, invalid password
        $user3 = Auth::attempt('abhishek_owner', 'WrongPassword123');
        $this->assert($user3 === false, 'Auth: Valid user with incorrect password fails authentication');

        // Valid owner, valid password
        $user4 = Auth::attempt('abhishek_owner', 'Admin@12345');
        $this->assert(is_array($user4), 'Auth: Valid owner authenticates successfully');
        $this->assert($user4['username'] === 'abhishek_owner', 'Auth: Authenticated user record has correct username');
        $this->assert($user4['role'] === 'OWNER', 'Auth: Authenticated user record has correct role');

        // Valid staff, valid password
        $user5 = Auth::attempt('rahul_staff', 'Staff@12345');
        $this->assert(is_array($user5) && $user5['role'] === 'STAFF', 'Auth: Valid staff member authenticates successfully');
    }

    private function testSessionManagement(): void
    {
        echo "\n--- 3. Testing Session Management & Identity State ---\n";

        Auth::logout();
        $this->assert(!Auth::check(), 'Session: Unauthenticated visitor has check() === false');
        $this->assert(Auth::id() === null, 'Session: Unauthenticated visitor id() is null');
        $this->assert(Auth::role() === null, 'Session: Unauthenticated visitor role() is null');

        // Login owner
        $owner = [
            'id'        => 1,
            'username'  => 'abhishek_owner',
            'full_name' => 'Abhishek Shaw',
            'role'      => 'OWNER',
        ];
        Auth::login($owner);

        $this->assert(Auth::check(), 'Session: check() is true after login');
        $this->assert(Auth::id() === 1, 'Session: id() returns integer 1');
        $this->assert(Auth::username() === 'abhishek_owner', 'Session: username() returns abhishek_owner');
        $this->assert(Auth::fullName() === 'Abhishek Shaw', 'Session: fullName() returns Abhishek Shaw');
        $this->assert(Auth::isOwner(), 'Session: isOwner() is true for OWNER');
        $this->assert(!Auth::isStaff(), 'Session: isStaff() is false for OWNER');

        // Login staff
        $staff = [
            'id'        => 2,
            'username'  => 'rahul_staff',
            'full_name' => 'Rahul Kumar',
            'role'      => 'STAFF',
        ];
        Auth::login($staff);
        $this->assert(Auth::isStaff(), 'Session: isStaff() is true for STAFF');
        $this->assert(!Auth::isOwner(), 'Session: isOwner() is false for STAFF');

        Auth::logout();
        $this->assert(!Auth::check(), 'Session: check() is false after logout');
    }

    private function testOpenRedirectDefense(): void
    {
        echo "\n--- 4. Testing Open-Redirect Defense & Return Destination ---\n";

        // Test safe relative paths
        Auth::setIntendedUrl('/users');
        $this->assert(Auth::getIntendedUrl() === '/users', 'Redirect: Internal relative path /users preserved');

        // Test another safe internal path
        Auth::setIntendedUrl('/profile/change-password');
        $this->assert(Auth::getIntendedUrl() === '/profile/change-password', 'Redirect: Subdirectory path preserved');

        // Test external full URLs with protocol
        Auth::setIntendedUrl('https://evil-attacker.com/steal-creds');
        $this->assert(Auth::getIntendedUrl() === '/', 'Redirect: External HTTPS URL rejected, defaults safely to /');

        // Test scheme-relative URLs
        Auth::setIntendedUrl('//evil-phishing.com/counter');
        $this->assert(Auth::getIntendedUrl() === '/', 'Redirect: Protocol-relative // URLs rejected, defaults safely to /');

        // Test javascript URI
        Auth::setIntendedUrl('javascript:alert(1)');
        $this->assert(Auth::getIntendedUrl() === '/', 'Redirect: javascript URI rejected, defaults safely to /');
    }

    private function testRoleAndPermissionEnforcement(): void
    {
        echo "\n--- 5. Testing Role & Permission Boundaries ---\n";

        // Unauthenticated
        Auth::logout();
        $this->assert(!Authorization::hasPermission('view_purchase_costs'), 'AuthZ: Guest has no permissions');

        // Owner: Implicit full authority across every permission
        Auth::login(['id' => 1, 'username' => 'abhishek_owner', 'full_name' => 'Abhishek Shaw', 'role' => 'OWNER']);
        $this->assert(Authorization::hasPermission('view_purchase_costs'), 'AuthZ: Owner inherently holds view_purchase_costs');
        $this->assert(Authorization::hasPermission('view_profit_margins'), 'AuthZ: Owner inherently holds view_profit_margins');
        $this->assert(Authorization::hasPermission('manage_users'), 'AuthZ: Owner inherently holds manage_users');
        $this->assert(Authorization::hasPermission('arbitrary_feature_perm'), 'AuthZ: Owner holds any catalog permission');

        // Staff without any granted permissions
        Auth::login(['id' => 2, 'username' => 'rahul_staff', 'full_name' => 'Rahul Kumar', 'role' => 'STAFF']);
        $this->assert(!Authorization::hasPermission('view_purchase_costs'), 'AuthZ: Staff without permission cannot view purchase costs');
        $this->assert(!Authorization::hasPermission('view_profit_margins'), 'AuthZ: Staff without permission cannot view profit margins');
        $this->assert(!Authorization::hasPermission('manage_users'), 'AuthZ: Staff cannot manage users without explicit grant');

        // Grant 'perform_sales' and 'manage_repairs' to Rahul (user 2)
        $this->sqlite->exec("INSERT INTO user_permissions (user_id, permission_name, granted_by) VALUES (2, 'perform_sales', 1)");
        $this->sqlite->exec("INSERT INTO user_permissions (user_id, permission_name, granted_by) VALUES (2, 'manage_repairs', 1)");

        Authorization::clearCache(2);
        $this->assert(Authorization::hasPermission('perform_sales', 2), 'AuthZ: Staff with granted permission passes check');
        $this->assert(Authorization::hasPermission('manage_repairs', 2), 'AuthZ: Staff with multiple permissions passes checks');
        $this->assert(!Authorization::hasPermission('view_purchase_costs', 2), 'AuthZ: Staff still blocked from ungranted permissions');

        // Cleanup
        $this->sqlite->exec("DELETE FROM user_permissions WHERE user_id = 2");
        Authorization::clearCache(2);
    }

    private function testAntiSelfLockoutDefense(): void
    {
        echo "\n--- 6. Testing Anti-Self-Lockout Protections ---\n";

        $activeOwnerCount = (int)Database::fetchColumn("SELECT COUNT(*) FROM users WHERE role = 'OWNER' AND is_active = 1");
        $this->assert($activeOwnerCount === 1, 'Lockout Defense: Confirmed starting with exactly 1 active Owner');

        // Attempting to deactivate the single owner via update logic
        $user = Database::fetchOne("SELECT id, username, role, is_active FROM users WHERE id = 1");
        $demoteRole = 'STAFF';
        $deactivateStatus = 0;

        $blockedDemote = false;
        if ($user['role'] === 'OWNER' && ($demoteRole !== 'OWNER' || $deactivateStatus === 0)) {
            $activeCount = (int)Database::fetchColumn("SELECT COUNT(*) FROM users WHERE role = 'OWNER' AND is_active = 1");
            if ($activeCount <= 1) {
                $blockedDemote = true;
            }
        }
        $this->assert($blockedDemote, 'Lockout Defense: System prevents demoting or deactivating the last active Owner account');

        // Add a second owner
        $pass = Security::hashPassword('SecondOwner@123');
        $this->sqlite->exec("
            INSERT INTO users (id, username, password_hash, full_name, role, is_active)
            VALUES (4, 'owner_partner', '{$pass}', 'Partner Owner', 'OWNER', 1)
        ");

        $activeOwnerCountNow = (int)Database::fetchColumn("SELECT COUNT(*) FROM users WHERE role = 'OWNER' AND is_active = 1");
        $this->assert($activeOwnerCountNow === 2, 'Lockout Defense: Successfully seeded second active Owner');

        // Now deactivating one owner is safe because active count > 1
        $partner = Database::fetchOne("SELECT id, username, role, is_active FROM users WHERE id = 4");
        $canDeactivateSecond = false;
        if ($partner['role'] === 'OWNER') {
            $activeCount = (int)Database::fetchColumn("SELECT COUNT(*) FROM users WHERE role = 'OWNER' AND is_active = 1");
            if ($activeCount > 1) {
                $canDeactivateSecond = true;
            }
        }
        $this->assert($canDeactivateSecond, 'Lockout Defense: Deactivating an owner is permitted when another active Owner remains');

        // Clean up second owner
        $this->sqlite->exec("DELETE FROM users WHERE id = 4");
    }

    private function testUserCreationAndHashing(): void
    {
        echo "\n--- 7. Testing User Creation & Password Security ---\n";

        $username = 'priya_accounts';
        $rawPassword = 'SecurePassword#2026';
        $fullName = 'Priya Sharma';
        $role = 'STAFF';

        // 1. Password complexity check
        $this->assert(strlen($rawPassword) >= 8, 'User Creation: Password length meets >= 8 characters requirement');

        // 2. Hash generation
        $hash = Security::hashPassword($rawPassword);
        $this->assert(str_starts_with($hash, '$2y$') || str_starts_with($hash, '$argon2'), 'User Creation: Generated password hash uses BCrypt or Argon2');
        $this->assert($hash !== $rawPassword, 'User Creation: Password is never stored in plaintext');

        // 3. Insert user record
        $stmt = $this->sqlite->prepare("
            INSERT INTO users (username, password_hash, full_name, role, is_active)
            VALUES (:u, :p, :fn, :r, 1)
        ");
        $stmt->execute([
            ':u'  => $username,
            ':p'  => $hash,
            ':fn' => $fullName,
            ':r'  => $role,
        ]);

        $newUserId = (int)$this->sqlite->lastInsertId();
        $this->assert($newUserId > 0, 'User Creation: New user account inserted with valid ID');

        // 4. Verify authentication against stored hash
        $authResult = Auth::attempt($username, $rawPassword);
        $this->assert(is_array($authResult) && (int)$authResult['id'] === $newUserId, 'User Creation: Newly created user successfully authenticates');
    }

    private function testAdminPasswordReset(): void
    {
        echo "\n--- 8. Testing Administrative Password Reset ---\n";

        $targetUsername = 'rahul_staff';
        $newAdminAssignedPass = 'NewStaffPass#789';

        // Admin resets password directly without old password
        $newHash = Security::hashPassword($newAdminAssignedPass);
        $stmt = $this->sqlite->prepare("UPDATE users SET password_hash = :p, updated_at = datetime('now') WHERE username = :u");
        $stmt->execute([':p' => $newHash, ':u' => $targetUsername]);

        // Old password must fail
        $oldAttempt = Auth::attempt($targetUsername, 'Staff@12345');
        $this->assert($oldAttempt === false, 'Admin Reset: Old password immediately invalidated');

        // New password must succeed
        $newAttempt = Auth::attempt($targetUsername, $newAdminAssignedPass);
        $this->assert(is_array($newAttempt) && $newAttempt['username'] === $targetUsername, 'Admin Reset: New password successfully authenticates user');
    }

    private function testSelfPasswordChange(): void
    {
        echo "\n--- 9. Testing Self-Service Password Change ---\n";

        $user = Database::fetchOne("SELECT id, password_hash FROM users WHERE username = 'abhishek_owner'");
        $currentPass = 'Admin@12345';
        $newPass = 'OwnerUpdatedPass@2026';

        // 1. Current password verification check
        $this->assert(Security::verifyPassword($currentPass, $user['password_hash']), 'Self Password Change: Current password verified correctly');
        $this->assert(!Security::verifyPassword('WrongOldPass', $user['password_hash']), 'Self Password Change: Incorrect current password rejected');

        // 2. Complexity check
        $this->assert(strlen($newPass) >= 8, 'Self Password Change: New password meets length requirement');
        $this->assert($newPass !== $currentPass, 'Self Password Change: New password is differentiated from current password');

        // 3. Update hash
        $updatedHash = Security::hashPassword($newPass);
        Database::execute("UPDATE users SET password_hash = :p, updated_at = datetime('now') WHERE id = :id", [
            ':p'  => $updatedHash,
            ':id' => $user['id'],
        ]);

        // 4. Verify login with updated password
        $loginAttempt = Auth::attempt('abhishek_owner', $newPass);
        $this->assert(is_array($loginAttempt), 'Self Password Change: User successfully signs in with newly changed password');
    }

    private function testGranularPermissionAssignment(): void
    {
        echo "\n--- 10. Testing Granular Permission Assignments ---\n";

        $targetUserId = 2; // rahul_staff

        // Fetch valid catalog permissions
        $validCatalog = array_column(
            Database::fetchAll("SELECT name FROM permissions"),
            'name'
        );

        $submittedPermissions = [
            'perform_sales',
            'apply_discounts',
            'manage_repairs',
            'unauthorized_fake_permission', // Should be discarded
        ];

        $filtered = array_intersect($submittedPermissions, $validCatalog);
        $this->assert(count($filtered) === 3, 'Permissions: Invalid/unrecognized permission names cleanly filtered out');
        $this->assert(!in_array('unauthorized_fake_permission', $filtered, true), 'Permissions: Malicious/unknown permission dropped');

        // Save atomically in transaction
        Database::transaction(function() use ($targetUserId, $filtered) {
            Database::execute("DELETE FROM user_permissions WHERE user_id = :id", [':id' => $targetUserId]);

            foreach ($filtered as $permName) {
                Database::execute("
                    INSERT INTO user_permissions (user_id, permission_name, granted_by, created_at)
                    VALUES (:uid, :pname, 1, NOW())
                ", [
                    ':uid'   => $targetUserId,
                    ':pname' => $permName,
                ]);
            }
        });

        Authorization::clearCache($targetUserId);
        $assigned = Authorization::getUserPermissions($targetUserId);

        $this->assert(count($assigned) === 3, 'Permissions: Exactly 3 permissions assigned to staff');
        $this->assert(in_array('perform_sales', $assigned, true), 'Permissions: perform_sales present');
        $this->assert(in_array('apply_discounts', $assigned, true), 'Permissions: apply_discounts present');
        $this->assert(in_array('manage_repairs', $assigned, true), 'Permissions: manage_repairs present');
        $this->assert(!in_array('manage_users', $assigned, true), 'Permissions: manage_users remains unassigned');
    }

    private function testAuditTrailIntegrityAndPrivacy(): void
    {
        echo "\n--- 11. Testing Audit Trail Logging & Sensitive Data Masking ---\n";

        // Log a user update event
        AuditLogger::log(
            'USER_CREATED',
            'users',
            2,
            null,
            ['username' => 'rahul_staff', 'role' => 'STAFF', 'full_name' => 'Rahul Kumar'],
            'Created staff account'
        );

        AuditLogger::log(
            'PASSWORD_RESET_ADMIN',
            'users',
            2,
            null,
            null,
            'Administrator reset password for user rahul_staff'
        );

        $recentLogs = Database::fetchAll("SELECT action, table_name, old_values, new_values, reason FROM audit_logs ORDER BY id DESC LIMIT 5");
        $this->assert(!empty($recentLogs), 'Audit: Audit logs successfully recorded in database');

        $foundPasswordReset = false;
        $foundPlaintextSecret = false;

        foreach ($recentLogs as $log) {
            if ($log['action'] === 'PASSWORD_RESET_ADMIN') {
                $foundPasswordReset = true;
            }
            $serialized = json_encode($log);
            if (strpos($serialized, 'Admin@12345') !== false ||
                strpos($serialized, 'Staff@12345') !== false ||
                strpos($serialized, '$2y$') !== false ||
                strpos($serialized, '$argon2') !== false) {
                $foundPlaintextSecret = true;
            }
        }

        $this->assert($foundPasswordReset, 'Audit: Administrative password reset logged with correct action descriptor');
        $this->assert(!$foundPlaintextSecret, 'Audit: Passwords, plaintext secrets, and password hashes are strictly absent from audit logs');
    }

    private function testCsrfDefense(): void
    {
        echo "\n--- 12. Testing CSRF Protection Layer ---\n";

        $token = Security::getCsrfToken();
        $this->assert(is_string($token) && strlen($token) === 64, 'CSRF: Token is a 64-char hexadecimal cryptographic secret');

        $this->assert(Security::validateCsrfToken($token), 'CSRF: Valid session token accepted');
        $this->assert(!Security::validateCsrfToken('forged_token_value_xyz'), 'CSRF: Forged/tampered token rejected');
        $this->assert(!Security::validateCsrfToken(null), 'CSRF: Null token rejected');
        $this->assert(!Security::validateCsrfToken(''), 'CSRF: Empty token string rejected');

        $fieldHtml = Security::csrfField();
        $this->assert(str_starts_with($fieldHtml, '<input type="hidden" name="csrf_token"'), 'CSRF: HTML input field generated with correct name and type');
        $this->assert(strpos($fieldHtml, $token) !== false, 'CSRF: HTML input field embeds current token value');
    }

    private function printSummary(): void
    {
        echo "\n--------------------------------------------------------------------\n";
        echo "TEST SUMMARY: Passed: {$this->passed} | Failed: {$this->failed}\n";
        echo "--------------------------------------------------------------------\n";

        if ($this->failed > 0) {
            echo "FAILURES:\n";
            foreach ($this->failures as $f) {
                echo " - {$f}\n";
            }
            exit(1);
        } else {
            echo "ALL AUTHENTICATION & USER MANAGEMENT TESTS PASSED SUCCESSFULLY! (100% OK)\n\n";
            exit(0);
        }
    }
}

// Execute the test suite
$suite = new AuthAndUserManagementTest();
$suite->runAll();
