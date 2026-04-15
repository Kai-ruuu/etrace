<?php

namespace App\Services;

use App\Core\Types\VerificationStatus;
use App\Models\Course;
use App\Models\Occupation;
use App\Models\OccupationState;
use App\Models\ProfileAlumni;
use App\Models\ProfileDean;
use App\Models\RejectionMessageDean;
use App\Models\RejectionMessageDeanAppeal;
use App\Models\SocialMedia;
use App\Models\SocialMediaPlatform;
use App\Models\User;
use Exception;
use PDO;
use PDOException;

class ProfileAlumniService
{
    private PDO $pdo;
    
    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    private function attachRequired(ProfileAlumni $profile): ?array
    {
        $user = User::findById($this->pdo, $profile->userId);
        
        if (!$user) return null;

        $result = $user->toArray();
        $result["profile"] = $profile->toArray();
        $socialMedias = SocialMedia::findAllByAlumniId($this->pdo, $profile->id);
        $socialMedias = array_map(fn($s) => array_merge(
            $s->toArray(),
            ['platform' => SocialMediaPlatform::findById($this->pdo, $s->platformId)?->toArray()]
        ), $socialMedias);
        $result['profile']['social_medias'] = $socialMedias;
        $result['profile']['course'] = Course::findById($this->pdo, $profile->courseId)->toArray();
        $result['profile']['occupations'] = array_map(function ($os) {
            $occupationStateArray = $os->toArray();
            $occupationStateArray['occupation'] = Occupation::findById($this->pdo, $os->occupationId)->toArray();
            return $occupationStateArray;
        }, OccupationState::findAllByAlumniId($this->pdo, $profile->id));

        $rejectionDean = RejectionMessageDean::findByAlumniId($this->pdo, $profile->id);
        $result['profile']['rejection_dean'] = $rejectionDean ? $rejectionDean->toArray() : null;

        if ($rejectionDean) {
            $rejectorDean = ProfileDean::findById($this->pdo, $rejectionDean->deanId);
            $result['profile']['rejection_dean']['rejected_by'] = $rejectorDean->toArray();
            
            $rejectionDeanAppeal = RejectionMessageDeanAppeal::findByMessageId($this->pdo, $rejectionDean->id);
            $result['profile']["rejection_dean"]['appeal'] = $rejectionDeanAppeal ? $rejectionDeanAppeal->toArray() : null;
        }
        
        return $result;
    }

    public function findAll(
        ?bool $enabled = null,
        ?VerificationStatus $status = null,
        ?int $courseId = null,
        ?int $batch = null,
        string $query = '',
        int $page = 1,
        int $perPage = 20,
    ): array
    {
        $page    = max(1, (int)$page);
        $perPage = min(100, max(1, (int)$perPage));
        $offset  = ($page - 1) * $perPage;
        $search = "%$query%";
        $bindings = [];

        $bindings = $enabled === null ? $bindings : array_merge($bindings, [$enabled]);
        $bindings = $status === null ? $bindings : array_merge($bindings, [$status->value]);
        $bindings = $courseId === null ? $bindings : array_merge($bindings, [$courseId]);
        $bindings = $batch === null ? $bindings : array_merge($bindings, [$batch]);

        $condEnabled = $enabled === null ? '' : 'u.enabled = ? AND';
        $condStatus  = $status === null ? '' : 'a.ver_stat_dean = ? AND';
        $condCourse  = $courseId === null ? '' : 'a.course_id = ? AND';
        $condBatch   = $batch === null ? '' : 'a.graduation_year = ? AND';
        
        $bindings = array_merge($bindings, [
            $search, $search, $search,
            $search, $search, $search,
            $search, $search, $search
        ]);

        $sqlTotal = $this->pdo->prepare("
            SELECT COUNT(a.id)
            FROM alumni a
            JOIN users u ON u.id = a.user_id
            WHERE
                {$condEnabled}
                {$condStatus}
                {$condCourse}
                {$condBatch}
                (
                    u.email LIKE ? OR
                    a.name_extension LIKE ? OR
                    a.first_name LIKE ? OR
                    a.last_name LIKE ? OR
                    a.birth_date LIKE ? OR
                    a.birth_place LIKE ? OR
                    a.student_number LIKE ? OR
                    a.phone_number LIKE ? OR
                    a.address LIKE ?
                )
        ");
        
        $sqlTotal->execute($bindings);
        $total = $sqlTotal->fetchColumn();

        $bindings = array_merge($bindings, [$perPage, $offset]);
        
        $sql = $this->pdo->prepare("
            SELECT a.*
            FROM alumni a
            JOIN users u ON u.id = a.user_id
            WHERE
                {$condEnabled}
                {$condStatus}
                {$condCourse}
                {$condBatch}
                (
                    u.email LIKE ? OR
                    a.name_extension LIKE ? OR
                    a.first_name LIKE ? OR
                    a.last_name LIKE ? OR
                    a.birth_date LIKE ? OR
                    a.birth_place LIKE ? OR
                    a.student_number LIKE ? OR
                    a.phone_number LIKE ? OR
                    a.address LIKE ?
                )
            LIMIT ? OFFSET ?
        ");

        $sql->execute($bindings);

        $results = array_map(fn($row) => $this->attachRequired(ProfileAlumni::fromRow($row)), $sql->fetchAll());
        
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
        $profile = ProfileAlumni::findById($this->pdo, $id);
        return $profile ? $this->attachRequired($profile) : null;
    }

    public function create(array $data): ?array
    {
        try {
            $this->pdo->beginTransaction();

            // create user
            $user = User::create($this->pdo, $data);
            $data['user_id'] = $user->id;
            
            // create profile
            $profile = ProfileAlumni::create($this->pdo, $data);
            
            // create social media accounts
            $socials = $data['social_medias'];
            /**
             * [
             *      ['platform' => 'platform1', 'url' => 'https://...']
             *      ['platform' => 'platform2', 'url' => 'https://...']
             * ]
             */
            $existingPlatforms = [];

            foreach ($socials as $social) {
                $socialPlatformId = null;
                $socialPlatform = $social['platform'];
                $socialUrl = $social['url'];
                
                if (!array_key_exists(strtolower($socialPlatform), $existingPlatforms)) {
                    $existingPlatform = SocialMediaPlatform::findByName($this->pdo, $socialPlatform);

                    if (!$existingPlatform) {
                        $existingPlatform = SocialMediaPlatform::create($this->pdo, ['name' => $socialPlatform]);
                    }

                    $existingPlatforms[strtolower($existingPlatform->name)] = $existingPlatform->id;
                    $socialPlatformId = $existingPlatform->id;
                } else {
                    $socialPlatformId = $existingPlatforms[strtolower($socialPlatform)];
                }
                
                SocialMedia::create($this->pdo, [
                    'alumni_id' => $profile->id,
                    'platform_id' => $socialPlatformId,
                    'url' => $socialUrl
                ]);
            }

            // create occupation states
            $occupations = $data['occupations'];
            /**
             * [
             *      ['name' => 'OccName1', 'company' => 'company1', 'address' => 'companyAddr1', 'start_year' => 2001, 'end_year' => null, 'is_current' => true]
             *      ['name' => 'OccName2', 'company' => 'company2', 'address' => 'companyAddr2', 'start_year' => 2002, 'end_year' => 2003, 'is_current' => false]
             * ]
             */
            $existingOccupations = [];

            foreach ($occupations as $occupation) {
                $occupationId = null;
                $occName = $occupation['name'];
                
                if (!array_key_exists(strtolower($occName), $existingOccupations)) {
                    $existingOccupation = Occupation::findByName($this->pdo, $occName);

                    if (!$existingOccupation) {
                        $existingOccupation = Occupation::create($this->pdo, ['name' => $occName]);
                    }

                    $existingOccupations[strtolower($existingOccupation->name)] = $existingOccupation->id;
                    $occupationId = $existingOccupation->id;
                } else {
                    $occupationId = $existingOccupations[strtolower($occupation)];
                }

                OccupationState::create($this->pdo, [
                    'alumni_id' => $profile->id,
                    'occupation_id' => $occupationId,
                    'company' => $occupation['company'],
                    'address' => $occupation['address'],
                    'start_year' => $occupation['start_year'],
                    'end_year' => $occupation['end_year'],
                    'is_current' => $occupation['is_current'],
                ]);
            }

            $this->pdo->commit();
            return $this->findById($profile->id);
        } catch (PDOException $e) {
            $this->pdo->rollBack();
            throw new Exception($e->getMessage());
        }
    }

    public function update(int $id, array $data): ?array
    {
        $profile = ProfileAlumni::update($this->pdo, $id, $data);
        return $profile ? $this->attachRequired($profile) : null;
    }

    public function reject(int $id, array $data): ?array
    {
        try {
            $this->pdo->beginTransaction();

            RejectionMessageDean::create($this->pdo, $data);
            $updatedProfile = ProfileAlumni::update($this->pdo, $id, $data);

            $this->pdo->commit();

            return $updatedProfile->toArray();
        } catch (PDOException $e) {
            error_log($e->getMessage());
            $this->pdo->rollBack();
            return null;
        }
    }
}