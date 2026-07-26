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
    public function __construct(
        private readonly EncartaCatalogResolver $catalogResolver,
    ) {}

    /**
     * @return array{ok: bool, message: string, reference?: string, status?: string}
     */
    public function place(Order $order, BundlePackage $bundle, FulfillmentApiProfile $profile, string $productCode): array
    {
        $bundleId = $this->resolveBundleId($profile, $bundle, $productCode);
        if ($bundleId === null) {
            return [
                'ok' => false,
                'message' => __('Encarta bundle_id is missing. Set Provider bundle code on this package (Encarta bundle ID from GET /bundles) or match size label to catalogue capacity.'),
            ];
        }

        $base = FulfillmentApiProfile::normalizeEncartaBaseUrl((string) $profile->base_url);
        $placePath = (string) config('datahome.fulfillment.providers.encarta.place_path', '/purchase');
        $url = $base.$placePath;

        $payload = [
            'bundle_id' => $bundleId,
            'recipient' => GhanaPhoneNumber::forLocal((string) $order->phone_number),
            'idempotency_key' => 'dhgh_'.$order->id,
            'webhook_url' => EncartaWebhookService::webhookUrl(),
        ];

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

            return ['ok' => false, 'message' => __('Could not reach Encarta: :msg', ['msg' => $e->getMessage()])];
        }

        if (! $response->successful()) {
            return ['ok' => false, 'message' => $this->httpErrorMessage($response->status(), $url, $response->body())];
        }

        $json = $response->json();
        if (! is_array($json)) {
            return ['ok' => false, 'message' => __('Encarta returned an invalid response.')];
        }

        if ($this->responseIndicatesFailure($json)) {
            $msg = $this->extractErrorMessage($json);

            return ['ok' => false, 'message' => $msg];
        }

        $data = is_array($json['data'] ?? null) ? $json['data'] : [];
        $providerRef = $this->extractReference($data, (string) $order->id);
        $status = $this->extractStatus($data);

        return [
            'ok' => true,
            'message' => __('Order sent to Encarta. Reference: :ref', ['ref' => $providerRef]),
            'reference' => $providerRef,
            'status' => $status ?? 'accepted',
        ];
    }

    /**
     * Encarta v2 documents status via signed webhooks; no public poll endpoint.
     *
     * @return array{ok: bool, message: string, status?: string|null}
     */
    public function refreshStatus(Order $order, FulfillmentApiProfile $profile, string $reference): array
    {
        unset($order, $profile, $reference);

        return [
            'ok' => false,
            'message' => __('Encarta order status updates via webhooks. Register :url in your Encarta API Dashboard.', [
                'url' => EncartaWebhookService::webhookUrl(),
            ]),
        ];
    }

    private function resolveBundleId(FulfillmentApiProfile $profile, BundlePackage $bundle, string $productCode): ?int
    {
        $code = trim($productCode);
        if ($code !== '' && ctype_digit($code)) {
            return (int) $code;
        }

        return $this->catalogResolver->resolveBundleId($profile, $bundle);
    }

    /**
     * @param  array<string, mixed>  $json
     */
    private function responseIndicatesFailure(array $json): bool
    {
        $status = strtolower((string) ($json['status'] ?? ''));
        if ($status === 'error') {
            return true;
        }

        if (array_key_exists('success', $json) && $json['success'] === false) {
            return true;
        }

        $dataStatus = strtolower((string) data_get($json, 'data.status', ''));

        return in_array($dataStatus, ['failed', 'error', 'cancelled'], true);
    }

    /**
     * @param  array<string, mixed>  $json
     */
    private function extractErrorMessage(array $json): string
    {
        $errors = $json['errors'] ?? null;
        if (is_array($errors) && $errors !== []) {
            $first = reset($errors);

            return is_string($first) ? $first : __('Encarta rejected the order.');
        }

        return (string) ($json['message'] ?? __('Encarta did not accept the order.'));
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function extractReference(array $data, string $fallback): string
    {
        $candidates = [
            $data['reference'] ?? null,
            $data['order_id'] ?? null,
            $data['id'] ?? null,
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
     * @param  array<string, mixed>  $data
     */
    private function extractStatus(array $data): ?string
    {
        $status = $data['status'] ?? null;

        return is_string($status) && $status !== '' ? $status : null;
    }

    private function httpErrorMessage(int $code, string $url, string $body): string
    {
        return __('Encarta returned HTTP :code for :url. :body', [
            'code' => $code,
            'url' => $url,
            'body' => strlen($body) > 200 ? substr($body, 0, 200).'…' : $body,
        ]);
    }
}
