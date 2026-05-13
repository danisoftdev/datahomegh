@extends('layouts.admin')

@section('title', __('Edit bundle'))
@section('heading', __('Edit bundle'))

@section('content')
    <form method="post" action="{{ route('admin.bundles.update', $bundle) }}" class="max-w-xl space-y-4 rounded-xl border border-white/10 bg-[#16213E]/80 p-6">
        @csrf
        @method('PUT')
        @include('admin.bundles._form')
        <div class="flex gap-3">
            <button class="rounded-lg bg-[#FFD700] px-6 py-2 font-semibold text-[#1A1A2E]">{{ __('Save') }}</button>
            <a href="{{ route('admin.bundles.index') }}" class="rounded-lg border border-white/20 px-6 py-2 text-slate-300">{{ __('Cancel') }}</a>
        </div>
    </form>
    <form method="post" action="{{ route('admin.bundles.destroy', $bundle) }}" class="mt-4 max-w-xl" onsubmit="return confirm(@json(__('Delete this bundle?')))">
        @csrf
        @method('DELETE')
        <button class="rounded-lg bg-red-600/80 px-4 py-2 text-sm text-white">{{ __('Delete bundle') }}</button>
    </form>
@endsection
