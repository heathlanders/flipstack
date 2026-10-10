<?php

namespace App\Jobs\Fulfillment;

use App\Services\Fulfillment\TrackingService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class ProcessTrackingWebhook implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $timeout = 30;

    public function __construct(protected array $payload)
    {
        $this->onQueue('fulfillment');
    }

    public function handle(TrackingService $trackingService): void
    {
        try {
            $result = $this->payload['result'] ?? [];

            $trackingData = [
                'tracking_code' => $result['tracking_code'] ?? null,
                'status' => $result['status'] ?? null,
                'status_detail' => $result['status_detail'] ?? null,
                'est_delivery_date' => $result['est_delivery_date'] ?? null,
                'carrier' => $result['carrier'] ?? null,
                'shipment_id' => $result['shipment_id'] ?? null,
            ];

            $trackingService->processTrackingUpdate($trackingData);
        } catch (\Exception $e) {
            Log::error('Failed to process tracking webhook', [
                'error' => $e->getMessage(),
                'payload' => $this->payload,
            ]);
            throw $e;
        }
    }
}
