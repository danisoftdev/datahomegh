@extends('layouts.auth')

@section('title', __('Supplier dashboard') . ' — ' . config('app.name'))

@section('content')
    <div class="w-full max-w-lg rounded-2xl border border-white/10 bg-[#16213E]/80 p-8 text-center shadow-xl backdrop-blur-sm">
        <h1 class="mb-2 text-xl font-bold text-white">{{ __('Supplier dashboard') }}</h1>
        <p class="mb-6 text-slate-400">{{ __('Placeholder — build your admin experience here.') }}</p>
        <form method="post" action="{{ route('logout') }}">
            @csrf
            <button type="submit" class="rounded-lg bg-[#FFD700] px-5 py-2.5 text-sm font-semibold text-[#1A1A2E] hover:brightness-95">{{ __('Log out') }}</button>
        </form>
    </div>
@endsection
