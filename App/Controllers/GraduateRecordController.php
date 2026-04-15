<?php

namespace App\Controllers;

use App\Config\UploadsConfig;
use App\Core\HttpResponse;
use App\Core\Request;
use App\Core\Types\Builtin\Mime;
use App\Core\Upload;
use App\Core\UploadHandler;
use App\Core\Validator;
use App\Models\Course;
use App\Models\GraduateRecord;
use App\Services\GraduateRecordService;
use App\Utils\GraduateRecordValidator;
use Exception;
use PDO;

class GraduateRecordController
{
    private PDO $pdo;
    private GraduateRecordService $service;
    
    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
        $this->service = new GraduateRecordService($this->pdo);
    }

    public function show(Request $req, array $cont): void
    {
        $user     = $cont['user'];
        $schoolId = $user['profile']['school_id'];
        $id       = Validator::requiredInt('id', $req->fromParams('id'), 1);
        $record   = $this->service->findById($id);

        if ($record === null) {
            HttpResponse::notFound(["message" => "Record not found."]);
        }

        $course = Course::findById($this->pdo, $record['course_id']);

        if (!$course)
            HttpResponse::notFound(['message' => 'Course not found.']);

        if ($course->schoolId !== $schoolId) {
            HttpResponse::forbidden(['message' => 'You are not allowed to access this record.']);
        }
        
        $fileName = $record["filename"];
        $filePath = UploadsConfig::folder('graduate_record') . "/" . $fileName;

        if (!file_exists($filePath)) {
            HttpResponse::notFound(["message" => "Record not found."]);
        }

        HttpResponse::ok([
            "filename" => $fileName,
            "content" => file_get_contents($filePath)
        ]);
    }

    public function showByAlumniInfo(Request $req, array $cont): void
    {
        $user     = $cont['user'];
        $schoolId = $user['profile']['school_id'];
        $courseId = Validator::requiredInt('Course ID', $req->fromParams('course_id'), 1);
        $batch = Validator::requiredInt('Batch', $req->fromParams('batch'), 2007, (int) date('Y'));
        $record   = $this->service->findByBatchAndCourseId($batch, $courseId);

        if ($record === null) {
            HttpResponse::notFound(["message" => "There are no matching graduate records based on the alumni information."]);
        }

        $course = Course::findById($this->pdo, $record['course_id']);

        if (!$course)
            HttpResponse::notFound(['message' => 'Course not found.']);

        if ($course->schoolId !== $schoolId) {
            HttpResponse::forbidden(['message' => 'You are not allowed to access this record.']);
        }
        
        $fileName = $record["filename"];
        $filePath = UploadsConfig::folder('graduate_record') . "/" . $fileName;

        if (!file_exists($filePath)) {
            HttpResponse::notFound(["message" => "Record not found."]);
        }

        HttpResponse::ok([
            "filename" => $fileName,
            "content" => file_get_contents($filePath)
        ]);
    }

    public function showAll(Request $req, array $cont): void
    {
        $archived = Validator::bool('archived', $req->fromQuery('archived'));
        $courseId = Validator::int('course_id', $req->fromQuery('course_id'), 1);
        $batch    = Validator::int('batch', $req->fromQuery('batch'), 2007, (int) date('Y'));
        $query    = Validator::string('query', $req->fromQuery('query')) ?? '';
        $page     = Validator::requiredInt('page', $req->fromQuery('page'), 1);
        $perPage  = Validator::requiredInt('per_page', $req->fromQuery('per_page'), 1, 20);

        $result = $this->service->findAll($archived, $courseId, $batch, $query, $page, $perPage);

        HttpResponse::ok($result);
    }

    public function store(Request $req, array $cont): void
    {
        $user            = $cont['user'];
        $profileId       = $user['profile']['id'];
        $schoolId        = $user['profile']['school_id'];
        $courseId        = Validator::requiredInt('course_id', $req->fromBody('course_id'), 1);
        $graduationYear  = Validator::requiredInt('graduation_year', $req->fromBody('graduation_year'), 2007, (int) date('Y'));

        $course = Course::findById($this->pdo, $courseId);

        if ($course === null) {
            HttpResponse::notFound(['message' => "Course with id {$courseId} was not found."]);
        }

        if ($course->schoolId !== $schoolId) {
            HttpResponse::forbidden(['message' => 'You are not allowed to upload records for this course.']);
        }

        $folder = UploadsConfig::folder('graduate_record');

        if ($folder === null) {
            HttpResponse::server(['message' => 'An unknown error occurred.']);
        }

        GraduateRecordValidator::validate();
        
        $uploads = new UploadHandler([
            new Upload('Graduate Record', 'record', $folder, [Mime::CSV]),
        ]);
        $uploads->stage();

        if (!empty($uploads->getErrors())) {
            HttpResponse::unprocessable(['message' => $uploads->getErrors()]);
        }
        
        try {
            $newRecord = $this->service->create([
                'course_id'        => $courseId,
                'dean_uploader_id' => $profileId,
                'graduation_year'  => $graduationYear,
                'filename'         => $uploads->getFilename('record'),
            ]);
            $uploads->commit();
            HttpResponse::ok($newRecord);
        } catch (Exception $e) {
            $uploads->rollback();
            HttpResponse::server(['message' => 'Unable to upload record.']);
        }
    }

    public function restore(Request $req, array $cont): void
    {
        $user     = $cont['user'];
        $schoolId = $user['profile']['school_id'];
        $id       = Validator::requiredInt('id', $req->fromParams('id'), 1);
        $record   = GraduateRecord::findById($this->pdo, $id);

        if ($record === null) {
            HttpResponse::notFound(['message' => 'Record not found.']);
        }

        $course = Course::findById($this->pdo, $record->courseId);

        if ($course->schoolId !== $schoolId) {
            HttpResponse::forbidden(['message' => 'You are not allowed to edit this record.']);
        }

        if (!$record->archived) {
            HttpResponse::conflict(['message' => 'Record is not archived.']);
        }

        $record->archived = false;
        $updatedRecord = $this->service->update($id, $record->toArray());

        if ($updatedRecord === null) {
            HttpResponse::server(['message' => 'Unable to restore record.']);
        }

        HttpResponse::ok($updatedRecord);
    }

    public function archive(Request $req, array $cont): void
    {
        $user     = $cont['user'];
        $schoolId = $user['profile']['school_id'];
        $id       = Validator::requiredInt('id', $req->fromParams('id'), 1);
        $record   = GraduateRecord::findById($this->pdo, $id);

        if ($record === null) {
            HttpResponse::notFound(['message' => 'Record not found.']);
        }

        $course = Course::findById($this->pdo, $record->courseId);

        if ($course->schoolId !== $schoolId) {
            HttpResponse::forbidden(['message' => 'You are not allowed to edit this record.']);
        }

        if ($record->archived) {
            HttpResponse::conflict(['message' => 'Record is already archived.']);
        }

        $record->archived = true;
        $updatedRecord = $this->service->update($id, $record->toArray());

        if ($updatedRecord === null) {
            HttpResponse::server(['message' => 'Unable to archive record.']);
        }

        HttpResponse::ok($updatedRecord);
    }
}