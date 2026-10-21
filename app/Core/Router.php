<?php

declare(strict_types=1);

namespace App\Core;

final class Router
{
    private array $routes = [];

    public function get(string $path, array $action, array $middleware = []): void
    {
        $this->add('GET', $path, $action, $middleware);
    }

    public function post(string $path, array $action, array $middleware = []): void
    {
        $this->add('POST', $path, $action, $middleware);
    }

    public function put(string $path, array $action, array $middleware = []): void
    {
        $this->add('PUT', $path, $action, $middleware);
    }

    public function patch(string $path, array $action, array $middleware = []): void
    {
        $this->add('PATCH', $path, $action, $middleware);
    }

    public function delete(string $path, array $action, array $middleware = []): void
    {
        $this->add('DELETE', $path, $action, $middleware);
    }

    private function add(string $method, string $path, array $action, array $middleware): void
    {
        $pattern = preg_replace('#\{(\w+)\}#', '(?P<$1>[^/]+)', '/' . trim($path, '/'));
        $this->routes[] = compact('method', 'action', 'middleware') + ['pattern' => '#^' . $pattern . '$#'];
    }

    public function dispatch(Request $request): Response
    {
        $allowed = false;

        foreach ($this->routes as $route) {
            if (!preg_match($route['pattern'], $request->path, $matches)) {
                continue;
            }
            if ($route['method'] !== $request->method) {
                $allowed = true;
                continue;
            }

            // API routes authenticate with a bearer token, not the session cookie: CSRF does not apply
            $api = in_array('api', $route['middleware'], true);
            if ($request->method !== 'GET' && !$api && !Csrf::verify($request)) {
                throw new HttpException(419, __('Your session has expired, please reload the page.'));
            }

            foreach ($route['middleware'] as $name) {
                if ($response = Middleware::handle($name, $request)) {
                    return $response;
                }
            }

            [$class, $method] = $route['action'];
            $params = array_filter($matches, 'is_string', ARRAY_FILTER_USE_KEY);

            return (new $class())->{$method}($request, ...$params);
        }

        throw new HttpException($allowed ? 405 : 404);
    }
}
