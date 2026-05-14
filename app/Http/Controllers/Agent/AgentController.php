<?php

namespace App\Http\Controllers\Agent;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\User;
use Illuminate\View\View;

class AgentController extends Controller
{
    public function dashboard(): View
    {
        $user = auth()->user();
        $user->loadMissing('wallet');

        $todayStart = now()->startOfDay();
        $todayEnd = now()->endOfDay();

        $todayOrdersCount = Order::query()
            ->where('agent_id', $user->id)
            ->whereBetween('created_at', [$todayStart, $todayEnd])
            ->count();

        $walletBalance = $user->wallet !== null
            ? (string) $user->wallet->balance
            : '0.00';

        $pendingCount = Order::query()
            ->where('agent_id', $user->id)
            ->whereIn('status', ['PENDING', 'PROCESSING'])
            ->count();

        $shopSlug = $user->shop_slug;
        $shopLink = $shopSlug !== null && $shopSlug !== ''
            ? rtrim(url('/'), '/').'/'.$shopSlug
            : null;

        $supplier = User::supplierUser();
        $platformSupportContact = ($supplier !== null && $supplier->hasSupportContactContent()) ? $supplier : null;

        return view('agent.dashboard', [
            'todayOrdersCount' => $todayOrdersCount,
            'walletBalance' => $walletBalance,
            'pendingCount' => $pendingCount,
            'shopLink' => $shopLink,
            'platformSupportContact' => $platformSupportContact,
        ]);
    }
}
