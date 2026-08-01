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

    /**
     * Customer may pay Paystack / channel surcharges above the initialized amount.
     * Accept when payment covers the listed amount within a generous upper band.
     */
    public static function initializedPaymentMatches(int $expectedPesewas, int $paidPesewas): bool
    {
        if ($paidPesewas <= 0 || $expectedPesewas <= 0) {
            return false;
        }

        $minAccepted = max(0, $expectedPesewas - 10);
        $maxAccepted = (int) ceil($expectedPesewas * 1.25) + 100;

        return $paidPesewas >= $minAccepted && $paidPesewas <= $maxAccepted;
    }

    /**
     * Registration fee: customer may pay slightly more than the listed fee (Paystack / channel surcharges).
     * We require at least the configured fee and allow a generous upper bound — not wallet credit.
     */
    public static function registrationFeeMatches(int $expectedPesewas, int $paidPesewas): bool
    {
        return self::initializedPaymentMatches($expectedPesewas, $paidPesewas);
    }

    /**
     * Credit only the initialized/list price. Paystack surcharges are paid by the customer but not wallet money.
     *
     * @param  array<string, mixed>  $verifyData
     */
    public static function creditGhsFromInitializedPayment(string|float|null $initializedAmountGhs, array $verifyData): string
    {
        if ($initializedAmountGhs === null || $initializedAmountGhs === '') {
            throw new \InvalidArgumentException('Initialized Paystack amount is missing.');
        }

        $expectedPesewas = (int) round(((float) $initializedAmountGhs) * 100);
        $paidPesewas = self::pesewasFromVerifyData($verifyData);

        if ($expectedPesewas <= 0) {
            throw new \InvalidArgumentException('Initialized Paystack amount is invalid.');
        }

        if ($paidPesewas <= 0) {
            throw new \RuntimeException('Paystack verify response did not include an amount.');
        }

        if (! self::initializedPaymentMatches($expectedPesewas, $paidPesewas)) {
            throw new \RuntimeException('Paystack payment does not match the expected amount.');
        }

        return number_format((float) $initializedAmountGhs, 2, '.', '');
    }
}
