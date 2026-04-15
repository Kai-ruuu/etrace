<?php

namespace App\Controllers;

use App\Core\HttpResponse;
use App\Core\Request;
use App\Core\Validator;
use App\Models\School;
use App\Services\SchoolService;
use Exception;
use PDO;

class SchoolController
{
    private PDO $pdo;
    private SchoolService $service;
    
    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
        $this->service = new SchoolService($this->pdo);
    }

    public function showAbsoluteAll(Request $req, array $cont): void
    {
        $result = School::findAllActive($this->pdo);
        HttpResponse::ok(array_map(fn($school) => $school->toArray(), $result));
    }

    public function showAll(Request $req, array $cont): void
    {
        $archived = Validator::bool('archived', $req->fromQuery('archived'));
        $query    = Validator::string('query', $req->fromQuery('query')) ?? '';
        $page     = Validator::requiredInt('page', $req->fromQuery('page'), 1);
        $perPage  = Validator::requiredInt('per_page', $req->fromQuery('per_page'), 1, 20);
        
        $result = $this->service->findAll($archived, $query, $page, $perPage);
        
        HttpResponse::ok($result);
    }

    public function store(Request $req, array $cont): void
    {
        $name = Validator::requiredString('name', $req->fromBody('name'), 1, 255);

        $school = $this->service->findByName($name);

        if ($school !== null) {
            HttpResponse::conflict(['message' => 'School already exists.']);
        }

        try {
            $newSchool = $this->service->create(['name' => $name]);
            HttpResponse::ok($newSchool);
        } catch (Exception $e) {
            HttpResponse::server(['message' => 'Unable to create school.']);
        }   
    }

    public function edit(Request $req, array $cont): void
    {
        $id   = Validator::requiredInt('id', $req->fromParams('id'), 1);
        $name = Validator::requiredString('name', $req->fromBody('name'), 1, 255);
        
        $school = School::findById($this->pdo, $id);

        if ($school === null) {
            HttpResponse::notFound(['message' => 'School not found.']);
        }

        $school->name = $name;
        $editedSchool = $this->service->update($id, $school->toArray());

        if ($editedSchool === null) {
            HttpResponse::server(['message' => 'Unable to edit school.']);
        }

        HttpResponse::ok($editedSchool);
    }

    public function restore(Request $req, array $cont): void
    {
        $id = Validator::requiredInt('id', $req->fromParams('id'), 1);
        $school = School::findById($this->pdo, $id);

        if ($school === null) {
            HttpResponse::notFound(['message' => 'School not found.']);
        }

        if (!$school->archived) {
            HttpResponse::conflict(['message' => 'School is not archived.']);
        }

        $school->archived = false;
        $updatedSchool = $this->service->update($id, $school->toArray());

        if ($updatedSchool === null) {
            HttpResponse::server(['message' => 'Unable to restore school.']);
        }

        HttpResponse::ok($updatedSchool);
    }

    public function archive(Request $req, array $cont): void
    {
        $id = Validator::requiredInt('id', $req->fromParams('id'), 1);
        $school = School::findById($this->pdo, $id);

        if ($school === null) {
            HttpResponse::notFound(['message' => 'School not found.']);
        }

        if ($school->archived) {
            HttpResponse::conflict(['message' => 'School is already archived.']);
        }

        $school->archived = true;
        $updatedSchool = $this->service->update($id, $school->toArray());

        if ($updatedSchool === null) {
            HttpResponse::server(['message' => 'Unable to archive school.']);
        }

        HttpResponse::ok($updatedSchool);
    }
}