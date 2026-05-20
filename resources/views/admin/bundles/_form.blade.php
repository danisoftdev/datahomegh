@php($editing = isset($bundle))
@php($showRoleListPrices = ! $editing || ! $bundle->isMtnAfaRegistration())
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
        <label class="mb-1 block text-sm text-slate-400">{{ __('Bundle type') }}</label>
        <select name="package_kind" class="w-full rounded-lg border border-white/10 bg-[#1A1A2E] px-3 py-2 text-white">
            <option value="data" @selected(old('package_kind', $editing ? ($bundle->package_kind ?? 'data') : 'data') === 'data')>{{ __('Data bundle') }}</option>
            <option value="mtn_afa" @selected(old('package_kind', $editing ? ($bundle->package_kind ?? 'data') : '') === 'mtn_afa')>{{ __('MTN AFA registration') }}</option>
        </select>
        <p class="mt-1 text-xs text-slate-500">{{ __('MTN AFA must use network MTN. Buyers fill the registration form and pay the price you set below—AFA uses that amount only (not role or resale pricing like data bundles).') }}</p>
    </div>
    <div>
        <label class="mb-1 block text-sm text-slate-400">{{ __('Name') }}</label>
        <input type="text" name="name" required value="{{ old('name', $editing ? $bundle->name : '') }}" class="w-full rounded-lg border border-white/10 bg-[#1A1A2E] px-3 py-2 text-white" />
    </div>
    <div>
        <label class="mb-1 block text-sm text-slate-400">{{ __('Size label') }}</label>
        <input type="text" name="size_label" required value="{{ old('size_label', $editing ? $bundle->size_label : '') }}" class="w-full rounded-lg border border-white/10 bg-[#1A1A2E] px-3 py-2 text-white" />
    </div>
    @php($pkgKind = old('package_kind', $editing ? ($bundle->package_kind ?? 'data') : 'data'))
    @php($bundleNetwork = old('network', $editing ? ($bundle->network ?? '') : ''))
    @if ($pkgKind !== 'mtn_afa' && $bundleNetwork === 'MTN')
        <p class="rounded-lg border border-white/10 bg-white/5 px-3 py-2 text-xs text-slate-400">
            {{ __('MTN data orders use Geonettech automatically (network_key :key). You only manage the network name customers see — no provider code on this bundle.', ['key' => \App\Support\GeonetMtnNetworkKey::resolve()]) }}
        </p>
    @elseif ($pkgKind !== 'mtn_afa')
        <div id="provider-code-field">
            <label class="mb-1 block text-sm text-slate-400">{{ __('Provider bundle code (Telecel / iGet)') }}</label>
            <input type="text" name="provider_bundle_type" value="{{ old('provider_bundle_type', $editing ? ($bundle->provider_bundle_type ?? '') : '') }}" maxlength="120"
                placeholder="telecelup2u"
                class="w-full rounded-lg border border-white/10 bg-[#1A1A2E] px-3 py-2 text-white" />
            <p class="mt-1 text-xs text-slate-500">{{ __('iGet bundleType for Telecel. Optional if set on the Telecel API profile or .env (FULFILLMENT_IGET_TELECEL_BUNDLE_TYPE).') }}</p>
            @error('provider_bundle_type')
                <p class="mt-1 text-sm text-red-400">{{ $message }}</p>
            @enderror
        </div>
    @endif
    <div>
        <label class="mb-1 block text-sm text-slate-400">{{ __('Base price (GHS)') }}</label>
        <input type="number" step="0.01" name="internal_cost" required value="{{ old('internal_cost', $editing ? $bundle->internal_cost : '') }}" class="w-full rounded-lg border border-white/10 bg-[#1A1A2E] px-3 py-2 text-white" />
        <p class="mt-1 text-xs text-slate-500">{{ __('Data: default list price when no role-specific price is set below. MTN AFA: this is the exact fee charged at checkout.') }}</p>
    </div>
    @if ($showRoleListPrices)
        <div class="rounded-lg border border-white/10 bg-[#1A1A2E]/50 p-4 space-y-3">
            <p class="text-sm font-medium text-slate-200">{{ __('Role list prices (data bundles only)') }}</p>
            <p class="text-xs text-slate-500">{{ __('Optional. When set, buyers see the buyer price and agents see the agent price for this platform bundle. Leave blank to use the base price for that role. Agent shop resale bundles are still priced by each agent; this does not change that.') }}</p>
            <div>
                <label class="mb-1 block text-sm text-slate-400">{{ __('Buyer list price (GHS)') }}</label>
                <input type="number" step="0.01" min="0.01" name="buyer_list_price" value="{{ old('buyer_list_price', $buyerListPrice ?? '') }}" class="w-full rounded-lg border border-white/10 bg-[#1A1A2E] px-3 py-2 text-white" placeholder="{{ __('Same as base if empty') }}" />
                @error('buyer_list_price')
                    <p class="mt-1 text-sm text-red-400">{{ $message }}</p>
                @enderror
            </div>
            <div>
                <label class="mb-1 block text-sm text-slate-400">{{ __('Agent list price (GHS)') }}</label>
                <input type="number" step="0.01" min="0.01" name="agent_list_price" value="{{ old('agent_list_price', $agentListPrice ?? '') }}" class="w-full rounded-lg border border-white/10 bg-[#1A1A2E] px-3 py-2 text-white" placeholder="{{ __('Same as base if empty') }}" />
                @error('agent_list_price')
                    <p class="mt-1 text-sm text-red-400">{{ $message }}</p>
                @enderror
            </div>
        </div>
    @endif
    <div>
        <label class="mb-1 block text-sm text-slate-400">{{ __('Stock count') }}</label>
        <input type="number" name="stock_count" required min="0" value="{{ old('stock_count', $editing ? $bundle->stock_count : 0) }}" class="w-full rounded-lg border border-white/10 bg-[#1A1A2E] px-3 py-2 text-white" />
    </div>
    <div class="flex items-center gap-2">
        <input type="hidden" name="is_available" value="0" />
        <input id="is_available" name="is_available" type="checkbox" value="1" class="size-4 rounded border-white/20 bg-[#1A1A2E] text-[#FFD700]" @checked(old('is_available', $editing ? $bundle->is_available : true)) />
        <label for="is_available" class="text-sm text-slate-300">{{ __('Available for sale') }}</label>
    </div>
</div>
