@extends('layouts.admin')

@section('title', __('Users') . ' — ' . config('app.name'))
@section('heading', __('Users'))

@section('content')
    <div class="mb-6 flex flex-wrap items-center justify-between gap-4">
        <h1 class="text-2xl font-bold text-white">{{ __('Users') }}</h1>
        <a href="{{ route('admin.users.create-agent') }}" class="rounded-lg bg-[#FFD700] px-4 py-2 text-sm font-semibold text-[#1A1A2E] hover:brightness-95">
            {{ __('Create agent') }}
        </a>
    </div>

    <form method="get" class="mb-6 flex flex-wrap gap-3 rounded-xl border border-white/10 bg-[#16213E]/60 p-4">
        <select name="role" class="rounded-lg border border-white/10 bg-[#1A1A2E] px-3 py-2 text-sm text-white">
            <option value="">{{ __('All roles') }}</option>
            @foreach (\App\Models\Role::query()->where('slug', '!=', \App\Models\Role::SLUG_SUPPLIER)->orderBy('name')->get() as $filterRole)
                <option value="{{ $filterRole->slug }}" @selected(request('role') === $filterRole->slug)>{{ $filterRole->name }}</option>
            @endforeach
        </select>
        <select name="status" class="rounded-lg border border-white/10 bg-[#1A1A2E] px-3 py-2 text-sm text-white">
            <option value="">{{ __('Any status') }}</option>
            @foreach (['pending_payment', 'pending', 'active', 'held', 'declined', 'deleted'] as $st)
                <option value="{{ $st }}" @selected(request('status') === $st)>{{ $st }}</option>
            @endforeach
        </select>
        <input type="search" name="search" value="{{ request('search') }}" placeholder="{{ __('Search…') }}" class="min-w-48 flex-1 rounded-lg border border-white/10 bg-[#1A1A2E] px-3 py-2 text-sm text-white" />
        <button type="submit" class="rounded-lg bg-[#FFD700] px-4 py-2 text-sm font-semibold text-[#1A1A2E]">{{ __('Filter') }}</button>
    </form>

    <div class="overflow-x-auto rounded-xl border border-white/10 bg-[#16213E]/80">
        <table class="w-full text-left text-sm text-slate-300">
            <thead class="border-b border-white/10 text-xs uppercase text-slate-500">
                <tr>
                    <th class="px-3 py-2">#</th>
                    <th class="px-3 py-2">{{ __('Username') }}</th>
                    <th class="px-3 py-2">{{ __('Role') }}</th>
                    <th class="px-3 py-2">{{ __('Status') }}</th>
                    <th class="px-3 py-2">{{ __('Shop') }}</th>
                    <th class="px-3 py-2">{{ __('Wallet') }}</th>
                    <th class="px-3 py-2"></th>
                </tr>
            </thead>
            <tbody>
                @foreach ($users as $u)
                    <tr class="border-b border-white/5">
                        <td class="px-3 py-2">{{ $u->id }}</td>
                        <td class="px-3 py-2">{{ $u->username }}</td>
                        <td class="px-3 py-2">{{ $u->role?->slug }}</td>
                        <td class="px-3 py-2">{{ $u->status }}</td>
                        <td class="px-3 py-2">{{ $u->shop_name ?? '—' }}</td>
                        <td class="px-3 py-2">
                            @if ($u->wallet)
                                <span class="font-medium text-emerald-300">{{ number_format((float) $u->wallet->balance, 2) }} GHS</span>
                                @if ($u->wallet->is_frozen)
                                    <span class="ml-1 text-xs text-amber-400">({{ __('frozen') }})</span>
                                @endif
                            @else
                                —
                            @endif
                        </td>
                        <td class="px-3 py-2"><a href="{{ route('admin.users.show', $u) }}" class="text-[#FFD700] hover:underline">{{ __('View') }}</a></td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
    {{ $users->links() }}
@endsection
