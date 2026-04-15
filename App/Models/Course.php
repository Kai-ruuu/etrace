<?php

namespace App\Models;

use App\Core\Migratable;
use App\Utils\ArrayLogger;
use DateTime;
use PDO;

class Course implements Migratable
{
    public int $id;
    public int $schoolId;
    public string $name;
    public string $code;
    public bool $archived;
    public DateTime $createdAt;
    public DateTime $updatedAt;

    public static function table(): string
    {
        return "courses";
    }

    public static function migrate(PDO $pdo): void
    {
        $table = self::table();
        $sql = $pdo->prepare("CREATE TABLE IF NOT EXISTS {$table}(
            id INT AUTO_INCREMENT PRIMARY KEY,
            school_id INT NOT NULL,
            name VARCHAR(255) NOT NULL,
            code VARCHAR(10) NOT NULL,
            archived BOOLEAN NOT NULL DEFAULT FALSE,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            FOREIGN KEY (school_id) REFERENCES schools(id) ON DELETE CASCADE,
            UNIQUE(school_id, name),
            UNIQUE(school_id, code)
        );");
        $sql->execute();
    }

    public static function fromRow(array $row): self
    {
        $instance            = new self();
        $instance->id        = (int) $row['id'];
        $instance->schoolId  = (int) $row['school_id'];
        $instance->name      = $row['name'];
        $instance->code      = $row['code'];
        $instance->archived  = $row['archived'];
        $instance->createdAt = new DateTime($row['created_at']);
        $instance->updatedAt = new DateTime($row['updated_at']);
        return $instance;
    }

    public static function findAll(PDO $pdo): array
    {
        $sql = $pdo->prepare('SELECT * FROM courses WHERE archived = FALSE');
        $sql->execute();

        return array_map(fn($row) => self::fromRow($row), $sql->fetchAll());
    }

    public static function findAllForDean(PDO $pdo, int $schoolId): array
    {
        $sql = $pdo->prepare('SELECT * FROM courses WHERE archived = FALSE AND school_id = ?');
        $sql->execute([$schoolId]);

        return array_map(fn($row) => self::fromRow($row), $sql->fetchAll());
    }

    public static function findById(PDO $pdo, int $id): ?self
    {
        $sql = $pdo->prepare('SELECT * FROM courses WHERE id = ? LIMIT 1');
        $sql->execute([$id]);

        $row = $sql->fetch();
        return $row ? self::fromRow($row) : null;
    }

    public static function findByName(PDO $pdo, string $name): ?self
    {
        $sql = $pdo->prepare('SELECT * FROM courses WHERE LOWER(name) = LOWER(?) LIMIT 1');
        $sql->execute([$name]);

        $row = $sql->fetch();
        return $row ? self::fromRow($row) : null;
    }

    public static function findByCode(PDO $pdo, string $code): ?self
    {
        $sql = $pdo->prepare('SELECT * FROM courses WHERE LOWER(code) = LOWER(?) LIMIT 1');
        $sql->execute([$code]);

        $row = $sql->fetch();
        return $row ? self::fromRow($row) : null;
    }

    public static function create(PDO $pdo, array $data): self
    {
        $sql = $pdo->prepare('
            INSERT INTO courses (school_id, name, code)
            VALUES (:school_id, :name, :code)
        ');

        $sql->execute([
            ':school_id' => $data["school_id"],
            ':name'      => $data["name"],
            ':code'      => $data["code"],
        ]);

        return self::findById($pdo, (int) $pdo->lastInsertId());
    }

    public static function update(PDO $pdo, int $id, array $data): ?self
    {
        $sql = $pdo->prepare('
            UPDATE courses
            SET
                archived  = :archived,
                name      = :name,
                code      = :code
            WHERE id = :id
        ');

        $sql->execute([
            ':archived'  => $data['archived'],
            ':name'      => $data["name"],
            ':code'      => $data["code"],
            ':id'        => $id,
        ]);

        return self::findById($pdo, $id);
    }

    public function toArray(): array
    {
        return [
            'id'         => $this->id,
            'school_id'  => $this->schoolId,
            'name'       => $this->name,
            'code'       => $this->code,
            'archived'   => $this->archived,
            'created_at' => $this->createdAt->format('Y-m-d H:i:s'),
            'updated_at' => $this->updatedAt->format('Y-m-d H:i:s'),
        ];
    }
}