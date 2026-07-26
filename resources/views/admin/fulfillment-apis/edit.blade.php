@extends('layouts.admin')

@section('title', __('Edit API profile'))
@section('heading', __('Edit API profile'))

@section('content')
    <div class="mb-6">
        <a href="{{ route('admin.fulfillment-apis.index') }}" class="text-sm text-[#FFD700] hover:underline">← {{ __('External data APIs') }}</a>
    </div>

    <div class="max-w-xl rounded-xl border border-white/10 bg-[#16213E]/80 p-6">
        <p class="mb-4 text-sm text-slate-400">
            {{ __('Network: :n · Provider: :p (cannot be changed here)', [
                'n' => $profile->network,
                'p' => \App\Support\FulfillmentProviderType::labels()[$profile->provider_type] ?? $profile->provider_type,
            ]) }}
        </p>

        <form method="post" action="{{ route('admin.fulfillment-apis.update', $profile) }}" class="space-y-4">
            @csrf
            @method('PATCH')
            <div>
                <label class="mb-1 block text-sm text-slate-400">{{ __('Label') }}</label>
                <input type="text" name="name" value="{{ old('name', $profile->name) }}" required maxlength="120" class="w-full rounded-lg border border-white/10 bg-[#1A1A2E] px-3 py-2 text-white" />
                @error('name')
                    <p class="mt-1 text-sm text-red-400">{{ $message }}</p>
                @enderror
            </div>
            <div>
                <label class="mb-1 block text-sm text-slate-400">{{ __('Base URL') }}</label>
                <input type="url" name="base_url" value="{{ old('base_url', $profile->base_url) }}" required maxlength="512" class="w-full rounded-lg border border-white/10 bg-[#1A1A2E] px-3 py-2 text-white" />
                <p class="mt-1 text-xs text-slate-500">
                    @if ($profile->isGeonet())
                        {{ __('Geonettech base, e.g. :api', ['api' => config('datahome.fulfillment.providers.geonet.default_base_url')]) }}
                    @elseif ($profile->isEncarta())
                        {{ __('Encarta base, e.g. :api', ['api' => config('datahome.fulfillment.providers.encarta.default_base_url')]) }}
                    @else
                        {{ __('iGet API host only — not console.igetghana.com. Example: :api', ['api' => config('datahome.fulfillment.providers.iget.default_base_url')]) }}
                    @endif
                </p>
                @error('base_url')
                    <p class="mt-1 text-sm text-red-400">{{ $message }}</p>
                @enderror
            </div>
            @if ($profile->isGeonet())
                <p class="rounded-lg border border-white/10 bg-white/5 px-3 py-2 text-xs text-slate-400">
                    {{ __('MTN Geonettech network_key (:key) is applied automatically for all MTN data orders.', ['key' => \App\Support\GeonetMtnNetworkKey::resolve()]) }}
                </p>
            @elseif ($profile->isEncarta())
                <p class="rounded-lg border border-white/10 bg-white/5 px-3 py-2 text-xs text-slate-400">
                    {{ __('MTN Encarta uses POST :path with bundle_id, recipient, idempotency_key, and webhook_url. Set FULFILLMENT_ENCARTA_WEBHOOK_SECRET in .env. Status updates via signed webhooks to :url.', [
                        'path' => config('datahome.fulfillment.providers.encarta.place_path', '/purchase'),
                        'url' => \App\Services\Fulfillment\EncartaWebhookService::webhookUrl(),
                    ]) }}
                </p>
            @else
                <p class="rounded-lg border border-white/10 bg-white/5 px-3 py-2 text-xs text-slate-400">
                    {{ __('Telecel iGet bundleType (:type) is applied automatically for all Telecel data orders.', ['type' => \App\Support\IgetTelecelBundleType::resolve()]) }}
                </p>
            @endif
            <div>
                <label class="mb-1 block text-sm text-slate-400">
                    @if ($profile->isGeonet())
                        {{ __('New Bearer token (leave blank to keep current)') }}
                    @else
                        {{ __('New API key (leave blank to keep current)') }}
                    @endif
                </label>
                <input type="password" name="api_key" value="" autocomplete="new-password" maxlength="2000" class="w-full rounded-lg border border-white/10 bg-[#1A1A2E] px-3 py-2 text-white" />
                @error('api_key')
                    <p class="mt-1 text-sm text-red-400">{{ $message }}</p>
                @enderror
            </div>
            <button type="submit" class="rounded-lg bg-[#FFD700] px-6 py-2 font-semibold text-[#1A1A2E]">{{ __('Save') }}</button>
        </form>
    </div>
@endsection
