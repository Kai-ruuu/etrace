<?php

namespace App\Core;

class Validator
{
    public static function int(string $label, ?string $value, ?int $minimum = null, ?int $maximum = null): ?int
    {
        if (empty($value)) return null;
        
        $value = filter_var($value, FILTER_VALIDATE_INT);

        if ($value === false) {
            HttpResponse::Unprocessable(['message' => $label . ' should be a valid whole number.']);
        }
        
        if ($minimum !== null && $value < $minimum) {
            HttpResponse::Unprocessable(['message' => $label . ' should be at least ' . $minimum]);
        }
        
        if ($maximum !== null && $value > $maximum) {
            HttpResponse::Unprocessable(['message' => $label . ' should not exceed ' . $maximum]);
        }
        
        return $value;
    }

    public static function float(string $label, ?string $value, ?float $minimum = null, ?float $maximum = null): ?float
    {
        if (empty($value)) return null;
        
        $value = filter_var($value, FILTER_VALIDATE_FLOAT);

        if ($value === false) {
            HttpResponse::Unprocessable(['message' => $label . ' should be a valid whole number.']);
        }
        
        if ($minimum !== null && $value < $minimum) {
            HttpResponse::Unprocessable(['message' => $label . ' should be at least ' . $minimum]);
        }
        
        if ($maximum !== null && $value > $maximum) {
            HttpResponse::Unprocessable(['message' => $label . ' should not exceed ' . $maximum]);
        }
        
        return $value;
    }

    public static function bool(string $label, ?string $value): ?bool
    {
        if (empty($value)) return null;
        
        $value = strtolower($value);
        $trueVals = ['true', '1', 'yes', 'high'];
        $falseVals = ['false', '0', 'no', 'low'];
        
        if (!in_array($value, $trueVals) && !in_array($value, $falseVals)) {
            HttpResponse::Unprocessable(['message' => $label . ' should be a boolean-like value.']);
        }

        if (in_array($value, $trueVals)) {
            return true;
        }

        if (in_array($value, $falseVals)) {
            return false;
        }

        HttpResponse::Unprocessable(['message' => $label . ' should be a boolean-like value.']);
    }

    public static function string(string $label, ?string $value, ?int $minimum = null, ?int $maximum = null): ?string
    {
        if (empty($value)) return null;
        
        if ($minimum !== null && strlen($value) < $minimum) {
            HttpResponse::Unprocessable(['message' => $label . ' should have at least ' . $minimum . ' characters.']);
        }

        if ($maximum !== null && strlen($value) > $maximum) {
            HttpResponse::Unprocessable(['message' => $label . ' should not exceed ' . $maximum . ' characters.']);
        }

        return $value;
    }

    public static function email(string $label, ?string $value): ?string
    {
        if (empty($value)) return null;
        
        $value = filter_var($value, FILTER_SANITIZE_EMAIL);

        if (filter_var($value, FILTER_VALIDATE_EMAIL)) {
            return $value;
        }
        
        return HttpResponse::Unprocessable(['message' => $label . ' should be a valid email address.']);
    }

    public static function enum(string $label, ?string $value, string $enumClass): mixed
    {
        if (empty($value)) return null;

        $result = $enumClass::tryFrom($value);

        if ($result === null) {
            $valid = implode(', ', array_column($enumClass::cases(), 'value'));
            HttpResponse::unprocessable(['message' => $label . ' must be one of: ' . $valid . '.']);
        }

        return $result;
    }

    public static function json(string $label, ?string $value): ?array
    {
        if (empty($value)) return null;

        $decoded = json_decode($value, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            HttpResponse::unprocessable(['message' => $label . ' should be a valid JSON string.']);
        }

        return $decoded;
    }

    public static function requiredInt(string $label, ?string $value, ?int $minimum = null, ?int $maximum = null): int
    {
        if (empty($value)) {
            HttpResponse::unprocessable(['message' => $label . ' is required.']);
        }

        return self::int($label, $value, $minimum, $maximum);
    }

    public static function requiredFloat(string $label, ?string $value, ?float $minimum = null, ?float $maximum = null): float
    {
        if (empty($value)) {
            HttpResponse::unprocessable(['message' => $label . ' is required.']);
        }

        return self::float($label, $value, $minimum, $maximum);
    }

    public static function requiredBool(string $label, ?string $value): ?bool
    {
        if ($value === null) {
            HttpResponse::unprocessable(['message' => $label . ' is required.']);
        }

        return self::bool($label, $value);
    }

    public static function requiredString(string $label, ?string $value, ?int $minimum = null, ?int $maximum = null): ?string
    {
        if (empty($value)) {
            HttpResponse::unprocessable(['message' => $label . ' is required.']);
        }

        return self::string($label, $value, $minimum, $maximum);
    }

    public static function requiredEmail(string $label, ?string $value): ?string
    {
        if (empty($value)) {
            HttpResponse::unprocessable(['message' => $label . ' is required.']);
        }

        return self::email($label, $value);
    }

    public static function requiredEnum(string $label, ?string $value, string $enumClass): mixed
    {
        if (empty($value)) {
            HttpResponse::unprocessable(['message' => $label . ' is required.']);
        }

        return self::enum($label, $value, $enumClass);
    }

    public static function requiredJson(string $label, ?string $value): array
    {
        if (empty($value)) {
            HttpResponse::unprocessable(['message' => $label . ' is required.']);
        }

        return self::json($label, $value);
    }
}