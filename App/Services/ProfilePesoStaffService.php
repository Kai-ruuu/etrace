<?php

namespace App\Services;

use App\Models\ProfilePesoStaff;
use App\Models\User;
use PDO;

class ProfilePesoStaffService
{
    private PDO $pdo;
    
    public function __construct(PDO $pdo) {
        $this->pdo = $pdo;
    }

    private function attachUser(ProfilePesoStaff $profile): ?array
    {
        $user = User::findById($this->pdo, $profile->userId);
        
        if (!$user) return null;

        $result = $user->toArray();
        $result["profile"] = $profile->toArray();
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
            SELECT COUNT(p.id)
            FROM pstaffs p
            JOIN users u ON u.id = p.user_id
            WHERE
                {$enabled}
                (
                    u.email LIKE ? OR
                    p.first_name LIKE ? OR
                    p.middle_name LIKE ? OR
                    p.last_name LIKE ?
                )
        ");
        
        $sqlTotal->execute($bindings);
        $total = $sqlTotal->fetchColumn();

        $bindings = array_merge($bindings, [$perPage, $offset]);
        
        $sql = $this->pdo->prepare("
            SELECT p.*
            FROM pstaffs p
            JOIN users u ON u.id = p.user_id
            WHERE 
                {$enabled}
                (
                    u.email LIKE ? OR
                    p.first_name LIKE ? OR
                    p.middle_name LIKE ? OR
                    p.last_name LIKE ?
                )
            LIMIT ? OFFSET ?
        ");

        $sql->execute($bindings);

        $results = array_map(fn($row) => $this->attachUser(ProfilePesoStaff::fromRow($row)), $sql->fetchAll());
        
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
        $profile = ProfilePesoStaff::findById($this->pdo, $id);
        return $profile ? $this->attachUser($profile) : null;
    }

    public function update(int $id, array $data): ?array
    {
        $profile = ProfilePesoStaff::update($this->pdo, $id, $data);
        return $profile ? $this->attachUser($profile) : null;
    }
}