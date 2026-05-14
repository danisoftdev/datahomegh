@props(['variant' => 'default'])

@php
    $ring = match ($variant) {
        'emerald' => 'ring-emerald-500/30 focus:ring-emerald-500/50',
        'gold' => 'ring-primary/40 focus:ring-primary/60',
        default => 'ring-white/10 focus:ring-white/20',
    };
    $badge = match ($variant) {
        'emerald' => 'bg-emerald-500 text-white',
        'gold' => 'bg-primary text-dark',
        default => 'bg-rose-500 text-white',
    };
@endphp

@php
    $__notificationBellConfig = [
        'unreadUrl' => route('notifications.unread-count'),
        'recentUrl' => route('notifications.recent'),
        'markReadUrl' => route('notifications.mark-read'),
        'pollMs' => 30000,
    ];
@endphp

<div
    class="relative shrink-0"
    x-data='notificationBell({{ json_encode($__notificationBellConfig) }})'
    @click.outside="open = false"
>
    <button
        type="button"
        @click.stop="open = !open"
        class="relative rounded-lg p-2 text-slate-300 hover:bg-white/10 focus:outline-none focus:ring-2 {{ $ring }}"
        aria-label="{{ __('Notifications') }}"
    >
        <svg class="size-6" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"/>
        </svg>
        <span
            x-show="unread > 0"
            x-cloak
            class="absolute -right-0.5 -top-0.5 flex min-h-4.5 min-w-4.5 items-center justify-center rounded-full px-1 text-[10px] font-bold {{ $badge }}"
            x-text="unread > 99 ? '99+' : unread"
        ></span>
    </button>

    <div
        x-show="open"
        x-cloak
        x-transition
        @click.stop
        class="absolute right-0 z-50 mt-2 w-[min(22rem,calc(100vw-1.25rem))] min-w-[18rem] max-w-[calc(100vw-1rem)] overflow-hidden rounded-xl border border-white/10 bg-navy text-left shadow-xl"
        style="display: none;"
    >
        <div class="flex items-center justify-between gap-3 border-b border-white/10 px-4 py-3">
            <span class="text-sm font-semibold text-white">{{ __('Notifications') }}</span>
            <button type="button" @click="markAllRead()" class="shrink-0 text-xs font-medium text-primary hover:underline">
                {{ __('Mark all read') }}
            </button>
        </div>
        <div class="max-h-80 overflow-y-auto overflow-x-hidden">
            <p x-show="loading" x-cloak class="px-4 py-4 text-sm text-slate-500" style="display: none;">{{ __('Loading…') }}</p>
            <p x-show="!loading && recent.length === 0" x-cloak class="px-4 py-4 text-sm text-slate-500" style="display: none;">{{ __('No notifications yet.') }}</p>
            <template x-for="n in recent" :key="n.id">
                <div class="border-b border-white/5 px-4 py-3 last:border-b-0">
                    <div class="flex items-start justify-between gap-3">
                        <span class="min-w-0 flex-1 break-words font-medium leading-snug text-white" x-text="n.title"></span>
                        <span class="shrink-0 whitespace-nowrap text-xs text-slate-500" x-text="timeAgo(n.created_at)"></span>
                    </div>
                    <p class="mt-1 line-clamp-3 break-words text-sm leading-relaxed text-slate-400" x-text="n.message"></p>
                </div>
            </template>
        </div>
        <a
            href="{{ route('notifications.index') }}"
            class="block border-t border-white/10 bg-white/5 px-4 py-3 text-center text-sm font-medium text-primary hover:bg-white/10"
        >
            {{ __('View all') }}
        </a>
    </div>

    <div
        x-show="alertModal"
        x-cloak
        class="fixed inset-0 z-200 flex flex-col items-center justify-center bg-black/85 p-4 sm:p-8"
        style="display: none;"
        role="dialog"
        aria-modal="true"
        @click.self="dismissAlert()"
    >
        <div class="max-h-[90vh] w-full max-w-lg overflow-y-auto rounded-xl border border-red-500/40 bg-dark p-6 shadow-2xl" @click.stop>
            <p class="text-xs font-semibold uppercase tracking-wide text-red-400">{{ __('Alert') }}</p>
            <h3 class="mt-2 text-lg font-bold text-white" x-text="alertModal?.title"></h3>
            <p class="mt-3 text-slate-300" x-text="alertModal?.message"></p>
            <div class="mt-6 flex flex-wrap justify-end gap-2">
                <button type="button" @click="dismissAlert()" class="rounded-lg border border-white/20 px-4 py-2 text-sm text-slate-300 hover:bg-white/5">
                    {{ __('Dismiss') }}
                </button>
                <button type="button" @click="markAllRead()" class="rounded-lg bg-primary px-4 py-2 text-sm font-semibold text-dark">
                    {{ __('Mark read') }}
                </button>
            </div>
        </div>
    </div>
</div>
