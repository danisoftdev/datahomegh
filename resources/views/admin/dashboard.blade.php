@extends('layouts.admin')

@section('title', __('Admin dashboard') . ' — ' . config('app.name'))
@section('heading', __('Dashboard'))

@section('content')
    <h1 class="mb-6 text-2xl font-bold text-white">{{ __('Dashboard') }}</h1>

    <div class="mb-8 grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
        <div class="rounded-xl border border-white/10 bg-[#16213E]/80 p-5">
            <p class="text-xs uppercase tracking-wide text-slate-500">{{ __('Orders today') }}</p>
            <p class="mt-1 text-3xl font-semibold text-[#FFD700]">{{ $todayOrdersCount }}</p>
        </div>
        <div class="rounded-xl border border-white/10 bg-[#16213E]/80 p-5">
            <p class="text-xs uppercase tracking-wide text-slate-500">{{ __('SENT revenue (this month) GHS') }}</p>
            <p class="mt-1 text-3xl font-semibold text-[#FFD700]">{{ number_format((float) $sentRevenueMonth, 2) }}</p>
        </div>
        <div class="rounded-xl border border-white/10 bg-[#16213E]/80 p-5">
            <p class="text-xs uppercase tracking-wide text-slate-500">{{ __('Pending / processing') }}</p>
            <p class="mt-1 text-3xl font-semibold text-[#FFD700]">{{ $pendingCount }}</p>
        </div>
        <div class="rounded-xl border border-white/10 bg-[#16213E]/80 p-5">
            <p class="text-xs uppercase tracking-wide text-slate-500">{{ __('Agents') }}</p>
            <p class="mt-1 text-3xl font-semibold text-white">{{ $agentsCount }}</p>
        </div>
        <div class="rounded-xl border border-white/10 bg-[#16213E]/80 p-5">
            <p class="text-xs uppercase tracking-wide text-slate-500">{{ __('Buyers') }}</p>
            <p class="mt-1 text-3xl font-semibold text-white">{{ $buyersCount }}</p>
        </div>
    </div>

    <div class="rounded-xl border border-white/10 bg-[#16213E]/80 p-6">
        <div class="mb-4 flex items-center justify-between">
            <h2 class="text-lg font-semibold text-white">{{ __('Recent orders') }}</h2>
            <a href="{{ route('admin.orders.index') }}" class="text-sm text-[#FFD700] hover:underline">{{ __('View all') }}</a>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full text-left text-sm text-slate-300">
                <thead class="border-b border-white/10 text-xs uppercase text-slate-500">
                    <tr>
                        <th class="py-2 pr-3">#</th>
                        <th class="py-2 pr-3">{{ __('When') }}</th>
                        <th class="py-2 pr-3">{{ __('User') }}</th>
                        <th class="py-2 pr-3">{{ __('Network') }}</th>
                        <th class="py-2 pr-3">{{ __('Phone') }}</th>
                        <th class="py-2 pr-3">{{ __('Status') }}</th>
                        <th class="py-2 pr-3">{{ __('Amount') }}</th>
                        <th class="py-2"></th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($recentOrders as $o)
                        <tr class="border-b border-white/5">
                            <td class="py-2 pr-3 font-mono text-xs">{{ $o->id }}</td>
                            <td class="py-2 pr-3 whitespace-nowrap">{{ $o->created_at?->format('m-d H:i') }}</td>
                            <td class="py-2 pr-3">{{ $o->user?->username }}</td>
                            <td class="py-2 pr-3">{{ $o->network }}</td>
                            <td class="py-2 pr-3">{{ $o->phone_number }}</td>
                            <td class="py-2 pr-3">{{ $o->status }}</td>
                            <td class="py-2 pr-3">{{ number_format((float) $o->amount, 2) }}</td>
                            <td class="py-2"><a href="{{ route('admin.orders.show', $o) }}" class="text-[#FFD700] hover:underline">{{ __('View') }}</a></td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
@endsection
