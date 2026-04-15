<?php

namespace App\Core;

use PDO;
use PDOException;

class Database
{
    private string $host;
    private string $user;
    private string $name;
    private string $pass;

    public function __construct(array $config)
    {
        $this->host = $config["host"];
        $this->user = $config["user"];
        $this->name = $config["name"];
        $this->pass = $config["pass"];
    }

    public static function forProd()
    {
        $hostname = $_ENV["DB_HOSTNAME"] ?? null;
        $user     = $_ENV["DB_USER"] ?? null;
        $name     = $_ENV["DB_NAME"] ?? null;
        $password = $_ENV["DB_PASSWORD"] ?? null;

        if (!$hostname) {
            error_log("DB_HOSTNAME is not set.");
            die();
        }
        if (!$user) {
            error_log("DB_USER is not set.");
            die();
        }
        if (!$name) {
            error_log("DB_NAME is not set.");
            die();
        }
        if (empty($password)) {
            error_log("DB_PASSWORD is not set.");
            die();
        }

        $database = new self([
            "host" => $hostname,
            "user" => $user,
            "name" => $name,
            "pass" => $password,
        ]);
        return $database;
    }

    public static function forDev(): self
    {
        $database = new self([
            "host" => "localhost",
            "user" => "root",
            "name" => "etrace_final",
            "pass" => "",
        ]);
        return $database;
    }

    public function connect(): PDO
    {
        try {
            $dsn = "mysql:host={$this->host};dbname={$this->name};charset=utf8mb4";
            $options = [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
            ];
            return new PDO($dsn, $this->user, $this->pass, $options);
        } catch (PDOException $e) {
            error_log("Database connection failed: " . $e->getMessage());
            die();
        }
    }
}