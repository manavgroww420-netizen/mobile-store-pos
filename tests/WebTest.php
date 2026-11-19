<?php
declare(strict_types=1);

/**
 * Front Controller Web Dispatch Test
 * File: tests/WebTest.php
 */

// --- Test 1: Guest Dispatch to /login ---
$_SERVER['REQUEST_METHOD'] = 'GET';
$_SERVER['REQUEST_URI'] = '/login';
$_SERVER['REMOTE_ADDR'] = '127.0.0.1';
$_SERVER['HTTP_USER_AGENT'] = 'TestClient/1.0';

ob_start();
require __DIR__ . '/../public/index.php';
$loginHtml = ob_get_clean();

$loginHasTitle = strpos($loginHtml, 'ABHISHEK MOBILE HUB') !== false;
$loginHasForm = strpos($loginHtml, 'action="/login"') !== false;
$loginHasUsername = strpos($loginHtml, 'name="username"') !== false;
$loginHasCsrf = strpos($loginHtml, 'name="csrf_token"') !== false;

echo "--- Web Dispatch Test: /login (Guest) ---\n";
echo "Title Rendered: " . ($loginHasTitle ? 'YES' : 'NO') . "\n";
echo "Form Rendered: " . ($loginHasForm ? 'YES' : 'NO') . "\n";
echo "CSRF Token Present: " . ($loginHasCsrf ? 'YES' : 'NO') . "\n";
echo "HTML Payload Length: " . strlen($loginHtml) . " bytes\n";

if (!$loginHasTitle || !$loginHasForm || !$loginHasUsername || !$loginHasCsrf) {
    echo "FAILED: /login view failed to render expected elements.\n";
    exit(1);
}

// --- Test 2: Authenticated Dispatch to / ---
\AMH\Auth::login([
    'id'        => 1,
    'username'  => 'abhishek',
    'full_name' => 'Abhishek Shaw',
    'role'      => 'OWNER',
]);

$_SERVER['REQUEST_METHOD'] = 'GET';
$_SERVER['REQUEST_URI'] = '/';

ob_start();
// Since constants are defined, dispatch the route via router directly or simulated request
\AMH\Router::dispatch('GET', '/');
$homeHtml = ob_get_clean();

$homeHasTitle = strpos($homeHtml, 'ABHISHEK MOBILE HUB') !== false;
$homeHasSidebar = strpos($homeHtml, 'Core Operations') !== false;
$homeHasAdmin = strpos($homeHtml, 'Staff &amp; Permissions') !== false || strpos($homeHtml, 'Staff & Permissions') !== false;

echo "\n--- Web Dispatch Test: / (Authenticated OWNER) ---\n";
echo "Title Rendered: " . ($homeHasTitle ? 'YES' : 'NO') . "\n";
echo "Sidebar Rendered: " . ($homeHasSidebar ? 'YES' : 'NO') . "\n";
echo "Staff & Permissions Link: " . ($homeHasAdmin ? 'YES' : 'NO') . "\n";
echo "HTML Payload Length: " . strlen($homeHtml) . " bytes\n";

if (!$homeHasTitle || !$homeHasSidebar) {
    echo "FAILED: / authenticated view failed to render expected elements.\n";
    exit(1);
}

echo "\nALL WEB DISPATCH INTEGRATION TESTS PASSED (100% OK)\n";
exit(0);

