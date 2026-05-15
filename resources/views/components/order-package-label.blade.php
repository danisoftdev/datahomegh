@props(['order'])

@php
    /** @var \App\Models\Order $order */
    $bundle = $order->bundlePackage;
@endphp

@if (! $bundle)
    <span class="text-slate-500">—</span>
@elseif ($bundle->isMtnAfaRegistration())
    <span class="whitespace-nowrap">{{ __('MTN AFA') }}</span>
@else
    @php
        $full = trim(($bundle->name ?? '').($bundle->size_label ? ' — '.$bundle->size_label : ''));
    @endphp
    <span class="block max-w-44 truncate sm:max-w-xs" title="{{ $full }}">
        <span class="text-inherit">{{ $bundle->name }}</span>
        @if ($bundle->size_label)
            <span class="text-slate-400"> · {{ $bundle->size_label }}</span>
        @endif
    </span>
@endif
