@extends('layouts.auth')

@section('title', __('Register') . ' — ' . config('app.name'))

@section('content')
    @php
        $agentLogoUrl = null;
        if (isset($agent) && $agent?->logo) {
            $agentLogoUrl = \Illuminate\Support\Str::startsWith($agent->logo, ['http://', 'https://'])
                ? $agent->logo
                : \Illuminate\Support\Facades\Storage::disk('public')->url($agent->logo);
        }
    @endphp

    <div class="w-full max-w-md rounded-2xl border border-white/10 bg-[#16213E]/80 p-8 shadow-xl backdrop-blur-sm">
        @if (! empty($agent))
            <div class="mb-8 flex flex-col items-center text-center">
                @if ($agentLogoUrl)
                    <img src="{{ $agentLogoUrl }}" alt="{{ $agent->shop_name ?? $agent->name }}" class="mb-4 h-20 w-20 rounded-xl border border-white/10 object-cover" />
                @endif
                <p class="text-sm text-slate-400">{{ __('Registering via') }}</p>
                <p class="text-lg font-semibold text-white">{{ $agent->shop_name ?? $agent->name }}</p>
            </div>
        @endif

        <h1 class="mb-6 text-center text-2xl font-bold tracking-tight text-white">{{ __('Create account') }}</h1>

        <form method="post" action="{{ route('register') }}" class="space-y-5">
            @csrf

            @if (! empty($agent))
                <input type="hidden" name="agent_slug" value="{{ $agent->shop_slug }}" />
            @else
                <div>
                    <label for="agent_slug" class="mb-1.5 block text-sm font-medium text-slate-300">{{ __('Agent shop code') }} <span class="text-slate-500">({{ __('optional') }})</span></label>
                    <input id="agent_slug" name="agent_slug" value="{{ old('agent_slug') }}" type="text" autocomplete="off"
                        class="w-full rounded-lg border border-white/10 bg-[#1A1A2E] px-4 py-2.5 text-white placeholder:text-slate-500 focus:border-[#FFD700]/50 focus:outline-none focus:ring-2 focus:ring-[#FFD700]/30" />
                    @error('agent_slug')
                        <p class="mt-1 text-sm text-red-400">{{ $message }}</p>
                    @enderror
                </div>
            @endif

            <div>
                <label for="username" class="mb-1.5 block text-sm font-medium text-slate-300">{{ __('Username') }}</label>
                <input id="username" name="username" value="{{ old('username') }}" required autocomplete="username"
                    class="w-full rounded-lg border border-white/10 bg-[#1A1A2E] px-4 py-2.5 text-white placeholder:text-slate-500 focus:border-[#FFD700]/50 focus:outline-none focus:ring-2 focus:ring-[#FFD700]/30" />
                @error('username')
                    <p class="mt-1 text-sm text-red-400">{{ $message }}</p>
                @enderror
            </div>

            <div>
                <label for="name" class="mb-1.5 block text-sm font-medium text-slate-300">{{ __('Full name') }}</label>
                <input id="name" name="name" value="{{ old('name') }}" required autocomplete="name"
                    class="w-full rounded-lg border border-white/10 bg-[#1A1A2E] px-4 py-2.5 text-white placeholder:text-slate-500 focus:border-[#FFD700]/50 focus:outline-none focus:ring-2 focus:ring-[#FFD700]/30" />
                @error('name')
                    <p class="mt-1 text-sm text-red-400">{{ $message }}</p>
                @enderror
            </div>

            <div>
                <label for="email" class="mb-1.5 block text-sm font-medium text-slate-300">{{ __('Email') }} <span class="text-slate-500">({{ __('optional') }})</span></label>
                <input id="email" name="email" value="{{ old('email') }}" type="email" autocomplete="email"
                    class="w-full rounded-lg border border-white/10 bg-[#1A1A2E] px-4 py-2.5 text-white placeholder:text-slate-500 focus:border-[#FFD700]/50 focus:outline-none focus:ring-2 focus:ring-[#FFD700]/30" />
                @error('email')
                    <p class="mt-1 text-sm text-red-400">{{ $message }}</p>
                @enderror
            </div>

            <div>
                <label for="phone" class="mb-1.5 block text-sm font-medium text-slate-300">{{ __('Phone') }}</label>
                <input id="phone" name="phone" value="{{ old('phone') }}" required inputmode="numeric" autocomplete="tel" placeholder="0XXXXXXXXX"
                    class="w-full rounded-lg border border-white/10 bg-[#1A1A2E] px-4 py-2.5 text-white placeholder:text-slate-500 focus:border-[#FFD700]/50 focus:outline-none focus:ring-2 focus:ring-[#FFD700]/30" />
                @error('phone')
                    <p class="mt-1 text-sm text-red-400">{{ $message }}</p>
                @enderror
            </div>

            <div>
                <label for="password" class="mb-1.5 block text-sm font-medium text-slate-300">{{ __('Password') }}</label>
                <input id="password" name="password" type="password" required autocomplete="new-password"
                    class="w-full rounded-lg border border-white/10 bg-[#1A1A2E] px-4 py-2.5 text-white placeholder:text-slate-500 focus:border-[#FFD700]/50 focus:outline-none focus:ring-2 focus:ring-[#FFD700]/30" />
                @error('password')
                    <p class="mt-1 text-sm text-red-400">{{ $message }}</p>
                @enderror
            </div>

            <div>
                <label for="password_confirmation" class="mb-1.5 block text-sm font-medium text-slate-300">{{ __('Confirm password') }}</label>
                <input id="password_confirmation" name="password_confirmation" type="password" required autocomplete="new-password"
                    class="w-full rounded-lg border border-white/10 bg-[#1A1A2E] px-4 py-2.5 text-white placeholder:text-slate-500 focus:border-[#FFD700]/50 focus:outline-none focus:ring-2 focus:ring-[#FFD700]/30" />
            </div>

            <button type="submit"
                class="w-full rounded-lg bg-[#FFD700] px-4 py-3 text-center text-sm font-semibold text-[#1A1A2E] shadow-lg transition hover:brightness-95 focus:outline-none focus:ring-2 focus:ring-[#FFD700] focus:ring-offset-2 focus:ring-offset-[#1A1A2E]">
                {{ __('Register') }}
            </button>
        </form>

        <p class="mt-6 text-center text-sm text-slate-400">
            {{ __('Already have an account?') }}
            <a href="{{ route('login') }}" class="font-medium text-[#FFD700] hover:underline">{{ __('Log in') }}</a>
        </p>
    </div>
@endsection
