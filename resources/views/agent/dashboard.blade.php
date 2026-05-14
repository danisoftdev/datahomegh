@extends('layouts.agent')

@section('title', __('Dashboard') . ' — ' . config('app.name'))
@section('heading', __('Dashboard'))

@section('content')
    <h1 class="mb-6 text-2xl font-bold text-white">{{ __('Agent dashboard') }}</h1>

    @if ($shopLink)
        <div
            class="mb-8 rounded-xl border border-emerald-500/30 bg-[#16213E]/80 p-6"
            x-data="{
                copied: false,
                link: @js($shopLink),
                async copyLink() {
                    try {
                        await navigator.clipboard.writeText(this.link);
                        this.copied = true;
                        setTimeout(() => { this.copied = false }, 2000);
                    } catch (e) {}
                },
                waUrl() {
                    const prefix = @json(__('My shop'));
                    return 'https://wa.me/?text=' + encodeURIComponent(prefix + ': ' + this.link);
                }
            }"
        >
            <h2 class="text-sm font-medium text-slate-400">{{ __('Your shop link') }}</h2>
            <p class="mt-2 break-all rounded-lg bg-black/30 px-3 py-2 font-mono text-sm text-emerald-300" x-ref="linkText">{{ $shopLink }}</p>
            <div class="mt-4 flex flex-wrap gap-3">
                <button type="button" @click="copyLink()" class="rounded-lg bg-emerald-500 px-4 py-2 text-sm font-semibold text-[#0f0f1a] hover:bg-emerald-400">
                    <span x-show="!copied">{{ __('Copy link') }}</span>
                    <span x-show="copied" x-cloak>{{ __('Copied!') }}</span>
                </button>
                <a :href="waUrl()" target="_blank" rel="noopener noreferrer" class="rounded-lg border border-white/20 px-4 py-2 text-sm text-white hover:bg-white/5">{{ __('Share on WhatsApp') }}</a>
            </div>
        </div>
    @else
        <div class="mb-8 rounded-xl border border-amber-500/30 bg-amber-500/10 px-4 py-3 text-sm text-amber-100">
            {{ __('Your shop link will appear here once an administrator approves your account and assigns a shop slug.') }}
        </div>
    @endif

    @if ($platformSupportContact ?? null)
        <div class="mb-8">
            <x-dashboard-contact-card :contact="$platformSupportContact" :heading="__('Platform support')" variant="agent" />
        </div>
    @endif

    <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
        <div class="rounded-xl border border-white/10 bg-[#16213E]/80 p-5">
            <p class="text-xs uppercase tracking-wide text-slate-500">{{ __("Today's orders") }}</p>
            <p class="mt-2 text-3xl font-bold text-white">{{ $todayOrdersCount }}</p>
        </div>
        <div class="rounded-xl border border-white/10 bg-[#16213E]/80 p-5">
            <p class="text-xs uppercase tracking-wide text-slate-500">{{ __('Wallet balance') }}</p>
            <p class="mt-2 text-3xl font-bold text-emerald-400">{{ number_format((float) $walletBalance, 2) }} <span class="text-lg font-normal text-slate-400">GHS</span></p>
        </div>
        <div class="rounded-xl border border-white/10 bg-[#16213E]/80 p-5">
            <p class="text-xs uppercase tracking-wide text-slate-500">{{ __('Pending / processing') }}</p>
            <p class="mt-2 text-3xl font-bold text-amber-300">{{ $pendingCount }}</p>
        </div>
        <div class="rounded-xl border border-white/10 bg-[#16213E]/80 p-5">
            <p class="text-xs uppercase tracking-wide text-slate-500">{{ __('Quick links') }}</p>
            <div class="mt-3 flex flex-col gap-2 text-sm">
                <a href="{{ route('agent.orders.create') }}" class="text-emerald-400 hover:underline">{{ __('New order') }}</a>
                <a href="{{ route('agent.orders.index') }}" class="text-emerald-400 hover:underline">{{ __('My orders') }}</a>
                <a href="{{ route('wallet.index') }}" class="text-emerald-400 hover:underline">{{ __('Wallet') }}</a>
            </div>
        </div>
    </div>
@endsection
