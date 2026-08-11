<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\FulfillmentApiProfile;
use App\Support\FulfillmentProviderType;
use App\Support\GeonetMtnNetworkKey;
use App\Support\IgetTelecelBundleType;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class AdminFulfillmentApiController extends Controller
{
    public function index(Request $request): View
    {
        $supplierId = (int) $request->user()->id;

        $profiles = FulfillmentApiProfile::query()
            ->where('supplier_user_id', $supplierId)
            ->orderBy('network')
            ->orderByDesc('is_active')
            ->orderBy('name')
            ->get();

        return view('admin.fulfillment-apis.index', [
            'profiles' => $profiles,
            'providerTypes' => FulfillmentProviderType::labels(),
            'fulfillmentProviderDefaults' => $this->fulfillmentProviderDefaults(),
        ]);
    }

    public function edit(Request $request, FulfillmentApiProfile $fulfillmentApiProfile): View
    {
        $this->authorizeProfile($request, $fulfillmentApiProfile);

        return view('admin.fulfillment-apis.edit', [
            'profile' => $fulfillmentApiProfile,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validatedCreate($request);
        $data['supplier_user_id'] = (int) $request->user()->id;
        $data['is_active'] = false;
        $data['base_url'] = $this->normalizeBaseUrlForProvider($data['provider_type'], $data['base_url']);
        if ($data['base_url'] === null) {
            return back()->withErrors([
                'base_url' => $this->consoleUrlNotApiMessage(),
            ])->withInput();
        }
        $data['default_provider_bundle_type'] = $this->normalizeDefaultProductCode(
            $data['provider_type'],
            $data['default_provider_bundle_type'] ?? null
        );

        FulfillmentApiProfile::query()->create($data);

        return back()->with('status', __('API profile added. Activate it when you want new orders for that network to use it.'));
    }

    public function update(Request $request, FulfillmentApiProfile $fulfillmentApiProfile): RedirectResponse
    {
        $this->authorizeProfile($request, $fulfillmentApiProfile);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'base_url' => ['required', 'url', 'max:512'],
            'api_key' => ['nullable', 'string', 'max:2000'],
            'default_provider_bundle_type' => ['nullable', 'string', 'max:120'],
        ]);

        if (($data['api_key'] ?? '') !== '') {
            $fulfillmentApiProfile->api_key = $data['api_key'];
        }
        $fulfillmentApiProfile->name = $data['name'];
        $baseUrl = $this->normalizeBaseUrlForProvider($fulfillmentApiProfile->provider_type, $data['base_url']);
        if ($baseUrl === null) {
            return back()->withErrors([
                'base_url' => $this->consoleUrlNotApiMessage(),
            ])->withInput();
        }
        $fulfillmentApiProfile->base_url = $baseUrl;
        $fulfillmentApiProfile->default_provider_bundle_type = $this->normalizeDefaultProductCode(
            $fulfillmentApiProfile->provider_type,
            $data['default_provider_bundle_type'] ?? null
        );
        $fulfillmentApiProfile->save();

        return back()->with('status', __('API profile updated.'));
    }

    public function activate(Request $request, FulfillmentApiProfile $fulfillmentApiProfile): RedirectResponse
    {
        $this->authorizeProfile($request, $fulfillmentApiProfile);

        DB::transaction(function () use ($fulfillmentApiProfile): void {
            FulfillmentApiProfile::query()
                ->where('supplier_user_id', $fulfillmentApiProfile->supplier_user_id)
                ->where('network', $fulfillmentApiProfile->network)
                ->update(['is_active' => false]);

            $fulfillmentApiProfile->update(['is_active' => true]);
        });

        return back()->with('status', __('This API is now active for :network. Others for that network are inactive.', ['network' => $fulfillmentApiProfile->network]));
    }

    public function destroy(Request $request, FulfillmentApiProfile $fulfillmentApiProfile): RedirectResponse
    {
        $this->authorizeProfile($request, $fulfillmentApiProfile);

        $fulfillmentApiProfile->delete();

        return back()->with('status', __('API profile removed.'));
    }

    private function authorizeProfile(Request $request, FulfillmentApiProfile $profile): void
    {
        abort_unless((int) $profile->supplier_user_id === (int) $request->user()->id, 404);
    }

    /**
     * @return string|null Normalized base URL, or null if user entered the iGet web console URL.
     */
    private function normalizeBaseUrlForProvider(string $providerType, string $baseUrl): ?string
    {
        $normalized = match ($providerType) {
            FulfillmentProviderType::GEONET => FulfillmentApiProfile::normalizeGeonetBaseUrl($baseUrl),
            FulfillmentProviderType::ENCARTA => FulfillmentApiProfile::normalizeEncartaBaseUrl($baseUrl),
            FulfillmentProviderType::SKANKA5 => FulfillmentApiProfile::normalizeSkanka5BaseUrl($baseUrl),
            default => FulfillmentApiProfile::normalizeBaseUrl($baseUrl),
        };

        if ($providerType === FulfillmentProviderType::IGET) {
            $host = strtolower((string) parse_url($normalized, PHP_URL_HOST));
            if ($host === 'console.igetghana.com' || str_starts_with($host, 'console.')) {
                return null;
            }
        }

        return $normalized;
    }

    private function consoleUrlNotApiMessage(): string
    {
        return __(':console is the iGet website login, not the API server. Use API base URL :api (from your iGet developer docs), then Activate your profile.', [
            'console' => 'https://console.igetghana.com',
            'api' => FulfillmentApiProfile::defaultBaseUrl(FulfillmentProviderType::IGET),
        ]);
    }

    /**
     * @return array{provider_type: string, name: string, network: string, base_url: string, api_key: string, default_provider_bundle_type?: string|null}
     */
    private function validatedCreate(Request $request): array
    {
        $data = $request->validate([
            'provider_type' => ['required', Rule::in(FulfillmentProviderType::all())],
            'network' => ['required', Rule::in(['MTN', 'Telecel', 'AirtelTigo'])],
            'name' => ['required', 'string', 'max:120'],
            'base_url' => ['required', 'url', 'max:512'],
            'api_key' => ['required', 'string', 'max:2000'],
            'default_provider_bundle_type' => ['nullable', 'string', 'max:120'],
        ]);

        if (! FulfillmentProviderType::allowsNetwork($data['provider_type'], $data['network'])) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'network' => __(':provider only supports :networks.', [
                    'provider' => FulfillmentProviderType::labels()[$data['provider_type']] ?? $data['provider_type'],
                    'networks' => implode(', ', FulfillmentProviderType::networksFor($data['provider_type'])),
                ]),
            ]);
        }

        return $data;
    }

    private function normalizeDefaultProductCode(string $providerType, mixed $submitted): ?string
    {
        if (in_array($providerType, [FulfillmentProviderType::GEONET, FulfillmentProviderType::IGET, FulfillmentProviderType::ENCARTA], true)) {
            return null;
        }

        return filled($submitted) ? trim((string) $submitted) : null;
    }

    /**
     * @return array<string, array{network: string, baseUrl: string, baseHint: string, autoKeyNote: string, keyLabel: string}>
     */
    private function fulfillmentProviderDefaults(): array
    {
        return [
            'iget' => [
                'network' => 'Telecel',
                'networks' => ['Telecel'],
                'baseUrl' => config('datahome.fulfillment.providers.iget.default_base_url'),
                'baseHint' => __('iGet API host only (not console.igetghana.com). Calls: {base}/api/developer/orders/place'),
                'autoKeyNote' => __('Telecel bundleType :type is applied automatically for all Telecel data orders.', [
                    'type' => IgetTelecelBundleType::resolve(),
                ]),
                'keyLabel' => __('iGet API key (X-API-Key)'),
                'showNetworkIdField' => false,
            ],
            'geonet' => [
                'network' => 'MTN',
                'networks' => ['MTN'],
                'baseUrl' => config('datahome.fulfillment.providers.geonet.default_base_url'),
                'baseHint' => __('Geonettech API base. Calls: {base}/v1/place-order'),
                'autoKeyNote' => __('MTN uses Geonettech network_key :key automatically.', [
                    'key' => GeonetMtnNetworkKey::resolve(),
                ]),
                'keyLabel' => __('Geonettech Bearer token'),
                'showNetworkIdField' => false,
            ],
            'encarta' => [
                'network' => 'MTN',
                'networks' => ['MTN'],
                'baseUrl' => config('datahome.fulfillment.providers.encarta.default_base_url'),
                'baseHint' => __('Encarta API base. MTN data uses POST /purchase with bundle_id from GET /bundles.'),
                'autoKeyNote' => __('MTN Encarta: POST /purchase (bundle_id, recipient, idempotency_key, webhook_url). Set FULFILLMENT_ENCARTA_WEBHOOK_SECRET in .env. Status via signed webhooks to :url.', [
                    'url' => \App\Services\Fulfillment\EncartaWebhookService::webhookUrl(),
                ]),
                'keyLabel' => __('Encarta X-API-Key'),
                'showNetworkIdField' => false,
            ],
            'skanka5' => [
                'network' => 'MTN',
                'networks' => ['MTN', 'Telecel', 'AirtelTigo'],
                'baseUrl' => config('datahome.fulfillment.providers.skanka5.default_base_url'),
                'baseHint' => __('Skanka5 API base. POST /orders (single line) + poll GET /orders/{reference}.'),
                'autoKeyNote' => __('Skanka5: network_id from GET /fetch-networks (or optional field below). volume_mb from bundle size (2GB → 2000). Bulk webhooks (5+ lines) optional — set FULFILLMENT_SKANKA5_WEBHOOK_SECRET in .env.'),
                'keyLabel' => __('Skanka5 x-api-key'),
                'showNetworkIdField' => true,
                'networkIdLabel' => __('Skanka5 network_id (optional)'),
                'networkIdHint' => __('Override network ID from Skanka5 GET /fetch-networks. Leave blank to auto-match by network name.'),
            ],
        ];
    }
}
