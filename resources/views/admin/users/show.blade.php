@extends('layouts.admin')

@section('title', $user->username . ' — ' . __('Users'))
@section('heading', $user->username)

@section('content')
    @php($plainReset = session()->pull('reset_code_plain'))

    <div class="mb-4 flex flex-wrap gap-3">
        <a href="{{ route('admin.users.index', request()->only(['role', 'status', 'search'])) }}" class="text-sm text-[#FFD700] hover:underline">← {{ __('Users') }}</a>
    </div>

    @if (session('status'))
        <div class="mb-4 rounded-lg border border-emerald-500/30 bg-emerald-500/10 px-4 py-2 text-sm text-emerald-200">{{ session('status') }}</div>
    @endif

    @if (session('error'))
        <div class="mb-4 rounded-lg border border-red-500/30 bg-red-500/10 px-4 py-2 text-sm text-red-200">{{ session('error') }}</div>
    @endif

    @if ($plainReset)
        <div class="fixed inset-0 z-200 flex items-center justify-center bg-black/80 p-4" x-data="{ open: true }" x-show="open" x-cloak>
            <div class="max-w-md rounded-2xl border border-[#FFD700]/40 bg-[#1A1A2E] p-6 shadow-xl">
                <h3 class="text-lg font-semibold text-[#FFD700]">{{ __('Password reset code') }}</h3>
                <p class="mt-2 text-sm text-slate-400">{{ __('Copy this code now. It will not be shown again.') }}</p>
                <p class="mt-4 select-all rounded-lg bg-black/40 p-4 text-center font-mono text-2xl tracking-widest text-white">{{ $plainReset }}</p>
                <button type="button" class="mt-6 w-full rounded-lg bg-[#FFD700] py-2 text-sm font-semibold text-[#1A1A2E]" @click="open = false">{{ __('Close') }}</button>
            </div>
        </div>
    @endif

    <div class="grid gap-6 lg:grid-cols-3">
        <div class="rounded-xl border border-white/10 bg-[#16213E]/80 p-6 lg:col-span-2">
            <h2 class="mb-4 text-lg font-semibold text-white">{{ __('Profile') }}</h2>
            <dl class="grid gap-2 text-sm sm:grid-cols-2">
                <div><dt class="text-slate-500">{{ __('Name') }}</dt><dd>{{ $user->name }}</dd></div>
                <div><dt class="text-slate-500">{{ __('Email') }}</dt><dd class="flex flex-wrap items-center gap-2">
                    @if ($user->email)
                        <a href="mailto:{{ $user->email }}" class="text-[#FFD700] hover:underline">{{ $user->email }}</a>
                    @else
                        —
                    @endif
                    @if ($user->role?->slug === \App\Models\Role::SLUG_AGENT && $user->status === 'pending' && ! $user->email)
                        <span class="text-xs text-amber-400">({{ __('Required before approval') }})</span>
                    @endif
                </dd></div>
                <div><dt class="text-slate-500">{{ __('Phone') }}</dt><dd>{{ $user->phone }}</dd></div>
                <div><dt class="text-slate-500">{{ __('Role') }}</dt><dd>{{ $user->role?->name }} ({{ $user->role?->slug }})</dd></div>
                <div><dt class="text-slate-500">{{ __('Status') }}</dt><dd>{{ $user->status }}</dd></div>
                <div><dt class="text-slate-500">{{ __('Shop name') }}</dt><dd>{{ $user->shop_name ?? '—' }}</dd></div>
                <div><dt class="text-slate-500">{{ __('Shop slug') }}</dt><dd>{{ $user->shop_slug ?? '—' }}</dd></div>
                <div><dt class="text-slate-500">{{ __('Daily order limit') }}</dt><dd>{{ $user->daily_order_limit ?? '∞' }}</dd></div>
                <div><dt class="text-slate-500">{{ __('Wallet balance') }}</dt><dd>{{ $user->wallet ? number_format((float) $user->wallet->balance, 2) : '—' }} GHS @if ($user->wallet?->is_frozen)<span class="text-amber-400">({{ __('frozen') }})</span>@endif</dd></div>
            </dl>

            @if ($user->role?->slug === \App\Models\Role::SLUG_AGENT)
                <div class="mt-6 rounded-lg border border-white/10 bg-[#1A1A2E]/60 p-4">
                    <h3 class="text-sm font-semibold text-[#FFD700]">{{ __('Shop registration payment (Paystack)') }}</h3>
                    <p class="mt-1 text-xs text-slate-400">{{ __('Fee goes to your Paystack business account — not the agent wallet. Confirmation uses the return link after payment (no Paystack dashboard webhook required).') }}</p>
                    <dl class="mt-3 grid gap-2 text-sm sm:grid-cols-2">
                        <div><dt class="text-slate-500">{{ __('Payment status') }}</dt>
                            <dd>
                                @if ($registrationTransaction?->status === 'success')
                                    <span class="text-emerald-400">{{ __('Paid') }}</span>
                                    @if ($registrationTransaction->paid_at)
                                        <span class="text-slate-400"> · {{ $registrationTransaction->paid_at->format('Y-m-d H:i') }}</span>
                                    @endif
                                @elseif ($registrationTransaction)
                                    <span class="text-amber-300">{{ ucfirst($registrationTransaction->status) }}</span>
                                @else
                                    <span class="text-slate-400">{{ __('No transaction record yet') }}</span>
                                @endif
                            </dd>
                        </div>
                        @if ($registrationTransaction?->reference)
                            <div><dt class="text-slate-500">{{ __('Paystack reference') }}</dt><dd class="font-mono text-xs">{{ $registrationTransaction->reference }}</dd></div>
                        @endif
                        @if ($registrationTransaction?->amount)
                            <div><dt class="text-slate-500">{{ __('Amount recorded') }}</dt><dd>{{ number_format((float) $registrationTransaction->amount, 2) }} GHS</dd></div>
                        @endif
                    </dl>
                    @if (in_array($user->status, ['pending_payment', 'pending'], true) && $registrationTransaction?->status !== 'success')
                        <form method="post" action="{{ route('admin.users.confirm-registration-payment', $user) }}" class="mt-4 flex flex-wrap items-end gap-3">
                            @csrf
                            <div class="min-w-48 flex-1">
                                <label class="mb-1 block text-xs text-slate-500">{{ __('Paystack reference (optional)') }}</label>
                                <input type="text" name="reference" value="{{ $registrationTransaction?->reference }}"
                                    placeholder="{{ __('From agent receipt') }}"
                                    class="w-full rounded-lg border border-white/10 bg-[#16213E] px-3 py-2 text-sm text-white" />
                            </div>
                            <button type="submit" class="rounded-lg bg-[#FFD700] px-4 py-2 text-sm font-semibold text-[#1A1A2E]">
                                {{ __('Verify payment with Paystack') }}
                            </button>
                        </form>
                    @elseif ($user->status === 'pending')
                        <p class="mt-3 text-xs text-emerald-300">{{ __('Registration fee received — you can approve this agent below.') }}</p>
                    @endif
                </div>
            @endif

            @include('admin.users.partials.wallet-load', ['user' => $user])

            <div class="mt-6 flex flex-wrap gap-2 border-t border-white/10 pt-6">
                @if ($user->role?->slug === \App\Models\Role::SLUG_AGENT && $user->status === 'pending_payment')
                    <p class="mb-2 w-full text-xs text-amber-200">{{ __('Waiting for Paystack to confirm the registration fee. If the agent already paid, use “Verify payment with Paystack” above or ask them to open the return link from Paystack.') }}</p>
                @endif
                @if ($user->role?->slug === \App\Models\Role::SLUG_AGENT && in_array($user->status, ['pending', 'pending_payment'], true))
                    <form method="post" action="{{ route('admin.users.decline-agent', $user) }}" onsubmit="return confirm(@json(__('Decline this agent application? They will not be able to sign in.')))">
                        @csrf
                        <button type="submit" class="rounded-lg bg-red-600/90 px-4 py-2 text-sm font-medium text-white hover:bg-red-600">{{ __('Decline') }}</button>
                    </form>
                @endif
                @if ($user->role?->slug === \App\Models\Role::SLUG_AGENT && $user->status === 'pending')
                    <form method="post" action="{{ route('admin.users.approve-agent', $user) }}">
                        @csrf
                        <button type="submit" class="rounded-lg bg-emerald-600 px-4 py-2 text-sm font-medium text-white hover:bg-emerald-500">{{ __('Approve agent') }}</button>
                    </form>
                @endif
                @if ($user->role?->slug === \App\Models\Role::SLUG_AGENT && $user->status === 'active')
                    <form method="post" action="{{ route('admin.users.hold-agent', $user) }}">
                        @csrf
                        <button class="rounded-lg bg-amber-600 px-4 py-2 text-sm font-medium text-white hover:bg-amber-500">{{ __('Hold') }}</button>
                    </form>
                @endif
                @if ($user->role?->slug === \App\Models\Role::SLUG_AGENT && $user->status === 'held')
                    <form method="post" action="{{ route('admin.users.release-agent', $user) }}">
                        @csrf
                        <button class="rounded-lg bg-sky-600 px-4 py-2 text-sm font-medium text-white hover:bg-sky-500">{{ __('Release') }}</button>
                    </form>
                @endif
                <form method="post" action="{{ route('admin.users.reset-code', $user) }}">
                    @csrf
                    <button class="rounded-lg border border-white/20 px-4 py-2 text-sm text-white hover:bg-white/5">{{ __('Issue reset code') }}</button>
                </form>
                @if ($user->id !== auth()->id())
                    <form method="post" action="{{ route('admin.users.destroy', $user) }}" onsubmit="return confirm(@json(__('Permanently delete this user?')))">
                        @csrf
                        @method('DELETE')
                        <button class="rounded-lg bg-red-600/80 px-4 py-2 text-sm text-white hover:bg-red-600">{{ __('Delete') }}</button>
                    </form>
                @endif
            </div>

            @if ($user->id !== auth()->id())
                <div class="mt-6 grid gap-4 border-t border-white/10 pt-6 sm:grid-cols-2">
                    <div>
                        <h3 class="mb-2 text-sm font-medium text-white">{{ __('Promote / change role') }}</h3>
                        <p class="mb-2 text-xs text-slate-500">{{ __('Only you can assign custom roles. They never appear on registration. Agent shops stay intact when promoted.') }}</p>
                        <form method="post" action="{{ route('admin.users.role', $user) }}" class="space-y-3">
                            @csrf
                            @method('PATCH')
                            <select name="role_id" class="w-full rounded-lg border border-white/10 bg-[#1A1A2E] px-2 py-1.5 text-sm text-white">
                                @foreach (\App\Models\Role::query()->where('slug', '!=', \App\Models\Role::SLUG_SUPPLIER)->orderBy('name')->get() as $r)
                                    <option value="{{ $r->id }}" @selected($user->role_id === $r->id)>
                                        {{ $r->name }}
                                        @if ($r->requires_promotion_fee && bccomp($r->promotionFeeAmount(), '0', 2) > 0)
                                            ({{ __('fee') }} {{ number_format((float) $r->promotion_fee, 2) }} GHS)
                                        @endif
                                    </option>
                                @endforeach
                            </select>
                            @error('role_id')
                                <p class="text-sm text-red-400">{{ $message }}</p>
                            @enderror
                            <label class="flex items-center gap-2 text-xs text-slate-400">
                                <input type="hidden" name="waive_promotion_fee" value="0" />
                                <input type="checkbox" name="waive_promotion_fee" value="1" class="size-4 rounded border-white/20" />
                                {{ __('Waive promotion fee (if the role charges one)') }}
                            </label>
                            <button class="rounded-lg bg-[#FFD700] px-3 py-1.5 text-xs font-semibold text-[#1A1A2E]">{{ __('Update role') }}</button>
                        </form>
                    </div>
                    <div>
                        <h3 class="mb-2 text-sm font-medium text-white">{{ __('Daily order limit') }}</h3>
                        <form method="post" action="{{ route('admin.users.daily-limit', $user) }}" class="flex gap-2">
                            @csrf
                            @method('PATCH')
                            <input type="number" name="daily_order_limit" min="0" value="{{ old('daily_order_limit', $user->daily_order_limit) }}" class="w-24 rounded-lg border border-white/10 bg-[#1A1A2E] px-2 py-1.5 text-sm text-white" placeholder="∞" />
                            <button class="rounded-lg bg-[#FFD700] px-3 py-1.5 text-xs font-semibold text-[#1A1A2E]">{{ __('Save') }}</button>
                        </form>
                    </div>
                </div>
            @endif
        </div>
    </div>

    <div class="mt-8 rounded-xl border border-white/10 bg-[#16213E]/80 p-6">
        <h3 class="mb-4 font-semibold text-white">{{ __('Recent orders') }}</h3>
        {{ $orders->links() }}
        <table class="mt-2 w-full text-left text-sm text-slate-300">
            <thead class="text-xs uppercase text-slate-500">
                <tr><th class="py-2">#</th><th class="py-2">{{ __('Status') }}</th><th class="py-2">{{ __('Amount') }}</th><th></th></tr>
            </thead>
            <tbody>
                @foreach ($orders as $o)
                    <tr class="border-t border-white/5">
                        <td class="py-2">{{ $o->id }}</td>
                        <td class="py-2">{{ $o->status }}</td>
                        <td class="py-2">{{ number_format((float) $o->amount, 2) }}</td>
                        <td class="py-2"><a href="{{ route('admin.orders.show', $o) }}" class="text-[#FFD700]">{{ __('View') }}</a></td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>

    <div class="mt-8 rounded-xl border border-white/10 bg-[#16213E]/80 p-6" x-data="{ open: false, detail: null, openDetail(payload) { this.detail = payload; this.open = true; }, closeDetail() { this.open = false; } }">
        <h3 class="mb-4 font-semibold text-white">{{ __('Wallet ledger') }}</h3>
        {{ $ledger->links() }}
        <div class="overflow-x-auto">
            <table class="mt-2 w-full min-w-3xl text-left text-sm text-slate-300">
                <thead class="text-xs uppercase text-slate-500">
                    <tr>
                        <th class="py-2 pr-3">{{ __('Transaction ID') }}</th>
                        <th class="py-2 pr-3">{{ __('Date & time') }}</th>
                        <th class="py-2 pr-3">{{ __('Type') }}</th>
                        <th class="py-2 pr-3">{{ __('Amount') }}</th>
                        <th class="py-2 pr-3">{{ __('After') }}</th>
                        <th class="py-2 pr-3">{{ __('Source') }}</th>
                        <th class="py-2 pr-3">{{ __('Ref') }}</th>
                        <th class="py-2">{{ __('Details') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($ledger as $row)
                        <tr class="border-t border-white/5">
                            <td class="py-2 pr-3 font-mono text-xs text-[#FFD700]">{{ \App\Support\TransactionReceipt::walletId((int) $row->id) }}</td>
                            <td class="py-2 pr-3 whitespace-nowrap">
                                <span class="block">{{ \App\Support\TransactionReceipt::formatDateTimeShort($row->created_at) }}</span>
                            </td>
                            <td class="py-2 pr-3">{{ $row->type }}</td>
                            <td class="py-2 pr-3 tabular-nums">{{ number_format((float) $row->amount, 2) }}</td>
                            <td class="py-2 pr-3 tabular-nums">{{ number_format((float) $row->balance_after, 2) }}</td>
                            <td class="py-2 pr-3">{{ $row->source }}</td>
                            <td class="py-2 pr-3 font-mono text-xs">{{ \Illuminate\Support\Str::limit($row->reference ?? '—', 20) }}</td>
                            <td class="py-2">
                                <button
                                    type="button"
                                    class="rounded-lg border border-white/15 bg-white/5 px-2.5 py-1 text-xs font-medium text-slate-200 hover:bg-white/10"
                                    @click="openDetail(@js(\App\Support\TransactionReceipt::ledgerDetails($row, $user)))"
                                >
                                    {{ __('Details') }}
                                </button>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        @include('partials.wallet-ledger-detail-modal')
    </div>
@endsection
