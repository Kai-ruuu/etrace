<?php

namespace App\Models;

use App\Core\Types\ApprovalStatus;
use App\Core\Types\Industry;
use App\Core\Migratable;
use App\Core\Types\VerificationStatus;
use DateTime;
use PDO;

class ProfileCompany implements Migratable
{
    public int $id;
    public int $userId;
    public string $logo;
    public string $name;
    public string $address;
    public Industry $industry;
    public string $reqCompanyProfile;
    public string $reqBusinessPermit;
    public string $reqSec;
    public string $reqDtiCda;
    public string $reqRegOfEst;
    public string $reqCertFromDole;
    public string $reqCertNoCase;
    public string $reqPhiljobnetReg;
    public string $reqListOfVacancies;
    public ApprovalStatus $statReqCompanyProfile;
    public ApprovalStatus $statReqBusinessPermit;
    public ApprovalStatus $statReqSec;
    public ApprovalStatus $statReqDtiCda;
    public ApprovalStatus $statReqRegOfEst;
    public ApprovalStatus $statReqCertFromDole;
    public ApprovalStatus $statReqCertNoCase;
    public ApprovalStatus $statReqPhiljobnetReg;
    public ApprovalStatus $statReqListOfVacancies;
    public VerificationStatus $verStatSysad;
    public VerificationStatus $verStatPstaff;
    public DateTime $createdAt;
    public DateTime $updatedAt;

    public static function table(): string
    {
        return "companies";
    }

    public static function migrate(PDO $pdo): void
    {
        $table = self::table();
        $sql = $pdo->prepare("CREATE TABLE IF NOT EXISTS {$table}(
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL,
            logo VARCHAR(255) NOT NULL,
            name VARCHAR(255) NOT NULL,
            address VARCHAR(512) NOT NULL,
            industry ENUM(
                'Technology / IT','Finance / Banking / Insurance','Healthcare / Pharmaceuticals',
                'Education / Research','Manufacturing / Industrial','Retail / E-commerce',
                'Food & Beverage / Hospitality','Transportation / Logistics','Energy / Utilities',
                'Media / Entertainment / Advertising','Government / Public Sector',
                'Real Estate / Construction','Consulting / Professional Services','Nonprofit / NGO',
                'Telecommunications'
            ) NOT NULL DEFAULT 'Technology / IT',
            req_company_profile VARCHAR(255) NOT NULL,
            req_business_permit VARCHAR(255) NOT NULL,
            req_sec VARCHAR(255) NOT NULL,
            req_dti_cda VARCHAR(255) NOT NULL,
            req_reg_of_est VARCHAR(255) NOT NULL,
            req_cert_from_dole VARCHAR(255) NOT NULL,
            req_cert_no_case VARCHAR(255) NOT NULL,
            req_philjobnet_reg VARCHAR(255) NOT NULL,
            req_list_of_vacancies VARCHAR(255) NOT NULL,
            stat_req_company_profile ENUM('Pending', 'Approved','For Revision') DEFAULT 'Pending',
            stat_req_business_permit ENUM('Pending', 'Approved','For Revision') DEFAULT 'Pending',
            stat_req_sec ENUM('Pending', 'Approved','For Revision') DEFAULT 'Pending',
            stat_req_dti_cda ENUM('Pending', 'Approved','For Revision') DEFAULT 'Pending',
            stat_req_reg_of_est ENUM('Pending', 'Approved','For Revision') DEFAULT 'Pending',
            stat_req_cert_from_dole ENUM('Pending', 'Approved','For Revision') DEFAULT 'Pending',
            stat_req_cert_no_case ENUM('Pending', 'Approved','For Revision') DEFAULT 'Pending',
            stat_req_philjobnet_reg ENUM('Pending', 'Approved','For Revision') DEFAULT 'Pending',
            stat_req_list_of_vacancies ENUM('Pending', 'Approved','For Revision') DEFAULT 'Pending',
            ver_stat_sysad ENUM('Verified','Pending','Rejected') DEFAULT 'Pending',
            ver_stat_pstaff ENUM('Verified','Pending','Rejected') DEFAULT 'Pending',
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
        );");
        $sql->execute();
    }

    public static function fromRow(array $row): self
    {
        $instance                         = new self();
        $instance->id                     = (int) $row['id'];
        $instance->userId                 = (int) $row['user_id'];
        $instance->logo                   = $row['logo'];
        $instance->name                   = $row['name'];
        $instance->address                = $row['address'];
        $instance->industry               = Industry::from($row['industry']);
        $instance->reqCompanyProfile      = $row['req_company_profile'];
        $instance->reqBusinessPermit      = $row['req_business_permit'];
        $instance->reqSec                 = $row['req_sec'];
        $instance->reqDtiCda              = $row['req_dti_cda'];
        $instance->reqRegOfEst            = $row['req_reg_of_est'];
        $instance->reqCertFromDole        = $row['req_cert_from_dole'];
        $instance->reqCertNoCase          = $row['req_cert_no_case'];
        $instance->reqPhiljobnetReg       = $row['req_philjobnet_reg'];
        $instance->reqListOfVacancies     = $row['req_list_of_vacancies'];
        $instance->statReqCompanyProfile  = ApprovalStatus::from($row['stat_req_company_profile']);
        $instance->statReqBusinessPermit  = ApprovalStatus::from($row['stat_req_business_permit']);
        $instance->statReqSec             = ApprovalStatus::from($row['stat_req_sec']);
        $instance->statReqDtiCda          = ApprovalStatus::from($row['stat_req_dti_cda']);
        $instance->statReqRegOfEst        = ApprovalStatus::from($row['stat_req_reg_of_est']);
        $instance->statReqCertFromDole    = ApprovalStatus::from($row['stat_req_cert_from_dole']);
        $instance->statReqCertNoCase      = ApprovalStatus::from($row['stat_req_cert_no_case']);
        $instance->statReqPhiljobnetReg   = ApprovalStatus::from($row['stat_req_philjobnet_reg']);
        $instance->statReqListOfVacancies = ApprovalStatus::from($row['stat_req_list_of_vacancies']);
        $instance->verStatSysad           = VerificationStatus::from($row['ver_stat_sysad']);
        $instance->verStatPstaff          = VerificationStatus::from($row['ver_stat_pstaff']);
        $instance->createdAt              = new DateTime($row['created_at']);
        $instance->updatedAt              = new DateTime($row['updated_at']);
        return $instance;
    }

    public static function findAll(PDO $pdo): array
    {
        $sql = $pdo->prepare('SELECT * FROM companies');
        $sql->execute();

        return array_map(fn($row) => self::fromRow($row), $sql->fetchAll());
    }

    public static function findById(PDO $pdo, int $id): ?self
    {
        $sql = $pdo->prepare('SELECT * FROM companies WHERE id = ? LIMIT 1');
        $sql->execute([$id]);

        $row = $sql->fetch();
        return $row ? self::fromRow($row) : null;
    }

    public static function findByUserId(PDO $pdo, int $id): ?self
    {
        $sql = $pdo->prepare('SELECT * FROM companies WHERE user_id = ? LIMIT 1');
        $sql->execute([$id]);

        $row = $sql->fetch();
        return $row ? self::fromRow($row) : null;
    }

    public static function create(PDO $pdo, array $data): self
    {
        $sql = $pdo->prepare('
            INSERT INTO companies (user_id, logo, name, address, industry, req_company_profile, req_business_permit, req_sec, req_dti_cda, req_reg_of_est, req_cert_from_dole, req_cert_no_case, req_philjobnet_reg, req_list_of_vacancies)
            VALUES (:user_id, :logo, :name, :address, :industry, :req_company_profile, :req_business_permit, :req_sec, :req_dti_cda, :req_reg_of_est, :req_cert_from_dole, :req_cert_no_case, :req_philjobnet_reg, :req_list_of_vacancies)
        ');

        $sql->execute([
            ':user_id'               => $data['user_id'],
            ':logo'                  => $data['logo'],
            ':name'                  => $data['name'],
            ':address'               => $data['address'],
            ':industry'              => $data['industry'],
            ':req_company_profile'   => $data['req_company_profile'],
            ':req_business_permit'   => $data['req_business_permit'],
            ':req_sec'               => $data['req_sec'],
            ':req_dti_cda'           => $data['req_dti_cda'],
            ':req_reg_of_est'        => $data['req_reg_of_est'],
            ':req_cert_from_dole'    => $data['req_cert_from_dole'],
            ':req_cert_no_case'      => $data['req_cert_no_case'],
            ':req_philjobnet_reg'    => $data['req_philjobnet_reg'],
            ':req_list_of_vacancies' => $data['req_list_of_vacancies'],
        ]);

        return self::findById($pdo, (int) $pdo->lastInsertId());
    }

    public static function update(PDO $pdo, int $id, array $data): ?self
    {
        $sql = $pdo->prepare('
            UPDATE companies
            SET
                logo                       = :logo,
                address                    = :address,
                req_company_profile        = :req_company_profile,
                req_business_permit        = :req_business_permit,
                req_sec                    = :req_sec,
                req_dti_cda                = :req_dti_cda,
                req_reg_of_est             = :req_reg_of_est,
                req_cert_from_dole         = :req_cert_from_dole,
                req_cert_no_case           = :req_cert_no_case,
                req_philjobnet_reg         = :req_philjobnet_reg,
                req_list_of_vacancies      = :req_list_of_vacancies,
                stat_req_company_profile   = :stat_req_company_profile,
                stat_req_business_permit   = :stat_req_business_permit,
                stat_req_sec               = :stat_req_sec,
                stat_req_dti_cda           = :stat_req_dti_cda,
                stat_req_reg_of_est        = :stat_req_reg_of_est,
                stat_req_cert_from_dole    = :stat_req_cert_from_dole,
                stat_req_cert_no_case      = :stat_req_cert_no_case,
                stat_req_philjobnet_reg    = :stat_req_philjobnet_reg,
                stat_req_list_of_vacancies = :stat_req_list_of_vacancies,
                ver_stat_sysad             = :ver_stat_sysad,
                ver_stat_pstaff            = :ver_stat_pstaff
            WHERE id = :id
        ');

        $sql->execute([
            ':logo'                       => $data['logo'],
            ':address'                    => $data['address'],
            ':req_company_profile'        => $data['req_company_profile'],
            ':req_business_permit'        => $data['req_business_permit'],
            ':req_sec'                    => $data['req_sec'],
            ':req_dti_cda'                => $data['req_dti_cda'],
            ':req_reg_of_est'             => $data['req_reg_of_est'],
            ':req_cert_from_dole'         => $data['req_cert_from_dole'],
            ':req_cert_no_case'           => $data['req_cert_no_case'],
            ':req_philjobnet_reg'         => $data['req_philjobnet_reg'],
            ':req_list_of_vacancies'      => $data['req_list_of_vacancies'],
            ':stat_req_company_profile'   => $data['stat_req_company_profile'],
            ':stat_req_business_permit'   => $data['stat_req_business_permit'],
            ':stat_req_sec'               => $data['stat_req_sec'],
            ':stat_req_dti_cda'           => $data['stat_req_dti_cda'],
            ':stat_req_reg_of_est'        => $data['stat_req_reg_of_est'],
            ':stat_req_cert_from_dole'    => $data['stat_req_cert_from_dole'],
            ':stat_req_cert_no_case'      => $data['stat_req_cert_no_case'],
            ':stat_req_philjobnet_reg'    => $data['stat_req_philjobnet_reg'],
            ':stat_req_list_of_vacancies' => $data['stat_req_list_of_vacancies'],
            ':ver_stat_sysad'             => $data['ver_stat_sysad'],
            ':ver_stat_pstaff'            => $data['ver_stat_pstaff'],
            ':id'                         => $id,
        ]);

        return self::findById($pdo, $id);
    }

    public function toArray(): array
    {
        return [
            'id'                         => $this->id,
            'user_id'                    => $this->userId,
            'logo'                       => $this->logo,
            'name'                       => $this->name,
            'address'                    => $this->address,
            'industry'                   => $this->industry->value,
            'req_company_profile'        => $this->reqCompanyProfile,
            'req_business_permit'        => $this->reqBusinessPermit,
            'req_sec'                    => $this->reqSec,
            'req_dti_cda'                => $this->reqDtiCda,
            'req_reg_of_est'             => $this->reqRegOfEst,
            'req_cert_from_dole'         => $this->reqCertFromDole,
            'req_cert_no_case'           => $this->reqCertNoCase,
            'req_philjobnet_reg'         => $this->reqPhiljobnetReg,
            'req_list_of_vacancies'      => $this->reqListOfVacancies,
            'stat_req_company_profile'   => $this->statReqCompanyProfile->value,
            'stat_req_business_permit'   => $this->statReqBusinessPermit->value,
            'stat_req_sec'               => $this->statReqSec->value,
            'stat_req_dti_cda'           => $this->statReqDtiCda->value,
            'stat_req_reg_of_est'        => $this->statReqRegOfEst->value,
            'stat_req_cert_from_dole'    => $this->statReqCertFromDole->value,
            'stat_req_cert_no_case'      => $this->statReqCertNoCase->value,
            'stat_req_philjobnet_reg'    => $this->statReqPhiljobnetReg->value,
            'stat_req_list_of_vacancies' => $this->statReqListOfVacancies->value,
            'ver_stat_sysad'             => $this->verStatSysad->value,
            'ver_stat_pstaff'            => $this->verStatPstaff->value,
            'created_at'                 => $this->createdAt->format('Y-m-d H:i:s'),
            'updated_at'                 => $this->updatedAt->format('Y-m-d H:i:s'),
        ];
    }
}