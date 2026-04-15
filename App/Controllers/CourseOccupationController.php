<?php

namespace App\Controllers;

use App\Core\HttpResponse;
use App\Core\Request;
use App\Core\Validator;
use App\Models\Course;
use App\Models\Occupation;
use App\Services\CourseOccupationService;
use Exception;
use PDO;

class CourseOccupationController
{
    private PDO $pdo;
    private CourseOccupationService $service;
    
    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
        $this->service = new CourseOccupationService($this->pdo);
    }

    public function store(Request $req, array $cont): void
    {
        $courseId = Validator::requiredInt('course_id', $req->fromBody('course_id'), 1);
        $occupationId = Validator::requiredInt('occupation_id', $req->fromBody('occupation_id'), 1);

        $course = Course::findById($this->pdo, $courseId);

        if ($course === null) {
            HttpResponse::notFound(['message' => 'Course not found.']);
        }
        
        $occupation = Occupation::findById($this->pdo, $occupationId);

        if ($occupation === null) {
            HttpResponse::notFound(['message' => 'Occupation not found.']);
        }

        try {
            $courseOccupation = $this->service->create([
                'course_id' => $courseId,
                'occupation_id' => $occupationId,
            ]);
            HttpResponse::ok($courseOccupation);
        } catch (Exception $e) {
            HttpResponse::server(["message" => "Unable to align occupaion to course due to an error."]);
        }
    }

    public function destroy(Request $req, array $cont): void
    {
        $courseId = Validator::requiredInt('course_id', $req->fromBody('course_id'), 1);
        $occupationId = Validator::requiredInt('occupation_id', $req->fromBody('occupation_id'), 1);

        $course = Course::findById($this->pdo, $courseId);

        if ($course === null) {
            HttpResponse::notFound(['message' => 'Course not found.']);
        }
        
        $occupation = Occupation::findById($this->pdo, $occupationId);

        if ($occupation === null) {
            HttpResponse::notFound(['message' => 'Occupation not found.']);
        }
        
        $courseOccupation = $this->service->findByIds($courseId, $occupationId);

        if ($courseOccupation === null) {
            HttpResponse::notFound(['message' => 'Alignment not found.']);
        }
        
        if (!$this->service->delete($courseId, $occupationId)) {
            HttpResponse::server(['message' => 'Unable to unalign occupation to course due to an error.']);
        }
        
        HttpResponse::ok(["message" => "Occupation has been successfully unaligned to course."]);
    }
}