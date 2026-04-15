<?php

namespace App\Core;

use PDO;

class Router
{
    private PDO $pdo;
    
    private int $rateLimit;
    private int $rateLimitWindow = 60;
    private string $storagePath;
    
    private array $routes = [];
    private array $baseContext = [];

    public function __construct(
        PDO $pdo,
        int $rateLimit = 60,
        string $storagePath = '/tmp/router_rate_limits'
    )
    {
        $this->pdo = $pdo;
        $this->rateLimit   = $rateLimit;
        $this->storagePath = $storagePath;

        if (!is_dir($this->storagePath)) {
            mkdir($this->storagePath, 0755, true);
        }
    }

    public function get(string $path, string $controller, string $method, array $middlewares = []): void
    {
        $this->register('GET', $path, $controller, $method, $middlewares);
    }

    public function post(string $path, string $controller, string $method, array $middlewares = []): void
    {
        $this->register('POST', $path, $controller, $method, $middlewares);
    }

    public function patch(string $path, string $controller, string $method, array $middlewares = []): void
    {
        $this->register('PATCH', $path, $controller, $method, $middlewares);
    }

    public function delete(string $path, string $controller, string $method, array $middlewares = []): void
    {
        $this->register('DELETE', $path, $controller, $method, $middlewares);
    }

    private function register(string $httpMethod, string $path, string $controller, string $method, array $middlewares = []): void
    {
        $this->routes[] = [
            'http_method' => strtoupper($httpMethod),
            'path'        => $path,
            'pattern'     => $this->buildPattern($path),
            'param_names' => $this->extractParamNames($path),
            'controller'  => $controller,
            'method'      => $method,
            'middlewares' => $middlewares,
        ];
    }

    public function addToContext(string $label, $value)
    {
        $this->baseContext[$label] = $value;
    }

    public function dispatch(): void
    {
        if (!$this->checkRateLimit())
            Response::json(['message' => 'Too Many Requests. Limit: ' . $this->rateLimit . ' rpm.'], 429);

        $httpMethod  = $_SERVER['REQUEST_METHOD'] ?? 'GET';
        $requestPath = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
        $requestPath = rtrim($requestPath, '/') ?: '/';

        foreach ($this->routes as $route) {
            if ($route['http_method'] !== strtoupper($httpMethod)) {
                continue;
            }

            if (!preg_match($route['pattern'], $requestPath, $matches)) {
                continue;
            }

            // Build named params from captured groups
            $params = [];
            foreach ($route['param_names'] as $i => $name) {
                $params[$name] = $matches[$i + 1] ?? null;
            }

            $this->callController($route['controller'], $route['method'], $params, $route['middlewares']);
            return;
        }

        HttpResponse::notFound(['message' => 'Route not found.']);
    }


    private function buildPattern(string $path): string
    {
        $pattern = preg_replace('/\{[^}]+\}/', '([^/]+)', $path);
        $pattern = '#^' . rtrim($pattern, '/') . '$#';
        return $pattern;
    }

    private function extractParamNames(string $path): array
    {
        preg_match_all('/\{([^}]+)\}/', $path, $matches);
        return $matches[1];
    }

    private function callController(string $controllerClass, string $method, array $params, array $middlewares): void
    {
        if (!class_exists($controllerClass)) {
            HttpResponse::server(['message' => "Controller class '{$controllerClass}' not found."]);
        }

        $controller = new $controllerClass($this->pdo);

        if (!method_exists($controller, $method)) {
            HttpResponse::server(['message' => "Method '{$method}' not found on '{$controllerClass}'."]);
        }

        $context = [];
        foreach ($middlewares as $middlewareClass) {
            $instance          = new $middlewareClass();
            $label             = $instance::label();
            $context[$label]   = $middlewareClass->run($this->pdo);
        }

        $context = array_merge($this->baseContext, $context);

        $req = new Request($params);
        $controller->$method($req, $context);
    }

    private function checkRateLimit(): bool
    {
        $ip       = $this->getClientIp();
        $file     = $this->storagePath . '/' . md5($ip) . '.json';
        $now      = time();
        $windowStart = $now - $this->rateLimitWindow;

        // Load existing timestamps
        $timestamps = [];
        if (file_exists($file)) {
            $data = json_decode(file_get_contents($file), true);
            if (is_array($data)) {
                // Keep only timestamps within the current window
                $timestamps = array_filter($data, fn($t) => $t > $windowStart);
                $timestamps = array_values($timestamps);
            }
        }

        if (count($timestamps) >= $this->rateLimit) {
            return false; // Limit exceeded
        }

        // Record this request
        $timestamps[] = $now;
        file_put_contents($file, json_encode($timestamps), LOCK_EX);

        return true;
    }

    private function getClientIp(): string
    {
        $headers = [
            'HTTP_CF_CONNECTING_IP',   // Cloudflare
            'HTTP_X_FORWARDED_FOR',
            'HTTP_X_REAL_IP',
            'REMOTE_ADDR',
        ];

        foreach ($headers as $header) {
            if (!empty($_SERVER[$header])) {
                // X-Forwarded-For can be a comma-separated list; take the first
                return trim(explode(',', $_SERVER[$header])[0]);
            }
        }

        return '0.0.0.0';
    }
}