<?php

namespace App\Models;

use App\Core\Migratable;
use PDO;

class Qualification implements Migratable
{
    public int $id;
    public int $jobPostId;
    public string $qualification;

    public static function table(): string
    {
        return "qualifications";
    }

    public static function migrate(PDO $pdo): void
    {
        $table = self::table();
        $sql = $pdo->prepare("CREATE TABLE IF NOT EXISTS {$table}(
            id INT AUTO_INCREMENT PRIMARY KEY,
            job_post_id INT NOT NULL,
            qualification VARCHAR(500) NOT NULL,
            FOREIGN KEY (job_post_id) REFERENCES job_posts(id) ON DELETE CASCADE
        );");
        $sql->execute();
    }

    public static function fromRow(array $row): self
    {
        $instance                = new self();
        $instance->id            = (int) $row['id'];
        $instance->jobPostId     = $row['job_post_id'];
        $instance->qualification = $row['qualification'];
        return $instance;
    }

    public static function findAllByJobPostId(PDO $pdo, int $id): array
    {
        $sql = $pdo->prepare('SELECT * FROM qualifications WHERE job_post_id = ?');
        $sql->execute([$id]);

        return array_map(fn($row) => self::fromRow($row), $sql->fetchAll());
    }

    public static function findById(PDO $pdo, int $id): ?self
    {
        $sql = $pdo->prepare('SELECT * FROM qualifications WHERE id = ? LIMIT 1');
        $sql->execute([$id]);

        $row = $sql->fetch();
        return $row ? self::fromRow($row) : null;
    }

    public static function findByQualificationAndPostId(PDO $pdo, string $qualification, int $id): ?self
    {
        $sql = $pdo->prepare('SELECT * FROM qualifications WHERE job_post_id = ? AND LOWER(qualification) = LOWER(?) LIMIT 1');
        $sql->execute([$qualification, $id]);

        $row = $sql->fetch();
        return $row ? self::fromRow($row) : null;
    }

    public static function create(PDO $pdo, array $data): self
    {
        $sql = $pdo->prepare('
            INSERT INTO qualifications (job_post_id, qualification)
            VALUES (?, ?)
        ');

        $sql->execute([
            $data['job_post_id'],
            $data["qualification"],
        ]);

        return self::findById($pdo, (int) $pdo->lastInsertId());
    }

    public static function update(PDO $pdo, int $id, array $data): ?self
    {
        $sql = $pdo->prepare('
            UPDATE qualifications
            SET qualification = ?
            WHERE id = ?
        ');

        $sql->execute([
            $data['qualification'],
            $id,
        ]);

        return self::findById($pdo, $id);
    }

    public static function deleteById(PDO $pdo, int $id, ): bool
    {
        $sql = $pdo->prepare('
            DELETE FROM qualifications
            WHERE id = ?
        ');

        $sql->execute([$id]);

        return $sql->rowCount() > 0;
    }

    public function toArray(): array
    {
        return [
            'id'            => $this->id,
            'job_post_id'   => $this->jobPostId,
            'qualification' => $this->qualification,
        ];
    }
}