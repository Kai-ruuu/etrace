<?php

namespace App\Models;

use App\Core\Migratable;
use DateTime;
use PDO;

class SocialMedia implements Migratable
{
    public int $id;
    public int $alumniId;
    public int $platformId;
    public string $url;
    public DateTime $createdAt;
    public DateTime $updatedAt;

    public static function table(): string
    {
        return "social_medias";
    }

    public static function migrate(PDO $pdo): void
    {
        $table = self::table();
        $sql = $pdo->prepare("CREATE TABLE IF NOT EXISTS {$table}(
            id INT AUTO_INCREMENT PRIMARY KEY,
            alumni_id INT NOT NULL,
            platform_id INT NOT NULL,
            url VARCHAR(512) UNIQUE NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            FOREIGN KEY (alumni_id) REFERENCES alumni(id) ON DELETE CASCADE,
            FOREIGN KEY (platform_id) REFERENCES social_media_platforms(id) ON DELETE CASCADE
        );");
        $sql->execute();
    }

    public static function fromRow(array $row): self
    {
        $instance             = new self();
        $instance->id         = (int) $row['id'];
        $instance->alumniId   = (int) $row['alumni_id'];
        $instance->platformId = (int) $row['platform_id'];
        $instance->url        = $row['url'];
        $instance->createdAt  = new DateTime($row['created_at']);
        $instance->updatedAt  = new DateTime($row['updated_at']);
        return $instance;
    }

    public static function findAllByAlumniId(PDO $pdo, int $id): array
    {
        $sql = $pdo->prepare('SELECT * FROM social_medias WHERE alumni_id = ?');
        $sql->execute([$id]);

        return array_map(fn($row) => self::fromRow($row), $sql->fetchAll());
    }

    public static function findById(PDO $pdo, int $id): ?self
    {
        $sql = $pdo->prepare('SELECT * FROM social_medias WHERE id = ? LIMIT 1');
        $sql->execute([$id]);

        $row = $sql->fetch();
        return $row ? self::fromRow($row) : null;
    }

    public static function create(PDO $pdo, array $data): self
    {
        $sql = $pdo->prepare('
            INSERT INTO social_medias (alumni_id, platform_id, url)
            VALUES (?, ?, ?)
        ');
        $sql->execute([
            $data['alumni_id'],
            $data['platform_id'],
            $data['url']
        ]);

        return self::findById($pdo, (int) $pdo->lastInsertId());
    }

    public static function update(PDO $pdo, int $id, array $data): ?self
    {
        $sql = $pdo->prepare('
            UPDATE social_medias
            SET url = ?
            WHERE id = ?
        ');
        $sql->execute([
            $data['url'],
            $data['id']
        ]);

        return self::findById($pdo, $id);
    }

    public function toArray(): array
    {
        return [
            'id'          => $this->id,
            'alumni_id'   => $this->alumniId,
            'platform_id' => $this->platformId,
            'url'   => $this->url,
            'created_at'  => $this->createdAt->format('Y-m-d H:i:s'),
            'updated_at'  => $this->updatedAt->format('Y-m-d H:i:s'),
        ];
    }
}