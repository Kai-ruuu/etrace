<?php

namespace App\Models;

use App\Core\Migratable;
use DateTime;
use PDO;

class School implements Migratable
{
    public int $id;
    public string $name;
    public bool $archived;
    public DateTime $createdAt;
    public DateTime $updatedAt;

    public static function table(): string
    {
        return "schools";
    }

    public static function migrate(PDO $pdo): void
    {
        $table = self::table();
        $sql = $pdo->prepare("CREATE TABLE IF NOT EXISTS {$table}(
            id INT AUTO_INCREMENT PRIMARY KEY,
            name VARCHAR(255) UNIQUE NOT NULL,
            archived BOOLEAN NOT NULL DEFAULT FALSE,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        );");
        $sql->execute();
    }

    public static function fromRow(array $row): self
    {
        $instance            = new self();
        $instance->id        = (int) $row['id'];
        $instance->name      = $row['name'];
        $instance->archived  = (bool) $row['archived'];
        $instance->createdAt = new DateTime($row['created_at']);
        $instance->updatedAt = new DateTime($row['updated_at']);
        return $instance;
    }

    public static function findAll(PDO $pdo): array
    {
        $sql = $pdo->prepare('SELECT * FROM schools');
        $sql->execute();

        return array_map(fn($row) => self::fromRow($row), $sql->fetchAll());
    }

    public static function findAllActive(PDO $pdo): array
    {
        $sql = $pdo->prepare('SELECT * FROM schools WHERE archived = FALSE');
        $sql->execute();

        return array_map(fn($row) => self::fromRow($row), $sql->fetchAll());
    }

    public static function findById(PDO $pdo, int $id): ?self
    {
        $sql = $pdo->prepare('SELECT * FROM schools WHERE id = ? LIMIT 1');
        $sql->execute([$id]);

        $row = $sql->fetch();
        return $row ? self::fromRow($row) : null;
    }

    public static function findByCourseId(PDO $pdo, int $id): ?self
    {
        $sql = $pdo->prepare('SELECT * FROM schools WHERE course_id = ? LIMIT 1');
        $sql->execute([$id]);

        $row = $sql->fetch();
        return $row ? self::fromRow($row) : null;
    }

    public static function findByName(PDO $pdo, string $name): ?self
    {
        $sql = $pdo->prepare('SELECT * FROM schools WHERE LOWER(name) = LOWER(?) LIMIT 1');
        $sql->execute([$name]);

        $row = $sql->fetch();
        return $row ? self::fromRow($row) : null;
    }

    public static function create(PDO $pdo, array $data): self
    {
        $sql = $pdo->prepare('
            INSERT INTO schools (name)
            VALUES (:name)
        ');

        $sql->execute([
            ':name'    => $data['name'],
        ]);

        return self::findById($pdo, (int) $pdo->lastInsertId());
    }

    public static function update(PDO $pdo, int $id, array $data): ?self
    {
        $sql = $pdo->prepare('
            UPDATE schools
            SET
                name     = :name,
                archived = :archived
            WHERE id = :id
        ');

        $sql->execute([
            ':name'     => $data['name'],
            ':archived' => $data['archived'],
            ':id'       => $id,
        ]);

        return self::findById($pdo, $id);
    }

    public function toArray(): array
    {
        return [
            'id'         => $this->id,
            'name'       => $this->name,
            'archived'   => $this->archived,
            'created_at' => $this->createdAt->format('Y-m-d H:i:s'),
            'updated_at' => $this->updatedAt->format('Y-m-d H:i:s'),
        ];
    }
}