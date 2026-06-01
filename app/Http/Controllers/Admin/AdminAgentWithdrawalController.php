<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AgentWithdrawalRequest;
use App\Services\AgentWithdrawalService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use InvalidArgumentException;

class AdminAgentWithdrawalController extends Controller
{
    public function __construct(
        private readonly AgentWithdrawalService $agentWithdrawalService,
    ) {}

    public function index(Request $request): View
    {
        $status = $request->string('status')->toString();
        $query = AgentWithdrawalRequest::query()->with('agent')->latest();

        if ($status !== '' && in_array($status, [
            AgentWithdrawalRequest::STATUS_PENDING,
            AgentWithdrawalRequest::STATUS_PROCESSING,
            AgentWithdrawalRequest::STATUS_PAID,
            AgentWithdrawalRequest::STATUS_REJECTED,
        ], true)) {
            $query->where('status', $status);
        }

        $requests = $query->paginate(20)->withQueryString();

        return view('admin.withdrawals.index', [
            'requests' => $requests,
            'currentStatus' => $status,
            'minWithdrawal' => $this->agentWithdrawalService->minAmount(),
            'withdrawalFee' => $this->agentWithdrawalService->feeAmount(),
        ]);
    }

    public function show(AgentWithdrawalRequest $withdrawal): View
    {
        $withdrawal->load(['agent', 'processedBy']);

        return view('admin.withdrawals.show', compact('withdrawal'));
    }

    public function updateStatus(Request $request, AgentWithdrawalRequest $withdrawal): RedirectResponse
    {
        $validated = $request->validate([
            'status' => ['required', Rule::in([
                AgentWithdrawalRequest::STATUS_PROCESSING,
                AgentWithdrawalRequest::STATUS_PAID,
                AgentWithdrawalRequest::STATUS_REJECTED,
            ])],
            'admin_note' => ['nullable', 'string', 'max:2000'],
        ]);

        try {
            $this->agentWithdrawalService->updateStatus(
                $withdrawal,
                $validated['status'],
                $request->user(),
                $validated['admin_note'] ?? null,
            );
        } catch (InvalidArgumentException $e) {
            return back()->withErrors(['status' => $e->getMessage()]);
        }

        return redirect()->route('admin.withdrawals.show', $withdrawal->fresh())->with('status', __('Withdrawal updated.'));
    }
}
