@extends('layouts.auth')

@section('title', __('Log in') . ' — ' . config('app.name'))

@section('content')
    <div class="w-full max-w-md rounded-2xl border border-white/10 bg-[#16213E]/80 p-8 shadow-xl backdrop-blur-sm">
        <h1 class="mb-6 text-center text-2xl font-bold tracking-tight text-white">{{ __('Welcome back') }}</h1>

        @if (session('status'))
            <div class="mb-4 rounded-lg border border-emerald-500/30 bg-emerald-500/10 px-4 py-2 text-center text-sm text-emerald-200">{{ session('status') }}</div>
        @endif

        @if (session('agent_reserved_code'))
            <div class="mb-4 rounded-lg border border-[#FFD700]/40 bg-[#FFD700]/10 px-4 py-3 text-sm text-slate-200">
                <p class="font-medium text-[#FFD700]">{{ __('Your shop code (save this)') }}</p>
                <p class="mt-2 text-center font-mono text-xl tracking-widest text-white">{{ session('agent_reserved_code') }}</p>
                <p class="mt-2 text-xs text-slate-400">{{ __('This code is assigned by the system and cannot be changed. You can log in after the supplier approves your agent account.') }}</p>
            </div>
        @endif

        <form method="post" action="{{ route('login') }}" class="space-y-5">
            @csrf

            <div>
                <label for="username" class="mb-1.5 block text-sm font-medium text-slate-300">{{ __('Username') }}</label>
                <input id="username" name="username" value="{{ old('username') }}" required autofocus autocomplete="username"
                    class="w-full rounded-lg border border-white/10 bg-[#1A1A2E] px-4 py-2.5 text-white placeholder:text-slate-500 focus:border-[#FFD700]/50 focus:outline-none focus:ring-2 focus:ring-[#FFD700]/30" />
                @error('username')
                    <p class="mt-1 text-sm text-red-400">{{ $message }}</p>
                @enderror
            </div>

            <div>
                <label for="password" class="mb-1.5 block text-sm font-medium text-slate-300">{{ __('Password') }}</label>
                <input id="password" name="password" type="password" required autocomplete="current-password"
                    class="w-full rounded-lg border border-white/10 bg-[#1A1A2E] px-4 py-2.5 text-white placeholder:text-slate-500 focus:border-[#FFD700]/50 focus:outline-none focus:ring-2 focus:ring-[#FFD700]/30" />
                @error('password')
                    <p class="mt-1 text-sm text-red-400">{{ $message }}</p>
                @enderror
            </div>

            <div class="flex flex-wrap items-center justify-between gap-2">
                <div class="flex items-center gap-2">
                    <input id="remember" name="remember" type="checkbox" value="1" {{ old('remember') ? 'checked' : '' }}
                        class="size-4 rounded border-white/20 bg-[#1A1A2E] text-[#FFD700] focus:ring-[#FFD700]/30" />
                    <label for="remember" class="text-sm text-slate-300">{{ __('Remember me') }}</label>
                </div>
                <a href="{{ route('password.request') }}" class="text-sm font-medium text-[#FFD700] hover:underline">{{ __('Forgot password?') }}</a>
            </div>

            <button type="submit"
                class="w-full rounded-lg bg-[#FFD700] px-4 py-3 text-center text-sm font-semibold text-[#1A1A2E] shadow-lg transition hover:brightness-95 focus:outline-none focus:ring-2 focus:ring-[#FFD700] focus:ring-offset-2 focus:ring-offset-[#1A1A2E]">
                {{ __('Log in') }}
            </button>
        </form>

        <p class="mt-6 text-center text-sm text-slate-400">
            {{ __('No account yet?') }}
            <a href="{{ route('register') }}" class="font-medium text-[#FFD700] hover:underline">{{ __('Register') }}</a>
        </p>
        @if (\Illuminate\Support\Facades\Route::has('register.agent-fee.confirm-form'))
            <p class="mt-3 text-center text-xs text-slate-500">
                {{ __('Paid agent registration fee but still waiting?') }}
                <a href="{{ route('register.agent-fee.confirm-form') }}" class="text-[#FFD700] hover:underline">{{ __('Confirm payment') }}</a>
            </p>
        @endif
    </div>
@endsection
