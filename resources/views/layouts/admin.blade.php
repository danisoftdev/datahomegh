<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="dark">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>@yield('title', __('Admin') . ' — ' . config('app.name'))</title>

    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=instrument-sans:400,500,600,700" rel="stylesheet" />

    @if (file_exists(public_path('build/manifest.json')) || file_exists(public_path('hot')))
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    @endif
    <style>[x-cloak]{display:none !important}</style>
</head>
<body class="min-h-screen bg-[#0f0f1a] font-sans text-slate-200 antialiased">
    <div id="admin-app" class="flex min-h-screen" x-data="{ sidebarOpen: false }">
        <div x-show="sidebarOpen" x-transition.opacity class="fixed inset-0 z-40 bg-black/60 lg:hidden" @click="sidebarOpen = false" style="display: none;"></div>

        <aside
            class="fixed inset-y-0 left-0 z-50 flex w-64 flex-col border-r border-white/10 bg-[#1A1A2E] transition-transform lg:static lg:translate-x-0"
            :class="sidebarOpen ? 'translate-x-0' : '-translate-x-full lg:translate-x-0'"
        >
            <div class="flex h-14 shrink-0 items-center justify-between gap-2 border-b border-white/10 px-4">
                <div class="min-w-0 truncate">
                    <span class="font-semibold text-[#FFD700]">{{ config('app.name') }}</span>
                    <span class="ml-2 text-xs text-slate-500">{{ __('Admin') }}</span>
                </div>
                <x-notification-bell variant="gold" />
            </div>
            <nav class="min-h-0 flex-1 space-y-1 overflow-y-auto p-3 text-sm">
                @php
                    $r = request();
                    $shownUser = $r->route('user');
                    $agentsNav = ($r->routeIs('admin.users.index') && $r->get('role') === \App\Models\Role::SLUG_AGENT)
                        || ($r->routeIs('admin.users.show') && $shownUser && $shownUser->role?->slug === \App\Models\Role::SLUG_AGENT);
                    $buyersNav = ($r->routeIs('admin.users.index') && $r->get('role') === \App\Models\Role::SLUG_BUYER)
                        || ($r->routeIs('admin.users.show') && $shownUser && $shownUser->role?->slug === \App\Models\Role::SLUG_BUYER);
                @endphp
                <a href="{{ route('admin.dashboard') }}" class="{{ $r->routeIs('admin.dashboard') ? 'bg-white/10 text-[#FFD700]' : 'text-slate-300 hover:bg-white/5' }} block rounded-lg px-3 py-2">{{ __('Dashboard') }}</a>
                <a href="{{ route('admin.orders.index') }}" class="{{ $r->routeIs('admin.orders.*') ? 'bg-white/10 text-[#FFD700]' : 'text-slate-300 hover:bg-white/5' }} block rounded-lg px-3 py-2">{{ __('Orders') }}</a>
                <a href="{{ route('admin.users.index', ['role' => \App\Models\Role::SLUG_AGENT]) }}" class="{{ $agentsNav ? 'bg-white/10 text-[#FFD700]' : 'text-slate-300 hover:bg-white/5' }} block rounded-lg px-3 py-2">{{ __('Agents') }}</a>
                <a href="{{ route('admin.users.index', ['role' => \App\Models\Role::SLUG_BUYER]) }}" class="{{ $buyersNav ? 'bg-white/10 text-[#FFD700]' : 'text-slate-300 hover:bg-white/5' }} block rounded-lg px-3 py-2">{{ __('Buyers') }}</a>
                <a href="{{ route('admin.bundles.index') }}" class="{{ $r->routeIs('admin.bundles.*') ? 'bg-white/10 text-[#FFD700]' : 'text-slate-300 hover:bg-white/5' }} block rounded-lg px-3 py-2">{{ __('Bundles') }}</a>
                <a href="{{ route('admin.wallet.index') }}" class="{{ $r->routeIs('admin.wallet.*') ? 'bg-white/10 text-[#FFD700]' : 'text-slate-300 hover:bg-white/5' }} block rounded-lg px-3 py-2">{{ __('Wallet controls') }}</a>
                <a href="{{ route('admin.roles.index') }}" class="{{ $r->routeIs('admin.roles.*') ? 'bg-white/10 text-[#FFD700]' : 'text-slate-300 hover:bg-white/5' }} block rounded-lg px-3 py-2">{{ __('Roles') }}</a>
                <a href="{{ route('admin.notifications.index') }}" class="{{ $r->routeIs('admin.notifications.*') ? 'bg-white/10 text-[#FFD700]' : 'text-slate-300 hover:bg-white/5' }} block rounded-lg px-3 py-2">{{ __('Notifications') }}</a>
                <a href="{{ route('admin.profile.edit') }}" class="{{ $r->routeIs('admin.profile.*') ? 'bg-white/10 text-[#FFD700]' : 'text-slate-300 hover:bg-white/5' }} block rounded-lg px-3 py-2">{{ __('Account settings') }}</a>
            </nav>
            <div class="shrink-0 border-t border-white/10 p-3">
                <form method="post" action="{{ route('logout') }}">
                    @csrf
                    <button type="submit" class="w-full rounded-lg px-3 py-2 text-left text-sm text-slate-400 hover:bg-white/5">{{ __('Log out') }}</button>
                </form>
                <a href="{{ route('dashboard') }}" class="mt-1 block rounded-lg px-3 py-2 text-sm text-slate-500 hover:text-slate-300">{{ __('Exit admin') }}</a>
            </div>
        </aside>

        <div class="flex min-h-0 flex-1 flex-col">
            <header class="sticky top-0 z-30 flex h-14 shrink-0 items-center justify-between gap-3 border-b border-white/10 bg-[#1A1A2E]/95 px-4 backdrop-blur lg:hidden">
                <div class="flex min-w-0 flex-1 items-center gap-3">
                    <button type="button" class="rounded-lg p-2 text-slate-300 hover:bg-white/10" @click="sidebarOpen = true" aria-label="{{ __('Menu') }}">
                        <svg class="size-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"/></svg>
                    </button>
                    <span class="truncate font-medium text-white">@yield('heading', __('Admin'))</span>
                </div>
                <x-notification-bell variant="gold" />
            </header>

            <main class="min-h-0 flex-1 overflow-y-auto p-4 lg:p-8">
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
    </div>
    <x-fcm-init />
</body>
</html>
