@extends('layouts.auth')

@section('title', __('Order') . ' #' . $order->id . ' — ' . config('app.name'))

@section('content')
    <div class="w-full max-w-3xl space-y-8">
        <div class="rounded-2xl border border-white/10 bg-[#16213E]/80 p-6 shadow-xl backdrop-blur-sm">
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

        <div class="rounded-2xl border border-white/10 bg-[#16213E]/80 p-6 shadow-xl backdrop-blur-sm sm:p-8">
            @include('orders.partials.status-history-timeline', [
                'histories' => $histories,
                'heading' => __('Timeline'),
                'emptyMessage' => __('No history yet.'),
            ])
        </div>

        @if (auth()->user()->isAgent() || auth()->user()->isSupplier())
            <div class="rounded-2xl border border-white/10 bg-[#16213E]/80 p-6 shadow-xl backdrop-blur-sm">
                <h2 class="text-lg font-semibold text-white">{{ __('Update status') }}</h2>
                <form method="post" action="{{ route('orders.update-status', $order) }}" class="mt-4 space-y-3">
                    @csrf
                    @method('PATCH')
                    <div>
                        <label for="status" class="mb-1 block text-sm text-slate-300">{{ __('New status') }}</label>
                        <select id="status" name="status" required class="w-full rounded-lg border border-white/10 bg-[#1A1A2E] px-3 py-2 text-white">
                            <option value="PROCESSING">PROCESSING</option>
                            <option value="SENT">SENT</option>
                            <option value="FAILED">FAILED</option>
                            @if (auth()->user()->isSupplier())
                                <option value="REFUNDED">REFUNDED</option>
                            @endif
                        </select>
                    </div>
                    <div>
                        <label for="note" class="mb-1 block text-sm text-slate-300">{{ __('Note') }}</label>
                        <textarea id="note" name="note" rows="2" class="w-full rounded-lg border border-white/10 bg-[#1A1A2E] px-3 py-2 text-white"></textarea>
                    </div>
                    <div class="flex items-center gap-2">
                        <input id="visible_to_buyer" name="visible_to_buyer" type="hidden" value="0" />
                        <input id="visible_to_buyer_chk" name="visible_to_buyer" type="checkbox" value="1" class="size-4 rounded border-white/20 bg-[#1A1A2E] text-[#FFD700]" checked />
                        <label for="visible_to_buyer_chk" class="text-sm text-slate-300">{{ __('Visible to buyer') }}</label>
                    </div>
                    <button type="submit" class="rounded-lg bg-[#FFD700] px-4 py-2 text-sm font-semibold text-[#1A1A2E] hover:brightness-95">{{ __('Save') }}</button>
                </form>
            </div>
        @endif

        @php
            $ordersListUrl = match (true) {
                auth()->user()->isBuyer() => route('buyer.orders.index'),
                auth()->user()->isAgent() => route('agent.orders.index'),
                default => route('admin.orders.index'),
            };
        @endphp
        <p class="text-center text-sm text-slate-500">
            <a href="{{ $ordersListUrl }}" class="text-[#FFD700] hover:underline">{{ __('Back to orders') }}</a>
        </p>
    </div>
@endsection
