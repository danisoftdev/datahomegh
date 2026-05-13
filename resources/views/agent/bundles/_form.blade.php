@php($editing = isset($bundle))
<div class="space-y-4">
    <div>
        <label class="mb-1 block text-sm text-slate-400">{{ __('Network') }}</label>
        <select name="network" required class="w-full rounded-lg border border-white/10 bg-[#1A1A2E] px-3 py-2 text-white">
            @foreach ($networks as $n)
                <option value="{{ $n }}" @selected(old('network', $editing ? $bundle->network : '') === $n)>{{ $n }}</option>
            @endforeach
        </select>
    </div>
    <div>
        <label class="mb-1 block text-sm text-slate-400">{{ __('Name') }}</label>
        <input type="text" name="name" required value="{{ old('name', $editing ? $bundle->name : '') }}" class="w-full rounded-lg border border-white/10 bg-[#1A1A2E] px-3 py-2 text-white" />
    </div>
    <div>
        <label class="mb-1 block text-sm text-slate-400">{{ __('Size label') }}</label>
        <input type="text" name="size_label" required value="{{ old('size_label', $editing ? $bundle->size_label : '') }}" class="w-full rounded-lg border border-white/10 bg-[#1A1A2E] px-3 py-2 text-white" />
    </div>
    <div>
        <label class="mb-1 block text-sm text-slate-400">{{ __('Internal cost (GHS)') }}</label>
        <input type="number" step="0.01" name="internal_cost" required value="{{ old('internal_cost', $editing ? $bundle->internal_cost : '') }}" class="w-full rounded-lg border border-white/10 bg-[#1A1A2E] px-3 py-2 text-white" />
    </div>
    <div>
        <label class="mb-1 block text-sm text-slate-400">{{ __('Stock count') }}</label>
        <input type="number" name="stock_count" required min="0" value="{{ old('stock_count', $editing ? $bundle->stock_count : 0) }}" class="w-full rounded-lg border border-white/10 bg-[#1A1A2E] px-3 py-2 text-white" />
    </div>
    <div class="flex items-center gap-2">
        <input type="hidden" name="is_available" value="0" />
        <input id="is_available" name="is_available" type="checkbox" value="1" class="size-4 rounded border-white/20 bg-[#1A1A2E] text-emerald-500" @checked(old('is_available', $editing ? $bundle->is_available : true)) />
        <label for="is_available" class="text-sm text-slate-300">{{ __('Available for sale') }}</label>
    </div>
</div>
