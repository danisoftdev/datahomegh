<?php

namespace App\Services;

use App\Models\Order;
use App\Models\PaystackTransaction;
use App\Models\User;
use App\Support\AgentShopBuyerPolicy;
use App\Support\PaystackPaymentPurpose;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use RuntimeException;

class AgentShopCheckoutService
{
    public function __construct(
        private readonly PaystackService $paystackService,
        private readonly OrderService $orderService,
    ) {}

    public function walletCutoffGhs(): string
    {
        return AgentShopBuyerPolicy::walletCutoffGhs();
    }

    public function isAgentShopBuyer(User $buyer): bool
    {
        return AgentShopBuyerPolicy::isAgentShopBuyer($buyer);
    }

    public function requiresPaystackCheckout(User $buyer): bool
    {
        return AgentShopBuyerPolicy::requiresPaystackCheckout($buyer);
    }

    public function canUseWalletCheckout(User $buyer): bool
    {
        return AgentShopBuyerPolicy::canUseWalletCheckout($buyer);
    }

    /**
     * @param  list<array{network: string, phone_number: string, bundle_package_id: int, afa_registration?: array<string, mixed>|null}>  $lines
     * @return array{authorization_url: string, reference: string}
     */
    public function initializePaystackCheckout(User $buyer, array $lines): array
    {
        if (! $this->isAgentShopBuyer($buyer)) {
            throw new InvalidArgumentException('Paystack checkout is only for buyers linked to an agent shop.');
        }

        $total = $this->orderService->estimateCheckoutTotal($buyer->id, $lines);

        try {
            $init = $this->paystackService->initializePayment($buyer, (float) $total, [
                'callback_url' => route('buyer.orders.paystack.callback', [], true),
                'metadata' => [
                    'type' => PaystackPaymentPurpose::AGENT_SHOP_ORDER,
                    'user_id' => $buyer->id,
                ],
            ]);
        } catch (RuntimeException $e) {
            throw new InvalidArgumentException($e->getMessage(), 0, $e);
        }

        $reference = $init['reference'];

        PaystackTransaction::query()->updateOrCreate(
            ['reference' => $reference],
            [
                'user_id' => $buyer->id,
                'amount' => $total,
                'status' => 'pending',
                'channel' => null,
                'paid_at' => null,
                'metadata' => [
                    'kind' => PaystackPaymentPurpose::AGENT_SHOP_ORDER,
                    'lines' => $lines,
                    'initialized_at' => now()->toIso8601String(),
                ],
            ],
        );

        return $init;
    }

    /**
     * @param  array<string, mixed>  $verifyData
     */
    public function completePaystackCheckout(int $userId, string $reference, string $amountGhs, array $verifyData): void
    {
        $txn = PaystackTransaction::query()->where('reference', $reference)->firstOrFail();
        $metadata = is_array($txn->metadata) ? $txn->metadata : [];

        if ($txn->status === 'success' && ($metadata['orders_created'] ?? false) === true) {
            return;
        }

        if (Order::query()->where('paystack_reference', $reference)->exists()) {
            $this->markCheckoutTransactionSuccessful($txn, $amountGhs, $verifyData, $metadata);

            return;
        }

        $lines = $metadata['lines'] ?? null;
        if (! is_array($lines) || $lines === []) {
            throw new RuntimeException('Checkout lines missing for this payment.');
        }

        if ((int) $txn->user_id !== $userId) {
            throw new RuntimeException('Payment user mismatch.');
        }

        $this->orderService->placeOrders($userId, $lines, [
            'payment_method' => 'paystack',
            'paystack_reference' => $reference,
            'skip_wallet_debit' => true,
        ]);

        $this->markCheckoutTransactionSuccessful($txn->fresh(), $amountGhs, $verifyData, $metadata);
    }

    /**
     * @param  array<string, mixed>  $metadata
     * @param  array<string, mixed>  $verifyData
     */
    private function markCheckoutTransactionSuccessful(PaystackTransaction $txn, string $amountGhs, array $verifyData, array $metadata): void
    {
        DB::transaction(function () use ($txn, $amountGhs, $verifyData, $metadata): void {
            /** @var PaystackTransaction $locked */
            $locked = PaystackTransaction::query()->whereKey($txn->id)->lockForUpdate()->firstOrFail();

            $locked->fill([
                'status' => 'success',
                'amount' => $amountGhs,
                'channel' => isset($verifyData['channel']) ? (string) $verifyData['channel'] : $locked->channel,
                'paid_at' => now(),
                'metadata' => array_merge(
                    PaystackPaymentPurpose::metadataAfterSuccess(
                        PaystackPaymentPurpose::AGENT_SHOP_ORDER,
                        $verifyData,
                        $locked,
                    ),
                    [
                        'orders_created' => true,
                        'lines' => $metadata['lines'] ?? [],
                    ],
                ),
            ]);
            $locked->save();
        });
    }

    public function applyWalletCutoffIfNeeded(User $buyer): void
    {
        AgentShopBuyerPolicy::applyWalletCutoffIfNeeded($buyer);
    }
}
