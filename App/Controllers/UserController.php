<?php

namespace App\Controllers;

use App\Core\HttpResponse;
use App\Core\Password;
use App\Core\Request;
use App\Core\Types\Link;
use App\Core\Validator;
use App\Models\PasswordReset;
use App\Models\User;
use App\Models\Verification;
use App\Services\MailingService;
use App\Services\UserService;
use DateTime;
use PDO;

class UserController
{
    private PDO $pdo;
    private UserService $service;
    private MailingService $mailingService;
    
    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
        $this->service = new UserService($this->pdo);
        $this->mailingService = MailingService::forProd();
    }

    public function updatePassword(Request $req, array $cont): void
    {
        $currentPassword = Validator::requiredString('Current Password', $req->fromBody('current_password'), 8, 65);
        $newPassword = Validator::requiredString('New Password', $req->fromBody('new_password'), 8, 65);

        $user = User::findById($this->pdo, $cont['user']['id']);

        if (!$user)
            HttpResponse::notFound(['message' => 'User not found.']);
        
        if (!Password::verify($currentPassword, $user->passwordHash))
            HttpResponse::bad(['message' => 'Current password is incorrect.']);

        $updatedUser = User::updatePassword($this->pdo, $user->id, Password::hash($newPassword));

        if (!$updatedUser)
            HttpResponse::server(['message' => 'Unable to update password due to an error.']);
        
        HttpResponse::ok(["message" => "Password has been updated."]);
    }

    public function sendPasswordResetLink(Request $req, array $cont): void
    {
        $email = Validator::email('Email', $req->fromBody('email'));

        $user = User::findByEmail($this->pdo, $email);

        if (!$user)
            HttpResponse::notFound(['message' => 'Email unrecognized.']);

        if (!$user->enabled)
            HttpResponse::bad(['message' => 'Account is currently disabled.']);

        if (!$user->emailVerified)
            HttpResponse::bad(['message' => 'Email should be verified first.']);

        $userWithProfile = $this->service->findByEmail($email);

        $token = substr(bin2hex(random_bytes(16)), 0, 8);
        $resetLink = Link::PASSWORD_RESET->value . $token;
        PasswordReset::create($this->pdo, [
            'user_id' => $user->id,
            'token'   => $token
        ]);
        $this->mailingService->sendForgotPasswordResetLink($userWithProfile, $resetLink);

        HttpResponse::ok(['message' => 'Link has been sent. You may now check your inbox.']);
    }

    public function resetPassword(Request $req, array $cont): void
    {
        $token = Validator::requiredString('token', $req->fromBody('token'), 1, 100);
        $newPassword = Validator::requiredString('new_password', $req->fromBody('new_password'), 8, 65);

        $passwordReset = PasswordReset::findByToken($this->pdo, $token);

        if (!$passwordReset)
            HttpResponse::bad(['message' => 'Invalid password reset token.']);

        if (new DateTime() > $passwordReset->expiresAt)
            HttpResponse::bad(['message' => 'Password reset link has expired.']);

        if ($passwordReset->used)
            HttpResponse::bad(['message' => 'Password reset link was already used.']);

        $user = User::findById($this->pdo, $passwordReset->userId);
        $updatedUser = User::updatePassword($this->pdo, $user->id, Password::hash($newPassword));

        if (!$updatedUser)
            HttpResponse::server(['message' => 'Unable to update password due to an error.']);

        PasswordReset::setAsUsed($this->pdo, $passwordReset->id);

        $userWithProfile = $this->service->findById($user->id);

        $this->mailingService->sendPasswordResetSuccess($userWithProfile);
        
        HttpResponse::ok(["message" => "Password has been reset. You may now try logging-in with your new password."]);
    }

    public function sendVerificationLink(Request $req, array $cont): void
    {
        $email = Validator::email('Email', $req->fromBody('email'));

        $user = User::findByEmail($this->pdo, $email);

        if (!$user)
            HttpResponse::notFound(['message' => 'Email unrecognized.']);

        if (!$user->enabled)
            HttpResponse::bad(['message' => 'Account is currently disabled.']);
        
        if ($user->emailVerified)
            HttpResponse::bad(['message' => 'Your email is already verified.']);

        $userWithProfile = $this->service->findByEmail($email);

        $token = substr(bin2hex(random_bytes(16)), 0, 8);
        $verificationLink = Link::EMAIL_VERIFICATION->value . $token;
        Verification::create($this->pdo, [
            'user_id' => $user->id,
            'token'   => $token
        ]);
        $this->mailingService->sendExistingWithEmailVerification($userWithProfile, $verificationLink);

        HttpResponse::ok(['message' => 'Link has been sent. You may now check your inbox.']);
    }
}