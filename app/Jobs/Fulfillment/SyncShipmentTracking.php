<?php

namespace App\Jobs\Fulfillment;

use App\Models\Fulfillment\Shipment;
use App\Services\Tracking\TrackingSyncService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class SyncShipmentTracking implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 2;

    public int $timeout = 60;

    public function __construct(public string $shipmentId)
    {
        $this->onQueue('default');
    }

    public function handle(TrackingSyncService $service): void
    {
        $shipment = Shipment::find($this->shipmentId);
        if (! $shipment) {
            return;
        }

        try {
            $service->sync($shipment);
        } catch (\Throwable $e) {
            Log::warning('SyncShipmentTracking failed', [
                'shipment_id' => $this->shipmentId,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
