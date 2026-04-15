<?php

namespace App\Core;

use App\Core\Types\Role;
use Exception;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;

class AuthToken
{
    private static function secret()
    {
        return $_ENV["JWT_SECRET"];
    }

    public static function encode(int $userId, string $role)
    {
        $payload = [
            "iss"  => "cct-etrace",
            "iat"  => time(),
            "exp"  => time() + 3600,
            "sub"  => $userId,
            "role" => $role
        ];
        return JWT::encode($payload, self::secret(), "HS256");
    }

    public static function decode($token)
    {
        try {
            $decoded = JWT::decode($token, new Key(self::secret(), "HS256"));
            return [
                'id' => $decoded->sub,
                'role' => Role::from($decoded->role),
            ];
        } catch (Exception $e) {
            return null;
        }
    }
}