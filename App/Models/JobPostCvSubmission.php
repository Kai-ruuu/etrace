<?php

namespace App\Models;

use App\Core\Migratable;
use App\Core\Types\CvReviewStatus;
use PDO;

class JobPostCvSubmission implements Migratable
{
    public int $id;
    public int $alumniId;
    public CvReviewStatus $status;
    public int $jobPostId;

    public static function table(): string
    {
        return "job_post_cv_submissions";
    }

    public static function migrate(PDO $pdo): void
    {
        $table = self::table();
        $sql = $pdo->prepare("CREATE TABLE IF NOT EXISTS {$table}(
            id INT AUTO_INCREMENT PRIMARY KEY,
            alumni_id INT NOT NULL,
            job_post_id INT NOT NULL,
            status ENUM(
                'Pending',
                'Reviewed'
            ) DEFAULT 'Pending',
            FOREIGN KEY (alumni_id) REFERENCES alumni(id) ON DELETE CASCADE,
            FOREIGN KEY (job_post_id) REFERENCES job_posts(id) ON DELETE CASCADE
        );");
        $sql->execute();
    }

    public static function fromRow(array $row): self
    {
        $instance            = new self();
        $instance->id        = (int) $row['id'];
        $instance->alumniId  = (int) $row['alumni_id'];
        $instance->jobPostId = (int) $row['job_post_id'];
        $instance->status    = CvReviewStatus::from($row['status']);
        return $instance;
    }

    public static function findById(PDO $pdo, int $id): ?self
    {
        $sql = $pdo->prepare('SELECT * FROM job_post_cv_submissions WHERE id = ? LIMIT 1');
        $sql->execute([$id]);

        $row = $sql->fetch();
        return $row ? self::fromRow($row) : null;
    }

    public static function findByAlumniAndPostId(PDO $pdo, int $alumniId, int $jobPostId): ?self
    {
        $sql = $pdo->prepare('SELECT * FROM job_post_cv_submissions WHERE alumni_id = ? AND job_post_id = ? LIMIT 1');
        $sql->execute([$alumniId, $jobPostId]);

        $row = $sql->fetch();
        return $row ? self::fromRow($row) : null;
    }

    public static function findByAllByPostId(PDO $pdo, int $id): ?array
    {
        $sql = $pdo->prepare('SELECT * FROM job_post_cv_submissions WHERE job_post_id = ?');
        $sql->execute([$id]);
        return array_map(fn($row) => self::fromRow($row), $sql->fetchAll());
    }

    public static function findByAllByAlumniId(PDO $pdo, int $id): ?array
    {
        $sql = $pdo->prepare('SELECT * FROM job_post_cv_submissions WHERE alumni_id = ?');
        $sql->execute([$id]);
        return array_map(fn($row) => self::fromRow($row), $sql->fetchAll());
    }

    public static function create(PDO $pdo, array $data): self
    {
        $sql = $pdo->prepare('
            INSERT INTO job_post_cv_submissions (alumni_id, job_post_id)
            VALUES (?, ?)
        ');
        $sql->execute([
            $data['alumni_id'],
            $data["job_post_id"],
        ]);

        return self::findById($pdo, (int) $pdo->lastInsertId());
    }

    public static function markAsReviewed(PDO $pdo, int $id): self
    {
        $sql = $pdo->prepare('UPDATE job_post_cv_submissions SET status = ? WHERE id = ?');
        $sql->execute([CvReviewStatus::REVIEWED->value, $id]);
        return self::findById($pdo, $id);
    }

    public static function deleteById(PDO $pdo, int $id): bool
    {
        $sql = $pdo->prepare('
            DELETE FROM job_post_cv_submissions
            WHERE id = ?
        ');
        $sql->execute([$id]);
        return $sql->rowCount() > 0;
    }

    public function toArray(): array
    {
        return [
            'id'          => $this->id,
            'alumni_id'   => $this->alumniId,
            'job_post_id' => $this->jobPostId,
            'status'      => $this->status->value,
        ];
    }
}