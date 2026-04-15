<?php

namespace App\Controllers;

use App\Core\HttpResponse;
use App\Core\Request;
use App\Models\SocialMediaPlatform;
use PDO;

class SocialMediaPlatformController
{
    private PDO $pdo;
    
    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    public function showAll(Request $req, array $cont): void
    {
        HttpResponse::ok(SocialMediaPlatform::findAll($this->pdo));
    }
}