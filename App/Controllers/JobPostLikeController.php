<?php

namespace App\Controllers;

use App\Core\HttpResponse;
use App\Core\Request;
use App\Core\Validator;
use App\Models\JobPost;
use App\Models\JobPostLike;
use App\Models\TargetCourse;
use App\Services\JobPostLikeService;
use PDO;

class JobPostLikeController
{
    private PDO $pdo;
    private JobPostLikeService $service;
    
    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
        $this->service = new JobPostLikeService($this->pdo);
    }

    public function showAll(Request $req, array $cont): void
    {
        $profile = $cont['user']['profile'];
        $profileId = $profile['id'];
        HttpResponse::ok($this->service->findAllByAlumniId($profileId));
    }

    public function store(Request $req, array $cont): void
    {
        $profile = $cont['user']['profile'];
        $profileId = $profile['id'];
        $courseId = $profile['course_id'];
        $jobPostId = Validator::requiredInt('Job Post ID', $req->fromParams('id'), 1);

        $jobPost = JobPost::findById($this->pdo, $jobPostId);

        if (!$jobPost)
            HttpResponse::notFound(['message' => 'Job post not found.']);

        if (!TargetCourse::findByCourseAndPostId($this->pdo, $courseId, $jobPostId))
            HttpResponse::forbidden(['message' => 'You cannot like a post that is not for your course.']);
        
        if (!$jobPost->open)
            HttpResponse::forbidden(['message' => 'You cannot like a closed job post.']);
        
        if (JobPostLike::findByAlumniAndPostId($this->pdo, $profileId, $jobPostId))
            HttpResponse::conflict(['message' => 'You have already liked the post.']);
        
        $newLike = JobPostLike::create($this->pdo, [
            'alumni_id'   => $profileId,
            'job_post_id' => $jobPostId
        ]);

        if (!$newLike)
            HttpResponse::server(['message' => 'Unable to like post due to an error.']);
        
        HttpResponse::ok(["message" => "Post has been liked."]);
    }

    public function destroy(Request $req, array $cont): void
    {
        $profileId = $cont['user']['profile']['id'];
        $jobPostId = Validator::requiredInt('Job Post ID', $req->fromParams('id'), 1);

        $jobPost = JobPost::findById($this->pdo, $jobPostId);

        if (!$jobPost)
            HttpResponse::notFound(['message' => 'Job post not found.']);

        $like = JobPostLike::findByAlumniAndPostId($this->pdo, $profileId, $jobPostId);

        if (!$like)
            HttpResponse::bad(['message' => 'You did not like this post.']);

        $deletedLike = JobPostLike::deleteById($this->pdo, $like->id);

        if (!$deletedLike)
            HttpResponse::server(['message' => 'Unable to dislike the post due to an error.']);
        
        HttpResponse::ok(["message" => "Post has been disliked."]);
    }
}