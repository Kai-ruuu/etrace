<?php

namespace App\Controllers;

use App\Core\HttpResponse;
use App\Core\Request;
use App\Core\Validator;
use App\Models\User;
use App\Services\AuthService;
use App\Services\UserService;
use PDO;

class AuthController
{
    private PDO $pdo;
    private AuthService $authService;
    private UserService $userService;
    
    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
        $this->authService = new AuthService();
        $this->userService = new UserService($this->pdo);
    }

    public function authenticate(Request $req, array $cont): void
    {
        $email = Validator::requiredEmail('email', $req->fromBody('email'));
        $password = Validator::requiredString('password', $req->fromBody('password'), 8, 65);
        $user = User::findByEmail($this->pdo, $email);

        if (!$user)
            HttpResponse::bad(['message' => 'Email or password must be incorrect.']);

        if (!$user->emailVerified)
            HttpResponse::forbidden(['message' => 'Unable to login. You need to verify your email first.']);
        
        if (!$user->enabled)
            HttpResponse::forbidden(['message' => 'Unable to login. Your account is currently disabled.']);
        
        $result = $this->authService->authenticate($user, $password);
        
        if ($result !== true)
            HttpResponse::bad(['message' => $result]);

        HttpResponse::ok($this->userService->findById($user->id));
    }

    public function me(Request $req, array $cont): void
    {
        HttpResponse::ok($cont["user"]);
    }
    
    public function logout(Request $req, array $cont): void
    {
        $result = $this->authService->logout();

        if ($result !== true)
            HttpResponse::bad(['message' => $result]);

        HttpResponse::ok(['message' => 'Logged out successfully!']);
    }
}