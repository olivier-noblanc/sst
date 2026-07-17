<?php

namespace App\Router;

class Router
{
    /** @var list<array{path: string, name: string, methods: list<string>, handler: callable}> */
    private array $routes = [];
    /** @var array<string, string> */
    private array $postHandlers = [];
    /** @var array<string, list<callable>> */
    private array $postMiddlewares = [];
    /** @var array<string, string> */
    private array $pageTitles = [];

    // ═══════════════════════════════════════════════════════════════════════════════
    // Route registration
    // ═══════════════════════════════════════════════════════════════════════════════

    /** @param list<string> $methods */
    public function addRoute(string $path, string $name, array $methods, callable $handler): void
    {
        $this->routes[] = compact('path', 'name', 'methods', 'handler');
    }

    public function addPostHandler(string $name, string $handlerFile): void
    {
        $this->postHandlers[$name] = $handlerFile;
    }

    /** @param list<callable> $middlewares */
    public function setPostMiddleware(string $name, array $middlewares): void
    {
        $this->postMiddlewares[$name] = $middlewares;
    }

    public function setPageTitle(string $name, string $title): void
    {
        $this->pageTitles[$name] = $title;
    }

    // ═══════════════════════════════════════════════════════════════════════════════
    // Matching
    // ═══════════════════════════════════════════════════════════════════════════════

    /** @return array{handler: callable, params: array<int|string, string>, name: string}|null */
    public function match(string $method, string $uri): ?array
    {
        foreach ($this->routes as $route) {
            if (!in_array($method, $route['methods'])) {
                continue;
            }
            $pattern = preg_replace('#\{(\w+)\}#', '(?P<$1>[^/]+)', (string) $route['path']);
            if (preg_match('#^' . $pattern . '$#', $uri, $matches)) {
                return ['handler' => $route['handler'], 'params' => $matches, 'name' => $route['name']];
            }
        }
        return null;
    }

    // ═══════════════════════════════════════════════════════════════════════════════
    // Dispatch
    // ═══════════════════════════════════════════════════════════════════════════════

    /**
     * Dispatch a POST request to the appropriate handler file.
     * Applies configured middlewares before the handler.
     */
    public function dispatchPost(string $page): bool
    {
        if (!isset($this->postHandlers[$page])) {
            return false;
        }

        $middlewares = $this->postMiddlewares[$page] ?? [];
        $handlerFile = $this->postHandlers[$page];

        $handler = function () use ($handlerFile) {
            require $handlerFile;
        };

        // Apply middlewares in reverse order (innermost first)
        foreach (array_reverse($middlewares) as $middleware) {
            $next = $handler;
            $handler = function () use ($middleware, $next) {
                $middleware($next);
            };
        }

        $handler();
        return true;
    }

    /**
     * Dispatch a GET request to the appropriate page renderer.
     */
    public function dispatchGet(string $page): void
    {
        $match = $this->match('GET', $page);
        if ($match) {
            ($match['handler'])();
        } else {
            redirect(url('home'));
        }
    }

    // ═══════════════════════════════════════════════════════════════════════════════
    // Page metadata
    // ═══════════════════════════════════════════════════════════════════════════════

    public function validatePage(string $page): string
    {
        return in_array($page, $this->getValidPages(), true) ? $page : 'home';
    }

    public function getPageTitle(string $page): string
    {
        return $this->pageTitles[$page] ?? 'Accueil';
    }

    /** @return list<string> */
    public function getValidPages(): array
    {
        return array_keys($this->pageTitles) + array_keys($this->postHandlers);
    }

    /** @return array<string, string> */
    public function getHandlerMap(): array
    {
        return $this->postHandlers;
    }

    /** @return list<array{path: string, name: string, methods: list<string>, handler: callable}> */
    public function getRoutes(): array
    {
        return $this->routes;
    }
}
