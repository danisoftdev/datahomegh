@extends('layouts.auth')

@section('title', __('Log in') . ' — ' . config('app.name'))

@section('content')
    <div class="w-full max-w-md rounded-2xl border border-white/10 bg-[#16213E]/80 p-8 shadow-xl backdrop-blur-sm">
        <h1 class="mb-6 text-center text-2xl font-bold tracking-tight text-white">{{ __('Welcome back') }}</h1>

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

            <div class="flex items-center gap-2">
                <input id="remember" name="remember" type="checkbox" value="1" {{ old('remember') ? 'checked' : '' }}
                    class="size-4 rounded border-white/20 bg-[#1A1A2E] text-[#FFD700] focus:ring-[#FFD700]/30" />
                <label for="remember" class="text-sm text-slate-300">{{ __('Remember me') }}</label>
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
    </div>
@endsection
