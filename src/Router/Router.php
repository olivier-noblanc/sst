<?php
namespace App\Router;

class Router
{
    private array $routes = [];

    public function addRoute(string $path, string $name, array $methods, callable $handler): void
    {
        $this->routes[] = compact('path', 'name', 'methods', 'handler');
    }

    public function match(string $method, string $uri): ?array
    {
        foreach ($this->routes as $route) {
            if (!in_array($method, $route['methods'])) continue;
            $pattern = preg_replace('#\{(\w+)\}#', '(?P<$1>[^/]+)', $route['path']);
            if (preg_match('#^' . $pattern . '$#', $uri, $matches)) {
                return ['handler' => $route['handler'], 'params' => $matches, 'name' => $route['name']];
            }
        }
        return null;
    }

    public function getRoutes(): array { return $this->routes; }
}
