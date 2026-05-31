<?php

namespace App\Services\Fulfillment;

use App\Models\Order;
use App\Support\FulfillmentProviderType;
use Illuminate\Support\Facades\Log;

final class EncartaWebhookService
{
    public function verifySignature(string $rawBody, ?string $signatureHeader): bool
    {
        if ($signatureHeader === null || $signatureHeader === '') {
            return false;
        }

        $secret = (string) config('datahome.fulfillment.providers.encarta.webhook_secret', 'direct');
        $expected = 'sha256='.hash_hmac('sha256', $rawBody, $secret);

        return hash_equals($expected, $signatureHeader);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function handle(array $payload, ?string $eventHeader = null): void
    {
        $event = (string) ($payload['event'] ?? $eventHeader ?? '');
        $data = $payload['data'] ?? [];
        if (! is_array($data)) {
            Log::warning('encarta_webhook_missing_data', ['event' => $event]);

            return;
        }

        if (str_starts_with($event, 'afa.')) {
            Log::info('encarta_webhook_afa_ignored', ['event' => $event]);

            return;
        }

        $order = $this->findOrder($data);
        if ($order === null) {
            Log::warning('encarta_webhook_order_not_found', [
                'event' => $event,
                'reference' => $data['reference'] ?? null,
                'provider_reference' => $data['provider_reference'] ?? null,
            ]);

            return;
        }

        $providerStatus = is_string($data['status'] ?? null) ? (string) $data['status'] : null;
        $providerReference = is_string($data['provider_reference'] ?? null) ? (string) $data['provider_reference'] : null;

        $updates = [
            'provider_status' => $providerStatus ?? $order->provider_status,
            'provider_status_synced_at' => now(),
        ];

        if ($providerReference !== null && $providerReference !== '') {
            $updates['provider_order_reference'] = $providerReference;
        }

        Order::query()->whereKey($order->id)->update($updates);

        Log::info('encarta_webhook_provider_status_updated', [
            'event' => $event,
            'order_id' => $order->id,
            'provider_status' => $updates['provider_status'],
        ]);
    }

    public static function webhookUrl(): string
    {
        $configured = trim((string) config('datahome.fulfillment.providers.encarta.webhook_url', ''));

        if ($configured !== '') {
            return $configured;
        }

        return rtrim((string) config('app.url'), '/').'/webhooks/encarta';
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function findOrder(array $data): ?Order
    {
        $reference = trim((string) ($data['reference'] ?? ''));
        $providerReference = trim((string) ($data['provider_reference'] ?? ''));

        foreach (array_values(array_unique(array_filter([$reference, $providerReference]))) as $candidate) {
            if (ctype_digit($candidate)) {
                $order = Order::query()->whereKey((int) $candidate)->first();
                if ($order !== null && $this->isEncartaOrder($order)) {
                    return $order;
                }
            }

            $order = Order::query()->where('provider_order_reference', $candidate)->first();
            if ($order !== null && $this->isEncartaOrder($order)) {
                return $order;
            }
        }

        return null;
    }

    private function isEncartaOrder(Order $order): bool
    {
        $order->loadMissing('fulfillmentApiProfile');

        return $order->fulfillmentApiProfile?->provider_type === FulfillmentProviderType::ENCARTA;
    }
}
