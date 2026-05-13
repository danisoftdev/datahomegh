<?php

namespace App\Http\Controllers\Agent;

use App\Http\Controllers\Controller;
use App\Models\Role;
use App\Models\User;
use App\Services\NotificationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AgentBuyerController extends Controller
{
    public function __construct(
        private readonly NotificationService $notificationService,
    ) {}

    public function index(Request $request): View
    {
        $buyers = User::query()
            ->with('role')
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
