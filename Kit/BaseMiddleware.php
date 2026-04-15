<?php

namespace App\Middlewares;

use App\Core\Middleware;

class BaseMiddleware implements Middleware
{
    public function __construct()
    {
        
    }
    
    public static function make(mixed ...$args): static
    {
        return new self();
    }
    
    public static function label(): string
    {
        return "middleware_label";
    }
    
    public function run(mixed ...$args): mixed
    {
        return null;
    }
}