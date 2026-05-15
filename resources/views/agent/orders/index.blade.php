@extends('layouts.agent')

@section('title', __('My orders') . ' — ' . config('app.name'))
@section('heading', __('My orders'))

@section('content')
    <div class="mb-6 flex flex-wrap items-center justify-between gap-4">
        <h1 class="text-2xl font-bold text-white">{{ __('My orders') }}</h1>
    </div>

    <form method="get" action="{{ route('agent.orders.index') }}" class="mb-6 flex flex-wrap gap-3 rounded-xl border border-white/10 bg-[#16213E]/60 p-4">
        <div>
            <label class="mb-1 block text-xs text-slate-500">{{ __('Status') }}</label>
            <select name="status" class="rounded-lg border border-white/10 bg-[#1A1A2E] px-2 py-1.5 text-sm text-white">
                <option value="">{{ __('Any') }}</option>
                @foreach (['PENDING', 'PROCESSING', 'SENT', 'FAILED', 'REFUNDED'] as $s)
                    <option value="{{ $s }}" @selected(request('status') === $s)>{{ $s }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label class="mb-1 block text-xs text-slate-500">{{ __('Phone contains') }}</label>
            <input type="text" name="phone" value="{{ request('phone') }}" class="rounded-lg border border-white/10 bg-[#1A1A2E] px-2 py-1.5 text-sm text-white" />
        </div>
        <div class="flex items-end gap-2">
            <button type="submit" class="rounded-lg bg-emerald-500 px-4 py-2 text-sm font-semibold text-[#0f0f1a]">{{ __('Filter') }}</button>
            <a href="{{ route('agent.orders.index') }}" class="rounded-lg border border-white/20 px-4 py-2 text-sm text-slate-300">{{ __('Reset') }}</a>
        </div>
    </form>

    <form method="post" action="{{ route('agent.orders.bulk-update') }}" class="space-y-4">
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
                <input type="checkbox" name="visible_to_buyer" value="1" checked class="size-4 rounded border-white/20 text-emerald-500" />
                {{ __('Visible to buyer') }}
            </label>
            <button type="submit" class="rounded-lg bg-emerald-500 px-4 py-2 text-sm font-semibold text-[#0f0f1a]">{{ __('Bulk update') }}</button>
        </div>

        <div class="overflow-x-auto rounded-xl border border-white/10 bg-[#16213E]/80">
            <table class="w-full text-left text-sm text-slate-300">
                <thead class="border-b border-white/10 text-xs uppercase text-slate-500">
                    <tr>
                        <th class="px-3 py-2"><input type="checkbox" class="rounded border-white/20" onclick="document.querySelectorAll('.order-cb').forEach(c => c.checked = this.checked)" /></th>
                        <th class="px-3 py-2">#</th>
                        <th class="px-3 py-2">{{ __('When') }}</th>
                        <th class="px-3 py-2">{{ __('Buyer') }}</th>
                        <th class="px-3 py-2">{{ __('Net') }}</th>
                        <th class="px-3 py-2">{{ __('Package') }}</th>
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
                            <td class="px-3 py-2">{{ (int) $o->user_id === (int) auth()->id() ? __('You') : $o->user?->username }}</td>
                            <td class="px-3 py-2">{{ $o->bundlePackage?->isMtnAfaRegistration() ? __('MTN AFA') : $o->network }}</td>
                            <td class="px-3 py-2"><x-order-package-label :order="$o" /></td>
                            <td class="px-3 py-2 whitespace-nowrap">{{ $o->phone_number }}</td>
                            <td class="px-3 py-2"><x-status-badge :status="$o->status" /></td>
                            <td class="px-3 py-2">{{ number_format((float) $o->amount, 2) }}</td>
                            <td class="px-3 py-2"><a href="{{ route('agent.orders.show', $o) }}" class="text-emerald-400 hover:underline">{{ __('View') }}</a></td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        {{ $orders->links() }}
    </form>
@endsection
