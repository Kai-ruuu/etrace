<?php

namespace App\Models;

use App\Core\Migratable;
use App\Core\Types\RequirementKey;
use App\Utils\ArrayLogger;
use DateTime;
use PDO;

class RevisionMessage implements Migratable
{
    public int $id;
    public int $companyId;
    public int $pstaffId;
    public string $message;
    public RequirementKey $requirementColumn;
    public DateTime $createdAt;

    public static function table(): string
    {
        return "revision_messages";
    }

    public static function migrate(PDO $pdo): void
    {
        $table = self::table();
        $sql = $pdo->prepare("CREATE TABLE IF NOT EXISTS {$table}(
            id INT AUTO_INCREMENT PRIMARY KEY,
            company_id INT NOT NULL,
            pstaff_id INT NOT NULL,
            message VARCHAR(1000) NOT NULL,
            requirement_column VARCHAR(65) NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (company_id) REFERENCES companies(id) ON DELETE CASCADE,
            FOREIGN KEY (pstaff_id) REFERENCES pstaffs(id) ON DELETE CASCADE
        );");
        $sql->execute();
    }

    public static function fromRow(array $row): self
    {
        $instance                    = new self();
        $instance->id                = (int) $row['id'];
        $instance->companyId         = (int) $row['company_id'];
        $instance->pstaffId          = (int) $row['pstaff_id'];
        $instance->message           = $row['message'];
        $instance->requirementColumn = RequirementKey::from($row['requirement_column']);
        $instance->createdAt         = new DateTime($row['created_at']);
        return $instance;
    }

    public static function findById(PDO $pdo, int $id): ?self
    {
        $sql = $pdo->prepare('SELECT * FROM revision_messages WHERE id = ? LIMIT 1');
        $sql->execute([$id]);

        $row = $sql->fetch();
        return $row ? self::fromRow($row) : null;
    }

    public static function findByCompanyId(PDO $pdo, int $id): ?self
    {
        $sql = $pdo->prepare('SELECT * FROM revision_messages WHERE company_id = ? LIMIT 1');
        $sql->execute([$id]);

        $row = $sql->fetch();
        return $row ? self::fromRow($row) : null;
    }

    public static function findAllByCompanyId(PDO $pdo, int $id): ?array
    {
        $sql = $pdo->prepare('SELECT * FROM revision_messages WHERE company_id = ?');
        $sql->execute([$id]);

        $rows = $sql->fetchAll();
        return count($rows) > 0 ? array_map(fn($r) => self::fromRow($r), $rows) : null;
    }

    public static function findByCompanyIdAndReqKey(PDO $pdo, int $id, RequirementKey $key): ?self
    {
        $sql = $pdo->prepare('SELECT * FROM revision_messages WHERE requirement_column = ? AND company_id = ? LIMIT 1');
        $sql->execute([$key->value, $id]);

        $row = $sql->fetch();
        return $row ? self::fromRow($row) : null;
    }

    public static function create(PDO $pdo, array $data): self
    {
        ArrayLogger::log($data);
        
        $sql = $pdo->prepare('
            INSERT INTO revision_messages (company_id, pstaff_id, message, requirement_column)
            VALUES (?, ?, ?, ?)
        ');

        $sql->execute([
            $data['company_id'],
            $data['pstaff_id'],
            $data["message"],
            $data["requirement_column"],
        ]);

        return self::findById($pdo, (int) $pdo->lastInsertId());
    }

    public static function deleteAllByCompanyId(PDO $pdo, int $id): bool
    {
        $sql = $pdo->prepare('DELETE FROM revision_messages WHERE company_id = ?');
        $sql->execute([$id]);
        return $sql->rowCount() > 0;
    }

    public static function deleteAllByCompanyIdAndRequirementKey(PDO $pdo, int $id, RequirementKey $key): bool
    {
        $sql = $pdo->prepare('DELETE FROM revision_messages WHERE company_id = ? AND requirement_column = ?');
        $sql->execute([$id, $key->value]);
        return $sql->rowCount() > 0;
    }

    public function toArray(): array
    {
        return [
            'id'                 => $this->id,
            'company_id'         => $this->companyId,
            'pstaff_id'          => $this->pstaffId,
            'message'            => $this->message,
            'requirement_column' => $this->requirementColumn->value,
            'created_at'         => $this->createdAt->format('Y-m-d H:i:s'),
        ];
    }
}