<?php

namespace App\Models;

use App\Core\Migratable;
use DateTime;
use PDO;

class ProfilePesoStaff implements Migratable
{
    public int $id;
    public int $userId;
    public string $firstName;
    public ?string $middleName;
    public string $lastName;
    public bool $agreedToConsent;
    public DateTime $createdAt;
    public DateTime $updatedAt;

    public static function table(): string
    {
        return "pstaffs";
    }

    public static function migrate(PDO $pdo): void
    {
        $table = self::table();
        $sql = $pdo->prepare("CREATE TABLE IF NOT EXISTS {$table}(
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL,
            first_name VARCHAR(50) NOT NULL,
            middle_name VARCHAR(50) DEFAULT NULL,
            last_name VARCHAR(50) NOT NULL,
            agreed_to_consent BOOLEAN NOT NULL DEFAULT FALSE,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
        )");
        $sql->execute();
    }

    public static function fromRow(array $row): self
    {
        $instance                  = new self();
        $instance->id              = (int) $row['id'];
        $instance->userId          = (int) $row['user_id'];
        $instance->firstName       = $row['first_name'];
        $instance->middleName      = $row['middle_name'];
        $instance->lastName        = $row['last_name'];
        $instance->agreedToConsent = (bool) $row['agreed_to_consent'];
        $instance->createdAt       = new DateTime($row['created_at']);
        $instance->updatedAt       = new DateTime($row['updated_at']);
        return $instance;
    }

    public static function findAll(PDO $pdo): array
    {
        $sql = $pdo->prepare('SELECT * FROM pstaffs');
        $sql->execute();

        return array_map(fn($row) => self::fromRow($row), $sql->fetchAll());
    }

    public static function findById(PDO $pdo, int $id): ?self
    {
        $sql = $pdo->prepare('SELECT * FROM pstaffs WHERE id = ? LIMIT 1');
        $sql->execute([$id]);

        $row = $sql->fetch();
        return $row ? self::fromRow($row) : null;
    }

    public static function findByUserId(PDO $pdo, int $id): ?self
    {
        $sql = $pdo->prepare('SELECT * FROM pstaffs WHERE user_id = ? LIMIT 1');
        $sql->execute([$id]);

        $row = $sql->fetch();
        return $row ? self::fromRow($row) : null;
    }

    public static function create(PDO $pdo, array $data): self
    {
        $sql = $pdo->prepare('
            INSERT INTO pstaffs (user_id, first_name, middle_name, last_name)
            VALUES (:user_id, :first_name, :middle_name, :last_name)
        ');

        $sql->execute([
            ':user_id'     => $data['user_id'],
            ':first_name'  => $data['first_name'],
            ':middle_name' => $data['middle_name'],
            ':last_name'   => $data['last_name'],
        ]);

        return self::findById($pdo, (int) $pdo->lastInsertId());
    }

    public static function update(PDO $pdo, int $id, array $data): ?self
    {
        $sql = $pdo->prepare('
            UPDATE pstaffs
            SET
                first_name        = :first_name,
                middle_name       = :middle_name,
                last_name         = :last_name,
                agreed_to_consent = :agreed_to_consent
            WHERE id = :id
        ');

        $sql->execute([
            ':first_name'        => $data['first_name'],
            ':middle_name'       => $data['middle_name'],
            ':last_name'         => $data['last_name'],
            ':agreed_to_consent' => $data['agreed_to_consent'],
            ':id'                => $id,
        ]);

        return self::findById($pdo, $id);
    }

    public function toArray(): array
    {
        return [
            'id'                => $this->id,
            'user_id'           => $this->userId,
            'first_name'        => $this->firstName,
            'middle_name'       => $this->middleName,
            'last_name'         => $this->lastName,
            'agreed_to_consent' => $this->agreedToConsent,
            'created_at'        => $this->createdAt->format('Y-m-d H:i:s'),
            'updated_at'        => $this->updatedAt->format('Y-m-d H:i:s'),
        ];
    }
}