<?php

namespace App\Models;

use App\Core\Migratable;
use DateTime;
use PDO;

class CourseOccupation implements Migratable
{
    public int $id;
    public int $courseId;
    public int $occupationId;
    public DateTime $createdAt;
    public DateTime $updatedAt;

    public static function table(): string
    {
        return "course_occupations";
    }

    public static function migrate(PDO $pdo): void
    {
        $table = self::table();
        $sql = $pdo->prepare("CREATE TABLE IF NOT EXISTS {$table}(
            id INT AUTO_INCREMENT PRIMARY KEY,
            course_id INT NOT NULL,
            occupation_id INT NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            FOREIGN KEY (course_id) REFERENCES courses(id) ON DELETE CASCADE,
            FOREIGN KEY (occupation_id) REFERENCES occupations(id) ON DELETE CASCADE,
            UNIQUE(course_id, occupation_id)
        );");
        $sql->execute();
    }

    public static function fromRow(array $row): self
    {
        $instance                = new self();
        $instance->id            = (int) $row['id'];
        $instance->courseId      = (int) $row['id'];
        $instance->occupationId  = (int) $row['id'];
        $instance->createdAt     = new DateTime($row['created_at']);
        $instance->updatedAt     = new DateTime($row['updated_at']);
        return $instance;
    }

    public static function findAll(PDO $pdo): array
    {
        $sql = $pdo->prepare('SELECT * FROM course_occupations');
        $sql->execute();

        return array_map(fn($row) => self::fromRow($row), $sql->fetchAll());
    }

    public static function findById(PDO $pdo, int $id): ?self
    {
        $sql = $pdo->prepare('
            SELECT * FROM course_occupations
            WHERE id = ? LIMIT 1
        ');
        $sql->execute([$id]);

        $row = $sql->fetch();
        return $row ? self::fromRow($row) : null;
    }

    public static function findByIds(PDO $pdo, int $courseId, int $occupationId): ?self
    {
        $sql = $pdo->prepare('
            SELECT * FROM course_occupations
            WHERE
                course_id = :course_id AND
                occupation_id = :occupation_id
            LIMIT 1
        ');
        $sql->execute([
            ':course_id' => $courseId,
            ':occupation_id' => $occupationId,
        ]);

        $row = $sql->fetch();
        return $row ? self::fromRow($row) : null;
    }

    public static function create(PDO $pdo, array $data): self
    {
        $sql = $pdo->prepare('
            INSERT INTO course_occupations (course_id, occupation_id)
            VALUES (:course_id, :occupation_id)
        ');

        $sql->execute([
            ':course_id' => $data['course_id'],
            ':occupation_id' => $data["occupation_id"],
        ]);

        return self::findById($pdo, (int) $pdo->lastInsertId());
    }

    public static function delete(PDO $pdo, int $course_id, int $occupation_id): bool
    {
        $sql = $pdo->prepare('
            DELETE FROM course_occupations
            WHERE
                course_id = :course_id AND
                occupation_id = :occupation_id;
        ');
        $sql->execute([
            ':course_id' => $course_id,
            ':occupation_id' => $occupation_id,
        ]);

        return $sql->rowCount() > 0;
    }

    public function toArray(): array
    {
        return [
            'id'            => $this->id,
            'course_id'     => $this->courseId,
            'occupation_id' => $this->occupationId,
            'created_at'    => $this->createdAt->format('Y-m-d H:i:s'),
            'updated_at'    => $this->updatedAt->format('Y-m-d H:i:s'),
        ];
    }
}