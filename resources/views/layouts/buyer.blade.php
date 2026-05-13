<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="dark">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>@yield('title', __('Buyer') . ' — ' . config('app.name'))</title>

    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=instrument-sans:400,500,600,700" rel="stylesheet" />

    @if (file_exists(public_path('build/manifest.json')) || file_exists(public_path('hot')))
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    @endif
    <style>[x-cloak]{display:none !important}</style>
</head>
<body class="min-h-screen bg-[#0f0f1a] font-sans text-slate-200 antialiased">
    <div class="flex min-h-screen flex-col" x-data="{ mobileNav: false }">
        <header class="sticky top-0 z-40 border-b border-white/10 bg-[#1A1A2E]/95 backdrop-blur">
            <div class="mx-auto flex max-w-6xl items-center justify-between gap-4 px-4 py-3">
                <a href="{{ route('buyer.dashboard') }}" class="font-semibold text-[#FFD700]">{{ config('app.name') }}</a>
                <div class="flex items-center gap-1 sm:gap-2">
                    <nav class="hidden items-center gap-1 text-sm md:flex">
                        @php($r = request())
                        <a href="{{ route('buyer.dashboard') }}" class="{{ $r->routeIs('buyer.dashboard') ? 'bg-white/10 text-[#FFD700]' : 'text-slate-300 hover:bg-white/5' }} rounded-lg px-3 py-2">{{ __('Dashboard') }}</a>
                        <a href="{{ route('buyer.orders.index') }}" class="{{ $r->routeIs('buyer.orders.*') && ! $r->routeIs('buyer.orders.create') ? 'bg-white/10 text-[#FFD700]' : 'text-slate-300 hover:bg-white/5' }} rounded-lg px-3 py-2">{{ __('Orders') }}</a>
                        <a href="{{ route('buyer.orders.create') }}" class="{{ $r->routeIs('buyer.orders.create') ? 'bg-white/10 text-[#FFD700]' : 'text-slate-300 hover:bg-white/5' }} rounded-lg px-3 py-2">{{ __('New order') }}</a>
                        <a href="{{ route('wallet.index') }}" class="{{ $r->routeIs('wallet.*') ? 'bg-white/10 text-[#FFD700]' : 'text-slate-300 hover:bg-white/5' }} rounded-lg px-3 py-2">{{ __('Wallet') }}</a>
                    </nav>
                    <x-notification-bell variant="gold" />
                    <form method="post" action="{{ route('logout') }}" class="hidden md:block">
                        @csrf
                        <button type="submit" class="rounded-lg px-3 py-2 text-sm text-slate-400 hover:bg-white/5">{{ __('Log out') }}</button>
                    </form>
                    <button type="button" class="rounded-lg p-2 text-slate-300 hover:bg-white/10 md:hidden" @click="mobileNav = !mobileNav" aria-label="{{ __('Menu') }}">
                        <svg class="size-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"/></svg>
                    </button>
                </div>
            </div>
            <div x-show="mobileNav" x-cloak class="border-t border-white/10 px-4 py-3 md:hidden">
                <div class="flex flex-col gap-1 text-sm">
                    <a href="{{ route('buyer.dashboard') }}" class="rounded-lg px-3 py-2 hover:bg-white/5">{{ __('Dashboard') }}</a>
                    <a href="{{ route('buyer.orders.index') }}" class="rounded-lg px-3 py-2 hover:bg-white/5">{{ __('Orders') }}</a>
                    <a href="{{ route('buyer.orders.create') }}" class="rounded-lg px-3 py-2 hover:bg-white/5">{{ __('New order') }}</a>
                    <a href="{{ route('wallet.index') }}" class="rounded-lg px-3 py-2 hover:bg-white/5">{{ __('Wallet') }}</a>
                    <form method="post" action="{{ route('logout') }}">
                        @csrf
                        <button type="submit" class="w-full rounded-lg px-3 py-2 text-left text-slate-400 hover:bg-white/5">{{ __('Log out') }}</button>
                    </form>
                </div>
            </div>
        </header>

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
    </div>
    <x-fcm-init />
</body>
</html>
