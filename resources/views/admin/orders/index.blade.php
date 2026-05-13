@extends('layouts.admin')

@section('title', __('Orders') . ' — ' . config('app.name'))
@section('heading', __('Orders'))

@section('content')
    <div class="mb-6 flex flex-wrap items-center justify-between gap-4">
        <h1 class="text-2xl font-bold text-white">{{ __('Orders') }}</h1>
        <a href="{{ route('admin.orders.export', request()->query()) }}" class="rounded-lg border border-white/20 px-4 py-2 text-sm text-primary hover:bg-white/5">{{ __('Export CSV') }}</a>
    </div>

    <div class="mb-4 md:hidden" x-data="{ filtersOpen: false }">
        <button type="button" @click="filtersOpen = !filtersOpen" class="flex w-full items-center justify-center gap-2 rounded-xl border border-white/10 bg-navy/80 px-4 py-3 text-sm font-medium text-white">
            <svg class="size-5 text-primary" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 4a1 1 0 011-1h16a1 1 0 011 1v2.586a1 1 0 01-.293.707l-6.414 6.414a1 1 0 00-.293.707V17l-4 4v-6.586a1 1 0 00-.293-.707L3.293 7.293A1 1 0 013 6.586V4z"/></svg>
            {{ __('Filters') }}
        </button>
        <div x-show="filtersOpen" x-cloak class="mt-2" style="display: none;">
            @include('admin.orders.partials.filters')
        </div>
    </div>

    <div class="mb-6 hidden md:block">
        @include('admin.orders.partials.filters')
    </div>

    <form method="post" action="{{ route('admin.orders.bulk-update') }}" class="space-y-4" x-data="{
        allSelected: false,
        orderCheckboxes: [],
        init() {
            this.orderCheckboxes = Array.from(this.$el.querySelectorAll('.order-cb'));
        },
        toggleSelectAll() {
            this.allSelected = !this.allSelected;
            this.orderCheckboxes.forEach((cb) => { cb.checked = this.allSelected; });
        },
        syncMaster() {
            const n = this.orderCheckboxes.length;
            const c = this.orderCheckboxes.filter((x) => x.checked).length;
            this.allSelected = n > 0 && c === n;
        }
    }">
        @csrf
        <div class="flex flex-wrap items-center gap-3">
            <select name="status" required class="rounded-lg border border-white/10 bg-dark px-3 py-2 text-sm text-white">
                <option value="PROCESSING">PROCESSING</option>
                <option value="SENT">SENT</option>
                <option value="FAILED">FAILED</option>
                <option value="REFUNDED">REFUNDED</option>
            </select>
            <input type="text" name="note" placeholder="{{ __('Note (optional)') }}" class="min-w-48 flex-1 rounded-lg border border-white/10 bg-dark px-3 py-2 text-sm text-white" />
            <label class="flex items-center gap-2 text-sm text-slate-300">
                <input type="hidden" name="visible_to_buyer" value="0" />
                <input type="checkbox" name="visible_to_buyer" value="1" checked class="size-4 rounded border-white/20 text-primary" />
                {{ __('Visible to buyer') }}
            </label>
            <button type="submit" class="rounded-lg bg-primary px-4 py-2 text-sm font-semibold text-dark">{{ __('Bulk update') }}</button>
        </div>

        <div class="-mx-4 overflow-x-auto px-4 sm:mx-0 sm:px-0">
            <div class="inline-block min-w-full rounded-xl border border-white/10 bg-navy/80 align-middle">
                <table class="min-w-4xl w-full text-left text-sm text-slate-300">
                    <thead class="sticky top-0 z-10 border-b border-white/10 bg-navy text-xs uppercase text-slate-500 shadow-sm">
                        <tr>
                            <th class="px-3 py-3">
                                <input type="checkbox" class="rounded border-white/20" @click.prevent="toggleSelectAll()" :aria-checked="allSelected" />
                            </th>
                            <th class="px-3 py-3">#</th>
                            <th class="px-3 py-3">{{ __('When') }}</th>
                            <th class="px-3 py-3">{{ __('User') }}</th>
                            <th class="px-3 py-3">{{ __('Agent') }}</th>
                            <th class="px-3 py-3">{{ __('Net') }}</th>
                            <th class="px-3 py-3">{{ __('Phone') }}</th>
                            <th class="px-3 py-3">{{ __('Status') }}</th>
                            <th class="px-3 py-3">{{ __('Amt') }}</th>
                            <th class="px-3 py-3"></th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($orders as $o)
                            <tr class="border-b border-white/5">
                                <td class="px-3 py-2">
                                    <input class="order-cb rounded border-white/20" type="checkbox" name="order_ids[]" value="{{ $o->id }}" @change="syncMaster()" />
                                </td>
                                <td class="px-3 py-2 font-mono text-xs">{{ $o->id }}</td>
                                <td class="px-3 py-2 whitespace-nowrap">{{ $o->created_at?->format('Y-m-d H:i') }}</td>
                                <td class="px-3 py-2">{{ $o->user?->username }}</td>
                                <td class="px-3 py-2">{{ $o->agent?->username ?? '—' }}</td>
                                <td class="px-3 py-2">{{ $o->network }}</td>
                                <td class="px-3 py-2">{{ $o->phone_number }}</td>
                                <td class="px-3 py-2"><x-status-badge :status="$o->status" /></td>
                                <td class="px-3 py-2 tabular-nums">{{ number_format((float) $o->amount, 2) }}</td>
                                <td class="px-3 py-2"><a href="{{ route('admin.orders.show', $o) }}" class="text-primary hover:underline">{{ __('View') }}</a></td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
        {{ $orders->links() }}
    </form>
@endsection
