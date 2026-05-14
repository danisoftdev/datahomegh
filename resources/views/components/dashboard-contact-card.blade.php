@props([
    'contact',
    'heading',
    'variant' => 'buyer',
])

@php
    /** @var \App\Models\User $contact */
    $links = $contact->supportContactLinks();
    $ring = $variant === 'agent'
        ? 'border-emerald-500/30 from-emerald-500/10 to-[#16213E]/90'
        : 'border-[#FFD700]/30 from-amber-500/10 to-[#16213E]/90';
    $accent = $variant === 'agent' ? 'text-emerald-400' : 'text-[#FFD700]';
    $btnBase = $variant === 'agent'
        ? 'border-emerald-500/40 bg-emerald-500/15 text-emerald-200 hover:bg-emerald-500/25'
        : 'border-[#FFD700]/40 bg-[#FFD700]/10 text-amber-100 hover:bg-[#FFD700]/20';
@endphp

<div class="rounded-2xl border bg-linear-to-br p-px shadow-lg {{ $ring }}">
    <div class="rounded-2xl bg-[#1A1A2E]/95 p-5 sm:p-6">
        <h2 class="text-sm font-semibold uppercase tracking-wider {{ $accent }}">{{ $heading }}</h2>
        @if ($contact->shop_name || $contact->name)
            <p class="mt-1 text-base font-medium text-white">{{ $contact->shop_name ?: $contact->name }}</p>
        @endif
        <div class="mt-4 flex flex-wrap gap-2">
            @if ($links['whatsapp_chat'])
                <a href="{{ $links['whatsapp_chat'] }}" target="_blank" rel="noopener noreferrer" class="inline-flex items-center gap-2 rounded-lg border px-4 py-2 text-sm font-medium {{ $btnBase }}">
                    <svg class="size-4 shrink-0" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.435 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 00-3.48-8.413z"/></svg>
                    {{ __('WhatsApp chat') }}
                </a>
            @endif
            @if ($links['whatsapp_channel'])
                <a href="{{ $links['whatsapp_channel'] }}" target="_blank" rel="noopener noreferrer" class="inline-flex items-center gap-2 rounded-lg border px-4 py-2 text-sm font-medium {{ $btnBase }}">
                    <svg class="size-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.828 10.172a4 4 0 00-5.656 0l-4 4a4 4 0 105.656 5.656l1.102-1.101m-.758-4.899a4 4 0 005.656 0l4-4a4 4 0 00-5.656-5.656l-1.1 1.1"/></svg>
                    {{ __('WhatsApp channel') }}
                </a>
            @endif
            @if ($links['telephone'])
                <a href="{{ $links['telephone'] }}" class="inline-flex items-center gap-2 rounded-lg border border-white/20 bg-white/5 px-4 py-2 text-sm font-medium text-slate-100 hover:bg-white/10">
                    <svg class="size-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z"/></svg>
                    {{ __('Call') }}
                </a>
            @endif
        </div>
    </div>
</div>
