@extends('layouts.agent')

@section('title', __('New resale plan'))
@section('heading', __('New resale plan'))

@section('content')
    <div class="mb-4 text-sm text-slate-400">{{ $bundle->name }}</div>
    <form method="post" action="{{ route('agent.bundles.resale-plans.store', $bundle) }}" class="max-w-xl space-y-4 rounded-xl border border-white/10 bg-[#16213E]/80 p-6">
        @csrf
        @php($editing = false)
        @include('agent.resale-plans._form')
        <button class="rounded-lg bg-emerald-500 px-6 py-2 font-semibold text-[#0f0f1a]">{{ __('Create') }}</button>
    </form>
@endsection
