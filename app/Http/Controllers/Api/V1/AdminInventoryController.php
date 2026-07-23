<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Inventory;
use App\Models\StockReservation;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AdminInventoryController extends Controller
{
    use ApiResponse;

    public function index(): JsonResponse
    {
        $inventories = Inventory::with(['variant.product'])
            ->get()
            ->map(function ($inv) {
                return [
                    'id' => $inv->id,
                    'location' => $inv->location_name,
                    'sku' => $inv->variant->sku,
                    'product_name' => $inv->variant->product->name ?? 'N/A',
                    'variant_name' => $inv->variant->name,
                    'quantity_on_hand' => $inv->quantity_on_hand,
                    'quantity_reserved' => $inv->quantity_reserved,
                    'available_stock' => $inv->availableQuantity(),
                ];
            });

        return $this->successResponse([
            'inventories' => $inventories,
            'active_reservations_count' => StockReservation::count(),
        ]);
    }

    public function updateStock(Request $request, int $id): JsonResponse
    {
        $validated = $request->validate([
            'quantity_on_hand' => 'required|integer|min:0',
        ]);

        $inventory = Inventory::findOrFail($id);
        $inventory->update(['quantity_on_hand' => $validated['quantity_on_hand']]);

        return $this->successResponse($inventory, 'Inventory updated successfully');
    }
}
