<?php

namespace App\Services;

use App\Exceptions\InsufficientBalanceException;
use App\Models\BundlePackage;
use App\Models\Order;
use App\Models\OrderStatusHistory;
use App\Models\Role;
use App\Models\ResalePlan;
use App\Models\RolePrice;
use App\Models\User;
use App\Models\Wallet;
use App\Support\AfaRegistrationPayload;
use App\Support\AgentShopBuyerPolicy;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use RuntimeException;

class OrderService
{
    private const MAX_LINES_PER_CHECKOUT = 30;

    public function __construct(
        private readonly WalletService $walletService,
        private readonly NotificationService $notificationService,
        private readonly DataPackageFulfillmentService $dataPackageFulfillmentService,
        private readonly AgentCommissionService $agentCommissionService,
    ) {}

    /**
     * @param  array{network: string, phone_number: string, bundle_package_id: int, afa_registration?: array<string, mixed>|null}  $data
     */
    public function placeOrder(int $userId, array $data): Order
    {
        return $this->placeOrders($userId, [$data])->firstOrFail();
    }

    /**
     * Place multiple orders in one wallet checkout (one submit). Each line must include the recipient phone number.
     *
     * @param  list<array{network: string, phone_number: string, bundle_package_id: int, afa_registration?: array<string, mixed>|null}>  $lines
     * @param  array{payment_method?: string, paystack_reference?: string|null, skip_wallet_debit?: bool}  $options
     * @return Collection<int, Order>
     */
    public function placeOrders(int $userId, array $lines, array $options = []): Collection
    {
        if ($lines === []) {
            throw new InvalidArgumentException('Add at least one order line.');
        }

        if (count($lines) > self::MAX_LINES_PER_CHECKOUT) {
            throw new InvalidArgumentException('Too many items in one checkout.');
        }

        $paymentMethod = (string) ($options['payment_method'] ?? 'wallet');
        $paystackReference = isset($options['paystack_reference']) ? (string) $options['paystack_reference'] : null;
        $skipWalletDebit = (bool) ($options['skip_wallet_debit'] ?? false);

        if ($paymentMethod === 'paystack' && ! $skipWalletDebit) {
            throw new InvalidArgumentException('Paystack orders must skip wallet debit.');
        }

        $orders = DB::transaction(function () use ($userId, $lines, $paymentMethod, $paystackReference, $skipWalletDebit): Collection {
            /** @var User $user */
            $user = User::query()->whereKey($userId)->lockForUpdate()->firstOrFail();

            if ($user->status !== 'active') {
                throw new InvalidArgumentException('Your account must be active to place orders.');
            }

            if ($user->wallet_frozen) {
                throw new InvalidArgumentException('Your wallet is frozen.');
            }

            $wallet = Wallet::query()->where('user_id', $userId)->lockForUpdate()->first();

            if ($wallet === null || $wallet->is_frozen) {
                throw new InvalidArgumentException('Your wallet is not available or is frozen.');
            }

            if ($paymentMethod === 'wallet' && AgentShopBuyerPolicy::isAgentShopBuyer($user)) {
                if (AgentShopBuyerPolicy::requiresPaystackCheckout($user)) {
                    throw new InvalidArgumentException(__('Your account must pay with Paystack for each order.'));
                }

                if (! AgentShopBuyerPolicy::canUseWalletCheckout($user)) {
                    throw new InvalidArgumentException(__('Your wallet balance is too low. Pay with Paystack for your next order.'));
                }
            }

            if ($paymentMethod === 'wallet' && ! $skipWalletDebit) {
                // wallet checkout continues below
            } elseif ($paymentMethod !== 'paystack' || ! $skipWalletDebit) {
                throw new InvalidArgumentException('Invalid checkout payment configuration.');
            }

            $limit = $user->daily_order_limit;

            if ($limit !== null && $limit > 0) {
                $todayCount = Order::query()
                    ->where('user_id', $userId)
                    ->whereDate('created_at', now()->toDateString())
                    ->count();

                if ($todayCount + count($lines) > $limit) {
                    throw new InvalidArgumentException('Daily order limit reached.');
                }
            }

            $bundleIds = array_values(array_unique(array_map(fn (array $l): int => (int) $l['bundle_package_id'], $lines)));
            sort($bundleIds);

            /** @var array<int, BundlePackage> $bundlesById */
            $bundlesById = [];
            foreach ($bundleIds as $bid) {
                /** @var BundlePackage $b */
                $b = BundlePackage::query()->whereKey($bid)->lockForUpdate()->firstOrFail();
                $bundlesById[$bid] = $b;
            }

            $neededPerBundle = [];
            foreach ($lines as $line) {
                $bid = (int) $line['bundle_package_id'];
                $neededPerBundle[$bid] = ($neededPerBundle[$bid] ?? 0) + 1;
            }

            foreach ($neededPerBundle as $bid => $need) {
                $b = $bundlesById[$bid];
                if (! $b->is_available || $b->stock_count < $need) {
                    throw new InvalidArgumentException('One of the selected bundles is not available or does not have enough stock for this checkout.');
                }
            }

            $seenInBatch = [];
            $total = '0.00';
            $prepared = [];

            foreach ($lines as $index => $line) {
                $dupKey = $line['phone_number'].'|'.$line['network'].'|'.$line['bundle_package_id'];
                if (isset($seenInBatch[$dupKey])) {
                    throw new InvalidArgumentException('This checkout has duplicate lines for the same number, network, and bundle.');
                }
                $seenInBatch[$dupKey] = true;

                $dup = Order::query()
                    ->where('user_id', $userId)
                    ->where('phone_number', $line['phone_number'])
                    ->where('network', $line['network'])
                    ->where('bundle_package_id', $line['bundle_package_id'])
                    ->whereIn('status', ['PENDING', 'PROCESSING'])
                    ->exists();

                if ($dup) {
                    throw new InvalidArgumentException('You already have a pending or processing order for this number, network, and bundle.');
                }

                /** @var BundlePackage $bundle */
                $bundle = $bundlesById[(int) $line['bundle_package_id']];

                if ($bundle->network !== $line['network']) {
                    throw new InvalidArgumentException('The selected network does not match this bundle.');
                }

                $afaRegistration = null;
                if ($bundle->isMtnAfaRegistration()) {
                    $afaRegistration = AfaRegistrationPayload::validateOrFail(
                        isset($line['afa_registration']) && is_array($line['afa_registration']) ? $line['afa_registration'] : null
                    );
                } elseif (isset($line['afa_registration']) && is_array($line['afa_registration']) && $line['afa_registration'] !== []) {
                    throw new InvalidArgumentException('Registration details are only used for MTN AFA bundles.');
                }

                $price = $this->resolvePrice($user, $bundle);
                $total = bcadd($total, $price, 2);

                $commissionMeta = null;
                if ($paymentMethod === 'paystack' && AgentShopBuyerPolicy::isAgentShopBuyer($user)) {
                    $commissionMeta = $this->agentCommissionService->calculateForAgentShopOrder($user, $bundle, $price);
                }

                $prepared[] = [
                    'network' => $line['network'],
                    'phone_number' => $line['phone_number'],
                    'bundle' => $bundle,
                    'afa_registration' => $afaRegistration,
                    'price' => $price,
                    'commission_meta' => $commissionMeta,
                ];
            }

            if (! $skipWalletDebit && bccomp((string) $wallet->balance, $total, 2) < 0) {
                throw InsufficientBalanceException::forAmount($total);
            }

            $orders = new Collection;

            foreach ($prepared as $row) {
                /** @var BundlePackage $bundle */
                $bundle = $row['bundle'];

                $order = Order::query()->create([
                    'user_id' => $userId,
                    'agent_id' => ($user->isAgent() || $user->hasAgentShop()) ? $user->id : $user->agent_id,
                    'network' => $row['network'],
                    'phone_number' => $row['phone_number'],
                    'afa_registration' => $row['afa_registration'],
                    'bundle_package_id' => $bundle->id,
                    'amount' => $row['price'],
                    'payment_method' => $paymentMethod,
                    'paystack_reference' => $paystackReference,
                    'agent_cost_amount' => $row['commission_meta']['cost'] ?? null,
                    'agent_commission_amount' => $row['commission_meta']['commission'] ?? null,
                    'agent_commission_status' => $row['commission_meta'] !== null ? 'pending' : null,
                    'status' => 'PENDING',
                ]);

                if (! $skipWalletDebit) {
                    $this->walletService->debit(
                        $userId,
                        $row['price'],
                        'ORDER',
                        (string) $order->id,
                        'Order #'.$order->id
                    );
                }

                $bundle->decrement('stock_count');
                $bundle->refresh();

                if ($bundle->stock_count < 1) {
                    $bundle->is_available = false;
                    $bundle->save();
                }

                OrderStatusHistory::query()->create([
                    'order_id' => $order->id,
                    'old_status' => null,
                    'new_status' => 'PENDING',
                    'changed_by' => $userId,
                    'note' => null,
                    'visible_to_buyer' => true,
                    'created_at' => now(),
                ]);

                $this->notifyBuyer(
                    $userId,
                    'Order received',
                    'Your order #'.$order->id.' for '.$bundle->name.' ('.$row['network'].') was received and is pending.',
                    'order_received'
                );

                if ($order->fresh() && Order::query()->visibleToSupplier()->whereKey($order->id)->exists()) {
                    $this->notificationService->notifySuppliersNewOrder($order);
                }

                $orders->push($order->fresh(['bundlePackage', 'orderStatusHistories']));
            }

            if (! $skipWalletDebit && AgentShopBuyerPolicy::isAgentShopBuyer($user)) {
                $user->refresh();
                $user->load('wallet');
                AgentShopBuyerPolicy::applyWalletCutoffIfNeeded($user);
            }

            return $orders;
        });

        foreach ($orders as $order) {
            $this->dataPackageFulfillmentService->dispatchAfterOrderPlaced($order);
        }

        return $orders;
    }

    /**
     * Buyer or agent cancels their own mistaken purchase while still PENDING: refund wallet and RESTOCK bundle (same as admin REFUNDED).
     *
     * @throws InvalidArgumentException
     */
    public function cancelPendingOrderByPurchaser(User $actor, Order $order): Order
    {
        if ($actor->canAccessAgentArea()) {
            throw new InvalidArgumentException(__('Agents cannot cancel or refund orders.'));
        }

        if ($actor->role?->slug !== Role::SLUG_BUYER) {
            throw new InvalidArgumentException('Only buyers can cancel purchases this way.');
        }

        if ((int) $order->user_id !== (int) $actor->id) {
            throw new InvalidArgumentException('You can only cancel orders charged to your wallet.');
        }

        if ($order->status !== 'PENDING') {
            throw new InvalidArgumentException(
                __('This order can no longer be cancelled from your account. Once it moves past pending, please contact support.')
            );
        }

        return $this->updateStatus(
            $order->id,
            'REFUNDED',
            (int) $actor->id,
            __('Cancelled by customer — refunded to wallet'),
            true,
        );
    }

    /**
     * @throws InvalidArgumentException|RuntimeException
     */
    public function updateStatus(
        int $orderId,
        string $newStatus,
        int $changedByUserId,
        ?string $note,
        bool $visibleToBuyer,
    ): Order {
        return DB::transaction(function () use ($orderId, $newStatus, $changedByUserId, $note, $visibleToBuyer): Order {
            /** @var Order $order */
            $order = Order::query()->whereKey($orderId)->lockForUpdate()->firstOrFail();

            $old = $order->status;

            if ($newStatus === 'REFUNDED') {
                $changer = User::query()->whereKey($changedByUserId)->first();
                if ($changer === null || ! $changer->isSupplier()) {
                    throw new InvalidArgumentException(__('Only the supplier can refund orders.'));
                }
            }

            if ($old === 'REFUNDED') {
                throw new InvalidArgumentException('Cannot change status from '.$old.'.');
            }

            if ($old === 'SENT' && $newStatus !== 'REFUNDED') {
                throw new InvalidArgumentException('Cannot change status from '.$old.'.');
            }

            if ($old !== 'SENT') {
                $this->assertStatusTransition($old, $newStatus);
            }

            if ($newStatus === 'REFUNDED') {
                if ($order->payment_method !== 'paystack') {
                    $this->walletService->credit(
                        $order->user_id,
                        (string) $order->amount,
                        'ORDER_REFUND',
                        (string) $order->id,
                        'Refund for order #'.$order->id
                    );
                }

                BundlePackage::query()->whereKey($order->bundle_package_id)->increment('stock_count');
                $this->agentCommissionService->reverseCommission($order);
            }

            if ($newStatus === 'FAILED') {
                $this->agentCommissionService->reverseCommission($order);
            }

            if ($newStatus === 'SENT') {
                $this->agentCommissionService->creditOnSent($order);
            }

            $order->status = $newStatus;
            $order->save();

            OrderStatusHistory::query()->create([
                'order_id' => $order->id,
                'old_status' => $old,
                'new_status' => $newStatus,
                'changed_by' => $changedByUserId,
                'note' => $note,
                'visible_to_buyer' => $visibleToBuyer,
                'created_at' => now(),
            ]);

            $this->notifyBuyer(
                $order->user_id,
                'Order status updated',
                'Your order #'.$order->id.' is now '.$newStatus.'.',
                'order_status'
            );

            return $order->fresh(['bundlePackage', 'orderStatusHistories']);
        });
    }

    /**
     * @param  list<int>  $orderIds
     * @return list<Order>
     */
    public function bulkUpdateStatus(
        array $orderIds,
        string $newStatus,
        int $changedByUserId,
        ?string $note,
        bool $visibleToBuyer,
    ): array {
        $orders = [];

        foreach ($orderIds as $id) {
            $orders[] = $this->updateStatus((int) $id, $newStatus, $changedByUserId, $note, $visibleToBuyer);
        }

        return $orders;
    }

    public function addOrderNote(int $orderId, int $changedByUserId, string $note, bool $visibleToBuyer): Order
    {
        return DB::transaction(function () use ($orderId, $changedByUserId, $note, $visibleToBuyer): Order {
            /** @var Order $order */
            $order = Order::query()->whereKey($orderId)->lockForUpdate()->firstOrFail();

            $status = $order->status;

            OrderStatusHistory::query()->create([
                'order_id' => $order->id,
                'old_status' => $status,
                'new_status' => $status,
                'changed_by' => $changedByUserId,
                'note' => $note,
                'visible_to_buyer' => $visibleToBuyer,
                'created_at' => now(),
            ]);

            if ($visibleToBuyer) {
                $this->notifyBuyer(
                    $order->user_id,
                    'Order update',
                    'A note was added to your order #'.$order->id.'.',
                    'order_note'
                );
            }

            return $order->fresh(['bundlePackage', 'orderStatusHistories']);
        });
    }

    private function assertStatusTransition(string $from, string $to): void
    {
        if ($from === $to) {
            throw new InvalidArgumentException('New status is the same as the current status.');
        }

        $allowed = [
            'PENDING' => ['PROCESSING', 'FAILED', 'REFUNDED'],
            'PROCESSING' => ['SENT', 'FAILED', 'REFUNDED'],
            'FAILED' => ['REFUNDED'],
        ];

        if (! in_array($to, $allowed[$from] ?? [], true)) {
            throw new InvalidArgumentException("Cannot transition from {$from} to {$to}.");
        }
    }

    public function priceForBuyer(User $buyer, BundlePackage $bundle): string
    {
        return $this->resolvePrice($buyer, $bundle);
    }

    /**
     * @param  list<array{network: string, phone_number: string, bundle_package_id: int, afa_registration?: array<string, mixed>|null}>  $lines
     */
    public function estimateCheckoutTotal(int $userId, array $lines): string
    {
        /** @var User $user */
        $user = User::query()->whereKey($userId)->firstOrFail();

        $total = '0.00';
        foreach ($lines as $line) {
            /** @var BundlePackage $bundle */
            $bundle = BundlePackage::query()->whereKey((int) $line['bundle_package_id'])->firstOrFail();
            $total = bcadd($total, $this->resolvePrice($user, $bundle), 2);
        }

        return $total;
    }

    private function resolvePrice(User $buyer, BundlePackage $bundle): string
    {
        // MTN AFA is a fixed registration/minute-call style product: one list price on the bundle,
        // not tiered like data (no role price or agent resale overlay).
        if ($bundle->isMtnAfaRegistration()) {
            return bcadd((string) $bundle->internal_cost, '0', 2);
        }

        $rolePrice = RolePrice::query()
            ->where('role_id', $buyer->role_id)
            ->where('bundle_package_id', $bundle->id)
            ->first();

        if ($rolePrice !== null) {
            return bcadd((string) $rolePrice->price, '0', 2);
        }

        if ($buyer->agent_id !== null) {
            $plan = ResalePlan::query()
                ->where('bundle_package_id', $bundle->id)
                ->where('agent_id', $buyer->agent_id)
                ->where('is_active', true)
                ->first();

            if ($plan !== null) {
                return bcadd((string) $plan->price, '0', 2);
            }
        }

        return bcadd((string) $bundle->internal_cost, '0', 2);
    }

    private function notifyBuyer(int $userId, string $title, string $message, string $type): void
    {
        $this->notificationService->notify($userId, $title, $message, $type);
    }
}
