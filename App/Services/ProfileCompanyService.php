<?php

namespace App\Services;

use App\Core\Types\Industry;
use App\Core\Types\VerificationStatus;
use App\Models\ProfileCompany;
use App\Models\ProfilePesoStaff;
use App\Models\ProfileSystemAdmin;
use App\Models\RejectionMessagePstaff;
use App\Models\RejectionMessagePstaffAppeal;
use App\Models\RejectionMessageSysad;
use App\Models\RejectionMessageSysadAppeal;
use App\Models\User;
use App\Utils\ArrayLogger;
use Exception;
use PDO;
use PDOException;

class ProfileCompanyService
{
    private PDO $pdo;
    
    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    private function attachRequired(ProfileCompany $profile): ?array
    {
        $user = User::findById($this->pdo, $profile->userId);
        
        if (!$user) return null;

        $result = $user->toArray();
        $result["profile"] = $profile->toArray();

        $rejectionSysad = RejectionMessageSysad::findByCompanyId($this->pdo, $profile->id);
        $rejectionPstaff = RejectionMessagePstaff::findByCompanyId($this->pdo, $profile->id);
        $result['profile']["rejection_sysad"] = $rejectionSysad ? $rejectionSysad->toArray() : null;
        $result['profile']["rejection_pstaff"] = $rejectionPstaff ? $rejectionPstaff->toArray() : null;

        if ($rejectionSysad) {
            $rejectorSysad = ProfileSystemAdmin::findById($this->pdo, $rejectionSysad->sysadId);
            $result['profile']['rejection_sysad']['rejected_by'] = $rejectorSysad->toArray();
            
            $rejectionSysadAppeal = RejectionMessageSysadAppeal::findByMessageId($this->pdo, $rejectionSysad->id);
            $result['profile']["rejection_sysad"]['appeal'] = $rejectionSysadAppeal ? $rejectionSysadAppeal->toArray() : null;
        }
        
        if ($rejectionPstaff) {
            $rejectorPstaff = ProfilePesoStaff::findById($this->pdo, $rejectionPstaff->pstaffId);
            $result['profile']['rejection_pstaff']['rejected_by'] = $rejectorPstaff->toArray();
            
            $rejectionPstaffAppeal = RejectionMessagePstaffAppeal::findByMessageId($this->pdo, $rejectionPstaff->id);
            $result['profile']["rejection_pstaff"]['appeal'] = $rejectionPstaffAppeal ? $rejectionPstaffAppeal->toArray() : null;
        }
        
        return $result;
    }

    public function findAll(
        ?bool $enabled = true,
        ?VerificationStatus $statSysad = null,
        ?VerificationStatus $statPstaff = null,
        ?Industry $industry = null,
        string $query = '',
        int $page = 1,
        int $perPage = 20,
    ): array
    {
        $page    = max(1, (int)$page);
        $perPage = min(100, max(1, (int)$perPage));
        $offset  = ($page - 1) * $perPage;
        $search = "%$query%";
        $bindings = $enabled === null
            ? []
            : [$enabled];
        $bindings = $statSysad === null
            ? $bindings
            : array_merge($bindings, [$statSysad->value]);
        $bindings = $statPstaff === null
            ? $bindings
            : array_merge($bindings, [$statPstaff->value]);
        $bindings = $industry === null
            ? $bindings
            : array_merge($bindings, [$industry->value]);
        $bindings = array_merge($bindings, [$search, $search, $search]);
        $enabled = $enabled === null
            ? ''
            : 'u.enabled = ? AND';
        $statSysad = $statSysad === null
            ? ''
            : 'c.ver_stat_sysad = ? AND';
        $statPstaff = $statPstaff === null
            ? ''
            : 'c.ver_stat_pstaff = ? AND';
        $industry = $industry === null
            ? ''
            : 'c.industry = ? AND';

        $sqlTotal = $this->pdo->prepare("
            SELECT COUNT(c.id)
            FROM companies c
            JOIN users u ON u.id = c.user_id
            WHERE
                {$enabled}
                {$statSysad}
                {$statPstaff}
                {$industry}
                (
                    u.email LIKE ? OR
                    c.name LIKE ? OR
                    c.address LIKE ?
                )
        ");
        
        $sqlTotal->execute($bindings);
        $total = $sqlTotal->fetchColumn();

        $bindings = array_merge($bindings, [$perPage, $offset]);
        
        $sql = $this->pdo->prepare("
            SELECT c.*
            FROM companies c
            JOIN users u ON u.id = c.user_id
            WHERE
                {$enabled}
                {$statSysad}
                {$statPstaff}
                {$industry}
                (
                    u.email LIKE ? OR
                    c.name LIKE ? OR
                    c.address LIKE ?
                )
            LIMIT ? OFFSET ?
        ");

        $sql->execute($bindings);

        $results = array_map(fn($row) => $this->attachRequired(ProfileCompany::fromRow($row)), $sql->fetchAll());
        
        return [
            'results'     => $results,
            'total'       => $total,
            'page'        => $page,
            'per_page'    => $perPage,
            'total_pages' => (int) ceil($total / $perPage),
            'has_next'    => $page < ceil($total / $perPage),
            'has_prev'    => $page > 1,
        ];
    }

    public function findById(int $id): ?array
    {
        $profile = ProfileCompany::findById($this->pdo, $id);
        return $profile ? $this->attachRequired($profile) : null;
    }

    public function findByUserId(int $id): ?array
    {
        $profile = ProfileCompany::findByUserId($this->pdo, $id);
        return $profile ? $this->attachRequired($profile) : null;
    }

    public function create(array $data): ?array
    {
        try {
            $this->pdo->beginTransaction();

            $modelInstance = ProfileCompany::create($this->pdo, $data);

            $this->pdo->commit();
            return $this->findById($modelInstance->id);
        } catch (PDOException $e) {
            $this->pdo->rollBack();
            throw new Exception($e->getMessage());
        }
    }

    public function update(int $id, array $data): ?array
    {
        $updatedProfile = ProfileCompany::update($this->pdo, $id, $data);
        return $updatedProfile ? $updatedProfile->toArray() : null;
    }

    public function reject(int $id, array $data): ?array
    {
        try {
            $this->pdo->beginTransaction();

            if (array_key_exists('sysad_id', $data)) {
                RejectionMessageSysad::create($this->pdo, $data);
            } else {
                RejectionMessagePstaff::create($this->pdo, $data);
            }
            $updatedProfile = ProfileCompany::update($this->pdo, $id, $data);

            $this->pdo->commit();

            return $updatedProfile->toArray();
        } catch (PDOException $e) {
            $this->pdo->rollBack();
            return null;
        }
    }
}