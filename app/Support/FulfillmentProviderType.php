<?php

namespace App\Support;

final class FulfillmentProviderType
{
    public const IGET = 'iget';

    public const GEONET = 'geonet';

    public const ENCARTA = 'encarta';

    /**
     * @return array<string, string>
     */
    public static function labels(): array
    {
        return [
            self::IGET => 'iGet',
            self::GEONET => 'Geonettech',
            self::ENCARTA => 'Encarta Stores',
        ];
    }

    /**
     * @return list<string>
     */
    public static function all(): array
    {
        return [self::IGET, self::GEONET, self::ENCARTA];
    }

    public static function isValid(string $type): bool
    {
        return in_array($type, self::all(), true);
    }

    /**
     * @return list<string>
     */
    public static function networksFor(string $providerType): array
    {
        return match ($providerType) {
            self::IGET => ['Telecel'],
            self::GEONET, self::ENCARTA => ['MTN'],
            default => [],
        };
    }

    public static function allowsNetwork(string $providerType, string $network): bool
    {
        return in_array($network, self::networksFor($providerType), true);
    }
}
