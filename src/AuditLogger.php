<?php
declare(strict_types=1);

namespace AMH;

/**
 * Append-Only Audit Logging Foundation
 *
 * Captures critical operational events (stock changes, price overrides, voids,
 * sensitive ID views) directly into the `audit_logs` table with fallback file logging.
 */
class AuditLogger
{
    /**
     * Record an audit trail entry.
     *
     * @param string $action Standard action descriptor (e.g. 'STOCK_ADJUSTMENT', 'PRICE_OVERRIDE')
     * @param string $tableName Target entity table name
     * @param int|string $recordId Target record primary key
     * @param array|null $oldValues Previous snapshot values before mutation
     * @param array|null $newValues New snapshot values after mutation
     * @param string|null $reason Optional business rationale or operator remark
     * @param int|null $userId Explicit user ID (defaults to authenticated user)
     * @return bool True if logged successfully
     */
    public static function log(
        string $action,
        string $tableName,
        int|string $recordId,
        ?array $oldValues = null,
        ?array $newValues = null,
        ?string $reason = null,
        ?int $userId = null
    ): bool {
        $actorId = $userId ?? Auth::id() ?? 1; // Fallback to 1 (Admin/System) if unauthenticated
        $ip = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
        $userAgent = substr($_SERVER['HTTP_USER_AGENT'] ?? 'CLI/System', 0, 255);

        $oldJson = $oldValues !== null ? json_encode($oldValues, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) : null;
        $newJson = $newValues !== null ? json_encode($newValues, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) : null;

        try {
            $sql = "INSERT INTO audit_logs (
                        user_id, action, table_name, record_id,
                        old_values, new_values, reason, ip_address, user_agent, created_at
                    ) VALUES (
                        :uid, :action, :tbl, :rec_id,
                        :old_val, :new_val, :reason, :ip, :ua, NOW()
                    )";

            Database::execute($sql, [
                ':uid'     => $actorId,
                ':action'  => strtoupper(trim($action)),
                ':tbl'     => trim($tableName),
                ':rec_id'  => (int)$recordId,
                ':old_val' => $oldJson,
                ':new_val' => $newJson,
                ':reason'  => $reason ? substr(trim($reason), 0, 255) : null,
                ':ip'      => $ip,
                ':ua'      => $userAgent,
            ]);

            return true;
        } catch (\Throwable $e) {
            // Graceful fallback to file logging so failure to audit never silently aborts primary business actions
            self::fallbackLogToFile($actorId, $action, $tableName, $recordId, $oldJson, $newJson, $reason, $e->getMessage());
            return false;
        }
    }

    /**
     * Fallback file-based logging if database is unavailable.
     */
    private static function fallbackLogToFile(
        int $userId,
        string $action,
        string $tableName,
        int|string $recordId,
        ?string $oldJson,
        ?string $newJson,
        ?string $reason,
        string $dbError
    ): void {
        $logDir = AMH_LOGS_PATH;
        if (!is_dir($logDir)) {
            @mkdir($logDir, 0755, true);
        }

        $logPath = $logDir . DIRECTORY_SEPARATOR . 'audit_fallback.log';
        $entry = sprintf(
            "[%s] USER:%d ACTION:%s TABLE:%s ID:%s REASON:%s | DB_ERR:%s\n  OLD: %s\n  NEW: %s\n",
            date('Y-m-d H:i:s'),
            $userId,
            $action,
            $tableName,
            $recordId,
            $reason ?? 'None',
            $dbError,
            $oldJson ?? 'null',
            $newJson ?? 'null'
        );

        @file_put_contents($logPath, $entry, FILE_APPEND | LOCK_EX);
    }

    /**
     * Fetch recent audit logs for administration inspection.
     */
    public static function getRecentLogs(int $limit = 50): array
    {
        try {
            $sql = "SELECT a.*, u.username, u.full_name 
                    FROM audit_logs a
                    LEFT JOIN users u ON a.user_id = u.id
                    ORDER BY a.id DESC
                    LIMIT " . (int)$limit;

            return Database::fetchAll($sql);
        } catch (\Throwable $e) {
            return [];
        }
    }
}
