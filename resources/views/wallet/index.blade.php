@extends('layouts.auth')

@section('title', __('Wallet') . ' — ' . config('app.name'))

@section('content')
    <div class="w-full max-w-3xl space-y-8">
        <div class="rounded-2xl border border-white/10 bg-[#16213E]/80 p-6 shadow-xl backdrop-blur-sm">
            <h1 class="text-xl font-bold text-white">{{ __('Wallet') }}</h1>
            <p class="mt-1 text-sm text-slate-400">{{ __('Balance and recent ledger entries.') }}</p>

            @if (session('status'))
                <p class="mt-4 rounded-lg border border-emerald-500/30 bg-emerald-500/10 px-3 py-2 text-sm text-emerald-200">{{ session('status') }}</p>
            @endif

            @if ($errors->any())
                <ul class="mt-4 list-inside list-disc rounded-lg border border-red-500/30 bg-red-500/10 px-3 py-2 text-sm text-red-200">
                    @foreach ($errors->all() as $err)
                        <li>{{ $err }}</li>
                    @endforeach
                </ul>
            @endif

            <dl class="mt-6 flex flex-wrap gap-6">
                <div>
                    <dt class="text-xs font-medium uppercase tracking-wide text-slate-500">{{ __('Balance (GHS)') }}</dt>
                    <dd class="text-2xl font-semibold text-[#FFD700]">
                        {{ $wallet !== null ? number_format((float) $wallet->balance, 2) : '—' }}
                    </dd>
                </div>
                @if ($wallet?->is_frozen)
                    <div class="flex items-end">
                        <span class="rounded-full border border-amber-500/40 bg-amber-500/10 px-3 py-1 text-xs font-medium text-amber-200">{{ __('Wallet frozen') }}</span>
                    </div>
                @endif
            </dl>
        </div>

        @if ($wallet !== null && ! $wallet->is_frozen && ! auth()->user()->wallet_frozen)
            <div class="rounded-2xl border border-white/10 bg-[#16213E]/80 p-6 shadow-xl backdrop-blur-sm">
                <h2 class="text-lg font-semibold text-white">{{ __('Top up via Paystack') }}</h2>
                <p class="mt-1 text-sm text-slate-400">{{ __('Amount between 1 and 10,000 GHS.') }}</p>
                <form method="post" action="{{ route('wallet.topup.initialize') }}" class="mt-4 flex flex-wrap items-end gap-3">
                    @csrf
                    <div>
                        <label for="amount" class="sr-only">{{ __('Amount') }}</label>
                        <input id="amount" name="amount" type="number" step="0.01" min="1" max="10000" required
                            class="w-40 rounded-lg border border-white/10 bg-[#1A1A2E] px-3 py-2 text-white focus:border-[#FFD700]/50 focus:outline-none focus:ring-2 focus:ring-[#FFD700]/30"
                            placeholder="10.00" />
                    </div>
                    <button type="submit" class="rounded-lg bg-[#FFD700] px-4 py-2 text-sm font-semibold text-[#1A1A2E] hover:brightness-95">
                        {{ __('Pay') }}
                    </button>
                </form>
            </div>
        @endif

        <div class="rounded-2xl border border-white/10 bg-[#16213E]/80 p-6 shadow-xl backdrop-blur-sm">
            <h2 class="text-lg font-semibold text-white">{{ __('Ledger') }}</h2>
            <div class="mt-4 overflow-x-auto">
                <table class="w-full text-left text-sm text-slate-300">
                    <thead class="border-b border-white/10 text-xs uppercase text-slate-500">
                        <tr>
                            <th class="py-2 pr-4">{{ __('When') }}</th>
                            <th class="py-2 pr-4">{{ __('Type') }}</th>
                            <th class="py-2 pr-4">{{ __('Amount') }}</th>
                            <th class="py-2 pr-4">{{ __('Balance after') }}</th>
                            <th class="py-2 pr-4">{{ __('Source') }}</th>
                            <th class="py-2">{{ __('Ref') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($ledger as $row)
                            <tr class="border-b border-white/5">
                                <td class="py-2 pr-4 whitespace-nowrap">{{ $row->created_at?->timezone(config('app.timezone'))->format('Y-m-d H:i') }}</td>
                                <td class="py-2 pr-4">{{ $row->type }}</td>
                                <td class="py-2 pr-4">{{ number_format((float) $row->amount, 2) }}</td>
                                <td class="py-2 pr-4">{{ number_format((float) $row->balance_after, 2) }}</td>
                                <td class="py-2 pr-4">{{ $row->source }}</td>
                                <td class="py-2 max-w-32 truncate" title="{{ $row->reference }}">{{ $row->reference }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="py-6 text-center text-slate-500">{{ __('No ledger entries yet.') }}</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            <div class="mt-4">
                {{ $ledger->links() }}
            </div>
        </div>

        <p class="text-center text-sm text-slate-500">
            <a href="{{ route('dashboard') }}" class="text-[#FFD700] hover:underline">{{ __('Back to dashboard') }}</a>
        </p>
    </div>
@endsection
