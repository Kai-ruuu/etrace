<?php

namespace App\Services;

use App\Models\ProfileDean;
use App\Models\School;
use App\Models\User;
use PDO;

class ProfileDeanService
{
    private PDO $pdo;
    
    public function __construct(PDO $pdo) {
        $this->pdo = $pdo;
    }

    private function attachRequired(ProfileDean $profile): ?array
    {
        $user = User::findById($this->pdo, $profile->userId);
        $school = School::findById($this->pdo, $profile->schoolId);
        
        if (!$user) return null;

        $result = $user->toArray();
        $result["profile"] = $profile->toArray();
        $result["profile"]['school'] = $school->toArray();
        return $result;
    }

    public function findAll(
        ?bool $enabled = null,
        string $query = '',
        int $page = 1,
        int $perPage = 20,
    ): array
    {
        $page    = max(1, (int)$page);
        $perPage = min(100, max(1, (int)$perPage));
        $offset  = ($page - 1) * $perPage;
        $search = "%$query%";
        $bindings = $enabled === null
            ? [$search, $search, $search, $search]
            : [$enabled, $search, $search, $search, $search];
        
        $enabled = $enabled === null
            ? ''
            : 'u.enabled = ? AND';

        $sqlTotal = $this->pdo->prepare("
            SELECT COUNT(d.id)
            FROM deans d
            JOIN users u ON u.id = d.user_id
            WHERE
                {$enabled}
                (
                    u.email LIKE ? OR
                    d.first_name LIKE ? OR
                    d.middle_name LIKE ? OR
                    d.last_name LIKE ?
                )
        ");
        
        $sqlTotal->execute($bindings);
        $total = $sqlTotal->fetchColumn();

        $bindings = array_merge($bindings, [$perPage, $offset]);
        
        $sql = $this->pdo->prepare("
            SELECT d.*
            FROM deans d
            JOIN users u ON u.id = d.user_id
            WHERE
                {$enabled}
                (
                    u.email LIKE ? OR
                    d.first_name LIKE ? OR
                    d.middle_name LIKE ? OR
                    d.last_name LIKE ?
                )
            LIMIT ? OFFSET ?
        ");

        $sql->execute($bindings);

        $results = array_map(fn($row) => $this->attachRequired(ProfileDean::fromRow($row)), $sql->fetchAll());
        
        return [
            'results'     => $results,
            'total'       => $total,
            'page'        => $page,
            'per_page'    => $perPage,
            'total_pages' => (int) ceil($total / $perPage),
            'has_next'    => $page < ceil($total / $perPage),
            'has_prev'    => $page > 1,
        ];
    }

    public function findById(int $id): ?array
    {
        $profile = ProfileDean::findById($this->pdo, $id);
        return $profile ? $this->attachRequired($profile) : null;
    }

    public function update(int $id, array $data): ?array
    {
        $profile = ProfileDean::update($this->pdo, $id, $data);
        return $profile ? $this->attachRequired($profile) : null;
    }
}