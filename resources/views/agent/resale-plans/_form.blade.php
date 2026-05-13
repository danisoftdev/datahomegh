<div class="space-y-4">
    <div>
        <label class="mb-1 block text-sm text-slate-400">{{ __('Label') }}</label>
        <input type="text" name="label" required value="{{ old('label', $editing ? $resalePlan->label : '') }}" class="w-full rounded-lg border border-white/10 bg-[#1A1A2E] px-3 py-2 text-white" />
    </div>
    <div>
        <label class="mb-1 block text-sm text-slate-400">{{ __('Price (GHS)') }}</label>
        <input type="number" step="0.01" name="price" required min="0" value="{{ old('price', $editing ? $resalePlan->price : '') }}" class="w-full rounded-lg border border-white/10 bg-[#1A1A2E] px-3 py-2 text-white" />
    </div>
    <div class="flex items-center gap-2">
        <input type="hidden" name="is_active" value="0" />
        <input id="is_active" name="is_active" type="checkbox" value="1" class="size-4 rounded border-white/20 bg-[#1A1A2E] text-emerald-500" @checked(old('is_active', $editing ? $resalePlan->is_active : true)) />
        <label for="is_active" class="text-sm text-slate-300">{{ __('Active') }}</label>
    </div>
</div>
