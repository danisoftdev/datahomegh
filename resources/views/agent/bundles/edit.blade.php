@extends('layouts.agent')

@section('title', __('Edit bundle'))
@section('heading', __('Edit bundle'))

@section('content')
    <div class="mb-4">
        <a href="{{ route('agent.bundles.resale-plans.index', $bundle) }}" class="text-sm text-emerald-400 hover:underline">{{ __('Resale plans') }} →</a>
    </div>
    <form method="post" action="{{ route('agent.bundles.update', $bundle) }}" class="max-w-xl space-y-4 rounded-xl border border-white/10 bg-[#16213E]/80 p-6">
        @csrf
        @method('PUT')
        @include('agent.bundles._form')
        <div class="flex gap-3">
            <button class="rounded-lg bg-emerald-500 px-6 py-2 font-semibold text-[#0f0f1a]">{{ __('Save') }}</button>
            <a href="{{ route('agent.bundles.index') }}" class="rounded-lg border border-white/20 px-6 py-2 text-slate-300">{{ __('Cancel') }}</a>
        </div>
    </form>
    <form method="post" action="{{ route('agent.bundles.destroy', $bundle) }}" class="mt-4 max-w-xl" onsubmit="return confirm(@json(__('Delete this bundle?')))">
        @csrf
        @method('DELETE')
        <button class="rounded-lg bg-red-600/80 px-4 py-2 text-sm text-white">{{ __('Delete bundle') }}</button>
    </form>
@endsection
