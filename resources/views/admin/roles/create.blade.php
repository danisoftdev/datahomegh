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
            <p class="mt-2 text-xs text-slate-500">{{ __('Custom roles are not shown on registration — only you can assign them from Users.') }}</p>
        </div>
        <div>
            <label for="pricing_persona" class="mb-1.5 block text-sm font-medium text-slate-300">{{ __('Checkout experience') }}</label>
            <select id="pricing_persona" name="pricing_persona" class="w-full rounded-lg border border-white/10 bg-[#1A1A2E] px-3 py-2 text-white">
                <option value="buyer" @selected(old('pricing_persona', 'buyer') === 'buyer')>{{ __('Buyer-style — platform catalogue like a buyer') }}</option>
                <option value="agent" @selected(old('pricing_persona') === 'agent')>{{ __('Agent-style — platform wholesale catalogue') }}</option>
            </select>
            <p class="mt-2 text-xs text-slate-500">{{ __('Promoted agents keep their shop; only platform data prices change.') }}</p>
        </div>
        <div class="rounded-lg border border-white/10 bg-[#1A1A2E]/50 p-4 space-y-3">
            <label class="flex items-center gap-2 text-sm text-slate-300">
                <input type="hidden" name="requires_promotion_fee" value="0" />
                <input type="checkbox" name="requires_promotion_fee" value="1" class="size-4 rounded border-white/20" @checked(old('requires_promotion_fee')) />
                {{ __('Charge a fee when you promote a user to this role') }}
            </label>
            <div>
                <label for="promotion_fee" class="mb-1 block text-xs text-slate-500">{{ __('Promotion fee (GHS, debited from user wallet)') }}</label>
                <input id="promotion_fee" name="promotion_fee" type="number" step="0.01" min="0" value="{{ old('promotion_fee') }}" class="w-full rounded-lg border border-white/10 bg-[#1A1A2E] px-3 py-2 text-white" />
            </div>
        </div>
        <p class="text-xs text-slate-500">{{ __('After saving, set per-bundle prices under Bundles and assign permissions below.') }}</p>
        <div class="flex gap-3">
            <button type="submit" class="rounded-lg bg-[#FFD700] px-5 py-2 text-sm font-semibold text-[#1A1A2E] hover:brightness-95">{{ __('Create & continue') }}</button>
            <a href="{{ route('admin.roles.index') }}" class="rounded-lg border border-white/15 px-5 py-2 text-sm text-slate-300 hover:bg-white/5">{{ __('Cancel') }}</a>
        </div>
    </form>
@endsection
