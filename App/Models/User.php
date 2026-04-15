<?php

namespace App\Models;

use App\Core\Migratable;
use App\Core\Types\Role;
use DateTime;
use PDO;

class User implements Migratable
{
    public int $id;
    public string $email;
    public string $passwordHash;
    public bool $enabled;
    public Role $role;
    public bool $emailVerified;
    public ?DateTime $emailVerifiedAt;
    public DateTime $createdAt;
    public DateTime $updatedAt;

    public static function table(): string
    {
        return "users";
    }

    public static function migrate(PDO $pdo): void
    {
        $table = self::table();
        $sql = $pdo->prepare("CREATE TABLE IF NOT EXISTS {$table}(
            id INT AUTO_INCREMENT PRIMARY KEY,
            email VARCHAR(255) UNIQUE NOT NULL,
            password_hash VARCHAR(255) NOT NULL,
            enabled BOOLEAN NOT NULL DEFAULT TRUE,
            email_verified BOOLEAN NOT NULL DEFAULT FALSE,
            email_verified_at DATE DEFAULT NULL,
            role ENUM('sysad','dean','pstaff','company','alumni') NOT NULL DEFAULT 'alumni',
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        );");
        $sql->execute();
    }

    public static function fromRow(array $row): self
    {
        $user                  = new self();
        $user->id              = (int) $row['id'];
        $user->email           = $row['email'];
        $user->passwordHash    = $row['password_hash'];
        $user->enabled         = (bool) $row['enabled'];
        $user->emailVerified   = (bool) $row['email_verified'];
        $user->emailVerifiedAt = $row['email_verified_at'] ? new DateTime($row['email_verified_at']) : null;
        $user->role            = Role::from($row['role']);
        $user->createdAt       = new DateTime($row['created_at']);
        $user->updatedAt       = new DateTime($row['updated_at']);

        return $user;
    }

    public static function findAll(PDO $pdo): array
    {
        $sql = $pdo->prepare('SELECT * FROM users');
        $sql->execute();

        return array_map(fn($row) => self::fromRow($row), $sql->fetchAll());
    }

    public static function findById(PDO $pdo, int $id): ?self
    {
        $sql = $pdo->prepare('SELECT * FROM users WHERE id = ? LIMIT 1');
        $sql->execute([$id]);

        $row = $sql->fetch();
        return $row ? self::fromRow($row) : null;
    }

    public static function findByEmail(PDO $pdo, string $email): ?self
    {
        $sql = $pdo->prepare('SELECT * FROM users WHERE email = ? LIMIT 1');
        $sql->execute([$email]);

        $row = $sql->fetch();
        return $row ? self::fromRow($row) : null;
    }

    public static function create(PDO $pdo, array $data): self
    {
        $sql = $pdo->prepare('
            INSERT INTO users (email, password_hash, enabled, email_verified, email_verified_at, role)
            VALUES (:email, :password, :enabled, :email_verified, :email_verified_at, :role)
        ');

        $sql->execute([
            ':email'             => $data['email'],
            ':password'          => password_hash($data['password'], PASSWORD_BCRYPT),
            ':enabled'           => $data['enabled'],
            ':email_verified'    => $data['email_verified'] ?? false,
            ':email_verified_at' => isset($data['email_verified_at']) ? $data['email_verified_at'] : null,
            ':role'              => $data['role']    ?? Role::ALUMNI->value,
        ]);

        return self::findById($pdo, (int) $pdo->lastInsertId());
    }

    public static function update(PDO $pdo, int $id, array $data): ?self
    {
        $sql = $pdo->prepare('
            UPDATE users
            SET
                enabled           = :enabled,
                email_verified    = :email_verified,
                email_verified_at = :email_verified_at
            WHERE id = :id
        ');

        $sql->execute([
            ':enabled'           => $data['enabled'],
            ':email_verified'    => $data['email_verified'],
            ':email_verified_at' => $data['email_verified_at'],
            ':id'                => $id,
        ]);

        return self::findById($pdo, $id);
    }

    public static function updatePassword(PDO $pdo, int $id, string $newPassword): bool
    {
        $sql = $pdo->prepare('
            UPDATE users
            SET password_hash = :password_hash, updated_at = NOW()
            WHERE id = :id
        ');

        return $sql->execute([
            ':password_hash' => password_hash($newPassword, PASSWORD_BCRYPT),
            ':id'            => $id,
        ]);
    }

    public function toArray(): array
    {
        return [
            'id'                => $this->id,
            'email'             => $this->email,
            'enabled'           => $this->enabled,
            'email_verified'    => $this->emailVerified,
            'email_verified_at' => $this->emailVerifiedAt ? $this->emailVerifiedAt->format('Y-m-d') : null,
            'role'              => $this->role->value,
            'created_at'        => $this->createdAt->format('Y-m-d H:i:s'),
            'updated_at'        => $this->updatedAt->format('Y-m-d H:i:s'),
        ];
    }
}