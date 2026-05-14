@extends('layouts.auth')

@section('title', config('app.name'))

@section('content')
    <div class="w-full max-w-lg text-center">
        <p class="text-sm font-medium uppercase tracking-widest text-[#FFD700]/90">{{ __('Data bundles') }}</p>
        <h1 class="mt-3 text-3xl font-bold tracking-tight text-white sm:text-4xl">{{ config('app.name') }}</h1>
        <p class="mx-auto mt-4 max-w-md text-base leading-relaxed text-slate-400">
            {{ __('Buy MTN, Telecel, and AirtelTigo data through verified agent shops, or sign in to manage your wallet and orders.') }}
        </p>

        <div class="mt-10 flex flex-col items-stretch gap-3 sm:flex-row sm:justify-center">
            @guest
                <a
                    href="{{ route('register') }}"
                    class="inline-flex items-center justify-center rounded-lg bg-[#FFD700] px-8 py-3 text-sm font-semibold text-[#1A1A2E] shadow-lg transition hover:brightness-95"
                >
                    {{ __('Create account') }}
                </a>
                <a
                    href="{{ route('login') }}"
                    class="inline-flex items-center justify-center rounded-lg border border-white/20 bg-white/5 px-8 py-3 text-sm font-semibold text-white transition hover:bg-white/10"
                >
                    {{ __('Log in') }}
                </a>
            @else
                <a
                    href="{{ route('dashboard') }}"
                    class="inline-flex items-center justify-center rounded-lg bg-[#FFD700] px-8 py-3 text-sm font-semibold text-[#1A1A2E] shadow-lg transition hover:brightness-95"
                >
                    {{ __('Go to dashboard') }}
                </a>
            @endguest
        </div>

        <p class="mt-10 text-xs text-slate-500">
            {{ __('Have an agent shop link? Open it directly — you will register as a buyer linked to that shop.') }}
        </p>
    </div>
@endsection
