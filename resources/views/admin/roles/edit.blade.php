@extends('layouts.admin')

@section('title', __('Edit role') . ': ' . $role->name . ' — ' . config('app.name'))
@section('heading', __('Edit role'))

@section('content')
    <div class="mb-6">
        <a href="{{ route('admin.roles.index') }}" class="text-sm text-[#FFD700] hover:underline">← {{ __('Back to roles') }}</a>
    </div>

    <h1 class="mb-2 text-2xl font-bold text-white">{{ $role->name }}</h1>
    <p class="mb-6 font-mono text-sm text-slate-500">{{ $role->slug }}</p>

    <form method="post" action="{{ route('admin.roles.update', $role) }}"
        class="space-y-8 rounded-xl border border-white/10 bg-[#16213E]/80 p-6"
        x-data="{
            selectAll(group) {
                document.querySelectorAll(`[data-perm-group='${group}']`).forEach((el) => { el.checked = true; });
            },
            clearGroup(group) {
                document.querySelectorAll(`[data-perm-group='${group}']`).forEach((el) => { el.checked = false; });
            }
        }">
        @csrf
        @method('PUT')

        <div>
            <label for="name" class="mb-1.5 block text-sm font-medium text-slate-300">{{ __('Role name') }}</label>
            <input id="name" name="name" type="text" value="{{ old('name', $role->name) }}" required maxlength="100"
                class="max-w-lg w-full rounded-lg border border-white/10 bg-[#1A1A2E] px-3 py-2 text-white focus:border-[#FFD700]/50 focus:outline-none focus:ring-2 focus:ring-[#FFD700]/20" />
            @error('name')
                <p class="mt-1 text-sm text-red-400">{{ $message }}</p>
            @enderror
        </div>

        @if ($role->isCustomRole())
            <div class="grid gap-4 sm:grid-cols-2">
                <div>
                    <label for="pricing_persona" class="mb-1.5 block text-sm font-medium text-slate-300">{{ __('Checkout experience') }}</label>
                    <select id="pricing_persona" name="pricing_persona" class="w-full rounded-lg border border-white/10 bg-[#1A1A2E] px-3 py-2 text-white">
                        <option value="buyer" @selected(old('pricing_persona', $role->pricing_persona) === 'buyer')>{{ __('Buyer-style platform catalogue') }}</option>
                        <option value="agent" @selected(old('pricing_persona', $role->pricing_persona) === 'agent')>{{ __('Agent-style platform wholesale catalogue') }}</option>
                    </select>
                </div>
                <div class="rounded-lg border border-white/10 bg-[#1A1A2E]/50 p-4 space-y-3">
                    <label class="flex items-center gap-2 text-sm text-slate-300">
                        <input type="hidden" name="requires_promotion_fee" value="0" />
                        <input type="checkbox" name="requires_promotion_fee" value="1" class="size-4 rounded border-white/20" @checked(old('requires_promotion_fee', $role->requires_promotion_fee)) />
                        {{ __('Charge promotion fee from wallet') }}
                    </label>
                    <input id="promotion_fee" name="promotion_fee" type="number" step="0.01" min="0" value="{{ old('promotion_fee', $role->promotion_fee) }}" class="w-full rounded-lg border border-white/10 bg-[#1A1A2E] px-3 py-2 text-white" placeholder="{{ __('GHS') }}" />
                </div>
            </div>
            <p class="text-xs text-slate-500">{{ __('This role never appears on public registration. Set data prices per bundle under Admin → Bundles.') }}</p>
        @else
            <p class="text-sm text-slate-400">{{ __('System role — registration and promotion rules are fixed.') }}</p>
        @endif

        <div>
            <h2 class="mb-4 text-lg font-semibold text-white">{{ __('Permissions') }}</h2>
            <div class="space-y-8">
                @foreach ($permissionsByCategory as $category => $perms)
                    @if ($perms->isEmpty())
                        @continue
                    @endif
                    @php $groupId = Str::slug($category); @endphp
                    <section class="rounded-lg border border-white/10 bg-[#1A1A2E]/50 p-4" aria-labelledby="cat-{{ $groupId }}">
                        <div class="mb-3 flex flex-wrap items-center justify-between gap-2">
                            <h3 id="cat-{{ $groupId }}" class="text-sm font-semibold text-[#FFD700]">{{ $category }}</h3>
                            <div class="flex gap-2">
                                <button type="button" @click="selectAll('{{ $groupId }}')" class="rounded border border-white/15 px-2 py-1 text-xs text-slate-300 hover:bg-white/10">{{ __('Select All') }}</button>
                                <button type="button" @click="clearGroup('{{ $groupId }}')" class="rounded border border-white/15 px-2 py-1 text-xs text-slate-300 hover:bg-white/10">{{ __('Clear') }}</button>
                            </div>
                        </div>
                        <ul class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                            @foreach ($perms as $perm)
                                <li class="flex items-start gap-2 rounded-lg border border-white/5 bg-[#0f0f1a]/60 px-3 py-2">
                                    <input type="checkbox" name="permission_ids[]" value="{{ $perm->id }}" data-perm-group="{{ $groupId }}"
                                        class="mt-0.5 size-4 shrink-0 rounded border-white/20 text-[#FFD700]"
                                        @checked(in_array($perm->id, old('permission_ids', $selectedIds), true)) />
                                    <span class="text-sm leading-snug">
                                        <span class="font-medium text-slate-200">{{ $perm->name }}</span>
                                        <span class="mt-0.5 block font-mono text-xs text-slate-500">{{ $perm->slug }}</span>
                                    </span>
                                </li>
                            @endforeach
                        </ul>
                    </section>
                @endforeach
            </div>
            @error('permission_ids')
                <p class="mt-2 text-sm text-red-400">{{ $message }}</p>
            @enderror
        </div>

        <div class="flex flex-wrap gap-3 border-t border-white/10 pt-6">
            <button type="submit" class="rounded-lg bg-[#FFD700] px-6 py-2.5 text-sm font-semibold text-[#1A1A2E] hover:brightness-95">{{ __('Save') }}</button>
            <a href="{{ route('admin.roles.index') }}" class="rounded-lg border border-white/15 px-6 py-2.5 text-sm text-slate-300 hover:bg-white/5">{{ __('Cancel') }}</a>
        </div>
    </form>
@endsection
