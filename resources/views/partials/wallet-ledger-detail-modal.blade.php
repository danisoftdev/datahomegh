<template x-teleport="body">
    <div
        x-show="open"
        x-cloak
        class="fixed inset-0 z-200 flex items-center justify-center p-4"
        @keydown.escape.window="closeDetail()"
    >
        <div class="absolute inset-0 bg-black/75" @click="closeDetail()"></div>
        <div
            class="relative max-h-[90vh] w-full max-w-lg overflow-y-auto rounded-2xl border border-white/10 bg-[#1A1A2E] p-6 shadow-2xl"
            role="dialog"
            aria-modal="true"
            aria-labelledby="wallet-ledger-detail-title"
            @click.stop
        >
        <div class="flex items-start justify-between gap-3">
            <div>
                <p class="text-xs font-semibold uppercase tracking-wide text-emerald-300">{{ __('Transaction details') }}</p>
                <h3 id="wallet-ledger-detail-title" class="mt-1 text-lg font-semibold text-white" x-text="detail?.title ?? ''"></h3>
            </div>
            <button type="button" class="rounded-lg border border-white/10 px-2 py-1 text-xs text-slate-400 hover:bg-white/5" @click="closeDetail()">{{ __('Close') }}</button>
        </div>

        <template x-if="detail">
            <dl class="mt-5 grid gap-4 text-sm sm:grid-cols-2">
                <div class="sm:col-span-2">
                    <dt class="text-xs uppercase tracking-wide text-slate-500">{{ __('Transaction ID') }}</dt>
                    <dd class="mt-0.5 font-mono text-base font-semibold text-emerald-300" x-text="detail.transaction_id"></dd>
                </div>
                <div class="sm:col-span-2">
                    <dt class="text-xs uppercase tracking-wide text-slate-500">{{ __('Date & time') }}</dt>
                    <dd class="mt-0.5 text-white" x-text="detail.occurred_at"></dd>
                    <dd class="font-mono text-xs text-slate-500" x-text="detail.occurred_at_short"></dd>
                </div>
                <div>
                    <dt class="text-xs uppercase tracking-wide text-slate-500">{{ __('Type') }}</dt>
                    <dd class="mt-0.5 text-white" x-text="detail.type"></dd>
                </div>
                <div>
                    <dt class="text-xs uppercase tracking-wide text-slate-500">{{ __('Direction') }}</dt>
                    <dd class="mt-0.5 text-white" x-text="detail.direction"></dd>
                </div>
                <div>
                    <dt class="text-xs uppercase tracking-wide text-slate-500">{{ __('Amount') }}</dt>
                    <dd class="mt-0.5 text-lg font-semibold tabular-nums text-white"><span x-text="detail.amount"></span> GHS</dd>
                </div>
                <div>
                    <dt class="text-xs uppercase tracking-wide text-slate-500">{{ __('Source') }}</dt>
                    <dd class="mt-0.5 text-white" x-text="detail.source"></dd>
                </div>
                <div>
                    <dt class="text-xs uppercase tracking-wide text-slate-500">{{ __('Balance before') }}</dt>
                    <dd class="mt-0.5 text-white"><span x-text="detail.balance_before"></span> GHS</dd>
                </div>
                <div>
                    <dt class="text-xs uppercase tracking-wide text-slate-500">{{ __('Balance after') }}</dt>
                    <dd class="mt-0.5 text-white"><span x-text="detail.balance_after"></span> GHS</dd>
                </div>
                <div class="sm:col-span-2 rounded-lg border border-white/10 bg-black/20 p-3">
                    <dt class="text-xs uppercase tracking-wide text-slate-500" x-text="detail.performed_by_label"></dt>
                    <dd class="mt-1 text-white">
                        <span x-text="detail.performed_by_name"></span>
                        <span class="text-slate-400"> · </span>
                        <span class="text-slate-300" x-text="detail.performed_by_role"></span>
                    </dd>
                </div>
                <template x-if="detail.account_username">
                    <div class="sm:col-span-2">
                        <dt class="text-xs uppercase tracking-wide text-slate-500">{{ __('Wallet account') }}</dt>
                        <dd class="mt-0.5 text-white">
                            <span x-text="detail.account_name"></span>
                            <span class="text-slate-400"> (@</span><span x-text="detail.account_username"></span><span class="text-slate-400">)</span>
                        </dd>
                    </div>
                </template>
                <template x-if="detail.reference">
                    <div class="sm:col-span-2">
                        <dt class="text-xs uppercase tracking-wide text-slate-500">{{ __('Reference') }}</dt>
                        <dd class="mt-0.5 break-all font-mono text-xs text-slate-300" x-text="detail.reference"></dd>
                    </div>
                </template>
                <template x-if="detail.note">
                    <div class="sm:col-span-2">
                        <dt class="text-xs uppercase tracking-wide text-slate-500">{{ __('Note') }}</dt>
                        <dd class="mt-0.5 text-slate-200" x-text="detail.note"></dd>
                    </div>
                </template>
            </dl>
        </template>
        </div>
    </div>
</template>
