<?php

namespace App\Seeders;

use App\Services\OccupationService;
use Exception;
use PDO;

class OccupationSeeder
{
    public static int $order = 1;
    private array $seeds = [
        [ 'name' => 'Frontend Web Developer' ],
        [ 'name' => 'Backend Web Developer' ],
        [ 'name' => 'Fullstack Web Developer' ],
        [ 'name' => 'UI/UX Designer' ],
        [ 'name' => 'Game Developer' ],
        [ 'name' => 'Software Engineer' ],
        [ 'name' => 'AI Engineer' ],
        [ 'name' => 'Data Analyst' ],
    ];

    public function seed(PDO $pdo)
    {
        $service = new OccupationService($pdo);

        foreach ($this->seeds as $seed)
        {
            try {
                $result = $service->create($seed);
                $name = $result['name'];
                echo "Created: {$name}." . PHP_EOL;
            } catch (Exception $e) {
                echo "Failed: {$seed['name']}." . $e->getMessage() . PHP_EOL;
            }
        }
    }
}