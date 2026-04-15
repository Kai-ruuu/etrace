<?php

namespace App\Core;

class Request
{
    public array $body;
    public array $params;
    public array $queries;
    
    public function __construct(array $params = [])
    {
        $this->body = self::parseBody();
        $this->params = $params;
        $this->queries = self::parseQueries();
    }
    
    public static function parseBody(): array
    {
        $contentType = $_SERVER['CONTENT_TYPE'] ?? '';
        
        if (str_contains($contentType, 'application/json')) {
            $raw = file_get_contents('php://input');
            return json_decode($raw, true) ?? [];
        }

        return $_POST ?? [];
    }

    public static function parseQueries(): array
    {
        return $_GET ?? [];
    }

    public function fromQuery(string $key, mixed $default = null): ?string
    {
        return $this->queries[$key] ?? $default;
    }

    public function fromBody(string $key): ?string
    {
        return $this->body[$key] ?? null;
    }

    public function fromParams(string $key): ?string
    {
        return $this->params[$key] ?? null;
    }
}