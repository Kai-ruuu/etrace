<?php

namespace App\Core;

use App\Config\UploadsConfig;

class App
{
    private Router $router;
    private array $allowedOrigins;
    private string $allowCredentials;
    private string $allowedMethods;
    private string $allowedHeaders;
    
    public function __construct(
        Router $router,
        array $allowedOrigins,
        bool $allowCredentials = true,
        array $allowedMethods = ['GET', 'POST', 'PATCH', 'DELETE', 'OPTIONS'],
        array $allowedHeaders = ['Content-Type'],
    ) {
        $this->router = $router;
        $this->allowedOrigins = $allowedOrigins;
        $this->allowCredentials = $allowCredentials ? "true" : "false";
        $this->allowedMethods = implode(',', $allowedMethods);
        $this->allowedHeaders = implode(',', $allowedHeaders);
    }

    private function setHeaders()
    {
        $origin = $_SERVER["HTTP_ORIGIN"] ?? '';

        if (in_array($origin, $this->allowedOrigins)) {
            header("Access-Control-Allow-Origin: {$origin}");
        }
        header("Access-Control-Allow-Credentials: {$this->allowCredentials}");
        header("Access-Control-Allow-Methods: {$this->allowedMethods}");
        header("Access-Control-Allow-Headers: {$this->allowedHeaders}");
    }

    public function run()
    {
        $this->setHeaders();

        if ($_SERVER["REQUEST_METHOD"] === "OPTIONS") {
            http_response_code(200);
            exit();
        }

        $path = parse_url($_SERVER["REQUEST_URI"], PHP_URL_PATH);

        $finalPath = realpath(dirname(__DIR__, 2) . $path);

        if ($finalPath && is_file($finalPath)) {
            $mime = mime_content_type($finalPath);
            header('Content-Type: ' . $mime);
            header('Content-Length: ' . filesize($finalPath));
            readfile($finalPath);
            exit();
        }

        $this->router->dispatch();
    }
}