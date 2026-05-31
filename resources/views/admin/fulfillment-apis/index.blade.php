@extends('layouts.admin')

@section('title', __('External data APIs'))
@section('heading', __('External data APIs'))

@section('content')
    <p class="mb-6 max-w-2xl text-sm text-slate-400">
        {{ __('Configure one active API per network. MTN: Geonettech or Encarta Stores. Telecel: iGet. All buyer and agent orders are sent to the API automatically and appear in Admin → Orders. MTN AFA is never sent to an API.') }}
    </p>

    <div class="mb-6 grid max-w-4xl gap-4 lg:grid-cols-3">
        <p class="rounded-lg border border-amber-500/30 bg-amber-500/5 px-4 py-3 text-sm text-amber-100">
            <strong class="text-amber-200">{{ __('Telecel — iGet') }}</strong><br>
            {{ __('Dashboard: :console. API base: :api. Auth: X-API-Key. Telecel bundleType is automatic (:type).', [
                'console' => 'https://console.igetghana.com',
                'api' => config('datahome.fulfillment.providers.iget.default_base_url'),
                'type' => \App\Support\IgetTelecelBundleType::resolve(),
            ]) }}
        </p>
        <p class="rounded-lg border border-sky-500/30 bg-sky-500/5 px-4 py-3 text-sm text-sky-100">
            <strong class="text-sky-200">{{ __('MTN — Geonettech') }}</strong><br>
            {{ __('Base: :api. Auth: Bearer token. network_key automatic (:key).', [
                'api' => config('datahome.fulfillment.providers.geonet.default_base_url'),
                'key' => \App\Support\GeonetMtnNetworkKey::resolve(),
            ]) }}
        </p>
        <p class="rounded-lg border border-violet-500/30 bg-violet-500/5 px-4 py-3 text-sm text-violet-100">
            <strong class="text-violet-200">{{ __('MTN — Encarta Stores') }}</strong><br>
            {{ __('Base: :api. Auth: X-API-Key from API Management. POST :path · GET :status.', [
                'api' => config('datahome.fulfillment.providers.encarta.default_base_url'),
                'path' => config('datahome.fulfillment.providers.encarta.place_path', '/purchase'),
                'status' => config('datahome.fulfillment.providers.encarta.status_path', '/ishare-status'),
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
            <div id="default_code_wrap">
                <label class="mb-1 block text-sm text-slate-400" id="default_code_label">{{ __('Default product code (optional)') }}</label>
                <input type="text" name="default_provider_bundle_type" id="default_provider_bundle_type" value="{{ old('default_provider_bundle_type') }}" maxlength="120"
                    class="w-full rounded-lg border border-white/10 bg-[#1A1A2E] px-3 py-2 text-white" />
                <p class="mt-1 text-xs text-slate-500" id="default_code_hint"></p>
                @error('default_provider_bundle_type')
                    <p class="mt-1 text-sm text-red-400">{{ $message }}</p>
                @enderror
            </div>
            <p id="provider_auto_key_note" class="hidden rounded-lg border border-white/10 bg-white/5 px-3 py-2 text-xs text-slate-400"></p>
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
            const defaults = @json($fulfillmentProviderDefaults);

            const providerEl = document.getElementById('provider_type');
            const networkEl = document.getElementById('network');
            const baseUrlEl = document.getElementById('base_url');
            const baseHintEl = document.getElementById('base_url_hint');
            const codeWrap = document.getElementById('default_code_wrap');
            const codeEl = document.getElementById('default_provider_bundle_type');
            const codeHintEl = document.getElementById('default_code_hint');
            const codeLabelEl = document.getElementById('default_code_label');
            const providerNote = document.getElementById('provider_auto_key_note');
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
                baseHintEl.textContent = p.baseHint.replace('{base}', baseUrlEl.value || p.baseUrl);
                keyLabelEl.textContent = p.keyLabel;
                codeWrap.classList.add('hidden');
                codeEl.removeAttribute('name');
                providerNote.textContent = p.autoKeyNote || '';
                providerNote.classList.remove('hidden');
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
                        @if ($profile->isGeonet())
                            <p class="mt-1 text-xs text-slate-500">{{ __('MTN API key:') }} <span class="font-mono text-slate-300">{{ __('automatic (:key)', ['key' => \App\Support\GeonetMtnNetworkKey::resolve()]) }}</span></p>
                        @elseif ($profile->isEncarta())
                            <p class="mt-1 text-xs text-slate-500">{{ __('MTN Encarta:') }} <span class="font-mono text-slate-300">{{ config('datahome.fulfillment.providers.encarta.place_path', '/purchase') }}</span> · networkKey <span class="font-mono text-slate-300">{{ \App\Support\EncartaMtnNetwork::resolve() }}</span></p>
                        @elseif ($profile->isIget())
                            <p class="mt-1 text-xs text-slate-500">{{ __('Telecel bundleType:') }} <span class="font-mono text-slate-300">{{ __('automatic (:type)', ['type' => \App\Support\IgetTelecelBundleType::resolve()]) }}</span></p>
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
