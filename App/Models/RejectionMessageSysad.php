<?php

namespace App\Models;

use App\Core\Migratable;
use DateTime;
use PDO;

class RejectionMessageSysad implements Migratable
{
    public int $id;
    public int $sysadId;
    public int $companyId;
    public string $message;
    public DateTime $createdAt;

    public static function table(): string
    {
        return "rejection_message_sysads";
    }

    public static function migrate(PDO $pdo): void
    {
        $table = self::table();
        $sql = $pdo->prepare("CREATE TABLE IF NOT EXISTS {$table}(
            id INT AUTO_INCREMENT PRIMARY KEY,
            sysad_id INT,
            company_id INT NOT NULL,
            message VARCHAR(1000) NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (sysad_id) REFERENCES sysads(id) ON DELETE CASCADE,
            FOREIGN KEY (company_id) REFERENCES companies(id) ON DELETE CASCADE
        );");
        $sql->execute();
    }

    public static function fromRow(array $row): self
    {
        $instance            = new self();
        $instance->id        = (int) $row['id'];
        $instance->sysadId   = (int) $row['sysad_id'];
        $instance->companyId = (int) $row['company_id'];
        $instance->message   = $row['message'];        
        $instance->createdAt = new DateTime($row['created_at']);
        return $instance;
    }

    public static function findById(PDO $pdo, int $id): ?self
    {
        $sql = $pdo->prepare('SELECT * FROM rejection_message_sysads WHERE id = ? LIMIT 1');
        $sql->execute([$id]);

        $row = $sql->fetch();
        return $row ? self::fromRow($row) : null;
    }

    public static function findByCompanyId(PDO $pdo, int $id): ?self
    {
        $sql = $pdo->prepare('SELECT * FROM rejection_message_sysads WHERE company_id = ? LIMIT 1');
        $sql->execute([$id]);

        $row = $sql->fetch();
        return $row ? self::fromRow($row) : null;
    }

    public static function create(PDO $pdo, array $data): self
    {
        $sql = $pdo->prepare('
            INSERT INTO rejection_message_sysads (sysad_id, company_id, message)
            VALUES (?, ?, ?)
        ');

        $sql->execute([
            $data['sysad_id'],
            $data['company_id'],
            $data['message'],
        ]);

        return self::findById($pdo, (int) $pdo->lastInsertId());
    }

    public static function deleteAllByCompanyId(PDO $pdo, int $id): bool
    {
        $sql = $pdo->prepare('DELETE FROM rejection_message_sysads WHERE company_id = ?');
        $sql->execute([$id]);
        return $sql->rowCount() > 0;
    }

    public function toArray(): array
    {
        return [
            'id'         => $this->id,
            'sysad_id'   => $this->sysadId,
            'company_id' => $this->companyId,
            'message'    => $this->message,
            'created_at' => $this->createdAt->format('Y-m-d H:i:s'),
        ];
    }
}