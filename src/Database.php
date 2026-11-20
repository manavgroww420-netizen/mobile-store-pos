<?php
declare(strict_types=1);

namespace AMH;

use PDO;
use PDOException;
use PDOStatement;
use Throwable;
use RuntimeException;

/**
 * Central PDO Database Connection & Transaction Manager
 *
 * Enforces strict prepared statements, exception mode, and atomic transactions.
 */
class Database
{
    private static ?PDO $pdo = null;
    private static ?array $customConfig = null;

    /**
     * Set a custom configuration array (useful for testing or dynamic cPanel settings).
     */
    public static function setConfig(array $config): void
    {
        self::$customConfig = $config;
        self::$pdo = null; // Reset connection if config changes
    }

    /**
     * Inject an active PDO instance (for automated testing or external pooling).
     */
    public static function setPdo(PDO $pdo): void
    {
        self::$pdo = $pdo;
    }

    /**
     * Get the active PDO singleton instance.
     *
     * @throws RuntimeException If connection cannot be established.
     */
    public static function getConnection(): PDO
    {
        if (self::$pdo !== null) {
            return self::$pdo;
        }

        $config = self::$customConfig ?? require AMH_CONFIG_PATH . DIRECTORY_SEPARATOR . 'database.php';
        $driver = $config['driver'] ?? 'mysql';

        if ($driver === 'sqlite') {
            return self::initSqlite();
        }

        $dsn = sprintf(
            'mysql:host=%s;port=%d;dbname=%s;charset=%s',
            $config['host'],
            $config['port'],
            $config['dbname'],
            $config['charset'] ?? 'utf8mb4'
        );

        try {
            self::$pdo = new PDO(
                $dsn,
                $config['username'],
                $config['password'],
                $config['options'] ?? []
            );

            return self::$pdo;
        } catch (PDOException|Throwable $e) {
            error_log('MySQL Connection Failure (' . $e->getMessage() . '), attempting SQLite fallback...');
            try {
                return self::initSqlite();
            } catch (Throwable $se) {
                error_log('Database Connection Failure: ' . $se->getMessage());
                throw new RuntimeException(
                    'Unable to connect to the shop database. Please verify service status and configuration.',
                    (int)$e->getCode()
                );
            }
        }
    }

    /**
     * Initialize embedded SQLite database with custom functions and automatic schema seeding.
     */
    private static function initSqlite(): PDO
    {
        $storageDir = AMH_STORAGE_PATH;
        if (!is_dir($storageDir)) {
            @mkdir($storageDir, 0777, true);
        }

        $dbFile = $storageDir . DIRECTORY_SEPARATOR . 'database.sqlite';
        $isNew = !file_exists($dbFile) || filesize($dbFile) === 0;

        self::$pdo = new PDO('sqlite:' . $dbFile, null, null, [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ]);

        self::$pdo->exec('PRAGMA foreign_keys = ON;');
        self::$pdo->exec('PRAGMA journal_mode = WAL;');

        // Register MySQL-compatible SQL functions in SQLite
        self::$pdo->sqliteCreateFunction('NOW', fn() => date('Y-m-d H:i:s'));
        self::$pdo->sqliteCreateFunction('now', fn() => date('Y-m-d H:i:s'));
        self::$pdo->sqliteCreateFunction('CURDATE', fn() => date('Y-m-d'));
        self::$pdo->sqliteCreateFunction('curdate', fn() => date('Y-m-d'));
        self::$pdo->sqliteCreateFunction('LAST_INSERT_ID', fn() => self::$pdo->lastInsertId());
        self::$pdo->sqliteCreateFunction('last_insert_id', fn() => self::$pdo->lastInsertId());
        self::$pdo->sqliteCreateFunction('CONCAT', fn(...$parts) => implode('', $parts));
        self::$pdo->sqliteCreateFunction('concat', fn(...$parts) => implode('', $parts));

        // Auto-initialize schema if new or empty
        if ($isNew) {
            $schemaFile = AMH_BASE_PATH . DIRECTORY_SEPARATOR . 'sql' . DIRECTORY_SEPARATOR . 'schema_sqlite.sql';
            if (file_exists($schemaFile)) {
                $sql = file_get_contents($schemaFile);
                self::$pdo->exec($sql);
            }
        }

        return self::$pdo;
    }

    /**
     * Execute a callable inside an atomic database transaction.
     *
     * Automatically handles BEGIN, COMMIT, and ROLLBACK.
     * If any exception or error occurs, the transaction is rolled back completely.
     *
     * @template T
     * @param callable(PDO): T $callback
     * @return T
     * @throws Throwable Re-throws the triggering exception to preserve business logic failure signals.
     */
    public static function transaction(callable $callback): mixed
    {
        $pdo = self::getConnection();

        $isTopLevel = !$pdo->inTransaction();
        if ($isTopLevel) {
            $pdo->beginTransaction();
        }

        try {
            $result = $callback($pdo);

            if ($isTopLevel && $pdo->inTransaction()) {
                $pdo->commit();
            }

            return $result;
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $e;
        }
    }

    /**
     * Manually begin a transaction.
     */
    public static function beginTransaction(): bool
    {
        $pdo = self::getConnection();
        return $pdo->inTransaction() ? false : $pdo->beginTransaction();
    }

    /**
     * Manually commit an active transaction.
     */
    public static function commit(): bool
    {
        $pdo = self::getConnection();
        return $pdo->inTransaction() ? $pdo->commit() : false;
    }

    /**
     * Manually roll back an active transaction.
     */
    public static function rollBack(): bool
    {
        $pdo = self::getConnection();
        return $pdo->inTransaction() ? $pdo->rollBack() : false;
    }

    /**
     * Check if a transaction is currently active.
     */
    public static function inTransaction(): bool
    {
        return self::getConnection()->inTransaction();
    }

    /**
     * Execute a prepared query with parameters.
     */
    public static function query(string $sql, array $params = []): PDOStatement
    {
        $stmt = self::getConnection()->prepare($sql);
        $stmt->execute($params);
        return $stmt;
    }

    /**
     * Fetch a single row as an associative array.
     */
    public static function fetchOne(string $sql, array $params = []): ?array
    {
        $stmt = self::query($sql, $params);
        $row = $stmt->fetch();
        return $row !== false ? $row : null;
    }

    /**
     * Fetch all rows matching the query.
     */
    public static function fetchAll(string $sql, array $params = []): array
    {
        $stmt = self::query($sql, $params);
        return $stmt->fetchAll();
    }

    /**
     * Fetch a single column from the first row.
     */
    public static function fetchColumn(string $sql, array $params = [], int $column = 0): mixed
    {
        $stmt = self::query($sql, $params);
        return $stmt->fetchColumn($column);
    }

    /**
     * Execute an INSERT/UPDATE/DELETE statement and return affected rows.
     */
    public static function execute(string $sql, array $params = []): int
    {
        $stmt = self::query($sql, $params);
        return $stmt->rowCount();
    }

    /**
     * Get the auto-increment ID generated by the last INSERT query.
     */
    public static function lastInsertId(): string
    {
        return self::getConnection()->lastInsertId();
    }
}
