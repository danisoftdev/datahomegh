@extends('layouts.buyer')

@section('title', __('New order') . ' — ' . config('app.name'))

@section('content')
    <div class="mx-auto max-w-2xl">
        <h1 class="mb-2 text-2xl font-bold text-white">{{ __('New order') }}</h1>
        <p class="mb-6 text-sm text-slate-400">{{ __('Complete each step to place your order.') }}</p>

        <div
            class="rounded-2xl border border-white/10 bg-[#16213E]/80 p-6 shadow-xl"
            x-data="{
                step: 1,
                network: '',
                phone: '',
                bundleId: null,
                bundles: @json($bundlesJson),
                walletBalance: {{ json_encode((float) $walletBalance) }},
                confirm: false,
                submitting: false,
                init() {
                    const p = new URLSearchParams(window.location.search);
                    if (p.get('network')) this.network = p.get('network');
                    if (p.get('phone_number')) this.phone = p.get('phone_number');
                    const bid = p.get('bundle_package_id');
                    if (bid) this.bundleId = parseInt(bid, 10);
                    if (this.network && this.phone && this.bundleId) this.step = 4;
                    else if (this.network && this.phone) this.step = 3;
                    else if (this.network) this.step = 2;
                },
                filteredBundles() {
                    return this.bundles.filter(b => b.network === this.network);
                },
                selectedBundle() {
                    return this.bundles.find(b => b.id === this.bundleId) || null;
                },
                priceNum() {
                    const b = this.selectedBundle();
                    return b ? parseFloat(b.price) : 0;
                },
                hasBalance() {
                    return this.walletBalance >= this.priceNum();
                },
                canNext1() { return this.network !== ''; },
                canNext2() { return /^0[235][0-9]{8}$/.test(this.phone); },
                canNext3() { return this.bundleId !== null; },
                canSubmit() { return this.confirm && this.hasBalance() && !this.submitting; }
            }"
            x-init="init()"
        >
            <ol class="mb-8 flex items-center justify-between text-xs text-slate-500 sm:text-sm">
                @foreach ([1 => __('Network'), 2 => __('Phone'), 3 => __('Bundle'), 4 => __('Confirm')] as $n => $lbl)
                    <li class="flex flex-1 items-center {{ $n < 4 ? '' : '' }}">
                        <span class="flex size-8 shrink-0 items-center justify-center rounded-full border text-[11px] font-bold sm:size-9 sm:text-xs"
                            :class="step >= {{ $n }} ? 'border-[#FFD700] bg-[#FFD700]/20 text-[#FFD700]' : 'border-white/20'">{{ $n }}</span>
                        <span class="ml-2 hidden min-w-0 truncate sm:inline">{{ $lbl }}</span>
                        @if ($n < 4)
                            <span class="mx-2 h-px flex-1 bg-white/10"></span>
                        @endif
                    </li>
                @endforeach
            </ol>

            <div x-show="step === 1" x-cloak>
                <h2 class="mb-4 text-lg font-semibold text-white">{{ __('Choose network') }}</h2>
                <div class="grid gap-4 sm:grid-cols-3">
                    @foreach (['MTN', 'Telecel', 'AirtelTigo'] as $net)
                        @php
                            $card = match ($net) {
                                'MTN' => 'from-yellow-500/20 to-amber-900/30 border-yellow-500/30 focus:ring-yellow-400/50',
                                'Telecel' => 'from-red-600/20 to-rose-900/30 border-red-500/30 focus:ring-red-400/50',
                                default => 'from-sky-500/20 to-blue-900/30 border-sky-500/30 focus:ring-sky-400/50',
                            };
                        @endphp
                        <button
                            type="button"
                            @click="network = '{{ $net }}'; step = 2"
                            class="rounded-2xl border bg-linear-to-br p-6 text-center text-lg font-bold text-white transition hover:scale-[1.02] focus:outline-none focus:ring-2 {{ $card }}"
                        >
                            {{ $net }}
                        </button>
                    @endforeach
                </div>
            </div>

            <div x-show="step === 2" x-cloak>
                <h2 class="mb-4 text-lg font-semibold text-white">{{ __('Recipient number') }}</h2>
                <div class="flex items-stretch gap-3 rounded-xl border border-white/10 bg-[#1A1A2E] p-3">
                    <span class="flex items-center rounded-lg bg-black/30 px-3 text-2xl" title="{{ __('Ghana') }}">🇬🇭</span>
                    <div class="min-w-0 flex-1">
                        <label class="mb-1 block text-xs text-slate-500" for="phone">{{ __('Phone (10 digits)') }}</label>
                        <input id="phone" type="tel" maxlength="10" x-model="phone" placeholder="0XXXXXXXXX" class="w-full border-0 bg-transparent text-lg text-white placeholder:text-slate-600 focus:ring-0" />
                        <p class="mt-1 text-xs text-slate-500">{{ __('Ghana: 0 + 2, 3, or 5 + 8 digits') }}</p>
                    </div>
                </div>
                <div class="mt-6 flex gap-3">
                    <button type="button" @click="step = 1" class="rounded-lg border border-white/20 px-4 py-2 text-sm text-slate-300 hover:bg-white/5">{{ __('Back') }}</button>
                    <button type="button" @click="step = 3" :disabled="!canNext2()" class="rounded-lg bg-[#FFD700] px-4 py-2 text-sm font-semibold text-[#1A1A2E] disabled:opacity-40">{{ __('Continue') }}</button>
                </div>
            </div>

            <div x-show="step === 3" x-cloak>
                <h2 class="mb-4 text-lg font-semibold text-white">{{ __('Choose bundle') }}</h2>
                <template x-if="filteredBundles().length === 0">
                    <p class="text-sm text-amber-200">{{ __('No bundles for this network. Go back and pick another network.') }}</p>
                </template>
                <div class="grid gap-3 sm:grid-cols-2">
                    <template x-for="b in filteredBundles()" :key="b.id">
                        <button
                            type="button"
                            @click="bundleId = b.id; step = 4"
                            class="rounded-xl border p-4 text-left transition hover:border-[#FFD700]/50"
                            :class="bundleId === b.id ? 'border-[#FFD700] bg-[#FFD700]/10' : 'border-white/10 bg-black/20'"
                        >
                            <p class="font-semibold text-white" x-text="b.name"></p>
                            <p class="text-sm text-slate-400" x-text="b.size_label"></p>
                            <p class="mt-2 text-lg font-bold text-[#FFD700]"><span x-text="parseFloat(b.price).toFixed(2)"></span> GHS</p>
                            <span class="mt-1 inline-block rounded px-2 py-0.5 text-xs text-slate-400" x-text="b.network"></span>
                        </button>
                    </template>
                </div>
                <div class="mt-6 flex gap-3">
                    <button type="button" @click="step = 2" class="rounded-lg border border-white/20 px-4 py-2 text-sm text-slate-300 hover:bg-white/5">{{ __('Back') }}</button>
                </div>
            </div>

            <div x-show="step === 4" x-cloak>
                <h2 class="mb-4 text-lg font-semibold text-white">{{ __('Review & confirm') }}</h2>
                <dl class="space-y-3 rounded-xl border border-white/10 bg-black/20 p-4 text-sm">
                    <div class="flex justify-between"><dt class="text-slate-500">{{ __('Network') }}</dt><dd class="font-medium text-white" x-text="network"></dd></div>
                    <div class="flex justify-between"><dt class="text-slate-500">{{ __('Phone') }}</dt><dd class="font-medium text-white" x-text="phone"></dd></div>
                    <template x-if="selectedBundle()">
                        <div>
                            <div class="flex justify-between"><dt class="text-slate-500">{{ __('Bundle') }}</dt><dd class="text-right font-medium text-white"><span x-text="selectedBundle().name"></span> — <span x-text="selectedBundle().size_label"></span></dd></div>
                            <div class="mt-2 flex justify-between border-t border-white/10 pt-2"><dt class="text-slate-500">{{ __('Price') }}</dt><dd class="text-lg font-bold text-[#FFD700]"><span x-text="priceNum().toFixed(2)"></span> GHS</dd></div>
                        </div>
                    </template>
                    <div class="flex justify-between border-t border-white/10 pt-2"><dt class="text-slate-500">{{ __('Your balance') }}</dt><dd class="font-medium" :class="hasBalance() ? 'text-emerald-400' : 'text-red-400'" x-text="walletBalance.toFixed(2) + ' GHS'"></dd></div>
                </dl>

                <template x-if="!hasBalance() && selectedBundle()">
                    <p class="mt-4 rounded-lg border border-red-500/30 bg-red-500/10 px-3 py-2 text-sm text-red-200">{{ __('Insufficient balance. Add funds before continuing.') }}</p>
                </template>

                <form method="post" action="{{ route('buyer.orders.store') }}" @submit="if (!canSubmit()) { $event.preventDefault(); } else { submitting = true; }" class="mt-6 space-y-6">
                    @csrf
                    <input type="hidden" name="network" :value="network" />
                    <input type="hidden" name="phone_number" :value="phone" />
                    <input type="hidden" name="bundle_package_id" :value="bundleId" />

                    <label class="flex cursor-pointer items-start gap-3 rounded-lg border border-white/10 bg-black/20 p-4">
                        <input type="checkbox" name="confirm" value="1" x-model="confirm" class="mt-1 size-4 rounded border-white/20 text-[#FFD700]" />
                        <span class="text-sm text-slate-300">{{ __('I confirm this order. I understand that charges apply immediately and that data bundle purchases are non-refundable once processing has started, except where required by platform policy.') }}</span>
                    </label>

                    <div class="flex flex-wrap items-center gap-3">
                    <button type="button" @click="step = 3" class="rounded-lg border border-white/20 px-4 py-2 text-sm text-slate-300 hover:bg-white/5">{{ __('Back') }}</button>
                    <button type="submit" :disabled="!canSubmit()" class="inline-flex min-w-40 items-center justify-center gap-2 rounded-lg bg-[#FFD700] px-6 py-3 text-sm font-bold text-[#1A1A2E] disabled:opacity-40">
                        <svg x-show="submitting" class="size-5 animate-spin" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg>
                        <span x-text="submitting ? '{{ __('Placing…') }}' : '{{ __('Place order') }}'"></span>
                    </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
@endsection
