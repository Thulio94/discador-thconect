<?php

namespace App\Services\Telephony;

final class BrazilianPhoneNormalizer
{
    /** @var array<int, int> */
    private const VALID_DDDS = [
        11, 12, 13, 14, 15, 16, 17, 18, 19,
        21, 22, 24, 27, 28,
        31, 32, 33, 34, 35, 37, 38,
        41, 42, 43, 44, 45, 46, 47, 48, 49,
        51, 53, 54, 55,
        61, 62, 63, 64, 65, 66, 67, 68, 69,
        71, 73, 74, 75, 77, 79,
        81, 82, 83, 84, 85, 86, 87, 88, 89,
        91, 92, 93, 94, 95, 96, 97, 98, 99,
    ];

    /**
     * Returns the national format accepted by the dialer: DDD + subscriber
     * number. Legacy eight-digit mobile numbers receive the mandatory ninth
     * digit before persistence.
     */
    public function normalize(string $value): ?string
    {
        $value = trim($value);

        if ($value === '' || preg_match('/^\+?[\d\s().-]+$/', $value) !== 1) {
            return null;
        }

        $digits = preg_replace('/\D+/', '', $value) ?: '';

        if (str_starts_with($digits, '55') && in_array(strlen($digits), [12, 13], true)) {
            $digits = substr($digits, 2);
        }

        if (! in_array(strlen($digits), [10, 11], true) || preg_match('/^(\d)\1+$/', $digits) === 1) {
            return null;
        }

        $ddd = (int) substr($digits, 0, 2);
        $subscriber = substr($digits, 2);

        if (! in_array($ddd, self::VALID_DDDS, true)) {
            return null;
        }

        if (strlen($subscriber) === 8) {
            $firstDigit = (int) $subscriber[0];

            if ($firstDigit >= 2 && $firstDigit <= 5) {
                return $digits;
            }

            if ($firstDigit >= 6 && $firstDigit <= 9) {
                return (string) $ddd.'9'.$subscriber;
            }

            return null;
        }

        return preg_match('/^9[6-9]\d{7}$/', $subscriber) === 1 ? $digits : null;
    }
}
