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
     * @return Collection<int, Order>
     */
    public function placeOrders(int $userId, array $lines): Collection
    {
        if ($lines === []) {
            throw new InvalidArgumentException('Add at least one order line.');
        }

        if (count($lines) > self::MAX_LINES_PER_CHECKOUT) {
            throw new InvalidArgumentException('Too many items in one checkout.');
        }

        $orders = DB::transaction(function () use ($userId, $lines): Collection {
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

                $prepared[] = [
                    'network' => $line['network'],
                    'phone_number' => $line['phone_number'],
                    'bundle' => $bundle,
                    'afa_registration' => $afaRegistration,
                    'price' => $price,
                ];
            }

            if (bccomp((string) $wallet->balance, $total, 2) < 0) {
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
                    'status' => 'PENDING',
                ]);

                $this->walletService->debit(
                    $userId,
                    $row['price'],
                    'ORDER',
                    (string) $order->id,
                    'Order #'.$order->id
                );

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
        if (! in_array($actor->role?->slug, [Role::SLUG_BUYER, Role::SLUG_AGENT], true)) {
            throw new InvalidArgumentException('Only buyers and agents can cancel purchases this way.');
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

            if (in_array($old, ['SENT', 'REFUNDED'], true)) {
                throw new InvalidArgumentException('Cannot change status from '.$old.'.');
            }

            $this->assertStatusTransition($old, $newStatus);

            if ($newStatus === 'REFUNDED') {
                $this->walletService->credit(
                    $order->user_id,
                    (string) $order->amount,
                    'ORDER_REFUND',
                    (string) $order->id,
                    'Refund for order #'.$order->id
                );

                BundlePackage::query()->whereKey($order->bundle_package_id)->increment('stock_count');
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
