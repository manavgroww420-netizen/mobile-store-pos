<?php
declare(strict_types=1);

/**
 * ABHISHEK MOBILE HUB — Automated Foundation Test Suite
 *
 * File: tests/FoundationTest.php
 * Run via terminal:
 * C:\xampp\php\php.exe tests/FoundationTest.php
 */

// Define application base paths
defined('AMH_BASE_PATH') or define('AMH_BASE_PATH', dirname(__DIR__));
defined('AMH_CONFIG_PATH') or define('AMH_CONFIG_PATH', AMH_BASE_PATH . DIRECTORY_SEPARATOR . 'config');
defined('AMH_SRC_PATH') or define('AMH_SRC_PATH', AMH_BASE_PATH . DIRECTORY_SEPARATOR . 'src');
defined('AMH_VIEWS_PATH') or define('AMH_VIEWS_PATH', AMH_BASE_PATH . DIRECTORY_SEPARATOR . 'views');
defined('AMH_PUBLIC_PATH') or define('AMH_PUBLIC_PATH', AMH_BASE_PATH . DIRECTORY_SEPARATOR . 'public');
defined('AMH_STORAGE_PATH') or define('AMH_STORAGE_PATH', AMH_BASE_PATH . DIRECTORY_SEPARATOR . 'storage');
defined('AMH_LOGS_PATH') or define('AMH_LOGS_PATH', AMH_STORAGE_PATH . DIRECTORY_SEPARATOR . 'logs');

// Load Core Classes
require_once AMH_SRC_PATH . '/ErrorHandler.php';
require_once AMH_SRC_PATH . '/Database.php';
require_once AMH_SRC_PATH . '/Security.php';
require_once AMH_SRC_PATH . '/Financial.php';
require_once AMH_SRC_PATH . '/Auth.php';
require_once AMH_SRC_PATH . '/Authorization.php';
require_once AMH_SRC_PATH . '/AuditLogger.php';
require_once AMH_SRC_PATH . '/View.php';
require_once AMH_SRC_PATH . '/Router.php';
require_once AMH_SRC_PATH . '/Helpers.php';

use AMH\Security;
use AMH\Financial;
use AMH\Auth;
use AMH\Authorization;
use AMH\AuditLogger;
use AMH\Database;

class FoundationTest
{
    private int $passed = 0;
    private int $failed = 0;
    private array $failures = [];

    public function runAll(): void
    {
        echo "====================================================================\n";
        echo "ABHISHEK MOBILE HUB — APPLICATION FOUNDATION AUTOMATED TEST SUITE\n";
        echo "====================================================================\n\n";

        $this->testConfiguration();
        $this->testOutputEscaping();
        $this->testCsrfGenerationAndValidation();
        $this->testPasswordHashing();
        $this->testImeiValidation();
        $this->testAadhaarSecurity();
        $this->testFinancialCalculations();
        $this->testProportionalDiscountAllocation();
        $this->testAuthAndSessionManagement();
        $this->testAuthorizationPermissions();
        $this->testDatabaseTransactions();
        $this->testAuditLogger();

        echo "\n--------------------------------------------------------------------\n";
        echo "TEST SUMMARY: Passed: {$this->passed} | Failed: {$this->failed}\n";
        echo "--------------------------------------------------------------------\n";

        if ($this->failed > 0) {
            echo "\nFAILURES DETECTED:\n";
            foreach ($this->failures as $f) {
                echo " - {$f}\n";
            }
            exit(1);
        } else {
            echo "ALL FOUNDATION TESTS PASSED SUCCESSFULLY! (100% OK)\n";
            exit(0);
        }
    }

    private function assert(bool $condition, string $testName): void
    {
        if ($condition) {
            $this->passed++;
            echo " [PASS] {$testName}\n";
        } else {
            $this->failed++;
            $this->failures[] = $testName;
            echo " [FAIL] {$testName}\n";
        }
    }

    private function testConfiguration(): void
    {
        echo "--- 1. Testing Configuration & Bootstrap ---\n";
        $config = require AMH_CONFIG_PATH . '/config.php';
        $this->assert(isset($config['app']['name']) && $config['app']['name'] === 'ABHISHEK MOBILE HUB', 'Config: App Name is ABHISHEK MOBILE HUB');
        $this->assert(date_default_timezone_get() === 'Asia/Kolkata', 'Config: Timezone correctly set to Asia/Kolkata');
        $this->assert(isset($config['security']['encryption_key']), 'Config: AES encryption key configured');
    }

    private function testOutputEscaping(): void
    {
        echo "\n--- 2. Testing Security: XSS Output Escaping ---\n";
        $malicious = '<script>alert("XSS")</script>&"\'';
        $escaped = Security::e($malicious);
        $this->assert(strpos($escaped, '<script>') === false, 'XSS: Tags properly escaped');
        $this->assert($escaped === '&lt;script&gt;alert(&quot;XSS&quot;)&lt;/script&gt;&amp;&quot;&#039;', 'XSS: Exact entity matching');
        $this->assert(e(null) === '', 'XSS: Null values return empty string without PHP warnings');
    }

    private function testCsrfGenerationAndValidation(): void
    {
        echo "\n--- 3. Testing Security: CSRF Protection ---\n";
        $token1 = Security::getCsrfToken();
        $this->assert(!empty($token1) && strlen($token1) === 64, 'CSRF: Token generated as 64-char hex string');
        $this->assert(Security::validateCsrfToken($token1), 'CSRF: Token validates correctly with hash_equals');
        $this->assert(!Security::validateCsrfToken('invalid_token_12345'), 'CSRF: Forged token rejected');
        $this->assert(!Security::validateCsrfToken(null), 'CSRF: Null token rejected');
    }

    private function testPasswordHashing(): void
    {
        echo "\n--- 4. Testing Security: Password Hashing ---\n";
        $raw = 'OwnerStrongSecretPass#2026';
        $hash = Security::hashPassword($raw);
        $this->assert(str_starts_with($hash, '$2y$'), 'Password: BCRYPT hash generated with prefix $2y$');
        $this->assert(Security::verifyPassword($raw, $hash), 'Password: Valid password verifies successfully');
        $this->assert(!Security::verifyPassword('WrongPassword', $hash), 'Password: Invalid password rejected');
    }

    private function testImeiValidation(): void
    {
        echo "\n--- 5. Testing Security: IMEI 15-Digit & Luhn Validation ---\n";
        // Valid 15-digit IMEI examples
        $validImei = '861234567890123';
        $this->assert(Security::validateImei($validImei), 'IMEI: 15 numeric digits format accepted');
        $this->assert(!Security::validateImei('86123456789012'), 'IMEI: 14 digits rejected');
        $this->assert(!Security::validateImei('8612345678901234'), 'IMEI: 16 digits rejected');
        $this->assert(!Security::validateImei('86123456789012A'), 'IMEI: Non-numeric alphanumeric string rejected');
        
        // Luhn algorithm check
        $luhnValid = '49927398716';
        $this->assert(Security::validateLuhn($luhnValid), 'IMEI/Luhn: Luhn check digit verification works');
    }

    private function testAadhaarSecurity(): void
    {
        echo "\n--- 6. Testing Security: Aadhaar Privacy & AES-256-GCM Encryption ---\n";
        $aadhaar = '5482 1294 8923';
        $masked = Security::maskAadhaar($aadhaar);
        $this->assert($masked === 'XXXXXXXX8923', 'Aadhaar: Masking displays only last 4 digits (XXXXXXXX8923)');
        $this->assert(Security::extractAadhaarLast4($aadhaar) === '8923', 'Aadhaar: Last 4 digits extraction');

        $hash1 = Security::hashAadhaar($aadhaar);
        $hash2 = Security::hashAadhaar('548212948923');
        $this->assert($hash1 === $hash2, 'Aadhaar: Normalized space-invariant hash matching for duplicate check');

        // AES-256-GCM Authenticated Encryption Round-Trip
        $encrypted = Security::encryptAadhaar($aadhaar);
        $this->assert(!empty($encrypted) && $encrypted !== $aadhaar, 'Aadhaar: Encrypted payload is not plaintext');
        $decrypted = Security::decryptAadhaar($encrypted);
        $this->assert($decrypted === $aadhaar, 'Aadhaar: Decrypted string matches original plaintext');
    }

    private function testFinancialCalculations(): void
    {
        echo "\n--- 7. Testing Financial Engine: Fixed-Precision Arithmetic ---\n";
        $sum = Financial::add('19999.99', '5500.50');
        $this->assert($sum === '25500.49', "Financial: Fixed addition (19999.99 + 5500.50 = 25500.49)");

        $costBasis = Financial::calculateUsedPhoneCostBasis('20000.00', '4000.00', '1500.00');
        $this->assert($costBasis === '25500.00', "Financial: Used phone cost basis formulation (20000 + 4000 + 1500 = 25500.00)");

        $profit = Financial::calculateGrossProfit('30000.00', $costBasis);
        $this->assert($profit === '4500.00', "Financial: Gross profit calculation (30000.00 - 25500.00 = 4500.00)");

        $lossProfit = Financial::calculateGrossProfit('24000.00', '25500.00');
        $this->assert($lossProfit === '-1500.00', "Financial: Negative loss recorded accurately (-1500.00)");

        $repairProfit = Financial::calculateRepairProfit('2800.00', '1800.00', '300.00');
        $this->assert($repairProfit === '700.00', "Financial: Customer repair profit (2800 - 2100 = 700.00)");

        $formatted = Financial::format('25500.50');
        $this->assert($formatted === '₹ 25,500.50', "Financial: Rupee formatting with commas and symbol (₹ 25,500.50)");
    }

    private function testProportionalDiscountAllocation(): void
    {
        echo "\n--- 8. Testing Financial Engine: Proportional Discount Reconciliation ---\n";
        $items = [
            ['id' => 1, 'subtotal' => '1000.00', 'cost' => '700.00'],
            ['id' => 2, 'subtotal' => '2000.00', 'cost' => '1500.00'],
        ];
        $headerDiscount = '300.00';

        $reconciled = Financial::allocateDiscountProportionally($items, $headerDiscount);

        // Subtotal = 3000.00. Item 1 is 1/3 (100.00 discount). Item 2 is 2/3 (200.00 discount).
        $this->assert($reconciled[0]['discount'] === '100.00', 'Discount Allocation: Item 1 gets 100.00 discount');
        $this->assert($reconciled[1]['discount'] === '200.00', 'Discount Allocation: Item 2 gets 200.00 discount');
        $this->assert($reconciled[0]['net_price'] === '900.00', 'Discount Allocation: Item 1 net price is 900.00');
        $this->assert($reconciled[1]['net_price'] === '1800.00', 'Discount Allocation: Item 2 net price is 1800.00');
        $this->assert($reconciled[0]['profit'] === '200.00', 'Discount Allocation: Item 1 profit is 200.00 (900 - 700)');
        $this->assert($reconciled[1]['profit'] === '300.00', 'Discount Allocation: Item 2 profit is 300.00 (1800 - 1500)');

        $sumLineProfits = Financial::add($reconciled[0]['profit'], $reconciled[1]['profit']);
        $expectedTotalProfit = Financial::sub(Financial::sub('3000.00', '300.00'), '2200.00'); // 2700 - 2200 = 500.00
        $this->assert($sumLineProfits === $expectedTotalProfit, 'Discount Allocation: Sum of line profits strictly equals header gross profit (500.00)');
    }

    private function testAuthAndSessionManagement(): void
    {
        echo "\n--- 9. Testing Authentication & Session State ---\n";
        Auth::startSession();

        $testUser = [
            'id'        => 42,
            'username'  => 'abhishek_owner',
            'full_name' => 'Abhishek Shaw',
            'role'      => 'OWNER',
            'is_active' => 1,
        ];

        Auth::login($testUser);
        $this->assert(Auth::check(), 'Auth: User check returns true after login');
        $this->assert(Auth::id() === 42, 'Auth: Authenticated user ID is 42');
        $this->assert(Auth::username() === 'abhishek_owner', 'Auth: Username matches');
        $this->assert(Auth::isOwner(), 'Auth: isOwner returns true for OWNER role');
        $this->assert(!Auth::isStaff(), 'Auth: isStaff returns false for OWNER role');

        Auth::logout();
        $this->assert(!Auth::check(), 'Auth: User check returns false after logout');
        $this->assert(Auth::id() === null, 'Auth: User ID cleared on logout');
    }

    private function testAuthorizationPermissions(): void
    {
        echo "\n--- 10. Testing Authorization & Role-Based Permissions ---\n";
        // Owner check
        $owner = [
            'id'        => 1,
            'username'  => 'admin',
            'full_name' => 'Abhishek (Owner)',
            'role'      => 'OWNER',
            'is_active' => 1,
        ];
        Auth::login($owner);

        // Owner automatically has ALL permissions
        $this->assert(Authorization::hasPermission('view_purchase_costs'), 'AuthZ: Owner automatically inherits view_purchase_costs');
        $this->assert(Authorization::hasPermission('view_profit_margins'), 'AuthZ: Owner automatically inherits view_profit_margins');
        $this->assert(Authorization::hasPermission('manage_inventory'), 'AuthZ: Owner automatically inherits manage_inventory');

        Auth::logout();
        $this->assert(!Authorization::hasPermission('view_profit_margins'), 'AuthZ: Unauthenticated visitor has no permissions');
    }

    private function testDatabaseTransactions(): void
    {
        echo "\n--- 11. Testing Database Transaction Wrapper ---\n";
        // Setup an in-memory SQLite PDO instance to test transaction BEGIN, COMMIT, and ROLLBACK mechanics safely
        $sqlite = new PDO('sqlite::memory:', null, null, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
        ]);
        $sqlite->exec("CREATE TABLE test_ledger (id INTEGER PRIMARY KEY, note TEXT, amount REAL)");

        Database::setPdo($sqlite);

        // Test 1: Successful Transaction Commit
        $result = Database::transaction(function(PDO $pdo) {
            $pdo->exec("INSERT INTO test_ledger (note, amount) VALUES ('Inward Stock Purchase', 15000.00)");
            $pdo->exec("INSERT INTO test_ledger (note, amount) VALUES ('Sale Item', 18000.00)");
            return 'OK_COMMITTED';
        });

        $this->assert($result === 'OK_COMMITTED', 'Transactions: Transaction completed with return value');
        $count = (int)$sqlite->query("SELECT COUNT(*) FROM test_ledger")->fetchColumn();
        $this->assert($count === 2, 'Transactions: Both rows committed successfully');

        // Test 2: Atomic Rollback on Business Logic Exception
        $rolledBack = false;
        try {
            Database::transaction(function(PDO $pdo) {
                $pdo->exec("INSERT INTO test_ledger (note, amount) VALUES ('Partial Step 1', 5000.00)");
                throw new \DomainException("Simulated business error: Insufficient stock for item 101");
            });
        } catch (\DomainException $e) {
            $rolledBack = true;
        }

        $this->assert($rolledBack, 'Transactions: Exception caught by caller');
        $countAfterRollback = (int)$sqlite->query("SELECT COUNT(*) FROM test_ledger")->fetchColumn();
        $this->assert($countAfterRollback === 2, 'Transactions: Partial insertion was rolled back completely (atomic integrity preserved)');
    }

    private function testAuditLogger(): void
    {
        echo "\n--- 12. Testing Audit Logger Foundation ---\n";
        // Verify fallback logger writes to disk if table is not in test database
        $logged = AuditLogger::log(
            'STOCK_ADJUSTMENT',
            'inventory_stock',
            105,
            ['quantity' => 10],
            ['quantity' => 8],
            'Damaged screen protector written off',
            1
        );

        $fallbackLog = AMH_LOGS_PATH . '/audit_fallback.log';
        $this->assert(file_exists($fallbackLog), 'AuditLogger: Disk fallback log created when DB is decoupled');
        $content = file_get_contents($fallbackLog);
        $this->assert(strpos($content, 'STOCK_ADJUSTMENT') !== false, 'AuditLogger: Log contains correct action descriptor');
    }
}

// Execute the test suite
$test = new FoundationTest();
$test->runAll();
