<?php

namespace App\Jobs\Fulfillment;

use App\Services\Fulfillment\TrackingService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class CheckStaleShipments implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;

    public int $timeout = 60;

    public function __construct()
    {
        $this->onQueue('fulfillment');
    }

    public function handle(TrackingService $trackingService): void
    {
        $staleShipments = $trackingService->checkStaleShipments();

        if ($staleShipments->isEmpty()) {
            return;
        }

        Log::warning('Stale shipments detected', [
            'count' => $staleShipments->count(),
            'shipment_ids' => $staleShipments->pluck('id')->toArray(),
        ]);
    }
}
