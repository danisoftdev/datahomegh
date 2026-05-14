@extends('layouts.buyer')

@section('title', __('Order') . ' #' . $order->id)

@section('content')
    <div class="mx-auto max-w-2xl space-y-8">
        <a href="{{ route('buyer.orders.index') }}" class="text-sm text-[#FFD700] hover:underline">← {{ __('My orders') }}</a>

        <div class="rounded-2xl border border-white/10 bg-[#16213E]/80 p-6 shadow-xl">
            <div class="flex flex-wrap items-start justify-between gap-4">
                <div>
                    <h1 class="text-xl font-bold text-white">{{ __('Order') }} #{{ $order->id }}</h1>
                    <p class="mt-1 text-sm text-slate-400">{{ $order->created_at?->timezone(config('app.timezone'))->format('Y-m-d H:i') }}</p>
                </div>
                <span class="{{ $order->status_color }}">{{ $order->status }}</span>
            </div>

            <dl class="mt-6 grid gap-4 text-sm sm:grid-cols-2">
                <div>
                    <dt class="text-slate-500">{{ __('Network') }}</dt>
                    <dd class="font-medium text-white">{{ $order->bundlePackage?->isMtnAfaRegistration() ? __('MTN AFA') : $order->network }}</dd>
                </div>
                <div>
                    <dt class="text-slate-500">{{ __('Phone') }}</dt>
                    <dd class="font-medium text-white">{{ $order->phone_number }}</dd>
                </div>
                <div>
                    <dt class="text-slate-500">{{ __('Bundle') }}</dt>
                    <dd class="font-medium text-white">{{ $order->bundlePackage?->name }} ({{ $order->bundlePackage?->size_label }})</dd>
                </div>
                <div>
                    <dt class="text-slate-500">{{ __('Amount (GHS)') }}</dt>
                    <dd class="font-semibold text-[#FFD700]">{{ number_format((float) $order->amount, 2) }}</dd>
                </div>
            </dl>
            @include('orders.partials.afa-registration', ['order' => $order])
        </div>

        <div class="rounded-2xl border border-white/10 bg-[#16213E]/80 p-6 shadow-xl sm:p-8">
            @include('orders.partials.status-history-timeline', [
                'histories' => $histories,
                'heading' => __('Status history'),
                'emptyMessage' => __('No updates visible yet.'),
            ])
        </div>
    </div>
@endsection
