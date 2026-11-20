<?php
declare(strict_types=1);

namespace AMH;

use Throwable;
use ErrorException;

/**
 * Centralized Error & Exception Handler
 *
 * Guarantees that no internal credentials, stack traces, or SQL queries
 * leak to the browser in production while logging full diagnostics server-side.
 */
class ErrorHandler
{
    private static bool $debug = true;
    private static string $logFile = '';

    /**
     * Register global PHP error, exception, and fatal shutdown handlers.
     */
    public static function register(bool $debug = true, string $logFile = ''): void
    {
        self::$debug = $debug;
        self::$logFile = $logFile ?: (AMH_LOGS_PATH . DIRECTORY_SEPARATOR . 'error.log');

        // Set error reporting
        error_reporting(E_ALL);

        if (self::$debug) {
            ini_set('display_errors', '1');
            ini_set('display_startup_errors', '1');
        } else {
            ini_set('display_errors', '0');
            ini_set('display_startup_errors', '0');
        }

        // Convert standard PHP errors into ErrorException
        set_error_handler([self::class, 'handleError']);

        // Handle uncaught exceptions
        set_exception_handler([self::class, 'handleException']);

        // Handle fatal errors on shutdown
        register_shutdown_function([self::class, 'handleShutdown']);
    }

    /**
     * Convert PHP runtime warnings/notices to ErrorException.
     */
    public static function handleError(int $severity, string $message, string $file, int $line): bool
    {
        // Suppress and ignore deprecation notices
        if ($severity === E_DEPRECATED || $severity === E_USER_DEPRECATED) {
            return true;
        }

        // Respect error suppression operator (@)
        if (!(error_reporting() & $severity)) {
            return false;
        }

        throw new ErrorException($message, 0, $severity, $file, $line);
    }

    /**
     * Uncaught exception handler.
     */
    public static function handleException(Throwable $e): void
    {
        $referenceId = 'ERR-' . strtoupper(substr(bin2hex(random_bytes(4)), 0, 8));

        // Log full technical details to server log
        self::logToFile($e, $referenceId);

        // Clear any half-sent output buffer if headers have not been sent
        if (!headers_sent() && ob_get_level() > 0) {
            while (ob_get_level() > 0) {
                ob_end_clean();
            }
        }

        if (!headers_sent()) {
            http_response_code(500);
            header('Content-Type: text/html; charset=UTF-8');
        }

        // Display user-friendly or diagnostic screen
        self::renderErrorResponse($e, $referenceId);
        exit(1);
    }

    /**
     * Handle fatal errors on script termination.
     */
    public static function handleShutdown(): void
    {
        $error = error_get_last();
        if ($error !== null && in_array($error['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR], true)) {
            $e = new ErrorException(
                $error['message'],
                0,
                $error['type'],
                $error['file'],
                $error['line']
            );
            self::handleException($e);
        }
    }

    /**
     * Log technical details safely to disk.
     */
    private static function logToFile(Throwable $e, string $referenceId): void
    {
        $logDir = dirname(self::$logFile);
        if (!is_dir($logDir)) {
            @mkdir($logDir, 0755, true);
        }

        $date = date('Y-m-d H:i:s');
        $class = get_class($e);
        $message = $e->getMessage();
        $file = $e->getFile();
        $line = $e->getLine();
        $trace = $e->getTraceAsString();
        $ip = $_SERVER['REMOTE_ADDR'] ?? 'CLI';
        $uri = $_SERVER['REQUEST_URI'] ?? 'CLI';

        $entry = sprintf(
            "[%s] [%s] [%s] %s: \"%s\" in %s:%d (URI: %s)\nStack trace:\n%s\n--------------------------------------------------------------------\n",
            $date,
            $referenceId,
            $ip,
            $class,
            $message,
            $file,
            $line,
            $uri,
            $trace
        );

        @file_put_contents(self::$logFile, $entry, FILE_APPEND | LOCK_EX);
    }

    /**
     * Output appropriate HTML response depending on environment.
     */
    private static function renderErrorResponse(Throwable $e, string $referenceId): void
    {
        $isAjax = (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest')
               || (isset($_SERVER['HTTP_ACCEPT']) && strpos($_SERVER['HTTP_ACCEPT'], 'application/json') !== false);

        if ($isAjax) {
            header('Content-Type: application/json; charset=UTF-8');
            $payload = [
                'success'      => false,
                'error'        => self::$debug ? $e->getMessage() : 'An internal system error occurred. Please contact the administrator.',
                'reference_id' => $referenceId,
            ];
            echo json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
            return;
        }

        // Check if custom error view exists
        $viewPath = AMH_VIEWS_PATH . DIRECTORY_SEPARATOR . 'layouts' . DIRECTORY_SEPARATOR . 'error.php';
        if (file_exists($viewPath)) {
            $errorTitle = 'System Error';
            $errorMessage = self::$debug ? $e->getMessage() : 'A system error occurred while processing your request. Please quote the reference ID below.';
            $debugDetails = self::$debug ? [
                'exception' => get_class($e),
                'file'      => $e->getFile(),
                'line'      => $e->getLine(),
                'trace'     => $e->getTraceAsString(),
            ] : null;

            include $viewPath;
            return;
        }

        // Clean standalone fallback HTML
        echo '<!DOCTYPE html>';
        echo '<html lang="en"><head><meta charset="UTF-8"><title>System Error — Mobile Store Terminal</title>';
        echo '<style>body{font-family:-apple-system,BlinkMacSystemFont,"Segoe UI",Roboto,sans-serif;background:#f8fafc;color:#0f172a;display:flex;align-items:center;justify-content:center;min-height:100vh;margin:0;}';
        echo '.box{background:#fff;border:1px solid #e2e8f0;border-radius:8px;padding:32px;max-width:540px;width:90%;box-shadow:0 4px 6px -1px rgba(0,0,0,0.05);}';
        echo 'h2{color:#b91c1c;margin-top:0;}p{color:#475569;line-height:1.5;}code{background:#f1f5f9;padding:2px 6px;border-radius:4px;font-family:monospace;}';
        echo '.ref{margin-top:16px;font-size:0.875rem;color:#64748b;border-top:1px solid #f1f5f9;padding-top:12px;}</style></head><body>';
        echo '<div class="box"><h2>System Error</h2>';
        echo '<p>' . htmlspecialchars(self::$debug ? $e->getMessage() : 'An unexpected error occurred while processing this request.', ENT_QUOTES, 'UTF-8') . '</p>';
        echo '<div class="ref">Error Reference: <code>' . htmlspecialchars($referenceId, ENT_QUOTES, 'UTF-8') . '</code></div>';
        echo '</div></body></html>';
    }
}
