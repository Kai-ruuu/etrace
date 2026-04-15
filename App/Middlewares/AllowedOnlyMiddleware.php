<?php

namespace App\Middlewares;

use App\Core\AuthToken;
use App\Core\HttpResponse;
use App\Core\Middleware;
use App\Services\UserService;

class AllowedOnlyMiddleware implements Middleware
{
    private array $allowedRoles;

    public function __construct(array $allowedRoles = [])
    {
        $this->allowedRoles = $allowedRoles;
    }
    
    public static function make(mixed ...$args): static
    {
        return new self($args[0] ?? []);
    }
    
    public static function label(): string
    {
        return "user";
    }
    
    public function run(mixed ...$args): array
    {
        $pdo = $args[0];
        
        // parse token
        $token = $_COOKIE["token"] ?? null;

        if (!$token) {
            HttpResponse::bad(["message" => "Not authenticated. Please login."]);
        }
            
        $result = AuthToken::decode($token);
        
        if (!$result) {
            HttpResponse::bad(["message" => "Invalid session."]);
        }

        // get current user
        $id = $result["id"];
        $role = $result["role"];

        if (!in_array($role, $this->allowedRoles)) {
            HttpResponse::unauthorized(["message" => "You are not authorized to access this resource."]);
        }

        $userService = new UserService($pdo);
        $user = $userService->findById($id);

        if ($user === null) {
            HttpResponse::bad(['message' => 'Bad request.']);
        }

        return $user;
    }
}