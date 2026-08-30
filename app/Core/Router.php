<?php
declare(strict_types=1);

namespace App\Core;

/**
 * Routeur HTTP
 * Gère les routes et la correspondance avec les contrôleurs
 */
class Router
{
    /** @var array<string, array> Routes enregistrées */
    private array $routes = [];

    /** @var string Préfixe de groupe */
    private string $groupPrefix = '';

    /** @var array Middleware de groupe */
    private array $groupMiddleware = [];

    /**
     * Enregistre une route GET
     */
    public function get(string $path, array|callable $handler, array $middleware = []): self
    {
        return $this->addRoute('GET', $path, $handler, $middleware);
    }

    /**
     * Enregistre une route POST
     */
    public function post(string $path, array|callable $handler, array $middleware = []): self
    {
        return $this->addRoute('POST', $path, $handler, $middleware);
    }

    /**
     * Enregistre une route PUT
     */
    public function put(string $path, array|callable $handler, array $middleware = []): self
    {
        return $this->addRoute('PUT', $path, $handler, $middleware);
    }

    /**
     * Enregistre une route DELETE
     */
    public function delete(string $path, array|callable $handler, array $middleware = []): self
    {
        return $this->addRoute('DELETE', $path, $handler, $middleware);
    }

    /**
     * Crée un groupe de routes
     */
    public function group(string $prefix, callable $callback, array $middleware = []): void
    {
        $previousPrefix = $this->groupPrefix;
        $previousMiddleware = $this->groupMiddleware;

        $this->groupPrefix .= $prefix;
        $this->groupMiddleware = array_merge($this->groupMiddleware, $middleware);

        $callback($this);

        $this->groupPrefix = $previousPrefix;
        $this->groupMiddleware = $previousMiddleware;
    }

    /**
     * Ajoute une route
     */
    private function addRoute(string $method, string $path, array|callable $handler, array $middleware = []): self
    {
        $fullPath = $this->groupPrefix . $path;
        $fullPath = $fullPath === '' ? '/' : $fullPath;

        $this->routes[] = [
            'method' => $method,
            'path' => $fullPath,
            'handler' => $handler,
            'middleware' => array_merge($this->groupMiddleware, $middleware),
        ];

        return $this;
    }

    /**
     * Résout la route pour la requête courante
     */
    public function resolve(Request $request): ?array
    {
        $method = $request->getMethod();
        $uri = $request->getUri();

        // HEAD équivaut à GET (sans corps) : certains validateurs et
        // crawlers externes sondent les URL en HEAD, ils doivent recevoir
        // le même statut qu'un GET (200 et non 404).
        if ($method === 'HEAD') {
            $method = 'GET';
        }

        // Support des méthodes PUT/DELETE via _method
        if ($method === 'POST') {
            $override = $request->post('_method');
            if ($override && in_array(strtoupper($override), ['PUT', 'DELETE'])) {
                $method = strtoupper($override);
            }
        }

        foreach ($this->routes as $route) {
            if ($route['method'] !== $method) {
                continue;
            }

            $params = $this->matchRoute($route['path'], $uri);
            if ($params !== null) {
                foreach ($params as $key => $value) {
                    $request->setParam($key, $value);
                }

                return [
                    'handler' => $route['handler'],
                    'middleware' => $route['middleware'],
                    'params' => $params,
                ];
            }
        }

        return null;
    }

    /**
     * Vérifie si un URI correspond à un pattern de route
     */
    private function matchRoute(string $routePath, string $uri): ?array
    {
        // Route exacte
        if ($routePath === $uri) {
            return [];
        }

        // Convertit le pattern en regex
        $pattern = preg_replace('/\{([a-zA-Z_]+)\}/', '(?P<$1>[^/]+)', $routePath);
        $pattern = '#^' . $pattern . '$#';

        if (preg_match($pattern, $uri, $matches)) {
            return array_filter($matches, 'is_string', ARRAY_FILTER_USE_KEY);
        }

        return null;
    }

    /**
     * Génère une URL pour une route
     */
    public function url(string $path, array $params = []): string
    {
        foreach ($params as $key => $value) {
            $path = str_replace('{' . $key . '}', (string) $value, $path);
        }
        return $path;
    }

    /**
     * Retourne toutes les routes
     */
    public function getRoutes(): array
    {
        return $this->routes;
    }
}
