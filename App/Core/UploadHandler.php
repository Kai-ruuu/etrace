<?php

namespace App\Core;

use App\Core\Upload;

class UploadHandler
{
    /** @var Upload[] $uploads */
    private array $uploads;
    
    public function __construct(array $uploads = [])
    {
        $this->uploads = $uploads;
    }

    public function stage()
    {
        foreach ($this->uploads as $upload) {
            $upload->stage();

            if ($upload->hasError()) {
                $this->rollback();
                return;
            }
        }
    }

    public function commit()
    {
        foreach ($this->uploads as $upload) {
            $upload->commit();

            if ($upload->hasError()) {
                $this->rollback();
                return;
            }
        }
    }

    public function rollback()
    {
        foreach ($this->uploads as $upload) {
            $upload->rollback();
        }
    }

    public function hasErrors()
    {
        foreach ($this->uploads as $upload) {
            if ($upload->hasError()) return true;
        }
        
        return false;
    }

    public function getErrors(): array
    {
        $errors = [];

        foreach ($this->uploads as $upload) {
            if ($upload->hasError()) {
                $errors[] = $upload->getError();
            }
        }

        return $errors;
    }

    public function getFirstError(): ?string
    {
        foreach ($this->uploads as $upload) {
            if ($upload->hasError()) {
                return $upload->getError();
            }
        }

        return null;
    }

    // public function getFilename($index) {
    //     if ($index < 0 || $index > count($this->uploads) - 1) {
    //         return null;
    //     }

    //     return $this->uploads[$index]->getFilename();
    // }

    public function getFilename(string $sourceField): ?string
    {
        foreach ($this->uploads as $upload) {
            if ($upload->sourceField === $sourceField) {
                return $upload->getFilename();
            }
        }

        return null;
    }
}