<?php

namespace App\Support;

/**
 * Geonettech MTN network_key sent to the API (e.g. YELLO).
 * Bundles may show "MTN" to staff; this value is never taken from bundle/profile labels.
 */
final class GeonetMtnNetworkKey
{
    public static function resolve(): string
    {
        return trim((string) config('datahome.fulfillment.fallback_codes.geonet.MTN', 'YELLO'));
    }

    public static function isMtnNetwork(string $network): bool
    {
        return strcasecmp(trim($network), 'MTN') === 0;
    }
}
