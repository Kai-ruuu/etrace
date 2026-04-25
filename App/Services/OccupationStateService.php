<?php

namespace App\Services;

use App\Models\Occupation;
use App\Models\OccupationState;
use PDO;
use PDOException;

class OccupationStateService
{
    private PDO $pdo;
    
    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    private function attachedRequired(OccupationState $occState): array
    {
        $occStateArray = $occState->toArray();
        $occStateArray['occupation'] = Occupation::findById($this->pdo, $occState->occupationId)->toArray();
        return $occStateArray;
    }

    public function findById(int $id): ?array
    {
        $instance = OccupationState::findById($this->pdo, $id);
        return $instance ? $this->attachedRequired($instance) : null;
    }

    public function create(array $data): ?array
    {
        try {
            $this->pdo->beginTransaction();

            $existingOccupation = Occupation::findByName($this->pdo, $data['name']);
            
            if ($existingOccupation)
                $occupationId = $existingOccupation->id;
            else
                $occupationId = Occupation::create($this->pdo, ['name' => $data['name']])->id;

            $modelInstance = OccupationState::create($this->pdo, [
                'alumni_id' => $data['alumni_id'],
                'occupation_id' => $occupationId,
                'company' => $data['company'],
                'address' => $data['address'],
                'start_year' => $data['start_year'],
                'end_year' => $data['end_year'],
                'is_current' => $data['is_current'],
            ]);
            
            $this->pdo->commit();
            return $this->findById($modelInstance->id);
        } catch (PDOException $e) {
            $this->pdo->rollBack();
            return null;
        }
    }

    public function update(int $id, array $data): ?array
    {
        try {
            $this->pdo->beginTransaction();

            $existingOccupation = Occupation::findByName($this->pdo, $data['name']);
            
            if ($existingOccupation)
                $occupationId = $existingOccupation->id;
            else
                $occupationId = Occupation::create($this->pdo, ['name' => $data['name']])->id;

            $modelInstance = OccupationState::update($this->pdo, $id, [
                'alumni_id' => $data['alumni_id'],
                'occupation_id' => $occupationId,
                'company' => $data['company'],
                'address' => $data['address'],
                'start_year' => $data['start_year'],
                'end_year' => $data['end_year'],
                'is_current' => $data['is_current'],
            ]);
            
            $this->pdo->commit();
            return $this->findById($modelInstance->id);
        } catch (PDOException $e) {
            $this->pdo->rollBack();
            return null;
        }
    }
}