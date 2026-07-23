<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Cart;
use App\Models\Order;
use App\Models\OrderItem;
use App\Services\InventoryService;
use App\Services\PaymentService;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Exception;

class CheckoutController extends Controller
{
    use ApiResponse;

    public function __construct(
        protected InventoryService $inventoryService,
        protected PaymentService $paymentService
    ) {
    }

    public function process(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'cart_id' => 'required|uuid|exists:carts,id',
            'shipping_address' => 'required|array',
            'billing_address' => 'required|array',
            'payment_method' => 'nullable|string|in:stripe,mock',
        ]);

        $cart = Cart::with('items.variant.product')->findOrFail($validated['cart_id']);

        if ($cart->items->isEmpty()) {
            return $this->errorResponse('Cannot checkout an empty cart', 400);
        }

        try {
            // 1. Lock & Reserve Stock using Redis Distributed Lock Engine
            $this->inventoryService->reserveStock($cart, 15);

            // 2. Calculate Order Total
            $totalAmount = $cart->calculateTotal();
            $orderNumber = 'ORD-' . strtoupper(Str::random(8));

            // 3. Create Order in Pending State
            $order = Order::create([
                'id' => (string) Str::uuid(),
                'user_id' => $request->user()?->id ?: $cart->user_id,
                'order_number' => $orderNumber,
                'status' => 'pending',
                'total_amount' => $totalAmount,
                'shipping_address' => $validated['shipping_address'],
                'billing_address' => $validated['billing_address'],
                'payment_status' => 'pending',
                'payment_method' => $validated['payment_method'] ?? 'stripe',
            ]);

            // 4. Create Order Items
            foreach ($cart->items as $item) {
                OrderItem::create([
                    'order_id' => $order->id,
                    'product_variant_id' => $item->product_variant_id,
                    'sku' => $item->variant->sku,
                    'product_name' => $item->variant->product->name . ' - ' . $item->variant->name,
                    'quantity' => $item->quantity,
                    'unit_price' => $item->unit_price,
                    'total_price' => $item->unit_price * $item->quantity,
                ]);
            }

            // 5. Update Cart status
            $cart->update(['status' => 'converted']);

            // 6. Generate PaymentIntent via PaymentService
            $paymentIntent = $this->paymentService->createPaymentIntent(
                $order,
                $validated['payment_method'] ?? 'stripe'
            );

            return $this->successResponse([
                'order' => $order->load('items'),
                'payment' => $paymentIntent,
                'reservation_expires_at' => now()->addMinutes(15)->toIso8601String(),
            ], 'Order initialized and stock reserved for 15 minutes', 201);

        } catch (Exception $e) {
            return $this->errorResponse($e->getMessage(), 422);
        }
    }
}
