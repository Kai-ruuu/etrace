<?php

namespace App\Models;

use App\Core\Migratable;
use DateTime;
use PDO;

class OccupationState implements Migratable
{
    public int $id;

    public int $alumniId;
    public int $occupationId;
    public string $address;
    public string $company;
    public int $startYear;
    public int $endYear;
    public bool $isCurrent;
    
    public DateTime $createdAt;
    public DateTime $updatedAt;

    public static function table(): string
    {
        return "occupation_states";
    }

    public static function migrate(PDO $pdo): void
    {
        $table = self::table();
        $sql = $pdo->prepare("CREATE TABLE IF NOT EXISTS {$table}(
            id INT AUTO_INCREMENT PRIMARY KEY,
            alumni_id INT NOT NULL,
            occupation_id INT NOT NULL,
            address VARCHAR(512) NOT NULL,
            company VARCHAR(512) NOT NULL,
            start_year INT NOT NULL,
            end_year INT NULL,
            is_current BOOLEAN DEFAULT TRUE,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            FOREIGN KEY (alumni_id) REFERENCES alumni(id) ON DELETE CASCADE,
            FOREIGN KEY (occupation_id) REFERENCES occupations(id) ON DELETE CASCADE,
            CONSTRAINT check_current CHECK (
                (is_current = TRUE AND end_year IS NULL) OR
                (is_current = FALSE AND end_year IS NOT NULL)
            ),
            CONSTRAINT check_years CHECK (end_year IS NULL OR end_year >= start_year)
        );");
        $sql->execute();
    }

    public static function fromRow(array $row): self
    {
        $instance               = new self();
        $instance->id           = (int) $row['id'];

        $instance->alumniId     = (int) $row['alumni_id'];
        $instance->occupationId = (int) $row['occupation_id'];
        $instance->address      = $row['address'];
        $instance->company      = $row['company'];
        $instance->startYear    = (int) $row['start_year'];
        $instance->endYear      = (int) $row['end_year'];
        $instance->isCurrent    = (bool) $row['is_current'];
        
        $instance->createdAt    = new DateTime($row['created_at']);
        $instance->updatedAt    = new DateTime($row['updated_at']);
        return $instance;
    }

    public static function findById(PDO $pdo, int $id): ?self
    {
        $sql = $pdo->prepare('SELECT * FROM occupation_states WHERE id = ? LIMIT 1');
        $sql->execute([$id]);

        $row = $sql->fetch();
        return $row ? self::fromRow($row) : null;
    }

    public static function findAllByAlumniId(PDO $pdo, int $id): array
    {
        $sql = $pdo->prepare('SELECT * FROM occupation_states WHERE alumni_id = ?');
        $sql->execute([$id]);

        return array_map(fn($row) => self::fromRow($row), $sql->fetchAll());
    }

    public static function create(PDO $pdo, array $data): self
    {
        $sql = $pdo->prepare('
            INSERT INTO occupation_states (
                alumni_id,
                occupation_id,
                address,
                company,
                start_year,
                end_year,
                is_current
            )
            VALUES (?, ?, ?, ?, ?, ?, ?)
        ');

        $sql->execute([
            $data['alumni_id'],
            $data['occupation_id'],
            $data['address'],
            $data['company'],
            $data['start_year'],
            $data['end_year'],
            $data['is_current'],
        ]);

        return self::findById($pdo, (int) $pdo->lastInsertId());
    }

    public static function update(PDO $pdo, int $id, array $data): ?self
    {
        $sql = $pdo->prepare('
            UPDATE occupation_states
            SET
                start_year    = ?
                end_year      = ?
                is_current    = ?
            WHERE id = ?
        ');

        $sql->execute([
            $data['start_year'],
            $data['end_year'],
            $data['is_current'],
            $id
        ]);

        return self::findById($pdo, $id);
    }

    public function toArray(): array
    {
        return [
            'id'            => $this->id,
            'alumni_id'     => $this->alumniId,
            'occupation_id' => $this->occupationId,
            'address'       => $this->address,
            'company'       => $this->company,
            'start_year'    => $this->startYear,
            'end_year'      => $this->endYear,
            'is_current'    => $this->isCurrent,
            'created_at'    => $this->createdAt->format('Y-m-d H:i:s'),
            'updated_at'    => $this->updatedAt->format('Y-m-d H:i:s'),
        ];
    }
}