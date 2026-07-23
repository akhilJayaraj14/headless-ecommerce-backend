<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class OrderController extends Controller
{
    use ApiResponse;

    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        $orders = Order::with(['items.variant.product', 'payments'])
            ->where('user_id', $user->id)
            ->latest()
            ->paginate(10);

        return $this->successResponse($orders);
    }

    public function show(Request $request, string $id): JsonResponse
    {
        $order = Order::with(['items.variant.product', 'payments'])
            ->where('id', $id)
            ->first();

        if (! $order) {
            return $this->errorResponse('Order not found', 404);
        }

        return $this->successResponse($order);
    }
}
