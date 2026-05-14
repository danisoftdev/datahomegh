@extends('layouts.buyer')

@section('title', __('My orders') . ' — ' . config('app.name'))

@section('content')
    @php
        $tabs = [
            '' => __('All'),
            'PENDING' => 'PENDING',
            'PROCESSING' => 'PROCESSING',
            'SENT' => 'SENT',
            'FAILED' => 'FAILED',
            'REFUNDED' => 'REFUNDED',
        ];
    @endphp

    <div class="mb-6 flex flex-wrap items-center justify-between gap-4">
        <h1 class="text-2xl font-bold text-white">{{ __('My orders') }}</h1>
        <a href="{{ route('buyer.orders.create') }}" class="rounded-lg bg-[#FFD700] px-4 py-2 text-sm font-semibold text-[#1A1A2E] hover:brightness-95">{{ __('New order') }}</a>
    </div>

    <div class="mb-6 flex flex-wrap gap-2 border-b border-white/10 pb-1">
        @foreach ($tabs as $value => $label)
            <a
                href="{{ route('buyer.orders.index', $value === '' ? [] : ['status' => $value]) }}"
                class="{{ ($currentStatus === '' && $value === '') || $currentStatus === $value ? 'border-b-2 border-[#FFD700] text-[#FFD700]' : 'text-slate-400 hover:text-white' }} -mb-px px-3 py-2 text-sm font-medium"
            >{{ $label }}</a>
        @endforeach
    </div>

    <div class="overflow-hidden rounded-xl border border-white/10 bg-[#16213E]/80">
        <table class="w-full text-left text-sm text-slate-300">
            <thead class="border-b border-white/10 text-xs uppercase text-slate-500">
                <tr>
                    <th class="px-4 py-3">{{ __('ID') }}</th>
                    <th class="px-4 py-3">{{ __('Network') }}</th>
                    <th class="px-4 py-3">{{ __('Phone') }}</th>
                    <th class="px-4 py-3">{{ __('Bundle') }}</th>
                    <th class="px-4 py-3">{{ __('Amount') }}</th>
                    <th class="px-4 py-3">{{ __('Status') }}</th>
                    <th class="px-4 py-3"></th>
                </tr>
            </thead>
            <tbody>
                @forelse ($orders as $order)
                    <tr class="border-b border-white/5">
                        <td class="px-4 py-3 font-mono text-xs">#{{ $order->id }}</td>
                        <td class="px-4 py-3">{{ $order->bundlePackage?->isMtnAfaRegistration() ? __('MTN AFA') : $order->network }}</td>
                        <td class="px-4 py-3">{{ $order->phone_number }}</td>
                        <td class="px-4 py-3">{{ $order->bundlePackage?->name ?? '—' }}</td>
                        <td class="px-4 py-3">{{ number_format((float) $order->amount, 2) }}</td>
                        <td class="px-4 py-3"><span class="{{ $order->status_color }}">{{ $order->status }}</span></td>
                        <td class="px-4 py-3"><a href="{{ route('buyer.orders.show', $order) }}" class="text-[#FFD700] hover:underline">{{ __('View') }}</a></td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="7" class="px-4 py-8 text-center text-slate-500">{{ __('No orders in this view.') }}</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div class="mt-4 text-slate-500">{{ $orders->links() }}</div>
@endsection
