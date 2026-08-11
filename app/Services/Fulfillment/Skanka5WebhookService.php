<?php

namespace App\Services\Fulfillment;

use App\Models\Order;
use App\Support\FulfillmentProviderType;
use Illuminate\Support\Facades\Log;

final class Skanka5WebhookService
{
    public function verifySignature(string $rawBody, ?string $signatureHeader): bool
    {
        if ($signatureHeader === null || trim($signatureHeader) === '') {
            return false;
        }

        $secret = trim((string) config('datahome.fulfillment.providers.skanka5.webhook_secret', ''));
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
     * Bulk webhook: orders.processed with flat items[] — match by order_code.
     *
     * @param  array<string, mixed>  $payload
     */
    public function handle(array $payload): void
    {
        $event = (string) ($payload['event'] ?? '');
        if ($event !== '' && $event !== 'orders.processed') {
            Log::info('skanka5_webhook_event_ignored', ['event' => $event]);

            return;
        }

        $items = $payload['items'] ?? $payload['data']['items'] ?? null;
        if (! is_array($items) || $items === []) {
            Log::warning('skanka5_webhook_missing_items');

            return;
        }

        foreach ($items as $item) {
            if (! is_array($item)) {
                continue;
            }

            $this->applyItemUpdate($item);
        }
    }

    /**
     * @param  array<string, mixed>  $item
     */
    private function applyItemUpdate(array $item): void
    {
        $orderCode = trim((string) ($item['order_code'] ?? ''));
        if ($orderCode === '') {
            return;
        }

        $order = Order::query()
            ->where('provider_order_reference', $orderCode)
            ->whereHas('fulfillmentApiProfile', fn ($q) => $q->where('provider_type', FulfillmentProviderType::SKANKA5))
            ->first();

        if ($order === null) {
            Log::warning('skanka5_webhook_order_not_found', ['order_code' => $orderCode]);

            return;
        }

        $providerStatus = is_string($item['status'] ?? null) ? strtolower((string) $item['status']) : null;

        Order::query()->whereKey($order->id)->update([
            'provider_status' => $providerStatus ?? $order->provider_status,
            'provider_status_synced_at' => now(),
        ]);

        Log::info('skanka5_webhook_provider_status_updated', [
            'order_id' => $order->id,
            'order_code' => $orderCode,
            'provider_status' => $providerStatus,
        ]);
    }
}
