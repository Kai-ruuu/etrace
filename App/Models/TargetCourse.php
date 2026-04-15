<?php

namespace App\Models;

use App\Core\Migratable;
use PDO;

class TargetCourse implements Migratable
{
    public int $id;
    public int $jobPostId;
    public int $courseId;

    public static function table(): string
    {
        return "target_courses";
    }

    public static function migrate(PDO $pdo): void
    {
        $table = self::table();
        $sql = $pdo->prepare("CREATE TABLE IF NOT EXISTS {$table}(
            id INT AUTO_INCREMENT PRIMARY KEY,
            job_post_id INT NOT NULL,
            course_id INT NOT NULL,
            FOREIGN KEY (job_post_id) REFERENCES job_posts(id) ON DELETE CASCADE,
            FOREIGN KEY (course_id) REFERENCES courses(id) ON DELETE CASCADE
        );");
        $sql->execute();
    }

    public static function fromRow(array $row): self
    {
        $instance            = new self();
        $instance->id        = (int) $row['id'];
        $instance->jobPostId = (int) $row['job_post_id'];
        $instance->courseId  = (int) $row['course_id'];
        return $instance;
    }

    public static function findAllForPost(PDO $pdo, $id): array
    {
        $sql = $pdo->prepare('SELECT * FROM target_courses WHERE job_post_id = ?');
        $sql->execute([$id]);

        return array_map(fn($row) => self::fromRow($row), $sql->fetchAll());
    }

    public static function findById(PDO $pdo, int $id): ?self
    {
        $sql = $pdo->prepare('SELECT * FROM target_courses WHERE id = ? LIMIT 1');
        $sql->execute([$id]);

        $row = $sql->fetch();
        return $row ? self::fromRow($row) : null;
    }

    public static function findByCourseAndPostId(PDO $pdo, int $courseId, int $postId): ?self
    {
        $sql = $pdo->prepare('SELECT * FROM target_courses WHERE course_id = ? AND job_post_id = ? LIMIT 1');
        $sql->execute([$courseId, $postId]);

        $row = $sql->fetch();
        return $row ? self::fromRow($row) : null;
    }

    public static function create(PDO $pdo, array $data): self
    {
        $sql = $pdo->prepare('
            INSERT INTO target_courses (job_post_id, course_id)
            VALUES (?, ?)
        ');

        $sql->execute([
            $data['job_post_id'],
            $data["course_id"],
        ]);

        return self::findById($pdo, (int) $pdo->lastInsertId());
    }

    public static function deleteById(PDO $pdo, int $id): bool
    {
        $sql = $pdo->prepare('
            DELETE FROM target_courses
            WHERE id = ?
        ');

        $sql->execute([$id]);

        return $sql->rowCount() > 0;
    }

    public function toArray(): array
    {
        return [
            'id'          => $this->id,
            'job_post_id' => $this->jobPostId,
            'course_id'   => $this->courseId,
        ];
    }
}