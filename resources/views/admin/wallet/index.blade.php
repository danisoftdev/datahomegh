@extends('layouts.admin')

@section('title', __('Wallet controls'))
@section('heading', __('Wallet controls'))

@section('content')
    <h1 class="mb-6 text-2xl font-bold text-white">{{ __('Wallet controls') }}</h1>

    <div class="grid gap-8 lg:grid-cols-2">
        <div class="rounded-xl border border-white/10 bg-[#16213E]/80 p-6">
            <h2 class="mb-4 text-lg font-semibold text-white">{{ __('Credit user') }}</h2>
            <form method="post" action="{{ route('admin.wallet.credit') }}" class="space-y-3">
                @csrf
                <div>
                    <label class="mb-1 block text-sm text-slate-400">{{ __('User ID') }}</label>
                    <input type="number" name="user_id" required min="1" class="w-full rounded-lg border border-white/10 bg-[#1A1A2E] px-3 py-2 text-white" />
                </div>
                <div>
                    <label class="mb-1 block text-sm text-slate-400">{{ __('Amount (GHS)') }}</label>
                    <input type="number" step="0.01" name="amount" required min="0.01" class="w-full rounded-lg border border-white/10 bg-[#1A1A2E] px-3 py-2 text-white" />
                </div>
                <div>
                    <label class="mb-1 block text-sm text-slate-400">{{ __('Note') }}</label>
                    <textarea name="note" rows="2" class="w-full rounded-lg border border-white/10 bg-[#1A1A2E] px-3 py-2 text-white"></textarea>
                </div>
                <button class="rounded-lg bg-[#FFD700] px-4 py-2 font-semibold text-[#1A1A2E]">{{ __('Credit wallet') }}</button>
            </form>
        </div>
        <div class="rounded-xl border border-white/10 bg-[#16213E]/80 p-6">
            <h2 class="mb-4 text-lg font-semibold text-white">{{ __('Debit user') }}</h2>
            <form method="post" action="{{ route('admin.wallet.debit') }}" class="space-y-3">
                @csrf
                <div>
                    <label class="mb-1 block text-sm text-slate-400">{{ __('User ID') }}</label>
                    <input type="number" name="user_id" required min="1" class="w-full rounded-lg border border-white/10 bg-[#1A1A2E] px-3 py-2 text-white" />
                </div>
                <div>
                    <label class="mb-1 block text-sm text-slate-400">{{ __('Amount (GHS)') }}</label>
                    <input type="number" step="0.01" name="amount" required min="0.01" class="w-full rounded-lg border border-white/10 bg-[#1A1A2E] px-3 py-2 text-white" />
                </div>
                <div>
                    <label class="mb-1 block text-sm text-slate-400">{{ __('Note') }}</label>
                    <textarea name="note" rows="2" class="w-full rounded-lg border border-white/10 bg-[#1A1A2E] px-3 py-2 text-white"></textarea>
                </div>
                <button class="rounded-lg border border-red-400/50 bg-red-500/20 px-4 py-2 font-semibold text-red-100">{{ __('Debit wallet') }}</button>
            </form>
        </div>
    </div>
@endsection
