@extends('layouts.admin')

@section('title', __('New bundle'))
@section('heading', __('New bundle'))

@section('content')
    <form method="post" action="{{ route('admin.bundles.store') }}" class="max-w-xl space-y-4 rounded-xl border border-white/10 bg-[#16213E]/80 p-6">
        @csrf
        @include('admin.bundles._form')
        <button class="rounded-lg bg-[#FFD700] px-6 py-2 font-semibold text-[#1A1A2E]">{{ __('Create') }}</button>
    </form>
@endsection
