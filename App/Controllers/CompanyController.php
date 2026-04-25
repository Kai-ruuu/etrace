<?php

namespace App\Controllers;

use App\Config\UploadsConfig;
use App\Core\HttpResponse;
use App\Core\Request;
use App\Core\Types\ApprovalStatus;
use App\Core\Types\Builtin\Mime;
use App\Core\Types\Industry;
use App\Core\Types\RequirementKey;
use App\Core\Types\Role;
use App\Core\Types\VerificationStatus;
use App\Core\Upload;
use App\Core\UploadHandler;
use App\Core\UploadManager;
use App\Core\Validator;
use App\Models\ProfileCompany;
use App\Models\ProfilePesoStaff;
use App\Models\RejectionMessagePstaff;
use App\Models\RejectionMessagePstaffAppeal;
use App\Models\RejectionMessageSysad;
use App\Models\RejectionMessageSysadAppeal;
use App\Models\RevisionMessage;
use App\Models\RevisionMessageAppeal;
use App\Models\User;
use App\Services\ProfileCompanyService;
use App\Services\UserService;
use App\Utils\ArrayLogger;
use Exception;
use PDO;

class CompanyController
{
    private PDO $pdo;
    private ProfileCompanyService $service;
    private UserService $userService;
    
    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
        $this->service = new ProfileCompanyService($this->pdo);
        $this->userService = new UserService($this->pdo);
    }

    public function show(Request $req, array $cont): void
    {
        $id = Validator::requiredInt('Id', $req->fromParams('id'));
        $company = $this->service->findByUserid($id);

        if (!$company)
            HttpResponse::notFound(['message' => 'Company not found.']);

        HttpResponse::ok($company);
    }
    
    public function showAll(Request $req, array $cont): void
    {
        $role = Role::from($cont['user']['role']);
        $enabled  = Validator::bool('enabled', $req->fromQuery('enabled'));

        if (!in_array($role, [Role::SYSTEM_ADMIN, Role::PESO_STAFF])) {
            HttpResponse::unauthorized(['message' => 'You are not allowed to access this resource.']);
        }

        if ($role === Role::SYSTEM_ADMIN) {
            $statSysad  = Validator::enum('stat_sysad', $req->fromQuery('stat_sysad'), VerificationStatus::class);
            $statPstaff = null;
        } else {
            $statSysad = VerificationStatus::VERIFIED;
            $statPstaff  = Validator::enum('stat_pstaff', $req->fromQuery('stat_pstaff'), VerificationStatus::class);
        }
        $industry = Validator::enum('industry', $req->fromQuery('industry'), Industry::class);
        $query    = Validator::string('query', $req->fromQuery('query')) ?? '';
        $page     = Validator::requiredInt('page', $req->fromQuery('page'), 1);
        $perPage  = Validator::requiredInt('per_page', $req->fromQuery('per_page'), 1, 20);
        
        $result = $this->service->findAll($enabled, $statSysad, $statPstaff, $industry, $query, $page, $perPage);
        
        HttpResponse::ok($result);
    }

    public function store(Request $req, array $cont): void
    {
        $email     = Validator::requiredEmail('email', $req->fromBody('email'));
        $password  = Validator::requiredString('password', $req->fromBody('password'), 8, 65);
        $name      = Validator::requiredString('name', $req->fromBody('name'), 1, 255);
        $address   = Validator::requiredString('address', $req->fromBody('address'), 10, 512);
        $industry  = Validator::requiredEnum('industry', $req->fromBody('industry'), Industry::class);
        
        $uploads = new UploadHandler([
            new Upload('Logo',                                      'logo',       UploadsConfig::folder('logo'),      [Mime::PNG, Mime::JPG, Mime::JPEG]),
            new Upload('Company Profile',                           'profile',    UploadsConfig::folder('profile'),   [Mime::PDF], true, 10),
            new Upload('Business Permit',                           'permit',     UploadsConfig::folder('permit'),    [Mime::PDF], true, 10),
            new Upload('SEC',                                       'sec',        UploadsConfig::folder('sec'),       [Mime::PDF], true, 10),
            new Upload('DTI / CDA Reg.',                            'dti',        UploadsConfig::folder('dti'),       [Mime::PDF], true, 10),
            new Upload('Registry of Establishment fr. DOLE',        'reg_est',    UploadsConfig::folder('reg_est'),   [Mime::PDF], true, 10),
            new Upload('Certification from DOLE Provincial Office', 'cert_dole',  UploadsConfig::folder('cert_dole'), [Mime::PDF], true, 10),
            new Upload('Certification of No Pending Case',          'cert_npc',   UploadsConfig::folder('cert_npc'),  [Mime::PDF], true, 10),
            new Upload('Phil-JobNet Reg.',                          'reg_pjn',    UploadsConfig::folder('reg_pjn'),   [Mime::PDF], true, 10),
            new Upload('List of Vacancies',                         'lov',        UploadsConfig::folder('lov'),       [Mime::PDF], true, 10),
        ]);
        $uploads->stage();

        if (!empty($uploads->getErrors())) {
            HttpResponse::unprocessable(['message' => $uploads->getErrors()]);
        }

        try {
            $newCompany = $this->userService->createCompanyWithProfile([
                'email'                 => $email,
                'password'              => $password,
                'name'                  => $name,
                'address'               => $address,
                'industry'              => $industry->value,
                'logo'                  => $uploads->getFilename('logo'),
                'req_company_profile'   => $uploads->getFilename('profile'),
                'req_business_permit'   => $uploads->getFilename('permit'),
                'req_sec'               => $uploads->getFilename('sec'),
                'req_dti_cda'           => $uploads->getFilename('dti'),
                'req_reg_of_est'        => $uploads->getFilename('reg_est'),
                'req_cert_from_dole'    => $uploads->getFilename('cert_dole'),
                'req_cert_no_case'      => $uploads->getFilename('cert_npc'),
                'req_philjobnet_reg'    => $uploads->getFilename('reg_pjn'),
                'req_list_of_vacancies' => $uploads->getFilename('lov'),
            ]);
            $uploads->commit();
            HttpResponse::ok($newCompany);
        } catch (Exception $e) {
            error_log($e->getMessage());
            $uploads->rollback();
            HttpResponse::server(['message' => 'Unable to register.']);
        }
    }

    public function enable(Request $req, array $cont): void
    {
        $user = $cont['user'];
        $id = Validator::requiredInt('id', $req->fromParams('id'), 1);

        $targetUser = User::findById($this->pdo, $id);

        if (!$targetUser) {
            HttpResponse::notFound(['message' => 'Company not found.']);
        }

        if ($targetUser->role !== Role::COMPANY || $targetUser->email === $user['email']) {
            HttpResponse::forbidden(['message' => 'You are not allowed to perform this action.']);
        }

        if ($targetUser->enabled) {
            HttpResponse::conflict(['message' => 'Company is already enabled.']);
        }
        
        $targetUser->enabled = true;
        $updatedTargetUser = $this->userService->update($id, $targetUser->toArray());

        if (!$updatedTargetUser) {
            HttpResponse::server(['message' => 'Unable to enable Company.']);
        }

        HttpResponse::ok($updatedTargetUser);
    }

    public function disable(Request $req, array $cont): void
    {
        $user = $cont['user'];
        $id = Validator::requiredInt('id', $req->fromParams('id'), 1);
        
        $targetUser = User::findById($this->pdo, $id);

        if (!$targetUser) {
            HttpResponse::notFound(['message' => 'Company not found.']);
        }

        if ($targetUser->role !== Role::COMPANY || $targetUser->email === $user['email']) {
            HttpResponse::forbidden(['message' => 'You are not allowed to perform this action.']);
        }

        if (!$targetUser->enabled) {
            HttpResponse::conflict(['message' => 'Company is already disabled.']);
        }
        
        $targetUser->enabled = false;
        $updatedTargetUser = $this->userService->update($id, $targetUser->toArray());

        if (!$updatedTargetUser) {
            HttpResponse::server(['message' => 'Unable to disable Company.']);
        }

        HttpResponse::ok($updatedTargetUser);
    }

    public function verify(Request $req, array $cont): void
    {
        $user = $cont['user'];
        $role = Role::from($user['role']);
        $id = Validator::requiredInt('id', $req->fromParams('id'), 1);

        $targetUser = User::findById($this->pdo, $id);
        $targetProfile = ProfileCompany::findByUserId($this->pdo, $id);

        if (!$targetUser) {
            HttpResponse::notFound(['message' => 'Company not found.']);
        }

        if ($targetUser->role !== Role::COMPANY || $targetUser->email === $user['email']) {
            HttpResponse::forbidden(['message' => 'You are not allowed to perform this action.']);
        }

        if ($role === Role::SYSTEM_ADMIN) {
            if ($targetProfile->verStatSysad === VerificationStatus::VERIFIED) {
                HttpResponse::conflict(['message' => 'Company is already verified.']);
            }

            $targetProfile->verStatSysad = VerificationStatus::VERIFIED;
        } else {
            if (
                $targetProfile->statReqBusinessPermit !== ApprovalStatus::APPROVED ||
                $targetProfile->statReqCertFromDole !== ApprovalStatus::APPROVED ||
                $targetProfile->statReqCertNoCase !== ApprovalStatus::APPROVED ||
                $targetProfile->statReqCompanyProfile !== ApprovalStatus::APPROVED ||
                $targetProfile->statReqDtiCda !== ApprovalStatus::APPROVED ||
                $targetProfile->statReqListOfVacancies !== ApprovalStatus::APPROVED ||
                $targetProfile->statReqPhiljobnetReg !== ApprovalStatus::APPROVED ||
                $targetProfile->statReqRegOfEst !== ApprovalStatus::APPROVED ||
                $targetProfile->statReqSec !== ApprovalStatus::APPROVED
            )
                HttpResponse::bad(['message' => "All the company's requirements are not yet approved. Please view their profile then review their requirements first."]);
            
            if ($targetProfile->verStatPstaff === VerificationStatus::VERIFIED) {
                HttpResponse::conflict(['message' => 'Company is already verified.']);
            }
            $targetProfile->verStatPstaff = VerificationStatus::VERIFIED;
        }
        
        $updatedTargetProfile = $this->service->update($targetProfile->id, $targetProfile->toArray());

        if (!$updatedTargetProfile) {
            HttpResponse::server(['message' => 'Unable to verify Company.']);
        } else {
            if ($role === Role::SYSTEM_ADMIN) {
                RejectionMessageSysad::deleteAllByCompanyId($this->pdo, $targetProfile->id);
            } else {
                RejectionMessagePstaff::deleteAllByCompanyId($this->pdo, $targetProfile->id);
            }
        }

        HttpResponse::ok($updatedTargetProfile);
    }

    public function reject(Request $req, array $cont): void
    {
        $user = $cont['user'];
        $userProfileId = $user['profile']['id'];
        $role = Role::from($user['role']);
        $id = Validator::requiredInt('id', $req->fromParams('id'), 1);
        $message = Validator::requiredString('Rejection Reason', $req->fromBody('message'), 1, 1000);

        $targetUser = User::findById($this->pdo, $id);
        $targetProfile = ProfileCompany::findByUserId($this->pdo, $id);

        if (!$targetUser) {
            HttpResponse::notFound(['message' => 'Company not found.']);
        }

        if ($targetUser->role !== Role::COMPANY || $targetUser->email === $user['email']) {
            HttpResponse::forbidden(['message' => 'You are not allowed to perform this action.']);
        }

        if ($role === Role::SYSTEM_ADMIN) {
            if ($targetProfile->verStatSysad === VerificationStatus::REJECTED) {
                HttpResponse::conflict(['message' => 'Company is already rejected.']);
            }

            $targetProfile->verStatSysad = VerificationStatus::REJECTED;
        } else {
            if ($targetProfile->verStatPstaff === VerificationStatus::REJECTED) {
                HttpResponse::conflict(['message' => 'Company is already rejected.']);
            }
            $targetProfile->verStatPstaff = VerificationStatus::REJECTED;
        }
        
        $newProfile = $targetProfile->toArray();
        $newProfile['message'] = $message;
        $newProfile['company_id'] = $targetProfile->id;

        if ($role === Role::SYSTEM_ADMIN) {
            $newProfile['sysad_id'] = $userProfileId;
        } else {
            $newProfile['pstaff_id'] = $userProfileId;
        }
        
        $updatedTargetProfile = $this->service->reject($targetProfile->id, $newProfile);

        if (!$updatedTargetProfile) {
            HttpResponse::server(['message' => 'Unable to reject Company.']);
        }

        HttpResponse::ok($updatedTargetProfile);
    }

    public function pend(Request $req, array $cont): void
    {
        $user = $cont['user'];
        $role = Role::from($user['role']);
        $id = Validator::requiredInt('id', $req->fromParams('id'), 1);

        $targetUser = User::findById($this->pdo, $id);
        $targetProfile = ProfileCompany::findByUserId($this->pdo, $id);

        if (!$targetUser) {
            HttpResponse::notFound(['message' => 'Company not found.']);
        }

        if ($targetUser->role !== Role::COMPANY || $targetUser->email === $user['email']) {
            HttpResponse::forbidden(['message' => 'You are not allowed to perform this action.']);
        }

        if ($role === Role::SYSTEM_ADMIN) {
            if ($targetProfile->verStatSysad === VerificationStatus::PENDING) {
                HttpResponse::conflict(['message' => 'Company is already pending for verification.']);
            }

            $targetProfile->verStatSysad = VerificationStatus::PENDING;
        } else {
            if ($targetProfile->verStatPstaff === VerificationStatus::PENDING) {
                HttpResponse::conflict(['message' => 'Company is already pending for verification.']);
            }
            $targetProfile->verStatPstaff = VerificationStatus::PENDING;
        }
        
        $updatedTargetProfile = $this->service->update($targetProfile->id, $targetProfile->toArray());
        
        if (!$updatedTargetProfile) {
            HttpResponse::server(['message' => 'Unable to mark the company as pending.']);
        } else {
            if ($role === Role::SYSTEM_ADMIN) {
                RejectionMessageSysad::deleteAllByCompanyId($this->pdo, $targetProfile->id);
            } else {
                RejectionMessagePstaff::deleteAllByCompanyId($this->pdo, $targetProfile->id);
            }
        }

        HttpResponse::ok($this->service->findById($targetProfile->id));
    }

    public function writeSysadRejectionAppeal(Request $req, array $cont): void
    {
        $profile = $cont['user']['profile'];

        if (VerificationStatus::from($profile['ver_stat_sysad']) !== VerificationStatus::REJECTED) {
            HttpResponse::forbidden(['message' => 'Your account is not rejected by the System Administrator. No need to write an appeal.']);
        }
        
        $rejectionId = Validator::requiredInt('Rejection Id', $req->fromBody('rejection_id'), 1);
        $message     = Validator::requiredString('Appeal', $req->fromBody('appeal'), 1, 1000);

        $rejectionMessage = RejectionMessageSysad::findById($this->pdo, $rejectionId);
        
        if (!$rejectionMessage) {
            HttpResponse::notFound(['message' => 'Rejection was not found.']);
        }

        if ($rejectionMessage->companyId !== $profile['id']) {
            HttpResponse::bad(['message' => 'Invalid action.']);
        }

        if (RejectionMessageSysadAppeal::findByMessageId($this->pdo, $rejectionId))
            HttpResponse::bad(['message' => 'You have already written an appeal for this rejection.']);

        $rejectionAppeal = RejectionMessageSysadAppeal::create($this->pdo, [
            'message_id' => $rejectionId,
            'message'    => $message
        ]);

        if (!$rejectionAppeal) {
            HttpResponse::server(['message' => 'Unable to write appeal due to an error.']);
        }

        HttpResponse::ok($rejectionAppeal->toArray());
    }

    public function writePstaffRejectionAppeal(Request $req, array $cont): void
    {
        $profile = $cont['user']['profile'];
        $verStatusSysad = VerificationStatus::from($profile['ver_stat_sysad']);
        $verStatusPstaff = VerificationStatus::from($profile['ver_stat_pstaff']);        
        
        if ($verStatusSysad !== VerificationStatus::VERIFIED) {
            HttpResponse::forbidden(['message' => 'Your account is not yet verified by the System Administrator. No need to write an appeal yet.']);
        }
        
        if ($verStatusPstaff !== VerificationStatus::REJECTED) {
            HttpResponse::forbidden(['message' => 'Your account is not rejected by the PESO Staff. No need to write an appeal.']);
        }
        
        $rejectionId = Validator::requiredInt('Rejection Id', $req->fromBody('rejection_id'), 1);
        $message     = Validator::requiredString('Appeal', $req->fromBody('appeal'), 1, 1000);

        $rejectionMessage = RejectionMessagePstaff::findById($this->pdo, $rejectionId);

        if (!$rejectionMessage) {
            HttpResponse::notFound(['message' => 'Rejection was not found.']);
        }

        if (RejectionMessagePstaffAppeal::findByMessageId($this->pdo, $rejectionId))
            HttpResponse::bad(['message' => 'You have already written an appeal for this rejection.']);

        $rejectionAppeal = RejectionMessagePstaffAppeal::create($this->pdo, [
            'message_id' => $rejectionId,
            'message'    => $message
        ]);

        if (!$rejectionAppeal) {
            HttpResponse::server(['message' => 'Unable to write appeal due to an error.']);
        }

        HttpResponse::ok($rejectionAppeal->toArray());
    }

    public function approveRequirement(Request $req, array $cont): void
    {
        $id             = Validator::requiredInt('Id', $req->fromParams('id'), 1);
        $requirementKey = Validator::requiredEnum('Requirement Key', $req->fromParams('requirement_key'), RequirementKey::class);
        $requirementStatKey = 'stat_' . $requirementKey->value;

        $company = $this->service->findByUserId($id);

        if (!$company)
            HttpResponse::notFound(['message' => 'Company not found.']);

        $companyProfile = $company['profile'];
        $companyProfile[$requirementStatKey] = ApprovalStatus::APPROVED->value;

        $updatedCompanyProfile = $this->service->update($companyProfile['id'], $companyProfile);

        if (!$updatedCompanyProfile)
            HttpResponse::server(['message' => 'Unable to approve requirement due to an error.']);
        else
            RevisionMessage::deleteAllByCompanyId($this->pdo, $company['profile']['id']);

        HttpResponse::ok($this->service->findByUserId($id));
    }

    public function requestReviseRequirement(Request $req, array $cont): void
    {
        $pstaffId = $cont['user']['profile']['id'];
        $id             = Validator::requiredInt('Id', $req->fromParams('id'), 1);
        $requirementKey = Validator::requiredEnum('Requirement Key', $req->fromParams('requirement_key'), RequirementKey::class);
        $revisionReason = Validator::requiredString('Revision reason', $req->fromBody('reason'), 1, 1000);

        $requirementStatKey = 'stat_' . $requirementKey->value;
        $company = $this->service->findByUserId($id);

        if (!$company)
            HttpResponse::notFound(['message' => 'Company not found.']);

        $companyProfile = $company['profile'];
        $companyProfile[$requirementStatKey] = ApprovalStatus::FOR_REVISION->value;

        $updatedCompanyProfile = $this->service->update($companyProfile['id'], $companyProfile);

        if (!$updatedCompanyProfile) {
            HttpResponse::server(['message' => 'Unable to approve requirement due to an error.']);
        } else {
            RevisionMessage::deleteAllByCompanyIdAndRequirementKey($this->pdo, $companyProfile['id'], $requirementKey);
            RevisionMessage::create($this->pdo, [
                'company_id'         => $companyProfile['id'],
                'pstaff_id'          => $pstaffId,
                'message'            => $revisionReason,
                'requirement_column' => $requirementKey->value
            ]);
        }

        HttpResponse::ok($this->service->findByUserId($id));
    }

    public function revisionRequests(Request $req, array $cont): void
    {
        $company = $cont['user'];
        $revisionMessages = RevisionMessage::findAllByCompanyId($this->pdo, $company['profile']['id']);
        $revisionMessages = array_map(function ($revisionMessage) use ($company): array {
            $revisionAppeal = RevisionMessageAppeal::findByMessageId($this->pdo, $revisionMessage->id);
            $revisionInfo = $revisionMessage->toArray();
            $revisionInfo['rejected_by'] = ProfilePesoStaff::findById($this->pdo, $revisionMessage->pstaffId)->toArray();
            $revisionInfo['appeal'] = $revisionAppeal ? $revisionAppeal->toArray() : null;
            return $revisionInfo;
        }, $revisionMessages);

        HttpResponse::ok($revisionMessages);
    }

    public function revisionInfo(Request $req, array $cont): void
    {
        $id             = Validator::requiredInt('Id', $req->fromParams('id'), 1);
        $requirementKey = Validator::requiredEnum('Requirement Key', $req->fromParams('requirement_key'), RequirementKey::class);
        $company = $this->service->findByUserId($id);

        if (!$company)
            HttpResponse::notFound(['message' => 'Company not found.']);

        $revisionMessage = RevisionMessage::findByCompanyIdAndReqKey($this->pdo, $company['profile']['id'], $requirementKey);

        if (!$revisionMessage)
            HttpResponse::notFound(['message' => 'Revision info not found.']);

        $revisionAppeal = RevisionMessageAppeal::findByMessageId($this->pdo, $revisionMessage->id);
        $revisionInfo = $revisionMessage->toArray();
        $revisionInfo['rejected_by'] = ProfilePesoStaff::findById($this->pdo, $revisionMessage->pstaffId)->toArray();
        $revisionInfo['appeal'] = $revisionAppeal ? $revisionAppeal->toArray() : null;

        HttpResponse::ok($revisionInfo);
    }

    public function renewBasicInfo(Request $req, array $cont): void
    {
        $name = Validator::requiredString('Company Name', $req->fromBody('name'), 1, 255);
        $address = Validator::requiredString('Company Address', $req->fromBody('address'), 10, 512);

        $profile = ProfileCompany::findByUserId($this->pdo, $cont['user']['id']);
        $profile->name = $name;
        $profile->address = $address;
        $updatedProfile = $this->service->update($profile->id, $profile->toArray());

        if (!$updatedProfile)
            HttpResponse::server(['message' => 'Unable to update basic company information due to an error.']);

        HttpResponse::ok($updatedProfile);
    }

    public function renewLogo(Request $req, array $cont): void
    {
        $profile = ProfileCompany::findByUserId($this->pdo, $cont['user']['id']);
        
        $uploads = new UploadHandler([
            new Upload('Logo', 'logo', UploadsConfig::folder('logo'), [Mime::PNG, Mime::JPG, Mime::JPEG])
        ]);
        $uploads->stage();

        if (!empty($uploads->getErrors()))
            HttpResponse::unprocessable(['message' => $uploads->getFirstError()]);
        
        $profileArray = $profile->toArray();
        $oldLogoName = $profileArray['logo'];
        $profileArray['logo'] = $uploads->getFilename('logo');

        try {
            $updatedProfile = $this->service->update($profile->id, $profileArray);
            $uploads->commit();
            UploadManager::deleteFile(UploadsConfig::folder('logo') . "/{$oldLogoName}");
            HttpResponse::ok($updatedProfile);
        } catch (Exception $e) {
            $uploads->rollback();
            HttpResponse::server(['message' => 'Unable to upload logo due to an error.']);
        }
    }

    public function reviseRequirement(Request $req, array $cont): void
    {
        $fileKey = Validator::requiredEnum('Requirement Key', $req->fromBody('requirement_key'), RequirementKey::class);
        $folderName = [
            'req_company_profile'   => 'profile',
            'req_business_permit'   => 'permit',
            'req_sec'               => 'sec',
            'req_dti_cda'           => 'dti',
            'req_reg_of_est'        => 'reg_est',
            'req_cert_from_dole'    => 'cert_dole',
            'req_cert_no_case'      => 'cert_npc',
            'req_philjobnet_reg'    => 'reg_pjn',
            'req_list_of_vacancies' => 'lov',
        ];

        $profile = ProfileCompany::findByUserId($this->pdo, $cont['user']['id']);
        
        $uploads = new UploadHandler([
            new Upload('Requirment', 'requirement', UploadsConfig::folder($folderName[$fileKey->value]), [Mime::PDF], true, 10)
        ]);
        $uploads->stage();

        if (!empty($uploads->getErrors()))
            HttpResponse::unprocessable(['message' => $uploads->getFirstError()]);

        $profileArray = $profile->toArray();

        $oldRequirementName = $profileArray[$fileKey->value];
        
        $profileArray['ver_stat_pstaff'] = VerificationStatus::PENDING->value;
        $profileArray[$fileKey->value] = $uploads->getFilename('requirement');
        $profileArray['stat_' . $fileKey->value] = ApprovalStatus::PENDING->value;

        try {
            $updatedProfile = $this->service->update($profile->id, $profileArray);
            RevisionMessage::deleteAllByCompanyIdAndRequirementKey($this->pdo, $profile->id, $fileKey);
            $uploads->commit();
            UploadManager::deleteFile(UploadsConfig::folder($folderName[$fileKey->value]) . "/{$oldRequirementName}");
            HttpResponse::ok($updatedProfile);
        } catch (Exception $e) {
            $uploads->rollback();
            HttpResponse::server(['message' => 'Unable to upload requirement due to an error.']);
        }
    }

    public function writePstaffRevisionAppeal(Request $req, array $cont): void
    {
        $profile = $cont['user']['profile'];
        $messageId = Validator::requiredInt('Revision request id', $req->fromParams('id'), 1);
        $message = Validator::requiredString('Appeal', $req->fromBody('appeal'), 1, 1000);

        $requestMessage = RevisionMessage::findById($this->pdo, $messageId);

        if (!$requestMessage)
            HttpResponse::notFound(['message' => 'Revision request not found.']);
        
        $verStatusSysad = VerificationStatus::from($profile['ver_stat_sysad']);
        $verStatusPstaff = VerificationStatus::from($profile['ver_stat_pstaff']);

        if ($verStatusSysad !== VerificationStatus::VERIFIED) {
            HttpResponse::forbidden(['message' => 'Your account is not yet verified by the System Administrator. No need to write an appeal yet.']);
        }

        if ($verStatusPstaff !== VerificationStatus::PENDING) {
            HttpResponse::forbidden(['message' => 'Your account is not pending for verification. You cannot write a revision appeal+.']);
        }

        if (RevisionMessageAppeal::findByMessageId($this->pdo, $messageId))
            HttpResponse::bad(['message' => 'You have already written an appeal for this revision request.']);

        $revisionAppeal = RevisionMessageAppeal::create($this->pdo, [
            'message_id' => $messageId,
            'message'    => $message
        ]);

        if (!$revisionAppeal) {
            HttpResponse::server(['message' => 'Unable to write appeal due to an error.']);
        }

        HttpResponse::ok($revisionAppeal->toArray());
    }
}