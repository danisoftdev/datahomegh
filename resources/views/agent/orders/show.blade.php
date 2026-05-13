@extends('layouts.agent')

@section('title', __('Order') . ' #' . $order->id)
@section('heading', __('Order') . ' #' . $order->id)

@section('content')
    <div class="mb-6">
        <a href="{{ route('agent.orders.index', request()->query()) }}" class="text-sm text-emerald-400 hover:underline">← {{ __('My orders') }}</a>
    </div>

    <div class="grid gap-6 lg:grid-cols-3">
        <div class="rounded-xl border border-white/10 bg-[#16213E]/80 p-6 lg:col-span-2">
            <dl class="grid gap-3 text-sm sm:grid-cols-2">
                <div><dt class="text-slate-500">{{ __('Status') }}</dt><dd class="font-medium text-white">{{ $order->status }}</dd></div>
                <div><dt class="text-slate-500">{{ __('Amount') }}</dt><dd class="text-emerald-400">{{ number_format((float) $order->amount, 2) }} GHS</dd></div>
                <div><dt class="text-slate-500">{{ __('Network') }}</dt><dd>{{ $order->network }}</dd></div>
                <div><dt class="text-slate-500">{{ __('Phone') }}</dt><dd>{{ $order->phone_number }}</dd></div>
                <div><dt class="text-slate-500">{{ __('Buyer') }}</dt><dd>{{ $order->user?->username }} (#{{ $order->user_id }})</dd></div>
                <div class="sm:col-span-2"><dt class="text-slate-500">{{ __('Bundle') }}</dt><dd>{{ $order->bundlePackage?->name }} — {{ $order->bundlePackage?->size_label }}</dd></div>
            </dl>
        </div>

        <div class="space-y-4">
            <div class="rounded-xl border border-white/10 bg-[#16213E]/80 p-4">
                <h3 class="mb-3 font-semibold text-white">{{ __('Update status') }}</h3>
                <form method="post" action="{{ route('agent.orders.status', $order) }}">
                    @csrf
                    @method('PATCH')
                    <select name="status" class="mb-2 w-full rounded-lg border border-white/10 bg-[#1A1A2E] px-3 py-2 text-sm text-white">
                        <option value="PROCESSING">PROCESSING</option>
                        <option value="SENT">SENT</option>
                        <option value="FAILED">FAILED</option>
                        <option value="REFUNDED">REFUNDED</option>
                    </select>
                    <textarea name="note" rows="2" placeholder="{{ __('Note') }}" class="mb-2 w-full rounded-lg border border-white/10 bg-[#1A1A2E] px-3 py-2 text-sm text-white"></textarea>
                    <input type="hidden" name="visible_to_buyer" value="0" />
                    <label class="mb-2 flex items-center gap-2 text-xs text-slate-400">
                        <input type="checkbox" name="visible_to_buyer" value="1" checked class="size-3 rounded text-emerald-500" />
                        {{ __('Visible to buyer') }}
                    </label>
                    <button type="submit" class="w-full rounded-lg bg-emerald-500 py-2 text-sm font-semibold text-[#0f0f1a]">{{ __('Save') }}</button>
                </form>
            </div>
            <div class="rounded-xl border border-white/10 bg-[#16213E]/80 p-4">
                <h3 class="mb-3 font-semibold text-white">{{ __('Add note') }}</h3>
                <form method="post" action="{{ route('agent.orders.notes', $order) }}">
                    @csrf
                    <textarea name="note" rows="3" required class="mb-2 w-full rounded-lg border border-white/10 bg-[#1A1A2E] px-3 py-2 text-sm text-white" placeholder="{{ __('Internal note') }}"></textarea>
                    <input type="hidden" name="visible_to_buyer" value="0" />
                    <label class="mb-2 flex items-center gap-2 text-xs text-slate-400">
                        <input type="checkbox" name="visible_to_buyer" value="1" class="size-3 rounded text-emerald-500" />
                        {{ __('Visible to buyer') }}
                    </label>
                    <button type="submit" class="w-full rounded-lg border border-white/20 py-2 text-sm text-white hover:bg-white/5">{{ __('Add note') }}</button>
                </form>
            </div>
        </div>
    </div>

    <div class="mt-8 rounded-xl border border-white/10 bg-[#16213E]/80 p-6">
        <h2 class="mb-4 text-lg font-semibold text-white">{{ __('Timeline') }}</h2>
        <ul class="space-y-3 text-sm text-slate-300">
            @foreach ($histories as $h)
                <li class="border-b border-white/5 pb-3">
                    <span class="text-slate-500">{{ $h->created_at?->format('Y-m-d H:i') }}</span>
                    <span class="ml-2 text-white">{{ $h->new_status }}</span>
                    @if ($h->note)
                        <p class="mt-1 text-slate-400">{{ $h->note }}</p>
                    @endif
                </li>
            @endforeach
        </ul>
    </div>
@endsection
