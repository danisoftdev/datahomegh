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
            @error('cancel')
                <p class="mt-4 rounded-lg border border-red-500/40 bg-red-500/10 px-3 py-2 text-sm text-red-200">{{ $message }}</p>
            @enderror
            @if ($order->status === 'PENDING')
                <div class="mt-6 rounded-xl border border-amber-500/30 bg-amber-500/5 p-4">
                    <p class="text-sm text-slate-300">{{ __('Orders are paid from your wallet. You can cancel a pending order — the amount goes back to your wallet and the supplier queue is cleared (status: refunded).') }}</p>
                    <form method="post" action="{{ route('buyer.orders.cancel', $order) }}" class="mt-4" onsubmit="return confirm(@json(__('Cancel this order and refund your wallet?')))">
                        @csrf
                        <button type="submit" class="rounded-lg border border-red-400/60 px-4 py-2 text-sm font-medium text-red-300 hover:bg-red-500/10">{{ __('Cancel order & refund wallet') }}</button>
                    </form>
                </div>
            @endif
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
