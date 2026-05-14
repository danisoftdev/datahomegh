@extends('layouts.agent')

@section('title', __('Order') . ' #' . $order->id)
@section('heading', __('Order') . ' #' . $order->id)

@section('content')
    <div class="mb-6">
        <a href="{{ route('agent.orders.index', request()->query()) }}" class="text-sm text-emerald-400 hover:underline">← {{ __('My orders') }}</a>
    </div>

    <div class="grid gap-6 lg:grid-cols-3">
        <div class="rounded-xl border border-white/10 bg-[#16213E]/80 p-6 lg:col-span-2">
            <dl class="grid gap-3 text-sm sm:grid-cols-2">
                <div><dt class="text-slate-500">{{ __('Status') }}</dt><dd class="font-medium text-white">{{ $order->status }}</dd></div>
                <div><dt class="text-slate-500">{{ __('Amount') }}</dt><dd class="text-emerald-400">{{ number_format((float) $order->amount, 2) }} GHS</dd></div>
                <div><dt class="text-slate-500">{{ __('Network') }}</dt><dd>{{ $order->bundlePackage?->isMtnAfaRegistration() ? __('MTN AFA') : $order->network }}</dd></div>
                <div><dt class="text-slate-500">{{ __('Phone') }}</dt><dd>{{ $order->phone_number }}</dd></div>
                <div><dt class="text-slate-500">{{ (int) $order->user_id === (int) auth()->id() ? __('Your account') : __('Buyer') }}</dt><dd class="font-medium text-white">
                    @if ((int) $order->user_id === (int) auth()->id())
                        {{ __('You') }}
                    @else
                        {{ $order->user?->username }} (#{{ $order->user_id }})
                    @endif
                </dd></div>
                <div class="sm:col-span-2"><dt class="text-slate-500">{{ __('Bundle') }}</dt><dd>{{ $order->bundlePackage?->name }} — {{ $order->bundlePackage?->size_label }}</dd></div>
            </dl>
            @include('orders.partials.afa-registration', ['order' => $order])
        </div>

        <div class="space-y-4">
            <div class="rounded-xl border border-white/10 bg-[#16213E]/80 p-4">
                <h3 class="mb-3 font-semibold text-white">{{ __('Update status') }}</h3>
                <form method="post" action="{{ route('agent.orders.status', $order) }}">
                    @csrf
                    @method('PATCH')
                    <select name="status" class="mb-2 w-full rounded-lg border border-white/10 bg-[#1A1A2E] px-3 py-2 text-sm text-white">
                        <option value="PROCESSING">PROCESSING</option>
                        <option value="SENT">SENT</option>
                        <option value="FAILED">FAILED</option>
                        <option value="REFUNDED">REFUNDED</option>
                    </select>
                    <textarea name="note" rows="2" placeholder="{{ __('Note') }}" class="mb-2 w-full rounded-lg border border-white/10 bg-[#1A1A2E] px-3 py-2 text-sm text-white"></textarea>
                    <input type="hidden" name="visible_to_buyer" value="0" />
                    <label class="mb-2 flex items-center gap-2 text-xs text-slate-400">
                        <input type="checkbox" name="visible_to_buyer" value="1" checked class="size-3 rounded text-emerald-500" />
                        {{ __('Visible to buyer') }}
                    </label>
                    <button type="submit" class="w-full rounded-lg bg-emerald-500 py-2 text-sm font-semibold text-[#0f0f1a]">{{ __('Save') }}</button>
                </form>
            </div>
            <div class="rounded-xl border border-white/10 bg-[#16213E]/80 p-4">
                <h3 class="mb-3 font-semibold text-white">{{ __('Add note') }}</h3>
                <form method="post" action="{{ route('agent.orders.notes', $order) }}">
                    @csrf
                    <textarea name="note" rows="3" required class="mb-2 w-full rounded-lg border border-white/10 bg-[#1A1A2E] px-3 py-2 text-sm text-white" placeholder="{{ __('Internal note') }}"></textarea>
                    <input type="hidden" name="visible_to_buyer" value="0" />
                    <label class="mb-2 flex items-center gap-2 text-xs text-slate-400">
                        <input type="checkbox" name="visible_to_buyer" value="1" class="size-3 rounded text-emerald-500" />
                        {{ __('Visible to buyer') }}
                    </label>
                    <button type="submit" class="w-full rounded-lg border border-white/20 py-2 text-sm text-white hover:bg-white/5">{{ __('Add note') }}</button>
                </form>
            </div>
        </div>
    </div>

    <div class="mt-8 rounded-xl border border-white/10 bg-[#16213E]/80 p-6 shadow-xl sm:p-8">
        @include('orders.partials.status-history-timeline', [
            'histories' => $histories,
            'heading' => __('Timeline'),
            'emptyMessage' => __('No history yet.'),
        ])
    </div>
@endsection
