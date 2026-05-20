<?php

namespace App\Http\Controllers\Agent;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\Role;
use App\Services\OrderService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use InvalidArgumentException;

class AgentOrderController extends Controller
{
    public function __construct(
        private readonly OrderService $orderService,
    ) {}

    public function index(Request $request): View
    {
        $agentId = (int) $request->user()->id;

        $query = Order::query()
            ->where('agent_id', $agentId)
            ->with(['user', 'bundlePackage']);

        if ($request->filled('status')) {
            $query->where('status', $request->string('status'));
        }

        if ($request->filled('phone')) {
            $query->where('phone_number', 'like', '%'.$request->string('phone').'%');
        }

        $orders = $query->latest()->paginate(20)->withQueryString();

        return view('agent.orders.index', compact('orders'));
    }

    public function show(Request $request, Order $order): View
    {
        $this->assertAgentOrder($request, $order);

        $order->load(['user', 'bundlePackage']);

        $histories = $order->orderStatusHistories()
            ->with('changedBy')
            ->orderBy('id')
            ->get();

        return view('agent.orders.show', [
            'order' => $order,
            'histories' => $histories,
        ]);
    }

    public function updateStatus(Request $request, Order $order): RedirectResponse
    {
        $this->assertAgentOrder($request, $order);

        $validated = $request->validate([
            'status' => ['required', Rule::in(['PROCESSING', 'SENT', 'FAILED', 'REFUNDED'])],
            'note' => ['nullable', 'string', 'max:2000'],
            'visible_to_buyer' => ['sometimes', 'boolean'],
        ]);

        try {
            $this->orderService->updateStatus(
                $order->id,
                $validated['status'],
                (int) $request->user()->id,
                $validated['note'] ?? null,
                $request->boolean('visible_to_buyer'),
            );
        } catch (InvalidArgumentException $e) {
            return back()->withErrors(['status' => $e->getMessage()]);
        }

        return back()->with('status', __('Order updated.'));
    }

    public function bulkUpdate(Request $request): RedirectResponse
    {
        $filtered = array_values(array_filter(
            (array) $request->input('order_ids', []),
            fn ($v) => $v !== null && $v !== ''
        ));

        $request->merge(['order_ids' => $filtered]);

        $validated = $request->validate([
            'order_ids' => ['required', 'array', 'min:1'],
            'order_ids.*' => ['integer', 'exists:orders,id'],
            'status' => ['required', Rule::in(['PROCESSING', 'SENT', 'FAILED', 'REFUNDED'])],
            'note' => ['nullable', 'string', 'max:2000'],
            'visible_to_buyer' => ['sometimes', 'boolean'],
        ]);

        $agentId = (int) $request->user()->id;

        foreach ($validated['order_ids'] as $orderId) {
            $order = Order::query()->findOrFail($orderId);
            abort_unless((int) $order->agent_id === $agentId, 403);
        }

        try {
            $this->orderService->bulkUpdateStatus(
                $validated['order_ids'],
                $validated['status'],
                (int) $request->user()->id,
                $validated['note'] ?? null,
                $request->boolean('visible_to_buyer'),
            );
        } catch (InvalidArgumentException $e) {
            return back()->withErrors(['status' => $e->getMessage()]);
        }

        return back()->with('status', __('Orders updated.'));
    }

    public function addNote(Request $request, Order $order): RedirectResponse
    {
        $this->assertAgentOrder($request, $order);

        $validated = $request->validate([
            'note' => ['required', 'string', 'max:5000'],
            'visible_to_buyer' => ['sometimes', 'boolean'],
        ]);

        $this->orderService->addOrderNote(
            $order->id,
            (int) $request->user()->id,
            $validated['note'],
            $request->boolean('visible_to_buyer'),
        );

        return back()->with('status', __('Note added.'));
    }

    /**
     * Agent cancels their own catalogue purchase (order.user_id = agent), not buyer-linked orders under their shop.
     */
    public function cancelPurchaserOwn(Request $request, Order $order): RedirectResponse
    {
        $this->assertAgentOrder($request, $order);

        abort_unless((int) $order->user_id === (int) $request->user()->id, 403);

        try {
            $this->orderService->cancelPendingOrderByPurchaser($request->user(), $order->fresh());
        } catch (InvalidArgumentException $e) {
            return back()->withErrors(['cancel' => $e->getMessage()]);
        }

        return back()->with('status', __('Order cancelled. Your wallet was refunded automatically.'));
    }

    private function assertAgentOrder(Request $request, Order $order): void
    {
        abort_unless($request->user()->role?->slug === Role::SLUG_AGENT, 403);
        abort_unless((int) $order->agent_id === (int) $request->user()->id, 403);
    }
}
