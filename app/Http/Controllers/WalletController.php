<?php

namespace App\Http\Controllers;

use App\Exceptions\InsufficientBalanceException;
use App\Models\PaystackTransaction;
use App\Models\User;
use App\Models\WalletLedger;
use App\Services\AgentShopRegistrationPaymentService;
use App\Services\PaystackService;
use App\Services\WalletService;
use App\Support\PaystackChargeMetadata;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\View\View;
use RuntimeException;
use Throwable;

class WalletController extends Controller
{
    public function __construct(
        private readonly WalletService $walletService,
        private readonly PaystackService $paystackService,
        private readonly AgentShopRegistrationPaymentService $agentShopRegistrationPaymentService,
    ) {}

    public function index(Request $request): View
    {
        $user = $request->user()->loadMissing(['wallet', 'role', 'agent']);

        $ledger = WalletLedger::query()
            ->where('user_id', $user->id)
            ->orderByDesc('id')
            ->paginate(15);

        $fundsViaAgent = $user->fundsWalletViaAgent();
        $agentMomoNumber = null;
        if ($fundsViaAgent && $user->agent !== null) {
            $agentMomoNumber = trim((string) ($user->agent->whatsapp_number ?: $user->agent->phone ?: '')) ?: null;
        }

        return view('wallet.index', [
            'wallet' => $user->wallet,
            'ledger' => $ledger,
            'fundsViaAgent' => $fundsViaAgent,
            'agentMomoNumber' => $agentMomoNumber,
            'agentShopName' => $user->agent?->shop_name ?? $user->agent?->name,
        ]);
    }

    public function initializeTopup(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'amount' => ['required', 'numeric', 'min:1', 'max:10000'],
        ]);

        $user = $request->user()->loadMissing('role');

        if ($user->fundsWalletViaAgent()) {
            return back()->withErrors([
                'amount' => __('Paystack top-up is not available for buyers linked to an agent. Send mobile money to your agent using the number on your wallet page, put your username in the reference, then your agent will credit your wallet.'),
            ]);
        }

        try {
            $init = $this->paystackService->initializePayment($user, (float) $data['amount']);
        } catch (RuntimeException $e) {
            return back()->withErrors(['amount' => $e->getMessage()]);
        }

        $reference = $init['reference'];

        PaystackTransaction::query()->updateOrCreate(
            ['reference' => $reference],
            [
                'user_id' => $user->id,
                'amount' => $data['amount'],
                'status' => 'pending',
                'channel' => null,
                'paid_at' => null,
                'metadata' => [
                    'initialized_at' => now()->toIso8601String(),
                ],
            ],
        );

        $request->session()->put('wallet_topup', [
            'reference' => $reference,
            'user_id' => $user->id,
        ]);

        return redirect()->away($init['authorization_url']);
    }

    public function callback(Request $request): RedirectResponse
    {
        $reference = (string) (
            $request->query('reference')
            ?? $request->query('trxref')
            ?? $request->input('reference')
            ?? $request->input('trxref')
            ?? ''
        );

        if ($reference === '') {
            return redirect()->route('wallet.index')->withErrors(['paystack' => __('Missing payment reference.')]);
        }

        $session = $request->session()->get('wallet_topup');

        try {
            $data = $this->paystackService->verifyPayment($reference);
        } catch (RuntimeException $e) {
            return redirect()->route('wallet.index')->withErrors(['paystack' => $e->getMessage()]);
        }

        if (strtolower((string) ($data['status'] ?? '')) !== 'success') {
            return redirect()->route('wallet.index')->withErrors(['paystack' => __('Payment was not successful.')]);
        }

        $userId = (int) (PaystackChargeMetadata::fromChargeData($data)['user_id'] ?? 0);
        $authId = (int) $request->user()->id;

        if ($userId !== $authId) {
            return redirect()->route('wallet.index')->withErrors(['paystack' => __('Payment does not match this account.')]);
        }

        if (is_array($session)) {
            $sessionRef = (string) ($session['reference'] ?? '');
            $sessionUid = (int) ($session['user_id'] ?? 0);
            if ($sessionRef !== $reference || $sessionUid !== $authId) {
                return redirect()->route('wallet.index')->withErrors(['paystack' => __('This payment session is invalid or expired.')]);
            }
        }

        $amountGhs = $this->amountGhsFromPaystackData($data);

        try {
            $this->applyVerifiedPaystackCredit($userId, $reference, $amountGhs, $data);
        } catch (Throwable $e) {
            Log::error('Wallet topup callback failed', ['reference' => $reference, 'exception' => $e]);

            return redirect()->route('wallet.index')->withErrors(['paystack' => __('Could not complete wallet credit. Support has been notified.')]);
        }

        $request->session()->forget('wallet_topup');

        return redirect()->route('wallet.index')->with('status', __('Wallet topped up successfully.'));
    }

    public function webhook(Request $request): JsonResponse
    {
        $raw = $request->getContent();
        $signature = (string) $request->header('X-Paystack-Signature', '');

        if (! $this->paystackService->validateWebhook($raw, $signature)) {
            return response()->json(['message' => 'Invalid signature'], 400);
        }

        $payload = $request->json()->all();

        if (($payload['event'] ?? '') !== 'charge.success') {
            return response()->json([], 200);
        }

        $data = $payload['data'] ?? [];
        $reference = (string) ($data['reference'] ?? '');

        if ($reference === '') {
            return response()->json([], 200);
        }

        $userId = (int) (PaystackChargeMetadata::fromChargeData($data)['user_id'] ?? 0);

        $txn = PaystackTransaction::query()->where('reference', $reference)->first();
        if ($txn !== null && (($txn->metadata['kind'] ?? null) === 'agent_shop_registration')) {
            $userId = (int) $txn->user_id;
        } elseif ($userId <= 0) {
            return response()->json([], 200);
        }

        $amountGhs = $this->amountGhsFromPaystackData($data);

        try {
            $this->applyVerifiedPaystackCredit($userId, $reference, $amountGhs, $data);
        } catch (Throwable $e) {
            Log::error('Paystack webhook wallet credit failed', ['reference' => $reference, 'exception' => $e]);

            return response()->json(['message' => 'Processing error'], 500);
        }

        return response()->json([], 200);
    }

    public function adminCredit(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'user_id' => ['required', 'integer', 'exists:users,id'],
            'amount' => ['required', 'numeric', 'min:0.01', 'max:1000000'],
            'note' => ['nullable', 'string', 'max:2000'],
        ]);

        $ref = 'admin_credit_'.uniqid('', true);

        $this->walletService->credit(
            (int) $data['user_id'],
            $data['amount'],
            'ADMIN_CREDIT',
            $ref,
            $data['note'] ?? null,
        );

        return back()->with('status', __('Wallet credited.'));
    }

    public function adminDebit(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'user_id' => ['required', 'integer', 'exists:users,id'],
            'amount' => ['required', 'numeric', 'min:0.01', 'max:1000000'],
            'note' => ['nullable', 'string', 'max:2000'],
        ]);

        $ref = 'admin_debit_'.uniqid('', true);

        try {
            $this->walletService->debit(
                (int) $data['user_id'],
                $data['amount'],
                'ADMIN_DEBIT',
                $ref,
                $data['note'] ?? null,
            );
        } catch (InsufficientBalanceException $e) {
            return back()->withErrors(['amount' => $e->getMessage()]);
        }

        return back()->with('status', __('Wallet debited.'));
    }

    /**
     * @param  array<string, mixed>  $verifyData
     */
    private function applyVerifiedPaystackCredit(int $userId, string $reference, string $amountGhs, array $verifyData): void
    {
        $txn = PaystackTransaction::query()->where('reference', $reference)->first();
        $meta = PaystackChargeMetadata::fromChargeData($verifyData);

        if ($txn !== null && (($txn->metadata['kind'] ?? null) === 'agent_shop_registration')) {
            $this->agentShopRegistrationPaymentService->completeSuccessfulPayment((int) $txn->user_id, $reference, $amountGhs, $verifyData);

            return;
        }

        if ($meta['type'] === 'agent_shop_registration') {
            $uid = (int) ($meta['user_id'] ?? 0);
            if ($uid <= 0) {
                Log::warning('paystack_agent_registration_missing_user_id', ['reference' => $reference]);

                return;
            }
            $this->agentShopRegistrationPaymentService->completeSuccessfulPayment($uid, $reference, $amountGhs, $verifyData);

            return;
        }

        $target = User::query()->with('role')->find($userId);
        if ($target !== null && $target->fundsWalletViaAgent()) {
            Log::warning('paystack_wallet_credit_refused_agent_linked_buyer', [
                'user_id' => $userId,
                'reference' => $reference,
                'amount_ghs' => $amountGhs,
            ]);

            return;
        }

        DB::transaction(function () use ($userId, $reference, $amountGhs, $verifyData): void {
            $txn = PaystackTransaction::query()->where('reference', $reference)->lockForUpdate()->first();

            if ($txn !== null && $txn->status === 'success') {
                return;
            }

            if (WalletLedger::query()
                ->where('user_id', $userId)
                ->where('reference', $reference)
                ->where('source', 'PAYSTACK')
                ->where('type', 'CREDIT')
                ->exists()) {
                return;
            }

            $this->walletService->credit($userId, $amountGhs, 'PAYSTACK', $reference, null);

            PaystackTransaction::query()->updateOrCreate(
                ['reference' => $reference],
                [
                    'user_id' => $userId,
                    'amount' => $amountGhs,
                    'status' => 'success',
                    'channel' => isset($verifyData['channel']) ? (string) $verifyData['channel'] : null,
                    'paid_at' => isset($verifyData['paid_at']) ? Carbon::parse($verifyData['paid_at']) : now(),
                    'metadata' => $verifyData,
                ],
            );
        });
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function amountGhsFromPaystackData(array $data): string
    {
        $pesewas = (int) ($data['amount'] ?? 0);

        return number_format($pesewas / 100, 2, '.', '');
    }
}
