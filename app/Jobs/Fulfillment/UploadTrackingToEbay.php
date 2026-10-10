<?php

namespace App\Jobs\Fulfillment;

use App\Models\Fulfillment\Shipment;
use App\Models\User\User;
use App\Notifications\Fulfillment\TrackingUploadFailedNotification;
use App\Services\Fulfillment\TrackingService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class UploadTrackingToEbay implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $timeout = 30;

    public int $backoff = 60;

    public function __construct(protected Shipment $shipment)
    {
        $this->onQueue('ebay');
    }

    public function handle(TrackingService $trackingService): void
    {
        $result = $trackingService->uploadTrackingToMarketplace($this->shipment);

        if (! $result) {
            Log::warning('Failed to upload tracking to eBay', [
                'shipment_id' => $this->shipment->id,
                'tracking_number' => $this->shipment->tracking_number,
            ]);

            if ($this->attempts() < $this->tries) {
                $this->release(300);
            }
        }
    }

    public function failed(?\Throwable $exception): void
    {
        Log::error('UploadTrackingToEbay: Job failed permanently', [
            'shipment_id' => $this->shipment->id,
            'error' => $exception?->getMessage(),
        ]);

        $order = $this->shipment->order;
        if ($order) {
            $admins = User::where('organization_id', $order->organization_id)
                ->whereHas('roles', fn ($q) => $q->whereIn('name', ['admin', 'owner']))
                ->get();

            $notification = new TrackingUploadFailedNotification(
                marketplace: 'eBay',
                orderNumber: $order->order_number ?? "#{$order->id}",
                errorMessage: $exception?->getMessage() ?? 'Unknown error',
                orderId: $order->id,
            );

            foreach ($admins as $admin) {
                $admin->notify($notification);
            }
        }
    }
}
