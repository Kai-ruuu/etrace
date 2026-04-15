<?php

namespace App\Controllers;

use App\Core\HttpResponse;
use App\Core\Request;
use App\Core\Validator;
use App\Services\OccupationService;
use App\Utils\ArrayLogger;
use PDO;

class OccupationController
{
    private PDO $pdo;
    private OccupationService $service;
    
    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
        $this->service = new OccupationService($this->pdo);
    }

    public function showAll(Request $req, array $cont): void
    {
        $user = $cont['user'];
        $schoolId = $user['profile']['school_id'];
        $aligned   = Validator::requiredBool('aligned', $req->fromQuery('aligned'));
        $courseId  = Validator::requiredInt('course_id', $req->fromQuery('course_id'));
        $query     = Validator::string('query', $req->fromQuery('query')) ?? '';
        $page      = Validator::requiredInt('page', $req->fromQuery('page'), 1);
        $perPage   = Validator::requiredInt('per_page', $req->fromQuery('per_page'), 1, 20);

        if ($aligned) {
            HttpResponse::ok($this->service->findAllAligned($schoolId, $courseId, $query, $page, $perPage));
        } else {
            HttpResponse::ok($this->service->findAllNotAligned($schoolId, $courseId, $query, $page, $perPage));
        }
    }
}