<?php

namespace App\Services;

use App\Models\FulfillmentApiProfile;
use App\Models\Order;
use App\Models\Role;
use App\Models\User;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

final class DataPackageFulfillmentService
{
    private const PLACE_PATH = '/api/developer/orders/place';

    private const STATUS_PATH_PREFIX = '/api/developer/orders/reference/';

    public function dispatchAfterOrderPlaced(Order $order): void
    {
        $order->loadMissing('bundlePackage');

        $bundle = $order->bundlePackage;
        if ($bundle === null || $bundle->isMtnAfaRegistration()) {
            return;
        }

        $bundleType = trim((string) ($bundle->provider_bundle_type ?? ''));
        if ($bundleType === '') {
            return;
        }

        $supplierId = $this->supplierUserId();
        if ($supplierId === null) {
            return;
        }

        $profile = FulfillmentApiProfile::activeForSupplierNetwork($supplierId, (string) $order->network);
        if ($profile === null) {
            return;
        }

        $base = rtrim((string) $profile->base_url, '/');
        $url = $base.self::PLACE_PATH;

        try {
            $response = Http::timeout(25)
                ->withHeaders([
                    'X-API-Key' => (string) $profile->api_key,
                    'Accept' => 'application/json',
                ])
                ->asJson()
                ->post($url, [
                    'recipientNumber' => (string) $order->phone_number,
                    'capacity' => 1,
                    'bundleType' => $bundleType,
                ]);
        } catch (Throwable $e) {
            Log::warning('data_package_fulfillment_place_request_failed', [
                'order_id' => $order->id,
                'message' => $e->getMessage(),
            ]);

            return;
        }

        if (! $response->successful()) {
            Log::warning('data_package_fulfillment_place_http_error', [
                'order_id' => $order->id,
                'status' => $response->status(),
                'body' => $response->body(),
            ]);

            return;
        }

        $json = $response->json();
        if (! is_array($json) || empty($json['success'])) {
            Log::warning('data_package_fulfillment_place_unsuccessful', [
                'order_id' => $order->id,
                'json' => $json,
            ]);

            return;
        }

        $ref = $this->extractOrderReference($json);
        if ($ref === null || $ref === '') {
            Log::warning('data_package_fulfillment_missing_order_reference', [
                'order_id' => $order->id,
                'json' => $json,
            ]);

            return;
        }

        Order::query()->whereKey($order->id)->update([
            'provider_order_reference' => $ref,
            'fulfillment_api_profile_id' => $profile->id,
            'provider_status' => $this->extractNested($json, ['data', 'order', 'status']),
            'provider_status_synced_at' => now(),
        ]);
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

    private function supplierUserId(): ?int
    {
        $id = User::query()
            ->whereHas('role', fn ($q) => $q->where('slug', Role::SLUG_SUPPLIER))
            ->value('id');

        return $id !== null ? (int) $id : null;
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
