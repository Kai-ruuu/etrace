<?php

namespace App\Seeders;

use App\Core\Types\Role;
use App\Services\UserService;
use Exception;
use PDO;

class UserSeeder
{
    public static int $order = 3;
    private array $seeds = [
        [
            "role" => Role::SYSTEM_ADMIN->value,
            "email" => "sysad2@email.com",
            "password" => "hahahaha",
            "first_name" => "System2",
            "middle_name" => null,
            "last_name" => "Administrator",
            "enabled" => true,
        ],
        [
            "role" => Role::DEAN->value,
            "email" => "dean.scs@email.com",
            "password" => "hahahaha",
            "school_id" => 1,
            "first_name" => "Dean",
            "middle_name" => null,
            "last_name" => "SCS",
            "enabled" => true,
        ],
        [
            "role" => Role::DEAN->value,
            "email" => "dean.educ@email.com",
            "password" => "hahahaha",
            "school_id" => 2,
            "first_name" => "Dean",
            "middle_name" => null,
            "last_name" => "SEd",
            "enabled" => true,
        ],
        [
            "role" => Role::DEAN->value,
            "email" => "dean.shtm@email.com",
            "password" => "hahahaha",
            "school_id" => 3,
            "first_name" => "Dean",
            "middle_name" => null,
            "last_name" => "SHTM",
            "enabled" => true,
        ],
        [
            "role" => Role::DEAN->value,
            "email" => "dean.sbm@email.com",
            "password" => "hahahaha",
            "school_id" => 4,
            "first_name" => "Dean",
            "middle_name" => null,
            "last_name" => "SBM",
            "enabled" => true,
        ],
        [
            "role" => Role::PESO_STAFF->value,
            "email" => "peso@email.com",
            "password" => "hahahaha",
            "first_name" => "PESO",
            "middle_name" => null,
            "last_name" => "Staff",
            "enabled" => true,
        ]
    ];

    public function seed(PDO $pdo)
    {
        $service = new UserService($pdo);
        $seeds = array_merge([
            [
                "role" => Role::SYSTEM_ADMIN->value,
                "email" => $_ENV['SYSAD_EMAIL'],
                "password" => $_ENV['SYSAD_PASSWORD'],
                "first_name" => $_ENV['SYSAD_FIRST_NAME'],
                "middle_name" => null,
                "last_name" => $_ENV['SYSAD_LAST_NAME'],
                "enabled" => true,
            ]
        ], $this->seeds);

        foreach ($seeds as $seed)
        {
            try {
                $user = $service->createAdminWithProfile($seed);
                $email = $user["email"];
                $role = $user["role"];
                echo "Created: [{$role}] {$email}" . PHP_EOL;
            } catch (Exception $e) {
                echo "Failed: [{$seed['role']}] {$seed['email']} " . $e->getMessage() . PHP_EOL;
            }
        }
    }
}