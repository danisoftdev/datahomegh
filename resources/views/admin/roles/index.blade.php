@extends('layouts.admin')

@section('title', __('Roles') . ' — ' . config('app.name'))
@section('heading', __('Roles'))

@section('content')
    <div class="mb-6 flex flex-wrap items-center justify-between gap-4">
        <h1 class="text-2xl font-bold text-white">{{ __('Roles & permissions') }}</h1>
        <a href="{{ route('admin.roles.create') }}" class="rounded-lg bg-[#FFD700] px-4 py-2 text-sm font-semibold text-[#1A1A2E] hover:brightness-95">{{ __('Create New Role') }}</a>
    </div>

    <div class="overflow-x-auto rounded-xl border border-white/10 bg-[#16213E]/80">
        <table class="w-full min-w-xl text-left text-sm text-slate-300">
            <thead class="border-b border-white/10 text-xs uppercase text-slate-500">
                <tr>
                    <th class="px-4 py-3">{{ __('Name') }}</th>
                    <th class="px-4 py-3">{{ __('Slug') }}</th>
                    <th class="px-4 py-3">{{ __('Status') }}</th>
                    <th class="px-4 py-3 text-right">{{ __('Permissions') }}</th>
                    <th class="px-4 py-3 text-right">{{ __('Users') }}</th>
                    <th class="px-4 py-3 text-right">{{ __('Actions') }}</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($roles as $role)
                    <tr class="border-b border-white/5 last:border-0">
                        <td class="px-4 py-3 font-medium text-white">{{ $role->name }}</td>
                        <td class="px-4 py-3 font-mono text-xs text-slate-400">{{ $role->slug }}</td>
                        <td class="px-4 py-3">
                            @if ($role->is_enabled)
                                <span class="inline-flex rounded-md bg-emerald-500/20 px-2 py-0.5 text-xs font-medium text-emerald-300">{{ __('Active') }}</span>
                            @else
                                <span class="inline-flex rounded-md bg-slate-500/30 px-2 py-0.5 text-xs font-medium text-slate-400">{{ __('Disabled') }}</span>
                            @endif
                        </td>
                        <td class="px-4 py-3 text-right tabular-nums">{{ $role->permissions_count }}</td>
                        <td class="px-4 py-3 text-right tabular-nums">{{ $role->users_count }}</td>
                        <td class="px-4 py-3 text-right">
                            <div class="flex flex-wrap items-center justify-end gap-2">
                                <a href="{{ route('admin.roles.edit', $role) }}" class="rounded-lg border border-white/15 px-3 py-1.5 text-xs font-medium text-[#FFD700] hover:bg-white/5">{{ __('Edit') }}</a>
                                @if ($role->slug === \App\Models\Role::SLUG_SUPPLIER)
                                    <span class="text-xs text-slate-500" title="{{ __('Supplier role cannot be toggled off') }}">{{ __('Toggle') }}</span>
                                @else
                                    <form method="post" action="{{ route('admin.roles.toggle', $role) }}" class="inline">
                                        @csrf
                                        <button type="submit" class="rounded-lg border border-white/15 px-3 py-1.5 text-xs font-medium text-slate-200 hover:bg-white/5">
                                            {{ __('Toggle') }}
                                        </button>
                                    </form>
                                @endif
                            </div>
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
@endsection
