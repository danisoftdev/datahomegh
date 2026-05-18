@extends('layouts.admin')

@section('title', __('Edit API profile'))
@section('heading', __('Edit API profile'))

@section('content')
    <div class="mb-6">
        <a href="{{ route('admin.fulfillment-apis.index') }}" class="text-sm text-[#FFD700] hover:underline">← {{ __('External data APIs') }}</a>
    </div>

    <div class="max-w-xl rounded-xl border border-white/10 bg-[#16213E]/80 p-6">
        <p class="mb-4 text-sm text-slate-400">{{ __('Network: :n (cannot be changed here)', ['n' => $profile->network]) }}</p>

        <form method="post" action="{{ route('admin.fulfillment-apis.update', $profile) }}" class="space-y-4">
            @csrf
            @method('PATCH')
            <div>
                <label class="mb-1 block text-sm text-slate-400">{{ __('Label') }}</label>
                <input type="text" name="name" value="{{ old('name', $profile->name) }}" required maxlength="120" class="w-full rounded-lg border border-white/10 bg-[#1A1A2E] px-3 py-2 text-white" />
                @error('name')
                    <p class="mt-1 text-sm text-red-400">{{ $message }}</p>
                @enderror
            </div>
            <div>
                <label class="mb-1 block text-sm text-slate-400">{{ __('Base URL') }}</label>
                <input type="url" name="base_url" value="{{ old('base_url', $profile->base_url) }}" required maxlength="512" class="w-full rounded-lg border border-white/10 bg-[#1A1A2E] px-3 py-2 text-white" />
                @error('base_url')
                    <p class="mt-1 text-sm text-red-400">{{ $message }}</p>
                @enderror
            </div>
            <div>
                <label class="mb-1 block text-sm text-slate-400">{{ __('New API key (leave blank to keep current)') }}</label>
                <input type="password" name="api_key" value="" autocomplete="new-password" maxlength="2000" class="w-full rounded-lg border border-white/10 bg-[#1A1A2E] px-3 py-2 text-white" />
                @error('api_key')
                    <p class="mt-1 text-sm text-red-400">{{ $message }}</p>
                @enderror
            </div>
            <button type="submit" class="rounded-lg bg-[#FFD700] px-6 py-2 font-semibold text-[#1A1A2E]">{{ __('Save') }}</button>
        </form>
    </div>
@endsection
