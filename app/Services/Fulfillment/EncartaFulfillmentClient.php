<?php

namespace App\Services\Fulfillment;

use App\Models\BundlePackage;
use App\Models\FulfillmentApiProfile;
use App\Models\Order;
use App\Support\GhanaPhoneNumber;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

final class EncartaFulfillmentClient
{
    /**
     * @return array{ok: bool, message: string}
     */
    public function place(Order $order, BundlePackage $bundle, FulfillmentApiProfile $profile, string $network): array
    {
        $base = FulfillmentApiProfile::normalizeEncartaBaseUrl((string) $profile->base_url);
        $placePath = (string) config('datahome.fulfillment.providers.encarta.place_path', '/ishare');
        $url = $base.$placePath;
        $ref = (string) $order->id;
        $volumeGb = $this->capacityGbForBundle($bundle);

        $payload = [
            'recipient' => GhanaPhoneNumber::forLocal((string) $order->phone_number),
            'volume' => $volumeGb,
            'reference' => $ref,
        ];

        if ($placePath === '/purchase' || str_ends_with($placePath, '/purchase')) {
            $payload['network'] = strtoupper($network);
        }

        try {
            $response = Http::timeout(25)
                ->withHeaders([
                    'X-API-Key' => (string) $profile->api_key,
                    'Accept' => 'application/json',
                ])
                ->asJson()
                ->post($url, $payload);
        } catch (Throwable $e) {
            Log::warning('encarta_fulfillment_place_request_failed', [
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
            return ['ok' => false, 'message' => __('Encarta returned an invalid response.')];
        }

        if ($this->responseIndicatesFailure($json)) {
            $msg = (string) ($json['message'] ?? __('Encarta did not accept the order.'));

            return ['ok' => false, 'message' => $msg];
        }

        $providerRef = $this->extractReference($json, $ref);
        $status = $this->extractStatus($json);

        return [
            'ok' => true,
            'message' => __('Order sent to Encarta. Reference: :ref', ['ref' => $providerRef]),
            'reference' => $providerRef,
            'status' => $status ?? 'pending',
        ];
    }

    /**
     * @return array{ok: bool, message: string}
     */
    public function refreshStatus(Order $order, FulfillmentApiProfile $profile, string $reference): array
    {
        $base = FulfillmentApiProfile::normalizeEncartaBaseUrl((string) $profile->base_url);
        $statusPath = (string) config('datahome.fulfillment.providers.encarta.status_path', '/ishare-status');
        $url = $base.$statusPath.'?'.http_build_query(['reference' => $reference]);

        try {
            $response = Http::timeout(25)
                ->withHeaders([
                    'X-API-Key' => (string) $profile->api_key,
                    'Accept' => 'application/json',
                ])
                ->get($url);
        } catch (Throwable $e) {
            Log::warning('encarta_fulfillment_status_request_failed', [
                'order_id' => $order->id,
                'message' => $e->getMessage(),
            ]);

            return ['ok' => false, 'message' => __('Could not reach provider: :msg', ['msg' => $e->getMessage()])];
        }

        if (! $response->successful()) {
            return ['ok' => false, 'message' => __('Provider returned HTTP :code.', ['code' => $response->status()])];
        }

        $json = $response->json();
        if (! is_array($json) || $this->responseIndicatesFailure($json)) {
            return ['ok' => false, 'message' => __('Provider did not confirm success.')];
        }

        $status = $this->extractStatus($json);

        return [
            'ok' => true,
            'message' => __('Provider status updated.'),
            'status' => is_string($status) ? $status : null,
        ];
    }

    /**
     * @param  array<string, mixed>  $json
     */
    private function responseIndicatesFailure(array $json): bool
    {
        if (array_key_exists('success', $json) && $json['success'] === false) {
            return true;
        }

        $status = strtolower((string) ($json['status'] ?? data_get($json, 'data.status', '')));

        return in_array($status, ['failed', 'error', 'cancelled'], true);
    }

    /**
     * @param  array<string, mixed>  $json
     */
    private function extractReference(array $json, string $fallback): string
    {
        $candidates = [
            data_get($json, 'data.reference'),
            data_get($json, 'data.transaction_id'),
            data_get($json, 'data.id'),
            data_get($json, 'reference'),
            data_get($json, 'transaction_id'),
        ];

        foreach ($candidates as $candidate) {
            if (is_string($candidate) && $candidate !== '') {
                return $candidate;
            }
            if (is_int($candidate)) {
                return (string) $candidate;
            }
        }

        return $fallback;
    }

    /**
     * @param  array<string, mixed>  $json
     */
    private function extractStatus(array $json): ?string
    {
        $status = data_get($json, 'data.status') ?? data_get($json, 'data.order.status') ?? ($json['status'] ?? null);

        return is_string($status) && $status !== '' ? $status : null;
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
