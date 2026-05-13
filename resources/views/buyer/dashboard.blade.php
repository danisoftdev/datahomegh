@extends('layouts.buyer')

@section('title', __('Dashboard') . ' — ' . config('app.name'))

@section('content')
    <div class="space-y-8" x-data="{
        bundles: @json($bundlesJson),
        async repeatLast() {
            const res = await fetch(@js(route('buyer.orders.repeat-last')), { headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' } });
            const d = await res.json();
            if (!d.ok) { window.location = @js(route('buyer.orders.create')); return; }
            const p = new URLSearchParams({ network: d.network, phone_number: d.phone_number, bundle_package_id: String(d.bundle_package_id) });
            window.location = @js(route('buyer.orders.create')) + '?' + p.toString();
        }
    }">
        <div class="flex flex-wrap items-end justify-between gap-4">
            <h1 class="text-2xl font-bold text-white">{{ __('Buyer dashboard') }}</h1>
            <div class="flex flex-wrap gap-3">
                <button type="button" @click="repeatLast()" class="rounded-lg border border-white/20 px-4 py-2 text-sm text-slate-200 hover:bg-white/5">{{ __('Repeat last') }}</button>
                <a href="{{ route('buyer.orders.create') }}" class="rounded-lg bg-[#FFD700] px-5 py-2 text-sm font-semibold text-[#1A1A2E] hover:brightness-95">{{ __('New order') }}</a>
            </div>
        </div>

        <div class="rounded-2xl bg-linear-to-br from-[#FFD700] via-amber-400 to-amber-600 p-px shadow-lg shadow-amber-500/20">
            <div class="rounded-2xl bg-[#1A1A2E] p-6 sm:p-8">
                <p class="text-xs font-medium uppercase tracking-wider text-amber-200/90">{{ __('Wallet balance') }}</p>
                <p class="mt-2 text-4xl font-bold tracking-tight text-white sm:text-5xl">{{ number_format((float) $walletBalance, 2) }} <span class="text-xl font-semibold text-slate-400">GHS</span></p>
                <a href="{{ route('wallet.index') }}" class="mt-6 inline-flex items-center justify-center rounded-xl bg-linear-to-r from-[#FFD700] to-amber-500 px-6 py-3 text-sm font-bold text-[#1A1A2E] shadow-md hover:brightness-105">{{ __('Add funds') }}</a>
            </div>
        </div>

        <div>
            <h2 class="mb-4 text-lg font-semibold text-white">{{ __('Recent orders') }}</h2>
            <div class="overflow-hidden rounded-xl border border-white/10 bg-[#16213E]/80">
                <table class="w-full text-left text-sm text-slate-300">
                    <thead class="border-b border-white/10 text-xs uppercase text-slate-500">
                        <tr>
                            <th class="px-4 py-3">#</th>
                            <th class="px-4 py-3">{{ __('Network') }}</th>
                            <th class="px-4 py-3">{{ __('Phone') }}</th>
                            <th class="px-4 py-3">{{ __('Amount') }}</th>
                            <th class="px-4 py-3">{{ __('Status') }}</th>
                            <th class="px-4 py-3"></th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($recentOrders as $order)
                            <tr class="border-b border-white/5">
                                <td class="px-4 py-3 font-mono text-xs">#{{ $order->id }}</td>
                                <td class="px-4 py-3">{{ $order->network }}</td>
                                <td class="px-4 py-3">{{ $order->phone_number }}</td>
                                <td class="px-4 py-3">{{ number_format((float) $order->amount, 2) }}</td>
                                <td class="px-4 py-3"><span class="{{ $order->status_color }}">{{ $order->status }}</span></td>
                                <td class="px-4 py-3"><a href="{{ route('buyer.orders.show', $order) }}" class="text-[#FFD700] hover:underline">{{ __('View') }}</a></td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="px-4 py-8 text-center text-slate-500">{{ __('No orders yet.') }}</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
@endsection
