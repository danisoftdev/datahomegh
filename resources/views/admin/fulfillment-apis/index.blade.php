@extends('layouts.admin')

@section('title', __('External data APIs'))
@section('heading', __('External data APIs'))

@section('content')
    <p class="mb-6 max-w-2xl text-sm text-slate-400">
        {{ __('Configure one active API per network. MTN uses Geonettech; Telecel uses iGet. Orders from agents (their own purchases) and platform buyers are sent automatically. Agent shop buyers stay with the agent. MTN AFA is never sent to an API.') }}
    </p>

    <div class="mb-6 grid max-w-2xl gap-4 sm:grid-cols-2">
        <p class="rounded-lg border border-amber-500/30 bg-amber-500/5 px-4 py-3 text-sm text-amber-100">
            <strong class="text-amber-200">{{ __('Telecel — iGet') }}</strong><br>
            {{ __('Dashboard: :console. API base: :api. Auth: X-API-Key. Product field: bundleType (e.g. telecelup2u).', [
                'console' => 'https://console.igetghana.com',
                'api' => config('datahome.fulfillment.providers.iget.default_base_url'),
            ]) }}
        </p>
        <p class="rounded-lg border border-sky-500/30 bg-sky-500/5 px-4 py-3 text-sm text-sky-100">
            <strong class="text-sky-200">{{ __('MTN — Geonettech') }}</strong><br>
            {{ __('Base: :api. Auth: Bearer token from dashboard → API Integration. Product field: network_key (e.g. YELLO).', [
                'api' => config('datahome.fulfillment.providers.geonet.default_base_url'),
            ]) }}
        </p>
    </div>

    <div class="mb-8 max-w-xl rounded-xl border border-white/10 bg-[#16213E]/80 p-6">
        <h2 class="mb-4 text-lg font-semibold text-white">{{ __('Add API profile') }}</h2>
        <form method="post" action="{{ route('admin.fulfillment-apis.store') }}" class="space-y-4" id="fulfillment-api-form">
            @csrf
            <div>
                <label class="mb-1 block text-sm text-slate-400">{{ __('Provider') }}</label>
                <select name="provider_type" id="provider_type" required class="w-full rounded-lg border border-white/10 bg-[#1A1A2E] px-3 py-2 text-white">
                    @foreach ($providerTypes as $value => $label)
                        <option value="{{ $value }}" @selected(old('provider_type', \App\Support\FulfillmentProviderType::GEONET) === $value)>{{ $label }}</option>
                    @endforeach
                </select>
                @error('provider_type')
                    <p class="mt-1 text-sm text-red-400">{{ $message }}</p>
                @enderror
            </div>
            <div>
                <label class="mb-1 block text-sm text-slate-400">{{ __('Network') }}</label>
                <select name="network" id="network" required class="w-full rounded-lg border border-white/10 bg-[#1A1A2E] px-3 py-2 text-white">
                    <option value="MTN" @selected(old('network') === 'MTN')>MTN</option>
                    <option value="Telecel" @selected(old('network') === 'Telecel')>Telecel</option>
                </select>
                @error('network')
                    <p class="mt-1 text-sm text-red-400">{{ $message }}</p>
                @enderror
            </div>
            <div>
                <label class="mb-1 block text-sm text-slate-400">{{ __('Label') }}</label>
                <input type="text" name="name" value="{{ old('name') }}" required maxlength="120" placeholder="{{ __('e.g. Primary Geonettech MTN') }}"
                    class="w-full rounded-lg border border-white/10 bg-[#1A1A2E] px-3 py-2 text-white" />
                @error('name')
                    <p class="mt-1 text-sm text-red-400">{{ $message }}</p>
                @enderror
            </div>
            <div>
                <label class="mb-1 block text-sm text-slate-400">{{ __('Base URL') }}</label>
                <input type="url" name="base_url" id="base_url" value="{{ old('base_url') }}" required maxlength="512"
                    class="w-full rounded-lg border border-white/10 bg-[#1A1A2E] px-3 py-2 text-white" />
                <p class="mt-1 text-xs text-slate-500" id="base_url_hint"></p>
                @error('base_url')
                    <p class="mt-1 text-sm text-red-400">{{ $message }}</p>
                @enderror
            </div>
            <div>
                <label class="mb-1 block text-sm text-slate-400" id="default_code_label">{{ __('Default product code (optional)') }}</label>
                <input type="text" name="default_provider_bundle_type" id="default_provider_bundle_type" value="{{ old('default_provider_bundle_type') }}" maxlength="120"
                    class="w-full rounded-lg border border-white/10 bg-[#1A1A2E] px-3 py-2 text-white" />
                <p class="mt-1 text-xs text-slate-500" id="default_code_hint"></p>
                @error('default_provider_bundle_type')
                    <p class="mt-1 text-sm text-red-400">{{ $message }}</p>
                @enderror
            </div>
            <div>
                <label class="mb-1 block text-sm text-slate-400" id="api_key_label">{{ __('API key / token') }}</label>
                <input type="password" name="api_key" value="{{ old('api_key') }}" required autocomplete="new-password" maxlength="2000"
                    class="w-full rounded-lg border border-white/10 bg-[#1A1A2E] px-3 py-2 text-white" />
                @error('api_key')
                    <p class="mt-1 text-sm text-red-400">{{ $message }}</p>
                @enderror
            </div>
            <button type="submit" class="rounded-lg bg-[#FFD700] px-6 py-2 font-semibold text-[#1A1A2E]">{{ __('Save profile') }}</button>
        </form>
    </div>

    <script>
        (function () {
            const defaults = {
                iget: {
                    network: 'Telecel',
                    baseUrl: @json(config('datahome.fulfillment.providers.iget.default_base_url')),
                    codePlaceholder: 'telecelup2u',
                    baseHint: @json(__('iGet API host only (not console.igetghana.com). Calls: {base}/api/developer/orders/place')),
                    codeHint: @json(__('iGet bundleType — used when bundles have no code.')),
                    keyLabel: @json(__('iGet API key (X-API-Key)')),
                },
                geonet: {
                    network: 'MTN',
                    baseUrl: @json(config('datahome.fulfillment.providers.geonet.default_base_url')),
                    codePlaceholder: 'YELLO',
                    baseHint: @json(__('Geonettech API base. Calls: {base}/v1/place-order')),
                    codeHint: @json(__('Geonettech network_key — used when bundles have no code.')),
                    keyLabel: @json(__('Geonettech Bearer token')),
                },
            };

            const providerEl = document.getElementById('provider_type');
            const networkEl = document.getElementById('network');
            const baseUrlEl = document.getElementById('base_url');
            const baseHintEl = document.getElementById('base_url_hint');
            const codeEl = document.getElementById('default_provider_bundle_type');
            const codeHintEl = document.getElementById('default_code_hint');
            const codeLabelEl = document.getElementById('default_code_label');
            const keyLabelEl = document.getElementById('api_key_label');

            function sync() {
                const p = defaults[providerEl.value] || defaults.geonet;
                networkEl.value = p.network;
                networkEl.querySelectorAll('option').forEach((opt) => {
                    opt.hidden = opt.value !== p.network;
                    opt.disabled = opt.value !== p.network;
                });
                if (!baseUrlEl.dataset.touched) {
                    baseUrlEl.value = p.baseUrl;
                }
                codeEl.placeholder = p.codePlaceholder;
                baseHintEl.textContent = p.baseHint.replace('{base}', baseUrlEl.value || p.baseUrl);
                codeHintEl.textContent = p.codeHint;
                codeLabelEl.textContent = providerEl.value === 'geonet'
                    ? @json(__('Default network_key (optional)'))
                    : @json(__('Default bundleType (optional)'));
                keyLabelEl.textContent = p.keyLabel;
            }

            providerEl.addEventListener('change', () => {
                baseUrlEl.dataset.touched = '';
                sync();
            });
            baseUrlEl.addEventListener('input', () => { baseUrlEl.dataset.touched = '1'; });
            sync();
        })();
    </script>

    <div class="space-y-6">
        @forelse ($profiles as $profile)
            <div class="rounded-xl border border-white/10 bg-[#16213E]/80 p-6">
                <div class="mb-4 flex flex-wrap items-start justify-between gap-3">
                    <div>
                        <p class="text-xs uppercase tracking-wide text-slate-500">
                            {{ $profile->network }}
                            · {{ \App\Support\FulfillmentProviderType::labels()[$profile->provider_type] ?? $profile->provider_type }}
                        </p>
                        <h3 class="text-lg font-semibold text-white">{{ $profile->name }}</h3>
                        <p class="mt-1 font-mono text-xs text-slate-400">{{ $profile->base_url }}</p>
                        @if ($profile->default_provider_bundle_type)
                            <p class="mt-1 text-xs text-slate-500">
                                {{ $profile->isGeonet() ? __('Default network_key:') : __('Default bundleType:') }}
                                <span class="font-mono text-slate-300">{{ $profile->default_provider_bundle_type }}</span>
                            </p>
                        @endif
                        @if ($profile->is_active)
                            <span class="mt-2 inline-flex rounded-full bg-emerald-500/20 px-2.5 py-0.5 text-xs font-medium text-emerald-300">{{ __('Active for this network') }}</span>
                        @else
                            <span class="mt-2 inline-flex rounded-full bg-slate-500/20 px-2.5 py-0.5 text-xs font-medium text-slate-400">{{ __('Inactive') }}</span>
                        @endif
                    </div>
                    <div class="flex flex-wrap gap-2">
                        <a href="{{ route('admin.fulfillment-apis.edit', $profile) }}" class="rounded-lg border border-white/20 px-4 py-2 text-sm text-white hover:bg-white/5">{{ __('Edit') }}</a>
                        @if (! $profile->is_active)
                            <form method="post" action="{{ route('admin.fulfillment-apis.activate', $profile) }}">
                                @csrf
                                <button type="submit" class="rounded-lg bg-emerald-600 px-4 py-2 text-sm font-medium text-white hover:bg-emerald-500">{{ __('Activate') }}</button>
                            </form>
                        @endif
                        <form method="post" action="{{ route('admin.fulfillment-apis.destroy', $profile) }}" onsubmit="return confirm(@json(__('Delete this API profile?')))">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="rounded-lg border border-red-500/50 px-4 py-2 text-sm text-red-300 hover:bg-red-500/10">{{ __('Delete') }}</button>
                        </form>
                    </div>
                </div>
            </div>
        @empty
            <p class="text-sm text-slate-500">{{ __('No API profiles yet. Add Geonettech for MTN and iGet for Telecel, then Activate each.') }}</p>
        @endforelse
    </div>
@endsection
