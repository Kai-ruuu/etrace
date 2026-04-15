<?php

namespace App\Models;

use App\Core\Migratable;
use DateTime;
use PDO;

class RejectionMessageDean implements Migratable
{
    public int $id;
    public int $deanId;
    public int $alumniId;
    public string $message;
    public DateTime $createdAt;

    public static function table(): string
    {
        return "rejection_message_deans";
    }

    public static function migrate(PDO $pdo): void
    {
        $table = self::table();
        $sql = $pdo->prepare("CREATE TABLE IF NOT EXISTS {$table}(
            id INT AUTO_INCREMENT PRIMARY KEY,
            dean_id INT NOT NULL,
            alumni_id INT NOT NULL,
            message VARCHAR(1000) NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (alumni_id) REFERENCES alumni(id) ON DELETE CASCADE,
            FOREIGN KEY (dean_id) REFERENCES deans(id) ON DELETE CASCADE
        );");
        $sql->execute();
    }

    public static function fromRow(array $row): self
    {
        $instance            = new self();
        $instance->id        = (int) $row['id'];

        $instance->deanId    = (int) $row['dean_id'];
        $instance->alumniId  = (int) $row['alumni_id'];
        $instance->message   = $row['message'];
        
        $instance->createdAt = new DateTime($row['created_at']);
        return $instance;
    }

    public static function findById(PDO $pdo, int $id): ?self
    {
        $sql = $pdo->prepare('SELECT * FROM rejection_message_deans WHERE id = ? LIMIT 1');
        $sql->execute([$id]);

        $row = $sql->fetch();
        return $row ? self::fromRow($row) : null;
    }

    public static function findByAlumniId(PDO $pdo, int $id): ?self
    {
        $sql = $pdo->prepare('SELECT * FROM rejection_message_deans WHERE alumni_id = ? LIMIT 1');
        $sql->execute([$id]);

        $row = $sql->fetch();
        return $row ? self::fromRow($row) : null;
    }

    public static function create(PDO $pdo, array $data): self
    {
        $sql = $pdo->prepare('
            INSERT INTO rejection_message_deans (dean_id, alumni_id, message)
            VALUES (?, ?, ?)
        ');

        $sql->execute([
            $data['dean_id'],
            $data['alumni_id'],
            $data['message'],
        ]);

        return self::findById($pdo, (int) $pdo->lastInsertId());
    }

    public static function deleteAllByAlumniId(PDO $pdo, int $id): bool
    {
        $sql = $pdo->prepare('DELETE FROM rejection_message_deans WHERE alumni_id = ?');
        $sql->execute([$id]);

        return $sql->rowCount() > 0;
    }

    public function toArray(): array
    {
        return [
            'id'         => $this->id,
            'dean_id'   => $this->deanId,
            'alumni_id' => $this->alumniId,
            'message'    => $this->message,
            'created_at' => $this->createdAt->format('Y-m-d H:i:s'),
        ];
    }
}