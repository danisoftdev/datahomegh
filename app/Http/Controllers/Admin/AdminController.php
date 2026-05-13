<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\Role;
use App\Models\User;
use Illuminate\View\View;

class AdminController extends Controller
{
    public function dashboard(): View
    {
        $todayStart = now()->startOfDay();
        $todayEnd = now()->endOfDay();
        $monthStart = now()->startOfMonth();
        $monthEnd = now()->endOfMonth();

        $todayOrdersCount = Order::query()
            ->whereBetween('created_at', [$todayStart, $todayEnd])
            ->count();

        $sentRevenueMonth = (string) Order::query()
            ->where('status', 'SENT')
            ->whereBetween('created_at', [$monthStart, $monthEnd])
            ->sum('amount');

        $pendingCount = Order::query()
            ->whereIn('status', ['PENDING', 'PROCESSING'])
            ->count();

        $agentsCount = User::query()
            ->whereHas('role', fn ($q) => $q->where('slug', Role::SLUG_AGENT))
            ->count();

        $buyersCount = User::query()
            ->whereHas('role', fn ($q) => $q->where('slug', Role::SLUG_BUYER))
            ->count();

        $recentOrders = Order::query()
            ->with(['user', 'agent', 'bundlePackage'])
            ->latest()
            ->limit(10)
            ->get();

        return view('admin.dashboard', [
            'todayOrdersCount' => $todayOrdersCount,
            'sentRevenueMonth' => $sentRevenueMonth,
            'pendingCount' => $pendingCount,
            'agentsCount' => $agentsCount,
            'buyersCount' => $buyersCount,
            'recentOrders' => $recentOrders,
        ]);
    }
}
