<?php

namespace App\Support;

final class EncartaMtnNetwork
{
    public static function resolve(): string
    {
        return trim((string) config('datahome.fulfillment.fallback_codes.encarta.MTN', 'MTN'));
    }

    public static function isMtnNetwork(string $network): bool
    {
        return strcasecmp(trim($network), 'MTN') === 0;
    }
}
