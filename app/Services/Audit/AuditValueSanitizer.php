<?php

namespace App\Services\Audit;

use DateTimeInterface;
use JsonSerializable;
use Stringable;

final class AuditValueSanitizer
{
    private const SENSITIVE_FRAGMENTS = [
        'password',
        'token',
        'secret',
        'api_key',
        'apikey',
        'remember',
    ];

    public function sanitize(array $values): array
    {
        $sanitized = [];

        foreach ($values as $key => $value) {
            if (is_string($key) && $this->isSensitive($key)) {
                continue;
            }

            $sanitized[$key] = $this->normalize($value);
        }

        return $sanitized;
    }

    private function isSensitive(string $key): bool
    {
        $normalized = strtolower($key);

        foreach (self::SENSITIVE_FRAGMENTS as $fragment) {
            if (str_contains($normalized, $fragment)) {
                return true;
            }
        }

        return false;
    }

    private function normalize(mixed $value): mixed
    {
        if (is_array($value)) {
            return $this->sanitize($value);
        }

        if ($value instanceof DateTimeInterface) {
            return $value->format(DATE_ATOM);
        }

        if ($value instanceof JsonSerializable) {
            return $this->normalize($value->jsonSerialize());
        }

        if (is_string($value)) {
            return mb_strlen($value) > 2000
                ? mb_substr($value, 0, 2000).'...'
                : $value;
        }

        if (is_scalar($value) || $value === null) {
            return $value;
        }

        if ($value instanceof Stringable) {
            return $this->normalize((string) $value);
        }

        return get_debug_type($value);
    }
}
