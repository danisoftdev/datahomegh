@extends('layouts.agent')

@section('title', $buyer->username . ' — ' . __('My buyers'))
@section('heading', $buyer->username)

@section('content')
    <div class="mb-6 flex flex-wrap items-center gap-3">
        <a href="{{ route('agent.buyers.index') }}" class="text-sm text-emerald-400 hover:underline">← {{ __('My buyers') }}</a>
    </div>

    <div class="rounded-xl border border-white/10 bg-[#16213E]/80 p-6">
        <h2 class="mb-4 text-lg font-semibold text-white">{{ __('Buyer') }}</h2>
        <dl class="grid gap-2 text-sm sm:grid-cols-2">
            <div><dt class="text-slate-500">{{ __('Name') }}</dt><dd>{{ $buyer->name }}</dd></div>
            <div><dt class="text-slate-500">{{ __('Phone') }}</dt><dd>{{ $buyer->phone }}</dd></div>
            <div><dt class="text-slate-500">{{ __('Status') }}</dt><dd>{{ $buyer->status }}</dd></div>
            <div><dt class="text-slate-500">{{ __('Wallet') }}</dt><dd>{{ $buyer->wallet ? number_format((float) $buyer->wallet->balance, 2).' GHS' : '—' }}</dd></div>
            <div><dt class="text-slate-500">{{ __('Checkout') }}</dt><dd>{{ $buyer->paystack_checkout_only ? __('Paystack only') : __('Wallet allowed while balance > :cutoff GHS', ['cutoff' => number_format((float) config('datahome.agent_shop.wallet_cutoff_ghs', 5), 2)]) }}</dd></div>
        </dl>

        <div class="mt-6 flex flex-wrap gap-2 border-t border-white/10 pt-6">
            @if ($buyer->status === 'active')
                <div class="w-full rounded-lg border border-white/10 bg-[#1A1A2E]/80 p-4">
                    <h3 class="mb-2 text-sm font-medium text-emerald-300">{{ __('Credit buyer wallet') }}</h3>
                    @if ($buyer->paystack_checkout_only)
                        <p class="text-sm text-amber-200">{{ __('This buyer pays with Paystack only. Wallet credits are disabled.') }}</p>
                    @else
                    <p class="mb-3 text-xs text-slate-400">{{ __('After you receive mobile money from this buyer (they should use their username as the reference), record the amount here.') }}</p>
                    <form method="post" action="{{ route('agent.buyers.wallet-credit', $buyer) }}" class="flex flex-wrap items-end gap-3">
                        @csrf
                        <div>
                            <label for="credit_amount" class="mb-1 block text-xs text-slate-500">{{ __('Amount (GHS)') }}</label>
                            <input id="credit_amount" name="amount" type="number" step="0.01" min="0.01" max="100000" required
                                class="w-36 rounded-lg border border-white/10 bg-[#16213E] px-3 py-2 text-sm text-white" />
                        </div>
                        <div class="min-w-48 flex-1">
                            <label for="credit_note" class="mb-1 block text-xs text-slate-500">{{ __('Note (optional)') }}</label>
                            <input id="credit_note" name="note" type="text" maxlength="500"
                                class="w-full rounded-lg border border-white/10 bg-[#16213E] px-3 py-2 text-sm text-white" placeholder="{{ __('e.g. MoMo ref from buyer') }}" />
                        </div>
                        <button type="submit" class="rounded-lg bg-emerald-600 px-4 py-2 text-sm font-medium text-white hover:bg-emerald-500">{{ __('Credit wallet') }}</button>
                    </form>
                    @endif
                </div>
            @endif
            @if ($buyer->status === 'pending')
                <form method="post" action="{{ route('agent.buyers.approve', $buyer) }}">
                    @csrf
                    <button type="submit" class="rounded-lg bg-emerald-600 px-4 py-2 text-sm font-medium text-white hover:bg-emerald-500">{{ __('Approve') }}</button>
                </form>
            @endif
            <form method="post" action="{{ route('agent.buyers.destroy', $buyer) }}" onsubmit="return confirm(@json(__('Remove this buyer from your shop? They will keep their account but no longer be linked to you.')))">
                @csrf
                @method('DELETE')
                <button type="submit" class="rounded-lg bg-red-600/80 px-4 py-2 text-sm text-white hover:bg-red-600">{{ __('Remove from shop') }}</button>
            </form>
        </div>
    </div>

    <div class="mt-8 rounded-xl border border-white/10 bg-[#16213E]/80 p-6">
        <h3 class="mb-4 font-semibold text-white">{{ __('Order history') }}</h3>
        {{ $orders->links() }}
        <table class="mt-2 w-full text-left text-sm text-slate-300">
            <thead class="text-xs uppercase text-slate-500">
                <tr>
                    <th class="py-2">#</th>
                    <th class="py-2">{{ __('When') }}</th>
                    <th class="py-2">{{ __('Status') }}</th>
                    <th class="py-2">{{ __('Amount') }}</th>
                    <th class="py-2"></th>
                </tr>
            </thead>
            <tbody>
                @foreach ($orders as $o)
                    <tr class="border-t border-white/5">
                        <td class="py-2">{{ $o->id }}</td>
                        <td class="py-2 whitespace-nowrap">{{ $o->created_at?->format('Y-m-d H:i') }}</td>
                        <td class="py-2">{{ $o->status }}</td>
                        <td class="py-2">{{ number_format((float) $o->amount, 2) }}</td>
                        <td class="py-2"><a href="{{ route('agent.orders.show', $o) }}" class="text-emerald-400 hover:underline">{{ __('View') }}</a></td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
@endsection
