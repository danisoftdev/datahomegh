<?php

namespace App\Support;

final class GhanaPhoneNumber
{
    /**
     * Local Ghana MSISDN for APIs that expect 0XXXXXXXXX (e.g. Encarta).
     */
    public static function forLocal(string $phone): string
    {
        $digits = preg_replace('/\D/', '', $phone) ?? '';

        if ($digits === '') {
            return trim($phone);
        }

        if (str_starts_with($digits, '233') && strlen($digits) === 12) {
            return '0'.substr($digits, 3);
        }

        if (str_starts_with($digits, '0') && strlen($digits) === 10) {
            return $digits;
        }

        return $digits;
    }

    /**
     * International Ghana MSISDN without plus (233XXXXXXXXX).
     */
    public static function forInternational(string $phone): string
    {
        $digits = preg_replace('/\D/', '', $phone) ?? '';

        if ($digits === '') {
            return trim($phone);
        }

        if (str_starts_with($digits, '0') && strlen($digits) === 10) {
            return '233'.substr($digits, 1);
        }

        return $digits;
    }
}
