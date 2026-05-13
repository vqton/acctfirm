<?php
namespace Accounting\Interfaces\HTTP;

class Router
{
    private array $routes = [];

    public function addRoute(string $method, string $pattern, $handler): void
    {
        $this->routes[] = [
            'method' => strtoupper($method),
            'pattern' => $pattern,
            'handler' => $handler
        ];
    }

    public function dispatch(): void
    {
        $method = $_SERVER['REQUEST_METHOD'];
        $uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
        $uri = explode('?', $uri)[0];

        if ($uri !== '/' && substr($uri, -1) === '/') {
            $uri = substr($uri, 0, -1);
        }

        foreach ($this->routes as $route) {
            if ($route['method'] !== $method) {
                continue;
            }

            $regex = preg_quote($route['pattern'], '/');
            $regex = preg_replace('/\\:([a-zA-Z0-9_]+)/', '([^/]+)', $regex);
            $regex = '/^' . $regex . '$/';

            if (preg_match($regex, $uri, $matches)) {
                array_shift($matches);
                call_user_func_array($route['handler'], $matches);
                return;
            }
        }

        HttpError::notFound('Route not found: ' . $method . ' ' . $uri);
    }

    public function get(string $pattern, $handler): void { $this->addRoute('GET', $pattern, $handler); }
    public function post(string $pattern, $handler): void { $this->addRoute('POST', $pattern, $handler); }
    public function put(string $pattern, $handler): void { $this->addRoute('PUT', $pattern, $handler); }
    public function delete(string $pattern, $handler): void { $this->addRoute('DELETE', $pattern, $handler); }
}