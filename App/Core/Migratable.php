<?php

namespace App\Core;

use PDO;

interface Migratable
{
    public static function table(): string;

    public static function migrate(PDO $pdo): void;
}