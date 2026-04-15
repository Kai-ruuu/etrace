<?php

namespace App\Core;

class AuthCookie
{
    public static function set($value)
    {
        setcookie("token", $value, [
            "expires"  => time() + 3600,
            "path"     => "/",
            "secure"   => ($_ENV["APP_ENV"] ?? "development") !== "development",
            "httponly" => true,
            "samesite" => "Lax"
        ]);
    }

    public static function unset()
    {
        setcookie("token", "", [
            "expires"  => time() - 3600,
            "path"     => "/",
            "secure"   => ($_ENV["APP_ENV"] ?? "development") !== "development",
            "httponly" => true,
            "samesite" => "Lax"
        ]);
    }
    
    public static function get($key) {
        return $_COOKIE[$key] ?? null;
    }
}