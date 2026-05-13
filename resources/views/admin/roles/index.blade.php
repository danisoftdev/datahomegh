@extends('layouts.admin')

@section('title', __('Roles'))
@section('heading', __('Roles'))

@section('content')
    <h1 class="mb-6 text-2xl font-bold text-white">{{ __('Roles') }}</h1>

    <div class="space-y-4">
        @foreach ($roles as $role)
            <div class="rounded-xl border border-white/10 bg-[#16213E]/80 p-6">
                <div class="flex flex-wrap items-start justify-between gap-4">
                    <div>
                        <h2 class="text-lg font-semibold text-white">{{ $role->name }}</h2>
                        <p class="text-sm text-slate-500">{{ $role->slug }} — {{ $role->permissions_count }} {{ __('permissions') }}</p>
                        <p class="mt-2 text-xs text-slate-500">{{ $role->permissions->pluck('slug')->take(8)->join(', ') }}@if ($role->permissions->count() > 8)…@endif</p>
                    </div>
                    <form method="post" action="{{ route('admin.roles.update', $role) }}" class="flex items-center gap-2">
                        @csrf
                        @method('PATCH')
                        <input type="hidden" name="is_enabled" value="0" />
                        <label class="flex items-center gap-2 text-sm text-slate-300">
                            <input type="checkbox" name="is_enabled" value="1" class="size-4 rounded text-[#FFD700]" @checked($role->is_enabled) onchange="this.form.submit()" />
                            {{ __('Enabled') }}
                        </label>
                    </form>
                </div>
            </div>
        @endforeach
    </div>
@endsection
