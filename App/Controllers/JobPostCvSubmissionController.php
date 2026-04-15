<?php

namespace App\Controllers;

use App\Core\HttpResponse;
use App\Core\Request;
use App\Core\Types\CvReviewStatus;
use App\Core\Validator;
use App\Models\JobPost;
use App\Models\JobPostCvSubmission;
use App\Models\TargetCourse;
use App\Services\JobPostCvSubmissionService;
use PDO;

class JobPostCvSubmissionController
{
    private PDO $pdo;
    private JobPostCvSubmissionService $service;
    
    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
        $this->service = new JobPostCvSubmissionService($this->pdo);
    }

    public function showAllForCompany(Request $req, array $cont): void
    {
        $profile = $cont['user']['profile'];
        $profileId = $profile['id'];
        $jobPostId = Validator::requiredInt('Job Post ID', $req->fromParams('id'), 1);

        $jobPost = JobPost::findById($this->pdo, $jobPostId);

        if (!$jobPost)
            HttpResponse::notFound(['message' => 'Job post not found.']);

        if ($jobPost->companyId !== $profileId)
            HttpResponse::forbidden(['message' => 'You are not allowed to perform this action.']);

        HttpResponse::ok($this->service->findByAllByPostId($jobPostId));
    }

    public function showAllForAlumni(Request $req, array $cont): void
    {
        $profile = $cont['user']['profile'];
        $profileId = $profile['id'];
        HttpResponse::ok($this->service->findByAllByAlumniId($profileId));
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
            HttpResponse::forbidden(['message' => 'You cannot submit your CV to a job post that is not for your course.']);
        
        if (!$jobPost->open)
            HttpResponse::forbidden(['message' => 'You cannot submit your CS to a closed job post.']);
        
        if (JobPostCvSubmission::findByAlumniAndPostId($this->pdo, $profileId, $jobPostId))
            HttpResponse::conflict(['message' => 'You have already submitted your CV to the post.']);
        
        $newSubmission = JobPostCvSubmission::create($this->pdo, [
            'alumni_id'   => $profileId,
            'job_post_id' => $jobPostId
        ]);

        if (!$newSubmission)
            HttpResponse::server(['message' => 'Unable to submit CV due to an error.']);
        
        HttpResponse::ok(["message" => "CV has been submitted."]);
    }

    public function markAsReviewed(Request $req, array $cont): void
    {
        $profile = $cont['user']['profile'];
        $profileId = $profile['id'];
        $submissionId = Validator::requiredInt('Submission ID', $req->fromParams('id'), 1);

        $submission = JobPostCvSubmission::findById($this->pdo, $submissionId);

        if (!$submission)
            HttpResponse::notFound(['message' => 'Submission not found.']);
        
        $jobPost = JobPost::findById($this->pdo, $submission->jobPostId);

        if ($jobPost->companyId !== $profileId)
            HttpResponse::forbidden(['message' => 'You are not allowed to perform this action.']);

        $udpatedSubmission = JobPostCvSubmission::markAsReviewed($this->pdo, $submissionId);

        if ($udpatedSubmission->status !== CvReviewStatus::REVIEWED)
            HttpResponse::server(['message' => 'Unable to set submission as reviewed due to an error.']);

        HttpResponse::ok(['message' => 'CV submission has been marked as Reviewed.']);
    }

    public function destroy(Request $req, array $cont): void
    {
        $profileId = $cont['user']['profile']['id'];
        $jobPostId = Validator::requiredInt('Job Post ID', $req->fromParams('id'), 1);

        $jobPost = JobPost::findById($this->pdo, $jobPostId);

        if (!$jobPost)
            HttpResponse::notFound(['message' => 'Job post not found.']);

        $submission = JobPostCvSubmission::findByAlumniAndPostId($this->pdo, $profileId, $jobPostId);

        if ($submission->status === CvReviewStatus::REVIEWED)
            HttpResponse::forbidden(['message' => 'Unable to unsubmit CV. Your CV has been reviewed already.']);

        if (!$submission)
            HttpResponse::bad(['message' => 'You did not submitted your CV to this post.']);

        $deletedLike = JobPostCvSubmission::deleteById($this->pdo, $submission->id);

        if (!$deletedLike)
            HttpResponse::server(['message' => 'Unable to unsubmit CV due to an error.']);
        
        HttpResponse::ok(["message" => "CV unsubmitted successfully."]);
    }
}