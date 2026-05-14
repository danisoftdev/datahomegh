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
        $viaAgent = ! empty($agent);
        $oldType = old('account_type', 'buyer');
    @endphp

    <div class="w-full max-w-md rounded-2xl border border-white/10 bg-[#16213E]/80 p-8 shadow-xl backdrop-blur-sm">
        @if ($viaAgent)
            <div class="mb-8 flex flex-col items-center text-center">
                @if ($agentLogoUrl)
                    <img src="{{ $agentLogoUrl }}" alt="{{ $agent->shop_name ?? $agent->name }}" class="mb-4 h-20 w-20 rounded-xl border border-white/10 object-cover" />
                @endif
                <p class="text-sm text-slate-400">{{ __('Registering via') }}</p>
                <p class="text-lg font-semibold text-white">{{ $agent->shop_name ?? $agent->name }}</p>
            </div>
        @endif

        <h1 class="mb-6 text-center text-2xl font-bold tracking-tight text-white">{{ __('Create account') }}</h1>

        <form method="post" action="{{ route('register') }}" class="space-y-5" id="register-form">
            @csrf

            @if ($viaAgent)
                <input type="hidden" name="via_agent_shop" value="1" />
                <input type="hidden" name="account_type" value="buyer" />
            @else
                <fieldset class="space-y-2">
                    <legend class="mb-1.5 block text-sm font-medium text-slate-300">{{ __('Account type') }}</legend>
                    <div class="flex gap-4">
                        <label class="flex cursor-pointer items-center gap-2 text-sm text-slate-200">
                            <input type="radio" name="account_type" value="buyer" class="text-[#FFD700]" @checked($oldType === 'buyer') />
                            {{ __('Buyer') }}
                        </label>
                        <label class="flex cursor-pointer items-center gap-2 text-sm text-slate-200">
                            <input type="radio" name="account_type" value="agent" class="text-[#FFD700]" @checked($oldType === 'agent') />
                            {{ __('Agent') }}
                        </label>
                    </div>
                    @error('account_type')
                        <p class="text-sm text-red-400">{{ $message }}</p>
                    @enderror
                </fieldset>
            @endif

            @if (! $viaAgent)
                <div id="buyer-agent-link-block">
                    <label for="agent_slug" class="mb-1.5 block text-sm font-medium text-slate-300">{{ __('Agent code') }} <span class="text-slate-500">({{ __('optional') }})</span></label>
                    <input id="agent_slug" name="agent_slug" value="{{ old('agent_slug') }}" type="text" autocomplete="off"
                        class="w-full rounded-lg border border-white/10 bg-[#1A1A2E] px-4 py-2.5 text-white placeholder:text-slate-500 focus:border-[#FFD700]/50 focus:outline-none focus:ring-2 focus:ring-[#FFD700]/30" />
                    @error('agent_slug')
                        <p class="mt-1 text-sm text-red-400">{{ $message }}</p>
                    @enderror
                </div>
            @else
                <input type="hidden" name="agent_slug" value="{{ $agent->shop_slug }}" />
            @endif

            <div id="agent-shop-block" class="{{ $oldType === 'agent' && ! $viaAgent ? '' : 'hidden' }} space-y-3">
                <div>
                    <label for="shop_name" class="mb-1.5 block text-sm font-medium text-slate-300">{{ __('Shop / business name') }} <span class="text-amber-400">*</span></label>
                    <input id="shop_name" name="shop_name" value="{{ old('shop_name') }}" type="text" autocomplete="organization"
                        class="w-full rounded-lg border border-white/10 bg-[#1A1A2E] px-4 py-2.5 text-white placeholder:text-slate-500 focus:border-[#FFD700]/50 focus:outline-none focus:ring-2 focus:ring-[#FFD700]/30" />
                    @error('shop_name')
                        <p class="mt-1 text-sm text-red-400">{{ $message }}</p>
                    @enderror
                </div>
                <p class="rounded-lg border border-white/10 bg-[#1A1A2E]/80 px-3 py-2 text-xs leading-relaxed text-slate-400">
                    {{ __('Your shop code is 5 letters and numbers, generated automatically when you register. You cannot choose or change it. It will appear on the next screen after you submit.') }}
                </p>
            </div>

            <div>
                <label for="username" class="mb-1.5 block text-sm font-medium text-slate-300">{{ __('Username') }}</label>
                <input id="username" name="username" value="{{ old('username') }}" required autocomplete="username"
                    class="w-full rounded-lg border border-white/10 bg-[#1A1A2E] px-4 py-2.5 text-white placeholder:text-slate-500 focus:border-[#FFD700]/50 focus:outline-none focus:ring-2 focus:ring-[#FFD700]/30" />
                @error('username')
                    <p class="mt-1 text-sm text-red-400">{{ $message }}</p>
                @enderror
            </div>

            <div>
                <label for="name" class="mb-1.5 block text-sm font-medium text-slate-300">
                    {{ __('Full name') }}
                    <span id="name-required-badge" class="{{ $oldType === 'agent' && ! $viaAgent ? '' : 'hidden' }} text-amber-400">*</span>
                    <span id="name-optional-hint" class="{{ $oldType === 'agent' && ! $viaAgent ? 'hidden' : '' }} text-slate-500">({{ __('optional — defaults to username') }})</span>
                </label>
                <input id="name" name="name" value="{{ old('name') }}" type="text" autocomplete="name"
                    class="w-full rounded-lg border border-white/10 bg-[#1A1A2E] px-4 py-2.5 text-white placeholder:text-slate-500 focus:border-[#FFD700]/50 focus:outline-none focus:ring-2 focus:ring-[#FFD700]/30" />
                @error('name')
                    <p class="mt-1 text-sm text-red-400">{{ $message }}</p>
                @enderror
            </div>

            <div>
                <label for="email" class="mb-1.5 block text-sm font-medium text-slate-300">
                    {{ __('Email') }}
                    <span id="email-required-badge" class="{{ $oldType === 'agent' && ! $viaAgent ? '' : 'hidden' }} text-amber-400">*</span>
                    <span id="email-optional-hint" class="{{ $oldType === 'agent' && ! $viaAgent ? 'hidden' : '' }} text-slate-500">({{ __('optional') }})</span>
                </label>
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

    @if (! $viaAgent)
        <script>
            (function () {
                var form = document.getElementById('register-form');
                if (!form) return;
                function isAgent() {
                    var r = form.querySelector('input[name="account_type"]:checked');
                    return r && r.value === 'agent';
                }
                function sync() {
                    var agent = isAgent();
                    var shopBlock = document.getElementById('agent-shop-block');
                    var buyerLink = document.getElementById('buyer-agent-link-block');
                    var badge = document.getElementById('email-required-badge');
                    var hint = document.getElementById('email-optional-hint');
                    var nameBadge = document.getElementById('name-required-badge');
                    var nameHint = document.getElementById('name-optional-hint');
                    var nameInput = document.getElementById('name');
                    if (shopBlock) shopBlock.classList.toggle('hidden', !agent);
                    if (buyerLink) buyerLink.classList.toggle('hidden', agent);
                    if (badge) badge.classList.toggle('hidden', !agent);
                    if (hint) hint.classList.toggle('hidden', agent);
                    if (nameBadge) nameBadge.classList.toggle('hidden', !agent);
                    if (nameHint) nameHint.classList.toggle('hidden', agent);
                    if (nameInput) {
                        if (agent) {
                            nameInput.setAttribute('required', 'required');
                        } else {
                            nameInput.removeAttribute('required');
                        }
                    }
                }
                form.querySelectorAll('input[name="account_type"]').forEach(function (el) {
                    el.addEventListener('change', sync);
                });
                sync();
            })();
        </script>
    @endif
@endsection
