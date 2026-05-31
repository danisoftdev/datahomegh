<?php

namespace App\Support;

final class GhanaPhoneNumber
{
    /**
     * Normalize a Ghana MSISDN for upstream data APIs (233XXXXXXXXX).
     */
    public static function forApi(string $phone): string
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
