<?php

namespace App\Http\Controllers;

use App\Exceptions\InsufficientBalanceException;
use App\Models\Order;
use App\Models\Role;
use App\Models\User;
use App\Services\OrderService;
use App\Support\BundleCatalog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use InvalidArgumentException;

class BuyerOrderController extends Controller
{
    public function __construct(
        private readonly OrderService $orderService,
    ) {}

    public function index(Request $request): View
    {
        /** @var User $user */
        $user = $request->user();

        $query = $user->orders()->with('bundlePackage')->latest();

        $status = $request->string('status')->toString();
        if ($status !== '' && in_array($status, ['PENDING', 'PROCESSING', 'SENT', 'FAILED', 'REFUNDED'], true)) {
            $query->where('status', $status);
        }

        $orders = $query->paginate(15)->withQueryString();

        return view('buyer.orders.index', [
            'orders' => $orders,
            'currentStatus' => $status,
        ]);
    }

    public function create(Request $request): View
    {
        /** @var User $user */
        $user = $request->user();

        $networks = ['MTN', 'Telecel', 'AirtelTigo'];
        $bundles = BundleCatalog::forBuyer($user);

        $bundlesJson = $bundles->map(function ($b) use ($user): array {
            return [
                'id' => $b->id,
                'network' => $b->network,
                'name' => $b->name,
                'size_label' => $b->size_label,
                'price' => $this->orderService->priceForBuyer($user, $b),
            ];
        })->values()->all();

        $user->loadMissing('wallet');
        $walletBalance = $user->wallet !== null ? (string) $user->wallet->balance : '0.00';

        return view('buyer.orders.create', [
            'networks' => $networks,
            'bundlesJson' => $bundlesJson,
            'walletBalance' => $walletBalance,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'network' => ['required', Rule::in(['MTN', 'Telecel', 'AirtelTigo'])],
            'phone_number' => ['required', 'string', 'regex:/^0[235]\d{8}$/'],
            'bundle_package_id' => ['required', 'integer', 'exists:bundle_packages,id'],
            'confirm' => ['accepted'],
        ]);

        $bundle = BundleCatalog::forBuyer($request->user())->firstWhere('id', (int) $data['bundle_package_id']);

        if ($bundle === null) {
            return back()->withInput()->withErrors(['bundle_package_id' => __('This bundle is not available for your account.')]);
        }

        try {
            $this->orderService->placeOrder($request->user()->id, $data);
        } catch (InsufficientBalanceException $e) {
            return back()->withInput()->withErrors(['amount' => $e->getMessage()]);
        } catch (InvalidArgumentException $e) {
            return back()->withInput()->withErrors(['order' => $e->getMessage()]);
        }

        return redirect()->route('buyer.orders.index')->with('status', __('Order placed.'));
    }

    public function show(Request $request, Order $order): View
    {
        $this->authorizeBuyerOrder($request->user(), $order);

        $order->load(['bundlePackage']);

        $histories = $order->orderStatusHistories()
            ->with('changedBy')
            ->where('visible_to_buyer', true)
            ->orderBy('id')
            ->get();

        return view('buyer.orders.show', [
            'order' => $order,
            'histories' => $histories,
        ]);
    }

    public function repeatLast(Request $request): JsonResponse
    {
        $last = $request->user()->orders()->latest()->first();

        if ($last === null) {
            return response()->json([
                'ok' => false,
                'network' => null,
                'phone_number' => null,
                'bundle_package_id' => null,
                'bundle_id' => null,
            ]);
        }

        return response()->json([
            'ok' => true,
            'network' => $last->network,
            'phone_number' => $last->phone_number,
            'bundle_package_id' => $last->bundle_package_id,
            'bundle_id' => $last->bundle_package_id,
        ]);
    }

    private function authorizeBuyerOrder(User $user, Order $order): void
    {
        abort_unless($user->role?->slug === Role::SLUG_BUYER, 403);
        abort_unless((int) $order->user_id === (int) $user->id, 403);
    }
}
