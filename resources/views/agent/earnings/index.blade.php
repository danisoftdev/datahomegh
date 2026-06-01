@extends('layouts.agent')

@section('title', __('Earnings') . ' — ' . config('app.name'))
@section('heading', __('Earnings'))

@section('content')
    <div class="grid gap-6 lg:grid-cols-3">
        <div class="rounded-xl border border-white/10 bg-[#16213E]/80 p-6 lg:col-span-1">
            <h2 class="text-lg font-semibold text-white">{{ __('Available balance') }}</h2>
            <p class="mt-2 text-3xl font-bold text-emerald-400">{{ number_format((float) $balance->balance, 2) }} GHS</p>
            <p class="mt-2 text-sm text-slate-400">{{ __('Pending withdrawal: :amount GHS', ['amount' => number_format((float) $balance->pending_withdrawal, 2)]) }}</p>
            <p class="mt-4 text-xs text-slate-500">{{ __('Interest is credited when admin marks Paystack orders as SENT.') }}</p>
        </div>

        <div class="rounded-xl border border-white/10 bg-[#16213E]/80 p-6 lg:col-span-2">
            <h2 class="mb-4 text-lg font-semibold text-white">{{ __('Request withdrawal') }}</h2>
            <p class="mb-4 text-sm text-slate-400">{{ __('Minimum :min GHS. Fee: :fee GHS (set by admin).', ['min' => number_format((float) $minWithdrawal, 2), 'fee' => number_format((float) $withdrawalFee, 2)]) }}</p>

            @if (session('status'))
                <div class="mb-4 rounded-lg border border-emerald-500/30 bg-emerald-500/10 px-4 py-3 text-sm text-emerald-100">{{ session('status') }}</div>
            @endif
            @if ($errors->any())
                <div class="mb-4 rounded-lg border border-red-500/30 bg-red-500/10 px-4 py-3 text-sm text-red-100">
                    @foreach ($errors->all() as $err)
                        <p>{{ $err }}</p>
                    @endforeach
                </div>
            @endif

            <form method="post" action="{{ route('agent.earnings.withdraw') }}" class="grid gap-4 sm:grid-cols-2">
                @csrf
                <div>
                    <label class="mb-1 block text-xs text-slate-400">{{ __('Amount (GHS)') }}</label>
                    <input type="number" name="amount" step="0.01" min="{{ $minWithdrawal }}" required value="{{ old('amount') }}" class="w-full rounded-lg border border-white/10 bg-[#1A1A2E] px-3 py-2 text-sm text-white" />
                </div>
                <div>
                    <label class="mb-1 block text-xs text-slate-400">{{ __('Payout method') }}</label>
                    <select name="payout_method" required class="w-full rounded-lg border border-white/10 bg-[#1A1A2E] px-3 py-2 text-sm text-white">
                        <option value="momo" @selected(old('payout_method', $payoutMethod) === 'momo')>{{ __('Mobile money') }}</option>
                        <option value="bank" @selected(old('payout_method', $payoutMethod) === 'bank')>{{ __('Bank transfer') }}</option>
                    </select>
                </div>
                <div>
                    <label class="mb-1 block text-xs text-slate-400">{{ __('Account name') }}</label>
                    <input type="text" name="account_name" required value="{{ old('account_name', $payoutDetails['account_name'] ?? '') }}" class="w-full rounded-lg border border-white/10 bg-[#1A1A2E] px-3 py-2 text-sm text-white" />
                </div>
                <div>
                    <label class="mb-1 block text-xs text-slate-400">{{ __('MoMo network') }}</label>
                    <input type="text" name="momo_network" value="{{ old('momo_network', $payoutDetails['momo_network'] ?? '') }}" placeholder="{{ __('MTN / Telecel / AirtelTigo') }}" class="w-full rounded-lg border border-white/10 bg-[#1A1A2E] px-3 py-2 text-sm text-white" />
                </div>
                <div>
                    <label class="mb-1 block text-xs text-slate-400">{{ __('MoMo number') }}</label>
                    <input type="text" name="momo_number" value="{{ old('momo_number', $payoutDetails['momo_number'] ?? '') }}" class="w-full rounded-lg border border-white/10 bg-[#1A1A2E] px-3 py-2 text-sm text-white" />
                </div>
                <div>
                    <label class="mb-1 block text-xs text-slate-400">{{ __('Bank name') }}</label>
                    <input type="text" name="bank_name" value="{{ old('bank_name', $payoutDetails['bank_name'] ?? '') }}" class="w-full rounded-lg border border-white/10 bg-[#1A1A2E] px-3 py-2 text-sm text-white" />
                </div>
                <div>
                    <label class="mb-1 block text-xs text-slate-400">{{ __('Account number') }}</label>
                    <input type="text" name="account_number" value="{{ old('account_number', $payoutDetails['account_number'] ?? '') }}" class="w-full rounded-lg border border-white/10 bg-[#1A1A2E] px-3 py-2 text-sm text-white" />
                </div>
                <div class="sm:col-span-2">
                    <label class="mb-1 block text-xs text-slate-400">{{ __('Note (optional)') }}</label>
                    <input type="text" name="agent_note" maxlength="500" value="{{ old('agent_note') }}" class="w-full rounded-lg border border-white/10 bg-[#1A1A2E] px-3 py-2 text-sm text-white" />
                </div>
                <div class="sm:col-span-2">
                    <button type="submit" class="rounded-lg bg-emerald-600 px-5 py-2.5 text-sm font-semibold text-white hover:bg-emerald-500">{{ __('Submit withdrawal request') }}</button>
                </div>
            </form>
        </div>
    </div>

    <div class="mt-8 grid gap-6 lg:grid-cols-2">
        <div class="rounded-xl border border-white/10 bg-[#16213E]/80 p-6">
            <h3 class="mb-4 font-semibold text-white">{{ __('Recent withdrawals') }}</h3>
            <div class="space-y-3 text-sm">
                @forelse ($withdrawals as $w)
                    <div class="rounded-lg border border-white/10 bg-black/20 p-3">
                        <div class="flex justify-between gap-2">
                            <span class="font-medium text-white">#{{ $w->id }} · {{ number_format((float) $w->amount, 2) }} GHS</span>
                            <span class="text-slate-400">{{ $w->status }}</span>
                        </div>
                        <p class="mt-1 text-xs text-slate-500">{{ $w->created_at?->format('Y-m-d H:i') }} · {{ __('Net') }} {{ number_format((float) $w->net_amount, 2) }} GHS</p>
                    </div>
                @empty
                    <p class="text-slate-500">{{ __('No withdrawal requests yet.') }}</p>
                @endforelse
            </div>
        </div>

        <div class="rounded-xl border border-white/10 bg-[#16213E]/80 p-6">
            <h3 class="mb-4 font-semibold text-white">{{ __('Earnings ledger') }}</h3>
            {{ $ledger->links() }}
            <table class="mt-2 w-full text-left text-sm">
                <thead class="text-xs uppercase text-slate-500">
                    <tr>
                        <th class="py-2">{{ __('When') }}</th>
                        <th class="py-2">{{ __('Type') }}</th>
                        <th class="py-2">{{ __('Amount') }}</th>
                        <th class="py-2">{{ __('Balance') }}</th>
                    </tr>
                </thead>
                <tbody class="text-slate-300">
                    @foreach ($ledger as $entry)
                        <tr class="border-t border-white/5">
                            <td class="py-2 whitespace-nowrap">{{ $entry->created_at?->format('Y-m-d H:i') }}</td>
                            <td class="py-2">{{ $entry->source }}</td>
                            <td class="py-2">{{ $entry->type === 'CREDIT' ? '+' : '-' }}{{ number_format((float) $entry->amount, 2) }}</td>
                            <td class="py-2">{{ number_format((float) $entry->balance_after, 2) }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
@endsection
