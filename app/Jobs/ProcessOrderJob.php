<?php

namespace App\Jobs;

use App\Models\Order;
use App\Services\InventoryService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

class ProcessOrderJob implements ShouldQueue
{
    use Queueable;

    /**
     * Create a new job instance.
     */
    public function __construct(public string $orderId)
    {
    }

    /**
     * Execute the job.
     */
    public function handle(InventoryService $inventoryService): void
    {
        $order = Order::find($this->orderId);

        if (! $order) {
            Log::error("ProcessOrderJob Error: Order {$this->orderId} not found.");
            return;
        }

        Log::info("ProcessOrderJob Started: Fulfilling inventory for Order #{$order->order_number}");

        // 1. Fulfill Inventory Stock
        $inventoryService->fulfillOrderStock($order);

        // 2. Update Order status to completed/processing
        $order->update(['status' => 'processing']);

        Log::info("ProcessOrderJob Completed: Order #{$order->order_number} successfully processed & queued for dispatch.");
    }
}
