<?php

namespace App\Models;

use App\Core\Migratable;
use App\Core\Types\WorkEmploymentType;
use App\Core\Types\WorkSetup;
use App\Core\Types\WorkShift;
use DateTime;
use PDO;

class JobPost implements Migratable
{
    public int $id;
    public int $companyId;
    public string $position;
    public string $description;
    public string $address;
    public int $salaryMin;
    public int $salaryMax;
    public WorkShift $workShift;
    public WorkSetup $workSetup;
    public WorkEmploymentType $workEmploymentType;
    public int $slots;
    public ?string $additionInfo;
    public DateTime $openUntil;
    public bool $open;
    public DateTime $createdAt;
    public DateTime $updatedAt;

    public static function table(): string
    {
        return "job_posts";
    }

    public static function migrate(PDO $pdo): void
    {
        $table = self::table();
        $sql = $pdo->prepare("CREATE TABLE IF NOT EXISTS {$table}(
            id INT AUTO_INCREMENT PRIMARY KEY,
            company_id INT NOT NULL,
            position VARCHAR(255) NOT NULL,
            description VARCHAR(3000) NOT NULL,
            address VARCHAR(512) NOT NULL,
            salary_min INT NOT NULL,
            salary_max INT NOT NULL,
            work_shift ENUM(
                'Day',
                'Evening / Swing',
                'Night / Graveyard',
                'Morning'
            ) DEFAULT 'Day',
            work_setup ENUM(
                'On-site',
                'Remote',
                'Hybrid'
            ) DEFAULT 'On-site',
            work_employment_type ENUM(
                'Full-time',
                'Part-time',
                'Contract',
                'Internship',
                'Freelance'
            ) DEFAULT 'Full-time',
            slots INT NOT NULL,
            additional_info VARCHAR(3000) DEFAULT NULL,
            open_until DATE NOT NULL,
            open BOOLEAN DEFAULT TRUE,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            FOREIGN KEY (company_id) REFERENCES companies(id) ON DELETE CASCADE,
            CONSTRAINT salary_check CHECK(salary_min <= salary_max)
        );");
        $sql->execute();
    }

    public static function fromRow(array $row): self
    {
        $instance                     = new self();
        $instance->id                 = (int) $row['id'];
        $instance->companyId          = (int) $row['company_id'];
        $instance->position           = $row['position'];
        $instance->description        = $row['description'];
        $instance->address            = $row['address'];
        $instance->salaryMin          = (int) $row['salary_min'];
        $instance->salaryMax          = (int) $row['salary_max'];
        $instance->workShift          = WorkShift::from($row['work_shift']);
        $instance->workSetup          = WorkSetup::from($row['work_setup']);
        $instance->workEmploymentType = WorkEmploymentType::from($row['work_employment_type']);
        $instance->slots              = $row['slots'];
        $instance->additionInfo       = $row['additional_info'];
        $instance->openUntil          = new DateTime($row['open_until']);
        $instance->open               = (bool) $row['open'];
        $instance->createdAt          = new DateTime($row['created_at']);
        $instance->updatedAt          = new DateTime($row['updated_at']);
        return $instance;
    }

    public static function findById(PDO $pdo, int $id): ?self
    {
        $sql = $pdo->prepare('SELECT * FROM job_posts WHERE id = ? LIMIT 1');
        $sql->execute([$id]);

        $row = $sql->fetch();
        return $row ? self::fromRow($row) : null;
    }

    public static function findByPositionAndCompanyId(PDO $pdo, string $position, int $id): ?self
    {
        $sql = $pdo->prepare('SELECT * FROM job_posts WHERE LOWER(position) = LOWER(?) AND company_id = ? LIMIT 1');
        $sql->execute([trim($position), $id]);

        $row = $sql->fetch();
        return $row ? self::fromRow($row) : null;
    }

    public static function create(PDO $pdo, array $data): self
    {
        $sql = $pdo->prepare('
            INSERT INTO job_posts (
                company_id,
                position,
                description,
                address,
                salary_min,
                salary_max,
                work_shift,
                work_setup,
                work_employment_type,
                slots,
                additional_info,
                open_until
            )
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ');

        $sql->execute([
            $data['company_id'],
            $data['position'],
            $data['description'],
            $data['address'],
            $data['salary_min'],
            $data['salary_max'],
            $data['work_shift'],
            $data['work_setup'],
            $data['work_employment_type'],
            $data['slots'],
            $data['additional_info'],
            $data['open_until'],
        ]);

        return self::findById($pdo, (int) $pdo->lastInsertId());
    }

    public static function update(PDO $pdo, int $id, array $data): ?self
    {
        $sql = $pdo->prepare('
            UPDATE job_posts
            SET 
                position             = ?,
                description          = ?,
                address              = ?,
                salary_min           = ?,
                salary_max           = ?,
                work_shift           = ?,
                work_setup           = ?,
                work_employment_type = ?,
                slots                = ?,
                additional_info      = ?,
                open                 = ?
            WHERE id = ?
        ');

        $sql->execute([
            $data['position'],
            $data['description'],
            $data['address'],
            $data['salary_min'],
            $data['salary_max'],
            $data['work_shift'],
            $data['work_setup'],
            $data['work_employment_type'],
            $data['slots'],
            $data['additional_info'],
            $data['open'],
            $id,
        ]);

        return self::findById($pdo, $id);
    }

    public static function delete(PDO $pdo, $id): bool
    {
        $sql = $pdo->prepare('DELETE FROM job_posts WHERE id = ?');
        $sql->execute([$id]);
        return $sql->rowCount() > 0;
    }

    public function toArray(): array
    {
        return [
            'id'                   => $this->id,
            'company_id'           => $this->companyId,
            'position'             => $this->position,
            'description'          => $this->description,
            'address'              => $this->address,
            'salary_min'           => $this->salaryMin,
            'salary_max'           => $this->salaryMax,
            'work_shift'           => $this->workShift->value,
            'work_setup'           => $this->workSetup->value,
            'work_employment_type' => $this->workEmploymentType->value,
            'slots'                => $this->slots,
            'additional_info'      => $this->additionInfo,
            'open_until'           => $this->openUntil->format('Y-m-d'),
            'open'                 => $this->open,
            'created_at'           => $this->createdAt->format('Y-m-d H:i:s'),
            'updated_at'           => $this->updatedAt->format('Y-m-d H:i:s'),
        ];
    }
}