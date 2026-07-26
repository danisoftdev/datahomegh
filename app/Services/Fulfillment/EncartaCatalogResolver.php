<?php

namespace App\Services\Fulfillment;

use App\Models\BundlePackage;
use App\Models\FulfillmentApiProfile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

final class EncartaCatalogResolver
{
    /**
     * Resolve Encarta catalogue bundle_id for a platform bundle.
     */
    public function resolveBundleId(FulfillmentApiProfile $profile, BundlePackage $bundle): ?int
    {
        $stored = trim((string) ($bundle->provider_bundle_type ?? ''));
        if ($stored !== '' && ctype_digit($stored)) {
            return (int) $stored;
        }

        $capacity = $this->capacityGbFromLabel((string) $bundle->size_label);
        if ($capacity === null) {
            return null;
        }

        $networkCode = $this->networkCodeFor((string) $bundle->network);
        if ($networkCode === null) {
            return null;
        }

        foreach ($this->bundlesForProfile($profile, $networkCode) as $item) {
            if (! is_array($item)) {
                continue;
            }

            if ((int) ($item['capacity_gb'] ?? 0) === $capacity) {
                return isset($item['id']) ? (int) $item['id'] : null;
            }
        }

        return null;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function bundlesForProfile(FulfillmentApiProfile $profile, string $networkCode): array
    {
        $cacheKey = 'encarta.bundles.'.md5($profile->id.'|'.$networkCode);

        /** @var list<array<string, mixed>>|null $cached */
        $cached = Cache::get($cacheKey);
        if (is_array($cached)) {
            return $cached;
        }

        $base = FulfillmentApiProfile::normalizeEncartaBaseUrl((string) $profile->base_url);
        $url = $base.'/bundles?'.http_build_query(['network_code' => $networkCode]);

        try {
            $response = Http::timeout(20)
                ->withHeaders([
                    'X-API-Key' => (string) $profile->api_key,
                    'Accept' => 'application/json',
                ])
                ->get($url);
        } catch (Throwable $e) {
            Log::warning('encarta_catalog_fetch_failed', [
                'profile_id' => $profile->id,
                'message' => $e->getMessage(),
            ]);

            return [];
        }

        if (! $response->successful()) {
            Log::warning('encarta_catalog_fetch_http_error', [
                'profile_id' => $profile->id,
                'status' => $response->status(),
            ]);

            return [];
        }

        $json = $response->json();
        if (! is_array($json) || strtolower((string) ($json['status'] ?? '')) !== 'success') {
            return [];
        }

        $data = $json['data'] ?? [];
        $bundles = is_array($data) ? array_values(array_filter($data, 'is_array')) : [];

        Cache::put($cacheKey, $bundles, now()->addMinutes(10));

        return $bundles;
    }

    public function capacityGbFromLabel(string $sizeLabel): ?int
    {
        if (preg_match('/(\d+)/', $sizeLabel, $matches)) {
            return max(1, (int) $matches[1]);
        }

        return null;
    }

    public function networkCodeFor(string $network): ?string
    {
        return match (strtoupper(trim($network))) {
            'MTN' => 'mtn',
            'TELECEL' => 'telecel',
            'AIRTELTIGO' => 'at_ishare',
            default => null,
        };
    }
}
