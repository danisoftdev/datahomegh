@extends('layouts.auth')

@section('title', __('Reset password') . ' — ' . config('app.name'))

@section('content')
    <div class="w-full max-w-md rounded-2xl border border-white/10 bg-[#16213E]/80 p-8 shadow-xl backdrop-blur-sm">
        <h1 class="mb-6 text-center text-2xl font-bold tracking-tight text-white">{{ __('Reset password') }}</h1>

        <form method="post" action="{{ route('password.update') }}" class="space-y-5">
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
                <label for="reset_code" class="mb-1.5 block text-sm font-medium text-slate-300">{{ __('Reset code') }}</label>
                <input id="reset_code" name="reset_code" value="{{ old('reset_code') }}" required autocomplete="one-time-code"
                    class="w-full rounded-lg border border-white/10 bg-[#1A1A2E] px-4 py-2.5 font-mono tracking-widest text-white placeholder:text-slate-500 focus:border-[#FFD700]/50 focus:outline-none focus:ring-2 focus:ring-[#FFD700]/30" />
                @error('reset_code')
                    <p class="mt-1 text-sm text-red-400">{{ $message }}</p>
                @enderror
            </div>

            <div>
                <label for="new_password" class="mb-1.5 block text-sm font-medium text-slate-300">{{ __('New password') }}</label>
                <input id="new_password" name="new_password" type="password" required autocomplete="new-password"
                    class="w-full rounded-lg border border-white/10 bg-[#1A1A2E] px-4 py-2.5 text-white placeholder:text-slate-500 focus:border-[#FFD700]/50 focus:outline-none focus:ring-2 focus:ring-[#FFD700]/30" />
                @error('new_password')
                    <p class="mt-1 text-sm text-red-400">{{ $message }}</p>
                @enderror
            </div>

            <div>
                <label for="new_password_confirmation" class="mb-1.5 block text-sm font-medium text-slate-300">{{ __('Confirm password') }}</label>
                <input id="new_password_confirmation" name="new_password_confirmation" type="password" required autocomplete="new-password"
                    class="w-full rounded-lg border border-white/10 bg-[#1A1A2E] px-4 py-2.5 text-white placeholder:text-slate-500 focus:border-[#FFD700]/50 focus:outline-none focus:ring-2 focus:ring-[#FFD700]/30" />
            </div>

            <button type="submit"
                class="w-full rounded-lg bg-[#FFD700] px-4 py-3 text-center text-sm font-semibold text-[#1A1A2E] shadow-lg transition hover:brightness-95 focus:outline-none focus:ring-2 focus:ring-[#FFD700] focus:ring-offset-2 focus:ring-offset-[#1A1A2E]">
                {{ __('Reset password') }}
            </button>
        </form>

        <p class="mt-6 text-center text-sm text-slate-400">
            <a href="{{ route('password.request') }}" class="font-medium text-[#FFD700] hover:underline">{{ __('Need a code?') }}</a>
            <span class="mx-2 text-slate-600">·</span>
            <a href="{{ route('login') }}" class="font-medium text-[#FFD700] hover:underline">{{ __('Back to log in') }}</a>
        </p>
    </div>
@endsection
