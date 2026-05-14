@if (! empty($order->afa_registration) && is_array($order->afa_registration))
    @php($a = $order->afa_registration)
    <div class="mt-6 rounded-xl border border-amber-500/25 bg-amber-500/5 p-4">
        <h3 class="mb-3 text-sm font-semibold text-amber-200">{{ __('MTN AFA registration') }}</h3>
        <dl class="grid gap-2 text-sm sm:grid-cols-2">
            <div><dt class="text-slate-500">{{ __('Name') }}</dt><dd class="text-white">{{ $a['name'] ?? '—' }}</dd></div>
            <div><dt class="text-slate-500">{{ __('Number') }}</dt><dd class="text-white">{{ $a['phone'] ?? '—' }}</dd></div>
            <div><dt class="text-slate-500">{{ __('Ghana Card number') }}</dt><dd class="text-white">{{ $a['ghana_card_number'] ?? '—' }}</dd></div>
            <div><dt class="text-slate-500">{{ __('Date of birth') }}</dt><dd class="text-white">{{ $a['date_of_birth'] ?? '—' }}</dd></div>
            <div><dt class="text-slate-500">{{ __('Occupation') }}</dt><dd class="text-white">{{ $a['occupation'] ?? '—' }}</dd></div>
            <div class="sm:col-span-2"><dt class="text-slate-500">{{ __('Location') }}</dt><dd class="text-white">{{ $a['location'] ?? '—' }}</dd></div>
        </dl>
    </div>
@endif
