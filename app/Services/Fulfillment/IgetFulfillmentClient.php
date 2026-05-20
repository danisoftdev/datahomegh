<?php

namespace App\Services\Fulfillment;

use App\Models\BundlePackage;
use App\Models\FulfillmentApiProfile;
use App\Models\Order;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

final class IgetFulfillmentClient
{
    private const PLACE_PATH = '/api/developer/orders/place';

    private const STATUS_PATH_PREFIX = '/api/developer/orders/reference/';

    /**
     * @return array{ok: bool, message: string}
     */
    public function place(Order $order, BundlePackage $bundle, FulfillmentApiProfile $profile, string $bundleType): array
    {
        $base = FulfillmentApiProfile::normalizeBaseUrl((string) $profile->base_url);
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
            Log::warning('iget_fulfillment_place_request_failed', [
                'order_id' => $order->id,
                'message' => $e->getMessage(),
            ]);

            return ['ok' => false, 'message' => __('Could not reach provider: :msg', ['msg' => $e->getMessage()])];
        }

        if (! $response->successful()) {
            return ['ok' => false, 'message' => $this->httpErrorMessage($response->status(), $url, $response->body())];
        }

        $json = $response->json();
        if (! is_array($json) || empty($json['success'])) {
            $providerMsg = is_array($json) ? (string) ($json['message'] ?? '') : '';

            return [
                'ok' => false,
                'message' => $providerMsg !== ''
                    ? __('Provider rejected the order: :msg', ['msg' => $providerMsg])
                    : __('Provider did not return success.'),
            ];
        }

        $ref = data_get($json, 'data.order.orderReference') ?? data_get($json, 'data.order.reference');
        if (! is_string($ref) || $ref === '') {
            return ['ok' => false, 'message' => __('Provider accepted the order but did not return an order reference.')];
        }

        return [
            'ok' => true,
            'message' => __('Order sent to provider. Reference: :ref', ['ref' => $ref]),
            'reference' => $ref,
            'status' => data_get($json, 'data.order.status'),
        ];
    }

    /**
     * @return array{ok: bool, message: string}
     */
    public function refreshStatus(Order $order, FulfillmentApiProfile $profile, string $reference): array
    {
        $base = FulfillmentApiProfile::normalizeBaseUrl((string) $profile->base_url);
        $url = $base.self::STATUS_PATH_PREFIX.$reference;

        try {
            $response = Http::timeout(25)
                ->withHeaders([
                    'X-API-Key' => (string) $profile->api_key,
                    'Accept' => 'application/json',
                ])
                ->get($url);
        } catch (Throwable $e) {
            Log::warning('iget_fulfillment_status_request_failed', [
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

        $status = data_get($json, 'data.order.status');

        return [
            'ok' => true,
            'message' => __('Provider status updated.'),
            'status' => is_string($status) ? $status : null,
        ];
    }

    private function capacityForBundle(BundlePackage $bundle): int
    {
        if (preg_match('/(\d+)/', (string) $bundle->size_label, $matches)) {
            return max(1, (int) $matches[1]);
        }

        return 1;
    }

    private function httpErrorMessage(int $code, string $url, string $body): string
    {
        return __('Provider returned HTTP :code for :url. :body', [
            'code' => $code,
            'url' => $url,
            'body' => strlen($body) > 200 ? substr($body, 0, 200).'…' : $body,
        ]);
    }
}
