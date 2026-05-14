<?php

namespace App\Support;

final class BundlePackageKind
{
    public const DATA = 'data';

    public const MTN_AFA = 'mtn_afa';

    /**
     * @return list<string>
     */
    public static function all(): array
    {
        return [self::DATA, self::MTN_AFA];
    }

    public static function isMtnAfa(?string $kind): bool
    {
        return ($kind ?? self::DATA) === self::MTN_AFA;
    }
}
