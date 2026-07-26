<?php

namespace App\Services\Fulfillment;

use App\Models\Order;
use App\Support\FulfillmentProviderType;
use Illuminate\Support\Facades\Log;

final class EncartaWebhookService
{
    public function verifySignature(string $rawBody, ?string $signatureHeader): bool
    {
        if ($signatureHeader === null || trim($signatureHeader) === '') {
            return false;
        }

        $secret = trim((string) config('datahome.fulfillment.providers.encarta.webhook_secret', ''));
        if ($secret === '') {
            return false;
        }

        $provided = trim($signatureHeader);
        if (str_starts_with(strtolower($provided), 'sha256=')) {
            $provided = substr($provided, 7);
        }

        $expected = hash_hmac('sha256', $rawBody, $secret);

        return hash_equals($expected, $provided);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function handle(array $payload, ?string $eventHeader = null): void
    {
        $event = (string) ($payload['event'] ?? $eventHeader ?? '');
        $orderData = $this->extractOrderData($payload);

        if ($orderData === []) {
            Log::warning('encarta_webhook_missing_order_payload', ['event' => $event]);

            return;
        }

        if (str_starts_with($event, 'afa.')) {
            Log::info('encarta_webhook_afa_ignored', ['event' => $event]);

            return;
        }

        $order = $this->findOrder($orderData);
        if ($order === null) {
            Log::warning('encarta_webhook_order_not_found', [
                'event' => $event,
                'reference' => $orderData['reference'] ?? null,
                'order_id' => $orderData['id'] ?? null,
            ]);

            return;
        }

        $providerStatus = is_string($orderData['status'] ?? null) ? (string) $orderData['status'] : null;
        $providerReference = is_string($orderData['reference'] ?? null) ? (string) $orderData['reference'] : null;

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
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function extractOrderData(array $payload): array
    {
        if (isset($payload['order']) && is_array($payload['order'])) {
            return $payload['order'];
        }

        if (isset($payload['data']) && is_array($payload['data'])) {
            return $payload['data'];
        }

        return [];
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function findOrder(array $data): ?Order
    {
        $reference = trim((string) ($data['reference'] ?? ''));
        $orderId = $data['id'] ?? $data['order_id'] ?? null;

        $candidates = array_values(array_unique(array_filter([
            $reference,
            is_int($orderId) ? (string) $orderId : (is_string($orderId) ? $orderId : null),
        ])));

        foreach ($candidates as $candidate) {
            if (preg_match('/^dhgh_(\d+)$/', $candidate, $matches)) {
                $order = Order::query()->whereKey((int) $matches[1])->first();
                if ($order !== null && $this->isEncartaOrder($order)) {
                    return $order;
                }
            }

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
