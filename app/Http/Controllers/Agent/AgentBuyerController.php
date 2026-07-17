<?php

namespace App\Http\Controllers\Agent;

use App\Http\Controllers\Controller;
use App\Models\Role;
use App\Models\User;
use App\Services\NotificationService;
use App\Services\WalletService;
use App\Support\AgentShopBuyerPolicy;
use App\Support\TransactionReceipt;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AgentBuyerController extends Controller
{
    public function __construct(
        private readonly NotificationService $notificationService,
        private readonly WalletService $walletService,
    ) {}

    public function index(Request $request): View
    {
        $buyers = User::query()
            ->with(['role', 'wallet'])
            ->where('agent_id', $request->user()->id)
            ->whereHas('role', fn ($q) => $q->where('slug', Role::SLUG_BUYER))
            ->orderByDesc('id')
            ->paginate(20)
            ->withQueryString();

        return view('agent.buyers.index', compact('buyers'));
    }

    public function show(Request $request, User $buyer): View
    {
        $this->assertAgentBuyer($request, $buyer);

        $buyer->load('role', 'wallet');

        $orders = $buyer->orders()
            ->with('bundlePackage')
            ->latest()
            ->paginate(15)
            ->withQueryString();

        return view('agent.buyers.show', [
            'buyer' => $buyer,
            'orders' => $orders,
        ]);
    }

    public function approve(Request $request, User $buyer): RedirectResponse
    {
        $this->assertAgentBuyer($request, $buyer);

        abort_unless($buyer->status === 'pending', 422);

        $buyer->status = 'active';
        $buyer->save();

        $this->notificationService->notify(
            $buyer->id,
            'Account approved',
            'Your buyer account has been approved by your agent.',
            'buyer_approved',
        );

        return back()->with('status', __('Buyer approved.'));
    }

    public function creditWallet(Request $request, User $buyer): RedirectResponse
    {
        $this->assertAgentBuyer($request, $buyer);

        abort_unless($buyer->status === 'active', 422);

        if (! AgentShopBuyerPolicy::canReceiveAgentWalletCredit($buyer)) {
            return back()->withErrors([
                'amount' => __('This buyer must pay with Paystack for orders. Wallet credits are no longer allowed.'),
            ]);
        }

        $validated = $request->validate([
            'amount' => ['required', 'numeric', 'min:0.01', 'max:100000'],
            'note' => ['nullable', 'string', 'max:500'],
        ]);

        $agent = $request->user();
        $ref = 'agent_credit_'.$buyer->id.'_'.str_replace('.', '', uniqid('', true));

        $extra = trim((string) ($validated['note'] ?? ''));
        $ledgerNote = $extra !== ''
            ? __('Agent :agent — :note', ['agent' => $agent->username, 'note' => $extra])
            : __('Credit from agent :agent', ['agent' => $agent->username]);

        $ledger = $this->walletService->credit(
            $buyer->id,
            $validated['amount'],
            'AGENT_CREDIT',
            $ref,
            $ledgerNote,
        );

        $this->notificationService->notify(
            $buyer->id,
            'Wallet credited',
            __('Your agent added :amount GHS to your wallet.', ['amount' => number_format((float) $validated['amount'], 2)]),
            'wallet_agent_credit',
        );

        return back()
            ->with('status', __('Buyer wallet credited.'))
            ->with('transaction_receipt', TransactionReceipt::fromWalletLedger($ledger, $buyer->username));
    }

    /**
     * Detach buyer from this agent (clear agent_id). Intentionally does not delete the user account,
     * so orders, wallet, and login remain valid.
     */
    public function destroy(Request $request, User $buyer): RedirectResponse
    {
        $this->assertAgentBuyer($request, $buyer);

        abort_if($buyer->id === $request->user()->id, 403);

        $buyer->agent_id = null;
        $buyer->save();

        return redirect()->route('agent.buyers.index')->with('status', __('Buyer removed from your shop.'));
    }

    private function assertAgentBuyer(Request $request, User $buyer): void
    {
        abort_unless($buyer->role?->slug === Role::SLUG_BUYER, 404);
        abort_unless((int) $buyer->agent_id === (int) $request->user()->id, 403);
    }
}
