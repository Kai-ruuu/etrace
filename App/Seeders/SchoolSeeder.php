<?php

namespace App\Seeders;

use App\Services\SchoolService;
use Exception;
use PDO;

class SchoolSeeder
{
    public static int $order = 1;
    private array $seeds = [
        [ 'name' => 'School of Computer Studies' ],
        [ 'name' => 'School of Education' ],
        [ 'name' => 'School of Hospitality and Tourism Management' ],
        [ 'name' => 'School of Business and Management' ],
    ];

    public function seed(PDO $pdo)
    {
        $service = new SchoolService($pdo);

        foreach ($this->seeds as $seed)
        {
            try {
                $school = $service->create($seed);
                $name = $school['name'];
                echo "Created: {$name}." . PHP_EOL;
            } catch (Exception $e) {
                echo "Failed: {$seed['name']}." . $e->getMessage() . PHP_EOL;
            }
        }
    }
}