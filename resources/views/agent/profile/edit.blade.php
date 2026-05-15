@extends('layouts.agent')

@section('title', __('Profile') . ' — ' . config('app.name'))
@section('heading', __('Profile'))

@section('content')
    <h1 class="mb-6 text-2xl font-bold text-white">{{ __('Your profile') }}</h1>

    @if (session('status'))
        <div class="mb-4 max-w-2xl rounded-lg border border-emerald-500/30 bg-emerald-500/10 px-4 py-2 text-sm text-emerald-200">{{ session('status') }}</div>
    @endif

    <form method="post" action="{{ route('agent.profile.update') }}" enctype="multipart/form-data" class="max-w-2xl space-y-8">
        @csrf
        @method('PUT')

        <div class="space-y-4 rounded-xl border border-white/10 bg-[#16213E]/80 p-6">
            <h2 class="text-lg font-semibold text-emerald-400">{{ __('Account') }}</h2>
            <div>
                <label class="mb-1 block text-sm text-slate-400">{{ __('Username') }}</label>
                <input type="text" name="username" value="{{ old('username', $user->username) }}" required class="w-full rounded-lg border border-white/10 bg-[#1A1A2E] px-3 py-2 text-white" />
                @error('username')<p class="mt-1 text-sm text-red-400">{{ $message }}</p>@enderror
            </div>
            <div>
                <label class="mb-1 block text-sm text-slate-400">{{ __('Full name') }}</label>
                <input type="text" name="name" value="{{ old('name', $user->name) }}" required class="w-full rounded-lg border border-white/10 bg-[#1A1A2E] px-3 py-2 text-white" />
                @error('name')<p class="mt-1 text-sm text-red-400">{{ $message }}</p>@enderror
            </div>
            <div>
                <label class="mb-1 block text-sm text-slate-400">{{ __('Email') }} <span class="text-slate-500">({{ __('optional') }})</span></label>
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
            <h2 class="text-lg font-semibold text-emerald-400">{{ __('Shop profile') }}</h2>
            <p class="text-xs text-slate-500">{{ __('Shop slug and approval status are managed by the platform admin. WhatsApp and phone links you add here appear on your buyers’ dashboards when they are linked to your shop.') }}</p>
            <div>
                <label class="mb-1 block text-sm text-slate-400">{{ __('Shop name') }}</label>
                <input type="text" name="shop_name" value="{{ old('shop_name', $user->shop_name) }}" class="w-full rounded-lg border border-white/10 bg-[#1A1A2E] px-3 py-2 text-white" />
            </div>
            <div>
                <label class="mb-1 block text-sm text-slate-400">{{ __('Business description') }}</label>
                <textarea name="business_description" rows="4" class="w-full rounded-lg border border-white/10 bg-[#1A1A2E] px-3 py-2 text-white">{{ old('business_description', $user->business_description) }}</textarea>
            </div>
            <div>
                <label class="mb-1 block text-sm text-slate-400">{{ __('WhatsApp number') }}</label>
                <input type="text" name="whatsapp_number" value="{{ old('whatsapp_number', $user->whatsapp_number) }}" class="w-full rounded-lg border border-white/10 bg-[#1A1A2E] px-3 py-2 text-white" placeholder="+233…" />
            </div>
            <div>
                <label class="mb-1 block text-sm text-slate-400">{{ __('WhatsApp channel link') }}</label>
                <input type="text" name="whatsapp_channel" value="{{ old('whatsapp_channel', $user->whatsapp_channel) }}" class="w-full rounded-lg border border-white/10 bg-[#1A1A2E] px-3 py-2 text-white" />
            </div>
            <div>
                <label class="mb-1 block text-sm text-slate-400">{{ __('Profile picture') }} <span class="text-xs text-slate-500">(300×300)</span></label>
                @if ($user->profile_picture)
                    <p class="mb-2 text-xs text-slate-500">{{ __('Current') }}: <img src="{{ asset('storage/'.$user->profile_picture) }}" alt="" class="mt-1 size-20 rounded-lg object-cover" /></p>
                @endif
                <input type="file" name="profile_picture" accept="image/*" class="text-sm text-slate-300" />
                @error('profile_picture')<p class="mt-1 text-sm text-red-400">{{ $message }}</p>@enderror
            </div>
            <div>
                <label class="mb-1 block text-sm text-slate-400">{{ __('Logo') }} <span class="text-xs text-slate-500">(600×200)</span></label>
                @if ($user->logo)
                    <p class="mb-2 text-xs text-slate-500">{{ __('Current') }}: <img src="{{ asset('storage/'.$user->logo) }}" alt="" class="mt-1 max-h-16 rounded object-contain" /></p>
                @endif
                <input type="file" name="logo" accept="image/*" class="text-sm text-slate-300" />
                @error('logo')<p class="mt-1 text-sm text-red-400">{{ $message }}</p>@enderror
            </div>
        </div>

        <button type="submit" class="rounded-lg bg-emerald-500 px-6 py-2 font-semibold text-[#0f0f1a]">{{ __('Save changes') }}</button>
    </form>

    <div class="mt-8 max-w-2xl space-y-4 rounded-xl border border-red-500/30 bg-red-500/5 p-6">
        <h2 class="text-lg font-semibold text-red-300">{{ __('Delete account') }}</h2>
        <p class="text-sm text-slate-400">{{ __('This permanently removes your agent shop, bundles, wallet, and orders from this platform. Linked buyers are kept but detached from your shop. You can register again with the same username or email if you wish.') }}</p>
        <form method="post" action="{{ route('agent.profile.destroy') }}" class="space-y-4" onsubmit="return confirm(@json(__('Permanently delete your account? This cannot be undone.')))">
            @csrf
            <div>
                <label class="mb-1 block text-sm text-slate-400">{{ __('Current password') }}</label>
                <input type="password" name="current_password" required autocomplete="current-password" class="w-full rounded-lg border border-white/10 bg-[#1A1A2E] px-3 py-2 text-white" />
                @error('current_password')<p class="mt-1 text-sm text-red-400">{{ $message }}</p>@enderror
            </div>
            <label class="flex items-start gap-2 text-sm text-slate-300">
                <input type="checkbox" name="delete_account" value="1" class="mt-1 rounded border-white/20 bg-[#1A1A2E]" />
                <span>{{ __('I understand my account and related data will be permanently deleted.') }}</span>
            </label>
            @error('delete_account')<p class="text-sm text-red-400">{{ $message }}</p>@enderror
            <button type="submit" class="rounded-lg border border-red-500/50 bg-red-500/20 px-4 py-2 text-sm font-semibold text-red-200 hover:bg-red-500/30">{{ __('Delete my account') }}</button>
        </form>
    </div>
@endsection
