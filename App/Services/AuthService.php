<?php

namespace App\Services;

use App\Core\AuthToken;
use App\Core\Password;
use App\Models\User;
use App\Core\AuthCookie;
use Exception;

class AuthService
{
    public function authenticate(User $user, string $password): mixed
    {
        try {
            if (!Password::verify($password, $user->passwordHash)) {
                return "Email or password must be incorrect.";
            }
            
            $authToken = AuthToken::encode($user->id, $user->role->value);
            AuthCookie::set($authToken);
            return true;
        } catch (Exception $e) {
            return "Unable to login.";
        }
    }

    public function logout(): mixed
    {
        try {
            AuthCookie::unset();
            return true;
        } catch (Exception $e) {
            return "Unable to logout.";
        }
    }
}