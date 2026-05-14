@extends('layouts.admin')

@section('title', __('Create agent') . ' — ' . config('app.name'))
@section('heading', __('Create agent'))

@section('content')
    <div class="mb-4 flex flex-wrap items-center gap-3">
        <a href="{{ route('admin.users.index', ['role' => \App\Models\Role::SLUG_AGENT]) }}" class="text-sm text-[#FFD700] hover:underline">← {{ __('Agents') }}</a>
    </div>

    <p class="mb-6 max-w-2xl text-sm text-slate-400">
        {{ __('Creates an active agent with a shop slug and wallet. Share the username and password with them securely. A welcome email is sent to the address you enter.') }}
    </p>

    <form method="post" action="{{ route('admin.users.store-agent') }}" class="max-w-2xl space-y-6">
        @csrf

        <div class="space-y-4 rounded-xl border border-white/10 bg-[#16213E]/80 p-6">
            <div>
                <label class="mb-1 block text-sm text-slate-400">{{ __('Username') }}</label>
                <input type="text" name="username" value="{{ old('username') }}" required autocomplete="username"
                    class="w-full rounded-lg border border-white/10 bg-[#1A1A2E] px-3 py-2 text-white" />
                @error('username')<p class="mt-1 text-sm text-red-400">{{ $message }}</p>@enderror
            </div>
            <div>
                <label class="mb-1 block text-sm text-slate-400">{{ __('Full name') }}</label>
                <input type="text" name="name" value="{{ old('name') }}" required autocomplete="name"
                    class="w-full rounded-lg border border-white/10 bg-[#1A1A2E] px-3 py-2 text-white" />
                @error('name')<p class="mt-1 text-sm text-red-400">{{ $message }}</p>@enderror
            </div>
            <div>
                <label class="mb-1 block text-sm text-slate-400">{{ __('Email') }}</label>
                <input type="email" name="email" value="{{ old('email') }}" required autocomplete="email"
                    class="w-full rounded-lg border border-white/10 bg-[#1A1A2E] px-3 py-2 text-white" />
                @error('email')<p class="mt-1 text-sm text-red-400">{{ $message }}</p>@enderror
            </div>
            <div>
                <label class="mb-1 block text-sm text-slate-400">{{ __('Phone') }}</label>
                <input type="text" name="phone" value="{{ old('phone') }}" required inputmode="numeric" autocomplete="tel" placeholder="0XXXXXXXXX"
                    class="w-full rounded-lg border border-white/10 bg-[#1A1A2E] px-3 py-2 text-white" />
                @error('phone')<p class="mt-1 text-sm text-red-400">{{ $message }}</p>@enderror
            </div>
            <div>
                <label class="mb-1 block text-sm text-slate-400">{{ __('Shop / brand name') }}</label>
                <input type="text" name="shop_name" value="{{ old('shop_name') }}" required
                    class="w-full rounded-lg border border-white/10 bg-[#1A1A2E] px-3 py-2 text-white" />
                @error('shop_name')<p class="mt-1 text-sm text-red-400">{{ $message }}</p>@enderror
            </div>
            <div>
                <label class="mb-1 block text-sm text-slate-400">{{ __('Password') }}</label>
                <input type="password" name="password" required autocomplete="new-password"
                    class="w-full rounded-lg border border-white/10 bg-[#1A1A2E] px-3 py-2 text-white" />
                @error('password')<p class="mt-1 text-sm text-red-400">{{ $message }}</p>@enderror
            </div>
            <div>
                <label class="mb-1 block text-sm text-slate-400">{{ __('Confirm password') }}</label>
                <input type="password" name="password_confirmation" required autocomplete="new-password"
                    class="w-full rounded-lg border border-white/10 bg-[#1A1A2E] px-3 py-2 text-white" />
            </div>
        </div>

        <div class="flex flex-wrap gap-3">
            <button type="submit" class="rounded-lg bg-[#FFD700] px-5 py-2.5 text-sm font-semibold text-[#1A1A2E] hover:brightness-95">
                {{ __('Create agent account') }}
            </button>
            <a href="{{ route('admin.users.index', ['role' => \App\Models\Role::SLUG_AGENT]) }}" class="rounded-lg border border-white/20 px-5 py-2.5 text-sm text-white hover:bg-white/5">
                {{ __('Cancel') }}
            </a>
        </div>
    </form>
@endsection
