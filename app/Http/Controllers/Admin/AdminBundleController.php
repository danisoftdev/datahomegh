<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\BundlePackage;
use App\Models\Role;
use App\Models\User;
use App\Support\BundlePackageKind;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class AdminBundleController extends Controller
{
    public function index(): View
    {
        $bundles = BundlePackage::query()
            ->with('agent')
            ->orderByDesc('id')
            ->paginate(20);

        return view('admin.bundles.index', compact('bundles'));
    }

    public function create(): View
    {
        $networks = ['MTN', 'Telecel', 'AirtelTigo'];
        $agents = User::query()
            ->whereHas('role', fn ($q) => $q->where('slug', Role::SLUG_AGENT))
            ->where('status', 'active')
            ->orderBy('username')
            ->get(['id', 'username', 'shop_name']);

        return view('admin.bundles.create', compact('networks', 'agents'));
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validatedBundle($request);
        BundlePackage::query()->create($data);

        return redirect()->route('admin.bundles.index')->with('status', __('Bundle created.'));
    }

    public function edit(BundlePackage $bundle): View
    {
        $networks = ['MTN', 'Telecel', 'AirtelTigo'];
        $agents = User::query()
            ->whereHas('role', fn ($q) => $q->where('slug', Role::SLUG_AGENT))
            ->where('status', 'active')
            ->orderBy('username')
            ->get(['id', 'username', 'shop_name']);

        return view('admin.bundles.edit', compact('bundle', 'networks', 'agents'));
    }

    public function update(Request $request, BundlePackage $bundle): RedirectResponse
    {
        $bundle->update($this->validatedBundle($request));

        return redirect()->route('admin.bundles.index')->with('status', __('Bundle updated.'));
    }

    public function destroy(BundlePackage $bundle): RedirectResponse
    {
        $bundle->delete();

        return redirect()->route('admin.bundles.index')->with('status', __('Bundle deleted.'));
    }

    public function updateStock(Request $request, BundlePackage $bundle): RedirectResponse
    {
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
            'agent_id' => ['nullable', 'integer', 'exists:users,id'],
            'network' => ['required', Rule::in(['MTN', 'Telecel', 'AirtelTigo'])],
            'package_kind' => ['nullable', Rule::in(BundlePackageKind::all())],
            'name' => ['required', 'string', 'max:255'],
            'size_label' => ['required', 'string', 'max:100'],
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

        return $data;
    }
}
