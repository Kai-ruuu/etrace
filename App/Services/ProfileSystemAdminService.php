<?php

namespace App\Services;

use App\Models\ProfileSystemAdmin;
use App\Models\User;
use PDO;

class ProfileSystemAdminService
{
    private PDO $pdo;
    
    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    private function attachUser(ProfileSystemAdmin $profile): ?array
    {
        $user = User::findById($this->pdo, $profile->userId);
        
        if (!$user) return null;

        $result = $user->toArray();
        $result['default'] = $_ENV['SYSAD_EMAIL'] === $user->email;
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
            SELECT COUNT(s.id)
            FROM sysads s
            JOIN users u ON u.id = s.user_id
            WHERE
                {$enabled}
                (
                    u.email LIKE ? OR
                    s.first_name LIKE ? OR
                    s.middle_name LIKE ? OR
                    s.last_name LIKE ?
                )
        ");
        
        $sqlTotal->execute($bindings);
        $total = $sqlTotal->fetchColumn();

        $bindings = array_merge($bindings, [$perPage, $offset]);
        
        $sql = $this->pdo->prepare("
            SELECT s.*
            FROM sysads s
            JOIN users u ON u.id = s.user_id
            WHERE
                {$enabled}
                (
                    u.email LIKE ? OR
                    s.first_name LIKE ? OR
                    s.middle_name LIKE ? OR
                    s.last_name LIKE ?
                )
            LIMIT ? OFFSET ?
        ");

        $sql->execute($bindings);

        $results = array_map(fn($row) => $this->attachUser(ProfileSystemAdmin::fromRow($row)), $sql->fetchAll());
        
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
        $profile = ProfileSystemAdmin::findById($this->pdo, $id);
        return $profile ? $this->attachUser($profile) : null;
    }

    public function update(int $id, array $data): ?array
    {
        $profile = ProfileSystemAdmin::update($this->pdo, $id, $data);
        return $profile ? $this->attachUser($profile) : null;
    }
}