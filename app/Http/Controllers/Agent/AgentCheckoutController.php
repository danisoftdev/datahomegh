<?php

namespace App\Http\Controllers\Agent;

use App\Exceptions\InsufficientBalanceException;
use App\Http\Controllers\Controller;
use App\Models\Role;
use App\Models\User;
use App\Services\OrderService;
use App\Support\BundleCatalog;
use App\Support\OrderCartItemsValidator;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use InvalidArgumentException;

class AgentCheckoutController extends Controller
{
    public function __construct(
        private readonly OrderService $orderService,
    ) {}

    public function create(Request $request): View
    {
        /** @var User $user */
        $user = $request->user();

        abort_unless($user->role?->slug === Role::SLUG_AGENT, 403);

        $networks = ['MTN', 'Telecel', 'AirtelTigo', 'MTN_AFA'];
        $bundles = BundleCatalog::forAgent($user);

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

        $user->loadMissing('wallet');
        $walletBalance = $user->wallet !== null ? (string) $user->wallet->balance : '0.00';

        return view('agent.orders.create', [
            'networks' => $networks,
            'networkLabels' => [
                'MTN' => 'MTN',
                'Telecel' => 'Telecel',
                'AirtelTigo' => 'AirtelTigo',
                'MTN_AFA' => __('MTN AFA'),
            ],
            'bundlesJson' => $bundlesJson,
            'walletBalance' => $walletBalance,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();

        abort_unless($user->role?->slug === Role::SLUG_AGENT, 403);

        $catalog = BundleCatalog::forAgent($user);
        $result = OrderCartItemsValidator::validate($request, $user, $catalog);

        if (! $result['ok']) {
            return back()->withInput()->withErrors($result['errors']);
        }

        try {
            $orders = $this->orderService->placeOrders($user->id, $result['lines']);
        } catch (InsufficientBalanceException $e) {
            return back()->withInput()->withErrors(['amount' => $e->getMessage()]);
        } catch (InvalidArgumentException $e) {
            return back()->withInput()->withErrors(['order' => $e->getMessage()]);
        }

        $message = $orders->count() > 1
            ? __(':count orders placed.', ['count' => $orders->count()])
            : __('Order placed.');

        return redirect()->route('agent.orders.index')->with('status', $message);
    }
}
