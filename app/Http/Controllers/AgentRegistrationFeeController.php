<?php

namespace App\Http\Controllers;

use App\Models\PaystackTransaction;
use App\Models\User;
use App\Services\AgentShopRegistrationPaymentService;
use App\Services\PaystackService;
use App\Support\PaystackChargeMetadata;
use App\Support\PaystackVerifyAmount;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Throwable;

class AgentRegistrationFeeController extends Controller
{
    public function __construct(
        private readonly PaystackService $paystackService,
        private readonly AgentShopRegistrationPaymentService $registrationPaymentService,
    ) {}

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
            return redirect()->route('login')->withErrors([
                'username' => __('Missing payment reference. If you completed payment, contact support with your Paystack receipt.'),
            ]);
        }

        $session = $request->session()->get('agent_shop_registration');
        $sessionRef = is_array($session) ? (string) ($session['reference'] ?? '') : '';
        $sessionUid = is_array($session) ? (int) ($session['user_id'] ?? 0) : 0;

        try {
            $data = $this->paystackService->verifyPayment($reference);
        } catch (RuntimeException $e) {
            return redirect()->route('login')->withErrors([
                'username' => $e->getMessage(),
            ]);
        }

        if (strtolower((string) ($data['status'] ?? '')) !== 'success') {
            return redirect()->route('login')->withErrors([
                'username' => __('Payment was not completed. Your agent application was not submitted for approval. You can try registering again or contact support.'),
            ]);
        }

        $meta = PaystackChargeMetadata::fromChargeData($data);
        $userId = (int) ($meta['user_id'] ?? 0);
        $type = $meta['type'];

        $txn = PaystackTransaction::query()->where('reference', $reference)->first();
        if ($txn !== null && (($txn->metadata['kind'] ?? null) === 'agent_shop_registration')) {
            $userId = (int) $txn->user_id;
            $type = 'agent_shop_registration';
        }

        if ($userId <= 0 || $type !== 'agent_shop_registration') {
            return redirect()->route('login')->withErrors([
                'username' => __('This payment could not be linked to an agent registration.'),
            ]);
        }

        if ($sessionRef !== '' && ($sessionRef !== $reference || $sessionUid !== $userId)) {
            Log::warning('agent_registration_fee_callback_session_mismatch', [
                'reference' => $reference,
                'session_reference' => $sessionRef,
                'session_user_id' => $sessionUid,
                'resolved_user_id' => $userId,
            ]);
        }

        $amountGhs = PaystackVerifyAmount::ghsFromVerifyData($data);

        try {
            $this->registrationPaymentService->completeSuccessfulPayment($userId, $reference, $amountGhs, $data);
        } catch (Throwable $e) {
            Log::error('agent_registration_fee_callback_failed', [
                'reference' => $reference,
                'user_id' => $userId,
                'exception' => $e,
            ]);

            return redirect()->route('login')->withErrors([
                'username' => __('We could not confirm your registration payment. If money left your account, contact support with reference: :ref.', ['ref' => $reference]),
            ]);
        }

        $request->session()->forget('agent_shop_registration');

        $agent = User::query()->find($userId);

        return redirect()->route('login')
            ->with('status', __('Payment received. Your agent application is now awaiting approval. You will receive an email at :email when it is approved.', ['email' => $agent?->email ?? '—']))
            ->with('agent_reserved_code', $agent?->shop_slug);
    }
}
