<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\FulfillmentApiProfile;
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

        $networks = ['MTN', 'Telecel', 'AirtelTigo'];

        return view('admin.fulfillment-apis.index', [
            'profiles' => $profiles,
            'networks' => $networks,
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
        $data['base_url'] = $this->normalizeAndValidateBaseUrl($data['base_url']);
        if ($data['base_url'] === null) {
            return back()->withErrors([
                'base_url' => $this->consoleUrlNotApiMessage(),
            ])->withInput();
        }
        if (isset($data['default_provider_bundle_type']) && $data['default_provider_bundle_type'] !== null && $data['default_provider_bundle_type'] !== '') {
            $data['default_provider_bundle_type'] = trim((string) $data['default_provider_bundle_type']);
        } else {
            $data['default_provider_bundle_type'] = null;
        }

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
        $baseUrl = $this->normalizeAndValidateBaseUrl($data['base_url']);
        if ($baseUrl === null) {
            return back()->withErrors([
                'base_url' => $this->consoleUrlNotApiMessage(),
            ])->withInput();
        }
        $fulfillmentApiProfile->base_url = $baseUrl;
        $fulfillmentApiProfile->default_provider_bundle_type = filled($data['default_provider_bundle_type'] ?? null)
            ? trim((string) $data['default_provider_bundle_type'])
            : null;
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
     * @return string|null Normalized API host, or null if user entered the web console URL.
     */
    private function normalizeAndValidateBaseUrl(string $baseUrl): ?string
    {
        $normalized = FulfillmentApiProfile::normalizeBaseUrl($baseUrl);
        $host = strtolower((string) parse_url($normalized, PHP_URL_HOST));

        if ($host === 'console.igetghana.com' || str_starts_with($host, 'console.')) {
            return null;
        }

        return $normalized;
    }

    private function consoleUrlNotApiMessage(): string
    {
        return __(':console is the iGet website login, not the API server. Use API base URL :api (from your iGet developer docs), then Activate your profile.', [
            'console' => 'https://console.igetghana.com',
            'api' => 'https://iget.onrender.com',
        ]);
    }

    /**
     * @return array{name: string, network: string, base_url: string, api_key: string, default_provider_bundle_type?: string|null}
     */
    private function validatedCreate(Request $request): array
    {
        return $request->validate([
            'network' => ['required', Rule::in(['MTN', 'Telecel', 'AirtelTigo'])],
            'name' => ['required', 'string', 'max:120'],
            'base_url' => ['required', 'url', 'max:512'],
            'api_key' => ['required', 'string', 'max:2000'],
            'default_provider_bundle_type' => ['nullable', 'string', 'max:120'],
        ]);
    }
}
