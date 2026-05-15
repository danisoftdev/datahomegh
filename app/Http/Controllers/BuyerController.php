<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\OrderService;
use App\Support\BundleCatalog;
use Illuminate\View\View;

class BuyerController extends Controller
{
    public function __construct(
        private readonly OrderService $orderService,
    ) {}

    public function dashboard(): View
    {
        /** @var User $user */
        $user = auth()->user();
        $user->loadMissing('wallet');

        $walletBalance = $user->wallet !== null
            ? (string) $user->wallet->balance
            : '0.00';

        $recentOrders = $user->orders()
            ->with('bundlePackage')
            ->latest()
            ->limit(5)
            ->get();

        $bundles = BundleCatalog::forBuyer($user);

        $bundlesJson = $bundles->map(function ($b) use ($user): array {
            return [
                'id' => $b->id,
                'network' => $b->network,
                'package_kind' => $b->package_kind ?? 'data',
                'order_network' => $b->isMtnAfaRegistration() ? 'MTN_AFA' : $b->network,
                'name' => $b->name,
                'size_label' => $b->size_label,
                'price' => $this->orderService->priceForBuyer($user, $b),
            ];
        })->values()->all();

        $support = User::dashboardSupportForBuyer($user);

        return view('buyer.dashboard', [
            'walletBalance' => $walletBalance,
            'recentOrders' => $recentOrders,
            'bundlesJson' => $bundlesJson,
            'dashboardSupportContact' => $support['user'],
            'dashboardSupportHeading' => $support['heading'],
        ]);
    }
}
