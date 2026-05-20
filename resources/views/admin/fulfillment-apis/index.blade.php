@extends('layouts.admin')

@section('title', __('External data APIs'))
@section('heading', __('External data APIs'))

@section('content')
    <p class="mb-6 max-w-2xl text-sm text-slate-400">
        {{ __('Add one or more provider APIs per network. Only one profile can be active per network at a time—use Activate to switch. Orders from agents (their own purchases) and platform buyers are sent to the API automatically. Agent shop buyers are not—those stay with the agent. MTN AFA bundles are never sent to the API.') }}
    </p>
    <p class="mb-6 max-w-2xl rounded-lg border border-amber-500/30 bg-amber-500/5 px-4 py-3 text-sm text-amber-100">
        {{ __('iGet: log in at :console. In DataHome, API base URL must be :api (not the console site). Copy your API key from the iGet developer settings.', [
            'console' => 'https://console.igetghana.com',
            'api' => config('datahome.fulfillment.default_provider_base_url', 'https://iget.onrender.com'),
        ]) }}
    </p>

    <div class="mb-8 max-w-xl rounded-xl border border-white/10 bg-[#16213E]/80 p-6">
        <h2 class="mb-4 text-lg font-semibold text-white">{{ __('Add API profile') }}</h2>
        <form method="post" action="{{ route('admin.fulfillment-apis.store') }}" class="space-y-4">
            @csrf
            <div>
                <label class="mb-1 block text-sm text-slate-400">{{ __('Network') }}</label>
                <select name="network" required class="w-full rounded-lg border border-white/10 bg-[#1A1A2E] px-3 py-2 text-white">
                    @foreach ($networks as $n)
                        <option value="{{ $n }}" @selected(old('network') === $n)>{{ $n }}</option>
                    @endforeach
                </select>
                @error('network')
                    <p class="mt-1 text-sm text-red-400">{{ $message }}</p>
                @enderror
            </div>
            <div>
                <label class="mb-1 block text-sm text-slate-400">{{ __('Label') }}</label>
                <input type="text" name="name" value="{{ old('name') }}" required maxlength="120" placeholder="{{ __('e.g. Primary iGet') }}"
                    class="w-full rounded-lg border border-white/10 bg-[#1A1A2E] px-3 py-2 text-white" />
                @error('name')
                    <p class="mt-1 text-sm text-red-400">{{ $message }}</p>
                @enderror
            </div>
            <div>
                <label class="mb-1 block text-sm text-slate-400">{{ __('Base URL') }}</label>
                <input type="url" name="base_url" value="{{ old('base_url', config('datahome.fulfillment.default_provider_base_url', 'https://iget.onrender.com')) }}" required maxlength="512"
                    class="w-full rounded-lg border border-white/10 bg-[#1A1A2E] px-3 py-2 text-white" />
                <p class="mt-1 text-xs text-slate-500">{{ __('API host only (not console.igetghana.com). DataHome calls: {base}/api/developer/orders/place', ['base' => config('datahome.fulfillment.default_provider_base_url', 'https://iget.onrender.com')]) }}</p>
                @error('base_url')
                    <p class="mt-1 text-sm text-red-400">{{ $message }}</p>
                @enderror
            </div>
            <div>
                <label class="mb-1 block text-sm text-slate-400">{{ __('Default provider bundle code (optional)') }}</label>
                <input type="text" name="default_provider_bundle_type" value="{{ old('default_provider_bundle_type') }}" maxlength="120" placeholder="{{ __('e.g. mtnup2u — used when a bundle has no code') }}"
                    class="w-full rounded-lg border border-white/10 bg-[#1A1A2E] px-3 py-2 text-white" />
                @error('default_provider_bundle_type')
                    <p class="mt-1 text-sm text-red-400">{{ $message }}</p>
                @enderror
            </div>
            <div>
                <label class="mb-1 block text-sm text-slate-400">{{ __('API key') }}</label>
                <input type="password" name="api_key" value="{{ old('api_key') }}" required autocomplete="new-password" maxlength="2000"
                    class="w-full rounded-lg border border-white/10 bg-[#1A1A2E] px-3 py-2 text-white" />
                @error('api_key')
                    <p class="mt-1 text-sm text-red-400">{{ $message }}</p>
                @enderror
            </div>
            <button type="submit" class="rounded-lg bg-[#FFD700] px-6 py-2 font-semibold text-[#1A1A2E]">{{ __('Save profile') }}</button>
        </form>
    </div>

    <div class="space-y-6">
        @forelse ($profiles as $profile)
            <div class="rounded-xl border border-white/10 bg-[#16213E]/80 p-6">
                <div class="mb-4 flex flex-wrap items-start justify-between gap-3">
                    <div>
                        <p class="text-xs uppercase tracking-wide text-slate-500">{{ $profile->network }}</p>
                        <h3 class="text-lg font-semibold text-white">{{ $profile->name }}</h3>
                        <p class="mt-1 font-mono text-xs text-slate-400">{{ $profile->base_url }}</p>
                        @if ($profile->default_provider_bundle_type)
                            <p class="mt-1 text-xs text-slate-500">{{ __('Default bundle code:') }} <span class="font-mono text-slate-300">{{ $profile->default_provider_bundle_type }}</span></p>
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
            <p class="text-sm text-slate-500">{{ __('No API profiles yet. Add one above for each network you want to automate.') }}</p>
        @endforelse
    </div>
@endsection
