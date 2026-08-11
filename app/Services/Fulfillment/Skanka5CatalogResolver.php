<?php

namespace App\Services\Fulfillment;

use App\Models\BundlePackage;
use App\Models\FulfillmentApiProfile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

final class Skanka5CatalogResolver
{
    /**
     * Resolve Skanka5 network_id for a platform network name.
     */
    public function resolveNetworkId(FulfillmentApiProfile $profile, string $network): ?int
    {
        $override = trim((string) ($profile->default_provider_bundle_type ?? ''));
        if ($override !== '' && ctype_digit($override)) {
            return (int) $override;
        }

        $fromConfig = trim((string) config(
            'datahome.fulfillment.fallback_codes.skanka5.'.strtoupper(trim($network)),
            ''
        ));
        if ($fromConfig !== '' && ctype_digit($fromConfig)) {
            return (int) $fromConfig;
        }

        $needle = $this->networkNeedleFor($network);
        if ($needle === null) {
            return null;
        }

        foreach ($this->networksForProfile($profile) as $item) {
            if (! is_array($item)) {
                continue;
            }

            $id = $item['id'] ?? $item['network_id'] ?? null;
            if (! is_numeric($id)) {
                continue;
            }

            $name = strtolower((string) ($item['name'] ?? $item['network'] ?? $item['title'] ?? ''));
            $code = strtolower((string) ($item['code'] ?? ''));

            if ($name !== '' && str_contains($name, $needle)) {
                return (int) $id;
            }

            if ($code !== '' && str_contains($code, $needle)) {
                return (int) $id;
            }
        }

        return null;
    }

    /**
     * Resolve volume_mb for a bundle (2GB → 2000).
     */
    public function resolveVolumeMb(BundlePackage $bundle): ?int
    {
        $stored = trim((string) ($bundle->provider_bundle_type ?? ''));
        if ($stored !== '' && ctype_digit($stored)) {
            return max(1, (int) $stored);
        }

        return $this->volumeMbFromLabel((string) $bundle->size_label);
    }

    public function volumeMbFromLabel(string $sizeLabel): ?int
    {
        if (preg_match('/(\d+)/', $sizeLabel, $matches)) {
            return max(1, (int) $matches[1]) * 1000;
        }

        return null;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function networksForProfile(FulfillmentApiProfile $profile): array
    {
        $cacheKey = 'skanka5.networks.'.md5((string) $profile->id);

        /** @var list<array<string, mixed>>|null $cached */
        $cached = Cache::get($cacheKey);
        if (is_array($cached)) {
            return $cached;
        }

        $base = FulfillmentApiProfile::normalizeSkanka5BaseUrl((string) $profile->base_url);
        $url = $base.'/fetch-networks';

        try {
            $response = Http::timeout(20)
                ->withHeaders([
                    'x-api-key' => (string) $profile->api_key,
                    'Accept' => 'application/json',
                ])
                ->get($url);
        } catch (Throwable $e) {
            Log::warning('skanka5_networks_fetch_failed', [
                'profile_id' => $profile->id,
                'message' => $e->getMessage(),
            ]);

            return [];
        }

        if (! $response->successful()) {
            Log::warning('skanka5_networks_fetch_http_error', [
                'profile_id' => $profile->id,
                'status' => $response->status(),
            ]);

            return [];
        }

        $json = $response->json();
        $networks = $this->extractList($json, ['data', 'networks']);

        Cache::put($cacheKey, $networks, now()->addMinutes(10));

        return $networks;
    }

    private function networkNeedleFor(string $network): ?string
    {
        return match (strtoupper(trim($network))) {
            'MTN' => 'mtn',
            'TELECEL' => 'telecel',
            'AIRTELTIGO' => 'airtel',
            default => null,
        };
    }

    /**
     * @param  array<string, mixed>|null  $json
     * @param  list<string>  $keys
     * @return list<array<string, mixed>>
     */
    private function extractList(?array $json, array $keys): array
    {
        if (! is_array($json)) {
            return [];
        }

        foreach ($keys as $key) {
            $value = $json[$key] ?? null;
            if (is_array($value)) {
                return array_values(array_filter($value, 'is_array'));
            }
        }

        if (array_is_list($json)) {
            return array_values(array_filter($json, 'is_array'));
        }

        return [];
    }
}
