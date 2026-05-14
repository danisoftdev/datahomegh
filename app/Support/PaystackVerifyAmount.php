<?php

namespace App\Support;

final class PaystackVerifyAmount
{
    /**
     * Paystack transaction verify payload: amount is in pesewas (GHS × 100).
     */
    public static function pesewasFromVerifyData(array $data): int
    {
        foreach (['amount', 'requested_amount'] as $key) {
            if (! array_key_exists($key, $data) || $data[$key] === null || $data[$key] === '') {
                continue;
            }

            return (int) $data[$key];
        }

        return 0;
    }

    public static function ghsFromPesewas(int $pesewas): string
    {
        return number_format($pesewas / 100, 2, '.', '');
    }

    /**
     * @param  array<string, mixed>  $verifyData
     */
    public static function ghsFromVerifyData(array $verifyData): string
    {
        return self::ghsFromPesewas(self::pesewasFromVerifyData($verifyData));
    }
}
