<?php

namespace App\Services\Campaigns;

use DateTimeImmutable;

final class ServiceFieldValidator
{
    public function validate(array $schema, array $values): ServiceFieldValidationResult
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
            $required = filter_var($field['required'] ?? false, FILTER_VALIDATE_BOOL);
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

            [$valid, $normalized, $message] = $this->validateField($type, $value, $label);

            if (! $valid) {
                $errors["service_fields.{$key}"] = $message;

                continue;
            }

            $normalizedValues[$key] = $normalized;
        }

        return new ServiceFieldValidationResult($normalizedValues, $errors);
    }

    private function validateField(string $type, mixed $value, string $label): array
    {
        return match ($type) {
            'number' => $this->validateNumber($value, $label),
            'date' => $this->validateDate($value, $label),
            default => $this->validateText($value, $label),
        };
    }

    private function validateText(mixed $value, string $label): array
    {
        if (! is_string($value)) {
            return [false, null, "{$label} possui texto invalido."];
        }

        if (mb_strlen($value) > 255) {
            return [false, null, "{$label} deve ter no maximo 255 caracteres."];
        }

        return [true, $value, null];
    }

    private function validateNumber(mixed $value, string $label): array
    {
        if (! is_string($value) || preg_match('/^-?\d+(?:[.,]\d+)?$/', $value) !== 1) {
            return [false, null, "{$label} possui numero invalido."];
        }

        return [true, str_replace(',', '.', $value), null];
    }

    private function validateDate(mixed $value, string $label): array
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
