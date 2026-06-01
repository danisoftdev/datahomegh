@extends('layouts.admin')

@section('title', __('Agent withdrawals') . ' — ' . config('app.name'))
@section('heading', __('Agent withdrawals'))

@section('content')
    <div class="mb-4 flex flex-wrap items-center justify-between gap-3">
        <p class="text-sm text-slate-400">{{ __('Minimum withdrawal: :min GHS · Fee: :fee GHS', ['min' => number_format((float) $minWithdrawal, 2), 'fee' => number_format((float) $withdrawalFee, 2)]) }}</p>
        <div class="flex flex-wrap gap-2 text-sm">
            @foreach (['', 'PENDING', 'PROCESSING', 'PAID', 'REJECTED'] as $st)
                <a href="{{ route('admin.withdrawals.index', $st !== '' ? ['status' => $st] : []) }}" class="rounded-lg px-3 py-1 {{ ($currentStatus === $st || ($st === '' && $currentStatus === '')) ? 'bg-[#FFD700]/20 text-[#FFD700]' : 'bg-white/5 text-slate-300 hover:bg-white/10' }}">{{ $st === '' ? __('All') : $st }}</a>
            @endforeach
        </div>
    </div>

    {{ $requests->links() }}

    <div class="overflow-x-auto rounded-xl border border-white/10 bg-[#16213E]/80">
        <table class="w-full text-left text-sm text-slate-300">
            <thead class="text-xs uppercase text-slate-500">
                <tr>
                    <th class="px-4 py-3">#</th>
                    <th class="px-4 py-3">{{ __('Agent') }}</th>
                    <th class="px-4 py-3">{{ __('Amount') }}</th>
                    <th class="px-4 py-3">{{ __('Net') }}</th>
                    <th class="px-4 py-3">{{ __('Status') }}</th>
                    <th class="px-4 py-3">{{ __('When') }}</th>
                    <th class="px-4 py-3"></th>
                </tr>
            </thead>
            <tbody>
                @foreach ($requests as $req)
                    <tr class="border-t border-white/5">
                        <td class="px-4 py-3">{{ $req->id }}</td>
                        <td class="px-4 py-3">{{ $req->agent?->username }}</td>
                        <td class="px-4 py-3">{{ number_format((float) $req->amount, 2) }}</td>
                        <td class="px-4 py-3">{{ number_format((float) $req->net_amount, 2) }}</td>
                        <td class="px-4 py-3">{{ $req->status }}</td>
                        <td class="px-4 py-3 whitespace-nowrap">{{ $req->created_at?->format('Y-m-d H:i') }}</td>
                        <td class="px-4 py-3"><a href="{{ route('admin.withdrawals.show', $req) }}" class="text-[#FFD700] hover:underline">{{ __('View') }}</a></td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
@endsection
