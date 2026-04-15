<?php

namespace App\Models;

use App\Core\Migratable;
use DateTime;
use PDO;

class SocialMediaPlatform implements Migratable
{
    public int $id;
    public string $name;
    public DateTime $createdAt;

    public static function table(): string
    {
        return "social_media_platforms";
    }

    public static function migrate(PDO $pdo): void
    {
        $table = self::table();
        $sql = $pdo->prepare("CREATE TABLE IF NOT EXISTS {$table}(
            id INT AUTO_INCREMENT PRIMARY KEY,
            name VARCHAR(25) UNIQUE NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        );");
        $sql->execute();
    }

    public static function fromRow(array $row): self
    {
        $instance            = new self();
        $instance->id        = (int) $row['id'];
        $instance->name      = $row['name'];
        $instance->createdAt = new DateTime($row['created_at']);
        return $instance;
    }

    public static function findAll(PDO $pdo): array
    {
        $sql = $pdo->prepare('SELECT * FROM social_media_platforms');
        $sql->execute();

        return array_map(fn($row) => self::fromRow($row), $sql->fetchAll());
    }

    public static function findById(PDO $pdo, int $id): ?self
    {
        $sql = $pdo->prepare('SELECT * FROM social_media_platforms WHERE id = ? LIMIT 1');
        $sql->execute([$id]);

        $row = $sql->fetch();
        return $row ? self::fromRow($row) : null;
    }

    public static function findByName(PDO $pdo, string $name): ?self
    {
        $sql = $pdo->prepare('SELECT * FROM social_media_platforms WHERE LOWER(name) = LOWER(?) LIMIT 1');
        $sql->execute([$name]);

        $row = $sql->fetch();
        return $row ? self::fromRow($row) : null;
    }

    public static function create(PDO $pdo, array $data): self
    {
        $sql = $pdo->prepare('
            INSERT INTO social_media_platforms (name)
            VALUES (?)
        ');

        $sql->execute([$data['name']]);

        return self::findById($pdo, (int) $pdo->lastInsertId());
    }

    public function toArray(): array
    {
        return [
            'id'         => $this->id,
            'name'       => $this->name,
            'created_at' => $this->createdAt->format('Y-m-d H:i:s'),
        ];
    }
}