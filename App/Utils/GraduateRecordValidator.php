<?php

namespace App\Utils;

use App\Core\HttpResponse;
use DateTime;

class GraduateRecordValidator
{
    private static $optionalFields = [
        "Middle Name",
    ];
    private static $requiredFields = [
        "Student Number",
        "Birthdate",
        "Birthplace",
        "First Name",
        "Last Name",
        "Gender",
        "Full Address",
        "Contact Number",
        "Deceased",
    ];

    public static function validate(): void
    {
        $fieldname = 'record';
        
        if (empty($_FILES[$fieldname]) || $_FILES[$fieldname]['error'] !== UPLOAD_ERR_OK) {
            HttpResponse::bad(["message" => "Graduate record is required."]);
        }

        $file = file($_FILES[$fieldname]["tmp_name"]);
        $rows = array_map("str_getcsv", $file);

        // handle empty trailing columns
        $headers = array_shift($rows);
        $headers = array_map('trim', $headers);

        // remove UTF-8 BOM
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
        $data = array_map(fn($row) => array_combine($headers, $row), $rows);

        // all fields (required + optional) must be present as headers
        $allFields = array_unique(array_merge(self::$requiredFields, self::$optionalFields));
        $missingFields = array_filter($allFields, fn($field) => !in_array($field, $headers));

        if (!empty($missingFields)) {
            $joinedMissingFields = implode(", ", array_map(fn($f) => "'{$f}'", $missingFields));
            HttpResponse::unprocessable(["message" => "Graduate record requires these fields to be present: {$joinedMissingFields}."]);
        }

        // validate row values — only required fields must have values
        $errMsgs = [];
        $row = 1;

        foreach ($data as $d) {
            foreach ($d as $column => $value) {
                if (in_array($column, self::$requiredFields) && empty(trim($value))) {
                    $errMsgs[] = "Missing value in row {$row} for '{$column}'.";
                }

                if ($column === "Birthdate" && !empty(trim($value)) && !self::isDateFormatValid($value)) {
                    $errMsgs[] = "Invalid date format in row {$row} for '{$column}'. Expected m/d/yyyy without leading zeros.";
                }

                if ($column === "Deceased" && !in_array($value, ['Yes', 'No'])) {
                    $errMsgs[] = "Deceased column value should only be either 'Yes' or 'No'.";
                }
            }

            $row++;
        }

        if (!empty($errMsgs)) {
            HttpResponse::unprocessable(["message" => $errMsgs]);
        }
    }

    private static function isDateFormatValid($dateString)
    {
        $d = DateTime::createFromFormat('n/j/Y', $dateString);
        return $d && $d->format('n/j/Y') === $dateString;
    }
}