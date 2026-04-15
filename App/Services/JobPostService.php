<?php

namespace App\Services;

use App\Core\Types\WorkEmploymentType;
use App\Core\Types\WorkSetup;
use App\Core\Types\WorkShift;
use App\Models\Course;
use App\Models\JobPost;
use App\Models\ProfileCompany;
use App\Models\Qualification;
use App\Models\TargetCourse;
use App\Utils\ArrayLogger;
use Exception;
use PDO;
use PDOException;

class JobPostService
{
    private PDO $pdo;
    
    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    private function attachRequired(JobPost $post): array
    {
        $postArray = $post->toArray();
        $company = ProfileCompany::findById($this->pdo, $post->companyId);
        $postArray['company'] = $company ? $company->toArray() : null;
        $postArray['qualifications'] = array_map(fn($q) => $q->toArray() , Qualification::findAllByJobPostId($this->pdo, $post->id));
        $targetCourses = TargetCourse::findAllForPost($this->pdo, $post->id);
        $targetCoursesArray = array_map(function ($tc) {
            $targetCourseArray = $tc->toArray();
            $targetCourseArray['course'] = Course::findById($this->pdo, $tc->courseId)->toArray();
            return $targetCourseArray;
        }, $targetCourses);
        $postArray['target_courses'] = $targetCoursesArray;
        return $postArray;
    }

    private function attachRequiredForCompany(
        JobPost $post,
        int $likes,
        int $submissions,
    ): array
    {
        $withAttachment = $this->attachRequired($post);
        $withAttachment['likes'] = $likes;
        $withAttachment['submissions'] = $submissions;
        return $withAttachment;
    }

    private function attachRequiredForAlumni(
        JobPost $post,
        int $likes,
        bool $isLiked,
        int $submissions,
        bool $isSubmitted
    ): array
    {
        $withAttachment = $this->attachRequired($post);
        $withAttachment['likes'] = $likes;
        $withAttachment['submissions'] = $submissions;
        $withAttachment['is_liked'] = $isLiked;
        $withAttachment['is_submitted'] = $isSubmitted;
        return $withAttachment;
    }

    public function findAllForCompany(
        int $companyId,
        bool $open = true,
        ?WorkEmploymentType $empType = null,
        ?WorkSetup $setup = null,
        ?WorkShift $shift = null,
        string $query = '',
        int $page = 1,
        int $perPage = 20,
    ): array
    {
        $page    = max(1, (int)$page);
        $perPage = min(100, max(1, (int)$perPage));
        $offset  = ($page - 1) * $perPage;
        $search = "%$query%";
        $bindings = [$companyId];
        $bindings = $empType === null ? $bindings : array_merge($bindings, [$empType->value]);
        $bindings = $setup === null ? $bindings : array_merge($bindings, [$setup->value]);
        $bindings = $shift === null ? $bindings : array_merge($bindings, [$shift->value]);
        $bindings = array_merge($bindings, [$search, $search, $search, $search, $search]);

        $filOpen = $open
            ? '(jp.open_until >= CURDATE() AND jp.open = TRUE) AND'
            : '(jp.open_until < CURDATE() OR jp.open = FALSE) AND';

        $condCompanyId = 'jp.company_id = ? AND';
        $condEmpType = $empType === null ? '' : 'jp.work_employment_type = ? AND';
        $condSetup   = $setup === null   ? '' : 'jp.work_setup = ? AND';
        $condShift   = $shift === null   ? '' : 'jp.work_shift = ? AND';

        $sqlTotal = $this->pdo->prepare("
            SELECT COUNT(jp.id)
            FROM job_posts jp
            WHERE
                {$filOpen}
                {$condCompanyId}
                {$condEmpType}
                {$condSetup}
                {$condShift}
                (
                    jp.position LIKE ? OR
                    jp.description LIKE ? OR
                    jp.address LIKE ? OR
                    jp.salary_min LIKE ? OR
                    jp.salary_max LIKE ?
                )
        ");
        
        $sqlTotal->execute($bindings);
        $total = $sqlTotal->fetchColumn();

        $bindings = array_merge($bindings, [$perPage, $offset]);
        
        $sql = $this->pdo->prepare("
            SELECT
                jp.*,
                (
                    SELECT COUNT(*) FROM job_post_likes jpl
                    WHERE jpl.job_post_id = jp.id
                ) AS likes,
                (
                    SELECT COUNT(DISTINCT jps.alumni_id) FROM job_post_cv_submissions jps
                    WHERE jps.job_post_id = jp.id
                ) AS submissions
            FROM job_posts jp
            WHERE
                {$filOpen}
                {$condCompanyId}
                {$condEmpType}
                {$condSetup}
                {$condShift}
                (
                    jp.position LIKE ? OR
                    jp.description LIKE ? OR
                    jp.address LIKE ? OR
                    jp.salary_min LIKE ? OR
                    jp.salary_max LIKE ?
                )
            LIMIT ? OFFSET ?
        ");

        $sql->execute($bindings);

        $results = array_map(fn($row) => $this->attachRequiredForCompany(JobPost::fromRow($row), $row['likes'], $row['submissions']), $sql->fetchAll());
        
        return [
            'results'     => $results,
            'total'       => $total,
            'page'        => $page,
            'per_page'    => $perPage,
            'total_pages' => (int) ceil($total / $perPage),
            'has_next'    => $page < ceil($total / $perPage),
            'has_prev'    => $page > 1,
        ];
    }

    public function findAllForAlumni(
        int $alumniId,
        int $courseId,
        string $query = '',
        int $page = 1,
        int $perPage = 20,
    ): array
    {
        $page    = max(1, (int)$page);
        $perPage = min(100, max(1, (int)$perPage));
        $offset  = ($page - 1) * $perPage;
        $search = "%$query%";
        $bindings = [$courseId, $search, $search, $search, $search, $search];

        $sqlTotal = $this->pdo->prepare("
            SELECT COUNT(jp.id)
            FROM job_posts jp
            JOIN target_courses tc ON tc.job_post_id = jp.id
            WHERE
                (jp.open_until >= CURDATE() AND jp.open = TRUE) AND
                tc.course_id = ? AND
                (
                    jp.position LIKE ? OR
                    jp.description LIKE ? OR
                    jp.address LIKE ? OR
                    jp.salary_min LIKE ? OR
                    jp.salary_max LIKE ?
                )
        ");
        
        $sqlTotal->execute($bindings);
        $total = $sqlTotal->fetchColumn();

        $bindings = array_merge($bindings, [$perPage, $offset]);
        
        $sql = $this->pdo->prepare("
            SELECT
                jp.*,
                (
                    SELECT COUNT(*) FROM job_post_likes jpl
                    WHERE jpl.job_post_id = jp.id
                ) AS likes,
                (
                    SELECT COUNT(DISTINCT jps.alumni_id) FROM job_post_cv_submissions jps
                    WHERE jps.job_post_id = jp.id
                ) AS submissions,
                EXISTS (
                    SELECT 1 FROM job_post_likes jpl
                    WHERE jpl.job_post_id = jp.id AND jpl.alumni_id = {$alumniId}
                ) AS is_liked,
                EXISTS (
                    SELECT 1 FROM job_post_cv_submissions jps
                    WHERE
                        jps.job_post_id = jp.id AND
                        jps.alumni_id = {$alumniId}
                ) AS is_submitted
            FROM job_posts jp
            JOIN target_courses tc ON tc.job_post_id = jp.id
            WHERE
                (jp.open_until >= CURDATE() AND jp.open = TRUE) AND
                tc.course_id = ? AND
                (
                    jp.position LIKE ? OR
                    jp.description LIKE ? OR
                    jp.address LIKE ? OR
                    jp.salary_min LIKE ? OR
                    jp.salary_max LIKE ?
                )
            LIMIT ? OFFSET ?
        ");

        $sql->execute($bindings);

        $results = array_map(
            fn($row) => $this->attachRequiredForAlumni(
                JobPost::fromRow($row),
                $row['likes'],
                $row['is_liked'],
                $row['submissions'],
                $row['is_submitted'],
            ),
            $sql->fetchAll()
        );
        
        return [
            'results'     => $results,
            'total'       => $total,
            'page'        => $page,
            'per_page'    => $perPage,
            'total_pages' => (int) ceil($total / $perPage),
            'has_next'    => $page < ceil($total / $perPage),
            'has_prev'    => $page > 1,
        ];
    }

    public function findById(int $id): ?array
    {
        $instance = JobPost::findById($this->pdo, $id);
        return $instance ? $this->attachRequired($instance) : null;
    }

    public function create(array $data): ?array
    {
        ArrayLogger::log($data);
        
        try {
            $this->pdo->beginTransaction();

            $modelInstance = JobPost::create($this->pdo, $data);
            $qualifications = $data['qualifications'];
            $targetCourses = $data['target_courses'];

            foreach ($qualifications as $qual) {
                Qualification::create($this->pdo, [
                    'job_post_id' => $modelInstance->id,
                    'qualification' => $qual
                ]);
            }

            foreach ($targetCourses as $courseId) {
                TargetCourse::create($this->pdo, [
                    'job_post_id' => $modelInstance->id,
                    'course_id'   => $courseId
                ]);
            }

            $this->pdo->commit();
            return $this->findById($modelInstance->id);
        } catch (PDOException $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }

            error_log($e->getMessage());
            
            return null;
        }
    }

    public function update(int $id, array $data): ?array
    {
        $updatedInstance = JobPost::update($this->pdo, $id, $data);
        return $updatedInstance ? $this->attachRequired($updatedInstance) : null;
    }

    public function deleteById(int $id): bool
    {
        return JobPost::delete($this->pdo, $id);
    }
}