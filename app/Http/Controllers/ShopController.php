<?php

namespace App\Http\Controllers;

use App\Models\Role;
use App\Models\User;
use Illuminate\Http\Response;
use Illuminate\View\View;

class ShopController extends Controller
{
    public function show(string $agentSlug): View|Response
    {
        $agent = User::query()
            ->where('shop_slug', $agentSlug)
            ->where('status', 'active')
            ->whereNotNull('shop_slug')
            ->whereHas('role', fn ($q) => $q->where('slug', '!=', Role::SLUG_SUPPLIER))
            ->with([
                'resalePlans' => function ($q): void {
                    $q->where('resale_plans.is_active', true)
                        ->whereHas('bundlePackage', fn ($bq) => $bq->where('is_available', true)->where('stock_count', '>', 0))
                        ->join('bundle_packages as bp', 'bp.id', '=', 'resale_plans.bundle_package_id')
                        ->orderBy('bp.network')
                        ->orderBy('bp.name')
                        ->select('resale_plans.*');
                },
                'resalePlans.bundlePackage',
            ])
            ->first();

        if ($agent === null) {
            return response()->view('shop.not-found', [
                'slug' => $agentSlug,
            ], 404);
        }

        return view('shop.show', [
            'agent' => $agent,
        ]);
    }
}
