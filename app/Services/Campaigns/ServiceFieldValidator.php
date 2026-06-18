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
            'cpf' => $this->validateCpf($value, $label),
            'cnpj' => $this->validateCnpj($value, $label),
            'phone' => $this->validatePhone($value, $label),
            'email' => $this->validateEmail($value, $label),
            'currency' => $this->validateCurrency($value, $label),
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

    private function validateCpf(mixed $value, string $label): array
    {
        if (! is_string($value)) {
            return [false, null, "{$label} possui CPF invalido."];
        }

        $document = preg_replace('/\D/', '', $value);

        if ($document === null || strlen($document) !== 11 || preg_match('/^(\d)\1{10}$/', $document) === 1) {
            return [false, null, "{$label} possui CPF invalido."];
        }

        $digits = array_map('intval', str_split($document));

        for ($position = 9; $position <= 10; $position++) {
            $sum = 0;

            for ($index = 0; $index < $position; $index++) {
                $sum += $digits[$index] * ($position + 1 - $index);
            }

            $checkDigit = ($sum * 10) % 11;
            $checkDigit = $checkDigit === 10 ? 0 : $checkDigit;

            if ($digits[$position] !== $checkDigit) {
                return [false, null, "{$label} possui CPF invalido."];
            }
        }

        return [true, $document, null];
    }

    private function validateCnpj(mixed $value, string $label): array
    {
        if (! is_string($value)) {
            return [false, null, "{$label} possui CNPJ invalido."];
        }

        $document = preg_replace('/\D/', '', $value);

        if ($document === null || strlen($document) !== 14 || preg_match('/^(\d)\1{13}$/', $document) === 1) {
            return [false, null, "{$label} possui CNPJ invalido."];
        }

        $digits = array_map('intval', str_split($document));
        $weights = [
            [5, 4, 3, 2, 9, 8, 7, 6, 5, 4, 3, 2],
            [6, 5, 4, 3, 2, 9, 8, 7, 6, 5, 4, 3, 2],
        ];

        foreach ($weights as $position => $weightSet) {
            $sum = 0;

            foreach ($weightSet as $index => $weight) {
                $sum += $digits[$index] * $weight;
            }

            $remainder = $sum % 11;
            $checkDigit = $remainder < 2 ? 0 : 11 - $remainder;

            if ($digits[12 + $position] !== $checkDigit) {
                return [false, null, "{$label} possui CNPJ invalido."];
            }
        }

        return [true, $document, null];
    }

    private function validatePhone(mixed $value, string $label): array
    {
        if (! is_string($value)) {
            return [false, null, "{$label} possui telefone invalido."];
        }

        if (str_starts_with($value, '+')) {
            if (preg_match('/^\+(?:[\s().-]*\d){8,15}[\s().-]*$/', $value) !== 1) {
                return [false, null, "{$label} possui telefone invalido."];
            }

            $digits = preg_replace('/\D/', '', $value);

            return [true, $digits, null];
        }

        if (preg_match('/^[\d\s().-]+$/', $value) !== 1) {
            return [false, null, "{$label} possui telefone invalido."];
        }

        $digits = preg_replace('/\D/', '', $value);

        if ($digits === null || ! in_array(strlen($digits), [10, 11], true)) {
            return [false, null, "{$label} possui telefone invalido."];
        }

        return [true, $digits, null];
    }

    private function validateEmail(mixed $value, string $label): array
    {
        if (! is_string($value)) {
            return [false, null, "{$label} possui e-mail invalido."];
        }

        $email = strtolower($value);

        if (strlen($email) > 255 || filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
            return [false, null, "{$label} possui e-mail invalido."];
        }

        return [true, $email, null];
    }

    private function validateCurrency(mixed $value, string $label): array
    {
        if (! is_string($value)) {
            return [false, null, "{$label} possui moeda invalida."];
        }

        if (preg_match('/^(?:R\$\s*)?(?:(?:\d{1,3}(?:\.\d{3})+)|\d+)(?:,\d{1,2})?$/', $value) !== 1) {
            return [false, null, "{$label} possui moeda invalida."];
        }

        $number = preg_replace('/^R\$\s*/', '', $value);
        $number = str_replace('.', '', $number);
        [$reais, $centavos] = array_pad(explode(',', $number, 2), 2, '0');

        return [true, ((int) $reais * 100) + (int) str_pad($centavos, 2, '0'), null];
    }
}
