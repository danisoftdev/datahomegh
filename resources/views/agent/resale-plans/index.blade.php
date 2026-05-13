@extends('layouts.agent')

@section('title', __('Resale plans') . ' — ' . $bundle->name)
@section('heading', __('Resale plans'))

@section('content')
    <div class="mb-6 flex flex-wrap items-center justify-between gap-4">
        <div>
            <a href="{{ route('agent.bundles.index') }}" class="text-sm text-emerald-400 hover:underline">← {{ __('My bundles') }}</a>
            <h1 class="mt-2 text-2xl font-bold text-white">{{ $bundle->name }}</h1>
            <p class="text-sm text-slate-500">{{ $bundle->network }} · {{ $bundle->size_label }}</p>
        </div>
        <a href="{{ route('agent.bundles.resale-plans.create', $bundle) }}" class="rounded-lg bg-emerald-500 px-4 py-2 text-sm font-semibold text-[#0f0f1a]">{{ __('New plan') }}</a>
    </div>

    <div class="overflow-x-auto rounded-xl border border-white/10 bg-[#16213E]/80">
        <table class="w-full text-left text-sm text-slate-300">
            <thead class="border-b border-white/10 text-xs uppercase text-slate-500">
                <tr>
                    <th class="px-3 py-2">#</th>
                    <th class="px-3 py-2">{{ __('Label') }}</th>
                    <th class="px-3 py-2">{{ __('Price') }}</th>
                    <th class="px-3 py-2">{{ __('Active') }}</th>
                    <th class="px-3 py-2"></th>
                </tr>
            </thead>
            <tbody>
                @foreach ($plans as $p)
                    <tr class="border-b border-white/5">
                        <td class="px-3 py-2">{{ $p->id }}</td>
                        <td class="px-3 py-2">{{ $p->label }}</td>
                        <td class="px-3 py-2">{{ number_format((float) $p->price, 2) }}</td>
                        <td class="px-3 py-2">{{ $p->is_active ? __('Yes') : __('No') }}</td>
                        <td class="px-3 py-2">
                            <a href="{{ route('agent.bundles.resale-plans.edit', [$bundle, $p]) }}" class="text-emerald-400 hover:underline">{{ __('Edit') }}</a>
                            <form method="post" action="{{ route('agent.bundles.resale-plans.destroy', [$bundle, $p]) }}" class="inline" onsubmit="return confirm(@json(__('Delete this plan?')))">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="ml-2 text-xs text-red-400 hover:text-red-300">{{ __('Delete') }}</button>
                            </form>
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
    {{ $plans->links() }}
@endsection
