<?php

namespace App\Services;

use App\Models\Model;
use PDO;
use PDOException;

class Service
{
    private PDO $pdo;
    
    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    public function findAll(
        string $query = '',
        int $page = 1,
        int $perPage = 20,
    ): array
    {
        $page    = max(1, (int)$page);
        $perPage = min(100, max(1, (int)$perPage));
        $offset  = ($page - 1) * $perPage;
        $search = "%$query%";
        $bindings = [$search, $search];

        $sqlTotal = $this->pdo->prepare("
            SELECT COUNT(t.id)
            FROM table_name t
            WHERE
                t.attribute1 = ? AND
                t.attribute2 = ?
        ");
        
        $sqlTotal->execute($bindings);
        $total = $sqlTotal->fetchColumn();

        $bindings = array_merge($bindings, [$perPage, $offset]);
        
        $sql = $this->pdo->prepare("
            SELECT t.*
            FROM table_name t
            WHERE
                t.attribute1 = ? AND
                t.attribute2 = ?
            LIMIT ? OFFSET ?
        ");

        $sql->execute($bindings);

        $results = array_map(fn($row) => Model::fromRow($row)->toArray(), $sql->fetchAll());
        
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
        $instance = Model::findById($this->pdo, $id);
        return $instance ? $instance->toArray() : null;
    }

    public function create(array $data): ?array
    {
        try {
            $this->pdo->beginTransaction();

            $modelInstance = Model::create($this->pdo, $data);

            $this->pdo->commit();
            return $this->findById($modelInstance->id);
        } catch (PDOException $e) {
            $this->pdo->rollBack();
            return null;
        }
    }

    public function update(int $id, array $data): ?array
    {
        $updatedInstance = Model::update($this->pdo, $id, $data);
        return $updatedInstance ? $updatedInstance->toArray() : null;
    }
}