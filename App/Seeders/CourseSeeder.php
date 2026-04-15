<?php

namespace App\Seeders;

use App\Services\CourseService;
use Exception;
use PDO;

class CourseSeeder
{
    public static int $order = 2;
    private array $seeds = [
        [
            'school_id' => 1,
            'name' => 'Bachelor of Science in Computer Science',
            'code' => 'BSCS'
        ],
        [
            'school_id' => 1,
            'name' => 'Bachelor of Science in Information Technology',
            'code' => 'BSIT'
        ],
        [
            'school_id' => 2,
            'name' => 'Bachelor of Secondary Education Major in English',
            'code' => 'BSE-E'
        ],
        [
            'school_id' => 2,
            'name' => 'Bachelor of Secondary Education Major in Filipino',
            'code' => 'BSE-F'
        ],
        [
            'school_id' => 2,
            'name' => 'Bachelor of Secondary Education Major in Mathematics',
            'code' => 'BSE-M'
        ],
        [
            'school_id' => 2,
            'name' => 'Bachelor of Secondary Education Major in Science',
            'code' => 'BSE-Sc'
        ],
        [
            'school_id' => 2,
            'name' => 'Bachelor of Secondary Education Major in Social Studies',
            'code' => 'BSE-Ss'
        ],
    ];

    public function seed(PDO $pdo)
    {
        $service = new CourseService($pdo);

        foreach ($this->seeds as $seed)
        {
            try {
                $school = $service->create($seed);
                $code = $school['code'];
                echo "Created: {$code}." . PHP_EOL;
            } catch (Exception $e) {
                echo "Failed: {$seed['code']}." . $e->getMessage() . PHP_EOL;
            }
        }
    }
}