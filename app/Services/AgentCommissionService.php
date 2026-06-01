<?php

namespace App\Services;

use App\Models\AgentEarningsBalance;
use App\Models\AgentEarningsLedger;
use App\Models\BundlePackage;
use App\Models\Order;
use App\Models\Role;
use App\Models\RolePrice;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class AgentCommissionService
{
    /**
     * @return array{cost: string, commission: string}
     */
    public function calculateForAgentShopOrder(User $buyer, BundlePackage $agentBundle, string $shopPrice): array
    {
        if ($buyer->agent_id === null || (int) $agentBundle->agent_id !== (int) $buyer->agent_id) {
            throw new InvalidArgumentException('Commission applies only to agent shop catalogue orders.');
        }

        $cost = $this->resolveAgentListPrice($agentBundle);
        $commission = bcsub($shopPrice, $cost, 2);

        if (bccomp($commission, '0', 2) < 0) {
            $commission = '0.00';
        }

        return [
            'cost' => bcadd($cost, '0', 2),
            'commission' => bcadd($commission, '0', 2),
        ];
    }

    public function resolveAgentListPrice(BundlePackage $agentBundle): string
    {
        $platformBundle = BundlePackage::query()
            ->whereNull('agent_id')
            ->where('network', $agentBundle->network)
            ->where('size_label', $agentBundle->size_label)
            ->where('package_kind', $agentBundle->package_kind ?? 'data')
            ->first();

        if ($platformBundle === null) {
            return bcadd((string) $agentBundle->internal_cost, '0', 2);
        }

        $agentRoleId = Role::query()->where('slug', Role::SLUG_AGENT)->value('id');
        if ($agentRoleId !== null) {
            $rolePrice = RolePrice::query()
                ->where('role_id', $agentRoleId)
                ->where('bundle_package_id', $platformBundle->id)
                ->first();

            if ($rolePrice !== null) {
                return bcadd((string) $rolePrice->price, '0', 2);
            }
        }

        return bcadd((string) $platformBundle->internal_cost, '0', 2);
    }

    public function creditOnSent(Order $order): void
    {
        if (! $this->isCommissionEligible($order)) {
            return;
        }

        if ($order->agent_commission_status === 'credited') {
            return;
        }

        $commission = bcadd((string) $order->agent_commission_amount, '0', 2);
        if (bccomp($commission, '0', 2) <= 0) {
            $order->agent_commission_status = 'credited';
            $order->save();

            return;
        }

        DB::transaction(function () use ($order, $commission): void {
            /** @var Order $locked */
            $locked = Order::query()->whereKey($order->id)->lockForUpdate()->firstOrFail();

            if ($locked->agent_commission_status === 'credited') {
                return;
            }

            $agentId = (int) $locked->agent_id;
            $this->creditBalance(
                $agentId,
                $commission,
                'ORDER_COMMISSION',
                (string) $locked->id,
                __('Commission for order #:id', ['id' => $locked->id]),
                $locked->id,
            );

            $locked->agent_commission_status = 'credited';
            $locked->save();
        });
    }

    public function reverseCommission(Order $order): void
    {
        if ($order->agent_commission_status !== 'credited') {
            if ($order->agent_commission_status === 'pending' || $order->agent_commission_status === null) {
                $order->agent_commission_status = 'reversed';
                $order->save();
            }

            return;
        }

        $commission = bcadd((string) $order->agent_commission_amount, '0', 2);
        if (bccomp($commission, '0', 2) <= 0) {
            $order->agent_commission_status = 'reversed';
            $order->save();

            return;
        }

        DB::transaction(function () use ($order, $commission): void {
            /** @var Order $locked */
            $locked = Order::query()->whereKey($order->id)->lockForUpdate()->firstOrFail();

            if ($locked->agent_commission_status !== 'credited') {
                return;
            }

            $agentId = (int) $locked->agent_id;
            $this->debitBalance(
                $agentId,
                $commission,
                'ORDER_COMMISSION_REVERSAL',
                (string) $locked->id,
                __('Commission reversed for order #:id', ['id' => $locked->id]),
                $locked->id,
            );

            $locked->agent_commission_status = 'reversed';
            $locked->save();
        });
    }

    public function ensureBalanceRow(int $agentId): AgentEarningsBalance
    {
        return AgentEarningsBalance::query()->firstOrCreate(
            ['agent_id' => $agentId],
            ['balance' => '0.00', 'pending_withdrawal' => '0.00'],
        );
    }

    public function creditBalance(
        int $agentId,
        string $amount,
        string $source,
        ?string $reference,
        ?string $note,
        ?int $orderId = null,
        ?int $withdrawalRequestId = null,
    ): AgentEarningsBalance {
        return DB::transaction(function () use ($agentId, $amount, $source, $reference, $note, $orderId, $withdrawalRequestId): AgentEarningsBalance {
            $balance = AgentEarningsBalance::query()->where('agent_id', $agentId)->lockForUpdate()->first();
            if ($balance === null) {
                $balance = AgentEarningsBalance::query()->create([
                    'agent_id' => $agentId,
                    'balance' => '0.00',
                    'pending_withdrawal' => '0.00',
                ]);
                $balance = AgentEarningsBalance::query()->whereKey($balance->id)->lockForUpdate()->firstOrFail();
            }

            $before = bcadd((string) $balance->balance, '0', 2);
            $after = bcadd($before, $amount, 2);

            AgentEarningsLedger::query()->create([
                'agent_id' => $agentId,
                'order_id' => $orderId,
                'withdrawal_request_id' => $withdrawalRequestId,
                'type' => 'CREDIT',
                'amount' => bcadd($amount, '0', 2),
                'balance_before' => $before,
                'balance_after' => $after,
                'source' => $source,
                'reference' => $reference,
                'note' => $note,
                'created_at' => now(),
            ]);

            $balance->balance = $after;
            $balance->save();

            return $balance->fresh();
        });
    }

    public function debitBalance(
        int $agentId,
        string $amount,
        string $source,
        ?string $reference,
        ?string $note,
        ?int $orderId = null,
        ?int $withdrawalRequestId = null,
    ): AgentEarningsBalance {
        return DB::transaction(function () use ($agentId, $amount, $source, $reference, $note, $orderId, $withdrawalRequestId): AgentEarningsBalance {
            $balance = AgentEarningsBalance::query()->where('agent_id', $agentId)->lockForUpdate()->firstOrFail();

            $before = bcadd((string) $balance->balance, '0', 2);
            if (bccomp($before, $amount, 2) < 0) {
                throw new InvalidArgumentException(__('Insufficient earnings balance.'));
            }

            $after = bcsub($before, $amount, 2);

            AgentEarningsLedger::query()->create([
                'agent_id' => $agentId,
                'order_id' => $orderId,
                'withdrawal_request_id' => $withdrawalRequestId,
                'type' => 'DEBIT',
                'amount' => bcadd($amount, '0', 2),
                'balance_before' => $before,
                'balance_after' => $after,
                'source' => $source,
                'reference' => $reference,
                'note' => $note,
                'created_at' => now(),
            ]);

            $balance->balance = $after;
            $balance->save();

            return $balance->fresh();
        });
    }

    private function isCommissionEligible(Order $order): bool
    {
        if ($order->payment_method !== 'paystack') {
            return false;
        }

        if ($order->agent_id === null) {
            return false;
        }

        if ((int) $order->user_id === (int) $order->agent_id) {
            return false;
        }

        return bccomp((string) ($order->agent_commission_amount ?? '0'), '0', 2) > 0;
    }
}
