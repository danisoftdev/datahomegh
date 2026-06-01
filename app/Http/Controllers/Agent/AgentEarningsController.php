<?php

namespace App\Http\Controllers\Agent;

use App\Http\Controllers\Controller;
use App\Models\AgentEarningsLedger;
use App\Models\AgentWithdrawalRequest;
use App\Services\AgentCommissionService;
use App\Services\AgentWithdrawalService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use InvalidArgumentException;

class AgentEarningsController extends Controller
{
    public function __construct(
        private readonly AgentCommissionService $agentCommissionService,
        private readonly AgentWithdrawalService $agentWithdrawalService,
    ) {}

    public function index(Request $request): View
    {
        $agent = $request->user();
        $balance = $this->agentCommissionService->ensureBalanceRow($agent->id);

        $ledger = AgentEarningsLedger::query()
            ->where('agent_id', $agent->id)
            ->orderByDesc('id')
            ->paginate(20)
            ->withQueryString();

        $withdrawals = AgentWithdrawalRequest::query()
            ->where('agent_id', $agent->id)
            ->latest()
            ->limit(10)
            ->get();

        return view('agent.earnings.index', [
            'balance' => $balance,
            'ledger' => $ledger,
            'withdrawals' => $withdrawals,
            'minWithdrawal' => $this->agentWithdrawalService->minAmount(),
            'withdrawalFee' => $this->agentWithdrawalService->feeAmount(),
            'payoutMethod' => $agent->payout_method,
            'payoutDetails' => $agent->payout_details ?? [],
        ]);
    }

    public function storeWithdrawal(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'amount' => ['required', 'numeric', 'min:0.01', 'max:1000000'],
            'agent_note' => ['nullable', 'string', 'max:500'],
            'payout_method' => ['required', Rule::in(['momo', 'bank'])],
            'momo_network' => ['nullable', 'string', 'max:64'],
            'momo_number' => ['nullable', 'string', 'max:32'],
            'bank_name' => ['nullable', 'string', 'max:128'],
            'account_number' => ['nullable', 'string', 'max:64'],
            'account_name' => ['required', 'string', 'max:128'],
        ]);

        $details = $validated['payout_method'] === 'momo'
            ? [
                'momo_network' => $validated['momo_network'] ?? '',
                'momo_number' => $validated['momo_number'] ?? '',
                'account_name' => $validated['account_name'],
            ]
            : [
                'bank_name' => $validated['bank_name'] ?? '',
                'account_number' => $validated['account_number'] ?? '',
                'account_name' => $validated['account_name'],
            ];

        try {
            $this->agentWithdrawalService->requestWithdrawal(
                $request->user(),
                (string) $validated['amount'],
                $validated['agent_note'] ?? null,
                $validated['payout_method'],
                $details,
            );
        } catch (InvalidArgumentException $e) {
            return back()->withInput()->withErrors(['amount' => $e->getMessage()]);
        }

        return back()->with('status', __('Withdrawal request submitted.'));
    }
}
