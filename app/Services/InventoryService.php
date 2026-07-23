<?php

namespace App\Services;

use App\Models\Cart;
use App\Models\Inventory;
use App\Models\Order;
use App\Models\ProductVariant;
use App\Models\StockReservation;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Exception;

class InventoryService
{
    /**
     * Reserve inventory for all items in a cart during checkout.
     * Uses Redis locks to prevent race conditions during high concurrency.
     */
    public function reserveStock(Cart $cart, int $holdMinutes = 15): bool
    {
        return DB::transaction(function () use ($cart, $holdMinutes) {
            foreach ($cart->items as $item) {
                $variantId = $item->product_variant_id;
                $lockKey = "inventory:lock:variant:{$variantId}";

                $lock = Cache::lock($lockKey, 5);

                if (! $lock->get()) {
                    throw new Exception("High concurrency detected. Unable to lock inventory for SKU variant ID {$variantId}. Please try again.");
                }

                try {
                    $inventory = Inventory::where('product_variant_id', $variantId)
                        ->lockForUpdate()
                        ->first();

                    if (! $inventory) {
                        throw new Exception("Inventory record missing for variant ID {$variantId}.");
                    }

                    $available = $inventory->quantity_on_hand - $inventory->quantity_reserved;

                    if ($available < $item->quantity) {
                        throw new Exception("Insufficient stock for variant ID {$variantId}. Required: {$item->quantity}, Available: {$available}.");
                    }

                    // Increment reserved stock
                    $inventory->increment('quantity_reserved', $item->quantity);

                    // Create stock reservation tracker
                    StockReservation::create([
                        'cart_id' => $cart->id,
                        'product_variant_id' => $variantId,
                        'quantity' => $item->quantity,
                        'expires_at' => now()->addMinutes($holdMinutes),
                    ]);

                } finally {
                    $lock->release();
                }
            }

            return true;
        });
    }

    /**
     * Release expired reservations to restore available inventory count.
     */
    public function releaseExpiredReservations(): int
    {
        $expired = StockReservation::where('expires_at', '<=', now())->get();
        $releasedCount = 0;

        foreach ($expired as $reservation) {
            DB::transaction(function () use ($reservation) {
                $inventory = Inventory::where('product_variant_id', $reservation->product_variant_id)
                    ->lockForUpdate()
                    ->first();

                if ($inventory) {
                    $inventory->decrement('quantity_reserved', min($inventory->quantity_reserved, $reservation->quantity));
                }

                $reservation->delete();
            });

            $releasedCount++;
        }

        Log::info("Inventory Engine: Released {$releasedCount} expired stock reservations.");
        return $releasedCount;
    }

    /**
     * Permanently deduct inventory when order is paid & fulfilled.
     */
    public function fulfillOrderStock(Order $order): void
    {
        DB::transaction(function () use ($order) {
            foreach ($order->items as $item) {
                $inventory = Inventory::where('product_variant_id', $item->product_variant_id)
                    ->lockForUpdate()
                    ->first();

                if ($inventory) {
                    // Deduct from both on_hand and reserved
                    $inventory->decrement('quantity_on_hand', min($inventory->quantity_on_hand, $item->quantity));
                    $inventory->decrement('quantity_reserved', min($inventory->quantity_reserved, $item->quantity));
                }
            }

            // Remove associated cart reservations
            StockReservation::where('cart_id', $order->id)->delete();
        });

        Log::info("Inventory Engine: Order {$order->order_number} stock fulfilled successfully.");
    }
}
