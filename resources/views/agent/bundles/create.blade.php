@extends('layouts.agent')

@section('title', __('New bundle'))
@section('heading', __('New bundle'))

@section('content')
    <form method="post" action="{{ route('agent.bundles.store') }}" class="max-w-xl space-y-4 rounded-xl border border-white/10 bg-[#16213E]/80 p-6">
        @csrf
        @include('agent.bundles._form')
        <button class="rounded-lg bg-emerald-500 px-6 py-2 font-semibold text-[#0f0f1a]">{{ __('Create') }}</button>
    </form>
@endsection
