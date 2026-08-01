<?php

namespace App\Http\Controllers;

use App\Exceptions\InsufficientBalanceException;
use App\Models\Order;
use App\Models\Role;
use App\Models\User;
use App\Services\AgentShopCheckoutService;
use App\Services\OrderService;
use App\Support\AgentShopBuyerPolicy;
use App\Support\AfaRegistrationPayload;
use App\Support\BundleCatalog;
use App\Support\OrderCartItemsValidator;
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
        private readonly AgentShopCheckoutService $agentShopCheckoutService,
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

        $networks = ['MTN', 'Telecel', 'AirtelTigo', 'MTN_AFA'];
        $bundles = BundleCatalog::forUser($user);

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
        $isAgentShopBuyer = AgentShopBuyerPolicy::isAgentShopBuyer($user);
        $requiresPaystack = AgentShopBuyerPolicy::requiresPaystackCheckout($user);
        $canUseWallet = AgentShopBuyerPolicy::canUseWalletCheckout($user);

        return view('buyer.orders.create', [
            'networks' => $networks,
            'networkLabels' => [
                'MTN' => 'MTN',
                'Telecel' => 'Telecel',
                'AirtelTigo' => 'AirtelTigo',
                'MTN_AFA' => __('MTN AFA'),
            ],
            'bundlesJson' => $bundlesJson,
            'walletBalance' => $walletBalance,
            'isAgentShopBuyer' => $isAgentShopBuyer,
            'requiresPaystack' => $requiresPaystack,
            'canUseWallet' => $canUseWallet,
            'walletCutoff' => AgentShopBuyerPolicy::walletCutoffGhs(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $items = $request->input('items');
        $useBatch = is_array($items) && count($items) > 0;

        if ($useBatch) {
            return $this->storeBatch($request);
        }

        $bundle = BundleCatalog::forUser($request->user())
            ->firstWhere('id', (int) $request->input('bundle_package_id'));

        if ($bundle === null) {
            return back()->withInput()->withErrors(['bundle_package_id' => __('This bundle is not available for your account.')]);
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
            if ($this->shouldPayWithPaystack($request->user(), $request)) {
                return $this->redirectToPaystackCheckout($request->user(), [$payload]);
            }

            $this->orderService->placeOrder($request->user()->id, $payload);
        } catch (InsufficientBalanceException $e) {
            return back()->withInput()->withErrors(['amount' => $e->getMessage()]);
        } catch (InvalidArgumentException $e) {
            return back()->withInput()->withErrors(['order' => $e->getMessage()]);
        }

        return redirect()->route('buyer.orders.index')->with('status', __('Order placed.'));
    }

    private function storeBatch(Request $request): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();

        $result = OrderCartItemsValidator::validate($request, $user, BundleCatalog::forUser($user));

        if (! $result['ok']) {
            return back()->withInput()->withErrors($result['errors']);
        }

        $lines = $result['lines'];

        try {
            if ($this->shouldPayWithPaystack($user, $request)) {
                return $this->redirectToPaystackCheckout($user, $lines);
            }

            $orders = $this->orderService->placeOrders($user->id, $lines);
        } catch (InsufficientBalanceException $e) {
            return back()->withInput()->withErrors(['amount' => $e->getMessage()]);
        } catch (InvalidArgumentException $e) {
            return back()->withInput()->withErrors(['order' => $e->getMessage()]);
        }

        $message = $orders->count() > 1
            ? __(':count orders placed.', ['count' => $orders->count()])
            : __('Order placed.');

        return redirect()->route('buyer.orders.index')->with('status', $message);
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

    public function cancel(Request $request, Order $order): RedirectResponse
    {
        $this->authorizeBuyerOrder($request->user(), $order);

        try {
            $this->orderService->cancelPendingOrderByPurchaser($request->user(), $order->fresh());
        } catch (InvalidArgumentException $e) {
            return back()->withErrors(['cancel' => $e->getMessage()]);
        }

        return redirect()->route('buyer.orders.show', $order->fresh())->with(
            'status',
            $order->payment_method === 'paystack'
                ? __('Order cancelled.')
                : __('Order cancelled. Your wallet was refunded automatically.')
        );
    }

    public function paystackCallback(Request $request): RedirectResponse
    {
        $reference = (string) (
            $request->query('reference')
            ?? $request->query('trxref')
            ?? ''
        );

        if ($reference === '') {
            return redirect()->route('buyer.orders.create')->withErrors(['paystack' => __('Missing payment reference.')]);
        }

        $user = $request->user();
        if ($user === null) {
            return redirect()->route('login')->withErrors(['paystack' => __('Sign in to complete your order.')]);
        }

        try {
            $verify = app(\App\Services\PaystackService::class)->verifyPayment($reference);
        } catch (\RuntimeException $e) {
            return redirect()->route('buyer.orders.create')->withErrors(['paystack' => $e->getMessage()]);
        }

        if (strtolower((string) ($verify['status'] ?? '')) !== 'success') {
            return redirect()->route('buyer.orders.create')->withErrors(['paystack' => __('Payment was not successful.')]);
        }

        try {
            $this->agentShopCheckoutService->completePaystackCheckout((int) $user->id, $reference, $verify);
        } catch (\Throwable $e) {
            return redirect()->route('buyer.orders.create')->withErrors(['paystack' => __('Could not place your order after payment. Contact support with reference :ref.', ['ref' => $reference])]);
        }

        return redirect()->route('buyer.orders.index')->with('status', __('Payment successful. Your order was placed.'));
    }

    /**
     * @param  list<array{network: string, phone_number: string, bundle_package_id: int, afa_registration?: array<string, mixed>|null}>  $lines
     */
    private function redirectToPaystackCheckout(User $user, array $lines): RedirectResponse
    {
        try {
            $init = $this->agentShopCheckoutService->initializePaystackCheckout($user, $lines);
        } catch (InvalidArgumentException $e) {
            return back()->withInput()->withErrors(['order' => $e->getMessage()]);
        }

        $request = request();
        $request->session()->put('agent_shop_checkout', [
            'reference' => $init['reference'],
            'user_id' => $user->id,
        ]);

        $authorizationUrl = trim((string) ($init['authorization_url'] ?? ''));
        if ($authorizationUrl === '') {
            return back()->withInput()->withErrors([
                'order' => __('Paystack did not return a payment page. Please try again or contact support.'),
            ]);
        }

        return redirect()->away($authorizationUrl);
    }

    private function shouldPayWithPaystack(User $user, Request $request): bool
    {
        if (! AgentShopBuyerPolicy::isAgentShopBuyer($user)) {
            return false;
        }

        if (AgentShopBuyerPolicy::requiresPaystackCheckout($user)) {
            return true;
        }

        return $request->input('payment_method') === 'paystack';
    }

    public function repeatLast(Request $request): JsonResponse
    {
        $last = $request->user()->orders()->with('bundlePackage')->latest()->first();

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
            'network' => $last->bundlePackage?->isMtnAfaRegistration() ? 'MTN_AFA' : $last->network,
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
