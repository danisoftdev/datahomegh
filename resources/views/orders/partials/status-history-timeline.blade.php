{{-- Expects: $histories (iterable). Optional: $heading, $emptyMessage --}}

@php
    $heading = $heading ?? __('Status history');
    $emptyMessage = $emptyMessage ?? __('No updates visible yet.');
@endphp

<h2 class="text-xl font-semibold tracking-tight text-white sm:text-2xl">{{ $heading }}</h2>

@if ($histories->isEmpty())
    <p class="mt-6 text-base text-slate-400">{{ $emptyMessage }}</p>
@else
    <ul class="mt-8 space-y-10 sm:space-y-12" role="list">
        @foreach ($histories as $h)
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
            <li class="flex gap-5 sm:gap-7">
                <div class="flex size-12 shrink-0 items-center justify-center rounded-full ring-2 sm:size-14 {{ $iconWrap }}">
                    @switch($h->new_status)
                        @case('PENDING')
                            <svg class="size-5 sm:size-6" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                            @break
                        @case('PROCESSING')
                            <svg class="size-5 sm:size-6" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"/></svg>
                            @break
                        @case('SENT')
                            <svg class="size-5 sm:size-6" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                            @break
                        @case('FAILED')
                            <svg class="size-5 sm:size-6" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                            @break
                        @case('REFUNDED')
                            <svg class="size-5 sm:size-6" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h10a8 8 0 018 8v2M3 10l6 6m-6-6l6-6"/></svg>
                            @break
                        @default
                            <span class="size-2.5 rounded-full bg-current sm:size-3" aria-hidden="true"></span>
                    @endswitch
                </div>
                <div class="min-w-0 flex-1 pt-0.5 sm:pt-1">
                    <p class="text-base font-semibold leading-snug text-white sm:text-lg">
                        {{ $h->new_status }}
                        @if ($h->old_status)
                            <span class="font-medium text-slate-400">({{ __('from') }} {{ $h->old_status }})</span>
                        @endif
                    </p>
                    <p class="mt-2 flex flex-wrap items-center gap-x-3 gap-y-1 text-sm text-slate-400 sm:text-base">
                        <span class="inline-flex items-center gap-2">
                            <svg class="size-4 shrink-0 opacity-80" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                            <span class="tabular-nums">{{ $h->created_at?->timezone(config('app.timezone'))->format('Y-m-d H:i') }}</span>
                        </span>
                        @if ($h->changedBy)
                            <span class="text-slate-500">·</span>
                            <span class="font-medium text-slate-300">{{ $h->changedBy->username }}</span>
                        @endif
                    </p>
                    @if ($h->note)
                        <p class="mt-4 rounded-xl border border-white/10 bg-black/25 px-4 py-3 text-base leading-relaxed text-slate-200 sm:px-5 sm:py-4">{{ $h->note }}</p>
                    @endif
                </div>
            </li>
        @endforeach
    </ul>
@endif
