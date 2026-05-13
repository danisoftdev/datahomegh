@extends('layouts.admin')

@section('title', __('Orders') . ' — ' . config('app.name'))
@section('heading', __('Orders'))

@section('content')
    <div class="mb-6 flex flex-wrap items-center justify-between gap-4">
        <h1 class="text-2xl font-bold text-white">{{ __('Orders') }}</h1>
        <a href="{{ route('admin.orders.export', request()->query()) }}" class="rounded-lg border border-white/20 px-4 py-2 text-sm text-[#FFD700] hover:bg-white/5">{{ __('Export CSV') }}</a>
    </div>

    <form method="get" action="{{ route('admin.orders.index') }}" class="mb-6 grid gap-3 rounded-xl border border-white/10 bg-[#16213E]/60 p-4 sm:grid-cols-2 lg:grid-cols-4 xl:grid-cols-7">
        <div>
            <label class="mb-1 block text-xs text-slate-500">{{ __('Status') }}</label>
            <select name="status" class="w-full rounded-lg border border-white/10 bg-[#1A1A2E] px-2 py-1.5 text-sm text-white">
                <option value="">{{ __('Any') }}</option>
                @foreach (['PENDING', 'PROCESSING', 'SENT', 'FAILED', 'REFUNDED'] as $s)
                    <option value="{{ $s }}" @selected(request('status') === $s)>{{ $s }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label class="mb-1 block text-xs text-slate-500">{{ __('Date from') }}</label>
            <input type="date" name="date_from" value="{{ request('date_from') }}" class="w-full rounded-lg border border-white/10 bg-[#1A1A2E] px-2 py-1.5 text-sm text-white" />
        </div>
        <div>
            <label class="mb-1 block text-xs text-slate-500">{{ __('Date to') }}</label>
            <input type="date" name="date_to" value="{{ request('date_to') }}" class="w-full rounded-lg border border-white/10 bg-[#1A1A2E] px-2 py-1.5 text-sm text-white" />
        </div>
        <div>
            <label class="mb-1 block text-xs text-slate-500">{{ __('Network') }}</label>
            <select name="network" class="w-full rounded-lg border border-white/10 bg-[#1A1A2E] px-2 py-1.5 text-sm text-white">
                <option value="">{{ __('Any') }}</option>
                @foreach (['MTN', 'Telecel', 'AirtelTigo'] as $n)
                    <option value="{{ $n }}" @selected(request('network') === $n)>{{ $n }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label class="mb-1 block text-xs text-slate-500">{{ __('Agent') }}</label>
            <select name="agent_id" class="w-full rounded-lg border border-white/10 bg-[#1A1A2E] px-2 py-1.5 text-sm text-white">
                <option value="">{{ __('Any') }}</option>
                @foreach ($agents as $a)
                    <option value="{{ $a->id }}" @selected((string) request('agent_id') === (string) $a->id)>{{ $a->username }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label class="mb-1 block text-xs text-slate-500">{{ __('User ID') }}</label>
            <input type="number" name="user_id" value="{{ request('user_id') }}" class="w-full rounded-lg border border-white/10 bg-[#1A1A2E] px-2 py-1.5 text-sm text-white" />
        </div>
        <div>
            <label class="mb-1 block text-xs text-slate-500">{{ __('Phone contains') }}</label>
            <input type="text" name="phone" value="{{ request('phone') }}" class="w-full rounded-lg border border-white/10 bg-[#1A1A2E] px-2 py-1.5 text-sm text-white" />
        </div>
        <div class="flex items-end gap-2">
            <button type="submit" class="rounded-lg bg-[#FFD700] px-4 py-2 text-sm font-semibold text-[#1A1A2E]">{{ __('Filter') }}</button>
            <a href="{{ route('admin.orders.index') }}" class="rounded-lg border border-white/20 px-4 py-2 text-sm text-slate-300">{{ __('Reset') }}</a>
        </div>
    </form>

    <form method="post" action="{{ route('admin.orders.bulk-update') }}" class="space-y-4">
        @csrf
        <div class="flex flex-wrap items-center gap-3">
            <select name="status" required class="rounded-lg border border-white/10 bg-[#1A1A2E] px-3 py-2 text-sm text-white">
                <option value="PROCESSING">PROCESSING</option>
                <option value="SENT">SENT</option>
                <option value="FAILED">FAILED</option>
                <option value="REFUNDED">REFUNDED</option>
            </select>
            <input type="text" name="note" placeholder="{{ __('Note (optional)') }}" class="min-w-48 flex-1 rounded-lg border border-white/10 bg-[#1A1A2E] px-3 py-2 text-sm text-white" />
            <label class="flex items-center gap-2 text-sm text-slate-300">
                <input type="hidden" name="visible_to_buyer" value="0" />
                <input type="checkbox" name="visible_to_buyer" value="1" checked class="size-4 rounded border-white/20 text-[#FFD700]" />
                {{ __('Visible to buyer') }}
            </label>
            <button type="submit" class="rounded-lg bg-[#FFD700] px-4 py-2 text-sm font-semibold text-[#1A1A2E]">{{ __('Bulk update') }}</button>
        </div>

        <div class="overflow-x-auto rounded-xl border border-white/10 bg-[#16213E]/80">
            <table class="w-full text-left text-sm text-slate-300">
                <thead class="border-b border-white/10 text-xs uppercase text-slate-500">
                    <tr>
                        <th class="px-3 py-2"><input type="checkbox" class="rounded border-white/20" onclick="document.querySelectorAll('.order-cb').forEach(c => c.checked = this.checked)" /></th>
                        <th class="px-3 py-2">#</th>
                        <th class="px-3 py-2">{{ __('When') }}</th>
                        <th class="px-3 py-2">{{ __('User') }}</th>
                        <th class="px-3 py-2">{{ __('Agent') }}</th>
                        <th class="px-3 py-2">{{ __('Net') }}</th>
                        <th class="px-3 py-2">{{ __('Phone') }}</th>
                        <th class="px-3 py-2">{{ __('Status') }}</th>
                        <th class="px-3 py-2">{{ __('Amt') }}</th>
                        <th class="px-3 py-2"></th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($orders as $o)
                        <tr class="border-b border-white/5">
                            <td class="px-3 py-2"><input class="order-cb" type="checkbox" name="order_ids[]" value="{{ $o->id }}" /></td>
                            <td class="px-3 py-2 font-mono text-xs">{{ $o->id }}</td>
                            <td class="px-3 py-2 whitespace-nowrap">{{ $o->created_at?->format('Y-m-d H:i') }}</td>
                            <td class="px-3 py-2">{{ $o->user?->username }}</td>
                            <td class="px-3 py-2">{{ $o->agent?->username ?? '—' }}</td>
                            <td class="px-3 py-2">{{ $o->network }}</td>
                            <td class="px-3 py-2">{{ $o->phone_number }}</td>
                            <td class="px-3 py-2">{{ $o->status }}</td>
                            <td class="px-3 py-2">{{ number_format((float) $o->amount, 2) }}</td>
                            <td class="px-3 py-2"><a href="{{ route('admin.orders.show', $o) }}" class="text-[#FFD700] hover:underline">{{ __('View') }}</a></td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        {{ $orders->links() }}
    </form>
@endsection
