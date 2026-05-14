<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Models\Role;
use App\Models\User;
use App\Services\OrderService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use InvalidArgumentException;

class OrderController extends Controller
{
    public function __construct(
        private readonly OrderService $orderService,
    ) {}

    public function show(Request $request, Order $order): View
    {
        $this->authorizeOrderAccess($request->user(), $order);

        $order->load(['bundlePackage', 'user']);

        $historyQuery = $order->orderStatusHistories()
            ->with('changedBy')
            ->orderBy('id');

        if ($request->user()->role?->slug === Role::SLUG_BUYER) {
            $historyQuery->where('visible_to_buyer', true);
        }

        return view('orders.show', [
            'order' => $order,
            'histories' => $historyQuery->get(),
        ]);
    }

    public function updateStatus(Request $request, Order $order): RedirectResponse
    {
        $this->authorizeStaffOrder($request->user(), $order);

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

    public function bulkUpdateStatus(Request $request): RedirectResponse
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

        foreach ($validated['order_ids'] as $orderId) {
            $order = Order::query()->findOrFail($orderId);
            $this->authorizeStaffOrder($request->user(), $order);
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

    private function authorizeOrderAccess(User $user, Order $order): void
    {
        if ($user->role?->slug === Role::SLUG_SUPPLIER) {
            abort_unless(
                Order::query()->visibleToSupplier()->whereKey($order->getKey())->exists(),
                403
            );

            return;
        }

        if ($user->role?->slug === Role::SLUG_BUYER) {
            abort_unless($order->user_id === $user->id, 403);

            return;
        }

        if ($user->role?->slug === Role::SLUG_AGENT) {
            $order->loadMissing('user');
            abort_unless((int) $order->user?->agent_id === (int) $user->id, 403);

            return;
        }

        abort(403);
    }

    private function authorizeStaffOrder(User $user, Order $order): void
    {
        if ($user->role?->slug === Role::SLUG_SUPPLIER) {
            abort_unless(
                Order::query()->visibleToSupplier()->whereKey($order->getKey())->exists(),
                403
            );

            return;
        }

        if ($user->role?->slug === Role::SLUG_AGENT) {
            $order->loadMissing('user');
            if ((int) $order->user?->agent_id === (int) $user->id) {
                return;
            }
        }

        abort(403);
    }
}
