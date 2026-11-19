<?php
declare(strict_types=1);

namespace AMH;

/**
 * Lightweight View & Layout Template Engine
 *
 * Provides safe HTML rendering, reusable layout wrapping,
 * partial inclusion, and session flash message management.
 */
class View
{
    /**
     * Render a view file wrapped within an application layout.
     *
     * @param string $view Name of view relative to views/ directory (e.g., 'home/index')
     * @param array $data Variables to pass to the view and layout
     * @param string $layout Name of layout relative to views/layouts/ (e.g., 'main')
     */
    public static function render(string $view, array $data = [], string $layout = 'main'): void
    {
        $viewFile = AMH_VIEWS_PATH . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $view) . '.php';

        if (!file_exists($viewFile)) {
            throw new \RuntimeException("View file not found: {$view}");
        }

        // Global default data
        $config = require AMH_CONFIG_PATH . DIRECTORY_SEPARATOR . 'config.php';
        $appConfig = $config['app'] ?? [];

        $data = array_merge([
            'appConfig'   => $appConfig,
            'appName'     => $appConfig['name'] ?? 'Mobile Hub POS',
            'appTagline'  => $appConfig['tagline'] ?? 'A Complete Mobile & Electronics Store',
            'appAddress'  => $appConfig['address'] ?? 'Commercial Market, Main Road',
            'appPhone'    => $appConfig['phone'] ?? '9800000000',
            'appEmail'    => $appConfig['email'] ?? 'contact@mobilestore.local',
            'currentUser' => Auth::user(),
            'csrfToken'   => Security::getCsrfToken(),
            'flashes'     => self::getFlashes(),
        ], $data);

        // Extract variables for view scope
        extract($data, EXTR_SKIP);

        // Capture view content
        ob_start();
        include $viewFile;
        $content = ob_get_clean();

        // If no layout requested, output view directly
        if ($layout === '') {
            echo $content;
            return;
        }

        // Render layout wrapping the content
        $layoutFile = AMH_VIEWS_PATH . DIRECTORY_SEPARATOR . 'layouts' . DIRECTORY_SEPARATOR . $layout . '.php';
        if (!file_exists($layoutFile)) {
            throw new \RuntimeException("Layout file not found: {$layout}");
        }

        include $layoutFile;
    }

    /**
     * Render a partial view template (e.g., header, sidebar, table row).
     */
    public static function partial(string $partial, array $data = []): string
    {
        $partialFile = AMH_VIEWS_PATH . DIRECTORY_SEPARATOR . 'partials' . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $partial) . '.php';

        if (!file_exists($partialFile)) {
            return '';
        }

        extract($data, EXTR_SKIP);

        ob_start();
        include $partialFile;
        return ob_get_clean() ?: '';
    }

    /**
     * Queue a session flash notification (success, danger, warning, info).
     */
    public static function flash(string $type, string $message): void
    {
        Auth::startSession();
        if (!isset($_SESSION['_flashes'])) {
            $_SESSION['_flashes'] = [];
        }
        $_SESSION['_flashes'][] = [
            'type'    => in_array($type, ['success', 'danger', 'warning', 'info']) ? $type : 'info',
            'message' => $message,
        ];
    }

    /**
     * Retrieve and clear all queued flash messages.
     */
    public static function getFlashes(): array
    {
        Auth::startSession();
        $flashes = $_SESSION['_flashes'] ?? [];
        unset($_SESSION['_flashes']);
        return $flashes;
    }
}
