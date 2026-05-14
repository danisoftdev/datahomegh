<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\Role;
use App\Models\User;
use App\Services\OrderService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use InvalidArgumentException;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AdminOrderController extends Controller
{
    public function __construct(
        private readonly OrderService $orderService,
    ) {}

    public function index(Request $request): View
    {
        $query = Order::query()->visibleToSupplier()->with(['user', 'agent', 'bundlePackage']);

        $this->applyOrderFilters($request, $query);

        $orders = $query->latest()->paginate(20)->withQueryString();

        $agents = User::query()
            ->whereHas('role', fn ($q) => $q->where('slug', Role::SLUG_AGENT))
            ->orderBy('username')
            ->get(['id', 'username', 'shop_name']);

        return view('admin.orders.index', [
            'orders' => $orders,
            'agents' => $agents,
        ]);
    }

    public function show(Order $order): View
    {
        $this->assertOrderVisibleToSupplier($order);

        $order->load(['user', 'agent', 'bundlePackage']);

        $histories = $order->orderStatusHistories()->with('changedBy')->orderBy('id')->get();

        return view('admin.orders.show', [
            'order' => $order,
            'histories' => $histories,
        ]);
    }

    public function updateStatus(Request $request, Order $order): RedirectResponse
    {
        $this->assertOrderVisibleToSupplier($order);

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

        $uniqueIds = array_values(array_unique($validated['order_ids']));
        $matched = Order::query()->visibleToSupplier()->whereIn('id', $uniqueIds)->count();
        if ($matched !== count($uniqueIds)) {
            return back()->withErrors(['order_ids' => __('One or more orders are not in your queue.')]);
        }

        try {
            $this->orderService->bulkUpdateStatus(
                $uniqueIds,
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
        $this->assertOrderVisibleToSupplier($order);

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

    public function export(Request $request): StreamedResponse
    {
        $query = Order::query()->visibleToSupplier()->with(['user', 'agent', 'bundlePackage']);
        $this->applyOrderFilters($request, $query);

        $filename = 'orders-'.now()->format('Y-m-d-His').'.csv';

        return response()->streamDownload(function () use ($query): void {
            $out = fopen('php://output', 'w');
            fputcsv($out, [
                'id', 'created_at', 'status', 'network', 'phone_number', 'user_id', 'username',
                'agent_id', 'bundle_package_id', 'bundle_name', 'amount',
            ]);

            $query->orderBy('id')->chunk(500, function ($orders) use ($out): void {
                foreach ($orders as $o) {
                    fputcsv($out, [
                        $o->id,
                        $o->created_at?->toIso8601String(),
                        $o->status,
                        $o->network,
                        $o->phone_number,
                        $o->user_id,
                        $o->user?->username,
                        $o->agent_id,
                        $o->bundle_package_id,
                        $o->bundlePackage?->name,
                        $o->amount,
                    ]);
                }
            });
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    private function applyOrderFilters(Request $request, $query): void
    {
        if ($request->filled('status')) {
            $query->where('status', $request->string('status'));
        }

        if ($request->filled('date_from')) {
            $query->whereDate('created_at', '>=', $request->date('date_from'));
        }

        if ($request->filled('date_to')) {
            $query->whereDate('created_at', '<=', $request->date('date_to'));
        }

        if ($request->filled('network')) {
            $query->where('network', $request->string('network'));
        }

        if ($request->filled('agent_id')) {
            $query->where('agent_id', (int) $request->input('agent_id'));
        }

        if ($request->filled('user_id')) {
            $query->where('user_id', (int) $request->input('user_id'));
        }

        if ($request->filled('phone')) {
            $phone = $request->string('phone');
            $query->where('phone_number', 'like', '%'.$phone.'%');
        }
    }

    private function assertOrderVisibleToSupplier(Order $order): void
    {
        abort_unless(
            Order::query()->visibleToSupplier()->whereKey($order->getKey())->exists(),
            404
        );
    }
}
