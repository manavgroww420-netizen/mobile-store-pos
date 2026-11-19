<?php
declare(strict_types=1);

namespace AMH;

/**
 * Lightweight Zero-Dependency Router
 *
 * Dispatches incoming HTTP requests to handlers based on method and URI.
 */
class Router
{
    private static array $routes = [];
    private static $notFoundHandler = null;

    /**
     * Register a GET route.
     */
    public static function get(string $path, callable|array $handler): void
    {
        self::addRoute('GET', $path, $handler);
    }

    /**
     * Register a POST route.
     */
    public static function post(string $path, callable|array $handler): void
    {
        self::addRoute('POST', $path, $handler);
    }

    /**
     * Register a route for any method.
     */
    public static function addRoute(string $method, string $path, callable|array $handler): void
    {
        self::$routes[] = [
            'method'  => strtoupper($method),
            'path'    => '/' . trim($path, '/'),
            'handler' => $handler,
        ];
    }

    /**
     * Get all registered routes.
     */
    public static function getRoutes(): array
    {
        return self::$routes;
    }

    /**
     * Check if a route matching method and path exists.
     */
    public static function matchRoute(string $method, string $path): ?array
    {
        $m = strtoupper($method);
        if ($m === 'HEAD') {
            $m = 'GET';
        }
        $normPath = '/' . trim(parse_url($path, PHP_URL_PATH) ?: '/', '/');
        if ($normPath === '//') $normPath = '/';

        foreach (self::$routes as $route) {
            if ($route['method'] !== $m && $route['method'] !== 'ANY') {
                continue;
            }
            $pattern = preg_replace('#\{([a-zA-Z0-9_]+)\}#', '(?P<$1>[^/]+)', $route['path']);
            if (preg_match('#^' . $pattern . '$#', $normPath)) {
                return $route;
            }
        }
        return null;
    }

    /**
     * Set custom 404 handler.
     */
    public static function setNotFound(callable $handler): void
    {
        self::$notFoundHandler = $handler;
    }

    /**
     * Resolve and dispatch current request.
     */
    public static function dispatch(?string $requestMethod = null, ?string $requestUri = null): void
    {
        $method = strtoupper($requestMethod ?? $_SERVER['REQUEST_METHOD'] ?? 'GET');
        if ($method === 'HEAD') {
            $method = 'GET';
        }
        $uri = $requestUri ?? $_SERVER['REQUEST_URI'] ?? '/';

        // Strip query string
        $path = parse_url($uri, PHP_URL_PATH) ?: '/';

        // Normalize leading and trailing slashes
        $path = '/' . trim($path, '/');
        if ($path === '//') {
            $path = '/';
        }

        foreach (self::$routes as $route) {
            if ($route['method'] !== $method && $route['method'] !== 'ANY') {
                continue;
            }

            // Convert route pattern with {param} into regex
            $pattern = preg_replace('#\{([a-zA-Z0-9_]+)\}#', '(?P<$1>[^/]+)', $route['path']);
            $pattern = '#^' . $pattern . '$#';

            if (preg_match($pattern, $path, $matches)) {
                $params = array_filter($matches, 'is_string', ARRAY_FILTER_USE_KEY);

                // CSRF verification for state-changing HTTP methods
                if (in_array($method, ['POST', 'PUT', 'DELETE', 'PATCH'], true)) {
                    $token = $_POST['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? null;
                    if (!Security::validateCsrfToken($token)) {
                        http_response_code(419);
                        View::flash('danger', 'Security token expired or invalid. Please refresh the page and try again.');
                        Security::safeRedirect($_SERVER['HTTP_REFERER'] ?? '/');
                        return;
                    }
                }

                $handler = $route['handler'];
                if (is_callable($handler)) {
                    call_user_func($handler, $params);
                    return;
                }

                if (is_array($handler) && count($handler) === 2) {
                    [$class, $action] = $handler;
                    if (is_string($class)) {
                        $instance = new $class();
                        $instance->$action($params);
                        return;
                    }
                }
            }
        }

        // No route matched -> 404
        if (self::$notFoundHandler !== null) {
            call_user_func(self::$notFoundHandler);
            return;
        }

        http_response_code(404);
        View::render('home/404', [
            'pageTitle' => '404 Page Not Found',
            'path'      => $path,
        ], 'main');
    }
}
