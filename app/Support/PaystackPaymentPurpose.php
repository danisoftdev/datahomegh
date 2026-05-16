<?php

namespace App\Support;

use App\Models\PaystackTransaction;

final class PaystackPaymentPurpose
{
    public const AGENT_SHOP_REGISTRATION = 'agent_shop_registration';

    public const WALLET_TOPUP = 'wallet_topup';

    public const UNKNOWN = 'unknown';

    /**
     * Decide how an inbound Paystack charge should be handled.
     */
    public static function resolve(?PaystackTransaction $txn, array $verifyData): string
    {
        $storedKind = self::storedKind($txn);
        if ($storedKind === self::AGENT_SHOP_REGISTRATION) {
            return self::AGENT_SHOP_REGISTRATION;
        }
        if ($storedKind === self::WALLET_TOPUP) {
            return self::WALLET_TOPUP;
        }

        $meta = PaystackChargeMetadata::fromChargeData($verifyData);
        if ($meta['type'] === self::AGENT_SHOP_REGISTRATION) {
            return self::AGENT_SHOP_REGISTRATION;
        }
        if ($meta['type'] === self::WALLET_TOPUP) {
            return self::WALLET_TOPUP;
        }

        if ($meta['user_id'] !== null && $meta['type'] === null) {
            return self::WALLET_TOPUP;
        }

        if ($txn !== null) {
            return self::WALLET_TOPUP;
        }

        return self::UNKNOWN;
    }

    public static function storedKind(?PaystackTransaction $txn): ?string
    {
        if ($txn === null) {
            return null;
        }

        $metadata = $txn->metadata;
        if (! is_array($metadata)) {
            return null;
        }

        $kind = $metadata['kind'] ?? null;

        return is_string($kind) && $kind !== '' ? $kind : null;
    }

    /**
     * @param  array<string, mixed>  $verifyData
     * @return array<string, mixed>
     */
    public static function metadataAfterSuccess(string $kind, array $verifyData, ?PaystackTransaction $txn = null): array
    {
        $base = [];
        if ($txn !== null && is_array($txn->metadata)) {
            $base = $txn->metadata;
        }

        return array_merge($base, [
            'kind' => $kind,
            'paystack' => $verifyData,
        ]);
    }
}
