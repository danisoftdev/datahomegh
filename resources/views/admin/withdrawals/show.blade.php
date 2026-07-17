@extends('layouts.admin')

@section('title', __('Withdrawal') . ' ' . \App\Support\TransactionReceipt::withdrawalId((int) $withdrawal->id))
@section('heading', __('Withdrawal') . ' ' . \App\Support\TransactionReceipt::withdrawalId((int) $withdrawal->id))

@section('content')
    <a href="{{ route('admin.withdrawals.index') }}" class="mb-6 inline-block text-sm text-[#FFD700] hover:underline">← {{ __('Agent withdrawals') }}</a>

    @if (session('status'))
        <div class="mb-4 rounded-lg border border-emerald-500/30 bg-emerald-500/10 px-4 py-3 text-sm text-emerald-100">{{ session('status') }}</div>
    @endif

    <div class="grid gap-6 lg:grid-cols-2">
        <div class="rounded-xl border border-white/10 bg-[#16213E]/80 p-6">
            <dl class="grid gap-3 text-sm sm:grid-cols-2">
                <div><dt class="text-slate-500">{{ __('Transaction ID') }}</dt><dd class="font-mono font-semibold text-[#FFD700]">{{ \App\Support\TransactionReceipt::withdrawalId((int) $withdrawal->id) }}</dd></div>
                <div><dt class="text-slate-500">{{ __('Agent') }}</dt><dd>{{ $withdrawal->agent?->username }} ({{ $withdrawal->agent?->name }})</dd></div>
                <div><dt class="text-slate-500">{{ __('Status') }}</dt><dd>{{ $withdrawal->status }}</dd></div>
                <div><dt class="text-slate-500">{{ __('Requested') }}</dt><dd>{{ \App\Support\TransactionReceipt::formatDateTime($withdrawal->created_at) }}<br><span class="font-mono text-xs text-slate-500">{{ \App\Support\TransactionReceipt::formatDateTimeShort($withdrawal->created_at) }}</span></dd></div>
                @if ($withdrawal->processed_at)
                    <div><dt class="text-slate-500">{{ __('Processed') }}</dt><dd>{{ \App\Support\TransactionReceipt::formatDateTime($withdrawal->processed_at) }}<br><span class="font-mono text-xs text-slate-500">{{ \App\Support\TransactionReceipt::formatDateTimeShort($withdrawal->processed_at) }}</span></dd></div>
                @endif
                <div><dt class="text-slate-500">{{ __('Amount') }}</dt><dd>{{ number_format((float) $withdrawal->amount, 2) }} GHS</dd></div>
                <div><dt class="text-slate-500">{{ __('Fee') }}</dt><dd>{{ number_format((float) $withdrawal->fee, 2) }} GHS</dd></div>
                <div><dt class="text-slate-500">{{ __('Net payout') }}</dt><dd class="font-semibold text-[#FFD700]">{{ number_format((float) $withdrawal->net_amount, 2) }} GHS</dd></div>
                <div><dt class="text-slate-500">{{ __('Method') }}</dt><dd>{{ strtoupper($withdrawal->payout_method) }}</dd></div>
            </dl>

            <div class="mt-6 rounded-lg border border-white/10 bg-black/20 p-4 text-sm">
                <h3 class="mb-2 font-medium text-white">{{ __('Payout details') }}</h3>
                @foreach ($withdrawal->payout_details ?? [] as $key => $value)
                    <p><span class="text-slate-500">{{ str_replace('_', ' ', ucfirst($key)) }}:</span> {{ $value }}</p>
                @endforeach
            </div>

            @if ($withdrawal->agent_note)
                <p class="mt-4 text-sm text-slate-400"><span class="text-slate-500">{{ __('Agent note') }}:</span> {{ $withdrawal->agent_note }}</p>
            @endif
            @if ($withdrawal->admin_note)
                <p class="mt-2 text-sm text-slate-400"><span class="text-slate-500">{{ __('Admin note') }}:</span> {{ $withdrawal->admin_note }}</p>
            @endif
        </div>

        @if (! in_array($withdrawal->status, [\App\Models\AgentWithdrawalRequest::STATUS_PAID, \App\Models\AgentWithdrawalRequest::STATUS_REJECTED], true))
            <div class="rounded-xl border border-white/10 bg-[#16213E]/80 p-6">
                <h2 class="mb-4 text-lg font-semibold text-white">{{ __('Update status') }}</h2>
                @if ($errors->any())
                    <div class="mb-4 rounded-lg border border-red-500/30 bg-red-500/10 px-4 py-3 text-sm text-red-100">{{ $errors->first() }}</div>
                @endif
                <form method="post" action="{{ route('admin.withdrawals.status', $withdrawal) }}" class="space-y-4">
                    @csrf
                    @method('PATCH')
                    <div>
                        <label class="mb-1 block text-xs text-slate-400">{{ __('New status') }}</label>
                        <select name="status" required class="w-full rounded-lg border border-white/10 bg-[#1A1A2E] px-3 py-2 text-sm text-white">
                            @if ($withdrawal->status === \App\Models\AgentWithdrawalRequest::STATUS_PENDING)
                                <option value="PROCESSING">{{ __('PROCESSING') }}</option>
                            @endif
                            @if (in_array($withdrawal->status, [\App\Models\AgentWithdrawalRequest::STATUS_PENDING, \App\Models\AgentWithdrawalRequest::STATUS_PROCESSING], true))
                                <option value="PAID">{{ __('PAID') }}</option>
                                <option value="REJECTED">{{ __('REJECTED') }}</option>
                            @endif
                        </select>
                    </div>
                    <div>
                        <label class="mb-1 block text-xs text-slate-400">{{ __('Admin note') }}</label>
                        <textarea name="admin_note" rows="3" class="w-full rounded-lg border border-white/10 bg-[#1A1A2E] px-3 py-2 text-sm text-white">{{ old('admin_note') }}</textarea>
                    </div>
                    <button type="submit" class="rounded-lg bg-[#FFD700] px-5 py-2.5 text-sm font-bold text-[#1A1A2E]">{{ __('Save') }}</button>
                </form>
            </div>
        @endif
    </div>
@endsection
