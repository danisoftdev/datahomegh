<?php

namespace Tests\Unit;

use App\Support\PaystackVerifyAmount;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class PaystackVerifyAmountTest extends TestCase
{
    #[DataProvider('registrationFeeMatchCases')]
    public function test_registration_fee_matches(int $expectedPesewas, int $paidPesewas, bool $matches): void
    {
        $this->assertSame($matches, PaystackVerifyAmount::registrationFeeMatches($expectedPesewas, $paidPesewas));
    }

    /**
     * @return array<string, array{int, int, bool}>
     */
    public static function registrationFeeMatchCases(): array
    {
        return [
            'exact' => [2500, 2500, true],
            'one_pesewa_under' => [2500, 2499, true],
            'twenty_percent_surcharge' => [2500, 3000, true],
            'max_allowed_band' => [2500, 3225, true],
            'above_max_band' => [2500, 3226, false],
            'far_below_fee' => [2500, 2000, false],
            'zero_paid' => [2500, 0, false],
        ];
    }
}
