@extends('layouts.auth')

@section('title', __('Forgot password') . ' — ' . config('app.name'))

@section('content')
    <div class="w-full max-w-md rounded-2xl border border-white/10 bg-[#16213E]/80 p-8 shadow-xl backdrop-blur-sm">
        <h1 class="mb-2 text-center text-2xl font-bold tracking-tight text-white">{{ __('Forgot password') }}</h1>
        <p class="mb-6 text-center text-sm text-slate-400">
            {{ __('Contact the admin on WhatsApp or by phone with your username to receive your reset code. Code expires in 20 minutes.') }}
        </p>

        @if ($supplier)
            <div class="mb-6 rounded-lg border border-white/10 bg-[#1A1A2E] px-4 py-3 text-sm text-slate-300">
                <p class="font-medium text-[#FFD700]">{{ __('Admin contact') }}</p>
                @if ($supplier->whatsapp_number)
                    <p class="mt-2">
                        <span class="text-slate-500">{{ __('WhatsApp') }}:</span>
                        <a href="https://wa.me/{{ preg_replace('/\D/', '', $supplier->whatsapp_number) }}" target="_blank" rel="noopener noreferrer" class="ml-1 font-medium text-[#FFD700] hover:underline">{{ $supplier->whatsapp_number }}</a>
                    </p>
                @endif
                @if ($supplier->phone)
                    <p class="mt-1">
                        <span class="text-slate-500">{{ __('Phone') }}:</span>
                        <a href="tel:{{ preg_replace('/\s+/', '', $supplier->phone) }}" class="ml-1 font-medium text-[#FFD700] hover:underline">{{ $supplier->phone }}</a>
                    </p>
                @endif
                @if (! $supplier->whatsapp_number && ! $supplier->phone)
                    <p class="mt-2 text-slate-500">{{ __('Contact details are not on file. Please use your usual support channel.') }}</p>
                @endif
            </div>
        @else
            <div class="mb-6 rounded-lg border border-amber-500/30 bg-amber-500/10 px-4 py-3 text-sm text-amber-200">
                {{ __('No supplier contact is available. Please reach out through your usual support channel.') }}
            </div>
        @endif

        <form method="post" action="{{ route('password.request.submit') }}" class="space-y-5">
            @csrf

            <div>
                <label for="username" class="mb-1.5 block text-sm font-medium text-slate-300">{{ __('Username') }}</label>
                <input id="username" name="username" value="{{ old('username') }}" required autofocus autocomplete="username"
                    class="w-full rounded-lg border border-white/10 bg-[#1A1A2E] px-4 py-2.5 text-white placeholder:text-slate-500 focus:border-[#FFD700]/50 focus:outline-none focus:ring-2 focus:ring-[#FFD700]/30" />
                @error('username')
                    <p class="mt-1 text-sm text-red-400">{{ $message }}</p>
                @enderror
            </div>

            <button type="submit"
                class="w-full rounded-lg bg-[#FFD700] px-4 py-3 text-center text-sm font-semibold text-[#1A1A2E] shadow-lg transition hover:brightness-95 focus:outline-none focus:ring-2 focus:ring-[#FFD700] focus:ring-offset-2 focus:ring-offset-[#1A1A2E]">
                {{ __('Submit request') }}
            </button>
        </form>

        <p class="mt-6 text-center text-sm text-slate-400">
            <a href="{{ route('password.reset.form') }}" class="font-medium text-[#FFD700] hover:underline">{{ __('I have a reset code') }}</a>
            <span class="mx-2 text-slate-600">·</span>
            <a href="{{ route('login') }}" class="font-medium text-[#FFD700] hover:underline">{{ __('Back to log in') }}</a>
        </p>
    </div>
@endsection
