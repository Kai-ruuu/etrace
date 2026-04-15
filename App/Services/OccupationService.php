<?php

namespace App\Services;

use App\Models\CourseOccupation;
use App\Models\Occupation;
use Exception;
use PDO;
use PDOException;

class OccupationService
{
    private PDO $pdo;
    
    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    public function findAllAligned(
        int $schoolId,
        int $courseId,
        string $query = '',
        int $page = 1,
        int $perPage = 20,
    ): array
    {
        $page    = max(1, (int)$page);
        $perPage = min(100, max(1, (int)$perPage));
        $offset  = ($page - 1) * $perPage;
        $search = "%$query%";

        $bindings = [$schoolId, $courseId, $search];

        $sqlTotal = $this->pdo->prepare("
            SELECT COUNT(o.id)
            FROM occupations o
            JOIN course_occupations co ON co.occupation_id = o.id
            JOIN courses c ON c.id = co.course_id
            WHERE
                c.school_id = ? AND
                c.id = ? AND
                o.name LIKE ?
        ");
        
        $sqlTotal->execute($bindings);
        $total = $sqlTotal->fetchColumn();

        $bindings = array_merge($bindings, [$perPage, $offset]);
        
        $sql = $this->pdo->prepare("
            SELECT o.*
            FROM occupations o
            JOIN course_occupations co ON co.occupation_id = o.id
            JOIN courses c ON c.id = co.course_id
            WHERE
                c.school_id = ? AND
                c.id = ? AND
                o.name LIKE ?
            LIMIT ? OFFSET ?
        ");

        $sql->execute($bindings);

        $results = array_map(function($row) {
            $item            = Occupation::fromRow($row)->toArray();
            $item['aligned'] = true;
            return $item;
        }, $sql->fetchAll());
        
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

    public function findAllNotAligned(
        int $schoolId,
        int $courseId,
        string $query = '',
        int $page = 1,
        int $perPage = 20,
    ): array
    {
        $page    = max(1, (int)$page);
        $perPage = min(100, max(1, (int)$perPage));
        $offset  = ($page - 1) * $perPage;
        $search  = "%$query%";

        $bindings = [$schoolId, $courseId, $search];

        $sqlTotal = $this->pdo->prepare("
            SELECT COUNT(o.id)
            FROM occupations o
            WHERE
                o.name LIKE ? AND
                o.id NOT IN (
                    SELECT co2.occupation_id
                    FROM course_occupations co2
                    JOIN courses c2 ON c2.id = co2.course_id
                    WHERE c2.school_id = ?
                    AND co2.course_id = ?
                )
        ");

        $totalBindings = array_merge([$search], [$schoolId], $courseId !== null ? [$courseId] : []);
        $sqlTotal->execute($totalBindings);
        $total = $sqlTotal->fetchColumn();

        $mainBindings = array_merge([$search], [$schoolId], $courseId !== null ? [$courseId] : [], [$perPage, $offset]);

        $sql = $this->pdo->prepare("
            SELECT o.*
            FROM occupations o
            WHERE
                o.name LIKE ? AND
                o.id NOT IN (
                    SELECT co2.occupation_id
                    FROM course_occupations co2
                    JOIN courses c2 ON c2.id = co2.course_id
                    WHERE c2.school_id = ?
                    AND co2.course_id = ?
                )
            LIMIT ? OFFSET ?
        ");

        $sql->execute($mainBindings);

        $results = array_map(function($row) {
            $item            = Occupation::fromRow($row)->toArray();
            $item['aligned'] = false;
            return $item;
        }, $sql->fetchAll());

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
        $occupation = Occupation::findById($this->pdo, $id);
        return $occupation ? $occupation->toArray() : null;
    }

    public function create(array $data): ?array
    {
        try {
            $this->pdo->beginTransaction();

            $modelInstance = Occupation::create($this->pdo, $data);

            $this->pdo->commit();
            return $this->findById($modelInstance->id);
        } catch (PDOException $e) {
            $this->pdo->rollBack();
            throw new Exception($e->getMessage());
        }
    }

    public function update(int $id, array $data): ?array
    {
        return Occupation::update($this->pdo, $id, $data)->toArray();
    }
}