@php
    /** @var \App\Models\User $user */
    $balanceFormatted = $user?->wallet ? number_format((float) $user->wallet->balance, 2) : '0.00';
    $avatarUrl = null;
    if ($user?->profile_picture) {
        $avatarUrl = \Illuminate\Support\Str::startsWith($user->profile_picture, ['http://', 'https://'])
            ? $user->profile_picture
            : \Illuminate\Support\Facades\Storage::disk('public')->url($user->profile_picture);
    }
    $initials = strtoupper(collect(preg_split('/\s+/', (string) ($user?->name ?? $user?->username ?? 'U')))->map(fn ($p) => mb_substr($p, 0, 1))->take(2)->implode(''));
@endphp

<header class="sticky top-0 z-40 border-b border-white/10 bg-dark/95 text-slate-200 backdrop-blur supports-backdrop-filter:bg-dark/90">
    <div class="mx-auto flex max-w-6xl items-center justify-between gap-3 px-4 py-3">
        <div class="flex min-w-0 flex-1 items-center gap-2">
            @if ($navVariant !== 'buyer')
                <button type="button" class="rounded-lg p-2 text-slate-300 hover:bg-white/10 md:hidden" @click="mobileDrawer = true" aria-label="{{ __('Menu') }}">
                    <svg class="size-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"/></svg>
                </button>
            @endif
            <a href="{{ $homeUrl }}" class="truncate font-semibold text-primary">{{ config('app.name') }}</a>
        </div>

        <nav class="hidden flex-1 items-center justify-center gap-1 text-sm md:flex">
            @if ($navVariant === 'buyer')
                @php($r = request())
                <a href="{{ route('buyer.dashboard') }}" class="{{ $r->routeIs('buyer.dashboard') ? 'bg-white/10 text-primary' : 'text-slate-300 hover:bg-white/5' }} rounded-lg px-3 py-2">{{ __('Home') }}</a>
                <a href="{{ route('buyer.orders.create') }}" class="{{ $r->routeIs('buyer.orders.create') ? 'bg-white/10 text-primary' : 'text-slate-300 hover:bg-white/5' }} rounded-lg px-3 py-2">{{ __('New order') }}</a>
                <a href="{{ route('buyer.orders.index') }}" class="{{ $r->routeIs('buyer.orders.index') || ($r->routeIs('buyer.orders.*') && ! $r->routeIs('buyer.orders.create')) ? 'bg-white/10 text-primary' : 'text-slate-300 hover:bg-white/5' }} rounded-lg px-3 py-2">{{ __('Orders') }}</a>
                <a href="{{ route('wallet.index') }}" class="{{ $r->routeIs('wallet.*') ? 'bg-white/10 text-primary' : 'text-slate-300 hover:bg-white/5' }} rounded-lg px-3 py-2">{{ __('Wallet') }}</a>
            @elseif ($navVariant === 'agent')
                @php($r = request())
                <a href="{{ route('agent.dashboard') }}" class="{{ $r->routeIs('agent.dashboard') ? 'bg-white/10 text-emerald-400' : 'text-slate-300 hover:bg-white/5' }} rounded-lg px-3 py-2">{{ __('Dashboard') }}</a>
                <a href="{{ route('agent.orders.index') }}" class="{{ $r->routeIs('agent.orders.*') ? 'bg-white/10 text-emerald-400' : 'text-slate-300 hover:bg-white/5' }} rounded-lg px-3 py-2">{{ __('Orders') }}</a>
                <a href="{{ route('agent.buyers.index') }}" class="{{ $r->routeIs('agent.buyers.*') ? 'bg-white/10 text-emerald-400' : 'text-slate-300 hover:bg-white/5' }} rounded-lg px-3 py-2">{{ __('Buyers') }}</a>
                <a href="{{ route('agent.bundles.index') }}" class="{{ $r->routeIs('agent.bundles.*') ? 'bg-white/10 text-emerald-400' : 'text-slate-300 hover:bg-white/5' }} rounded-lg px-3 py-2">{{ __('Bundles') }}</a>
                <a href="{{ route('wallet.index') }}" class="{{ $r->routeIs('wallet.*') ? 'bg-white/10 text-emerald-400' : 'text-slate-300 hover:bg-white/5' }} rounded-lg px-3 py-2">{{ __('Wallet') }}</a>
            @endif
        </nav>

        <div class="flex shrink-0 items-center gap-1 sm:gap-2">
            @if ($user?->wallet)
                <div class="hidden items-center gap-1 rounded-lg border border-white/10 bg-navy/80 px-2 py-1.5 text-xs sm:flex sm:text-sm" x-data="{ revealed: false }">
                    <span class="font-mono tabular-nums text-primary" x-show="revealed" x-cloak>{{ $balanceFormatted }} GHS</span>
                    <span class="font-mono tabular-nums tracking-widest text-slate-400" x-show="!revealed">••••••</span>
                    <button type="button" class="rounded p-1 text-slate-400 hover:bg-white/10 hover:text-primary" @click="revealed = !revealed" :aria-pressed="revealed" title="{{ __('Show or hide balance') }}">
                        <svg x-show="!revealed" class="size-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>
                        <svg x-show="revealed" x-cloak class="size-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.88 9.88l-3.29-3.29m7.532 7.532l3.29 3.29M3 3l3.59 3.59m0 0A9.953 9.953 0 0112 5c4.478 0 8.268 2.943 9.543 7a10.025 10.025 0 01-4.132 5.411m0 0L21 21"/></svg>
                    </button>
                </div>
            @endif

            <x-notification-bell :variant="$navVariant === 'agent' ? 'emerald' : 'gold'" />

            <div class="relative" x-data="{ open: false }" @click.outside="open = false">
                <button type="button" @click="open = !open" class="flex items-center gap-2 rounded-lg p-1 hover:bg-white/10" aria-expanded="false" :aria-expanded="open">
                    @if ($avatarUrl)
                        <img src="{{ $avatarUrl }}" alt="" class="size-9 rounded-full border border-white/10 object-cover" />
                    @else
                        <span class="flex size-9 items-center justify-center rounded-full border border-white/10 bg-navy text-xs font-bold text-primary">{{ $initials }}</span>
                    @endif
                    <svg class="hidden size-4 text-slate-400 sm:block" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
                </button>
                <div x-show="open" x-cloak x-transition class="absolute right-0 z-50 mt-2 w-48 overflow-hidden rounded-xl border border-white/10 bg-navy py-1 shadow-xl" style="display: none;">
                    <a href="{{ $profileUrl }}" class="block px-4 py-2 text-sm text-slate-200 hover:bg-white/5">{{ __('Profile') }}</a>
                    <form method="post" action="{{ route('logout') }}">
                        @csrf
                        <button type="submit" class="w-full px-4 py-2 text-left text-sm text-slate-400 hover:bg-white/5">{{ __('Log out') }}</button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</header>
