<?php

namespace App\Models;

use App\Core\Migratable;
use DateTime;
use PDO;

class RevisionMessageAppeal implements Migratable
{
    public int $id;
    public int $messageId;
    public string $message;
    public DateTime $createdAt;

    public static function table(): string
    {
        return "revision_message_appeals";
    }

    public static function migrate(PDO $pdo): void
    {
        $table = self::table();
        $sql = $pdo->prepare("CREATE TABLE IF NOT EXISTS {$table}(
            id INT AUTO_INCREMENT PRIMARY KEY,
            message_id INT NOT NULL,
            message TEXT NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        );");
        $sql->execute();
    }

    public static function fromRow(array $row): self
    {
        $instance            = new self();
        $instance->id        = (int) $row['id'];
        $instance->messageId = (int) $row['message_id'];
        $instance->message   = $row['message'];
        $instance->createdAt = new DateTime($row['created_at']);
        return $instance;
    }

    public static function findById(PDO $pdo, int $id): ?self
    {
        $sql = $pdo->prepare('SELECT * FROM revision_message_appeals WHERE id = ? LIMIT 1');
        $sql->execute([$id]);

        $row = $sql->fetch();
        return $row ? self::fromRow($row) : null;
    }

    public static function findByMessageId(PDO $pdo, int $id): ?self
    {
        $sql = $pdo->prepare('SELECT * FROM revision_message_appeals WHERE message_id = ? LIMIT 1');
        $sql->execute([$id]);

        $row = $sql->fetch();
        return $row ? self::fromRow($row) : null;
    }

    public static function create(PDO $pdo, array $data): self
    {
        $sql = $pdo->prepare('
            INSERT INTO revision_message_appeals (message_id, message)
            VALUES (?, ?)
        ');

        $sql->execute([
            $data['message_id'],
            $data['message']
        ]);

        return self::findById($pdo, (int) $pdo->lastInsertId());
    }

    public static function deleteByMessageId(PDO $pdo, int $id): bool
    {
        $sql = $pdo->prepare('
            DELETE FROM revision_message_appeals
            WHERE message_id = ?
        ');

        $sql->execute([$id]);

        return $sql->rowCount() > 0;
    }

    public function toArray(): array
    {
        return [
            'id'         => $this->id,
            'message_id' => $this->messageId,
            'message'    => $this->message,
            'created_at' => $this->createdAt->format('Y-m-d H:i:s'),
        ];
    }
}