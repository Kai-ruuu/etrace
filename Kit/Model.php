<?php

namespace App\Models;

use App\Core\Migratable;
use DateTime;
use PDO;

class Model implements Migratable
{
    public int $id;
    public DateTime $createdAt;
    public DateTime $updatedAt;

    public static function table(): string
    {
        return "table_name";
    }

    public static function migrate(PDO $pdo): void
    {
        $table = self::table();
        $sql = $pdo->prepare("CREATE TABLE IF NOT EXISTS {$table}(
            id INT AUTO_INCREMENT PRIMARY KEY,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        );");
        $sql->execute();
    }

    public static function fromRow(array $row): self
    {
        $instance            = new self();
        $instance->id        = (int) $row['id'];
        $instance->createdAt = new DateTime($row['created_at']);
        $instance->updatedAt = new DateTime($row['updated_at']);
        return $instance;
    }

    public static function findAll(PDO $pdo): array
    {
        $sql = $pdo->prepare('SELECT * FROM table_name');
        $sql->execute();

        return array_map(fn($row) => self::fromRow($row), $sql->fetchAll());
    }

    public static function findById(PDO $pdo, int $id): ?self
    {
        $sql = $pdo->prepare('SELECT * FROM table_name WHERE id = ? LIMIT 1');
        $sql->execute([$id]);

        $row = $sql->fetch();
        return $row ? self::fromRow($row) : null;
    }

    public static function create(PDO $pdo, array $data): self
    {
        $sql = $pdo->prepare('
            INSERT INTO table_name (data1, data2)
            VALUES (:data1, :data2)
        ');

        $sql->execute([
            ':data1' => $data['data1'],
            ':data2' => $data["data2"],
        ]);

        return self::findById($pdo, (int) $pdo->lastInsertId());
    }

    public static function update(PDO $pdo, int $id, array $data): ?self
    {
        $sql = $pdo->prepare('
            UPDATE table_name
            SET data1 = :data1
            WHERE id = :id
        ');

        $sql->execute([
            ':data1' => $data['data1'],
            ':id'      => $id,
        ]);

        return self::findById($pdo, $id);
    }

    public function toArray(): array
    {
        return [
            'id'         => $this->id,
            'created_at' => $this->createdAt->format('Y-m-d H:i:s'),
            'updated_at' => $this->updatedAt->format('Y-m-d H:i:s'),
        ];
    }
}