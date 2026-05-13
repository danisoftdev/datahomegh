<?php

namespace App\Http\Controllers;

use App\Exceptions\InsufficientBalanceException;
use App\Models\PaystackTransaction;
use App\Models\WalletLedger;
use App\Services\PaystackService;
use App\Services\WalletService;
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
    ) {}

    public function index(Request $request): View
    {
        $user = $request->user()->loadMissing('wallet');

        $ledger = WalletLedger::query()
            ->where('user_id', $user->id)
            ->orderByDesc('id')
            ->paginate(15);

        return view('wallet.index', [
            'wallet' => $user->wallet,
            'ledger' => $ledger,
        ]);
    }

    public function initializeTopup(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'amount' => ['required', 'numeric', 'min:1', 'max:10000'],
        ]);

        $user = $request->user();

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
        $reference = (string) ($request->query('reference') ?? $request->query('trxref') ?? '');

        if ($reference === '') {
            return redirect()->route('wallet.index')->withErrors(['paystack' => __('Missing payment reference.')]);
        }

        $session = $request->session()->get('wallet_topup');

        try {
            $data = $this->paystackService->verifyPayment($reference);
        } catch (RuntimeException $e) {
            return redirect()->route('wallet.index')->withErrors(['paystack' => $e->getMessage()]);
        }

        if (($data['status'] ?? '') !== 'success') {
            return redirect()->route('wallet.index')->withErrors(['paystack' => __('Payment was not successful.')]);
        }

        $userId = (int) ($this->metadataUserId($data) ?? 0);
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

        $userId = (int) ($this->metadataUserId($data) ?? 0);

        if ($userId <= 0) {
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
    private function metadataUserId(array $data): ?int
    {
        $meta = $data['metadata'] ?? null;

        if (is_string($meta)) {
            $decoded = json_decode($meta, true);
            $meta = is_array($decoded) ? $decoded : null;
        }

        if (is_array($meta) && isset($meta['user_id'])) {
            return (int) $meta['user_id'];
        }

        if (is_object($meta) && isset($meta->user_id)) {
            return (int) $meta->user_id;
        }

        return null;
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
