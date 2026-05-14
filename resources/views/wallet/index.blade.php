@extends('layouts.app')

@section('nav_variant')
{{ auth()->user()->role?->slug === \App\Models\Role::SLUG_AGENT ? 'agent' : 'buyer' }}
@endsection

@section('title', __('Wallet') . ' — ' . config('app.name'))

@section('content')
    <div class="mx-auto max-w-3xl space-y-8">
        <div class="overflow-hidden rounded-2xl bg-linear-to-br from-accent via-navy to-dark p-px shadow-xl">
            <div class="rounded-2xl bg-dark/90 p-6 sm:p-8">
                <p class="text-xs font-medium uppercase tracking-wider text-primary/90">{{ __('Wallet balance') }}</p>
                <p class="mt-2 text-4xl font-bold tabular-nums tracking-tight text-primary sm:text-5xl">
                    {{ $wallet !== null ? number_format((float) $wallet->balance, 2) : '—' }} <span class="text-xl font-semibold text-slate-400">GHS</span>
                </p>
                @if ($wallet?->is_frozen)
                    <p class="mt-3 inline-flex rounded-full border border-amber-500/40 bg-amber-500/10 px-3 py-1 text-xs font-medium text-amber-200">{{ __('Wallet frozen') }}</p>
                @elseif (auth()->user()->wallet_frozen)
                    <p class="mt-3 inline-flex rounded-full border border-amber-500/40 bg-amber-500/10 px-3 py-1 text-xs font-medium text-amber-200">{{ __('Wallet access restricted') }}</p>
                @endif
                <div class="mt-6 flex flex-wrap gap-3">
                    @if ($wallet !== null && ! $wallet->is_frozen && ! auth()->user()->wallet_frozen)
                        @if (! empty($fundsViaAgent))
                            <div class="w-full rounded-xl border border-amber-500/30 bg-amber-500/10 px-4 py-3 text-sm text-amber-100">
                                <p class="font-semibold text-amber-200">{{ __('Add funds through your agent') }}</p>
                                <p class="mt-2 text-slate-200">{{ __('You are linked to :shop. Paystack top-up is turned off so payments go to your agent, not the platform.', ['shop' => $agentShopName ?? __('your agent')]) }}</p>
                                <ol class="mt-3 list-decimal space-y-1 pl-5 text-slate-200">
                                    <li>{{ __('Send mobile money to your agent’s number below.') }}</li>
                                    <li>{{ __('Use your username as the payment reference:') }} <span class="font-mono font-bold text-white">{{ auth()->user()->username }}</span></li>
                                    <li>{{ __('After they receive the money, they will credit your wallet from their dashboard.') }}</li>
                                </ol>
                                @if (! empty($agentMomoNumber))
                                    <p class="mt-3 text-slate-300">{{ __('Agent MoMo number') }}: <span class="font-mono text-lg font-bold text-white">{{ $agentMomoNumber }}</span></p>
                                @else
                                    <p class="mt-3 text-slate-400">{{ __('Your agent has not published a MoMo number yet. Contact them for payment details.') }}</p>
                                @endif
                            </div>
                        @else
                            <button type="button" onclick="document.getElementById('topup-panel')?.classList.toggle('hidden')" class="rounded-xl bg-primary px-6 py-3 text-sm font-bold text-dark shadow-md hover:brightness-105">
                                {{ __('Add funds') }}
                            </button>
                        @endif
                    @endif
                    <a href="#ledger" class="rounded-xl border border-white/20 px-6 py-3 text-sm font-semibold text-slate-200 hover:bg-white/5">{{ __('View history') }}</a>
                </div>
            </div>
        </div>

        @if ($wallet !== null && ! $wallet->is_frozen && ! auth()->user()->wallet_frozen && empty($fundsViaAgent))
            <div id="topup-panel" class="hidden rounded-2xl border border-white/10 bg-navy/80 p-6 shadow-xl backdrop-blur-sm">
                <h2 class="text-lg font-semibold text-white">{{ __('Top up via Paystack') }}</h2>
                <p class="mt-1 text-sm text-slate-400">{{ __('Amount between 1 and 10,000 GHS.') }}</p>
                <form method="post" action="{{ route('wallet.topup.initialize') }}" class="mt-4 flex flex-wrap items-end gap-3">
                    @csrf
                    <div>
                        <label for="amount" class="sr-only">{{ __('Amount') }}</label>
                        <input id="amount" name="amount" type="number" step="0.01" min="1" max="10000" required
                            class="w-40 rounded-lg border border-white/10 bg-dark px-3 py-2 text-white focus:border-primary/50 focus:outline-none focus:ring-2 focus:ring-primary/30"
                            placeholder="10.00" />
                    </div>
                    <button type="submit" class="rounded-lg bg-primary px-4 py-2 text-sm font-semibold text-dark hover:brightness-95">
                        {{ __('Continue to Paystack') }}
                    </button>
                </form>
            </div>
        @endif

        <div id="ledger" class="scroll-mt-24 rounded-2xl border border-white/10 bg-navy/80 p-6 shadow-xl backdrop-blur-sm">
            <h2 class="text-lg font-semibold text-white">{{ __('Transaction history') }}</h2>
            <ul class="mt-4 divide-y divide-white/5">
                @forelse ($ledger as $row)
                    @php
                        $isCredit = $row->type === 'CREDIT';
                        $isRefund = $row->type === 'REFUND';
                        $iconWrap = $isRefund ? 'bg-purple-400/15 text-purple-400' : ($isCredit ? 'bg-green-400/15 text-green-400' : 'bg-red-400/15 text-red-400');
                    @endphp
                    <li class="flex gap-3 py-3 first:pt-0">
                        <div class="flex size-10 shrink-0 items-center justify-center rounded-full {{ $iconWrap }}">
                            @if ($isRefund)
                                <svg class="size-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/></svg>
                            @elseif ($isCredit)
                                <svg class="size-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 10l7-7m0 0l7 7m-7-7v18"/></svg>
                            @else
                                <svg class="size-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 14l-7 7m0 0l-7-7m7 7V3"/></svg>
                            @endif
                        </div>
                        <div class="min-w-0 flex-1">
                            <div class="flex flex-wrap items-baseline justify-between gap-2">
                                <span class="font-medium text-white">{{ $row->source }}</span>
                                <span class="font-mono text-sm tabular-nums {{ $isRefund ? 'text-purple-400' : ($isCredit ? 'text-green-400' : 'text-red-400') }}">
                                    {{ ($isCredit || $isRefund) ? '+' : '−' }}{{ number_format((float) $row->amount, 2) }} GHS
                                </span>
                            </div>
                            @if ($row->reference)
                                <p class="mt-0.5 truncate font-mono text-xs text-slate-500" title="{{ $row->reference }}">{{ $row->reference }}</p>
                            @endif
                            <p class="mt-1 text-xs text-slate-500">{{ $row->created_at?->diffForHumans() }} · {{ $row->created_at?->timezone(config('app.timezone'))->format('Y-m-d H:i') }}</p>
                        </div>
                    </li>
                @empty
                    <li class="py-8 text-center text-slate-500">{{ __('No transactions yet.') }}</li>
                @endforelse
            </ul>
            <div class="mt-4">
                {{ $ledger->links() }}
            </div>
        </div>
    </div>
@endsection
