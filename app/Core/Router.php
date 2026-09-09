<?php

namespace App\Core;

class Router
{
    private array $routes = [];

    public function get(string $path, array $handler): void
    {
        $this->add('GET', $path, $handler);
    }

    public function post(string $path, array $handler): void
    {
        $this->add('POST', $path, $handler);
    }

    private function add(string $method, string $path, array $handler): void
    {
        $this->routes[] = [
            'method' => $method,
            'pattern' => $this->toPattern($path),
            'handler' => $handler,
        ];
    }

    private function toPattern(string $path): string
    {
        $pattern = preg_replace('#\{[a-zA-Z_]+\}#', '([^/]+)', $path);
        return '#^' . $pattern . '$#';
    }

    public function dispatch(string $method, string $uri): void
    {
        $path = parse_url($uri, PHP_URL_PATH) ?: '/';
        $path = rtrim($path, '/');
        if ($path === '') {
            $path = '/';
        }

        foreach ($this->routes as $route) {
            if ($route['method'] !== $method) {
                continue;
            }

            if (preg_match($route['pattern'], $path, $matches)) {
                array_shift($matches);

                // Permissao granular por tela (so Gestor/Vendedor, ver App\Core\ScreenPermissions)
                // -- checagem central aqui pra nao precisar repetir em cada controller individual.
                $currentUser = Auth::user();
                $screenKey = $currentUser ? ScreenPermissions::screenForPath($path) : null;
                if ($screenKey !== null && !ScreenPermissions::can($currentUser, $screenKey)) {
                    http_response_code(403);
                    require BASE_PATH . '/app/Views/errors/403.php';
                    return;
                }

                [$class, $action] = $route['handler'];
                $controller = new $class();
                $controller->$action(...$matches);
                return;
            }
        }

        http_response_code(404);
        require BASE_PATH . '/app/Views/errors/404.php';
    }

    public static function redirect(string $path): void
    {
        header('Location: ' . $path);
        exit;
    }
}
