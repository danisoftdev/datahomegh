@extends('layouts.auth')

@section('title', __('Pending approval') . ' — ' . config('app.name'))

@section('content')
    <div class="w-full max-w-lg rounded-2xl border border-white/10 bg-[#16213E]/80 p-8 shadow-xl backdrop-blur-sm">
        <div class="mb-6 flex justify-center">
            <span class="inline-flex size-14 items-center justify-center rounded-full border border-[#FFD700]/40 bg-[#FFD700]/10 text-2xl" aria-hidden="true">⏳</span>
        </div>

        <h1 class="mb-3 text-center text-2xl font-bold text-white">{{ __('Awaiting approval') }}</h1>
        <p class="mb-6 text-center text-slate-400">
            {{ __('Your registration was received. An administrator will review and activate your account. You will be able to log in once your status is active.') }}
        </p>

        <div class="rounded-xl border border-white/10 bg-[#1A1A2E]/60 p-5">
            <h2 class="mb-2 text-sm font-semibold uppercase tracking-wide text-[#FFD700]">{{ __('Need it faster?') }}</h2>
            <p class="mb-4 text-sm text-slate-300">
                {{ __('Message our team on WhatsApp with your registered username so we can match your account.') }}
            </p>
            <a href="{{ $whatsappUrl }}" target="_blank" rel="noopener noreferrer"
                class="inline-flex w-full items-center justify-center gap-2 rounded-lg bg-[#FFD700] px-4 py-3 text-center text-sm font-semibold text-[#1A1A2E] shadow-lg transition hover:brightness-95 focus:outline-none focus:ring-2 focus:ring-[#FFD700] focus:ring-offset-2 focus:ring-offset-[#1A1A2E]">
                {{ __('Contact admin on WhatsApp') }}
            </a>
        </div>

        <p class="mt-8 text-center text-sm text-slate-500">
            <a href="{{ route('login') }}" class="text-[#FFD700] hover:underline">{{ __('Back to log in') }}</a>
            <span class="mx-2 text-slate-600">·</span>
            <a href="{{ url('/') }}" class="text-slate-400 hover:text-slate-300">{{ __('Home') }}</a>
        </p>
    </div>
@endsection
