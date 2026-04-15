<?php

namespace App\Controllers;

use App\Core\HttpResponse;
use App\Core\Password;
use App\Core\Request;
use App\Core\Types\Role;
use App\Core\Validator;
use App\Models\ProfileSystemAdmin;
use App\Models\User;
use App\Services\ProfileSystemAdminService;
use App\Services\UserService;
use Exception;
use PDO;

class SystemAdminController
{
    private PDO $pdo;
    private UserService $userService;
    private ProfileSystemAdminService $systemAdminProfileService;
    
    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
        $this->userService = new UserService($this->pdo);
        $this->systemAdminProfileService = new ProfileSystemAdminService($this->pdo);
    }

    public function showAll(Request $req, array $cont): void
    {
        $user = $cont['user'];
        $enabled = Validator::bool('enabled', $req->fromQuery('enabled'));
        $query   = Validator::string('query', $req->fromQuery('query')) ?? '';
        $page    = Validator::requiredInt('page', $req->fromQuery('page'), 1);
        $perPage = Validator::requiredInt('per_page', $req->fromQuery('per_page'), 1, 20);
        
        if ($_ENV['SYSAD_EMAIL'] !== $user['email']) {   
            HttpResponse::forbidden(['message' => 'You are not allowed to perform this action.']);
        }

        $result = $this->systemAdminProfileService->findAll($enabled, $query, $page, $perPage);

        HttpResponse::ok($result);
    }

    public function store(Request $req, array $cont): void
    {
        $user = $cont['user'];
        $email      = Validator::requiredEmail('email', $req->fromBody('email'));
        $firstName  = Validator::requiredString('first_name', $req->fromBody('first_name'), 1, 50);
        $middleName = Validator::string('middle_name', $req->fromBody('middle_name')) ?? '';
        $lastName   = Validator::requiredString('last_name', $req->fromBody('last_name'), 1, 50);

        if ($_ENV['SYSAD_EMAIL'] !== $user['email']) {   
            HttpResponse::forbidden(['message' => 'You are not allowed to perform this action.']);
        }

        $password = Password::generate();

        try {
            $newSystemAdmin = $this->userService->createAdminWithProfile([
                'role'        => Role::SYSTEM_ADMIN->value,
                'email'       => $email,
                'password'    => $password,
                'first_name'  => $firstName,
                'middle_name' => $middleName,
                'last_name'   => $lastName,
            ], $user);
            HttpResponse::ok($newSystemAdmin);
        } catch (Exception $e) {
            HttpResponse::server(['message' => 'Unable to create System Administrator.']);
        }
    }

    public function enable(Request $req, array $cont): void
    {
        $user = $cont['user'];
        $id = Validator::requiredInt('id', $req->fromParams('id'), 1);
        
        if ($_ENV['SYSAD_EMAIL'] !== $user['email']) {   
            HttpResponse::forbidden(['message' => 'You are not allowed to perform this action.']);
        }

        $targetUser = User::findById($this->pdo, $id);

        if (!$targetUser) {
            HttpResponse::notFound(['message' => 'System Administrator not found.']);
        }

        if ($targetUser->role !== Role::SYSTEM_ADMIN || $targetUser->email === $user['email']) {
            HttpResponse::forbidden(['message' => 'You are not allowed to perform this action.']);
        }

        if ($targetUser->enabled) {
            HttpResponse::conflict(['message' => 'System Administrator is already enabled.']);
        }
        
        $targetUser->enabled = true;
        $updatedTargetUser = $this->userService->update($id, $targetUser->toArray());

        if (!$updatedTargetUser) {
            HttpResponse::server(['message' => 'Unable to enable System Administrator.']);
        }

        HttpResponse::ok($updatedTargetUser);
    }

    public function disable(Request $req, array $cont): void
    {
        $user = $cont['user'];
        $id = Validator::requiredInt('id', $req->fromParams('id'), 1);
        
        if ($_ENV['SYSAD_EMAIL'] !== $user['email']) {   
            HttpResponse::forbidden(['message' => 'You are not allowed to perform this action.']);
        }

        $targetUser = User::findById($this->pdo, $id);

        if (!$targetUser) {
            HttpResponse::notFound(['message' => 'System Administrator not found.']);
        }

        if ($targetUser->role !== Role::SYSTEM_ADMIN || $targetUser->email === $user['email']) {
            HttpResponse::forbidden(['message' => 'You are not allowed to perform this action.']);
        }

        if (!$targetUser->enabled) {
            HttpResponse::conflict(['message' => 'System Administrator is already disabled.']);
        }
        
        $targetUser->enabled = false;
        $updatedTargetUser = $this->userService->update($id, $targetUser->toArray());

        if (!$updatedTargetUser) {
            HttpResponse::server(['message' => 'Unable to disable System Administrator.']);
        }

        HttpResponse::ok($updatedTargetUser);
    }

    public function agreeToConsent(Request $req, array $cont): void
    {
        $user = $cont['user'];
        $userProfile = ProfileSystemAdmin::findByUserId($this->pdo, $user['id']);
        $userProfile->agreedToConsent = true;

        $updatedProfile = ProfileSystemAdmin::update($this->pdo, $userProfile->id, $userProfile->toArray());

        if (!$updatedProfile)
            HttpResponse::server(['message' => 'Unable to proceed due to an error.']);
        
        HttpResponse::ok($this->userService->findById($user['id']));
    }
}