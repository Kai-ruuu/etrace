<?php

namespace App\Controllers;

use App\Core\HttpResponse;
use App\Core\Request;
use App\Core\Validator;
use App\Services\Service;
use PDO;

class Controller
{
    private PDO $pdo;
    private Service $service;
    
    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
        $this->service = new Service($this->pdo);
    }

    public function show(Request $req, array $cont): void
    {
        HttpResponse::ok(["message" => "showed"]);
    }

    public function showAll(Request $req, array $cont): void
    {
        $query   = Validator::string('query', $req->fromQuery('query')) ?? '';
        $page    = Validator::requiredInt('page', $req->fromQuery('page'), 1);
        $perPage = Validator::requiredInt('per_page', $req->fromQuery('per_page'), 1, 20);

        $result = $this->service->findAll($query, $page, $perPage);

        HttpResponse::ok($result);
    }

    public function store(Request $req, array $cont): void
    {
        HttpResponse::ok(["message" => "stored"]);
    }

    public function update(Request $req, array $cont): void
    {
        HttpResponse::ok(["message" => "updated"]);
    }

    public function destroy(Request $req, array $cont): void
    {
        HttpResponse::ok(["message" => "deleted"]);
    }
}