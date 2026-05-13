@extends('layouts.agent')

@section('title', __('Profile') . ' — ' . config('app.name'))
@section('heading', __('Profile'))

@section('content')
    <h1 class="mb-6 text-2xl font-bold text-white">{{ __('Shop profile') }}</h1>

    <form method="post" action="{{ route('agent.profile.update') }}" enctype="multipart/form-data" class="max-w-2xl space-y-6 rounded-xl border border-white/10 bg-[#16213E]/80 p-6">
        @csrf
        @method('PUT')

        <div>
            <label class="mb-1 block text-sm text-slate-400">{{ __('Shop name') }}</label>
            <input type="text" name="shop_name" value="{{ old('shop_name', $user->shop_name) }}" class="w-full rounded-lg border border-white/10 bg-[#1A1A2E] px-3 py-2 text-white" />
        </div>
        <div>
            <label class="mb-1 block text-sm text-slate-400">{{ __('Business description') }}</label>
            <textarea name="business_description" rows="4" class="w-full rounded-lg border border-white/10 bg-[#1A1A2E] px-3 py-2 text-white">{{ old('business_description', $user->business_description) }}</textarea>
        </div>
        <div>
            <label class="mb-1 block text-sm text-slate-400">{{ __('WhatsApp number') }}</label>
            <input type="text" name="whatsapp_number" value="{{ old('whatsapp_number', $user->whatsapp_number) }}" class="w-full rounded-lg border border-white/10 bg-[#1A1A2E] px-3 py-2 text-white" placeholder="+233…" />
        </div>
        <div>
            <label class="mb-1 block text-sm text-slate-400">{{ __('WhatsApp channel link') }}</label>
            <input type="text" name="whatsapp_channel" value="{{ old('whatsapp_channel', $user->whatsapp_channel) }}" class="w-full rounded-lg border border-white/10 bg-[#1A1A2E] px-3 py-2 text-white" />
        </div>
        <div>
            <label class="mb-1 block text-sm text-slate-400">{{ __('Profile picture') }} <span class="text-xs text-slate-500">(300×300)</span></label>
            @if ($user->profile_picture)
                <p class="mb-2 text-xs text-slate-500">{{ __('Current') }}: <img src="{{ asset('storage/'.$user->profile_picture) }}" alt="" class="mt-1 size-20 rounded-lg object-cover" /></p>
            @endif
            <input type="file" name="profile_picture" accept="image/*" class="text-sm text-slate-300" />
        </div>
        <div>
            <label class="mb-1 block text-sm text-slate-400">{{ __('Logo') }} <span class="text-xs text-slate-500">(600×200)</span></label>
            @if ($user->logo)
                <p class="mb-2 text-xs text-slate-500">{{ __('Current') }}: <img src="{{ asset('storage/'.$user->logo) }}" alt="" class="mt-1 max-h-16 rounded object-contain" /></p>
            @endif
            <input type="file" name="logo" accept="image/*" class="text-sm text-slate-300" />
        </div>

        <button type="submit" class="rounded-lg bg-emerald-500 px-6 py-2 font-semibold text-[#0f0f1a]">{{ __('Save profile') }}</button>
    </form>
@endsection
