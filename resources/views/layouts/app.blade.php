<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="dark">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>@yield('title', config('app.name'))</title>

    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=instrument-sans:400,500,600,700" rel="stylesheet" />

    @if (file_exists(public_path('build/manifest.json')) || file_exists(public_path('hot')))
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    @endif
    <style>[x-cloak]{display:none !important}</style>
    @stack('head')
</head>
@php
    $user = auth()->user()?->loadMissing(['wallet', 'role']);
    $navVariant = trim($__env->yieldContent('nav_variant')) ?: (match ($user?->role?->slug ?? '') {
        \App\Models\Role::SLUG_AGENT => 'agent',
        default => 'buyer',
    });
    $homeUrl = trim($__env->yieldContent('home_url')) ?: match ($navVariant) {
        'agent' => route('agent.dashboard'),
        default => route('buyer.dashboard'),
    };
    $profileUrl = trim($__env->yieldContent('profile_url')) ?: match ($navVariant) {
        'agent' => route('agent.profile.edit'),
        default => route('buyer.profile.edit'),
    };
@endphp
<body class="min-h-screen bg-dark pb-20 font-sans text-slate-200 antialiased md:pb-0">
    <div id="app-shell" class="flex min-h-screen flex-col" x-data="{ mobileDrawer: false }" @keydown.escape.window="mobileDrawer = false">
        {{-- Mobile slide-in (agent + non-buyer wallet) --}}
        @if ($navVariant !== 'buyer')
            <div x-show="mobileDrawer" x-cloak class="fixed inset-0 z-50 md:hidden" style="display: none;">
                <div class="absolute inset-0 bg-black/60" @click="mobileDrawer = false" aria-hidden="true"></div>
                <div class="absolute left-0 top-0 flex h-full w-[min(20rem,88vw)] flex-col border-r border-white/10 bg-navy shadow-2xl" role="dialog" aria-modal="true">
                    <div class="flex items-center justify-between border-b border-white/10 px-4 py-3">
                        <span class="font-semibold text-primary">{{ config('app.name') }}</span>
                        <button type="button" class="rounded-lg p-2 text-slate-400 hover:bg-white/10" @click="mobileDrawer = false" aria-label="{{ __('Close') }}">
                            <svg class="size-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                        </button>
                    </div>
                    <nav class="flex-1 space-y-1 overflow-y-auto p-3 text-sm">
                        @if ($navVariant === 'agent')
                            @php($r = request())
                            <a href="{{ route('agent.dashboard') }}" @click="mobileDrawer=false" class="{{ $r->routeIs('agent.dashboard') ? 'bg-white/10 text-emerald-400' : 'text-slate-300 hover:bg-white/5' }} block rounded-lg px-3 py-2">{{ __('Dashboard') }}</a>
                            <a href="{{ route('agent.orders.index') }}" @click="mobileDrawer=false" class="{{ $r->routeIs('agent.orders.*') ? 'bg-white/10 text-emerald-400' : 'text-slate-300 hover:bg-white/5' }} block rounded-lg px-3 py-2">{{ __('My orders') }}</a>
                            <a href="{{ route('agent.buyers.index') }}" @click="mobileDrawer=false" class="{{ $r->routeIs('agent.buyers.*') ? 'bg-white/10 text-emerald-400' : 'text-slate-300 hover:bg-white/5' }} block rounded-lg px-3 py-2">{{ __('My buyers') }}</a>
                            <a href="{{ route('agent.bundles.index') }}" @click="mobileDrawer=false" class="{{ $r->routeIs('agent.bundles.*') ? 'bg-white/10 text-emerald-400' : 'text-slate-300 hover:bg-white/5' }} block rounded-lg px-3 py-2">{{ __('My bundles') }}</a>
                            <a href="{{ route('wallet.index') }}" @click="mobileDrawer=false" class="{{ $r->routeIs('wallet.*') ? 'bg-white/10 text-emerald-400' : 'text-slate-300 hover:bg-white/5' }} block rounded-lg px-3 py-2">{{ __('Wallet') }}</a>
                            <a href="{{ route('agent.profile.edit') }}" @click="mobileDrawer=false" class="{{ $r->routeIs('agent.profile.*') ? 'bg-white/10 text-emerald-400' : 'text-slate-300 hover:bg-white/5' }} block rounded-lg px-3 py-2">{{ __('Profile') }}</a>
                        @endif
                    </nav>
                </div>
            </div>
        @endif

        @include('layouts.partials.app-topbar', [
            'user' => $user,
            'navVariant' => $navVariant,
            'homeUrl' => $homeUrl,
            'profileUrl' => $profileUrl,
        ])

        <main class="mx-auto w-full max-w-6xl flex-1 px-4 py-8">
            @if (session('status'))
                <div class="mb-4 rounded-lg border border-emerald-500/30 bg-emerald-500/10 px-4 py-2 text-sm text-emerald-200">{{ session('status') }}</div>
            @endif
            @if (session('error'))
                <div class="mb-4 rounded-lg border border-red-500/30 bg-red-500/10 px-4 py-2 text-sm text-red-200">{{ session('error') }}</div>
            @endif
            @if ($errors->any())
                <div class="mb-4 rounded-lg border border-red-500/30 bg-red-500/10 px-4 py-2 text-sm text-red-200">
                    <ul class="list-inside list-disc">
                        @foreach ($errors->all() as $e)
                            <li>{{ $e }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            @yield('content')
        </main>

        @if ($navVariant === 'buyer')
            <nav class="fixed bottom-0 left-0 right-0 z-40 flex border-t border-white/10 bg-navy/95 pb-[env(safe-area-inset-bottom)] backdrop-blur md:hidden" aria-label="{{ __('Primary') }}">
                @php($r = request())
                <a href="{{ route('buyer.dashboard') }}" class="flex flex-1 flex-col items-center gap-0.5 py-2 text-[10px] font-medium {{ $r->routeIs('buyer.dashboard') ? 'text-primary' : 'text-slate-400' }}">
                    <svg class="size-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"/></svg>
                    {{ __('Home') }}
                </a>
                <a href="{{ route('buyer.orders.create') }}" class="flex flex-1 flex-col items-center gap-0.5 py-2 text-[10px] font-medium {{ $r->routeIs('buyer.orders.create') ? 'text-primary' : 'text-slate-400' }}">
                    <svg class="size-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                    {{ __('New') }}
                </a>
                <a href="{{ route('buyer.orders.index') }}" class="flex flex-1 flex-col items-center gap-0.5 py-2 text-[10px] font-medium {{ $r->routeIs('buyer.orders.index') || ($r->routeIs('buyer.orders.*') && ! $r->routeIs('buyer.orders.create')) ? 'text-primary' : 'text-slate-400' }}">
                    <svg class="size-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/></svg>
                    {{ __('Orders') }}
                </a>
                <a href="{{ route('buyer.profile.edit') }}" class="flex flex-1 flex-col items-center gap-0.5 py-2 text-[10px] font-medium {{ $r->routeIs('buyer.profile.*') ? 'text-primary' : 'text-slate-400' }}">
                    <svg class="size-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/></svg>
                    {{ __('Profile') }}
                </a>
                <a href="{{ route('wallet.index') }}" class="flex flex-1 flex-col items-center gap-0.5 py-2 text-[10px] font-medium {{ $r->routeIs('wallet.*') ? 'text-primary' : 'text-slate-400' }}">
                    <svg class="size-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z"/></svg>
                    {{ __('Wallet') }}
                </a>
            </nav>
        @endif
    </div>
    <x-fcm-init />
    @stack('scripts')
</body>
</html>
