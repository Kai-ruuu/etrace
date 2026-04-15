<?php

namespace App\Services;

use App\Models\Course;
use Exception;
use PDO;
use PDOException;

class CourseService
{
    private PDO $pdo;
    
    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    public function findAll(
        int $schoolId,
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
            ? [$schoolId, $search, $search]
            : [$schoolId, $archived, $search, $search];

        $archived = $archived === null
            ? ''
            : 'archived = ? AND';

        $sqlTotal = $this->pdo->prepare("
            SELECT COUNT(*)
            FROM courses
            WHERE
                school_id = ? AND
                {$archived}
                (
                    name LIKE ? OR
                    code LIKE ?
                )
        ");
        
        $sqlTotal->execute($bindings);
        $total = $sqlTotal->fetchColumn();

        $bindings = array_merge($bindings, [$perPage, $offset]);
        
        $sql = $this->pdo->prepare("
            SELECT *
            FROM courses
            WHERE
                school_id = ? AND
                {$archived}
                (
                    name LIKE ? OR
                    code LIKE ?
                )
            LIMIT ? OFFSET ?
        ");

        $sql->execute($bindings);

        $results = array_map(fn($row) => Course::fromRow($row)->toArray(), $sql->fetchAll());
        
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
        $course = Course::findById($this->pdo, $id);
        return $course ? $course->toArray() : null;
    }

    public function findByName(string $name): ?array
    {
        $course = Course::findByName($this->pdo, $name);
        return $course ? $course->toArray() : null;
    }

    public function findByCode(string $name): ?array
    {
        $course = Course::findByCode($this->pdo, $name);
        return $course ? $course->toArray() : null;
    }

    public function create(array $data): ?array
    {
        try {
            $this->pdo->beginTransaction();

            $course = Course::create($this->pdo, $data);

            $this->pdo->commit();
            return $this->findById($course->id);
        } catch (PDOException $e) {
            $this->pdo->rollBack();
            throw new Exception($e->getMessage());
        }
    }

    public function update(int $id, array $data): ?array
    {
        $course = Course::update($this->pdo, $id, $data);
        return $course ? $course->toArray() : null;
    }
}