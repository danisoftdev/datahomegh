<?php

namespace App\Support;

/**
 * Encarta Stores networkKey for MTN Data via POST /purchase (not /ishare — that is AirtelTigo).
 */
final class EncartaMtnNetwork
{
    public static function resolve(): string
    {
        return trim((string) config('datahome.fulfillment.fallback_codes.encarta.MTN', 'YELLO'));
    }

    public static function isMtnNetwork(string $network): bool
    {
        return strcasecmp(trim($network), 'MTN') === 0;
    }
}
