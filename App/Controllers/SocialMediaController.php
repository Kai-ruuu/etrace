<?php

namespace App\Controllers;

use App\Core\HttpResponse;
use App\Core\Request;
use App\Core\Validator;
use App\Models\SocialMedia;
use App\Models\SocialMediaPlatform;
use App\Utils\ArrayLogger;
use Exception;
use PDO;

class SocialMediaController
{
    private PDO $pdo;
    
    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    public function store(Request $req, array $cont): void
    {
        $profileId = $cont['user']['profile']['id'];
        $platform  = Validator::requiredString('Platform', $req->fromBody('platform'), 1, 25);
        $url       = Validator::requiredString('URL', $req->fromBody('url'), 8, 512);

        $existingPlatform = SocialMediaPlatform::findByName($this->pdo, $platform);

        if ($existingPlatform)
            $platformId = $existingPlatform->id;
        else
            $existingPlatform = SocialMediaPlatform::create($this->pdo, ['name' => $platform]);
            $platformId = $existingPlatform->id;
        
        if (SocialMedia::findByAlumniIdAndPlatformId($this->pdo, $profileId, $existingPlatform->id))
            HttpResponse::conflict(['message' => 'A social with the same platform already exists.']);

        $newSocial = SocialMedia::create($this->pdo, [
            'alumni_id' => $profileId,
            'platform_id' => $platformId,
            'url' => $url
        ]);

        if (!$newSocial)
            HttpResponse::server(['message' => 'Unable to add social media info due to an error.']);

        $socialArray = $newSocial->toArray();
        $socialArray['platform'] = $existingPlatform->toArray();
        
        HttpResponse::ok($socialArray);
    }

    public function update(Request $req, array $cont): void
    {
        ArrayLogger::log($req->body);

        $profileId = $cont['user']['profile']['id'];
        $socId     = Validator::requiredInt('Social ID', $req->fromParams('id'), 1);
        $platform  = Validator::requiredString('Platform', $req->fromBody('platform'), 1, 25);
        $url       = Validator::requiredString('URL', $req->fromBody('url'), 8, 512);

        $existingSocial = SocialMedia::findById($this->pdo, $socId);

        if (!$existingSocial)
            HttpResponse::notFound(['message' => 'Social info not found.']);

        if ($existingSocial->alumniId !== $profileId)
            HttpResponse::forbidden(['message' => 'You are not allowed to perform this action.']);

        $existingPlatform = SocialMediaPlatform::findByName($this->pdo, $platform);

        if (!$existingPlatform) {
            $existingPlatform = SocialMediaPlatform::create($this->pdo, ['name' => $platform]);
        }

        if (
            SocialMedia::findByAlumniIdAndPlatformId($this->pdo, $profileId, $existingPlatform->id) &&
            strtolower($existingSocial->url) === strtolower($url)
        )
            HttpResponse::conflict(['message' => 'A social with the same platform and url already exists.']);

        $existingSocial->platformId = $existingPlatform->id;
        $existingSocial->url = $url;

        ArrayLogger::log($existingSocial->toArray());

        $updatedSocial = SocialMedia::update($this->pdo, $socId, $existingSocial->toArray());

        if (!$updatedSocial)
            HttpResponse::server(['message' => 'Unable to update social due to an error.']);

        $socialArray = $updatedSocial->toArray();
        $socialArray['platform'] = $existingPlatform->toArray();
        
        HttpResponse::ok($socialArray);
    }

    public function destroy(Request $req, array $cont): void
    {
        $id = Validator::requiredInt('Id', $req->fromParams('id'), 1);
        $social = SocialMedia::findByIdAndAlumniId($this->pdo, $id, $cont['user']['profile']['id']);

        if (!$social)
            HttpResponse::notFound(['message' => 'Social not found.']);

        try {
            SocialMedia::delete($this->pdo, $id);
            HttpResponse::ok(['message' => 'Social has been deleted.']);
        } catch (Exception $e) {
            error_log($e->getMessage());
            HttpResponse::server(['message' => 'Unable to delete social due to an error.']);
        }
    }
}