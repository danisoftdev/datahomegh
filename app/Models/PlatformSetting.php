<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PlatformSetting extends Model
{
    public const KEY_AGENT_SHOP_REGISTRATION_FEE_GHS = 'agent_shop_registration_fee_ghs';

    protected $fillable = [
        'key',
        'value',
    ];

    public static function get(string $key, ?string $default = null): ?string
    {
        $row = static::query()->where('key', $key)->first();

        return $row?->value ?? $default;
    }

    public static function set(string $key, string $value): void
    {
        static::query()->updateOrCreate(
            ['key' => $key],
            ['value' => $value],
        );
    }

    /**
     * Positive fee in GHS as a decimal string (e.g. "25.00"), or null if unset or invalid.
     */
    public static function agentShopRegistrationFeeGhs(): ?string
    {
        $raw = trim((string) (static::get(self::KEY_AGENT_SHOP_REGISTRATION_FEE_GHS, '') ?? ''));
        if ($raw === '' || ! is_numeric($raw)) {
            return null;
        }

        $n = (float) $raw;
        if ($n <= 0) {
            return null;
        }

        return number_format($n, 2, '.', '');
    }
}
