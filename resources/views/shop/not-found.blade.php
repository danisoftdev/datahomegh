@extends('layouts.shop-public')

@section('title', __('Shop not found').' — DataHomeGH')
@section('meta_description', __('This shop link is invalid or the store is no longer available on DataHomeGH.'))

@section('content')
    <div class="flex min-h-[70vh] flex-col items-center justify-center px-4 py-16 text-center">
        <div class="mb-8 flex h-24 w-24 items-center justify-center rounded-2xl bg-linear-to-br from-[#B8860B] via-[#FFD700] to-[#DAA520] shadow-lg">
            <span class="text-3xl font-bold text-[#1A1A2E]">DH</span>
        </div>
        <h1 class="text-2xl font-bold text-white sm:text-3xl">{{ __('We couldn’t find this shop') }}</h1>
        <p class="mt-3 max-w-md text-slate-400">
            {{ __('The link “:slug” doesn’t match an active DataHomeGH agent store. Double-check the URL or ask the seller for their current shop link.', ['slug' => $slug]) }}
        </p>
        <a href="{{ url('/') }}" class="mt-8 inline-flex rounded-lg bg-[#FFD700] px-6 py-3 text-sm font-semibold text-[#1A1A2E] transition hover:bg-[#e6c200]">
            {{ __('Back to DataHomeGH') }}
        </a>
        <p class="mt-12 text-xs text-slate-500">Powered by DataHomeGH | datahomegh.shop</p>
    </div>
@endsection
