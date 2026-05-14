<?php

namespace App\Services;

use App\Models\PaystackTransaction;
use App\Models\Role;
use App\Models\User;
use App\Support\PaystackVerifyAmount;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Throwable;

class AgentShopRegistrationPaymentService
{
    public function __construct(
        private readonly NotificationService $notificationService,
    ) {}

    /**
     * After Paystack reports success: move agent from pending_payment to pending and notify suppliers once.
     *
     * @param  array<string, mixed>  $verifyData
     */
    public function completeSuccessfulPayment(int $userId, string $reference, string $amountGhs, array $verifyData): void
    {
        DB::transaction(function () use ($userId, $reference, $amountGhs, $verifyData): void {
            $user = User::query()->lockForUpdate()->find($userId);
            if ($user === null) {
                throw new RuntimeException('User not found for agent registration payment.');
            }

            $user->loadMissing('role');
            if ($user->role?->slug !== Role::SLUG_AGENT) {
                throw new RuntimeException('Agent registration payment does not match an agent account.');
            }

            if ($user->status === 'pending') {
                return;
            }

            $txn = PaystackTransaction::query()
                ->where('reference', $reference)
                ->lockForUpdate()
                ->first();

            if ($txn === null) {
                Log::warning('agent_shop_registration_txn_row_missing_reconciling', [
                    'reference' => $reference,
                    'user_id' => $userId,
                ]);
                $paidGhs = PaystackVerifyAmount::ghsFromVerifyData($verifyData);
                $txn = PaystackTransaction::query()->create([
                    'user_id' => $userId,
                    'reference' => $reference,
                    'amount' => $paidGhs,
                    'status' => 'pending',
                    'channel' => null,
                    'paid_at' => null,
                    'metadata' => [
                        'kind' => 'agent_shop_registration',
                        'reconciled_at' => now()->toIso8601String(),
                    ],
                ]);
                $txn = PaystackTransaction::query()
                    ->whereKey($txn->id)
                    ->lockForUpdate()
                    ->firstOrFail();
            }

            if ((int) $txn->user_id !== $userId) {
                throw new RuntimeException('Paystack transaction does not match this registration.');
            }

            if ($txn->status === 'success') {
                if ($user->status === 'pending_payment') {
                    $user->status = 'pending';
                    $user->save();
                    $this->notificationService->notifySuppliersNewAgentPendingApproval($user);
                }

                return;
            }

            if ($user->status !== 'pending_payment') {
                Log::warning('agent_shop_registration_payment_wrong_status', [
                    'user_id' => $userId,
                    'reference' => $reference,
                    'status' => $user->status,
                ]);

                return;
            }

            $expectedPesewas = (int) round((float) (string) $txn->amount * 100);
            $paidPesewas = PaystackVerifyAmount::pesewasFromVerifyData($verifyData);
            if ($paidPesewas <= 0) {
                Log::error('agent_shop_registration_verify_amount_missing', [
                    'reference' => $reference,
                    'user_id' => $userId,
                    'expected_pesewas' => $expectedPesewas,
                ]);
                throw new RuntimeException('Paystack verify response did not include an amount.');
            }

            $pesewasDiff = abs($expectedPesewas - $paidPesewas);
            if ($pesewasDiff > 10) {
                Log::error('agent_shop_registration_amount_mismatch', [
                    'reference' => $reference,
                    'user_id' => $userId,
                    'expected_pesewas' => $expectedPesewas,
                    'paid_pesewas' => $paidPesewas,
                    'txn_amount_ghs' => (string) $txn->amount,
                    'verify_amount_ghs' => $amountGhs,
                ]);
                throw new RuntimeException('Paid amount does not match the registration fee.');
            }

            if ($pesewasDiff > 0) {
                Log::warning('agent_shop_registration_amount_minor_pesewa_diff', [
                    'reference' => $reference,
                    'expected_pesewas' => $expectedPesewas,
                    'paid_pesewas' => $paidPesewas,
                ]);
            }

            $user->status = 'pending';
            $user->save();

            $paidAt = now();
            if (! empty($verifyData['paid_at'])) {
                try {
                    $paidAt = Carbon::parse($verifyData['paid_at']);
                } catch (Throwable) {
                    Log::warning('agent_shop_registration_paid_at_unparseable', [
                        'reference' => $reference,
                        'paid_at' => $verifyData['paid_at'],
                    ]);
                }
            }

            $txn->fill([
                'amount' => number_format($expectedPesewas / 100, 2, '.', ''),
                'status' => 'success',
                'channel' => isset($verifyData['channel']) ? (string) $verifyData['channel'] : null,
                'paid_at' => $paidAt,
                'metadata' => $verifyData,
            ]);
            $txn->save();

            $this->notificationService->notifySuppliersNewAgentPendingApproval($user);
        });
    }
}
