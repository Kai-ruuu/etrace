<?php

namespace App\Services;

use App\Models\Course;
use App\Models\GraduateRecord;
use App\Models\ProfileDean;
use Exception;
use PDO;
use PDOException;

class GraduateRecordService
{
    private PDO $pdo;
    
    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    private function attachRequired(GraduateRecord $record): ?array
    {
        $deanProfile = ProfileDean::findById($this->pdo, $record->deanUploaderId);
        
        if (!$deanProfile) return null;

        $result = $record->toArray();
        $result['course'] = Course::findById($this->pdo, $record->courseId);
        $result['dean_uploader'] = $deanProfile->toArray();
        return $result;
    }

    public function findAll(
        ?bool $archived = false,
        ?int $courseId = null,
        ?int $batch = null,
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
            ? []
            : [$archived];
        $bindings = $courseId === null
            ? $bindings
            : array_merge($bindings, [$courseId]);
        $bindings = $batch === null
            ? $bindings
            : array_merge($bindings, [$batch]);

        $required = [$search, $search];
        $bindings = array_merge($bindings, $required);

        $archived = $archived === null
            ? ''
            : 'gr.archived = ? AND';
        $course = $courseId === null
            ? ''
            : 'gr.course_id = ? AND';
        $batchYear = $batch === null
            ? ''
            : 'gr.graduation_year = ? AND';


        $sqlTotal = $this->pdo->prepare("
            SELECT COUNT(gr.id)
            FROM graduate_records gr
            WHERE
                {$archived}
                {$course}
                {$batchYear}
                (
                    gr.filename LIKE ? OR
                    gr.graduation_year LIKE ?
                )
        ");
        
        $sqlTotal->execute($bindings);
        $total = $sqlTotal->fetchColumn();

        $bindings = array_merge($bindings, [$perPage, $offset]);
        
        $sql = $this->pdo->prepare("
            SELECT gr.*
            FROM graduate_records gr
            WHERE
                {$archived}
                {$course}
                {$batchYear}
                (
                    gr.filename LIKE ? OR
                    gr.graduation_year LIKE ?
                )
            LIMIT ? OFFSET ?
        ");

        $sql->execute($bindings);

        $results = array_map(fn($row) => $this->attachRequired(GraduateRecord::fromRow($row)), $sql->fetchAll());
        
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
        $record = GraduateRecord::findById($this->pdo, $id);
        return $record ? $record->toArray() : null;
    }

    public function findByBatchAndCourseId(int $batch, int $courseId): ?array
    {
        $record = GraduateRecord::findByBatchAndCourseId($this->pdo, $batch, $courseId);
        return $record ? $record->toArray() : null;
    }

    public function create(array $data): ?array
    {
        try {
            $this->pdo->beginTransaction();

            $modelInstance = GraduateRecord::create($this->pdo, $data);

            $this->pdo->commit();
            return $this->findById($modelInstance->id);
        } catch (PDOException $e) {
            $this->pdo->rollBack();
            throw new Exception($e->getMessage());
        }
    }

    public function update(int $id, array $data): ?array
    {
        return GraduateRecord::update($this->pdo, $id, $data)->toArray();
    }
}