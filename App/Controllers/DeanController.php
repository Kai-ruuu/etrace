<?php

namespace App\Controllers;

use App\Core\HttpResponse;
use App\Core\Password;
use App\Core\Request;
use App\Core\Types\Role;
use App\Core\Validator;
use App\Models\ProfileDean;
use App\Models\User;
use App\Services\ProfileDeanService;
use App\Services\UserService;
use Exception;
use PDO;

class DeanController
{
    private PDO $pdo;
    private UserService $userService;
    private ProfileDeanService $deanProfileService;
    
    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
        $this->userService = new UserService($this->pdo);
        $this->deanProfileService = new ProfileDeanService($this->pdo);
    }

    public function showAll(Request $req, array $cont): void
    {
        $enabled = Validator::bool('enabled', $req->fromQuery('enabled'));
        $query   = Validator::string('query', $req->fromQuery('query')) ?? '';
        $page    = Validator::requiredInt('page', $req->fromQuery('page'), 1);
        $perPage = Validator::requiredInt('per_page', $req->fromQuery('per_page'), 1, 20);

        $result = $this->deanProfileService->findAll($enabled, $query, $page, $perPage);

        HttpResponse::ok($result);
    }

    public function store(Request $req, array $cont): void
    {
        $user = $cont['user'];
        $email      = Validator::requiredEmail('email', $req->fromBody('email'));
        $schoolId   = Validator::requiredInt('school_id', $req->fromBody('school_id'), 1);
        $firstName  = Validator::requiredString('first_name', $req->fromBody('first_name'), 1, 50);
        $middleName = Validator::string('middle_name', $req->fromBody('middle_name')) ?? '';
        $lastName   = Validator::requiredString('last_name', $req->fromBody('last_name'), 1, 50);

        try {
            $newDean = $this->userService->createAdminWithProfile([
                'role'        => Role::DEAN->value,
                'email'       => $email,
                'password'    => Password::generate(),
                'school_id'   => $schoolId,
                'first_name'  => $firstName,
                'middle_name' => $middleName,
                'last_name'   => $lastName,
        ], $cont['user']);
            HttpResponse::ok($newDean);
        } catch (Exception $e) {
            HttpResponse::server(['message' => 'Unable to create Dean.']);
        }
    }

    public function enable(Request $req, array $cont): void
    {
        $user = $cont['user'];
        $id = Validator::requiredInt('id', $req->fromParams('id'), 1);

        $targetUser = User::findById($this->pdo, $id);

        if (!$targetUser) {
            HttpResponse::notFound(['message' => 'Dean not found.']);
        }

        if ($targetUser->role !== Role::DEAN || $targetUser->email === $user['email']) {
            HttpResponse::forbidden(['message' => 'You are not allowed to perform this action.']);
        }

        if ($targetUser->enabled) {
            HttpResponse::conflict(['message' => 'Dean is already enabled.']);
        }
        
        $targetUser->enabled = true;
        $updatedTargetUser = $this->userService->update($id, $targetUser->toArray());

        if (!$updatedTargetUser) {
            HttpResponse::server(['message' => 'Unable to enable Dean.']);
        }

        HttpResponse::ok($updatedTargetUser);
    }

    public function disable(Request $req, array $cont): void
    {
        $user = $cont['user'];
        $id = Validator::requiredInt('id', $req->fromParams('id'), 1);
        
        $targetUser = User::findById($this->pdo, $id);

        if (!$targetUser) {
            HttpResponse::notFound(['message' => 'Dean not found.']);
        }

        if ($targetUser->role !== Role::DEAN || $targetUser->email === $user['email']) {
            HttpResponse::forbidden(['message' => 'You are not allowed to perform this action.']);
        }

        if (!$targetUser->enabled) {
            HttpResponse::conflict(['message' => 'Dean is already disabled.']);
        }
        
        $targetUser->enabled = false;
        $updatedTargetUser = $this->userService->update($id, $targetUser->toArray());

        if (!$updatedTargetUser) {
            HttpResponse::server(['message' => 'Unable to disable Dean.']);
        }

        HttpResponse::ok($updatedTargetUser);
    }

    public function agreeToConsent(Request $req, array $cont): void
    {
        $user = $cont['user'];
        $userProfile = ProfileDean::findByUserId($this->pdo, $user['id']);
        $userProfile->agreedToConsent = true;

        $updatedProfile = ProfileDean::update($this->pdo, $userProfile->id, $userProfile->toArray());

        if (!$updatedProfile)
            HttpResponse::server(['message' => 'Unable to proceed due to an error.']);
        
        HttpResponse::ok($this->userService->findById($user['id']));
    }
}