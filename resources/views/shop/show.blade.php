@extends('layouts.shop-public')

@php
    $shopTitle = $agent->shop_name ?: $agent->name;
    $logoUrl = null;
    if ($agent->logo) {
        $logoUrl = \Illuminate\Support\Str::startsWith($agent->logo, ['http://', 'https://'])
            ? $agent->logo
            : \Illuminate\Support\Facades\Storage::disk('public')->url($agent->logo);
    }
    $waDigits = $agent->whatsapp_number ? preg_replace('/\D+/', '', $agent->whatsapp_number) : '';
    if ($waDigits !== '' && str_starts_with($waDigits, '0') && strlen($waDigits) >= 10) {
        $waDigits = '233'.substr($waDigits, 1);
    }
    $waMessage = rawurlencode('Hi '.$shopTitle.", I'm interested in buying data bundles from your shop on DataHomeGH.");
    $waUrl = $waDigits !== '' ? 'https://wa.me/'.$waDigits.'?text='.$waMessage : null;
    $registerUrl = url('/register?agent='.rawurlencode((string) $agent->shop_slug));
@endphp

@section('title', $shopTitle.' — Buy Data on DataHomeGH')
@section('meta_description', $agent->business_description ? \Illuminate\Support\Str::limit(strip_tags($agent->business_description), 160) : __('Data bundles from :shop via DataHomeGH.', ['shop' => $shopTitle]))

@section('content')
    <div class="w-full">
        {{-- Header banner --}}
        <div class="relative w-full overflow-hidden bg-linear-to-r from-[#1A1A2E] via-[#16213E] to-[#0F3460]" style="max-height: 200px;">
            @if ($logoUrl)
                <img src="{{ $logoUrl }}" alt="{{ $shopTitle }}" class="h-[200px] w-full object-cover" />
            @else
                <div class="flex h-[200px] w-full items-center justify-center bg-linear-to-br from-[#B8860B] via-[#FFD700] to-[#DAA520]">
                    <div class="text-center px-4">
                        <p class="text-2xl font-bold tracking-tight text-[#1A1A2E] sm:text-3xl">DataHomeGH</p>
                        <p class="mt-1 text-sm font-medium text-[#1A1A2E]/80">{{ __('Trusted data bundles') }}</p>
                    </div>
                </div>
            @endif
        </div>

        <div class="mx-auto max-w-5xl px-4 py-8 sm:px-6">
            <header class="mb-10 text-center sm:text-left">
                <h1 class="text-3xl font-bold tracking-tight text-white sm:text-4xl">{{ $shopTitle }}</h1>
                @if ($agent->business_description)
                    <p class="mx-auto mt-3 max-w-2xl text-base leading-relaxed text-slate-400 sm:mx-0 whitespace-pre-line">{{ $agent->business_description }}</p>
                @endif
                @if ($waUrl)
                    <a href="{{ $waUrl }}" target="_blank" rel="noopener noreferrer"
                        class="mt-6 inline-flex items-center justify-center gap-2 rounded-lg bg-[#25D366] px-6 py-3 text-sm font-semibold text-white shadow-lg transition hover:bg-[#20BD5A]">
                        <svg class="h-5 w-5 shrink-0" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.435 9.884-9.881 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 00-3.48-8.413Z"/></svg>
                        {{ __('Chat on WhatsApp') }}
                    </a>
                @endif
            </header>

            <section aria-labelledby="bundles-heading">
                <h2 id="bundles-heading" class="sr-only">{{ __('Bundles') }}</h2>
                @if ($agent->resalePlans->isEmpty())
                    <p class="rounded-xl border border-white/10 bg-[#16213E]/60 px-4 py-8 text-center text-slate-400">{{ __('No bundles listed yet. Check back soon.') }}</p>
                @else
                    <ul class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3">
                        @foreach ($agent->resalePlans as $plan)
                            @php
                                $bp = $plan->bundlePackage;
                            @endphp
                            @continue(!$bp)
                            @php
                                $net = $bp->network;
                                $badgeClass = match ($net) {
                                    'MTN' => 'bg-yellow-500 text-[#1A1A2E]',
                                    'Telecel' => 'bg-red-600 text-white',
                                    'AirtelTigo' => 'bg-blue-600 text-white',
                                    default => 'bg-slate-600 text-white',
                                };
                            @endphp
                            <li class="flex flex-col rounded-xl border border-white/10 bg-[#16213E]/80 p-5 shadow-md backdrop-blur-sm">
                                <span class="inline-flex w-fit rounded-md px-2.5 py-1 text-xs font-bold {{ $badgeClass }}">{{ $net }}</span>
                                <p class="mt-3 text-lg font-semibold text-white">{{ $bp->size_label }}</p>
                                @if ($bp->name)
                                    <p class="mt-1 text-sm text-slate-400">{{ $bp->name }}</p>
                                @endif
                                <p class="mt-4 text-2xl font-bold text-[#FFD700]">GH₵ {{ number_format((float) $plan->price, 2) }}</p>
                                <a href="{{ $registerUrl }}" class="mt-auto pt-5">
                                    <span class="flex w-full items-center justify-center rounded-lg bg-[#FFD700] px-4 py-2.5 text-sm font-semibold text-[#1A1A2E] transition hover:bg-[#e6c200]">{{ __('Order Now') }}</span>
                                </a>
                            </li>
                        @endforeach
                    </ul>
                @endif
            </section>

            <div class="mt-12 flex flex-col items-center gap-4 rounded-xl border border-white/10 bg-[#16213E]/50 px-6 py-8 text-center sm:flex-row sm:justify-center sm:gap-6">
                <a href="{{ $registerUrl }}" class="inline-flex w-full max-w-xs items-center justify-center rounded-lg bg-[#FFD700] px-6 py-3 text-sm font-semibold text-[#1A1A2E] transition hover:bg-[#e6c200] sm:w-auto">{{ __('Register to Order') }}</a>
                <a href="{{ route('login') }}" class="text-sm font-medium text-[#FFD700] underline-offset-4 hover:underline">{{ __('Already registered? Login') }}</a>
            </div>
        </div>

        <footer class="mt-12 border-t border-white/10 py-6 text-center text-xs text-slate-500">
            Powered by DataHomeGH | datahomegh.shop
        </footer>
    </div>
@endsection
