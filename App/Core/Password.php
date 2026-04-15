<?php

namespace App\Core;

class Password
{
    public static function generate($len = 8): string
    {
        return substr(base64_encode(random_bytes(16)), 0, $len);
    }
    
    public static function hash(string $password): bool
    {
        return password_hash($password, PASSWORD_BCRYPT);
    }

    public static function verify(string $password, string $hash): bool
    {
        return password_verify($password, $hash);
    }
}