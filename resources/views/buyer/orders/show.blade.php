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

        <div class="rounded-2xl border border-white/10 bg-[#16213E]/80 p-6 shadow-xl">
            <h2 class="text-lg font-semibold text-white">{{ __('Status history') }}</h2>
            <ol class="relative ml-3 mt-6 border-l border-white/15 pl-8">
                @forelse ($histories as $h)
                    @php
                        $iconWrap = match ($h->new_status) {
                            'PENDING' => 'bg-amber-500/20 text-amber-300 ring-amber-400/40',
                            'PROCESSING' => 'bg-sky-500/20 text-sky-300 ring-sky-400/40',
                            'SENT' => 'bg-emerald-500/20 text-emerald-300 ring-emerald-400/40',
                            'FAILED' => 'bg-red-500/20 text-red-300 ring-red-400/40',
                            'REFUNDED' => 'bg-violet-500/20 text-violet-300 ring-violet-400/40',
                            default => 'bg-slate-500/20 text-slate-300 ring-white/20',
                        };
                    @endphp
                    <li class="relative mb-10 last:mb-0">
                        <span class="absolute -inset-s-6 top-0 flex size-9 -translate-x-px items-center justify-center rounded-full ring-2 {{ $iconWrap }}">
                            @switch($h->new_status)
                                @case('PENDING')
                                    <svg class="size-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                                    @break
                                @case('PROCESSING')
                                    <svg class="size-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"/></svg>
                                    @break
                                @case('SENT')
                                    <svg class="size-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                                    @break
                                @case('FAILED')
                                    <svg class="size-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                                    @break
                                @case('REFUNDED')
                                    <svg class="size-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h10a8 8 0 018 8v2M3 10l6 6m-6-6l6-6"/></svg>
                                    @break
                                @default
                                    <span class="size-2 rounded-full bg-current"></span>
                            @endswitch
                        </span>
                        <div>
                            <p class="text-sm font-semibold text-white">
                                {{ $h->new_status }}
                                @if ($h->old_status)
                                    <span class="font-normal text-slate-500">({{ __('from') }} {{ $h->old_status }})</span>
                                @endif
                            </p>
                            <p class="mt-1 flex flex-wrap items-center gap-x-2 text-xs text-slate-500">
                                <span class="inline-flex items-center gap-1">
                                    <svg class="size-3.5 opacity-70" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                                    {{ $h->created_at?->timezone(config('app.timezone'))->format('Y-m-d H:i') }}
                                </span>
                                @if ($h->changedBy)
                                    <span>· {{ $h->changedBy->username }}</span>
                                @endif
                            </p>
                            @if ($h->note)
                                <p class="mt-2 rounded-lg border border-white/5 bg-black/20 px-3 py-2 text-sm text-slate-300">{{ $h->note }}</p>
                            @endif
                        </div>
                    </li>
                @empty
                    <li class="text-sm text-slate-500">{{ __('No updates visible yet.') }}</li>
                @endforelse
            </ol>
        </div>
    </div>
@endsection
