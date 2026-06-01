@extends('layouts.buyer')

@section('title', __('New order') . ' — ' . config('app.name'))

@section('content')
    <div class="mx-auto max-w-3xl">
        <h1 class="mb-2 text-2xl font-bold text-white">{{ __('New order') }}</h1>
        <p class="mb-6 text-sm text-slate-400">{{ __('Add one or more bundles (different networks are fine). Each line needs its own recipient number. You pay once for the total.') }}</p>

        {{-- JSON must not sit inside x-data="..." or unescaped " from @json() ends the HTML attribute and the cart renders as text. --}}
        <script type="application/json" id="buyer-order-cart-bundles">@json($bundlesJson)</script>
        <script type="application/json" id="buyer-order-cart-old-items">@json(old('items'))</script>

        <div
            class="rounded-2xl border border-white/10 bg-[#16213E]/80 p-6 shadow-xl"
            x-data="{
                bundles: JSON.parse(document.getElementById('buyer-order-cart-bundles').textContent),
                walletBalance: {{ json_encode((float) $walletBalance) }},
                isAgentShopBuyer: @json($isAgentShopBuyer),
                requiresPaystack: @json($requiresPaystack),
                canUseWallet: @json($canUseWallet),
                walletCutoff: {{ json_encode((float) $walletCutoff) }},
                paymentMethod: 'wallet',
                rows: [],
                confirm: false,
                submitting: false,
                rowKey() { return 'k' + Date.now() + '_' + Math.random().toString(36).slice(2); },
                emptyRow() {
                    return {
                        _key: this.rowKey(),
                        network: '',
                        phone: '',
                        bundleId: null,
                        reg: { name: '', phone: '', ghana_card_number: '', date_of_birth: '', occupation: '', location: '' },
                    };
                },
                init() {
                    let oldItems = null;
                    try {
                        oldItems = JSON.parse(document.getElementById('buyer-order-cart-old-items').textContent);
                    } catch (e) {}
                    if (Array.isArray(oldItems) && oldItems.length > 0) {
                        this.rows = oldItems.map((it) => ({
                            _key: this.rowKey(),
                            network: String(it.network || ''),
                            phone: String(it.phone_number || ''),
                            bundleId: it.bundle_package_id != null ? parseInt(it.bundle_package_id, 10) : null,
                            reg: {
                                name: String(it.afa_registration?.name || ''),
                                phone: String(it.afa_registration?.phone || it.phone_number || ''),
                                ghana_card_number: String(it.afa_registration?.ghana_card_number || ''),
                                date_of_birth: String(it.afa_registration?.date_of_birth || ''),
                                occupation: String(it.afa_registration?.occupation || ''),
                                location: String(it.afa_registration?.location || ''),
                            },
                        }));
                    } else {
                        const row = this.emptyRow();
                        const p = new URLSearchParams(window.location.search);
                        const bid = p.get('bundle_package_id');
                        if (bid) row.bundleId = parseInt(bid, 10);
                        const b = row.bundleId ? this.bundles.find(x => x.id === row.bundleId) : null;
                        if (p.get('network')) {
                            row.network = p.get('network');
                        } else if (b) {
                            row.network = (b.package_kind || 'data') === 'mtn_afa' ? 'MTN_AFA' : b.network;
                        }
                        if (p.get('phone_number')) row.phone = p.get('phone_number');
                        const preBundle = row.bundleId ? this.bundles.find((x) => x.id === row.bundleId) : null;
                        if (preBundle && (preBundle.package_kind || 'data') === 'mtn_afa' && row.phone) {
                            row.reg.phone = row.phone;
                        }
                        this.rows = [row];
                    }
                },
                addRow() {
                    if (this.rows.length >= 30) return;
                    this.rows.push(this.emptyRow());
                },
                removeRow(i) {
                    if (this.rows.length < 2) return;
                    this.rows.splice(i, 1);
                },
                filteredBundles(row) {
                    if (row.network === 'MTN_AFA') {
                        return this.bundles.filter(b => (b.package_kind || 'data') === 'mtn_afa');
                    }
                    if (!row.network) return [];
                    return this.bundles.filter(b => b.network === row.network && (b.package_kind || 'data') !== 'mtn_afa');
                },
                selectedBundle(row) {
                    return row.bundleId ? this.bundles.find(b => b.id === row.bundleId) || null : null;
                },
                needsAfa(row) {
                    const b = this.selectedBundle(row);
                    return b && (b.package_kind || 'data') === 'mtn_afa';
                },
                rowPrice(row) {
                    const b = this.selectedBundle(row);
                    return b ? parseFloat(b.price) : 0;
                },
                totalPrice() {
                    return this.rows.reduce((s, r) => s + this.rowPrice(r), 0);
                },
                hasBalance() {
                    return this.walletBalance >= this.totalPrice() && this.totalPrice() > 0;
                },
                canPayWithWallet() {
                    return !this.requiresPaystack && this.canUseWallet && this.hasBalance();
                },
                canPayWithPaystack() {
                    return this.isAgentShopBuyer && this.totalPrice() > 0 && this.allRowsValid();
                },
                phoneOk(p) {
                    return /^0[235][0-9]{8}$/.test(String(p || ''));
                },
                rowValid(row) {
                    if (!row.network || !this.phoneOk(row.phone) || row.bundleId === null) return false;
                    const b = this.selectedBundle(row);
                    if (!b) return false;
                    if (!this.needsAfa(row)) return true;
                    const r = row.reg;
                    return !!(String(r.name || '').trim() && this.phoneOk(r.phone)
                        && String(r.ghana_card_number || '').trim()
                        && String(r.date_of_birth || '').trim() && String(r.occupation || '').trim()
                        && String(r.location || '').trim());
                },
                allRowsValid() {
                    return this.rows.length > 0 && this.rows.every((r) => this.rowValid(r));
                },
                canSubmitWallet() {
                    return this.confirm && this.canPayWithWallet() && this.allRowsValid() && !this.submitting;
                },
                canSubmitPaystack() {
                    return this.confirm && this.canPayWithPaystack() && !this.submitting;
                },
            }"
            x-init="init()"
        >
            @if ($errors->any())
                <div class="mb-6 rounded-xl border border-red-500/40 bg-red-500/10 px-4 py-3 text-sm text-red-100">
                    <ul class="list-inside list-disc space-y-1">
                        @foreach ($errors->all() as $err)
                            <li>{{ $err }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            <form method="post" action="{{ route('buyer.orders.store') }}" class="space-y-8">
                @csrf
                <input type="hidden" name="payment_method" :value="paymentMethod" />

                <div class="space-y-6">
                    <div class="flex flex-wrap items-center justify-between gap-3">
                        <h2 class="text-lg font-semibold text-white">{{ __('Order lines') }}</h2>
                        <button type="button" @click="addRow()" :disabled="rows.length >= 30" class="rounded-lg border border-[#FFD700]/50 px-3 py-1.5 text-sm font-medium text-[#FFD700] hover:bg-[#FFD700]/10 disabled:opacity-40">
                            {{ __('Add another bundle') }}
                        </button>
                    </div>

                    <template x-for="(row, idx) in rows" :key="row._key">
                        <div class="rounded-xl border border-white/10 bg-black/25 p-4">
                            <div class="mb-3 flex items-center justify-between gap-2">
                                <span class="text-xs font-semibold uppercase tracking-wide text-slate-500" x-text="'{{ __('Line') }} ' + (idx + 1)"></span>
                                <button type="button" x-show="rows.length > 1" @click="removeRow(idx)" class="text-xs text-red-400 hover:underline">{{ __('Remove') }}</button>
                            </div>

                            <input type="hidden" :name="'items[' + idx + '][network]'" :value="row.network" />

                            <div class="grid gap-4 sm:grid-cols-2">
                                <div class="sm:col-span-2">
                                    <label class="mb-1 block text-xs text-slate-400">{{ __('Network') }}</label>
                                    <select x-model="row.network" @change="row.bundleId = null" class="w-full rounded-lg border border-white/10 bg-[#1A1A2E] px-3 py-2 text-sm text-white">
                                        <option value="">{{ __('Select…') }}</option>
                                        @foreach ($networks as $net)
                                            <option value="{{ $net }}">{{ $networkLabels[$net] ?? $net }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="sm:col-span-2">
                                    <label class="mb-1 block text-xs text-slate-400">{{ __('Recipient number') }}</label>
                                    <div class="flex items-stretch gap-2 rounded-lg border border-white/10 bg-[#1A1A2E] p-2">
                                        <span class="flex items-center px-2 text-lg" title="{{ __('Ghana') }}">🇬🇭</span>
                                        <input type="tel" maxlength="10" x-model="row.phone" :name="'items[' + idx + '][phone_number]'" placeholder="0XXXXXXXXX" class="min-w-0 flex-1 border-0 bg-transparent text-white placeholder:text-slate-600 focus:ring-0" />
                                    </div>
                                    <p class="mt-1 text-xs text-slate-500">{{ __('Ghana: 0 + 2, 3, or 5 + 8 digits') }}</p>
                                </div>
                                <div class="sm:col-span-2">
                                    <label class="mb-1 block text-xs text-slate-400">{{ __('Bundle') }}</label>
                                    <select :name="'items[' + idx + '][bundle_package_id]'" x-model.number="row.bundleId" @change="if (needsAfa(row) && !String(row.reg.phone || '').trim()) { row.reg.phone = row.phone; }" :disabled="!row.network" class="w-full rounded-lg border border-white/10 bg-[#1A1A2E] px-3 py-2 text-sm text-white disabled:opacity-40">
                                        <option :value="null">{{ __('Select bundle…') }}</option>
                                        <template x-for="b in filteredBundles(row)" :key="b.id">
                                            <option :value="b.id" x-text="b.name + ' — ' + b.size_label + ' (' + parseFloat(b.price).toFixed(2) + ' GHS)'"></option>
                                        </template>
                                    </select>
                                    <p class="mt-1 text-xs text-amber-200" x-show="row.network && filteredBundles(row).length === 0">{{ __('No bundles for this network.') }}</p>
                                </div>
                            </div>

                            <template x-if="needsAfa(row)">
                                <div class="mt-4 space-y-3 rounded-lg border border-amber-500/30 bg-amber-500/5 p-4">
                                    <h3 class="text-sm font-semibold text-amber-200">{{ __('MTN AFA registration (this line)') }}</h3>
                                    <p class="text-xs text-slate-400">{{ __('Complete every field below before you pay. These details are required for the registration bundle.') }}</p>
                                    <div class="grid gap-3 sm:grid-cols-2">
                                        <div class="sm:col-span-2">
                                            <label class="mb-1 block text-xs text-slate-400">{{ __('Name') }}</label>
                                            <input type="text" :name="'items[' + idx + '][afa_registration][name]'" x-model="row.reg.name" autocomplete="name" class="w-full rounded-lg border border-white/10 bg-[#1A1A2E] px-3 py-2 text-sm text-white" />
                                        </div>
                                        <div class="sm:col-span-2">
                                            <label class="mb-1 block text-xs text-slate-400">{{ __('Number') }}</label>
                                            <div class="flex items-stretch gap-2 rounded-lg border border-white/10 bg-[#1A1A2E] p-2">
                                                <span class="flex items-center px-2 text-lg" title="{{ __('Ghana') }}">🇬🇭</span>
                                                <input type="tel" maxlength="10" :name="'items[' + idx + '][afa_registration][phone]'" x-model="row.reg.phone" placeholder="0XXXXXXXXX" class="min-w-0 flex-1 border-0 bg-transparent text-sm text-white placeholder:text-slate-600 focus:ring-0" />
                                            </div>
                                            <p class="mt-1 text-xs text-slate-500">{{ __('Ghana: 0 + 2, 3, or 5 + 8 digits') }}</p>
                                        </div>
                                        <div class="sm:col-span-2">
                                            <label class="mb-1 block text-xs text-slate-400">{{ __('Ghana Card number') }}</label>
                                            <input type="text" :name="'items[' + idx + '][afa_registration][ghana_card_number]'" x-model="row.reg.ghana_card_number" autocomplete="off" class="w-full rounded-lg border border-white/10 bg-[#1A1A2E] px-3 py-2 text-sm text-white" />
                                        </div>
                                        <div class="sm:col-span-2">
                                            <label class="mb-1 block text-xs text-slate-400">{{ __('Date of birth') }}</label>
                                            <input type="date" :name="'items[' + idx + '][afa_registration][date_of_birth]'" x-model="row.reg.date_of_birth" class="w-full rounded-lg border border-white/10 bg-[#1A1A2E] px-3 py-2 text-sm text-white" />
                                        </div>
                                        <div class="sm:col-span-2">
                                            <label class="mb-1 block text-xs text-slate-400">{{ __('Occupation') }}</label>
                                            <input type="text" :name="'items[' + idx + '][afa_registration][occupation]'" x-model="row.reg.occupation" class="w-full rounded-lg border border-white/10 bg-[#1A1A2E] px-3 py-2 text-sm text-white" />
                                        </div>
                                        <div class="sm:col-span-2">
                                            <label class="mb-1 block text-xs text-slate-400">{{ __('Location') }}</label>
                                            <input type="text" :name="'items[' + idx + '][afa_registration][location]'" x-model="row.reg.location" class="w-full rounded-lg border border-white/10 bg-[#1A1A2E] px-3 py-2 text-sm text-white" />
                                        </div>
                                    </div>
                                </div>
                            </template>
                        </div>
                    </template>
                </div>

                <div class="space-y-4 rounded-xl border border-white/10 bg-black/20 p-4">
                    <h2 class="text-lg font-semibold text-white">{{ __('Review & pay once') }}</h2>
                    <dl class="space-y-2 text-sm">
                        <div class="flex justify-between border-b border-white/10 pb-2">
                            <dt class="text-slate-500">{{ __('Lines') }}</dt>
                            <dd class="font-medium text-white" x-text="rows.length"></dd>
                        </div>
                        <div class="flex justify-between">
                            <dt class="text-slate-500">{{ __('Total') }}</dt>
                            <dd class="text-lg font-bold text-[#FFD700]"><span x-text="totalPrice().toFixed(2)"></span> GHS</dd>
                        </div>
                        <div class="flex justify-between pt-1" x-show="!requiresPaystack">
                            <dt class="text-slate-500">{{ __('Your balance') }}</dt>
                            <dd class="font-medium" :class="hasBalance() ? 'text-emerald-400' : 'text-red-400'" x-text="walletBalance.toFixed(2) + ' GHS'"></dd>
                        </div>
                    </dl>

                    <template x-if="isAgentShopBuyer && requiresPaystack">
                        <p class="rounded-lg border border-sky-500/30 bg-sky-500/10 px-3 py-2 text-sm text-sky-100">{{ __('Your wallet can no longer be used for orders. Each purchase is paid with Paystack.') }}</p>
                    </template>

                    <template x-if="isAgentShopBuyer && !requiresPaystack && canUseWallet">
                        <p class="rounded-lg border border-amber-500/30 bg-amber-500/10 px-3 py-2 text-sm text-amber-100">{{ __('You may still pay from wallet while your balance stays above :cutoff GHS. After that, Paystack is required for every order.', ['cutoff' => number_format((float) $walletCutoff, 2)]) }}</p>
                    </template>

                    <template x-if="!requiresPaystack && !hasBalance() && totalPrice() > 0 && canUseWallet">
                        <p class="rounded-lg border border-red-500/30 bg-red-500/10 px-3 py-2 text-sm text-red-200">{{ __('Insufficient balance for this total. Ask your agent to credit your wallet, or pay with Paystack.') }}</p>
                    </template>

                    <template x-if="!requiresPaystack && !canUseWallet && isAgentShopBuyer && totalPrice() > 0">
                        <p class="rounded-lg border border-red-500/30 bg-red-500/10 px-3 py-2 text-sm text-red-200">{{ __('Your wallet balance is at or below :cutoff GHS. Pay with Paystack for this order.', ['cutoff' => number_format((float) $walletCutoff, 2)]) }}</p>
                    </template>

                    <template x-if="totalPrice() <= 0">
                        <p class="text-sm text-amber-200">{{ __('Complete each line with network, recipient number, and bundle.') }}</p>
                    </template>

                    <template x-if="rows.some((r) => needsAfa(r)) && !allRowsValid()">
                        <p class="rounded-lg border border-amber-500/30 bg-amber-500/10 px-3 py-2 text-sm text-amber-100">{{ __('For MTN AFA lines, fill Name, Number, Ghana Card number, date of birth, occupation, and location before paying.') }}</p>
                    </template>

                    <label class="flex cursor-pointer items-start gap-3 rounded-lg border border-white/10 bg-black/30 p-4">
                        <input type="checkbox" name="confirm" value="1" x-model="confirm" class="mt-1 size-4 rounded border-white/20 text-[#FFD700]" />
                        <span class="text-sm text-slate-300">{{ __('I confirm these orders. Charges apply for the full total immediately. Each recipient number is correct for its bundle.') }}</span>
                    </label>

                    <button type="submit" x-show="!requiresPaystack && canUseWallet" @click="paymentMethod = 'wallet'; if (!canSubmitWallet()) { $event.preventDefault(); } else { submitting = true; }" :disabled="!canSubmitWallet()" class="inline-flex min-w-48 items-center justify-center gap-2 rounded-lg bg-[#FFD700] px-6 py-3 text-sm font-bold text-[#1A1A2E] disabled:opacity-40">
                        <svg x-show="submitting && paymentMethod === 'wallet'" class="size-5 animate-spin" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg>
                        <span x-text="submitting && paymentMethod === 'wallet' ? '{{ __('Placing…') }}' : (rows.length > 1 ? '{{ __('Pay from wallet') }}' : '{{ __('Place order from wallet') }}')"></span>
                    </button>

                    <button type="submit" x-show="isAgentShopBuyer" @click="paymentMethod = 'paystack'; if (!canSubmitPaystack()) { $event.preventDefault(); } else { submitting = true; }" :disabled="!canSubmitPaystack()" class="inline-flex min-w-48 items-center justify-center gap-2 rounded-lg border border-[#FFD700] bg-transparent px-6 py-3 text-sm font-bold text-[#FFD700] disabled:opacity-40">
                        <svg x-show="submitting && paymentMethod === 'paystack'" class="size-5 animate-spin" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg>
                        <span x-text="submitting && paymentMethod === 'paystack' ? '{{ __('Redirecting…') }}' : '{{ __('Pay with Paystack') }}'"></span>
                    </button>

                    <button type="submit" x-show="!isAgentShopBuyer" @click="paymentMethod = 'wallet'; if (!canSubmitWallet()) { $event.preventDefault(); } else { submitting = true; }" :disabled="!canSubmitWallet()" class="inline-flex min-w-48 items-center justify-center gap-2 rounded-lg bg-[#FFD700] px-6 py-3 text-sm font-bold text-[#1A1A2E] disabled:opacity-40">
                        <svg x-show="submitting" class="size-5 animate-spin" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg>
                        <span x-text="submitting ? '{{ __('Placing…') }}' : (rows.length > 1 ? '{{ __('Pay & place all') }}' : '{{ __('Place order') }}')"></span>
                    </button>
                </div>
            </form>
        </div>
    </div>
@endsection
