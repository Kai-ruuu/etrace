<?php

namespace App\Controllers;

use App\Core\HttpResponse;
use App\Core\Request;
use App\Core\Validator;
use App\Models\JobPost;
use App\Models\Qualification;
use PDO;

class QualificationController
{
    private PDO $pdo;
    
    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    public function store(Request $req, array $cont): void
    {
        $profileId = $cont['user']['profile']['id'];
        $jobPostId = Validator::requiredInt('Job Post ID', $req->fromParams('id'), 1);
        $qualification = Validator::requiredString('Qualification', $req->fromBody('qualification'), 1, 500);

        $jobPost = JobPost::findById($this->pdo, $jobPostId);

        if ($jobPost->companyId !== $profileId)
            HttpResponse::forbidden(['message' => 'You are not allowed to perform this action.']);

        $quali = Qualification::findByQualificationAndPostId($this->pdo, $qualification, $jobPostId);

        if ($quali)
            HttpResponse::conflict(['message' => 'Qualification is already added to the post.']);

        $newQuali = Qualification::create($this->pdo, [
            'job_post_id'   => $jobPostId,
            'qualification' => $qualification
        ]);

        if (!$newQuali)
            HttpResponse::server(['message' => 'Unable to add qualification due to an error.']);
        
        HttpResponse::ok($newQuali->toArray());
    }

    public function destroy(Request $req, array $cont): void
    {
        $profileId = $cont['user']['profile']['id'];
        $qualiId = Validator::requiredInt('Qualification ID', $req->fromParams('id'), 1);

        $qualification = Qualification::findById($this->pdo, $qualiId);

        if (!$qualification)
            HttpResponse::notFound(['message' => 'Qualification not found.']);
        
        $jobPost = JobPost::findById($this->pdo, $qualification->jobPostId);

        if ($jobPost->companyId !== $profileId)
            HttpResponse::forbidden(['message' => 'You are not allowed to perform this action.']);
        
        $deletedQuali = Qualification::deleteById($this->pdo, $qualiId);

        if (!$deletedQuali)
            HttpResponse::server(['message' => 'Unable to remove qualification due to an error.']);
        
        HttpResponse::ok(["message" => "Qualification has been removed."]);
    }
}