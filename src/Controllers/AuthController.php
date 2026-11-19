<?php
declare(strict_types=1);

namespace AMH\Controllers;

use AMH\Auth;
use AMH\View;
use AMH\Security;
use AMH\RateLimiter;
use AMH\AuditLogger;

/**
 * Authentication Controller
 *
 * Handles login presentation, rate-limited credential verification,
 * session initiation, and secure logout.
 */
class AuthController
{
    /**
     * Render the login screen.
     */
    public function showLogin(): void
    {
        if (Auth::check()) {
            Security::safeRedirect('/');
            return;
        }

        View::render('auth/login', [
            'pageTitle' => 'Counter Terminal Sign In',
        ], 'auth');
    }

    /**
     * Process authentication credentials with brute-force rate limiting.
     */
    public function login(): void
    {
        if (Auth::check()) {
            Security::safeRedirect('/');
            return;
        }

        $ip = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
        $username = trim($_POST['username'] ?? '');
        $password = (string)($_POST['password'] ?? '');
        $csrfToken = $_POST['csrf_token'] ?? null;

        // Verify CSRF Token
        if (!Security::validateCsrfToken($csrfToken)) {
            View::flash('danger', 'Security verification failed (CSRF token missing or expired). Please try again.');
            View::render('auth/login', [
                'pageTitle'   => 'Sign In',
                'oldUsername' => $username,
            ], 'auth');
            return;
        }

        // Form unique rate limit key based on client IP
        $rateKey = 'login:' . md5($ip);

        // 1. Enforce Brute-Force Rate Limiting (5 failed attempts per 15 minutes)
        if (RateLimiter::isThrottled($rateKey, 5, 900)) {
            $secondsRemaining = RateLimiter::availableIn($rateKey, 900);
            $minutesRemaining = (int)ceil($secondsRemaining / 60);

            AuditLogger::log(
                'LOGIN_THROTTLED',
                'users',
                0,
                null,
                ['ip' => $ip, 'username_attempted' => $username],
                'Rate limit exceeded. Login attempt blocked.'
            );

            http_response_code(429);
            View::flash('danger', "Too many failed login attempts. Terminal temporarily throttled. Please try again in {$minutesRemaining} minute(s).");
            
            View::render('auth/login', [
                'pageTitle'   => 'Terminal Throttled',
                'oldUsername' => $username,
            ], 'auth');
            return;
        }

        // 2. Validate non-empty fields
        if (empty($username) || empty($password)) {
            RateLimiter::hit($rateKey, 900);
            View::flash('danger', 'Please provide both your username and password.');
            View::render('auth/login', [
                'pageTitle'   => 'Sign In',
                'oldUsername' => $username,
            ], 'auth');
            return;
        }

        // 3. Attempt Authentication
        $user = Auth::attempt($username, $password);

        if ($user !== false) {
            // Authentication successful -> clear rate limit counter
            RateLimiter::clear($rateKey);

            AuditLogger::log(
                'LOGIN_SUCCESS',
                'users',
                (int)$user['id'],
                null,
                ['username' => $user['username'], 'role' => $user['role']],
                'User successfully authenticated and opened session.'
            );

            View::flash('success', "Welcome back, {$user['full_name']}!");

            // Redirect to intended safe internal route
            $targetUrl = Auth::getIntendedUrl('/');
            Security::safeRedirect($targetUrl);
            return;
        }

        // Authentication failed -> increment rate limit counter
        RateLimiter::hit($rateKey, 900);

        AuditLogger::log(
            'LOGIN_FAILED',
            'users',
            0,
            null,
            ['username_attempted' => $username, 'ip' => $ip],
            'Authentication failed: invalid credentials or inactive account.'
        );

        // Security requirement: Generic error message (never reveal if username exists)
        View::flash('danger', 'Invalid username or password.');

        View::render('auth/login', [
            'pageTitle'   => 'Sign In',
            'oldUsername' => $username,
        ], 'auth');
    }

    /**
     * Terminate authenticated session.
     */
    public function logout(): void
    {
        $csrfToken = $_POST['csrf_token'] ?? null;
        if (!Security::validateCsrfToken($csrfToken)) {
            http_response_code(403);
            die('Access Denied: Invalid or expired CSRF security token.');
        }

        if (Auth::check()) {
            $userId = Auth::id() ?? 0;
            $username = Auth::username() ?? 'unknown';

            AuditLogger::log(
                'LOGOUT',
                'users',
                $userId,
                null,
                ['username' => $username],
                'User signed out and closed session.'
            );
        }

        Auth::logout();
        View::flash('info', 'You have been safely signed out.');
        Security::safeRedirect('/login');
    }
}
