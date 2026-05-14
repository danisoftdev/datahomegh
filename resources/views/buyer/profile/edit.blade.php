@extends('layouts.app')

@section('title', __('Profile') . ' — ' . config('app.name'))
@section('nav_variant', 'buyer')

@section('content')
    <h1 class="mb-6 text-2xl font-bold text-white">{{ __('Your profile') }}</h1>

    <form method="post" action="{{ route('buyer.profile.update') }}" enctype="multipart/form-data" class="max-w-2xl space-y-6 rounded-xl border border-white/10 bg-[#16213E]/80 p-6">
        @csrf
        @method('PUT')

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
        <div>
            <label class="mb-1 block text-sm text-slate-400">{{ __('Profile picture') }}</label>
            @if ($user->profile_picture)
                <p class="mb-2 text-xs text-slate-500">{{ __('Current') }}: <img src="{{ asset('storage/'.$user->profile_picture) }}" alt="" class="mt-1 size-20 rounded-lg object-cover" /></p>
            @endif
            <input type="file" name="profile_picture" accept="image/*" class="text-sm text-slate-300" />
            @error('profile_picture')<p class="mt-1 text-sm text-red-400">{{ $message }}</p>@enderror
        </div>

        <button type="submit" class="rounded-lg bg-primary px-6 py-2 font-semibold text-[#0f0f1a]">{{ __('Save changes') }}</button>
    </form>
@endsection
