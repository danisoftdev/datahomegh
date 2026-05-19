<?php

namespace App\Services;

use App\Models\BundlePackage;
use App\Models\FulfillmentApiProfile;
use App\Models\Order;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

final class DataPackageFulfillmentService
{
    private const PLACE_PATH = '/api/developer/orders/place';

    private const STATUS_PATH_PREFIX = '/api/developer/orders/reference/';

    /**
     * @return array{ok: bool, message: string, skipped?: bool}
     */
    public function dispatchAfterOrderPlaced(Order $order): array
    {
        $order->loadMissing('bundlePackage');

        $bundle = $order->bundlePackage;
        if ($bundle === null || $bundle->isMtnAfaRegistration()) {
            return ['ok' => false, 'message' => __('MTN AFA orders are not sent to external APIs.'), 'skipped' => true];
        }

        $bundleType = trim((string) ($bundle->provider_bundle_type ?? ''));
        if ($bundleType === '') {
            $msg = __('This bundle has no provider bundle code. Edit the bundle and set Provider bundle code (e.g. mtnup2u).');
            $this->recordDispatchError($order->id, $msg);

            return ['ok' => false, 'message' => $msg, 'skipped' => true];
        }

        $profile = FulfillmentApiProfile::activeForNetwork((string) $order->network);
        if ($profile === null) {
            $msg = __('No active API profile for network :network. Go to Data APIs and activate one.', ['network' => $order->network]);
            $this->recordDispatchError($order->id, $msg);

            return ['ok' => false, 'message' => $msg, 'skipped' => true];
        }

        return $this->placeOnProvider($order, $bundle, $profile, $bundleType);
    }

    /**
     * @return array{ok: bool, message: string}
     */
    public function refreshProviderStatus(Order $order): array
    {
        $order->loadMissing('fulfillmentApiProfile');

        $profile = $order->fulfillmentApiProfile;
        $ref = trim((string) ($order->provider_order_reference ?? ''));

        if ($profile === null || $ref === '') {
            return ['ok' => false, 'message' => __('This order has no linked provider reference or API profile.')];
        }

        $base = rtrim((string) $profile->base_url, '/');
        $url = $base.self::STATUS_PATH_PREFIX.$ref;

        try {
            $response = Http::timeout(25)
                ->withHeaders([
                    'X-API-Key' => (string) $profile->api_key,
                    'Accept' => 'application/json',
                ])
                ->get($url);
        } catch (Throwable $e) {
            Log::warning('data_package_fulfillment_status_request_failed', [
                'order_id' => $order->id,
                'message' => $e->getMessage(),
            ]);

            return ['ok' => false, 'message' => __('Could not reach provider: :msg', ['msg' => $e->getMessage()])];
        }

        if (! $response->successful()) {
            return ['ok' => false, 'message' => __('Provider returned HTTP :code.', ['code' => $response->status()])];
        }

        $json = $response->json();
        if (! is_array($json) || empty($json['success'])) {
            return ['ok' => false, 'message' => __('Provider did not confirm success.')];
        }

        $status = $this->extractNested($json, ['data', 'order', 'status']);

        Order::query()->whereKey($order->id)->update([
            'provider_status' => $status,
            'provider_status_synced_at' => now(),
        ]);

        return ['ok' => true, 'message' => __('Provider status updated.')];
    }

    /**
     * @return array{ok: bool, message: string}
     */
    private function placeOnProvider(Order $order, BundlePackage $bundle, FulfillmentApiProfile $profile, string $bundleType): array
    {
        $base = rtrim((string) $profile->base_url, '/');
        $url = $base.self::PLACE_PATH;
        $capacity = $this->capacityForBundle($bundle);

        try {
            $response = Http::timeout(25)
                ->withHeaders([
                    'X-API-Key' => (string) $profile->api_key,
                    'Accept' => 'application/json',
                ])
                ->asJson()
                ->post($url, [
                    'recipientNumber' => (string) $order->phone_number,
                    'capacity' => $capacity,
                    'bundleType' => $bundleType,
                ]);
        } catch (Throwable $e) {
            Log::warning('data_package_fulfillment_place_request_failed', [
                'order_id' => $order->id,
                'message' => $e->getMessage(),
            ]);
            $msg = __('Could not reach provider: :msg', ['msg' => $e->getMessage()]);
            $this->recordDispatchError($order->id, $msg);

            return ['ok' => false, 'message' => $msg];
        }

        if (! $response->successful()) {
            $body = $response->body();
            Log::warning('data_package_fulfillment_place_http_error', [
                'order_id' => $order->id,
                'status' => $response->status(),
                'body' => $body,
            ]);
            $msg = __('Provider returned HTTP :code. :body', [
                'code' => $response->status(),
                'body' => strlen($body) > 200 ? substr($body, 0, 200).'…' : $body,
            ]);
            $this->recordDispatchError($order->id, $msg);

            return ['ok' => false, 'message' => $msg];
        }

        $json = $response->json();
        if (! is_array($json) || empty($json['success'])) {
            $providerMsg = is_array($json) ? (string) ($json['message'] ?? '') : '';
            Log::warning('data_package_fulfillment_place_unsuccessful', [
                'order_id' => $order->id,
                'json' => $json,
            ]);
            $msg = $providerMsg !== ''
                ? __('Provider rejected the order: :msg', ['msg' => $providerMsg])
                : __('Provider did not return success.');
            $this->recordDispatchError($order->id, $msg);

            return ['ok' => false, 'message' => $msg];
        }

        $ref = $this->extractOrderReference($json);
        if ($ref === null || $ref === '') {
            Log::warning('data_package_fulfillment_missing_order_reference', [
                'order_id' => $order->id,
                'json' => $json,
            ]);
            $msg = __('Provider accepted the order but did not return an order reference.');
            $this->recordDispatchError($order->id, $msg);

            return ['ok' => false, 'message' => $msg];
        }

        Order::query()->whereKey($order->id)->update([
            'provider_order_reference' => $ref,
            'fulfillment_api_profile_id' => $profile->id,
            'provider_status' => $this->extractNested($json, ['data', 'order', 'status']),
            'provider_status_synced_at' => now(),
            'provider_dispatch_error' => null,
        ]);

        return ['ok' => true, 'message' => __('Order sent to provider. Reference: :ref', ['ref' => $ref])];
    }

    private function recordDispatchError(int $orderId, string $message): void
    {
        Order::query()->whereKey($orderId)->update([
            'provider_dispatch_error' => $message,
        ]);
    }

    private function capacityForBundle(BundlePackage $bundle): int
    {
        if (preg_match('/(\d+)/', (string) $bundle->size_label, $matches)) {
            return max(1, (int) $matches[1]);
        }

        return 1;
    }

    /**
     * @param  array<string, mixed>  $json
     */
    private function extractOrderReference(array $json): ?string
    {
        $ref = data_get($json, 'data.order.orderReference');
        if (is_string($ref) && $ref !== '') {
            return $ref;
        }

        $alt = data_get($json, 'data.order.reference');
        if (is_string($alt) && $alt !== '') {
            return $alt;
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $json
     * @param  list<string>  $path
     */
    private function extractNested(array $json, array $path): ?string
    {
        $v = data_get($json, implode('.', $path));

        return is_string($v) && $v !== '' ? $v : null;
    }
}
