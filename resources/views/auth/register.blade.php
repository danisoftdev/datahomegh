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

        @error('registration')
            <div class="mb-4 rounded-lg border border-red-500/40 bg-red-500/10 px-3 py-2 text-sm text-red-200">{{ $message }}</div>
        @enderror

        @if (! $viaAgent)
            {{-- :has() toggles panels even if JS fails; radios use id+for + size so they stay clickable with Tailwind preflight --}}
            <style>
                #register-form #buyer-agent-link-block,
                #register-form #agent-shop-block { display: none; }
                #register-form:has(#register_account_buyer:checked) #buyer-agent-link-block { display: block; }
                #register-form:has(#register_account_agent:checked) #agent-shop-block { display: block; }
            </style>
        @endif

        <form method="post" action="{{ route('register') }}" class="space-y-5" id="register-form" autocomplete="off">
            @csrf

            @if ($viaAgent)
                <input type="hidden" name="via_agent_shop" value="1" />
                <input type="hidden" name="account_type" value="buyer" />
            @else
                <div class="relative z-10 space-y-2" role="radiogroup" aria-labelledby="register-acct-type-label">
                    <p id="register-acct-type-label" class="mb-1.5 block text-sm font-medium text-slate-300">{{ __('Account type') }}</p>
                    <div class="flex flex-wrap gap-6">
                        <div class="flex items-center gap-2.5">
                            <input
                                id="register_account_buyer"
                                type="radio"
                                name="account_type"
                                value="buyer"
                                class="register-acct-radio mt-0.5 h-4 w-4 shrink-0 cursor-pointer appearance-auto border-slate-500 accent-[#FFD700] focus:outline-none focus:ring-2 focus:ring-[#FFD700]/50"
                                @checked($oldType === 'buyer')
                            />
                            <label for="register_account_buyer" class="cursor-pointer select-none text-sm text-slate-200">{{ __('Buyer') }}</label>
                        </div>
                        <div class="flex items-center gap-2.5">
                            <input
                                id="register_account_agent"
                                type="radio"
                                name="account_type"
                                value="agent"
                                class="register-acct-radio mt-0.5 h-4 w-4 shrink-0 cursor-pointer appearance-auto border-slate-500 accent-[#FFD700] focus:outline-none focus:ring-2 focus:ring-[#FFD700]/50"
                                @checked($oldType === 'agent')
                            />
                            <label for="register_account_agent" class="cursor-pointer select-none text-sm text-slate-200">{{ __('Agent') }}</label>
                        </div>
                    </div>
                    @error('account_type')
                        <p class="text-sm text-red-400">{{ $message }}</p>
                    @enderror
                </div>
            @endif

            @if (! $viaAgent)
                <div id="buyer-agent-link-block" class="space-y-2">
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

            <div id="agent-shop-block" class="space-y-3">
                @if (! empty($agentShopRegistrationFeeGhs))
                    <div class="rounded-lg border border-amber-500/40 bg-amber-500/10 px-3 py-2 text-xs leading-relaxed text-amber-100">
                        {{ __('Caution: when you submit this form you will be sent to Paystack to pay :amount GHS. Your agent shop application is only sent to the administrator for approval after that payment succeeds. If you do not pay, your account will not be submitted.', ['amount' => $agentShopRegistrationFeeGhs]) }}
                    </div>
                @else
                    <div class="rounded-lg border border-red-500/40 bg-red-500/10 px-3 py-2 text-xs leading-relaxed text-red-200">
                        {{ __('Agent registration is disabled until the platform administrator sets the shop link fee (greater than zero) in account settings.') }}
                    </div>
                @endif
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
                    <span id="name-required-badge" class="text-amber-400" @if (! ($oldType === 'agent' && ! $viaAgent)) hidden @endif>*</span>
                    <span id="name-optional-hint" class="text-slate-500" @if ($oldType === 'agent' && ! $viaAgent) hidden @endif>({{ __('optional — defaults to username') }})</span>
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
                    <span id="email-required-badge" class="text-amber-400" @if (! ($oldType === 'agent' && ! $viaAgent)) hidden @endif>*</span>
                    <span id="email-optional-hint" class="text-slate-500" @if ($oldType === 'agent' && ! $viaAgent) hidden @endif>({{ __('optional') }})</span>
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

            <button type="submit" id="register-submit"
                class="w-full rounded-lg bg-[#FFD700] px-4 py-3 text-center text-sm font-semibold text-[#1A1A2E] shadow-lg transition hover:brightness-95 focus:outline-none focus:ring-2 focus:ring-[#FFD700] focus:ring-offset-2 focus:ring-offset-[#1A1A2E]">
                <span id="register-submit-label">{{ __('Register') }}</span>
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
                function syncRegisterAccountType() {
                    var form = document.getElementById('register-form');
                    if (!form) return;
                    var checked = form.querySelector('input[name="account_type"]:checked');
                    var agent = !!(checked && checked.value === 'agent');
                    var badge = document.getElementById('email-required-badge');
                    var hint = document.getElementById('email-optional-hint');
                    var nameBadge = document.getElementById('name-required-badge');
                    var nameHint = document.getElementById('name-optional-hint');
                    var nameInput = document.getElementById('name');
                    var submitLabel = document.getElementById('register-submit-label');
                    if (submitLabel) {
                        submitLabel.textContent = agent ? {{ json_encode(__('Continue to Paystack')) }} : {{ json_encode(__('Register')) }};
                    }
                    if (badge) badge.hidden = !agent;
                    if (hint) hint.hidden = agent;
                    if (nameBadge) nameBadge.hidden = !agent;
                    if (nameHint) nameHint.hidden = agent;
                    if (nameInput) {
                        if (agent) {
                            nameInput.setAttribute('required', 'required');
                        } else {
                            nameInput.removeAttribute('required');
                        }
                    }
                }
                function bind() {
                    var form = document.getElementById('register-form');
                    if (!form) return;
                    form.addEventListener('change', function (e) {
                        if (e.target && e.target.name === 'account_type') {
                            syncRegisterAccountType();
                        }
                    }, true);
                    form.querySelectorAll('input[name="account_type"]').forEach(function (el) {
                        el.addEventListener('input', syncRegisterAccountType);
                        el.addEventListener('click', function () {
                            window.requestAnimationFrame(syncRegisterAccountType);
                        });
                    });
                    syncRegisterAccountType();
                }
                if (document.readyState === 'loading') {
                    document.addEventListener('DOMContentLoaded', bind);
                } else {
                    bind();
                }
            })();
        </script>
    @endif
@endsection
