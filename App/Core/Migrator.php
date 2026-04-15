<?php

namespace App\Core;

use PDO;

class Migrator
{
    private PDO $pdo;
    
    private array $models = [
        \App\Models\User::class,
        
        \App\Models\ProfileSystemAdmin::class,
        \App\Models\School::class,
        
        \App\Models\ProfileDean::class,
        \App\Models\Course::class,
        \App\Models\GraduateRecord::class,
        
        \App\Models\ProfilePesoStaff::class,
        \App\Models\ProfileCompany::class,
        \App\Models\ProfileAlumni::class,

        \App\Models\Verification::class,

        \App\Models\Occupation::class,
        \App\Models\OccupationState::class,
        \App\Models\CourseOccupation::class,

        \App\Models\SocialMediaPlatform::class,
        \App\Models\SocialMedia::class,

        \App\Models\RejectionMessageSysad::class,
        \App\Models\RejectionMessageSysadAppeal::class,
        \App\Models\RejectionMessagePstaff::class,
        \App\Models\RejectionMessagePstaffAppeal::class,
        \App\Models\RejectionMessageDean::class,
        \App\Models\RejectionMessageDeanAppeal::class,
        \App\Models\RevisionMessage::class,
        \App\Models\RevisionMessageAppeal::class,
        
        \App\Models\JobPost::class,
        \App\Models\JobPostLike::class,
        \App\Models\JobPostCvSubmission::class,
        \App\Models\Qualification::class,
        \App\Models\TargetCourse::class,
    ];

    public function __construct(PDO $pdo) {
        $this->pdo = $pdo;
    }

    public function createTables(): void
    {
        foreach ($this->models as $model) {
            $model::migrate($this->pdo);
            echo "Created: {$model}" . PHP_EOL;
        }
    }

    public function dropTables(): void
    {
        foreach ($this->models as $model) {
            $table = $model::table();
            $sql = $this->pdo->prepare("DROP TABLE IF EXISTS {$table}");
            $sql->execute();
            echo "Dropped: {$table}" . PHP_EOL;
        }
    }
}