<?php

namespace App\Services;

use App\Models\Course;
use App\Models\JobPost;
use App\Models\JobPostCvSubmission;
use App\Models\Occupation;
use App\Models\OccupationState;
use App\Models\ProfileAlumni;
use App\Models\ProfileCompany;
use App\Models\Qualification;
use App\Models\SocialMedia;
use App\Models\SocialMediaPlatform;
use App\Models\User;
use PDO;

class JobPostCvSubmissionService
{
    private PDO $pdo;
    
    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    private function attachRequired(JobPostCvSubmission $submission): array
    {
        $submissionArray = $submission->toArray();
        $submissionArray['post'] = JobPost::findById($this->pdo, $submission->jobPostId)->toArray();
        $submissionArray['post']['company'] = ProfileCompany::findById($this->pdo, $submissionArray['post']['company_id'])->toArray();
        $submissionArray['post']['qualifications'] = array_map(fn($q) => $q->toArray() , Qualification::findAllByJobPostId($this->pdo, $submission->jobPostId));
        $submissionArray['alumni'] = ProfileAlumni::findById($this->pdo, $submission->alumniId)->toArray();
        $socialMedias = SocialMedia::findAllByAlumniId($this->pdo, $submissionArray['alumni']['id']);
        $socialMedias = array_map(fn($s) => array_merge(
            $s->toArray(),
            ['platform' => SocialMediaPlatform::findById($this->pdo, $s->platformId)?->toArray()]
        ), $socialMedias);
        $submissionArray['alumni']['occupations'] = array_map(function ($os) {
            $occupationStateArray = $os->toArray();
            $occupationStateArray['occupation'] = Occupation::findById($this->pdo, $os->occupationId)->toArray();
            return $occupationStateArray;
        }, OccupationState::findAllByAlumniId($this->pdo, $submissionArray['alumni']['id']));
        $submissionArray['alumni']['social_medias'] = $socialMedias;
        $submissionArray['alumni']['course'] = Course::findById($this->pdo, $submissionArray['alumni']['course_id']);
        $submissionArray['alumni']['email'] = User::findById($this->pdo , $submissionArray['alumni']['user_id'])->email;
        return $submissionArray;
    }

    public function findByAllByPostId(int $id): ?array
    {
        $submissions = JobPostCvSubmission::findByAllByPostId($this->pdo, $id);
        return $submissions
            ? array_map(fn($sub) => $this->attachRequired($sub), $submissions)
            : null;
    }

    public function findByAllByAlumniId(int $id): ?array
    {
        $submissions = JobPostCvSubmission::findByAllByAlumniId($this->pdo, $id);
        return $submissions
            ? array_map(fn($sub) => $this->attachRequired($sub), $submissions)
            : null;
    }
}