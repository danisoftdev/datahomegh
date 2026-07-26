@extends('layouts.admin')

@section('title', __('Order') . ' #' . $order->id)
@section('heading', __('Order') . ' #' . $order->id)

@section('content')
    <div class="mb-6 flex flex-wrap gap-4">
        <a href="{{ route('admin.orders.index', request()->query()) }}" class="text-sm text-[#FFD700] hover:underline">← {{ __('Orders') }}</a>
    </div>

    <div class="grid gap-6 lg:grid-cols-3">
        <div class="rounded-xl border border-white/10 bg-[#16213E]/80 p-6 lg:col-span-2">
            <dl class="grid gap-3 text-sm sm:grid-cols-2">
                <div><dt class="text-slate-500">{{ __('Status') }}</dt><dd class="font-medium text-white">{{ $order->status }}</dd></div>
                <div><dt class="text-slate-500">{{ __('Amount') }}</dt><dd class="text-[#FFD700]">{{ number_format((float) $order->amount, 2) }} GHS</dd></div>
                <div><dt class="text-slate-500">{{ __('Payment') }}</dt><dd class="text-white">{{ strtoupper((string) ($order->payment_method ?? 'wallet')) }}</dd></div>
                @if ($order->payment_method === 'paystack' && $order->agent_id)
                    <div><dt class="text-slate-500">{{ __('Agent commission') }}</dt>
                        <dd class="text-white">
                            {{ number_format((float) ($order->agent_commission_amount ?? 0), 2) }} GHS
                            @if ($order->agent_commission_status)
                                <span class="text-xs text-slate-500">({{ $order->agent_commission_status }})</span>
                            @endif
                        </dd>
                    </div>
                @endif
                <div><dt class="text-slate-500">{{ __('Network') }}</dt><dd>{{ $order->bundlePackage?->isMtnAfaRegistration() ? __('MTN AFA') : $order->network }}</dd></div>
                <div><dt class="text-slate-500">{{ __('Phone') }}</dt><dd>{{ $order->phone_number }}</dd></div>
                <div><dt class="text-slate-500">{{ __('Buyer') }}</dt><dd>{{ $order->user?->username }} (#{{ $order->user_id }})</dd></div>
                <div><dt class="text-slate-500">{{ __('Agent') }}</dt><dd>{{ $order->agent?->username ?? '—' }}</dd></div>
                <div class="sm:col-span-2"><dt class="text-slate-500">{{ __('Bundle') }}</dt><dd>{{ $order->bundlePackage?->name }} — {{ $order->bundlePackage?->size_label }}
                    @if ($order->bundlePackage && ! $order->bundlePackage->isMtnAfaRegistration())
                        @if ($order->network === 'MTN')
                            @php($mtnApi = $order->fulfillmentApiProfile ?? \App\Models\FulfillmentApiProfile::activeForNetwork('MTN'))
                            @if ($mtnApi?->isEncarta())
                                <span class="block text-xs text-slate-500">{{ __('Encarta MTN purchase') }} · {{ __('bundle_id from provider code or size label (e.g. 2GB)') }}</span>
                            @elseif ($mtnApi?->isGeonet())
                                <span class="block text-xs text-slate-500">{{ __('Geonettech') }}: {{ __('automatic (:key)', ['key' => \App\Support\GeonetMtnNetworkKey::resolve()]) }}</span>
                            @endif
                        @elseif ($order->network === 'Telecel')
                            <span class="block text-xs text-slate-500">{{ __('iGet') }}: {{ __('automatic (:type)', ['type' => \App\Support\IgetTelecelBundleType::resolve()]) }}</span>
                        @elseif ($order->bundlePackage->provider_bundle_type)
                            <span class="block text-xs text-slate-500">{{ __('Provider code') }}: {{ $order->bundlePackage->provider_bundle_type }}</span>
                        @endif
                    @endif
                </dd></div>
                @if ($order->bundlePackage && ! $order->bundlePackage->isMtnAfaRegistration())
                    <div class="sm:col-span-2 border-t border-white/10 pt-3">
                        <dt class="text-slate-500">{{ __('External provider') }}</dt>
                        <dd class="mt-1 space-y-1 text-sm">
                            @if ($order->provider_dispatch_error)
                                <p class="rounded-lg border border-amber-500/30 bg-amber-500/10 px-3 py-2 text-amber-200">{{ $order->provider_dispatch_error }}</p>
                                <p class="text-xs text-slate-500">{{ __('Provider errors do not cancel the order. Fulfill manually if needed, then mark SENT.') }}</p>
                            @endif
                            @if ($order->provider_order_reference)
                                <p><span class="text-slate-500">{{ __('Reference') }}:</span> <span class="font-mono text-white">{{ $order->provider_order_reference }}</span></p>
                            @endif
                            @if ($order->provider_status)
                                <p><span class="text-slate-500">{{ __('Provider status') }}:</span> <span class="text-[#FFD700]">{{ $order->provider_status }}</span>
                                    @if ($order->provider_status_synced_at)
                                        <span class="text-slate-500">({{ $order->provider_status_synced_at->format('Y-m-d H:i') }})</span>
                                    @endif
                                </p>
                            @endif
                            @if ($order->fulfillmentApiProfile)
                                <p class="text-xs text-slate-500">{{ $order->fulfillmentApiProfile->name }} · {{ $order->fulfillmentApiProfile->base_url }}</p>
                            @elseif (! $order->provider_order_reference)
                                <p class="text-xs text-slate-500">{{ __('Not sent yet — activate an API for :network and ensure a provider bundle code (bundle, API profile default, or .env fallback for that network).', ['network' => $order->network]) }}</p>
                            @endif
                        </dd>
                        <div class="mt-3 flex flex-wrap gap-2">
                            @if (! $order->provider_order_reference)
                                <form method="post" action="{{ route('admin.orders.dispatch-to-provider', $order) }}">
                                    @csrf
                                    <button type="submit" class="rounded-lg bg-[#FFD700] px-4 py-2 text-sm font-semibold text-[#1A1A2E]">{{ __('Send to provider now') }}</button>
                                </form>
                            @endif
                            @if ($order->provider_order_reference && $order->fulfillment_api_profile_id)
                                <form method="post" action="{{ route('admin.orders.refresh-provider-status', $order) }}">
                                    @csrf
                                    <button type="submit" class="rounded-lg border border-white/20 px-4 py-2 text-sm text-white hover:bg-white/5">{{ __('Refresh status from provider') }}</button>
                                </form>
                            @endif
                        </div>
                    </div>
                @endif
            </dl>
            @include('orders.partials.afa-registration', ['order' => $order])
        </div>

        <div class="space-y-4">
            <div class="rounded-xl border border-white/10 bg-[#16213E]/80 p-4">
                <h3 class="mb-3 font-semibold text-white">{{ __('Update status') }}</h3>
                <form method="post" action="{{ route('admin.orders.status', $order) }}">
                    @csrf
                    @method('PATCH')
                    <select name="status" class="mb-2 w-full rounded-lg border border-white/10 bg-[#1A1A2E] px-3 py-2 text-sm text-white">
                        <option value="PROCESSING">PROCESSING</option>
                        <option value="SENT">SENT</option>
                        <option value="FAILED">FAILED</option>
                        <option value="REFUNDED">REFUNDED</option>
                    </select>
                    <textarea name="note" rows="2" placeholder="{{ __('Note') }}" class="mb-2 w-full rounded-lg border border-white/10 bg-[#1A1A2E] px-3 py-2 text-sm text-white"></textarea>
                    <input type="hidden" name="visible_to_buyer" value="0" />
                    <label class="mb-2 flex items-center gap-2 text-xs text-slate-400">
                        <input type="checkbox" name="visible_to_buyer" value="1" checked class="size-3 rounded text-[#FFD700]" />
                        {{ __('Visible to buyer') }}
                    </label>
                    <button class="w-full rounded-lg bg-[#FFD700] py-2 text-sm font-semibold text-[#1A1A2E]">{{ __('Save') }}</button>
                </form>
            </div>
            <div class="rounded-xl border border-white/10 bg-[#16213E]/80 p-4">
                <h3 class="mb-3 font-semibold text-white">{{ __('Add note') }}</h3>
                <form method="post" action="{{ route('admin.orders.notes', $order) }}">
                    @csrf
                    <textarea name="note" required rows="3" class="mb-2 w-full rounded-lg border border-white/10 bg-[#1A1A2E] px-3 py-2 text-sm text-white"></textarea>
                    <input type="hidden" name="visible_to_buyer" value="0" />
                    <label class="mb-2 flex items-center gap-2 text-xs text-slate-400">
                        <input type="checkbox" name="visible_to_buyer" value="1" checked class="size-3 rounded text-[#FFD700]" />
                        {{ __('Visible to buyer') }}
                    </label>
                    <button class="w-full rounded-lg border border-white/20 py-2 text-sm text-white hover:bg-white/5">{{ __('Add note') }}</button>
                </form>
            </div>
        </div>
    </div>

    <div class="mt-8 rounded-xl border border-white/10 bg-[#16213E]/80 p-6 shadow-xl sm:p-8">
        @include('orders.partials.status-history-timeline', [
            'histories' => $histories,
            'heading' => __('Timeline'),
            'emptyMessage' => __('No history yet.'),
        ])
    </div>
@endsection
