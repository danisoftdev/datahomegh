<?php

namespace App\Support;

use App\Models\BundlePackage;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;

final class BundleCatalog
{
    /**
     * Active bundles a buyer may order: their agent’s bundles plus supplier (null agent) bundles when linked to an agent;
     * supplier-only when the buyer has no agent.
     *
     * @return Collection<int, BundlePackage>
     */
    public static function forBuyer(User $buyer): Collection
    {
        $query = BundlePackage::query()->available();

        if ($buyer->agent_id !== null) {
            $query->where(function ($q) use ($buyer): void {
                $q->where('agent_id', $buyer->agent_id)
                    ->orWhereNull('agent_id');
            });
        } else {
            $query->whereNull('agent_id');
        }

        return $query->orderBy('network')->orderBy('name')->get();
    }

    /**
     * Bundles an agent may purchase for resale / fulfilment: their own catalogue plus supplier (platform) bundles.
     *
     * @return Collection<int, BundlePackage>
     */
    public static function forAgent(User $agent): Collection
    {
        return BundlePackage::query()
            ->available()
            ->where(function ($q) use ($agent): void {
                $q->where('agent_id', $agent->id)
                    ->orWhereNull('agent_id');
            })
            ->orderBy('network')
            ->orderBy('name')
            ->get();
    }
}
