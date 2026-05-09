<?php

namespace App\Services;

use App\Config\UploadsConfig;
use App\Models\GraduateRecord;
use Exception;
use PDO;

class AlumniPreverifyingService
{
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    public static function parseRecordCsv(string $recordFilePath): ?array
    {
        try {
            $csvFile = file($recordFilePath);

            if ($csvFile === false) {
                error_log("CSV file not found: $recordFilePath");
                return null;
            }
            
            $rows = array_map("str_getcsv", $csvFile);
            $headers = array_shift($rows);
            $headers = array_map('trim', $headers);
            $headers = array_map(function($h) {
                $h = str_replace("\xEF\xBB\xBF", '', $h);
                return trim($h);
            }, $headers);
            $validIndices = array_keys(array_filter($headers, fn($h) => trim($h) !== ''));
            $headers = array_values(array_intersect_key($headers, array_flip($validIndices)));
            $rows = array_map(function($row) use ($validIndices) {
                $filtered = array_intersect_key($row, array_flip($validIndices));
                return array_values($filtered);
            }, $rows);
            $rows = array_filter($rows, fn($row) => !empty(array_filter($row, fn($col) => trim($col) !== '')));
    
            array_pop($headers);
            $rows = array_map(fn($row) => array_slice($row, 0, -1), $rows);
    
            $data = array_values(array_filter(
                array_map(fn($row) => count($row) === count($headers)
                    ? array_combine($headers, $row)
                    : null,
                $rows)
            ));
    
            return $data;
        } catch (Exception $e) {
            error_log('Record not found - ' . $e->getMessage());
            return null;
        }
    }

    private static function formatCreateInfo(array $createInfo): array
    {

        return [
            "Student Number" => $createInfo["student_number"] ?? "",
            "Birthdate"      => $createInfo["birth_date"]->format('n/j/Y'),
            "Birthplace"     => $createInfo["birth_place"],
            "First Name"     => $createInfo["first_name"],
            "Middle Name"    => $createInfo["middle_name"] ?? "",
            "Last Name"      => $createInfo["last_name"],
            "Gender"         => $createInfo["gender"],
            "Full Address"   => $createInfo["address"],
            "Contact Number" => $createInfo["phone_number"],
        ];
    }

    private function getRecord(array $createInfo): ?string
    {
        $record = GraduateRecord::findByBatchAndCourseId($this->pdo, $createInfo['graduation_year'], $createInfo['course_id']);

        if (!$record)
            return null;

        return UploadsConfig::folder('graduate_record') . '/' . $record->filename;
    }

    public function preverify(array $createInfo): string
    {
        $recordFilePath = $this->getRecord($createInfo);

        if (!$recordFilePath)
            return 'Pending';
        
        $verifyThreshold = 0.75;
        $alum = self::formatCreateInfo($createInfo);
        $rows = self::parseRecordCsv($recordFilePath);

        if ($rows === null)
            return 'Pending';
        
        $rowMaxScore = count($alum);

        foreach ($rows as $row) {
            $rowScore = 0;

            foreach ($alum as $key => $value) {
                $pVals = explode(" ", strtolower($value));
                $rVals = explode(" ", strtolower($row[$key]));
                $rowScore += !empty(array_intersect($pVals, $rVals)) ? 1 : 0;
            }

            if ($rowScore / $rowMaxScore >= $verifyThreshold)
                return "Verified";
        }

        return "Pending";
    }
}