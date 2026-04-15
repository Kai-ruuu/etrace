<?php

namespace App\Controllers;

use App\Core\HttpResponse;
use App\Core\Request;
use App\Core\Types\WorkEmploymentType;
use App\Core\Types\WorkSetup;
use App\Core\Types\WorkShift;
use App\Core\Validator;
use App\Models\JobPost;
use App\Services\JobPostService;
use DateTime;
use Exception;
use PDO;

class JobPostController
{
    private PDO $pdo;
    private JobPostService $service;
    
    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
        $this->service = new JobPostService($this->pdo);
    }

    public function show(Request $req, array $cont): void
    {
        $id = Validator::requiredInt('Id', $req->fromParams('id'), 1);
        $jobPost = $this->service->findById($id);

        if (!$jobPost)
            HttpResponse::notFound(['message' => 'Job post not found.']);
        
        HttpResponse::ok($jobPost);
    }

    public function showAllForCompany(Request $req, array $cont): void
    {
        $profileId = $cont['user']['profile']['id'];
        $open      = Validator::requiredBool('Open', $req->fromQuery('open'));
        $empType   = Validator::enum('Employment Type', $req->fromQuery('emp_type'), WorkEmploymentType::class);
        $setup     = Validator::enum('Work Setup', $req->fromQuery('setup'), WorkSetup::class);
        $shift     = Validator::enum('Work Shift', $req->fromQuery('shift'), WorkShift::class);
        $query     = Validator::string('query', $req->fromQuery('query')) ?? '';
        $page      = Validator::requiredInt('page', $req->fromQuery('page'), 1);
        $perPage   = Validator::requiredInt('per_page', $req->fromQuery('per_page'), 1, 20);

        $result = $this->service->findAllForCompany($profileId, $open, $empType, $setup, $shift, $query, $page, $perPage);
        HttpResponse::ok($result);
    }

    public function showAllForAlumni(Request $req, array $cont): void
    {
        $profile = $cont['user']['profile'];
        $alumniId = $profile['id'];
        $courseId = $profile['course_id'];
        $query     = Validator::string('query', $req->fromQuery('query')) ?? '';
        $page      = Validator::requiredInt('page', $req->fromQuery('page'), 1);
        $perPage   = Validator::requiredInt('per_page', $req->fromQuery('per_page'), 1, 20);

        $result = $this->service->findAllForAlumni($alumniId, $courseId, $query, $page, $perPage);
        HttpResponse::ok($result);
    }

    public function close(Request $req, array $cont): void
    {
        $profileId = $cont['user']['profile']['id'];
        $jobPostId = Validator::requiredInt('Job Post ID', $req->fromParams('id'), 1);

        $jobPost = JobPost::findById($this->pdo, $jobPostId);
        
        if (!$jobPost)
            HttpResponse::notFound(['message' => 'Job post not found.']);
        
        if ($profileId !== $jobPost->companyId)
            HttpResponse::forbidden(['message' => 'You are not allowed to perform this action.']);
        
        if (!$jobPost->open)
            HttpResponse::conflict(['message' => 'Job post is already closed.']);

        $jobPost->open = false;
        $updatedJobPost = JobPost::update($this->pdo, $jobPostId, $jobPost->toArray());

        if (!$updatedJobPost)
            HttpResponse::server(['message' => 'Unable to close post due to an error.']);

        HttpResponse::ok($this->service->findById($jobPostId));
    }

    public function repost(Request $req, array $cont): void
    {
        $profileId = $cont['user']['profile']['id'];
        $jobPostId = Validator::requiredInt('Job Post ID', $req->fromParams('id'), 1);

        $jobPost = JobPost::findById($this->pdo, $jobPostId);
        
        if (!$jobPost)
            HttpResponse::notFound(['message' => 'Job post not found.']);
        
        if ($profileId !== $jobPost->companyId)
            HttpResponse::forbidden(['message' => 'You are not allowed to perform this action.']);
        
        if ($jobPost->open)
            HttpResponse::conflict(['message' => 'Job post is already opened.']);

        try {
            $openUntil = new DateTime($req->fromBody('open_until'));
        } catch (Exception $e) {
            HttpResponse::unprocessable(['message' => 'Invalid open until date format.']);
        }

        $dateNow = new DateTime();

        if ($openUntil <= $dateNow)
            HttpResponse::bad(['message' => 'Open until date should not be in the past or today.']);

        $jobPost->open = true;
        $jobPost->openUntil = $openUntil;
        $updatedJobPost = JobPost::update($this->pdo, $jobPostId, $jobPost->toArray());

        if (!$updatedJobPost)
            HttpResponse::server(['message' => 'Unable to repost job due to an error.']);

        HttpResponse::ok($this->service->findById($jobPostId));
    }
    
    public function store(Request $req, array $cont): void
    {
        $profileId = $cont['user']['profile']['id'];
        $position           = Validator::requiredString('Position', $req->fromBody('position'), 1, 255);
        $description        = Validator::requiredString('Description', $req->fromBody('description'), 1, 3000);
        $address            = Validator::requiredString('Address', $req->fromBody('address'), 1, 512);
        $salaryMin          = Validator::requiredInt('Minimum Salary', $req->fromBody('salary_min'), 1);
        $salaryMax          = Validator::requiredInt('Maximum Salary', $req->fromBody('salary_max'), 1);
        $workShift          = Validator::requiredEnum('Work Shift', $req->fromBody('work_shift'), WorkShift::class);
        $workSetup          = Validator::requiredEnum('Work Setup', $req->fromBody('work_setup'), WorkSetup::class);
        $workEmploymentType = Validator::requiredEnum('Work Employment Type', $req->fromBody('work_employment_type'), WorkEmploymentType::class);
        $slots              = Validator::requiredInt('Slots', $req->fromBody('slots'), 1);
        $additionalInfo     = Validator::string('Additional Info', $req->fromBody('additional_info'), 1, 3000) ?? null;
        $qualifications     = Validator::requiredJson('Qualifications', $req->fromBody('qualifications'));
        $targetCourses      = Validator::requiredJson('Target courses', $req->fromBody('target_courses'));

        if (JobPost::findByPositionAndCompanyId($this->pdo, $position, $profileId))
            HttpResponse::conflict(['message' => 'You have already created a job post with the same position before, please edit it instead.']);

        if ($salaryMin > $salaryMax)
            HttpResponse::bad(['message' => 'Minimum salary should not be greater than the maximum salary.']);

        if ($salaryMax < $salaryMin)
            HttpResponse::bad(['message' => 'Maximum salary should not be less than the minimum salary.']);

        try {
            $openUntil = new DateTime($req->fromBody('open_until'));
        } catch (Exception $e) {
            HttpResponse::unprocessable(['message' => 'Invalid open until date format.']);
        }

        $dateNow = new DateTime();

        if ($openUntil <= $dateNow)
            HttpResponse::bad(['message' => 'Open until date should not be in the past or today.']);
    
        $newJobPost = $this->service->create([
            'company_id'           => $profileId,
            'position'             => $position,
            'description'          => $description,
            'address'              => $address,
            'salary_min'           => $salaryMin,
            'salary_max'           => $salaryMax,
            'work_shift'           => $workShift->value,
            'work_setup'           => $workSetup->value,
            'work_employment_type' => $workEmploymentType->value,
            'slots'                => $slots,
            'additional_info'      => $additionalInfo,
            'open_until'           => $openUntil->format('Y-m-d'),
            'qualifications'       => $qualifications,
            'target_courses'       => $targetCourses
        ]);

        if (!$newJobPost)
            HttpResponse::server(['message' => 'Unable to create job post due to an error.']);
        
        HttpResponse::ok($newJobPost);
    }

    public function update(Request $req, array $cont): void
    {
        $profileId = $cont['user']['profile']['id'];
        $jobPostId          = Validator::requiredInt('Job Post ID', $req->fromParams('id'), 1);
        $position           = Validator::requiredString('Position', $req->fromBody('position'), 1, 255);
        $description        = Validator::requiredString('Description', $req->fromBody('description'), 1, 3000);
        $address            = Validator::requiredString('Address', $req->fromBody('address'), 1, 512);
        $salaryMin          = Validator::requiredInt('Minimum Salary', $req->fromBody('salary_min'), 1);
        $salaryMax          = Validator::requiredInt('Maximum Salary', $req->fromBody('salary_max'), 1);
        $workShift          = Validator::requiredEnum('Work Shift', $req->fromBody('work_shift'), WorkShift::class);
        $workSetup          = Validator::requiredEnum('Work Setup', $req->fromBody('work_setup'), WorkSetup::class);
        $workEmploymentType = Validator::requiredEnum('Work Employment Type', $req->fromBody('work_employment_type'), WorkEmploymentType::class);
        $slots              = Validator::requiredInt('Slots', $req->fromBody('slots'), 1);
        $additionalInfo     = Validator::string('Additional Info', $req->fromBody('additional_info'), 1, 3000);
        
        $jobPost = JobPost::findById($this->pdo, $jobPostId);
        
        if (!$jobPost)
            HttpResponse::notFound(['message' => 'Job post not found.']);
        
        if ($profileId !== $jobPost->companyId)
            HttpResponse::forbidden(['message' => 'You are not allowed to perform this action.']);
        
        if ($salaryMin > $salaryMax)
            HttpResponse::bad(['message' => 'Minimum salary should not be greater than the maximum salary.']);

        if ($salaryMax < $salaryMin)
            HttpResponse::bad(['message' => 'Maximum salary should not be less than the minimum salary.']);

        try {
            $openUntil = new DateTime($req->fromBody('open_until'));
        } catch (Exception $e) {
            HttpResponse::unprocessable(['message' => 'Invalid open until date format.']);
        }

        $updatedJobPost = $this->service->update($jobPostId, [
            'position' => $position,
            'description' => $description,
            'address' => $address,
            'salary_min' => $salaryMin,
            'salary_max' => $salaryMax,
            'work_shift' => $workShift->value,
            'work_setup' => $workSetup->value,
            'work_employment_type' => $workEmploymentType->value,
            'slots' => $slots,
            'additional_info' => $additionalInfo,
            'open' => $jobPost->open
        ]);

        if (!$updatedJobPost)
            HttpResponse::server(['message' => 'Unable to update job post due to an error.']);
        
        HttpResponse::ok($updatedJobPost);
    }

    public function destroy(Request $req, array $cont): void
    {
        $profileId = $cont['user']['profile']['id'];
        $jobPostId = Validator::requiredInt('Job Post ID', $req->fromParams('id'), 1);

        $jobPost = JobPost::findById($this->pdo, $jobPostId);
        
        if (!$jobPost)
            HttpResponse::notFound(['message' => 'Job post not found.']);
        
        if ($profileId !== $jobPost->companyId)
            HttpResponse::forbidden(['message' => 'You are not allowed to perform this action.']);
        
        $jobPostDeleted = $this->service->deleteById($jobPostId);
        
        if (!$jobPostDeleted)
            HttpResponse::server(['message' => 'Unable to delete job post due to an error.']);
        
        HttpResponse::ok(["message" => "Job post has been deleted."]);
    }
}