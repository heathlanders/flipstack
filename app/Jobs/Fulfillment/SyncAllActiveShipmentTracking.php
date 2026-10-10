<?php

namespace App\Jobs\Fulfillment;

use App\Models\Fulfillment\Shipment;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class SyncAllActiveShipmentTracking implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;

    public int $timeout = 300;

    public function __construct()
    {
        $this->onQueue('default');
    }

    public function handle(): void
    {
        $cutoff = now()->subDays(45);
        $stalenessCutoff = now()->subMinutes(25);

        Shipment::query()
            ->whereNotNull('tracking_number')
            ->whereNotIn('status', [
                Shipment::STATUS_DELIVERED,
                Shipment::STATUS_RETURNED_TO_SENDER,
                Shipment::STATUS_CANCELLED,
            ])
            ->where(function ($q) use ($cutoff) {
                $q->where('shipped_at', '>=', $cutoff)
                    ->orWhereNull('shipped_at');
            })
            ->where(function ($q) use ($stalenessCutoff) {
                $q->whereNull('last_tracking_synced_at')
                    ->orWhere('last_tracking_synced_at', '<', $stalenessCutoff);
            })
            ->orderBy('last_tracking_synced_at')
            ->limit(500)
            ->get(['id'])
            ->each(function (Shipment $shipment) {
                SyncShipmentTracking::dispatch($shipment->id);
            });
    }
}
