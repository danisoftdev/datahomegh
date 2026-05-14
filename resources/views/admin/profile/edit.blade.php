@extends('layouts.admin')

@section('title', __('Account settings') . ' — ' . config('app.name'))
@section('heading', __('Account settings'))

@section('content')
    <p class="mb-6 text-sm text-slate-400">{{ __('Only one supplier account exists for this platform. Update your login and public contact details here.') }}</p>

    <form method="post" action="{{ route('admin.profile.update') }}" enctype="multipart/form-data" class="max-w-2xl space-y-8">
        @csrf
        @method('PUT')

        <div class="space-y-4 rounded-xl border border-white/10 bg-[#16213E]/80 p-6">
            <h2 class="text-lg font-semibold text-[#FFD700]">{{ __('Account & sign-in') }}</h2>
            <div>
                <label class="mb-1 block text-sm text-slate-400">{{ __('Username') }}</label>
                <input type="text" name="username" value="{{ old('username', $user->username) }}" required class="w-full rounded-lg border border-white/10 bg-[#1A1A2E] px-3 py-2 text-white" />
                @error('username')<p class="mt-1 text-sm text-red-400">{{ $message }}</p>@enderror
            </div>
            <div>
                <label class="mb-1 block text-sm text-slate-400">{{ __('Display name') }}</label>
                <input type="text" name="name" value="{{ old('name', $user->name) }}" required class="w-full rounded-lg border border-white/10 bg-[#1A1A2E] px-3 py-2 text-white" />
                @error('name')<p class="mt-1 text-sm text-red-400">{{ $message }}</p>@enderror
            </div>
            <div>
                <label class="mb-1 block text-sm text-slate-400">{{ __('Email') }}</label>
                <input type="email" name="email" value="{{ old('email', $user->email) }}" class="w-full rounded-lg border border-white/10 bg-[#1A1A2E] px-3 py-2 text-white" />
                @error('email')<p class="mt-1 text-sm text-red-400">{{ $message }}</p>@enderror
            </div>
            <div>
                <label class="mb-1 block text-sm text-slate-400">{{ __('Phone') }}</label>
                <input type="text" name="phone" value="{{ old('phone', $user->phone) }}" required class="w-full rounded-lg border border-white/10 bg-[#1A1A2E] px-3 py-2 text-white" placeholder="0XXXXXXXXX" />
                @error('phone')<p class="mt-1 text-sm text-red-400">{{ $message }}</p>@enderror
            </div>
            <div>
                <label class="mb-1 block text-sm text-slate-400">{{ __('New password') }}</label>
                <input type="password" name="password" autocomplete="new-password" class="w-full rounded-lg border border-white/10 bg-[#1A1A2E] px-3 py-2 text-white" />
                @error('password')<p class="mt-1 text-sm text-red-400">{{ $message }}</p>@enderror
            </div>
            <div>
                <label class="mb-1 block text-sm text-slate-400">{{ __('Confirm new password') }}</label>
                <input type="password" name="password_confirmation" autocomplete="new-password" class="w-full rounded-lg border border-white/10 bg-[#1A1A2E] px-3 py-2 text-white" />
            </div>
            <div>
                <label class="mb-1 block text-sm text-slate-400">{{ __('Current password') }} <span class="text-slate-500">({{ __('required if changing password') }})</span></label>
                <input type="password" name="current_password" autocomplete="current-password" class="w-full rounded-lg border border-white/10 bg-[#1A1A2E] px-3 py-2 text-white" />
                @error('current_password')<p class="mt-1 text-sm text-red-400">{{ $message }}</p>@enderror
            </div>
        </div>

        <div class="space-y-4 rounded-xl border border-white/10 bg-[#16213E]/80 p-6">
            <h2 class="text-lg font-semibold text-[#FFD700]">{{ __('Company & support') }}</h2>
            <p class="text-xs text-slate-500">{{ __('These details appear on the dashboards of buyers who are not linked to an agent, and on every agent dashboard (WhatsApp chat, channel, and call links).') }}</p>
            <div>
                <label class="mb-1 block text-sm text-slate-400">{{ __('Company / brand name') }}</label>
                <input type="text" name="shop_name" value="{{ old('shop_name', $user->shop_name) }}" class="w-full rounded-lg border border-white/10 bg-[#1A1A2E] px-3 py-2 text-white" />
            </div>
            <div>
                <label class="mb-1 block text-sm text-slate-400">{{ __('Business description') }}</label>
                <textarea name="business_description" rows="3" class="w-full rounded-lg border border-white/10 bg-[#1A1A2E] px-3 py-2 text-white">{{ old('business_description', $user->business_description) }}</textarea>
            </div>
            <div>
                <label class="mb-1 block text-sm text-slate-400">{{ __('WhatsApp number') }}</label>
                <input type="text" name="whatsapp_number" value="{{ old('whatsapp_number', $user->whatsapp_number) }}" class="w-full rounded-lg border border-white/10 bg-[#1A1A2E] px-3 py-2 text-white" />
            </div>
            <div>
                <label class="mb-1 block text-sm text-slate-400">{{ __('WhatsApp channel link') }}</label>
                <input type="text" name="whatsapp_channel" value="{{ old('whatsapp_channel', $user->whatsapp_channel) }}" class="w-full rounded-lg border border-white/10 bg-[#1A1A2E] px-3 py-2 text-white" />
            </div>
            <div>
                <label class="mb-1 block text-sm text-slate-400">{{ __('Profile picture') }}</label>
                @if ($user->profile_picture)
                    <p class="mb-2 text-xs text-slate-500">{{ __('Current') }}: <img src="{{ asset('storage/'.$user->profile_picture) }}" alt="" class="mt-1 size-20 rounded-lg object-cover" /></p>
                @endif
                <input type="file" name="profile_picture" accept="image/*" class="text-sm text-slate-300" />
                @error('profile_picture')<p class="mt-1 text-sm text-red-400">{{ $message }}</p>@enderror
            </div>
            <div>
                <label class="mb-1 block text-sm text-slate-400">{{ __('Logo') }}</label>
                @if ($user->logo)
                    <p class="mb-2 text-xs text-slate-500">{{ __('Current') }}: <img src="{{ asset('storage/'.$user->logo) }}" alt="" class="mt-1 max-h-16 rounded object-contain" /></p>
                @endif
                <input type="file" name="logo" accept="image/*" class="text-sm text-slate-300" />
                @error('logo')<p class="mt-1 text-sm text-red-400">{{ $message }}</p>@enderror
            </div>
        </div>

        <div class="space-y-4 rounded-xl border border-white/10 bg-[#16213E]/80 p-6">
            <h2 class="text-lg font-semibold text-[#FFD700]">{{ __('Agent self-registration') }}</h2>
            <p class="text-xs text-slate-500">{{ __('Buyers who choose “Agent” on the public registration form must pay this amount through Paystack before their application is sent to you for approval. Leave blank to disable new agent sign-ups until you set a fee.') }}</p>
            <div>
                <label class="mb-1 block text-sm text-slate-400">{{ __('Shop link registration fee (GHS)') }}</label>
                <input type="number" name="agent_shop_registration_fee_ghs" step="0.01" min="0.01" max="999999" value="{{ $agentShopRegistrationFeeGhs !== '' ? $agentShopRegistrationFeeGhs : '' }}" placeholder="{{ __('e.g. 50') }}" class="w-full max-w-xs rounded-lg border border-white/10 bg-[#1A1A2E] px-3 py-2 text-white" />
                @error('agent_shop_registration_fee_ghs')<p class="mt-1 text-sm text-red-400">{{ $message }}</p>@enderror
            </div>
        </div>

        <button type="submit" class="rounded-lg bg-[#FFD700] px-6 py-2 text-sm font-semibold text-[#1A1A2E]">{{ __('Save changes') }}</button>
    </form>
@endsection
