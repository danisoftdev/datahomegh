<?php

namespace App\Services;

use App\Models\AgentWithdrawalRequest;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class AgentWithdrawalService
{
    public function __construct(
        private readonly AgentCommissionService $agentCommissionService,
    ) {}

    public function minAmount(): string
    {
        return bcadd((string) config('datahome.agent_shop.withdrawal_min_amount', 10), '0', 2);
    }

    public function feeAmount(): string
    {
        return bcadd((string) config('datahome.agent_shop.withdrawal_fee_ghs', 0), '0', 2);
    }

    /**
     * @param  array<string, mixed>  $payoutDetails
     */
    public function requestWithdrawal(User $agent, string $amount, ?string $agentNote, string $payoutMethod, array $payoutDetails): AgentWithdrawalRequest
    {
        if (! $agent->canAccessAgentArea()) {
            throw new InvalidArgumentException('Only agents can request withdrawals.');
        }

        $amount = bcadd($amount, '0', 2);
        $fee = $this->feeAmount();
        $min = $this->minAmount();

        if (bccomp($amount, $min, 2) < 0) {
            throw new InvalidArgumentException(__('Minimum withdrawal is :amount GHS.', ['amount' => $min]));
        }

        $net = bcsub($amount, $fee, 2);
        if (bccomp($net, '0', 2) <= 0) {
            throw new InvalidArgumentException(__('Withdrawal amount must exceed the fee.'));
        }

        $this->validatePayoutDetails($payoutMethod, $payoutDetails);

        return DB::transaction(function () use ($agent, $amount, $fee, $net, $agentNote, $payoutMethod, $payoutDetails): AgentWithdrawalRequest {
            $balance = $this->agentCommissionService->ensureBalanceRow($agent->id);
            $available = bcadd((string) $balance->balance, '0', 2);

            if (bccomp($available, $amount, 2) < 0) {
                throw new InvalidArgumentException(__('Insufficient earnings balance.'));
            }

            $request = AgentWithdrawalRequest::query()->create([
                'agent_id' => $agent->id,
                'amount' => $amount,
                'fee' => $fee,
                'net_amount' => $net,
                'status' => AgentWithdrawalRequest::STATUS_PENDING,
                'payout_method' => $payoutMethod,
                'payout_details' => $payoutDetails,
                'agent_note' => $agentNote,
            ]);

            $this->agentCommissionService->debitBalance(
                $agent->id,
                $amount,
                'WITHDRAWAL_HOLD',
                (string) $request->id,
                __('Withdrawal request #:id', ['id' => $request->id]),
                null,
                $request->id,
            );

            $locked = $this->agentCommissionService->ensureBalanceRow($agent->id);
            $locked->pending_withdrawal = bcadd((string) $locked->pending_withdrawal, $amount, 2);
            $locked->save();

            $agent->payout_method = $payoutMethod;
            $agent->payout_details = $payoutDetails;
            $agent->save();

            return $request->fresh();
        });
    }

    public function updateStatus(AgentWithdrawalRequest $request, string $newStatus, User $admin, ?string $adminNote): AgentWithdrawalRequest
    {
        $allowed = [
            AgentWithdrawalRequest::STATUS_PENDING => [
                AgentWithdrawalRequest::STATUS_PROCESSING,
                AgentWithdrawalRequest::STATUS_REJECTED,
            ],
            AgentWithdrawalRequest::STATUS_PROCESSING => [
                AgentWithdrawalRequest::STATUS_PAID,
                AgentWithdrawalRequest::STATUS_REJECTED,
            ],
        ];

        if (! in_array($newStatus, $allowed[$request->status] ?? [], true)) {
            throw new InvalidArgumentException(__('Invalid withdrawal status transition.'));
        }

        return DB::transaction(function () use ($request, $newStatus, $admin, $adminNote): AgentWithdrawalRequest {
            /** @var AgentWithdrawalRequest $locked */
            $locked = AgentWithdrawalRequest::query()->whereKey($request->id)->lockForUpdate()->firstOrFail();

            if ($newStatus === AgentWithdrawalRequest::STATUS_REJECTED) {
                $this->agentCommissionService->creditBalance(
                    (int) $locked->agent_id,
                    bcadd((string) $locked->amount, '0', 2),
                    'WITHDRAWAL_RELEASE',
                    (string) $locked->id,
                    __('Withdrawal request #:id rejected', ['id' => $locked->id]),
                    null,
                    $locked->id,
                );

                $balance = $this->agentCommissionService->ensureBalanceRow((int) $locked->agent_id);
                $balance->pending_withdrawal = bcsub((string) $balance->pending_withdrawal, (string) $locked->amount, 2);
                if (bccomp((string) $balance->pending_withdrawal, '0', 2) < 0) {
                    $balance->pending_withdrawal = '0.00';
                }
                $balance->save();
            }

            if ($newStatus === AgentWithdrawalRequest::STATUS_PAID) {
                $balance = $this->agentCommissionService->ensureBalanceRow((int) $locked->agent_id);
                $balance->pending_withdrawal = bcsub((string) $balance->pending_withdrawal, (string) $locked->amount, 2);
                if (bccomp((string) $balance->pending_withdrawal, '0', 2) < 0) {
                    $balance->pending_withdrawal = '0.00';
                }
                $balance->save();
            }

            $locked->status = $newStatus;
            $locked->admin_note = $adminNote ?? $locked->admin_note;
            $locked->processed_by = $admin->id;
            $locked->processed_at = now();
            $locked->save();

            return $locked->fresh(['agent']);
        });
    }

    /**
     * @param  array<string, mixed>  $details
     */
    private function validatePayoutDetails(string $method, array $details): void
    {
        if ($method === 'momo') {
            $number = trim((string) ($details['momo_number'] ?? ''));
            $network = trim((string) ($details['momo_network'] ?? ''));
            $name = trim((string) ($details['account_name'] ?? ''));

            if ($number === '' || $network === '' || $name === '') {
                throw new InvalidArgumentException(__('Mobile money payout requires network, number, and account name.'));
            }

            return;
        }

        if ($method === 'bank') {
            $bank = trim((string) ($details['bank_name'] ?? ''));
            $account = trim((string) ($details['account_number'] ?? ''));
            $name = trim((string) ($details['account_name'] ?? ''));

            if ($bank === '' || $account === '' || $name === '') {
                throw new InvalidArgumentException(__('Bank payout requires bank name, account number, and account name.'));
            }

            return;
        }

        throw new InvalidArgumentException(__('Select a valid payout method.'));
    }
}
