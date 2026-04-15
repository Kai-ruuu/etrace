<?php

namespace App\Controllers;

use App\Core\HttpResponse;
use App\Core\Request;
use App\Core\Validator;
use App\Models\Course;
use App\Services\CourseService;
use App\Utils\ArrayLogger;
use PDO;

class CourseController
{
    private PDO $pdo;
    private CourseService $service;
    
    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
        $this->service = new CourseService($this->pdo);
    }

    public function showAbsoluteAll(Request $req, array $cont): void
    {
        $result = Course::findAll($this->pdo);
        HttpResponse::ok(array_map(fn($result) => $result->toArray(), $result));
    }

    public function showAllForDean(Request $req, array $cont): void
    {
        $user = $cont['user'];
        $schoolId = $user['profile']['school_id'];
        $result = Course::findAllForDean($this->pdo, $schoolId);
        HttpResponse::ok(array_map(fn($result) => $result->toArray(), $result));
    }


    public function showAll(Request $req, array $cont): void
    {
        $user = $cont['user'];
        $schoolId = $user['profile']['school_id'];
        $archived = Validator::bool('archived', $req->fromQuery('archived'));
        $query    = Validator::string('query', $req->fromQuery('query')) ?? '';
        $page     = Validator::requiredInt('page', $req->fromQuery('page'), 1);
        $perPage  = Validator::requiredInt('per_page', $req->fromQuery('per_page'), 1, 20);
        
        $result = $this->service->findAll($schoolId, $archived, $query, $page, $perPage);
        
        HttpResponse::ok($result);
    }

    public function store(Request $req, array $cont): void
    {
        $user = $cont['user'];
        $schoolId = $user['profile']['school_id'];
        $name = Validator::requiredString('name', $req->fromBody('name'), 1, 255);
        $code = Validator::requiredString('code', $req->fromBody('code'), 1, 10);

        $course = $this->service->findByName($name);

        if ($course !== null) {
            HttpResponse::conflict(['message' => 'Course with the same name already exists.']);
        }

        $course = $this->service->findByCode($code);

        if ($course !== null) {
            HttpResponse::conflict(['message' => 'Course with the same code already exists.']);
        }

        $newCourse = $this->service->create([
            'school_id' => $schoolId,
            'name' => $name,
            'code' => $code,
        ]);
        
        if (!$newCourse === null) {
            HttpResponse::server(['message' => 'Unable to create course.']);
        }

        HttpResponse::ok($newCourse);
    }

    public function edit(Request $req, array $cont): void
    {
        $user = $cont['user'];
        $schoolId = $user['profile']['school_id'];
        $id = Validator::requiredInt('id', $req->fromParams('id'), 1);
        $name = Validator::requiredString('name', $req->fromBody('name'), 1, 255);
        $code = Validator::requiredString('code', $req->fromBody('code'), 1, 10);
        
        $course = Course::findById($this->pdo, $id);

        if ($course === null) {
            HttpResponse::notFound(['message' => 'Course not found.']);
        }

        if ($course->schoolId !== $schoolId) {
            HttpResponse::forbidden(['message' => 'You are not allowed to edit this course.']);
        }

        $course->name = $name;
        $course->code = $code;
        $updatedCourse = $this->service->update($id, $course->toArray());

        if ($updatedCourse === null) {
            HttpResponse::server(['message' => 'Unable to update course.']);
        }

        HttpResponse::ok($updatedCourse);
    }

    public function restore(Request $req, array $cont): void
    {
        $user = $cont['user'];
        $schoolId = $user['profile']['school_id'];
        $id = Validator::requiredInt('id', $req->fromParams('id'), 1);
        $course = Course::findById($this->pdo, $id);

        if ($course === null) {
            HttpResponse::notFound(['message' => 'Course not found.']);
        }

        if ($course->schoolId !== $schoolId) {
            HttpResponse::forbidden(['message' => 'You are not allowed to edit this course.']);
        }

        if (!$course->archived) {
            HttpResponse::conflict(['message' => 'Course is not archived.']);
        }

        $course->archived = false;
        $updatedCourse = $this->service->update($id, $course->toArray());

        if ($updatedCourse === null) {
            HttpResponse::server(['message' => 'Unable to restore course.']);
        }

        HttpResponse::ok($updatedCourse);
    }

    public function archive(Request $req, array $cont): void
    {
        $user = $cont['user'];
        $schoolId = $user['profile']['school_id'];
        $id = Validator::requiredInt('id', $req->fromParams('id'), 1);

        $course = Course::findById($this->pdo, $id);

        if ($course === null) {
            HttpResponse::notFound(['message' => 'Course not found.']);
        }

        if ($course->schoolId !== $schoolId) {
            HttpResponse::forbidden(['message' => 'You are not allowed to edit this course.']);
        }

        if ($course->archived) {
            HttpResponse::conflict(['message' => 'Course is already archived.']);
        }

        $course->archived = true;
        $updatedCourse = $this->service->update($id, $course->toArray());

        if ($updatedCourse === null) {
            HttpResponse::server(['message' => 'Unable to archive course.']);
        }

        HttpResponse::ok($updatedCourse);
    }
}