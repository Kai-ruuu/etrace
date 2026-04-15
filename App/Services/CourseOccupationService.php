<?php

namespace App\Services;

use App\Models\CourseOccupation;
use Exception;
use PDO;
use PDOException;

class CourseOccupationService
{
    private PDO $pdo;
    
    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    public function findById(int $id): ?array
    {
        $courseOccupation = CourseOccupation::findById($this->pdo, $id);
        return $courseOccupation ? $courseOccupation->toArray() : null;
    }

    public function findByIds(int $courseId, int $occupationId): ?array
    {
        $courseOccupation = CourseOccupation::findByIds($this->pdo, $courseId, $occupationId);
        return $courseOccupation ? $courseOccupation->toArray() : null;
    }

    public function create(array $data): ?array
    {
        try {
            $this->pdo->beginTransaction();

            $modelInstance = CourseOccupation::create($this->pdo, $data);

            $this->pdo->commit();
            return $this->findById($modelInstance->id);
        } catch (PDOException $e) {
            $this->pdo->rollBack();
            throw new Exception($e->getMessage());
        }
    }

    public function delete(int $courseId, int $occupationId): bool
    {
        return CourseOccupation::delete($this->pdo, $courseId, $occupationId);
    }
}