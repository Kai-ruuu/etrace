<?php

namespace App\Services;

use App\Models\SocialMedia;
use Exception;
use PDO;
use PDOException;

class SocialMediaService
{
    private PDO $pdo;
    
    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    public function findById(int $id): ?array
    {
        $instance = SocialMedia::findById($this->pdo, $id);
        return $instance ? $instance->toArray() : null;
    }

    public function create(array $data): ?array
    {
        try {
            $this->pdo->beginTransaction();
            
            $modelInstance = SocialMedia::create($this->pdo, $data);

            $this->pdo->commit();
            return $this->findById($modelInstance->id);
        } catch (PDOException $e) {
            $this->pdo->rollBack();
            throw new Exception($e->getMessage());
        }
    }

    public function update(int $id, array $data): ?array
    {
        $updatedInstance = SocialMedia::update($this->pdo, $id, $data);
        return $updatedInstance ? $updatedInstance->toArray() : null;
    }
}