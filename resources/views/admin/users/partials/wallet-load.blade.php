@php
    /** @var \App\Models\User $user */
    $wallet = $user->wallet;
@endphp

<div class="mt-6 rounded-lg border border-[#FFD700]/30 bg-[#1A1A2E]/80 p-4">
    <h3 class="mb-1 text-sm font-semibold text-[#FFD700]">{{ __('Load wallet') }}</h3>
    <p class="mb-4 text-xs text-slate-400">
        {{ __('Add or remove balance for this :role account after you receive payment (e.g. mobile money).', ['role' => $user->role?->slug === \App\Models\Role::SLUG_AGENT ? __('agent') : __('buyer')]) }}
    </p>

    @if ($wallet?->is_frozen)
        <p class="mb-4 rounded-lg border border-amber-500/30 bg-amber-500/10 px-3 py-2 text-xs text-amber-200">
            {{ __('This wallet is frozen. Unfreeze it from Wallet controls before crediting.') }}
            <a href="{{ route('admin.wallet.index') }}" class="ml-1 font-medium text-[#FFD700] hover:underline">{{ __('Wallet controls') }}</a>
        </p>
    @endif

    @error('amount')
        <p class="mb-3 text-sm text-red-400">{{ $message }}</p>
    @enderror

    <div class="grid gap-6 lg:grid-cols-2">
        <form method="post" action="{{ route('admin.users.wallet-credit', $user) }}" class="space-y-3">
            @csrf
            <p class="text-xs font-medium uppercase tracking-wide text-slate-500">{{ __('Credit (add funds)') }}</p>
            <div class="flex flex-wrap items-end gap-3">
                <div>
                    <label for="admin_credit_amount" class="mb-1 block text-xs text-slate-500">{{ __('Amount (GHS)') }}</label>
                    <input id="admin_credit_amount" name="amount" type="number" step="0.01" min="0.01" max="1000000" required
                        @disabled($wallet?->is_frozen)
                        value="{{ old('amount') }}"
                        class="w-36 rounded-lg border border-white/10 bg-[#16213E] px-3 py-2 text-sm text-white disabled:opacity-50" />
                </div>
                <div class="min-w-48 flex-1">
                    <label for="admin_credit_note" class="mb-1 block text-xs text-slate-500">{{ __('Note (optional)') }}</label>
                    <input id="admin_credit_note" name="note" type="text" maxlength="2000"
                        @disabled($wallet?->is_frozen)
                        value="{{ old('note') }}"
                        class="w-full rounded-lg border border-white/10 bg-[#16213E] px-3 py-2 text-sm text-white disabled:opacity-50"
                        placeholder="{{ __('e.g. MoMo ref') }}" />
                </div>
                <button type="submit" @disabled($wallet?->is_frozen)
                    class="rounded-lg bg-[#FFD700] px-4 py-2 text-sm font-semibold text-[#1A1A2E] hover:brightness-95 disabled:cursor-not-allowed disabled:opacity-50">
                    {{ __('Credit wallet') }}
                </button>
            </div>
        </form>

        <form method="post" action="{{ route('admin.users.wallet-debit', $user) }}" class="space-y-3"
            onsubmit="return confirm(@json(__('Debit this wallet? This cannot be undone automatically.')))">
            @csrf
            <p class="text-xs font-medium uppercase tracking-wide text-slate-500">{{ __('Debit (remove funds)') }}</p>
            <div class="flex flex-wrap items-end gap-3">
                <div>
                    <label for="admin_debit_amount" class="mb-1 block text-xs text-slate-500">{{ __('Amount (GHS)') }}</label>
                    <input id="admin_debit_amount" name="amount" type="number" step="0.01" min="0.01" max="1000000" required
                        class="w-36 rounded-lg border border-white/10 bg-[#16213E] px-3 py-2 text-sm text-white" />
                </div>
                <div class="min-w-48 flex-1">
                    <label for="admin_debit_note" class="mb-1 block text-xs text-slate-500">{{ __('Note (optional)') }}</label>
                    <input id="admin_debit_note" name="note" type="text" maxlength="2000"
                        class="w-full rounded-lg border border-white/10 bg-[#16213E] px-3 py-2 text-sm text-white"
                        placeholder="{{ __('Reason for debit') }}" />
                </div>
                <button type="submit" class="rounded-lg border border-red-400/50 bg-red-500/20 px-4 py-2 text-sm font-medium text-red-100 hover:bg-red-500/30">
                    {{ __('Debit wallet') }}
                </button>
            </div>
        </form>
    </div>
</div>
