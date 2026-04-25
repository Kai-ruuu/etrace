<?php

namespace App\Models;

use App\Core\Types\CivilStatus;
use App\Core\Types\EmploymentStatus;
use App\Core\Types\Gender;
use App\Core\Migratable;
use App\Core\Types\VerificationStatus;
use DateTime;
use PDO;

class ProfileAlumni implements Migratable
{
    public int $id;
    public int $userId;
    public ?string $nameExtension;
    public string $firstName;
    public ?string $middleName;
    public string $lastName;
    public string $birthDate;
    public string $birthPlace;
    public Gender $gender;
    public string $studentNumber;
    public string $phoneNumber;
    public int $courseId;
    public int $graduationYear;
    public CivilStatus $civilStatus;
    public string $address;
    public EmploymentStatus $employmentStatus;
    public string $profilePicture;
    public string $cv;
    public VerificationStatus $verStatDean;
    public DateTime $createdAt;
    public DateTime $updatedAt;

    public static function table(): string
    {
        return "alumni";
    }

    public static function migrate(PDO $pdo): void
    {
        $table = self::table();
        $sql = $pdo->prepare("CREATE TABLE IF NOT EXISTS {$table}(
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL,
            name_extension VARCHAR(10) DEFAULT NULL,
            first_name VARCHAR(50) NOT NULL,
            middle_name VARCHAR(50) DEFAULT NULL,
            last_name VARCHAR(50) NOT NULL,
            birth_date DATE NOT NULL,
            birth_place VACHAR(512) DEFAULT NULL,
            gender ENUM('Male', 'Female') DEFAULT 'Male',
            student_numberR VARCHAR(255) DEFAULT NULL,
            phone_number VARCHAR(25) NOT NULL,
            course_id INT NOT NULL,
            graduation_year INT NOT NULL,
            civil_status ENUM('Single','Married','Widowed','Separated') DEFAULT 'Single',
            address VARCHAR(512) NOT NULL,
            employment_status ENUM('Unemployed','Employed','Self-employed', 'Deceased') DEFAULT 'Unemployed',
            profile_picture VARCHAR(255) NOT NULL,
            cv VARCHAR(255) NOT NULL,
            ver_stat_dean ENUM('Verified','Pending','Rejected') DEFAULT 'Pending',
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
            FOREIGN KEY (course_id) REFERENCES courses(id) ON DELETE CASCADE
        );");
        $sql->execute();
    }

    public static function fromRow(array $row): self
    {
        $instance                   = new self();
        $instance->id               = (int) $row['id'];
        $instance->userId           = (int) $row['user_id'];
        $instance->nameExtension    = $row['name_extension'];
        $instance->firstName        = $row['first_name'];
        $instance->middleName       = $row['middle_name'];
        $instance->lastName         = $row['last_name'];
        $instance->birthDate        = $row['birth_date'];
        $instance->birthPlace       = $row['birth_place'];
        $instance->gender           = Gender::from($row['gender']);
        $instance->studentNumber    = $row['student_number'];
        $instance->phoneNumber      = $row['phone_number'];
        $instance->courseId         = (int) $row['course_id'];
        $instance->graduationYear   = (int) $row['graduation_year'];
        $instance->civilStatus      = CivilStatus::from($row['civil_status']);
        $instance->address          = $row['address'];
        $instance->employmentStatus = EmploymentStatus::from($row['employment_status']);
        $instance->profilePicture   = $row['profile_picture'];
        $instance->cv               = $row['cv'];
        $instance->verStatDean      = VerificationStatus::from($row['ver_stat_dean']);
        $instance->createdAt        = new DateTime($row['created_at']);
        $instance->updatedAt        = new DateTime($row['updated_at']);
        return $instance;
    }

    public static function findAll(PDO $pdo): array
    {
        $sql = $pdo->prepare('SELECT * FROM alumni');
        $sql->execute();

        return array_map(fn($row) => self::fromRow($row), $sql->fetchAll());
    }

    public static function findById(PDO $pdo, int $id): ?self
    {
        $sql = $pdo->prepare('SELECT * FROM alumni WHERE id = ? LIMIT 1');
        $sql->execute([$id]);

        $row = $sql->fetch();
        return $row ? self::fromRow($row) : null;
    }

    public static function findByUserId(PDO $pdo, int $id): ?self
    {
        $sql = $pdo->prepare('SELECT * FROM alumni WHERE user_id = ? LIMIT 1');
        $sql->execute([$id]);

        $row = $sql->fetch();
        return $row ? self::fromRow($row) : null;
    }

    public static function create(PDO $pdo, array $data): self
    {
        $sql = $pdo->prepare('
            INSERT INTO alumni (user_id, name_extension, first_name, middle_name, last_name, birth_date, birth_place, gender, student_number, phone_number, course_id, graduation_year, civil_status, address, employment_status, profile_picture, cv)
            VALUES (:user_id, :name_extension, :first_name, :middle_name, :last_name, :birth_date, :birth_place, :gender, :student_number, :phone_number, :course_id, :graduation_year, :civil_status, :address, :employment_status, :profile_picture, :cv)
        ');

        $sql->execute([
            ':user_id'           => $data['user_id'],
            ':name_extension'    => $data['name_extension'],
            ':first_name'        => $data['first_name'],
            ':middle_name'       => $data['middle_name'],
            ':last_name'         => $data['last_name'],
            ':birth_date'        => $data['birth_date']->format('Y-m-d'),
            ':birth_place'       => $data['birth_place'],
            ':gender'            => $data['gender'],
            ':student_number'    => $data['student_number'],
            ':phone_number'      => $data['phone_number'],
            ':course_id'         => $data['course_id'],
            ':graduation_year'   => $data['graduation_year'],
            ':civil_status'      => $data['civil_status'],
            ':address'           => $data['address'],
            ':employment_status' => $data['employment_status'],
            ':profile_picture'   => $data['profile_picture'],
            ':cv'                => $data['cv'],
        ]);

        return self::findById($pdo, (int) $pdo->lastInsertId());
    }

    public static function update(PDO $pdo, int $id, array $data): ?self
    {
        $sql = $pdo->prepare('
            UPDATE alumni
            SET
                phone_number      = :phone_number,
                civil_status      = :civil_status,
                address           = :address,
                employment_status = :employment_status,
                profile_picture   = :profile_picture,
                cv                = :cv,
                ver_stat_dean     = :ver_stat_dean
            WHERE id = :id
        ');

        $sql->execute([
            ':phone_number'      => $data["phone_number"],
            ':civil_status'      => $data["civil_status"],
            ':address'           => $data["address"],
            ':employment_status' => $data["employment_status"],
            ':profile_picture'   => $data["profile_picture"],
            ':cv'                => $data["cv"],
            ':ver_stat_dean'     => $data["ver_stat_dean"],
            ':id'                => $id,
        ]);

        return self::findById($pdo, $id);
    }

    public function toArray(): array
    {
        return [
            'id'                => $this->id,
            'user_id'           => $this->userId,
            'name_extension'    => $this->nameExtension,
            'first_name'        => $this->firstName,
            'middle_name'       => $this->middleName,
            'last_name'         => $this->lastName,
            'birth_date'        => $this->birthDate,
            'birth_place'       => $this->birthPlace,
            'gender'            => $this->gender->value,
            'student_number'    => $this->studentNumber,
            'phone_number'      => $this->phoneNumber,
            'course_id'         => $this->courseId,
            'graduation_year'   => $this->graduationYear,
            'civil_status'      => $this->civilStatus->value,
            'address'           => $this->address,
            'employment_status' => $this->employmentStatus->value,
            'profile_picture'   => $this->profilePicture,
            'cv'                => $this->cv,
            'ver_stat_dean'     => $this->verStatDean->value,
            'created_at'        => $this->createdAt->format('Y-m-d H:i:s'),
            'updated_at'        => $this->updatedAt->format('Y-m-d H:i:s'),
        ];
    }
}