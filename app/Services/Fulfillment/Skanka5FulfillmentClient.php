<?php

namespace App\Services\Fulfillment;

use App\Models\BundlePackage;
use App\Models\FulfillmentApiProfile;
use App\Models\Order;
use App\Support\GhanaPhoneNumber;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

final class Skanka5FulfillmentClient
{
    public function __construct(
        private readonly Skanka5CatalogResolver $catalogResolver,
    ) {}

    /**
     * @return array{ok: bool, message: string, reference?: string, status?: string}
     */
    public function place(Order $order, BundlePackage $bundle, FulfillmentApiProfile $profile, string $productCode): array
    {
        unset($productCode);

        $networkId = $this->catalogResolver->resolveNetworkId($profile, (string) $order->network);
        if ($networkId === null) {
            return [
                'ok' => false,
                'message' => __('Skanka5 network_id is missing. Set Default network ID on the API profile, FULFILLMENT_SKANKA5_*_NETWORK_ID in .env, or ensure GET /fetch-networks matches :network.', [
                    'network' => $order->network,
                ]),
            ];
        }

        $volumeMb = $this->catalogResolver->resolveVolumeMb($bundle);
        if ($volumeMb === null) {
            return [
                'ok' => false,
                'message' => __('Skanka5 volume_mb is missing. Match size label to GB (e.g. 2GB → 2000 MB) or set Provider bundle code to volume_mb.'),
            ];
        }

        $base = FulfillmentApiProfile::normalizeSkanka5BaseUrl((string) $profile->base_url);
        $url = $base.'/orders';

        $payload = [
            'network_id' => $networkId,
            'msisdn' => GhanaPhoneNumber::forLocal((string) $order->phone_number),
            'volume_mb' => $volumeMb,
        ];

        try {
            $response = Http::timeout(25)
                ->withHeaders([
                    'x-api-key' => (string) $profile->api_key,
                    'Accept' => 'application/json',
                ])
                ->asJson()
                ->post($url, $payload);
        } catch (Throwable $e) {
            Log::warning('skanka5_fulfillment_place_request_failed', [
                'order_id' => $order->id,
                'message' => $e->getMessage(),
            ]);

            return ['ok' => false, 'message' => __('Could not reach Skanka5: :msg', ['msg' => $e->getMessage()])];
        }

        if ($response->status() !== 202 && ! $response->successful()) {
            return ['ok' => false, 'message' => $this->httpErrorMessage($response->status(), $url, $response->body())];
        }

        $json = $response->json();
        if (! is_array($json)) {
            return ['ok' => false, 'message' => __('Skanka5 returned an invalid response.')];
        }

        if ($this->responseIndicatesFailure($json)) {
            return ['ok' => false, 'message' => $this->extractErrorMessage($json)];
        }

        $reference = $this->extractReference($json);
        if ($reference === '') {
            return ['ok' => false, 'message' => __('Skanka5 accepted the order but did not return a reference.')];
        }

        $status = $this->extractLineStatus($json) ?? 'accepted';

        return [
            'ok' => true,
            'message' => __('Order sent to Skanka5. Reference: :ref', ['ref' => $reference]),
            'reference' => $reference,
            'status' => $status,
        ];
    }

    /**
     * @return array{ok: bool, message: string, status?: string|null}
     */
    public function refreshStatus(Order $order, FulfillmentApiProfile $profile, string $reference): array
    {
        unset($order);

        $base = FulfillmentApiProfile::normalizeSkanka5BaseUrl((string) $profile->base_url);
        $url = $base.'/orders/'.rawurlencode($reference);

        try {
            $response = Http::timeout(25)
                ->withHeaders([
                    'x-api-key' => (string) $profile->api_key,
                    'Accept' => 'application/json',
                ])
                ->get($url);
        } catch (Throwable $e) {
            Log::warning('skanka5_fulfillment_status_request_failed', [
                'reference' => $reference,
                'message' => $e->getMessage(),
            ]);

            return ['ok' => false, 'message' => __('Could not reach Skanka5: :msg', ['msg' => $e->getMessage()])];
        }

        if ($response->status() === 404) {
            return ['ok' => false, 'message' => __('Skanka5 order not found for reference :ref.', ['ref' => $reference])];
        }

        if (! $response->successful()) {
            return ['ok' => false, 'message' => __('Skanka5 returned HTTP :code.', ['code' => $response->status()])];
        }

        $json = $response->json();
        if (! is_array($json)) {
            return ['ok' => false, 'message' => __('Skanka5 returned an invalid status response.')];
        }

        $status = $this->extractPollStatus($json);

        return [
            'ok' => true,
            'message' => __('Provider status updated.'),
            'status' => $status,
        ];
    }

    /**
     * @param  array<string, mixed>  $json
     */
    private function responseIndicatesFailure(array $json): bool
    {
        $status = strtolower((string) ($json['status'] ?? ''));
        if (in_array($status, ['error', 'failed', 'failure'], true)) {
            return true;
        }

        if (array_key_exists('success', $json) && $json['success'] === false) {
            return true;
        }

        return false;
    }

    /**
     * @param  array<string, mixed>  $json
     */
    private function extractErrorMessage(array $json): string
    {
        $errors = $json['errors'] ?? null;
        if (is_array($errors) && $errors !== []) {
            $first = reset($errors);

            return is_string($first) ? $first : __('Skanka5 rejected the order.');
        }

        return (string) ($json['message'] ?? __('Skanka5 did not accept the order.'));
    }

    /**
     * @param  array<string, mixed>  $json
     */
    private function extractReference(array $json): string
    {
        $reference = trim((string) ($json['reference'] ?? ''));
        if ($reference !== '') {
            return $reference;
        }

        $orders = $json['orders'] ?? $json['data']['orders'] ?? null;
        if (is_array($orders) && isset($orders[0]) && is_array($orders[0])) {
            $orderCode = trim((string) ($orders[0]['order_code'] ?? ''));
            if ($orderCode !== '') {
                return $orderCode;
            }
        }

        return '';
    }

    /**
     * @param  array<string, mixed>  $json
     */
    private function extractLineStatus(array $json): ?string
    {
        $orders = $json['orders'] ?? $json['data']['orders'] ?? null;
        if (is_array($orders) && isset($orders[0]) && is_array($orders[0])) {
            $status = $orders[0]['status'] ?? null;

            return is_string($status) && $status !== '' ? strtolower($status) : null;
        }

        $status = $json['status'] ?? null;

        return is_string($status) && $status !== '' ? strtolower($status) : null;
    }

    /**
     * @param  array<string, mixed>  $json
     */
    private function extractPollStatus(array $json): ?string
    {
        $orders = $json['orders'] ?? data_get($json, 'data.orders');
        if (is_array($orders)) {
            foreach ($orders as $line) {
                if (! is_array($line)) {
                    continue;
                }

                $status = $line['status'] ?? null;
                if (is_string($status) && $status !== '') {
                    return strtolower($status);
                }
            }
        }

        $status = $json['status'] ?? data_get($json, 'data.status');
        if (is_string($status) && $status !== '') {
            return strtolower($status);
        }

        return null;
    }

    private function httpErrorMessage(int $code, string $url, string $body): string
    {
        return __('Skanka5 returned HTTP :code for :url. :body', [
            'code' => $code,
            'url' => $url,
            'body' => strlen($body) > 200 ? substr($body, 0, 200).'…' : $body,
        ]);
    }
}
