<?php

namespace App\Config;

class UploadsConfig
{
    public static string $uploadsFolderName = "Uploads";

    public static function uploadsFolderPath(): string
    {
        return __DIR__ . '/../../' . self::$uploadsFolderName;
    }
    
    public static array $uploadFolders = [
        'tmp' => 'tmp', // do not remove
        'dean' => 'dean',
        'graduate_record' => 'dean/graduate_record',
        'alumni' => 'alumni',
        'cv' => 'alumni/cv',
        'profile_picture' => 'alumni/profile_picture',
        'company' => 'company',
        'logo' => 'company/logo',
        'profile' => 'company/profile',
        'permit' => 'company/permit',
        'sec' => 'company/sec',
        'dti' => 'company/dti',
        'reg_est' => 'company/reg_est',
        'cert_dole' => 'company/cert_dole',
        'cert_npc' => 'company/cert_npc',
        'reg_pjn' => 'company/reg_pjn',
        'lov' => 'company/lov',
    ];

    public static function folder(string $name): ?string
    {
        if (!isset(self::$uploadFolders[$name])) return null;

        return self::uploadsFolderPath() . '/' . self::$uploadFolders[$name];
    }
}