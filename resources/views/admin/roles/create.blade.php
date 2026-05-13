@extends('layouts.admin')

@section('title', __('Create role') . ' — ' . config('app.name'))
@section('heading', __('Create role'))

@section('content')
    <div class="mb-6">
        <a href="{{ route('admin.roles.index') }}" class="text-sm text-[#FFD700] hover:underline">← {{ __('Back to roles') }}</a>
    </div>

    <h1 class="mb-6 text-2xl font-bold text-white">{{ __('Create New Role') }}</h1>

    <form method="post" action="{{ route('admin.roles.store') }}" class="max-w-lg space-y-6 rounded-xl border border-white/10 bg-[#16213E]/80 p-6">
        @csrf
        <div>
            <label for="name" class="mb-1.5 block text-sm font-medium text-slate-300">{{ __('Role name') }}</label>
            <input id="name" name="name" type="text" value="{{ old('name') }}" required maxlength="100"
                class="w-full rounded-lg border border-white/10 bg-[#1A1A2E] px-3 py-2 text-white placeholder:text-slate-500 focus:border-[#FFD700]/50 focus:outline-none focus:ring-2 focus:ring-[#FFD700]/20" />
            @error('name')
                <p class="mt-1 text-sm text-red-400">{{ $message }}</p>
            @enderror
            <p class="mt-2 text-xs text-slate-500">{{ __('A URL-safe slug will be generated automatically. You can assign permissions after saving.') }}</p>
        </div>
        <div class="flex gap-3">
            <button type="submit" class="rounded-lg bg-[#FFD700] px-5 py-2 text-sm font-semibold text-[#1A1A2E] hover:brightness-95">{{ __('Create & continue') }}</button>
            <a href="{{ route('admin.roles.index') }}" class="rounded-lg border border-white/15 px-5 py-2 text-sm text-slate-300 hover:bg-white/5">{{ __('Cancel') }}</a>
        </div>
    </form>
@endsection
