<?php

namespace App\Support;

use App\Models\BundlePackage;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;

final class BundleCatalog
{
    /**
     * Active bundles a buyer may order: only their agent’s catalogue when linked to an agent;
     * only supplier (platform) bundles when the buyer has no agent.
     *
     * @return Collection<int, BundlePackage>
     */
    public static function forBuyer(User $buyer): Collection
    {
        $query = BundlePackage::query()->available();

        if ($buyer->agent_id !== null) {
            $query->where('agent_id', $buyer->agent_id);
        } else {
            $query->whereNull('agent_id');
        }

        return $query->orderBy('network')->orderBy('name')->get();
    }

    /**
     * Bundles an agent may purchase on agent checkout: supplier (platform) catalogue only.
     * Agents manage their own resale bundles separately under “My bundles”; those are not self-checkout options.
     *
     * @return Collection<int, BundlePackage>
     */
    public static function forAgent(User $agent): Collection
    {
        return BundlePackage::query()
            ->available()
            ->whereNull('agent_id')
            ->orderBy('network')
            ->orderBy('name')
            ->get();
    }
}
