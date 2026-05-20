<?php

namespace App\Http\Controllers\Api\V1;

use App\Exceptions\InsufficientBalanceException;
use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\Role;
use App\Models\User;
use App\Services\OrderService;
use App\Support\AfaRegistrationPayload;
use App\Support\BundleCatalog;
use App\Support\OrderCartItemsValidator;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use InvalidArgumentException;

class ApiOrderController extends Controller
{
    public function __construct(
        private readonly OrderService $orderService,
    ) {}

    public function store(Request $request): JsonResponse
    {
        $user = $request->user();

        if (! in_array($user->role?->slug, [Role::SLUG_BUYER, Role::SLUG_AGENT], true)) {
            return response()->json([
                'success' => false,
                'message' => 'Forbidden',
            ], 403);
        }

        $items = $request->input('items');
        $useBatch = is_array($items) && count($items) > 0;

        if ($useBatch) {
            return $this->storeBatch($request);
        }

        $bundle = $this->bundleCatalogFor($user)
            ->firstWhere('id', (int) $request->input('bundle_package_id'));

        if ($bundle === null) {
            return response()->json([
                'success' => false,
                'message' => 'This bundle is not available for your account.',
            ], 422);
        }

        $allowedNetworks = $bundle->isMtnAfaRegistration()
            ? ['MTN', 'MTN_AFA']
            : ['MTN', 'Telecel', 'AirtelTigo'];

        $rules = [
            'network' => ['required', Rule::in($allowedNetworks)],
            'phone_number' => ['required', 'string', 'regex:/^0[235]\d{8}$/'],
            'bundle_package_id' => ['required', 'integer', 'exists:bundle_packages,id'],
            'confirm' => ['accepted'],
        ];

        if ($bundle->isMtnAfaRegistration()) {
            $rules['afa_registration'] = ['required', 'array'];
            $rules = array_merge($rules, AfaRegistrationPayload::nestedRules());
        }

        $data = $request->validate($rules);

        $networkForOrder = $data['network'] === 'MTN_AFA' ? 'MTN' : $data['network'];
        $payload = [
            'network' => $networkForOrder,
            'phone_number' => $data['phone_number'],
            'bundle_package_id' => (int) $data['bundle_package_id'],
        ];
        if ($bundle->isMtnAfaRegistration()) {
            $payload['afa_registration'] = $data['afa_registration'];
        }

        try {
            $order = $this->orderService->placeOrder($user->id, $payload);
        } catch (InsufficientBalanceException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 400);
        } catch (InvalidArgumentException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 400);
        }

        return response()->json([
            'success' => true,
            'message' => 'Order placed',
            'data' => $order->load('bundlePackage')->toArray(),
        ], 201);
    }

    private function storeBatch(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $result = OrderCartItemsValidator::validate($request, $user, $this->bundleCatalogFor($user));

        if (! $result['ok']) {
            return response()->json([
                'success' => false,
                'message' => $result['errors']->first(),
                'errors' => $result['errors'],
            ], 422);
        }

        try {
            $orders = $this->orderService->placeOrders($user->id, $result['lines']);
        } catch (InsufficientBalanceException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 400);
        } catch (InvalidArgumentException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 400);
        }

        $loaded = $orders->map(fn ($o) => $o->load('bundlePackage')->toArray())->values()->all();

        if (count($loaded) === 1) {
            return response()->json([
                'success' => true,
                'message' => 'Order placed',
                'data' => $loaded[0],
            ], 201);
        }

        return response()->json([
            'success' => true,
            'message' => 'Orders placed',
            'data' => [
                'orders' => $loaded,
            ],
        ], 201);
    }

    public function cancel(Request $request, Order $order): JsonResponse
    {
        $user = $request->user();

        if (! in_array($user->role?->slug, [Role::SLUG_BUYER, Role::SLUG_AGENT], true)) {
            return response()->json([
                'success' => false,
                'message' => 'Forbidden',
            ], 403);
        }

        if ((int) $order->user_id !== (int) $user->id) {
            return response()->json([
                'success' => false,
                'message' => 'Not your order',
            ], 403);
        }

        try {
            $updated = $this->orderService->cancelPendingOrderByPurchaser($user, $order->fresh());
        } catch (InvalidArgumentException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        }

        return response()->json([
            'success' => true,
            'message' => 'Order cancelled; wallet refunded',
            'data' => $updated->load('bundlePackage')->toArray(),
        ]);
    }

    private function bundleCatalogFor(User $user): EloquentCollection
    {
        return $user->isAgent()
            ? BundleCatalog::forAgent($user)
            : BundleCatalog::forBuyer($user);
    }
}
