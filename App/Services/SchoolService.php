<?php

namespace App\Services;

use App\Models\School;
use Exception;
use PDO;
use PDOException;

class SchoolService
{
    private PDO $pdo;
    
    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    public function findAll(
        ?bool $archived = null,
        string $query = '',
        int $page = 1,
        int $perPage = 20,
    ): array
    {
        $page    = max(1, (int)$page);
        $perPage = min(100, max(1, (int)$perPage));
        $offset  = ($page - 1) * $perPage;
        $search = "%$query%";
        $bindings = $archived === null
            ? [$search]
            : [$archived, $search];
        $archived = $archived === null
            ? ''
            : 'archived = ? AND';

        $sqlTotal = $this->pdo->prepare("
            SELECT COUNT(*)
            FROM schools
            WHERE
                {$archived}
                name LIKE ?
        ");
        
        $sqlTotal->execute($bindings);
        $total = $sqlTotal->fetchColumn();

        $bindings = array_merge($bindings, [$perPage, $offset]);
        
        $sql = $this->pdo->prepare("
            SELECT *
            FROM schools
            WHERE
                {$archived}
                name LIKE ?
            LIMIT ? OFFSET ?
        ");

        $sql->execute($bindings);

        $results = array_map(fn($row) => School::fromRow($row)->toArray(), $sql->fetchAll());
        
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
        $school = School::findById($this->pdo, $id);
        return $school ? $school->toArray() : null;
    }

    public function findByName(string $name): ?array
    {
        $school = School::findByName($this->pdo, $name);
        return $school ? $school->toArray() : null;
    }

    public function create(array $data): ?array
    {
        try {
            $school = School::create($this->pdo, $data);
            return $this->findById($school->id);
        } catch (PDOException $e) {
            $this->pdo->rollBack();
            throw new Exception($e->getMessage());
        }
    }

    public function update(int $id, array $data): ?array
    {
        $school = School::update($this->pdo, $id, $data);
        return $school ? $school->toArray() : null;
    }
}