<?php

namespace App\Support;

final class PaystackChargeMetadata
{
    /**
     * @param  array<string, mixed>  $data
     * @return array{user_id: int|null, type: string|null}
     */
    public static function fromChargeData(array $data): array
    {
        $meta = $data['metadata'] ?? null;

        if (is_string($meta)) {
            $decoded = json_decode($meta, true);
            $meta = is_array($decoded) ? $decoded : null;
        }

        if (! is_array($meta)) {
            return ['user_id' => null, 'type' => null];
        }

        $uid = isset($meta['user_id']) ? (int) $meta['user_id'] : 0;
        $type = isset($meta['type']) ? (string) $meta['type'] : null;

        return [
            'user_id' => $uid > 0 ? $uid : null,
            'type' => $type !== '' ? $type : null,
        ];
    }
}
