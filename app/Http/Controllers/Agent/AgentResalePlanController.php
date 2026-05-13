<?php

namespace App\Http\Controllers\Agent;

use App\Http\Controllers\Controller;
use App\Models\BundlePackage;
use App\Models\ResalePlan;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AgentResalePlanController extends Controller
{
    public function index(Request $request, BundlePackage $bundle): View
    {
        $this->assertOwnedBundle($request, $bundle);

        $plans = $bundle->resalePlans()
            ->orderByDesc('id')
            ->paginate(20);

        return view('agent.resale-plans.index', compact('bundle', 'plans'));
    }

    public function create(Request $request, BundlePackage $bundle): View
    {
        $this->assertOwnedBundle($request, $bundle);

        return view('agent.resale-plans.create', compact('bundle'));
    }

    public function store(Request $request, BundlePackage $bundle): RedirectResponse
    {
        $this->assertOwnedBundle($request, $bundle);

        $data = $this->validatedPlan($request);
        $data['bundle_package_id'] = $bundle->id;
        $data['agent_id'] = $request->user()->id;
        $data['is_active'] = $request->boolean('is_active', true);

        ResalePlan::query()->create($data);

        return redirect()->route('agent.bundles.resale-plans.index', $bundle)->with('status', __('Resale plan created.'));
    }

    public function edit(Request $request, BundlePackage $bundle, ResalePlan $resalePlan): View
    {
        $this->assertOwnedPlan($request, $bundle, $resalePlan);

        return view('agent.resale-plans.edit', compact('bundle', 'resalePlan'));
    }

    public function update(Request $request, BundlePackage $bundle, ResalePlan $resalePlan): RedirectResponse
    {
        $this->assertOwnedPlan($request, $bundle, $resalePlan);

        $data = $this->validatedPlan($request);
        $data['is_active'] = $request->boolean('is_active', true);

        $resalePlan->update($data);

        return redirect()->route('agent.bundles.resale-plans.index', $bundle)->with('status', __('Resale plan updated.'));
    }

    public function destroy(Request $request, BundlePackage $bundle, ResalePlan $resalePlan): RedirectResponse
    {
        $this->assertOwnedPlan($request, $bundle, $resalePlan);

        $resalePlan->delete();

        return redirect()->route('agent.bundles.resale-plans.index', $bundle)->with('status', __('Resale plan deleted.'));
    }

    private function assertOwnedBundle(Request $request, BundlePackage $bundle): void
    {
        abort_unless((int) $bundle->agent_id === (int) $request->user()->id, 403);
    }

    private function assertOwnedPlan(Request $request, BundlePackage $bundle, ResalePlan $resalePlan): void
    {
        $this->assertOwnedBundle($request, $bundle);
        abort_unless((int) $resalePlan->bundle_package_id === (int) $bundle->id, 404);
        abort_unless((int) $resalePlan->agent_id === (int) $request->user()->id, 403);
    }

    /**
     * @return array<string, mixed>
     */
    private function validatedPlan(Request $request): array
    {
        return $request->validate([
            'price' => ['required', 'numeric', 'min:0'],
            'label' => ['required', 'string', 'max:255'],
        ]);
    }
}
