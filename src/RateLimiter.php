<?php
declare(strict_types=1);

namespace AMH;

/**
 * Lightweight File & Session Rate Limiter
 *
 * Implements brute-force throttling for authentication attempts without Redis
 * or external services, making it fully compatible with shared and cPanel hosting.
 */
class RateLimiter
{
    private static string $storageFile = '';

    private static function getStorageFile(): string
    {
        if (empty(self::$storageFile)) {
            self::$storageFile = (defined('AMH_STORAGE_PATH') ? AMH_STORAGE_PATH : sys_get_temp_dir())
                . DIRECTORY_SEPARATOR . 'rate_limits.json';
        }
        return self::$storageFile;
    }

    /**
     * Check if a rate limit key has exceeded the allowed attempts.
     */
    public static function isThrottled(string $key, int $maxAttempts = 5, int $decaySeconds = 900): bool
    {
        $data = self::readData();
        $cleanKey = self::cleanKey($key);

        if (!isset($data[$cleanKey])) {
            return false;
        }

        $record = $data[$cleanKey];
        $now = time();

        // Check if decay window has expired
        if (($now - $record['first_attempt']) >= $decaySeconds) {
            self::clear($cleanKey);
            return false;
        }

        return $record['attempts'] >= $maxAttempts;
    }

    /**
     * Increment the failed attempt counter for a key.
     *
     * @return int The new attempt count.
     */
    public static function hit(string $key, int $decaySeconds = 900): int
    {
        $data = self::readData();
        $cleanKey = self::cleanKey($key);
        $now = time();

        if (!isset($data[$cleanKey]) || ($now - $data[$cleanKey]['first_attempt']) >= $decaySeconds) {
            $data[$cleanKey] = [
                'attempts'      => 1,
                'first_attempt' => $now,
                'last_attempt'  => $now,
            ];
        } else {
            $data[$cleanKey]['attempts']++;
            $data[$cleanKey]['last_attempt'] = $now;
        }

        self::writeData($data);
        return $data[$cleanKey]['attempts'];
    }

    /**
     * Clear all recorded attempts for a key (called after successful authentication).
     */
    public static function clear(string $key): void
    {
        $data = self::readData();
        $cleanKey = self::cleanKey($key);

        if (isset($data[$cleanKey])) {
            unset($data[$cleanKey]);
            self::writeData($data);
        }
    }

    /**
     * Calculate seconds remaining until a throttled key is released.
     */
    public static function availableIn(string $key, int $decaySeconds = 900): int
    {
        $data = self::readData();
        $cleanKey = self::cleanKey($key);

        if (!isset($data[$cleanKey])) {
            return 0;
        }

        $elapsed = time() - $data[$cleanKey]['first_attempt'];
        $remaining = $decaySeconds - $elapsed;

        return max(0, $remaining);
    }

    private static function cleanKey(string $key): string
    {
        return preg_replace('/[^a-zA-Z0-9_\-.:]/', '', $key);
    }

    private static function readData(): array
    {
        $file = self::getStorageFile();
        if (!file_exists($file)) {
            return [];
        }

        $content = @file_get_contents($file);
        if ($content === false || empty($content)) {
            return [];
        }

        $decoded = @json_decode($content, true);
        return is_array($decoded) ? $decoded : [];
    }

    private static function writeData(array $data): void
    {
        $file = self::getStorageFile();
        $dir = dirname($file);
        if (!is_dir($dir)) {
            @mkdir($dir, 0755, true);
        }

        // Periodically purge records older than 24 hours to prevent unbounded file growth
        $now = time();
        foreach ($data as $k => $item) {
            if (isset($item['first_attempt']) && ($now - $item['first_attempt']) > 86400) {
                unset($data[$k]);
            }
        }

        $payload = json_encode($data, JSON_UNESCAPED_SLASHES);
        @file_put_contents($file, $payload, LOCK_EX);
    }
}
