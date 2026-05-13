<?php

namespace App\Services;

use App\Exceptions\InsufficientBalanceException;
use App\Models\BundlePackage;
use App\Models\Order;
use App\Models\OrderStatusHistory;
use App\Models\ResalePlan;
use App\Models\RolePrice;
use App\Models\User;
use App\Models\Wallet;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use RuntimeException;

class OrderService
{
    public function __construct(
        private readonly WalletService $walletService,
        private readonly NotificationService $notificationService,
    ) {}

    /**
     * @param  array{network: string, phone_number: string, bundle_package_id: int}  $data
     */
    public function placeOrder(int $userId, array $data): Order
    {
        return DB::transaction(function () use ($userId, $data): Order {
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

            $dup = Order::query()
                ->where('user_id', $userId)
                ->where('phone_number', $data['phone_number'])
                ->where('network', $data['network'])
                ->where('bundle_package_id', $data['bundle_package_id'])
                ->whereIn('status', ['PENDING', 'PROCESSING'])
                ->exists();

            if ($dup) {
                throw new InvalidArgumentException('You already have a pending or processing order for this number, network, and bundle.');
            }

            $limit = $user->daily_order_limit;

            if ($limit !== null && $limit > 0) {
                $todayCount = Order::query()
                    ->where('user_id', $userId)
                    ->whereDate('created_at', now()->toDateString())
                    ->count();

                if ($todayCount >= $limit) {
                    throw new InvalidArgumentException('Daily order limit reached.');
                }
            }

            /** @var BundlePackage $bundle */
            $bundle = BundlePackage::query()->whereKey($data['bundle_package_id'])->lockForUpdate()->firstOrFail();

            if (! $bundle->is_available || $bundle->stock_count < 1) {
                throw new InvalidArgumentException('This bundle is not available or is out of stock.');
            }

            $price = $this->resolvePrice($user, $bundle);

            if (bccomp((string) $wallet->balance, $price, 2) < 0) {
                throw InsufficientBalanceException::forAmount($price);
            }

            $order = Order::query()->create([
                'user_id' => $userId,
                'agent_id' => $user->agent_id,
                'network' => $data['network'],
                'phone_number' => $data['phone_number'],
                'bundle_package_id' => $bundle->id,
                'amount' => $price,
                'status' => 'PENDING',
            ]);

            $this->walletService->debit(
                $userId,
                $price,
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
                'Your order #'.$order->id.' for '.$bundle->name.' ('.$data['network'].') was received and is pending.',
                'order_received'
            );

            return $order->fresh(['bundlePackage', 'orderStatusHistories']);
        });
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
