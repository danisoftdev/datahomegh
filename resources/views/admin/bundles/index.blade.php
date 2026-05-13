@extends('layouts.admin')

@section('title', __('Bundles') . ' — ' . config('app.name'))
@section('heading', __('Bundles'))

@section('content')
    <div class="mb-6 flex items-center justify-between">
        <h1 class="text-2xl font-bold text-white">{{ __('Bundles') }}</h1>
        <a href="{{ route('admin.bundles.create') }}" class="rounded-lg bg-[#FFD700] px-4 py-2 text-sm font-semibold text-[#1A1A2E]">{{ __('New bundle') }}</a>
    </div>

    <div class="overflow-x-auto rounded-xl border border-white/10 bg-[#16213E]/80">
        <table class="w-full text-left text-sm text-slate-300">
            <thead class="border-b border-white/10 text-xs uppercase text-slate-500">
                <tr>
                    <th class="px-3 py-2">#</th>
                    <th class="px-3 py-2">{{ __('Network') }}</th>
                    <th class="px-3 py-2">{{ __('Name') }}</th>
                    <th class="px-3 py-2">{{ __('Stock') }}</th>
                    <th class="px-3 py-2">{{ __('Avail') }}</th>
                    <th class="px-3 py-2">{{ __('Cost') }}</th>
                    <th class="px-3 py-2"></th>
                </tr>
            </thead>
            <tbody>
                @foreach ($bundles as $b)
                    <tr class="border-b border-white/5">
                        <td class="px-3 py-2">{{ $b->id }}</td>
                        <td class="px-3 py-2">{{ $b->network }}</td>
                        <td class="px-3 py-2">{{ $b->name }}</td>
                        <td class="px-3 py-2">{{ $b->stock_count }}</td>
                        <td class="px-3 py-2">{{ $b->is_available ? __('Yes') : __('No') }}</td>
                        <td class="px-3 py-2">{{ number_format((float) $b->internal_cost, 2) }}</td>
                        <td class="px-3 py-2">
                            <a href="{{ route('admin.bundles.edit', $b) }}" class="text-[#FFD700] hover:underline">{{ __('Edit') }}</a>
                            <form method="post" action="{{ route('admin.bundles.availability', $b) }}" class="inline">
                                @csrf
                                @method('PATCH')
                                <button type="submit" class="ml-2 text-xs text-slate-400 hover:text-white">{{ __('Toggle') }}</button>
                            </form>
                        </td>
                    </tr>
                    <tr class="border-b border-white/10 bg-black/20">
                        <td colspan="7" class="px-3 py-2">
                            <form method="post" action="{{ route('admin.bundles.stock', $b) }}" class="flex flex-wrap items-center gap-2 text-xs">
                                @csrf
                                <label class="text-slate-500">{{ __('Set stock') }}</label>
                                <input type="number" name="stock_count" min="0" value="{{ $b->stock_count }}" class="w-24 rounded border border-white/10 bg-[#1A1A2E] px-2 py-1 text-white" />
                                <button type="submit" class="rounded bg-white/10 px-2 py-1 text-slate-200">{{ __('Update') }}</button>
                            </form>
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
    {{ $bundles->links() }}
@endsection
