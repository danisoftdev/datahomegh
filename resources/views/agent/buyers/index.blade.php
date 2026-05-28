@extends('layouts.agent')

@section('title', __('My buyers') . ' — ' . config('app.name'))
@section('heading', __('My buyers'))

@section('content')
    <h1 class="mb-6 text-2xl font-bold text-white">{{ __('My buyers') }}</h1>

    <div class="overflow-x-auto rounded-xl border border-white/10 bg-[#16213E]/80">
        <table class="w-full text-left text-sm text-slate-300">
            <thead class="border-b border-white/10 text-xs uppercase text-slate-500">
                <tr>
                    <th class="px-3 py-2">#</th>
                    <th class="px-3 py-2">{{ __('Username') }}</th>
                    <th class="px-3 py-2">{{ __('Name') }}</th>
                    <th class="px-3 py-2">{{ __('Status') }}</th>
                    <th class="px-3 py-2">{{ __('Wallet') }}</th>
                    <th class="px-3 py-2"></th>
                </tr>
            </thead>
            <tbody>
                @foreach ($buyers as $b)
                    <tr class="border-b border-white/5">
                        <td class="px-3 py-2">{{ $b->id }}</td>
                        <td class="px-3 py-2">{{ $b->username }}</td>
                        <td class="px-3 py-2">{{ $b->name }}</td>
                        <td class="px-3 py-2">{{ $b->status }}</td>
                        <td class="px-3 py-2">
                            @if ($b->wallet)
                                <span class="font-medium text-emerald-300">{{ number_format((float) $b->wallet->balance, 2) }} GHS</span>
                                @if ($b->wallet->is_frozen)
                                    <span class="ml-1 text-xs text-amber-400">({{ __('frozen') }})</span>
                                @endif
                            @else
                                —
                            @endif
                        </td>
                        <td class="px-3 py-2">
                            <a href="{{ route('agent.buyers.show', $b) }}" class="text-emerald-400 hover:underline">{{ __('View') }}</a>
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
    {{ $buyers->links() }}
@endsection
