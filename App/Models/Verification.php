<?php

namespace App\Models;

use App\Core\Migratable;
use DateTime;
use PDO;

class Verification implements Migratable
{
    public int $id;
    public int $userId;
    public string $token;
    public bool $used;
    public DateTime $expiresAt;

    public static function table(): string
    {
        return "verifications";
    }

    public static function migrate(PDO $pdo): void
    {
        $table = self::table();
        $sql = $pdo->prepare("CREATE TABLE IF NOT EXISTS {$table}(
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL,
            token VARCHAR(100) NOT NULL,
            used BOOLEAN DEFAULT FALSE,
            expires_at TIMESTAMP DEFAULT (NOW() + INTERVAL 24 HOUR),
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
        );");
        $sql->execute();
    }

    public static function fromRow(array $row): self
    {
        $instance            = new self();
        $instance->id        = (int) $row['id'];
        $instance->userId    = (int) $row['user_id'];
        $instance->token     = $row['token'];
        $instance->used      = (bool) $row['used'];
        $instance->expiresAt = new DateTime($row['expires_at']);
        return $instance;
    }

    public static function findById(PDO $pdo, int $id): ?self
    {
        $sql = $pdo->prepare('SELECT * FROM verifications WHERE id = ? LIMIT 1');
        $sql->execute([$id]);

        $row = $sql->fetch();
        return $row ? self::fromRow($row) : null;
    }

    public static function findByToken(PDO $pdo, string $token): ?self
    {
        $sql = $pdo->prepare('SELECT * FROM verifications WHERE token = ? LIMIT 1');
        $sql->execute([$token]);

        $row = $sql->fetch();
        return $row ? self::fromRow($row) : null;
    }

    public static function create(PDO $pdo, array $data): self
    {
        $sql = $pdo->prepare('
            INSERT INTO verifications (user_id, token)
            VALUES (?, ?)
        ');

        $sql->execute([
            $data['user_id'],
            $data["token"],
        ]);

        return self::findById($pdo, (int) $pdo->lastInsertId());
    }

    public static function setAsUsed(PDO $pdo, int $id): ?self
    {
        $sql = $pdo->prepare('
            UPDATE verifications
            SET used = ?
            WHERE id = ?
        ');

        $sql->execute([true, $id]);

        return self::findById($pdo, $id);
    }

    public function toArray(): array
    {
        return [
            'id'         => $this->id,
            'user_id'    => $this->userId,
            'token'      => $this->token,
            'used'       => $this->used,
            'expires_at' => $this->expiresAt->format('Y-m-d H:i:s'),
        ];
    }
}