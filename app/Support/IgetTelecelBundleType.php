<?php

namespace App\Support;

/**
 * iGet Telecel bundleType sent to the API (e.g. Telecel-5959).
 * Bundles only show customer-facing network/size; this value comes from config.
 */
final class IgetTelecelBundleType
{
    public static function resolve(): string
    {
        return trim((string) config('datahome.fulfillment.fallback_codes.iget.TELECEL', 'Telecel-5959'));
    }

    public static function isTelecelNetwork(string $network): bool
    {
        return strcasecmp(trim($network), 'Telecel') === 0;
    }
}
