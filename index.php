<?php

require_once __DIR__ . "/vendor/autoload.php";

$dotenv = Dotenv\Dotenv::createImmutable(__DIR__);
$dotenv->load();

use App\Controllers\AlumniController;
use App\Controllers\AuthController;
use App\Controllers\CompanyController;
use App\Controllers\CourseController;
use App\Controllers\CourseOccupationController;
use App\Controllers\DeanController;
use App\Controllers\GraduateRecordController;
use App\Controllers\JobPostController;
use App\Controllers\JobPostCvSubmissionController;
use App\Controllers\JobPostLikeController;
use App\Controllers\OccupationController;
use App\Controllers\PesoStaffController;
use App\Controllers\QualificationController;
use App\Controllers\SchoolController;
use App\Controllers\SocialMediaPlatformController;
use App\Controllers\SystemAdminController;
use App\Controllers\TargetCourseController;
use App\Controllers\VerificationController;
use App\Core\App;
use App\Core\Database;
use App\Core\Router;
use App\Core\Types\Role;
use App\Middlewares\AllowedOnlyMiddleware;

$allowedOrigins = [
    "http://localhost:5173",
];
$database = Database::forDev();
$pdo = $database->connect();
$router = new Router($pdo);

// auth
$router->get('/api/auth', AuthController::class, 'logout', [AllowedOnlyMiddleware::make([Role::SYSTEM_ADMIN, Role::DEAN, Role::PESO_STAFF, Role::COMPANY, Role::ALUMNI])]);
$router->post('/api/auth', AuthController::class, 'authenticate');
$router->get('/api/auth/me', AuthController::class, 'me', [AllowedOnlyMiddleware::make([Role::SYSTEM_ADMIN, Role::DEAN, Role::PESO_STAFF, Role::COMPANY, Role::ALUMNI])]);

// email verification
$router->get('/api/email-verification/{token}', VerificationController::class, 'verify');

// registration
$router->post('/api/auth/register/company', CompanyController::class, 'store');
$router->post('/api/auth/register/alumni', AlumniController::class, 'store');

// system admin
$router->get('/api/user/system-admin', SystemAdminController::class, 'showAll', [AllowedOnlyMiddleware::make([Role::SYSTEM_ADMIN])]);
$router->post('/api/user/system-admin', SystemAdminController::class, 'store', [AllowedOnlyMiddleware::make([Role::SYSTEM_ADMIN])]);
$router->patch('/api/user/system-admin/enable/{id}', SystemAdminController::class, 'enable', [AllowedOnlyMiddleware::make([Role::SYSTEM_ADMIN])]);
$router->patch('/api/user/system-admin/disable/{id}', SystemAdminController::class, 'disable', [AllowedOnlyMiddleware::make([Role::SYSTEM_ADMIN])]);
$router->patch('/api/user/system-admin/agree-to-consent', SystemAdminController::class, 'agreeToConsent', [AllowedOnlyMiddleware::make([Role::SYSTEM_ADMIN])]);

// dean
$router->get('/api/user/dean', DeanController::class, 'showAll', [AllowedOnlyMiddleware::make([Role::SYSTEM_ADMIN])]);
$router->post('/api/user/dean', DeanController::class, 'store', [AllowedOnlyMiddleware::make([Role::SYSTEM_ADMIN])]);
$router->patch('/api/user/dean/enable/{id}', DeanController::class, 'enable', [AllowedOnlyMiddleware::make([Role::SYSTEM_ADMIN])]);
$router->patch('/api/user/dean/disable/{id}', DeanController::class, 'disable', [AllowedOnlyMiddleware::make([Role::SYSTEM_ADMIN])]);
$router->patch('/api/user/dean/agree-to-consent', DeanController::class, 'agreeToConsent', [AllowedOnlyMiddleware::make([Role::DEAN])]);

// peso staff
$router->get('/api/user/peso-staff', PesoStaffController::class, 'showAll', [AllowedOnlyMiddleware::make([Role::SYSTEM_ADMIN])]);
$router->post('/api/user/peso-staff', PesoStaffController::class, 'store', [AllowedOnlyMiddleware::make([Role::SYSTEM_ADMIN])]);
$router->patch('/api/user/peso-staff/enable/{id}', PesoStaffController::class, 'enable', [AllowedOnlyMiddleware::make([Role::SYSTEM_ADMIN])]);
$router->patch('/api/user/peso-staff/disable/{id}', PesoStaffController::class, 'disable', [AllowedOnlyMiddleware::make([Role::SYSTEM_ADMIN])]);
$router->patch('/api/user/peso-staff/agree-to-consent', PesoStaffController::class, 'agreeToConsent', [AllowedOnlyMiddleware::make([Role::PESO_STAFF])]);

// company
$router->get('/api/user/company', CompanyController::class, 'showAll', [AllowedOnlyMiddleware::make([Role::SYSTEM_ADMIN, Role::PESO_STAFF])]);
$router->get('/api/user/company/revision-requests', CompanyController::class, 'revisionRequests', [AllowedOnlyMiddleware::make([Role::COMPANY])]);
$router->get('/api/user/company/{id}', CompanyController::class, 'show', [AllowedOnlyMiddleware::make([Role::SYSTEM_ADMIN, Role::PESO_STAFF])]);
$router->get('/api/user/company/revision-info/{id}/{requirement_key}', CompanyController::class, 'revisionInfo', [AllowedOnlyMiddleware::make([Role::PESO_STAFF])]);
$router->post('/api/user/company', CompanyController::class, 'store');
$router->post('/api/user/company/write-appeal-sysad', CompanyController::class, 'writeSysadRejectionAppeal', [AllowedOnlyMiddleware::make([Role::COMPANY])]);
$router->post('/api/user/company/write-appeal-pstaff', CompanyController::class, 'writePstaffRejectionAppeal', [AllowedOnlyMiddleware::make([Role::COMPANY])]);
$router->post('/api/user/company/write-appeal-revision/{id}', CompanyController::class, 'writePstaffRevisionAppeal', [AllowedOnlyMiddleware::make([Role::COMPANY])]);
$router->patch('/api/user/company/approve/{id}/{requirement_key}', CompanyController::class, 'approveRequirement', [AllowedOnlyMiddleware::make([Role::PESO_STAFF])]);
$router->patch('/api/user/company/request-revise/{id}/{requirement_key}', CompanyController::class, 'requestReviseRequirement', [AllowedOnlyMiddleware::make([Role::PESO_STAFF])]);
$router->patch('/api/user/company/enable/{id}', CompanyController::class, 'enable', [AllowedOnlyMiddleware::make([Role::SYSTEM_ADMIN, Role::PESO_STAFF])]);
$router->patch('/api/user/company/disable/{id}', CompanyController::class, 'disable', [AllowedOnlyMiddleware::make([Role::SYSTEM_ADMIN, Role::PESO_STAFF])]);
$router->patch('/api/user/company/verify/{id}', CompanyController::class, 'verify', [AllowedOnlyMiddleware::make([Role::SYSTEM_ADMIN, Role::PESO_STAFF])]);
$router->patch('/api/user/company/reject/{id}', CompanyController::class, 'reject', [AllowedOnlyMiddleware::make([Role::SYSTEM_ADMIN, Role::PESO_STAFF])]);
$router->patch('/api/user/company/pend/{id}', CompanyController::class, 'pend', [AllowedOnlyMiddleware::make([Role::SYSTEM_ADMIN, Role::PESO_STAFF])]);

// job post
$router->get('/api/job-post/for-company', JobPostController::class, 'showAllForCompany', [AllowedOnlyMiddleware::make([Role::COMPANY])]);
$router->get('/api/job-post/for-alumni', JobPostController::class, 'showAllForAlumni', [AllowedOnlyMiddleware::make([Role::ALUMNI])]);
$router->post('/api/job-post', JobPostController::class, 'store', [AllowedOnlyMiddleware::make([Role::COMPANY])]);
$router->patch('/api/job-post/{id}', JobPostController::class, 'update', [AllowedOnlyMiddleware::make([Role::COMPANY])]);
$router->patch('/api/job-post/close/{id}', JobPostController::class, 'close', [AllowedOnlyMiddleware::make([Role::COMPANY])]);
$router->patch('/api/job-post/repost/{id}', JobPostController::class, 'repost', [AllowedOnlyMiddleware::make([Role::COMPANY])]);
$router->delete('/api/job-post/{id}', JobPostController::class, 'destroy', [AllowedOnlyMiddleware::make([Role::COMPANY])]);

// job post like
$router->get('/api/job-post-like', JobPostLikeController::class, 'showAll', [AllowedOnlyMiddleware::make([Role::ALUMNI])]);
$router->post('/api/job-post-like/{id}', JobPostLikeController::class, 'store', [AllowedOnlyMiddleware::make([Role::ALUMNI])]);
$router->delete('/api/job-post-like/{id}', JobPostLikeController::class, 'destroy', [AllowedOnlyMiddleware::make([Role::ALUMNI])]);

// job post cv submission
$router->get('/api/job-post-submission/for-alumni', JobPostCvSubmissionController::class, 'showAllForAlumni', [AllowedOnlyMiddleware::make([Role::ALUMNI])]);
$router->get('/api/job-post-submission/for-company/{id}', JobPostCvSubmissionController::class, 'showAllForCompany', [AllowedOnlyMiddleware::make([Role::COMPANY])]);
$router->post('/api/job-post-submission/{id}', JobPostCvSubmissionController::class, 'store', [AllowedOnlyMiddleware::make([Role::ALUMNI])]);
$router->patch('/api/job-post-submission/{id}', JobPostCvSubmissionController::class, 'markAsReviewed', [AllowedOnlyMiddleware::make([Role::COMPANY])]);
$router->delete('/api/job-post-submission/{id}', JobPostCvSubmissionController::class, 'destroy', [AllowedOnlyMiddleware::make([Role::ALUMNI])]);

// qualification
$router->post('/api/qualification/{id}', QualificationController::class, 'store', [AllowedOnlyMiddleware::make([Role::COMPANY])]);
$router->delete('/api/qualification/{id}', QualificationController::class, 'destroy', [AllowedOnlyMiddleware::make([Role::COMPANY])]);

// target course
$router->post('/api/target-course/{job_post_id}/{course_id}', TargetCourseController::class, 'store', [AllowedOnlyMiddleware::make([Role::COMPANY])]);
$router->delete('/api/target-course/{id}', TargetCourseController::class, 'destroy', [AllowedOnlyMiddleware::make([Role::COMPANY])]);

// alumni
$router->get('/api/user/alumni', AlumniController::class, 'showAll', [AllowedOnlyMiddleware::make([Role::DEAN, Role::COMPANY])]);
$router->get('/api/user/alumni/{id}', AlumniController::class, 'show', [AllowedOnlyMiddleware::make([Role::DEAN, Role::COMPANY])]);
$router->post('/api/user/company/write-appeal', AlumniController::class, 'writeRejectionAppeal', [AllowedOnlyMiddleware::make([Role::ALUMNI])]);
$router->patch('/api/user/alumni/enable/{id}', AlumniController::class, 'enable', [AllowedOnlyMiddleware::make([Role::DEAN])]);
$router->patch('/api/user/alumni/disable/{id}', AlumniController::class, 'disable', [AllowedOnlyMiddleware::make([Role::DEAN])]);
$router->patch('/api/user/alumni/verify/{id}', AlumniController::class, 'verify', [AllowedOnlyMiddleware::make([Role::DEAN])]);
$router->patch('/api/user/alumni/reject/{id}', AlumniController::class, 'reject', [AllowedOnlyMiddleware::make([Role::DEAN])]);
$router->patch('/api/user/alumni/pend/{id}', AlumniController::class, 'pend', [AllowedOnlyMiddleware::make([Role::DEAN])]);

// school
$router->get('/api/institution/school', SchoolController::class, 'showAll', [AllowedOnlyMiddleware::make([Role::SYSTEM_ADMIN])]);
$router->get('/api/institution/school/all', SchoolController::class, 'showAbsoluteAll', [AllowedOnlyMiddleware::make([Role::SYSTEM_ADMIN])]);
$router->post('/api/institution/school', SchoolController::class, 'store', [AllowedOnlyMiddleware::make([Role::SYSTEM_ADMIN])]);
$router->patch('/api/institution/school/edit/{id}', SchoolController::class, 'edit', [AllowedOnlyMiddleware::make([Role::SYSTEM_ADMIN])]);
$router->patch('/api/institution/school/restore/{id}', SchoolController::class, 'restore', [AllowedOnlyMiddleware::make([Role::SYSTEM_ADMIN])]);
$router->patch('/api/institution/school/archive/{id}', SchoolController::class, 'archive', [AllowedOnlyMiddleware::make([Role::SYSTEM_ADMIN])]);

// course
$router->get('/api/institution/course', CourseController::class, 'showAll', [AllowedOnlyMiddleware::make([Role::DEAN])]);
$router->get('/api/institution/course/all', CourseController::class, 'showAbsoluteAll');
$router->get('/api/institution/course/all-for-dean', CourseController::class, 'showAllForDean', [AllowedOnlyMiddleware::make([Role::DEAN])]);
$router->post('/api/institution/course', CourseController::class, 'store', [AllowedOnlyMiddleware::make([Role::DEAN])]);
$router->patch('/api/institution/course/edit/{id}', CourseController::class, 'edit', [AllowedOnlyMiddleware::make([Role::DEAN])]);
$router->patch('/api/institution/course/restore/{id}', CourseController::class, 'restore', [AllowedOnlyMiddleware::make([Role::DEAN])]);
$router->patch('/api/institution/course/archive/{id}', CourseController::class, 'archive', [AllowedOnlyMiddleware::make([Role::DEAN])]);

// graduate record
$router->get('/api/institution/graduate-record', GraduateRecordController::class, 'showAll', [AllowedOnlyMiddleware::make([Role::DEAN])]);
$router->get('/api/institution/graduate-record/{id}', GraduateRecordController::class, 'show', [AllowedOnlyMiddleware::make([Role::DEAN])]);
$router->get('/api/institution/graduate-record/{course_id}/{batch}', GraduateRecordController::class, 'showByAlumniInfo', [AllowedOnlyMiddleware::make([Role::DEAN])]);
$router->post('/api/institution/graduate-record', GraduateRecordController::class, 'store', [AllowedOnlyMiddleware::make([Role::DEAN])]);
$router->patch('/api/institution/graduate-record/restore/{id}', GraduateRecordController::class, 'restore', [AllowedOnlyMiddleware::make([Role::DEAN])]);
$router->patch('/api/institution/graduate-record/archive/{id}', GraduateRecordController::class, 'archive', [AllowedOnlyMiddleware::make([Role::DEAN])]);

// occupation
$router->get('/api/institution/occupation', OccupationController::class, 'showAll', [AllowedOnlyMiddleware::make([Role::DEAN])]);
// also make one for alumni access (without middleware)

// course occupation
$router->post('/api/institution/course-occupation', CourseOccupationController::class, 'store', [AllowedOnlyMiddleware::make([Role::DEAN])]);
$router->delete('/api/institution/course-occupation', CourseOccupationController::class, 'destroy', [AllowedOnlyMiddleware::make([Role::DEAN])]);

// social media
$router->get('/api/social-media/platforms', SocialMediaPlatformController::class, 'showAll');

$app = new App($router, $allowedOrigins);
$app->run();