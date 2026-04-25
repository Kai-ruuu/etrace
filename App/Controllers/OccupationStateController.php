<?php

namespace App\Controllers;

use App\Core\HttpResponse;
use App\Core\Request;
use App\Core\Types\EmploymentStatus;
use App\Core\Validator;
use App\Models\OccupationState;
use App\Models\ProfileAlumni;
use App\Services\OccupationStateService;
use App\Services\ProfileAlumniService;
use DateTime;
use Exception;
use PDO;

class OccupationStateController
{
    private PDO $pdo;
    private OccupationStateService $service;
    private ProfileAlumniService $alumniService;
    
    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
        $this->service = new OccupationStateService($this->pdo);
        $this->alumniService = new ProfileAlumniService($this->pdo);
    }

    public function store(Request $req, array $cont): void
    {
        $profileId = $cont['user']['profile']['id'];
        $name = Validator::requiredString('Occupation Name', $req->fromBody('name'), 1, 255);
        $company = Validator::requiredString('Company Name', $req->fromBody('company'), 1, 512);
        $address = Validator::requiredString('Company Address', $req->fromBody('address'), 1, 512);
        $isCurrent = Validator::requiredBool('Is Current Job', $req->fromBody('is_current'));
        $startYear = Validator::requiredInt('Start Year', $req->fromBody('start_year'), 1900, (int) date('Y'));
        $endYear = Validator::int('End Year', $req->fromBody('end_year'), 1900, (int) date('Y'));

        $occupation = OccupationState::findByNameAndAlumniId($this->pdo, $name, $profileId);

        if ($occupation && strtolower($occupation->address) === strtolower($address))
            HttpResponse::conflict(['message' => 'An occupation with the same company and address already exists.']);
        
        $birthYear = (int) (new DateTime($cont['user']['profile']['birth_date']))->format('Y');

        if ($startYear < $birthYear)
            HttpResponse::bad(['message' => 'Invalid start year.']);

        if (!$isCurrent) {    
            if ($endYear < $birthYear)
                HttpResponse::bad(['message' => 'Invalid end year.']);

            if ($startYear > $endYear)
                HttpResponse::bad(['message' => 'Start year must be earlier or equal to end year.']);

            if ($endYear < $startYear)
                HttpResponse::bad(['message' => 'End year must be later or equal to start year.']);
        }

        $newOccuState = $this->service->create([
            'name' => $name,
            'alumni_id' => $profileId,
            'company' => $company,
            'address' => $address,
            'start_year' => $startYear,
            'end_year' => $endYear,
            'is_current' => $isCurrent,
        ]);

        if (!$newOccuState)
            HttpResponse::server(['message' => 'Unable to add occupation due to an error.']);
        
        HttpResponse::ok($newOccuState);
    }

    public function update(Request $req, array $cont): void
    {
        $profileId = $cont['user']['profile']['id'];
        $occId = Validator::requiredInt('Occupation ID', $req->fromParams('id'), 1);
        $name = Validator::requiredString('Occupation Name', $req->fromBody('name'), 1, 255);
        $company = Validator::requiredString('Company Name', $req->fromBody('company'), 1, 512);
        $address = Validator::requiredString('Company Address', $req->fromBody('address'), 1, 512);
        $isCurrent = Validator::requiredBool('Is Current Job', $req->fromBody('is_current'));
        $startYear = Validator::requiredInt('Start Year', $req->fromBody('start_year'), 1900, (int) date('Y'));
        $endYear = Validator::int('End Year', $req->fromBody('end_year'), 1900, (int) date('Y'));

        $occupation = OccupationState::findByNameAndAlumniId($this->pdo, $name, $profileId);

        if (
            $occupation &&
            strtolower($occupation->company) === strtolower($company) &&
            strtolower($occupation->address) === strtolower($address) &&
            (int) $occupation->startYear === (int) $startYear &&
            (int) $occupation->endYear === (int) $endYear
        )
            HttpResponse::conflict(['message' => 'An occupation with the same details already exists.']);
        
        $birthYear = (int) (new DateTime($cont['user']['profile']['birth_date']))->format('Y');

        if ($startYear < $birthYear)
            HttpResponse::bad(['message' => 'Invalid start year.']);

        if (!$isCurrent) {    
            if ($endYear < $birthYear)
                HttpResponse::bad(['message' => 'Invalid end year.']);

            if ($startYear > $endYear)
                HttpResponse::bad(['message' => 'Start year must be earlier or equal to end year.']);

            if ($endYear < $startYear)
                HttpResponse::bad(['message' => 'End year must be later or equal to start year.']);
        }

        $newOccuState = $this->service->update($occId, [
            'name' => $name,
            'alumni_id' => $profileId,
            'company' => $company,
            'address' => $address,
            'start_year' => $startYear,
            'end_year' => $endYear,
            'is_current' => $isCurrent,
        ]);

        if (!$newOccuState)
            HttpResponse::server(['message' => 'Unable to add occupation due to an error.']);

        $profile = ProfileAlumni::findById($this->pdo, $profileId);
        $occStates = OccupationState::findAllByAlumniId($this->pdo, $profileId);
        $isUnemployed = empty(array_filter($occStates, fn($os) => $os->isCurrent));
        $profile->employmentStatus = $isUnemployed ? EmploymentStatus::UNEMPLOYED : EmploymentStatus::EMPLOYED;
            
        $this->alumniService->update($profile->id, $profile->toArray());
        
        HttpResponse::ok($newOccuState);
    }

    public function destroy(Request $req, array $cont): void
    {
        $id = Validator::requiredInt('Id', $req->fromParams('id'), 1);
        $occState = OccupationState::findByIdAndAlumniId($this->pdo, $id, $cont['user']['profile']['id']);

        if (!$occState)
            HttpResponse::notFound(['message' => 'Occupation not found.']);

        try {
            OccupationState::delete($this->pdo, $id);
            HttpResponse::ok(['message' => 'Occupation has been deleted.']);
        } catch (Exception $e) {
            error_log($e->getMessage());
            HttpResponse::server(['message' => 'Unable to delete occupation due to an error.']);
        }
    }
}