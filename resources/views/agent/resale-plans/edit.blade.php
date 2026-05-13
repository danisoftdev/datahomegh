@extends('layouts.agent')

@section('title', __('Edit resale plan'))
@section('heading', __('Edit resale plan'))

@section('content')
    <div class="mb-4 text-sm text-slate-400">{{ $bundle->name }}</div>
    <form method="post" action="{{ route('agent.bundles.resale-plans.update', [$bundle, $resalePlan]) }}" class="max-w-xl space-y-4 rounded-xl border border-white/10 bg-[#16213E]/80 p-6">
        @csrf
        @method('PUT')
        @php($editing = true)
        @include('agent.resale-plans._form')
        <div class="flex gap-3">
            <button class="rounded-lg bg-emerald-500 px-6 py-2 font-semibold text-[#0f0f1a]">{{ __('Save') }}</button>
            <a href="{{ route('agent.bundles.resale-plans.index', $bundle) }}" class="rounded-lg border border-white/20 px-6 py-2 text-slate-300">{{ __('Cancel') }}</a>
        </div>
    </form>
@endsection
