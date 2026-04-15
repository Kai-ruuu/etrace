<?php

namespace App\Controllers;

use App\Config\UploadsConfig;
use App\Core\HttpResponse;
use App\Core\Request;
use App\Core\Types\Builtin\Mime;
use App\Core\Types\CivilStatus;
use App\Core\Types\EmploymentStatus;
use App\Core\Types\Gender;
use App\Core\Types\Link;
use App\Core\Types\Role;
use App\Core\Types\VerificationStatus;
use App\Core\Upload;
use App\Core\UploadHandler;
use App\Core\Validator;
use App\Models\Course;
use App\Models\ProfileAlumni;
use App\Models\RejectionMessageDean;
use App\Models\RejectionMessageDeanAppeal;
use App\Models\User;
use App\Models\Verification;
use App\Services\MailingService;
use App\Services\ProfileAlumniService;
use App\Services\UserService;
use DateTime;
use Exception;
use PDO;

class AlumniController
{
    private PDO $pdo;
    private ProfileAlumniService $service;
    private UserService $userService;
    private MailingService $mailingService;
    
    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
        $this->service = new ProfileAlumniService($this->pdo);
        $this->userService = new UserService($this->pdo);
        $this->mailingService = MailingService::forProd();
    }

    public function show(Request $req, array $cont): void
    {
        $id = Validator::requiredInt('id', $req->fromParams('id'), 1);

        $alumni = ProfileAlumni::findByUserId($this->pdo, $id);

        if (!$alumni) {
            HttpResponse::notFound(['message' => 'Alumni not found.']);
        }

        HttpResponse::ok($this->service->findById($alumni->id));
    }

    public function showAll(Request $req, array $cont): void
    {
        $enabled  = Validator::bool('enabled', $req->fromQuery('enabled'));
        $status   = Validator::enum('status', $req->fromQuery('status'), VerificationStatus::class);
        $courseId = Validator::int('course_id', $req->fromQuery('course_id'), 1);
        $batch    = Validator::int('batch', $req->fromQuery('batch'), 2007, (int) date('Y'));
        $query    = Validator::string('query', $req->fromQuery('query')) ?? '';
        $page     = Validator::requiredInt('page', $req->fromQuery('page'), 1);
        $perPage  = Validator::requiredInt('per_page', $req->fromQuery('per_page'), 1, 20);

        if ($courseId !== null && !Course::findById($this->pdo, $courseId)) {
            HttpResponse::notFound(['message' => 'Course not found.']);
        }
        
        $result = $this->service->findAll(
            $enabled,
            $status,
            $courseId,
            $batch,
            $query,
            $page,
            $perPage
        );

        HttpResponse::ok($result);
    }

    public function store(Request $req, array $cont): void
    {
        $email = Validator::requiredEmail('Email', $req->fromBody('email'));
        $password = Validator::requiredString('Password', $req->fromBody('password'), 8, 65);
        $nameExtension = Validator::string('Name Extension', $req->fromBody('name_extension'), 1, 10);
        $firstName = Validator::requiredString('First Name', $req->fromBody('first_name'), 1, 50);
        $middleName = Validator::string('Middle Name', $req->fromBody('middle_name'), 1, 50);
        $lastName = Validator::requiredString('Last Name', $req->fromBody('last_name'), 1, 50);
        $birthPlace = Validator::requiredString('Birth Place', $req->fromBody('birth_place'), 1, 512);
        $gender = Validator::requiredEnum('Gender', $req->fromBody('gender'), Gender::class);
        $studentNumber = Validator::requiredString('Student Number', $req->fromBody('student_number'), 5, 255);
        $phoneNumber = Validator::requiredString('Phone Number', $req->fromBody('phone_number'), 11, 25);
        $courseId = Validator::requiredInt('Course ID', $req->fromBody('course_id'), 1);
        $graduationYear = Validator::requiredInt('Batch', $req->fromBody('batch'), 2007, (int) date('Y'));
        $civilStatus = Validator::requiredEnum('Civil Status', $req->fromBody('civil_status'), CivilStatus::class);
        $address = Validator::requiredString('Address', $req->fromBody('address'), 1, 512);
        $employmentStatus = Validator::requiredEnum('Employment Status', $req->fromBody('employment_status'), EmploymentStatus::class);
        $socialMedias = Validator::requiredJson('Social Media Links', $req->fromBody('social_medias'));
        $occupations = Validator::requiredJson('Occupations', $req->fromBody('occupations'));

        $uploads = new UploadHandler([
            new Upload('Profile Picture',  'profile_picture', UploadsConfig::folder('profile_picture'), [Mime::PNG, Mime::JPG, Mime::JPEG]),
            new Upload('Curriculum Vitae', 'cv',              UploadsConfig::folder('cv'),              [Mime::PDF]),
        ]);
        $uploads->stage();

        if (!empty($uploads->getErrors())) {
            HttpResponse::unprocessable(['message' => $uploads->getErrors()]);
        }

        // validate birth date
        $birthDate = $req->fromBody('birth_date');

        if (strlen($birthDate) === 0) {
            HttpResponse::bad(["message" => "Birth date is required."]);
        }

        try {
            $birthDate = new DateTime($birthDate);
        } catch (Exception $e) {
            HttpResponse::unprocessable(['message' => 'Inalid birhtdate date format.']);
        }

        $minDate = new DateTime();
        $minDate->modify("-21 years");

        if ($birthDate > $minDate) {
            HttpResponse::bad(['message' => "You must be at least 21 years old to register."]);
        }

        try {
            $newAlumni = $this->service->create([
                'email' => $email,
                'password' => $password,
                'name_extension' => $nameExtension,
                'first_name' => $firstName,
                'middle_name' => $middleName,
                'last_name' => $lastName,
                'birth_date' => $birthDate,
                'birth_place' => $birthPlace,
                'gender' => $gender->value,
                'student_number' => $studentNumber,
                'phone_number' => $phoneNumber,
                'course_id' => $courseId,
                'graduation_year' => $graduationYear,
                'civil_status' => $civilStatus->value,
                'address' => $address,
                'employment_status' => $employmentStatus->value,
                'profile_picture' => $uploads->getFilename('profile_picture'),
                'cv' => $uploads->getFilename('cv'),
                'social_medias' => $socialMedias,
                'occupations' => $occupations,
                'enabled' => false
            ]);
            $uploads->commit();
            $token = substr(bin2hex(random_bytes(16)), 0, 8);
            $verificationLink = Link::EMAIL_VERIFICATION->value . $token;
            Verification::create($this->pdo, [
                'user_id' => $newAlumni['id'],
                'token'   => $token
            ]);
            $this->mailingService->sendNewlyRegisteredAlumniWithEmailVerification($newAlumni, $verificationLink);
            HttpResponse::ok($newAlumni);
        } catch (Exception $e) {
            error_log($e->getMessage());
            $uploads->rollback();
            HttpResponse::server(['message' => 'Unable to register.']);
        }
    }

    public function enable(Request $req, array $cont): void
    {
        $user = $cont['user'];
        $schoolId = $user['profile']['school_id'];
        $id = Validator::requiredInt('id', $req->fromParams('id'), 1);

        $targetUser = User::findById($this->pdo, $id);

        if (!$targetUser) {
            HttpResponse::notFound(['message' => 'Alumni not found.']);
        }

        $profile = ProfileAlumni::findByUserId($this->pdo, $id);
        $course = Course::findById($this->pdo, $profile->courseId);

        if (
            $course->schoolId !== $schoolId ||
            $targetUser->role !== Role::ALUMNI ||
            $targetUser->email === $user['email']
        ) {
            HttpResponse::forbidden(['message' => 'You are not allowed to perform this action.']);
        }

        if ($targetUser->enabled) {
            HttpResponse::conflict(['message' => 'Alumni is already enabled.']);
        }
        
        $targetUser->enabled = true;
        $updatedTargetUser = $this->userService->update($id, $targetUser->toArray());

        if (!$updatedTargetUser) {
            HttpResponse::server(['message' => 'Unable to enable Alumni.']);
        }

        HttpResponse::ok($updatedTargetUser);
    }

    public function disable(Request $req, array $cont): void
    {
        $user = $cont['user'];
        $schoolId = $user['profile']['school_id'];
        $id = Validator::requiredInt('id', $req->fromParams('id'), 1);
        
        $targetUser = User::findById($this->pdo, $id);

        if (!$targetUser) {
            HttpResponse::notFound(['message' => 'Alumni not found.']);
        }

        $profile = ProfileAlumni::findByUserId($this->pdo, $id);
        $course = Course::findById($this->pdo, $profile->courseId);

        if (
            $course->schoolId !== $schoolId ||
            $targetUser->role !== Role::ALUMNI ||
            $targetUser->email === $user['email']
        ) {
            HttpResponse::forbidden(['message' => 'You are not allowed to perform this action.']);
        }

        if (!$targetUser->enabled) {
            HttpResponse::conflict(['message' => 'Alumni is already disabled.']);
        }
        
        $targetUser->enabled = false;
        $updatedTargetUser = $this->userService->update($id, $targetUser->toArray());

        if (!$updatedTargetUser) {
            HttpResponse::server(['message' => 'Unable to disable Alumni.']);
        }

        HttpResponse::ok($updatedTargetUser);
    }

    public function verify(Request $req, array $cont): void
    {
        $user = $cont['user'];
        $id = Validator::requiredInt('id', $req->fromParams('id'), 1);

        $targetUser = User::findById($this->pdo, $id);
        $targetProfile = ProfileAlumni::findByUserId($this->pdo, $id);

        if (!$targetUser) {
            HttpResponse::notFound(['message' => 'Alumni not found.']);
        }

        if ($targetUser->role !== Role::ALUMNI || $targetUser->email === $user['email']) {
            HttpResponse::forbidden(['message' => 'You are not allowed to perform this action.']);
        }

        if ($targetProfile->verStatDean === VerificationStatus::VERIFIED) {
            HttpResponse::conflict(['message' => 'Alumni is already verified.']);
        }

        $targetProfile->verStatDean = VerificationStatus::VERIFIED;
        
        $updatedTargetProfile = $this->service->update($targetProfile->id, $targetProfile->toArray());

        if (!$updatedTargetProfile) {
            HttpResponse::server(['message' => 'Unable to verify Alumni due to an error.']);
        } else {
            RejectionMessageDean::deleteAllByAlumniId($this->pdo, $targetProfile->id);
        }

        HttpResponse::ok($updatedTargetProfile);
    }

    public function reject(Request $req, array $cont): void
    {
        $user = $cont['user'];
        $userProfileId = $user['profile']['id'];
        $id = Validator::requiredInt('id', $req->fromParams('id'), 1);
        $message = Validator::requiredString('Rejection Reason', $req->fromBody('message'), 1, 1000);

        $targetUser = User::findById($this->pdo, $id);
        $targetProfile = ProfileAlumni::findByUserId($this->pdo, $id);

        if (!$targetUser) {
            HttpResponse::notFound(['message' => 'Alumni not found.']);
        }

        if ($targetUser->role !== Role::ALUMNI || $targetUser->email === $user['email']) {
            HttpResponse::forbidden(['message' => 'You are not allowed to perform this action.']);
        }

        if ($targetProfile->verStatDean === VerificationStatus::REJECTED) {
            HttpResponse::conflict(['message' => 'Alumni is already rejected.']);
        }

        $targetProfile->verStatDean = VerificationStatus::REJECTED;
        
        $newProfile = $targetProfile->toArray();
        $newProfile['message'] = $message;
        $newProfile['alumni_id'] = $targetProfile->id;
        $newProfile['dean_id'] = $userProfileId;
        
        $updatedTargetProfile = $this->service->reject($targetProfile->id, $newProfile);

        if (!$updatedTargetProfile) {
            HttpResponse::server(['message' => 'Unable to reject Alumni due to an error.']);
        }

        HttpResponse::ok($updatedTargetProfile);
    }

    public function pend(Request $req, array $cont): void
    {
        $user = $cont['user'];
        $id = Validator::requiredInt('id', $req->fromParams('id'), 1);

        $targetUser = User::findById($this->pdo, $id);
        $targetProfile = ProfileAlumni::findByUserId($this->pdo, $id);

        if (!$targetUser) {
            HttpResponse::notFound(['message' => 'Alumni not found.']);
        }

        if ($targetUser->role !== Role::ALUMNI || $targetUser->email === $user['email']) {
            HttpResponse::forbidden(['message' => 'You are not allowed to perform this action.']);
        }

        if ($targetProfile->verStatDean === VerificationStatus::PENDING) {
            HttpResponse::conflict(['message' => 'Alumni is already pending for verification.']);
        }

        $targetProfile->verStatDean = VerificationStatus::PENDING;
        
        $updatedTargetProfile = $this->service->update($targetProfile->id, $targetProfile->toArray());
        
        if (!$updatedTargetProfile) {
            HttpResponse::server(['message' => 'Unable to mark the alumni as pending.']);
        } else {
            RejectionMessageDean::deleteAllByAlumniId($this->pdo, $targetProfile->id);
        }

        HttpResponse::ok($this->service->findById($targetProfile->id));
    }

    public function writeRejectionAppeal(Request $req, array $cont): void
    {
        $profile = $cont['user']['profile'];

        if (VerificationStatus::from($profile['ver_stat_dean']) !== VerificationStatus::REJECTED) {
            HttpResponse::forbidden(['message' => 'Your account is not rejected by the Dean. No need to write an appeal.']);
        }
        
        $rejectionId = Validator::requiredInt('Rejection Id', $req->fromBody('rejection_id'), 1);
        $message     = Validator::requiredString('Appeal', $req->fromBody('appeal'), 1, 1000);

        $rejectionMessage = RejectionMessageDean::findById($this->pdo, $rejectionId);
        
        if (!$rejectionMessage) {
            HttpResponse::notFound(['message' => 'Rejection was not found.']);
        }

        if ($rejectionMessage->alumniId !== $profile['id']) {
            HttpResponse::bad(['message' => 'Invalid action.']);
        }

        if (RejectionMessageDeanAppeal::findByMessageId($this->pdo, $rejectionId))
            HttpResponse::bad(['message' => 'You have already written an appeal for this rejection.']);

        $rejectionAppeal = RejectionMessageDeanAppeal::create($this->pdo, [
            'message_id' => $rejectionId,
            'message'    => $message
        ]);

        if (!$rejectionAppeal) {
            HttpResponse::server(['message' => 'Unable to write appeal due to an error.']);
        }

        HttpResponse::ok($rejectionAppeal->toArray());
    }
}