<?php

namespace App\Support;

use App\Models\User;
use RuntimeException;

/**
 * Reserved shop_slug for self-registered agents (pending until admin approves).
 * Fixed length, mixed letters and digits, unique in users.shop_slug.
 */
final class AgentRegistrationShopCode
{
    private const LENGTH = 5;

    public static function generateUnique(): string
    {
        for ($attempt = 0; $attempt < 100; $attempt++) {
            $code = self::randomCode();
            if (! self::hasLetterAndDigit($code)) {
                continue;
            }
            if (! User::query()->where('shop_slug', $code)->exists()) {
                return $code;
            }
        }

        throw new RuntimeException('Could not allocate a unique agent registration code.');
    }

    private static function randomCode(): string
    {
        $chars = 'ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnpqrstuvwxyz23456789';

        $out = '';
        for ($i = 0; $i < self::LENGTH; $i++) {
            $out .= $chars[random_int(0, strlen($chars) - 1)];
        }

        return $out;
    }

    private static function hasLetterAndDigit(string $code): bool
    {
        return (bool) preg_match('/[A-Za-z]/', $code) && (bool) preg_match('/\d/', $code);
    }
}
