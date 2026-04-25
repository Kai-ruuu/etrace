<?php

namespace App\Core;

use App\Config\UploadsConfig;

class UploadManager
{
    public string $uploadsFoder;
    public array $folderMap;
    
    public function __construct()
    {
        $this->uploadsFoder = UploadsConfig::uploadsFolderPath();
        $this->folderMap = UploadsConfig::$uploadFolders;
    }
    
    public function initialize()
    {
        self::createFolder('Uploads', $this->uploadsFoder);

        foreach ($this->folderMap as $name => $path) {
            $folderPath = $this->uploadsFoder . '/' . $path;

            self::createFolder($name, $folderPath);
        }
    }

    public function reset()
    {
        self::deleteFolder($this->uploadsFoder);
        self::createFolder('Uploads', $this->uploadsFoder);
        error_log('Reset: Uploads folder has been reset');
    }

    public static function deleteFile($filePath): bool
    {
        if (!file_exists($filePath)) {
            return false;
        }

        unlink($filePath);
        return true;
    }

    private static function createFolder($name, $path)
    {
        if (is_dir($path)) {
            error_log("Skipped: {$name} folder found. Skipped creation process.");
            return;
        }

        mkdir($path);
        error_log("Created: {$name} folder has been created.");
    }

    private static function deleteFolder($dir) {
        if (!is_dir($dir)) {
            return false;
        }

        $items = array_diff(scandir($dir), array('.', '..'));

        foreach ($items as $item) {
            $path = $dir . DIRECTORY_SEPARATOR . $item;
            // If it's a directory, call this function again
            is_dir($path) ? self::deleteFolder($path) : unlink($path);
        }

        return rmdir($dir);
    }
}