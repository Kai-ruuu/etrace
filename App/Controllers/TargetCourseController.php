<?php

namespace App\Controllers;

use App\Core\HttpResponse;
use App\Core\Request;
use App\Core\Validator;
use App\Models\Course;
use App\Models\JobPost;
use App\Models\TargetCourse;
use PDO;

class TargetCourseController
{
    private PDO $pdo;
    
    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    public function store(Request $req, array $cont): void
    {
        $profileId = $cont['user']['profile']['id'];
        $jobPostId = Validator::requiredInt('Job Post ID', $req->fromParams('job_post_id'), 1);
        $courseId = Validator::requiredInt('Course ID', $req->fromParams('course_id'), 1);

        $jobPost = JobPost::findById($this->pdo, $jobPostId);

        if ($jobPost->companyId !== $profileId)
            HttpResponse::forbidden(['message' => 'You are not allowed to perform this action.']);

        $course = Course::findById($this->pdo, $courseId);

        if (!$course)
            HttpResponse::conflict(['message' => 'Course not found.']);

        $targetCourse = TargetCourse::findByCourseAndPostId($this->pdo, $courseId, $jobPostId);

        if ($targetCourse)
            HttpResponse::conflict(['message' => 'Job Post is already targeting the course.']);
        
        $newTargetCourse = TargetCourse::create($this->pdo, [
            'job_post_id' => $jobPostId,
            'course_id'   => $courseId  
        ]);

        if (!$newTargetCourse)
            HttpResponse::server(['message' => 'Unable to target course due to an error.']);
        
        HttpResponse::ok($newTargetCourse->toArray());
    }

    public function destroy(Request $req, array $cont): void
    {
        $profileId = $cont['user']['profile']['id'];
        $targetCourseId = Validator::requiredInt('Target Course ID', $req->fromParams('id'), 1);

        $targetCourse = TargetCourse::findById($this->pdo, $targetCourseId);

        if (!$targetCourse)
            HttpResponse::notFound(['message' => 'Course is not targeted by the job post.']);
        
        $jobPost = JobPost::findById($this->pdo, $targetCourse->jobPostId);

        if ($jobPost->companyId !== $profileId)
            HttpResponse::forbidden(['message' => 'You are not allowed to perform this action.']);
        
        $deletedTargetCourse = TargetCourse::deleteById($this->pdo, $targetCourseId);

        if (!$deletedTargetCourse)
            HttpResponse::server(['message' => 'Unable to untarget course due to an error.']);
        
        HttpResponse::ok(["message" => "Course has been has been removed from target courses."]);
    }
}