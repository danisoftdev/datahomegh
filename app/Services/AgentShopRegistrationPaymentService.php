<?php

namespace App\Services;

use App\Models\PaystackTransaction;
use App\Models\Role;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use RuntimeException;

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

            if ($txn === null || (int) $txn->user_id !== $userId) {
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

            $expected = number_format((float) (string) $txn->amount, 2, '.', '');
            $paid = number_format((float) $amountGhs, 2, '.', '');
            if (abs((float) $expected - (float) $paid) > 0.02) {
                throw new RuntimeException('Paid amount does not match the registration fee.');
            }

            $user->status = 'pending';
            $user->save();

            $txn->fill([
                'amount' => $expected,
                'status' => 'success',
                'channel' => isset($verifyData['channel']) ? (string) $verifyData['channel'] : null,
                'paid_at' => isset($verifyData['paid_at']) ? Carbon::parse($verifyData['paid_at']) : now(),
                'metadata' => $verifyData,
            ]);
            $txn->save();

            $this->notificationService->notifySuppliersNewAgentPendingApproval($user);
        });
    }
}
