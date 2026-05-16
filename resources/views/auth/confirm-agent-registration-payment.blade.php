@extends('layouts.auth')

@section('title', __('Confirm agent registration payment') . ' — ' . config('app.name'))

@section('content')
    <div class="w-full max-w-md rounded-2xl border border-white/10 bg-[#16213E]/80 p-8 shadow-xl backdrop-blur-sm">
        <h1 class="mb-2 text-center text-2xl font-bold tracking-tight text-white">{{ __('Confirm your registration payment') }}</h1>
        <p class="mb-6 text-center text-sm text-slate-400">
            {{ __('If you paid on Paystack but were not redirected back, enter the reference from your receipt. No webhook setup is required — we verify directly with Paystack.') }}
        </p>

        <form method="get" action="{{ route('register.agent-fee.callback') }}" class="space-y-5">
            <div>
                <label for="reference" class="mb-1.5 block text-sm font-medium text-slate-300">{{ __('Paystack reference') }}</label>
                <input id="reference" name="reference" value="{{ old('reference', request('reference')) }}" required
                    placeholder="{{ __('e.g. T1234567890123456789') }}"
                    class="w-full rounded-lg border border-white/10 bg-[#1A1A2E] px-4 py-2.5 font-mono text-sm text-white placeholder:text-slate-500 focus:border-[#FFD700]/50 focus:outline-none focus:ring-2 focus:ring-[#FFD700]/30" />
            </div>

            <button type="submit"
                class="w-full rounded-lg bg-[#FFD700] px-4 py-3 text-center text-sm font-semibold text-[#1A1A2E] shadow-lg transition hover:brightness-95">
                {{ __('Verify payment') }}
            </button>
        </form>

        <p class="mt-6 text-center text-sm text-slate-400">
            <a href="{{ route('login') }}" class="font-medium text-[#FFD700] hover:underline">{{ __('Back to log in') }}</a>
        </p>
    </div>
@endsection
