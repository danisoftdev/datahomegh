<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\BundlePackage;
use App\Models\Role;
use App\Models\RolePrice;
use App\Support\BundlePackageKind;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class AdminBundleController extends Controller
{
    public function index(): View
    {
        $bundles = BundlePackage::query()
            ->whereNull('agent_id')
            ->orderByDesc('id')
            ->paginate(20);

        return view('admin.bundles.index', compact('bundles'));
    }

    public function create(): View
    {
        $networks = ['MTN', 'Telecel', 'AirtelTigo'];
        $pricingRoles = Role::platformPricingQuery()->get();
        $roleListPrices = [];

        return view('admin.bundles.create', compact('networks', 'pricingRoles', 'roleListPrices'));
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validatedBundle($request);
        /** @var BundlePackage $bundle */
        $bundle = BundlePackage::query()->create($data);
        $this->syncPlatformBundleRolePrices(
            $bundle,
            $this->validatedRoleListPrices($request, (string) ($bundle->package_kind ?? BundlePackageKind::DATA)),
        );

        return redirect()->route('admin.bundles.index')->with('status', __('Bundle created.'));
    }

    public function edit(BundlePackage $bundle): View
    {
        $this->assertSupplierBundle($bundle);

        $networks = ['MTN', 'Telecel', 'AirtelTigo'];
        $pricingRoles = Role::platformPricingQuery()->get();
        $roleListPrices = $this->roleListPricesForBundle($bundle, $pricingRoles);

        return view('admin.bundles.edit', compact('bundle', 'networks', 'pricingRoles', 'roleListPrices'));
    }

    public function update(Request $request, BundlePackage $bundle): RedirectResponse
    {
        $this->assertSupplierBundle($bundle);

        $bundle->update($this->validatedBundle($request));
        $bundle->refresh();
        $this->syncPlatformBundleRolePrices(
            $bundle,
            $this->validatedRoleListPrices($request, (string) ($bundle->package_kind ?? BundlePackageKind::DATA)),
        );

        return redirect()->route('admin.bundles.index')->with('status', __('Bundle updated.'));
    }

    public function destroy(BundlePackage $bundle): RedirectResponse
    {
        $this->assertSupplierBundle($bundle);

        $bundle->delete();

        return redirect()->route('admin.bundles.index')->with('status', __('Bundle deleted.'));
    }

    public function updateStock(Request $request, BundlePackage $bundle): RedirectResponse
    {
        $this->assertSupplierBundle($bundle);

        $data = $request->validate([
            'stock_count' => ['required', 'integer', 'min:0', 'max:99999999'],
        ]);

        $bundle->stock_count = $data['stock_count'];
        if ($bundle->stock_count > 0) {
            $bundle->is_available = true;
        }
        $bundle->save();

        return back()->with('status', __('Stock updated.'));
    }

    public function toggleAvailability(BundlePackage $bundle): RedirectResponse
    {
        $this->assertSupplierBundle($bundle);

        $bundle->is_available = ! $bundle->is_available;
        $bundle->save();

        return back()->with('status', __('Availability toggled.'));
    }

    /**
     * @return array<string, mixed>
     */
    private function validatedBundle(Request $request): array
    {
        $data = $request->validate([
            'network' => ['required', Rule::in(['MTN', 'Telecel', 'AirtelTigo'])],
            'package_kind' => ['nullable', Rule::in(BundlePackageKind::all())],
            'name' => ['required', 'string', 'max:255'],
            'size_label' => ['required', 'string', 'max:100'],
            'provider_bundle_type' => ['nullable', 'string', 'max:120'],
            'internal_cost' => ['required', 'numeric', 'min:0'],
            'stock_count' => ['required', 'integer', 'min:0', 'max:99999999'],
        ]);

        $data['is_available'] = $request->boolean('is_available', true);
        $data['package_kind'] = $data['package_kind'] ?? BundlePackageKind::DATA;
        if ($data['package_kind'] === BundlePackageKind::MTN_AFA && $data['network'] !== 'MTN') {
            throw ValidationException::withMessages([
                'network' => [__('MTN AFA bundles must use the MTN network.')],
            ]);
        }

        $data['agent_id'] = null;

        $data['provider_bundle_type'] = filled($data['provider_bundle_type'] ?? null)
            ? trim((string) $data['provider_bundle_type'])
            : null;

        if (($data['package_kind'] ?? '') === BundlePackageKind::MTN_AFA
            || in_array($data['network'], ['MTN', 'Telecel'], true)) {
            $data['provider_bundle_type'] = null;
        }

        return $data;
    }

    private function assertSupplierBundle(BundlePackage $bundle): void
    {
        abort_unless($bundle->agent_id === null, 404);
    }

    /**
     * @param  Collection<int, Role>  $pricingRoles
     * @return array<int, string|null>
     */
    private function roleListPricesForBundle(BundlePackage $bundle, Collection $pricingRoles): array
    {
        $rows = RolePrice::query()
            ->where('bundle_package_id', $bundle->id)
            ->whereIn('role_id', $pricingRoles->pluck('id'))
            ->get()
            ->keyBy('role_id');

        $prices = [];
        foreach ($pricingRoles as $role) {
            $prices[$role->id] = isset($rows[$role->id]) ? (string) $rows[$role->id]->price : null;
        }

        return $prices;
    }

    /**
     * @return array<int, string|null>
     */
    private function validatedRoleListPrices(Request $request, string $packageKind): array
    {
        if (BundlePackageKind::isMtnAfa($packageKind)) {
            return [];
        }

        $pricingRoleIds = Role::platformPricingQuery()->pluck('id')->all();

        $data = $request->validate([
            'role_list_prices' => ['nullable', 'array'],
            'role_list_prices.*' => ['nullable', 'numeric', 'min:0.01', 'max:999999'],
        ]);

        $submitted = $data['role_list_prices'] ?? [];
        $normalized = [];

        foreach ($pricingRoleIds as $roleId) {
            $raw = $submitted[(string) $roleId] ?? $submitted[$roleId] ?? null;
            if ($raw === null || $raw === '') {
                $normalized[(int) $roleId] = null;

                continue;
            }

            $normalized[(int) $roleId] = number_format((float) $raw, 2, '.', '');
        }

        return $normalized;
    }

    /**
     * @param  array<int, string|null>  $prices
     */
    private function syncPlatformBundleRolePrices(BundlePackage $bundle, array $prices): void
    {
        if ($bundle->isMtnAfaRegistration()) {
            RolePrice::query()->where('bundle_package_id', $bundle->id)->delete();

            return;
        }

        foreach ($prices as $roleId => $price) {
            if ($price === null) {
                RolePrice::query()
                    ->where('bundle_package_id', $bundle->id)
                    ->where('role_id', $roleId)
                    ->delete();

                continue;
            }

            RolePrice::query()->updateOrCreate(
                [
                    'role_id' => $roleId,
                    'bundle_package_id' => $bundle->id,
                ],
                ['price' => $price],
            );
        }
    }
}
