<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="dark">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>@yield('title', config('app.name', 'Laravel'))</title>

    @include('partials.favicon-links', ['href' => \App\Support\BrandingFavicon::supplierPrimaryUrl()])

    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=instrument-sans:400,500,600,700" rel="stylesheet" />

    @if (file_exists(public_path('build/manifest.json')) || file_exists(public_path('hot')))
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    @endif
</head>
<body class="min-h-screen bg-[#1A1A2E] font-sans text-slate-200 antialiased">
    <div class="flex min-h-screen flex-col px-4 py-12">
        <div class="flex flex-1 flex-col items-center justify-center">
            @if (session('status'))
                <div class="mb-4 w-full max-w-md rounded-lg border border-emerald-500/30 bg-emerald-500/10 px-4 py-2 text-center text-sm text-emerald-200">{{ session('status') }}</div>
            @endif
            @if (session('error'))
                <div class="mb-4 w-full max-w-md rounded-lg border border-red-500/30 bg-red-500/10 px-4 py-2 text-center text-sm text-red-200">{{ session('error') }}</div>
            @endif
            @yield('content')
        </div>
        <footer class="mt-8 shrink-0 border-t border-white/10 pt-6 text-center text-xs text-slate-500">
            @include('partials.developer-credit')
        </footer>
    </div>
</body>
</html>
