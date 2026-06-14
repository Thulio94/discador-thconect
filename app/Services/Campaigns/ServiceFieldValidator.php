<?php

namespace App\Services\Campaigns;

use DateTimeImmutable;

final class ServiceFieldValidator
{
    public static function validate(array $schema, array $values): ServiceFieldValidationResult
    {
        $normalizedValues = [];
        $errors = [];

        foreach ($schema as $field) {
            if (! is_array($field) || ! isset($field['key']) || $field['key'] === '') {
                continue;
            }

            $key = (string) $field['key'];
            $label = isset($field['label']) ? (string) $field['label'] : $key;
            $type = isset($field['type']) ? (string) $field['type'] : 'text';
            $required = ($field['required'] ?? false) === true;
            $value = $values[$key] ?? null;

            if (is_string($value)) {
                $value = trim($value);
            }

            if ($value === null || $value === '') {
                if ($required) {
                    $errors["service_fields.{$key}"] = "Informe {$label}.";
                }

                continue;
            }

            [$valid, $normalized, $message] = self::validateField($type, $value, $label);

            if (! $valid) {
                $errors["service_fields.{$key}"] = $message;

                continue;
            }

            $normalizedValues[$key] = $normalized;
        }

        return new ServiceFieldValidationResult($normalizedValues, $errors);
    }

    private static function validateField(string $type, mixed $value, string $label): array
    {
        return match ($type) {
            'number' => self::validateNumber($value, $label),
            'date' => self::validateDate($value, $label),
            default => self::validateText($value, $label),
        };
    }

    private static function validateText(mixed $value, string $label): array
    {
        if (! is_string($value)) {
            return [false, null, "{$label} possui texto invalido."];
        }

        if (mb_strlen($value) > 255) {
            return [false, null, "{$label} deve ter no maximo 255 caracteres."];
        }

        return [true, $value, null];
    }

    private static function validateNumber(mixed $value, string $label): array
    {
        if (! is_string($value) || preg_match('/^-?\d+(?:[.,]\d+)?$/', $value) !== 1) {
            return [false, null, "{$label} possui numero invalido."];
        }

        return [true, str_replace(',', '.', $value), null];
    }

    private static function validateDate(mixed $value, string $label): array
    {
        if (! is_string($value)) {
            return [false, null, "{$label} possui data invalida."];
        }

        $date = DateTimeImmutable::createFromFormat('!Y-m-d', $value);

        if ($date === false || $date->format('Y-m-d') !== $value) {
            return [false, null, "{$label} possui data invalida."];
        }

        return [true, $value, null];
    }
}
