@props(['status' => ''])

@php
    $s = strtoupper((string) $status);
    $classes = match ($s) {
        'PENDING' => 'bg-yellow-400/15 text-yellow-400 ring-1 ring-inset ring-yellow-400/30',
        'PROCESSING' => 'bg-blue-400/15 text-blue-400 ring-1 ring-inset ring-blue-400/30',
        'SENT' => 'bg-green-400/15 text-green-400 ring-1 ring-inset ring-green-400/30',
        'FAILED' => 'bg-red-400/15 text-red-400 ring-1 ring-inset ring-red-400/30',
        'REFUNDED' => 'bg-purple-400/15 text-purple-400 ring-1 ring-inset ring-purple-400/30',
        default => 'bg-slate-500/15 text-slate-400 ring-1 ring-inset ring-white/10',
    };
@endphp

<span {{ $attributes->merge(['class' => 'inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-bold uppercase tracking-wide '.$classes]) }}>
    {{ $s !== '' ? $s : '—' }}
</span>
