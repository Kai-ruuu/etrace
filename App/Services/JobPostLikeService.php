<?php

namespace App\Services;

use App\Models\Course;
use App\Models\JobPost;
use App\Models\JobPostLike;
use App\Models\ProfileAlumni;
use App\Models\ProfileCompany;
use App\Models\Qualification;
use PDO;

class JobPostLikeService
{
    private PDO $pdo;
    
    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    private function attachRequired(JobPostLike $like): array
    {
        $likeArray = $like->toArray();
        $likeArray['post'] = JobPost::findById($this->pdo, $like->jobPostId)->toArray();
        $likeArray['post']['company'] = ProfileCompany::findById($this->pdo, $likeArray['post']['company_id'])->toArray();
        $likeArray['post']['qualifications'] = array_map(fn($q) => $q->toArray() , Qualification::findAllByJobPostId($this->pdo, $like->jobPostId));
        $likeArray['alumni'] = ProfileAlumni::findById($this->pdo, $like->alumniId)->toArray();
        $likeArray['alumni']['course'] = Course::findById($this->pdo, $likeArray['alumni']['course_id']);
        return $likeArray;
    }

    public function findAllByAlumniId(int $id): ?array
    {
        $likes = JobPostLike::findAllByAlumniId($this->pdo, $id);
        return $likes
            ? array_map(fn($sub) => $this->attachRequired($sub), $likes)
            : null;
    }
}