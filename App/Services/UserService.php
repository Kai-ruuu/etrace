<?php

namespace App\Services;

use App\Core\Types\Link;
use App\Core\Types\Role;
use App\Models\Course;
use App\Models\Occupation;
use App\Models\OccupationState;
use App\Models\ProfileAlumni;
use App\Models\ProfileCompany;
use App\Models\ProfileDean;
use App\Models\ProfilePesoStaff;
use App\Models\ProfileSystemAdmin;
use App\Models\RejectionMessageDean;
use App\Models\RejectionMessageDeanAppeal;
use App\Models\RejectionMessagePstaff;
use App\Models\RejectionMessagePstaffAppeal;
use App\Models\RejectionMessageSysad;
use App\Models\RejectionMessageSysadAppeal;
use App\Models\School;
use App\Models\SocialMedia;
use App\Models\SocialMediaPlatform;
use App\Models\User;
use App\Models\Verification;
use DateTime;
use Exception;
use PDO;
use PDOException;

class UserService
{
    private PDO $pdo;
    private MailingService $mailingService;
    
    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
        $this->mailingService = MailingService::forProd();
    }

    private function attachProfile(User $user): ?array
    {
        $profile = match($user->role) {
            Role::SYSTEM_ADMIN => ProfileSystemAdmin::findByUserId($this->pdo, $user->id),
            Role::DEAN         => ProfileDean::findByUserId($this->pdo, $user->id),
            Role::PESO_STAFF   => ProfilePesoStaff::findByUserId($this->pdo, $user->id),
            Role::COMPANY      => ProfileCompany::findByUserId($this->pdo, $user->id),
            Role::ALUMNI       => ProfileAlumni::findByUserId($this->pdo, $user->id),
            default            => null,
        };

        if (!$profile) return null;

        $result = $user->toArray();

        if ($user->role === Role::SYSTEM_ADMIN) {
            $result['default'] = $_ENV['SYSAD_EMAIL'] === $user->email;
        }
        
        $result['profile'] = $profile->toArray();

        if ($user->role === Role::DEAN) {
            $result['profile']['school'] = School::findById($this->pdo, $profile->schoolId)->toArray();
        }

        if ($user->role === Role::COMPANY) {
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
        }

        if ($user->role === Role::ALUMNI) {
            $result["profile"] = $profile->toArray();
            $socialMedias = SocialMedia::findAllByAlumniId($this->pdo, $profile->id);
            $socialMedias = array_map(fn($s) => array_merge(
                $s->toArray(),
                ['platform' => SocialMediaPlatform::findById($this->pdo, $s->platformId)?->toArray()]
            ), $socialMedias);
            $result['profile']['social_medias'] = $socialMedias;
            $result['profile']['course'] = Course::findById($this->pdo, $profile->courseId)->toArray();
            $result['profile']['occupations'] = array_map(function ($os) {
                $occupationStateArray = $os->toArray();
                $occupationStateArray['occupation'] = Occupation::findById($this->pdo, $os->occupationId)->toArray();
                return $occupationStateArray;
            }, OccupationState::findAllByAlumniId($this->pdo, $profile->id));

            $rejectionDean = RejectionMessageDean::findByAlumniId($this->pdo, $profile->id);
            $result['profile']['rejection_dean'] = $rejectionDean ? $rejectionDean->toArray() : null;

            if ($rejectionDean) {
                $rejectorDean = ProfileDean::findById($this->pdo, $rejectionDean->deanId);
                $result['profile']['rejection_dean']['rejected_by'] = $rejectorDean->toArray();
                
                $rejectionDeanAppeal = RejectionMessageDeanAppeal::findByMessageId($this->pdo, $rejectionDean->id);
                $result['profile']["rejection_dean"]['appeal'] = $rejectionDeanAppeal ? $rejectionDeanAppeal->toArray() : null;
            }
        }
        
        return $result;
    }

    public function findAllSystemAdmins(): array
    {
        $users = User::findAll($this->pdo);
        return array_map(fn($user) => $this->attachProfile($user), $users);
    }

    public function findById(int $id): ?array
    {
        $user = User::findById($this->pdo, $id);
        $userWithProfile = $this->attachProfile($user);

        if ($user->role === Role::SYSTEM_ADMIN) {
            $userWithProfile['default'] = $_ENV['SYSAD_EMAIL'] === $user->email;
        }
        
        return $user ? $userWithProfile : null;
    }

    public function findByEmail(string $email): ?array
    {
        $user = User::findByEmail($this->pdo, $email);
        return $user ? $this->attachProfile($user) : null;
    }

    public function createAdminWithProfile(array $data, ?array $sysad = null): ?array
    {
        try {
            $this->pdo->beginTransaction();

            $role = Role::tryFrom($data['role']);

            if (!$sysad) {
                $data['email_verified'] = true;
                $data['email_verified_at'] = (new DateTime())->format('Y-m-d');
            } else {
                $data['enabled'] = false;
            }
            
            $user = User::create($this->pdo, $data);
            $data['user_id'] = $user->id;
            $profile = null;

            switch($role) {
                case Role::SYSTEM_ADMIN:
                    $profile = ProfileSystemAdmin::create($this->pdo, $data);
                    break;
                case Role::DEAN:
                    $profile = ProfileDean::create($this->pdo, $data);
                    break;
                case Role::PESO_STAFF:
                    $profile = ProfilePesoStaff::create($this->pdo, $data);
                    break;
            };

            
            if ($sysad) {
                $token = substr(bin2hex(random_bytes(16)), 0, 8);
                $verificationLink = Link::EMAIL_VERIFICATION->value . $token;
                $userWithProfile = $user->toArray();
                $userWithProfile['profile'] = $profile->toArray();
                
                Verification::create($this->pdo, [
                    'user_id' => $user->id,
                    'token'   => $token
                ]);
                $this->mailingService->sendNewlyAssignedWithEmailVerification($sysad, $userWithProfile, $data['password'], $verificationLink);
            }
                
            $this->pdo->commit();
            return $this->findById($user->id);
        } catch (PDOException $e) {
            $this->pdo->rollBack();
            throw new Exception($e->getMessage());
        }
    }

    public function createCompanyWithProfile(array $data): ?array
    {
        try {
            $this->pdo->beginTransaction();
            
            $data['role'] = Role::COMPANY->value;
            $data['enabled'] = false;
            $user = User::create($this->pdo, $data);
            $data['user_id'] = $user->id;

            $profile = ProfileCompany::create($this->pdo, $data);

            $token = substr(bin2hex(random_bytes(16)), 0, 8);
            $verificationLink = Link::EMAIL_VERIFICATION->value . $token;
            $userWithProfile = $user->toArray();
            $userWithProfile['profile'] = $profile->toArray();
            Verification::create($this->pdo, [
                'user_id' => $user->id,
                'token'   => $token
            ]);
            $this->mailingService->sendNewlyRegisteredCompanyWithEmailVerification($userWithProfile, $verificationLink);
            
            $this->pdo->commit();
            return $this->findById($user->id);
        } catch (PDOException $e) {
            $this->pdo->rollBack();
            throw new Exception($e->getMessage());
        }
    }

    public function createAlumni()
    {

    }

    public function update(int $id, array $data): ?array
    {
        $user = User::update($this->pdo, $id, $data);
        return $user ? $this->attachProfile($user) : null;
    }
}