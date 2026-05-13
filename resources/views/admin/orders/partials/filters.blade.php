<form method="get" action="{{ route('admin.orders.index') }}" class="grid gap-3 rounded-xl border border-white/10 bg-navy/60 p-4 sm:grid-cols-2 lg:grid-cols-4 xl:grid-cols-7">
    <div>
        <label class="mb-1 block text-xs text-slate-500">{{ __('Status') }}</label>
        <select name="status" class="w-full rounded-lg border border-white/10 bg-dark px-2 py-1.5 text-sm text-white">
            <option value="">{{ __('Any') }}</option>
            @foreach (['PENDING', 'PROCESSING', 'SENT', 'FAILED', 'REFUNDED'] as $s)
                <option value="{{ $s }}" @selected(request('status') === $s)>{{ $s }}</option>
            @endforeach
        </select>
    </div>
    <div>
        <label class="mb-1 block text-xs text-slate-500">{{ __('Date from') }}</label>
        <input type="date" name="date_from" value="{{ request('date_from') }}" class="w-full rounded-lg border border-white/10 bg-dark px-2 py-1.5 text-sm text-white" />
    </div>
    <div>
        <label class="mb-1 block text-xs text-slate-500">{{ __('Date to') }}</label>
        <input type="date" name="date_to" value="{{ request('date_to') }}" class="w-full rounded-lg border border-white/10 bg-dark px-2 py-1.5 text-sm text-white" />
    </div>
    <div>
        <label class="mb-1 block text-xs text-slate-500">{{ __('Network') }}</label>
        <select name="network" class="w-full rounded-lg border border-white/10 bg-dark px-2 py-1.5 text-sm text-white">
            <option value="">{{ __('Any') }}</option>
            @foreach (['MTN', 'Telecel', 'AirtelTigo'] as $n)
                <option value="{{ $n }}" @selected(request('network') === $n)>{{ $n }}</option>
            @endforeach
        </select>
    </div>
    <div>
        <label class="mb-1 block text-xs text-slate-500">{{ __('Agent') }}</label>
        <select name="agent_id" class="w-full rounded-lg border border-white/10 bg-dark px-2 py-1.5 text-sm text-white">
            <option value="">{{ __('Any') }}</option>
            @foreach ($agents as $a)
                <option value="{{ $a->id }}" @selected((string) request('agent_id') === (string) $a->id)>{{ $a->username }}</option>
            @endforeach
        </select>
    </div>
    <div>
        <label class="mb-1 block text-xs text-slate-500">{{ __('User ID') }}</label>
        <input type="number" name="user_id" value="{{ request('user_id') }}" class="w-full rounded-lg border border-white/10 bg-dark px-2 py-1.5 text-sm text-white" />
    </div>
    <div>
        <label class="mb-1 block text-xs text-slate-500">{{ __('Phone contains') }}</label>
        <input type="text" name="phone" value="{{ request('phone') }}" class="w-full rounded-lg border border-white/10 bg-dark px-2 py-1.5 text-sm text-white" />
    </div>
    <div class="flex items-end gap-2">
        <button type="submit" class="rounded-lg bg-primary px-4 py-2 text-sm font-semibold text-dark">{{ __('Filter') }}</button>
        <a href="{{ route('admin.orders.index') }}" class="rounded-lg border border-white/20 px-4 py-2 text-sm text-slate-300">{{ __('Reset') }}</a>
    </div>
</form>
