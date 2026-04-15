<?php

namespace App\Core;

use App\Config\UploadsConfig;
use App\Core\Types\Builtin\Mime;
use Exception;
use finfo;

class Upload
{
    private string $label;
    public string $sourceField;
    private string $folder;
    private array $accept;
    private bool $required;
    private int $maxsize;
    public ?string $tmpPath = null;
    public ?string $errMessage = null;
    public ?string $filename = null;
    public ?string $file = null;
    public ?array $rawFile = null;
    
    public function __construct(
        string $label,
        string $sourceField,
        string $folder,
        array $accept = [],
        bool $required = true,
        int $maxsize = 5
    )
    {
        $this->label = $label;
        $this->sourceField = $sourceField;
        $this->folder = $folder;
        $this->accept = $accept;
        $this->required = $required;
        $this->maxsize = $maxsize;

        if (!empty($_FILES[$this->sourceField]) && $_FILES[$this->sourceField]['error'] === UPLOAD_ERR_OK) {
            $this->file = $_FILES[$this->sourceField]['tmp_name'];
            $this->rawFile = $_FILES[$this->sourceField];
        } else {
            $this->errMessage = "Failed to upload {$this->label}.";
        }
    }

    public function stage()
    {        
        // skip if has error
        if ($this->hasError()) return;
        
        if ($this->file === null) {
            if ($this->required) {
                $this->errMessage = "{$this->label} is required.";
            }
            return;
        }

        // check filesize
        $maxSizeB = $this->maxsize * 1024 * 1024;

        if (filesize($this->file) > $maxSizeB) {
            $this->errMessage = "{$this->label} must not exceed {$this->maxsize} MB.";
            return;
        }

        $this->filename = self::getUniqueName($this->rawFile['name']);
        $this->tmpPath = $this->folder . "/" . $this->filename;

        if (!copy($this->file, $this->tmpPath)) {
            $this->errMessage = "Failed to read {$this->label}.";
            return;
        }

        $finfo = new finfo(FILEINFO_MIME_TYPE);
        $mimeType = $finfo->file($this->tmpPath);
        $extension = strtolower(pathinfo($this->rawFile['name'], PATHINFO_EXTENSION));

        if ($mimeType === 'text/plain' && $extension === 'csv') {
            $mimeType = 'text/csv';
        }

        if (Mime::tryFrom($mimeType) === null) {
            $this->tmpPath = null;
            $this->errMessage = "{$this->label}'s file type is unsupoorted.";
            return;
        }
        
        if (!in_array(Mime::from($mimeType), $this->accept)) {
            unlink($this->tmpPath);
            $this->tmpPath = null;
            $this->errMessage = "{$this->label} has an invalid file format.";
            return;
        }
    }

     public function commit()
    {
        if ($this->errMessage !== null || $this->tmpPath === null) {
            return;
        }

        $finalPath = $this->folder . "/" . $this->filename;

        if (!rename($this->tmpPath, $finalPath)) {
            $this->errMessage = "Unable to upload {$this->label}.";
        }
    }

    public function rollback()
    {
        if ($this->tmpPath !== null && file_exists($this->tmpPath)) {
            unlink($this->tmpPath);
            $this->tmpPath = null;
        }
    }

    public function hasError()
    {
        return $this->errMessage !== null;
    }

    public function getError()
    {
        return $this->errMessage;
    }

    public function getFilename()
    {
        return $this->filename;
    }

    public static function getUniqueName($rawName)
    {
        $normalizedName = self::normalizeFilename($rawName);
        $name = pathinfo($normalizedName, PATHINFO_FILENAME);
        $ext = pathinfo($normalizedName, PATHINFO_EXTENSION);
        $uniqueTrail = substr(bin2hex(random_bytes(16)), 0, 8);
        return $name . "-" . $uniqueTrail . "." . $ext;
    }

    public static function normalizeFilename($filename) {
        $ext = pathinfo($filename, PATHINFO_EXTENSION);
        $name = pathinfo($filename, PATHINFO_FILENAME);
        $name = strtolower($name);
        $name = preg_replace('/[^a-z0-9]+/', '-', $name);
        $name = trim($name, '-');
        return $name . '.' . strtolower($ext);
    }
}