<?php

namespace App\Controllers;

use App\Core\HttpResponse;
use App\Core\Request;
use App\Core\Types\Link;
use App\Core\Validator;
use App\Models\User;
use App\Models\Verification;
use App\Services\MailingService;
use App\Services\UserService;
use App\Utils\ArrayLogger;
use DateTime;
use PDO;

class VerificationController
{
    private PDO $pdo;
    private UserService $userService;
    private MailingService $mailingService;
    
    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
        $this->userService = new UserService($this->pdo);
        $this->mailingService = MailingService::forProd();
    }

    public function verify(Request $req, array $cont): void
    {
        $token = Validator::requiredString('Verification Token', $req->fromParams('token'), 1, 100);

        $verification = Verification::findByToken($this->pdo, $token);

        ArrayLogger::log($verification->toArray());

        if (!$verification)
            HttpResponse::bad(['message' => 'Invalid verification token.']);

        if (new DateTime() > $verification->expiresAt)
            HttpResponse::bad(['message' => 'Verification link has expired.']);

        if ($verification->used)
            HttpResponse::bad(['message' => 'Verification link was already used.']);

        $user = User::findById($this->pdo, $verification->userId);
        $user->enabled = true;
        $user->emailVerified = true;
        $user->emailVerifiedAt = new DateTime();
        $updatedUser = $this->userService->update($verification->userId, $user->toArray());

        if (!$updatedUser)
            HttpResponse::server(['message' => 'Unable to verify account due to an error.']);

        Verification::setAsUsed($this->pdo, $verification->id);

        $this->mailingService->sendEmailVerified($this->userService->findById($verification->userId), Link::LOGIN->value);

        header("Location: " . Link::LOGIN->value . "?verified=true");
        exit();
    }
}