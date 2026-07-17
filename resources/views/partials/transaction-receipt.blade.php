@php
    /** @var array<string, mixed>|null $receipt */
    $receipt = session('transaction_receipt');
@endphp

@if (is_array($receipt) && ! empty($receipt['transaction_id']))
    <div class="mb-4 rounded-xl border border-emerald-500/40 bg-emerald-500/10 p-4 sm:p-5">
        <div class="flex flex-wrap items-start justify-between gap-3">
            <div>
                <p class="text-xs font-semibold uppercase tracking-wide text-emerald-300">{{ __('Transaction receipt') }}</p>
                <h3 class="mt-1 text-lg font-semibold text-white">{{ $receipt['title'] ?? __('Transaction') }}</h3>
            </div>
            <p class="rounded-lg bg-black/30 px-3 py-2 font-mono text-sm font-bold tracking-wide text-emerald-200" title="{{ __('Transaction ID') }}">
                {{ $receipt['transaction_id'] }}
            </p>
        </div>

        <dl class="mt-4 grid gap-3 text-sm sm:grid-cols-2">
            @if (! empty($receipt['occurred_at']))
                <div>
                    <dt class="text-xs uppercase tracking-wide text-slate-400">{{ __('Date & time') }}</dt>
                    <dd class="mt-0.5 text-white">{{ $receipt['occurred_at'] }}</dd>
                    @if (! empty($receipt['occurred_at_short']))
                        <dd class="font-mono text-xs text-slate-500">{{ $receipt['occurred_at_short'] }}</dd>
                    @endif
                </div>
            @endif

            @if (! empty($receipt['amount']))
                <div>
                    <dt class="text-xs uppercase tracking-wide text-slate-400">{{ __('Amount') }}</dt>
                    <dd class="mt-0.5 text-lg font-semibold tabular-nums text-white">{{ $receipt['amount'] }} GHS</dd>
                </div>
            @endif

            @if (($receipt['kind'] ?? '') === 'withdrawal')
                @if (! empty($receipt['fee']))
                    <div>
                        <dt class="text-xs uppercase tracking-wide text-slate-400">{{ __('Fee') }}</dt>
                        <dd class="mt-0.5 text-white">{{ $receipt['fee'] }} GHS</dd>
                    </div>
                @endif
                @if (! empty($receipt['net_amount']))
                    <div>
                        <dt class="text-xs uppercase tracking-wide text-slate-400">{{ __('Net payout') }}</dt>
                        <dd class="mt-0.5 font-semibold text-emerald-200">{{ $receipt['net_amount'] }} GHS</dd>
                    </div>
                @endif
                @if (! empty($receipt['status']))
                    <div>
                        <dt class="text-xs uppercase tracking-wide text-slate-400">{{ __('Status') }}</dt>
                        <dd class="mt-0.5 text-white">{{ $receipt['status'] }}</dd>
                    </div>
                @endif
                @if (! empty($receipt['payout_method']))
                    <div>
                        <dt class="text-xs uppercase tracking-wide text-slate-400">{{ __('Payout method') }}</dt>
                        <dd class="mt-0.5 text-white">{{ $receipt['payout_method'] }}</dd>
                    </div>
                @endif
            @else
                @if (! empty($receipt['type']))
                    <div>
                        <dt class="text-xs uppercase tracking-wide text-slate-400">{{ __('Type') }}</dt>
                        <dd class="mt-0.5 text-white">{{ $receipt['type'] }}</dd>
                    </div>
                @endif
                @if (! empty($receipt['source']))
                    <div>
                        <dt class="text-xs uppercase tracking-wide text-slate-400">{{ __('Source') }}</dt>
                        <dd class="mt-0.5 text-white">{{ $receipt['source'] }}</dd>
                    </div>
                @endif
                @if (! empty($receipt['balance_after']))
                    <div>
                        <dt class="text-xs uppercase tracking-wide text-slate-400">{{ __('Balance after') }}</dt>
                        <dd class="mt-0.5 text-white">{{ $receipt['balance_after'] }} GHS</dd>
                    </div>
                @endif
            @endif

            @if (! empty($receipt['account_label']))
                <div>
                    <dt class="text-xs uppercase tracking-wide text-slate-400">{{ __('Account') }}</dt>
                    <dd class="mt-0.5 text-white">{{ $receipt['account_label'] }}</dd>
                </div>
            @endif

            @if (! empty($receipt['reference']) && ($receipt['kind'] ?? '') !== 'withdrawal')
                <div class="sm:col-span-2">
                    <dt class="text-xs uppercase tracking-wide text-slate-400">{{ __('Reference') }}</dt>
                    <dd class="mt-0.5 break-all font-mono text-xs text-slate-300">{{ $receipt['reference'] }}</dd>
                </div>
            @endif

            @if (! empty($receipt['note']))
                <div class="sm:col-span-2">
                    <dt class="text-xs uppercase tracking-wide text-slate-400">{{ __('Note') }}</dt>
                    <dd class="mt-0.5 text-slate-200">{{ $receipt['note'] }}</dd>
                </div>
            @endif
        </dl>

        <p class="mt-4 text-xs text-slate-400">{{ __('Save this transaction ID when contacting support about this payment.') }}</p>
    </div>
@endif
