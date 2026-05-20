<?php

namespace App\Services\Fulfillment;

use App\Models\BundlePackage;
use App\Models\FulfillmentApiProfile;
use App\Models\Order;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

final class GeonetFulfillmentClient
{
    private const PLACE_PATH = '/v1/place-order';

    /**
     * @return array{ok: bool, message: string}
     */
    public function place(Order $order, BundlePackage $bundle, FulfillmentApiProfile $profile, string $networkKey): array
    {
        $base = FulfillmentApiProfile::normalizeGeonetBaseUrl((string) $profile->base_url);
        $url = $base.self::PLACE_PATH;
        $ref = (string) $order->id;
        $capacityGb = $this->capacityGbForBundle($bundle);

        try {
            $response = Http::timeout(25)
                ->withToken((string) $profile->api_key)
                ->acceptJson()
                ->asJson()
                ->post($url, [
                    'network_key' => $networkKey,
                    'ref' => $ref,
                    'recipient' => (string) $order->phone_number,
                    'capacity' => $capacityGb,
                ]);
        } catch (Throwable $e) {
            Log::warning('geonet_fulfillment_place_request_failed', [
                'order_id' => $order->id,
                'message' => $e->getMessage(),
            ]);

            return ['ok' => false, 'message' => __('Could not reach provider: :msg', ['msg' => $e->getMessage()])];
        }

        if (! $response->successful()) {
            return ['ok' => false, 'message' => $this->httpErrorMessage($response->status(), $url, $response->body())];
        }

        $json = $response->json();
        if (! is_array($json)) {
            return ['ok' => false, 'message' => __('Provider returned an invalid response.')];
        }

        $data = $json['data'] ?? null;
        if (! is_array($data) || ($data['status'] ?? '') !== 'success') {
            $failed = $data['failed_orders'] ?? [];
            $firstFail = is_array($failed) && isset($failed[0]) && is_array($failed[0])
                ? (string) ($failed[0]['message'] ?? $failed[0]['reason'] ?? '')
                : '';
            $msg = (string) ($json['message'] ?? __('Order was not accepted by Geonettech.'));
            if ($firstFail !== '') {
                $msg .= ' '.$firstFail;
            }

            return ['ok' => false, 'message' => $msg];
        }

        $orders = $data['orders'] ?? [];
        if (! is_array($orders) || $orders === []) {
            return ['ok' => false, 'message' => __('Geonettech did not return any accepted orders.')];
        }

        $first = $orders[0];
        $status = is_array($first) ? ($first['status'] ?? 'pending') : 'pending';

        return [
            'ok' => true,
            'message' => __('Order sent to Geonettech. Reference: :ref', ['ref' => $ref]),
            'reference' => $ref,
            'status' => is_string($status) ? $status : 'pending',
        ];
    }

    /**
     * @return array{ok: bool, message: string}
     */
    public function refreshStatus(Order $order, FulfillmentApiProfile $profile, string $reference): array
    {
        $base = FulfillmentApiProfile::normalizeGeonetBaseUrl((string) $profile->base_url);
        $url = $base.'/v1/order/'.rawurlencode($reference).'/status';

        try {
            $response = Http::timeout(25)
                ->withToken((string) $profile->api_key)
                ->acceptJson()
                ->get($url);
        } catch (Throwable $e) {
            Log::warning('geonet_fulfillment_status_request_failed', [
                'order_id' => $order->id,
                'message' => $e->getMessage(),
            ]);

            return ['ok' => false, 'message' => __('Could not reach provider: :msg', ['msg' => $e->getMessage()])];
        }

        if (! $response->successful()) {
            return ['ok' => false, 'message' => __('Provider returned HTTP :code.', ['code' => $response->status()])];
        }

        $json = $response->json();
        $data = is_array($json) ? ($json['data'] ?? null) : null;
        if (! is_array($data) || ($data['status'] ?? '') !== 'success') {
            return ['ok' => false, 'message' => __('Provider did not confirm success.')];
        }

        $orderData = $data['order'] ?? null;
        $status = is_array($orderData) ? ($orderData['status'] ?? null) : null;

        return [
            'ok' => true,
            'message' => __('Provider status updated.'),
            'status' => is_string($status) ? $status : null,
        ];
    }

    private function capacityGbForBundle(BundlePackage $bundle): int
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
