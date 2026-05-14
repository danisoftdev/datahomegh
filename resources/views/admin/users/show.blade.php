@extends('layouts.admin')

@section('title', $user->username . ' — ' . __('Users'))
@section('heading', $user->username)

@section('content')
    @php($plainReset = session()->pull('reset_code_plain'))

    <div class="mb-4 flex flex-wrap gap-3">
        <a href="{{ route('admin.users.index', request()->only(['role', 'status', 'search'])) }}" class="text-sm text-[#FFD700] hover:underline">← {{ __('Users') }}</a>
    </div>

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
                <div><dt class="text-slate-500">{{ __('Wallet balance') }}</dt><dd>{{ $user->wallet ? number_format((float) $user->wallet->balance, 2) : '—' }} GHS</dd></div>
            </dl>

            <div class="mt-6 flex flex-wrap gap-2 border-t border-white/10 pt-6">
                @if ($user->role?->slug === \App\Models\Role::SLUG_AGENT && $user->status === 'pending_payment')
                    <p class="mb-2 w-full text-xs text-amber-200">{{ __('Awaiting registration fee on Paystack. Approve is available only after status becomes pending (payment confirmed). You may still decline this application.') }}</p>
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
                        <h3 class="mb-2 text-sm font-medium text-white">{{ __('Change role') }}</h3>
                        <form method="post" action="{{ route('admin.users.role', $user) }}" class="flex gap-2">
                            @csrf
                            @method('PATCH')
                            <select name="role_id" class="flex-1 rounded-lg border border-white/10 bg-[#1A1A2E] px-2 py-1.5 text-sm text-white">
                                @foreach (\App\Models\Role::query()->where('slug', '!=', \App\Models\Role::SLUG_SUPPLIER)->get() as $r)
                                    <option value="{{ $r->id }}" @selected($user->role_id === $r->id)>{{ $r->name }}</option>
                                @endforeach
                            </select>
                            <button class="rounded-lg bg-[#FFD700] px-3 py-1.5 text-xs font-semibold text-[#1A1A2E]">{{ __('Save') }}</button>
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

    <div class="mt-8 rounded-xl border border-white/10 bg-[#16213E]/80 p-6">
        <h3 class="mb-4 font-semibold text-white">{{ __('Wallet ledger') }}</h3>
        {{ $ledger->links() }}
        <table class="mt-2 w-full text-left text-sm text-slate-300">
            <thead class="text-xs uppercase text-slate-500">
                <tr><th class="py-2">{{ __('Type') }}</th><th class="py-2">{{ __('Amount') }}</th><th class="py-2">{{ __('After') }}</th><th class="py-2">{{ __('Source') }}</th><th class="py-2">{{ __('Ref') }}</th></tr>
            </thead>
            <tbody>
                @foreach ($ledger as $row)
                    <tr class="border-t border-white/5">
                        <td class="py-2">{{ $row->type }}</td>
                        <td class="py-2">{{ $row->amount }}</td>
                        <td class="py-2">{{ $row->balance_after }}</td>
                        <td class="py-2">{{ $row->source }}</td>
                        <td class="py-2 font-mono text-xs">{{ \Illuminate\Support\Str::limit($row->reference ?? '', 16) }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
@endsection
