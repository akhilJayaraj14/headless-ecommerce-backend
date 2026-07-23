<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Cart;
use App\Models\CartItem;
use App\Models\ProductVariant;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class CartController extends Controller
{
    use ApiResponse;

    public function show(Request $request): JsonResponse
    {
        $cart = $this->getOrCreateCart($request);
        $cart->load('items.variant.product');

        return $this->successResponse([
            'cart' => $cart,
            'summary' => [
                'total_items' => $cart->items->sum('quantity'),
                'subtotal' => $cart->calculateTotal(),
            ],
        ]);
    }

    public function addItem(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'product_variant_id' => 'required|exists:product_variants,id',
            'quantity' => 'required|integer|min:1',
        ]);

        $cart = $this->getOrCreateCart($request);
        $variant = ProductVariant::findOrFail($validated['product_variant_id']);

        if ($variant->totalAvailableStock() < $validated['quantity']) {
            return $this->errorResponse("Insufficient stock for SKU {$variant->sku}.", 422);
        }

        $cartItem = CartItem::where('cart_id', $cart->id)
            ->where('product_variant_id', $variant->id)
            ->first();

        if ($cartItem) {
            $cartItem->increment('quantity', $validated['quantity']);
        } else {
            CartItem::create([
                'cart_id' => $cart->id,
                'product_variant_id' => $variant->id,
                'quantity' => $validated['quantity'],
                'unit_price' => $variant->price,
            ]);
        }

        $cart->load('items.variant.product');

        return $this->successResponse([
            'cart' => $cart,
            'summary' => [
                'total_items' => $cart->items->sum('quantity'),
                'subtotal' => $cart->calculateTotal(),
            ],
        ], 'Item added to cart');
    }

    public function removeItem(Request $request, int $itemId): JsonResponse
    {
        $cart = $this->getOrCreateCart($request);
        CartItem::where('cart_id', $cart->id)->where('id', $itemId)->delete();

        $cart->load('items.variant.product');

        return $this->successResponse([
            'cart' => $cart,
            'summary' => [
                'total_items' => $cart->items->sum('quantity'),
                'subtotal' => $cart->calculateTotal(),
            ],
        ], 'Item removed from cart');
    }

    protected function getOrCreateCart(Request $request): Cart
    {
        $cartId = $request->header('X-Cart-ID') ?: $request->input('cart_id');
        $userId = $request->user()?->id;

        if ($cartId) {
            $cart = Cart::find($cartId);
            if ($cart && $cart->status === 'active') {
                return $cart;
            }
        }

        return Cart::create([
            'id' => (string) Str::uuid(),
            'user_id' => $userId,
            'status' => 'active',
            'expires_at' => now()->addDays(7),
        ]);
    }
}
