<?php

namespace App\Http\Controllers\Api\V1;

use App\Exceptions\InsufficientBalanceException;
use App\Http\Controllers\Controller;
use App\Models\Role;
use App\Services\OrderService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use InvalidArgumentException;

class ApiOrderController extends Controller
{
    public function __construct(
        private readonly OrderService $orderService,
    ) {}

    public function store(Request $request): JsonResponse
    {
        if ($request->user()->role?->slug !== Role::SLUG_BUYER) {
            return response()->json([
                'success' => false,
                'message' => 'Forbidden',
            ], 403);
        }

        $data = $request->validate([
            'network' => ['required', Rule::in(['MTN', 'Telecel', 'AirtelTigo'])],
            'phone_number' => ['required', 'string', 'regex:/^0[235]\d{8}$/'],
            'bundle_package_id' => ['required', 'integer', 'exists:bundle_packages,id'],
            'confirm' => ['accepted'],
        ]);

        try {
            $order = $this->orderService->placeOrder($request->user()->id, $data);
        } catch (InsufficientBalanceException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 400);
        } catch (InvalidArgumentException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 400);
        }

        return response()->json([
            'success' => true,
            'message' => 'Order placed',
            'data' => $order->load('bundlePackage')->toArray(),
        ], 201);
    }
}
