<?php

namespace App\Seeders;

use App\Services\Service;
use Exception;
use PDO;

class Seeder
{
    public static int $order = 1;
    private array $seeds = [
        
    ];

    public function seed(PDO $pdo)
    {
        $service = new Service($pdo);

        foreach ($this->seeds as $seed)
        {
            try {
                $result = $service->create($seed);
                echo "Created: created message." . PHP_EOL;
            } catch (Exception $e) {
                echo "Failed: failed message." . $e->getMessage() . PHP_EOL;
            }
        }
    }
}