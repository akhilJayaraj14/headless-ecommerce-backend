<?php

namespace App\Jobs;

use App\Services\InventoryService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

class ReleaseExpiredReservationsJob implements ShouldQueue
{
    use Queueable;

    /**
     * Execute the job.
     */
    public function handle(InventoryService $inventoryService): void
    {
        Log::info("ReleaseExpiredReservationsJob: Running inventory release cleanup...");
        $released = $inventoryService->releaseExpiredReservations();
        Log::info("ReleaseExpiredReservationsJob: Finished. Released {$released} expired reservations.");
    }
}
