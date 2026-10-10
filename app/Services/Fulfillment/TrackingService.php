<?php

namespace App\Services\Fulfillment;

use App\Jobs\Fulfillment\UploadTrackingToAmazon;
use App\Jobs\Fulfillment\UploadTrackingToEbay;
use App\Jobs\Fulfillment\UploadTrackingToShopify;
use App\Models\Fulfillment\Order;
use App\Models\Fulfillment\Shipment;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

class TrackingService
{
    protected FulfillmentAuditService $auditService;

    public function __construct(FulfillmentAuditService $auditService)
    {
        $this->auditService = $auditService;
    }

    public function processTrackingUpdate(array $webhookPayload): void
    {
        $trackingCode = $webhookPayload['tracking_code'] ?? null;
        $easypostShipmentId = $webhookPayload['shipment_id'] ?? null;
        $status = $webhookPayload['status'] ?? null;

        if (! $trackingCode && ! $easypostShipmentId) {
            Log::warning('Tracking webhook missing identifiers', $webhookPayload);

            return;
        }

        $shipment = Shipment::query()
            ->when($easypostShipmentId, fn ($q) => $q->where('easypost_shipment_id', $easypostShipmentId))
            ->when(! $easypostShipmentId && $trackingCode, fn ($q) => $q->where('tracking_number', $trackingCode))
            ->first();

        if (! $shipment) {
            Log::info('Tracking update for unknown shipment', [
                'tracking_code' => $trackingCode,
                'easypost_shipment_id' => $easypostShipmentId,
            ]);

            return;
        }

        $this->updateShipmentStatus($shipment, $status, $webhookPayload);
    }

    public function updateShipmentStatus(Shipment $shipment, string $status, array $details = []): void
    {
        $mappedStatus = $this->mapTrackingStatus($status);
        $previousStatus = $shipment->status;

        if ($previousStatus === $mappedStatus) {
            return;
        }

        $updateData = ['status' => $mappedStatus];

        if ($mappedStatus === Shipment::STATUS_DELIVERED) {
            $updateData['actual_delivery_date'] = now()->toDateString();
            $this->handleDelivery($shipment);
        }

        $shipment->update($updateData);

        // Update parent order status
        $this->syncOrderStatus($shipment->order);

        Log::info('Shipment status updated', [
            'shipment_id' => $shipment->id,
            'from' => $previousStatus,
            'to' => $mappedStatus,
            'tracking' => $shipment->tracking_number,
        ]);
    }

    public function uploadTrackingToMarketplace(Shipment $shipment): bool
    {
        if ($shipment->tracking_uploaded_to_marketplace) {
            return true;
        }

        $order = $shipment->order;

        if (! $order->credential_id) {
            return false;
        }

        try {
            $result = match ($order->marketplace) {
                'ebay' => $this->uploadTrackingToEbay($order),
                'shopify' => $this->uploadTrackingToShopify($order),
                'amazon' => $this->uploadTrackingToAmazon($order),
                default => false,
            };

            if ($result) {
                $shipment->update([
                    'tracking_uploaded_to_marketplace' => true,
                    'tracking_uploaded_at' => now(),
                ]);
            }

            return $result;
        } catch (\Exception $e) {
            Log::error('Failed to upload tracking to marketplace', [
                'shipment_id' => $shipment->id,
                'order_id' => $order->id,
                'marketplace' => $order->marketplace,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    public function dispatchTrackingUpload(Shipment $shipment): void
    {
        $order = $shipment->order;

        if (! $order->credential_id || $shipment->tracking_uploaded_to_marketplace) {
            return;
        }

        match ($order->marketplace) {
            'ebay' => UploadTrackingToEbay::dispatch($shipment),
            'shopify' => UploadTrackingToShopify::dispatch($shipment),
            'amazon' => UploadTrackingToAmazon::dispatch($shipment),
            default => null,
        };
    }

    public function retryFailedTrackingUploads(): int
    {
        $shipments = Shipment::whereNotNull('tracking_number')
            ->where('tracking_uploaded_to_marketplace', false)
            ->whereHas('order', fn ($q) => $q->whereIn('marketplace', ['ebay', 'shopify', 'amazon']))
            ->with('order')
            ->get();

        $dispatched = 0;

        foreach ($shipments as $shipment) {
            $this->dispatchTrackingUpload($shipment);
            $dispatched++;
        }

        return $dispatched;
    }

    protected function uploadTrackingToEbay(Order $order): bool
    {
        $syncService = new OrderSyncService($order->credential);

        return $syncService->uploadTracking($order);
    }

    protected function uploadTrackingToShopify(Order $order): bool
    {
        $syncService = new ShopifyOrderSyncService($order->credential);

        return $syncService->uploadTracking($order);
    }

    protected function uploadTrackingToAmazon(Order $order): bool
    {
        $syncService = new AmazonOrderSyncService($order->credential);

        return $syncService->uploadTracking($order);
    }

    public function checkStaleShipments(): Collection
    {
        return Shipment::whereIn('status', [Shipment::STATUS_SHIPPED, Shipment::STATUS_IN_TRANSIT])
            ->where('shipped_at', '<', now()->subDays(7))
            ->whereNull('actual_delivery_date')
            ->get();
    }

    public function processShipStationUpdate(array $payload): void
    {
        $shipment = $payload['shipment'] ?? null;
        $status = $payload['status'] ?? null;

        if (! $shipment || ! $status) {
            return;
        }

        $mappedStatus = $this->mapShipStationStatus($status);
        $this->updateShipmentStatus($shipment, $mappedStatus);
    }

    public function processShippingEasyUpdate(array $payload): void
    {
        $shipment = $payload['shipment'] ?? null;
        $status = $payload['status'] ?? null;

        if (! $shipment || ! $status) {
            return;
        }

        $mappedStatus = $this->mapShippingEasyStatus($status);
        $this->updateShipmentStatus($shipment, $mappedStatus);
    }

    protected function mapShippingEasyStatus(string $shippingEasyStatus): string
    {
        return match (strtolower($shippingEasyStatus)) {
            'shipment.shipped', 'shipped' => Shipment::STATUS_SHIPPED,
            'in_transit' => Shipment::STATUS_IN_TRANSIT,
            'out_for_delivery' => Shipment::STATUS_OUT_FOR_DELIVERY,
            'shipment.delivered', 'delivered' => Shipment::STATUS_DELIVERED,
            'returned' => Shipment::STATUS_RETURNED_TO_SENDER,
            'exception', 'failed' => Shipment::STATUS_FAILED,
            default => Shipment::STATUS_IN_TRANSIT,
        };
    }

    protected function mapShipStationStatus(string $shipStationStatus): string
    {
        return match (strtolower($shipStationStatus)) {
            'shipped' => Shipment::STATUS_SHIPPED,
            'in_transit' => Shipment::STATUS_IN_TRANSIT,
            'out_for_delivery' => Shipment::STATUS_OUT_FOR_DELIVERY,
            'delivered' => Shipment::STATUS_DELIVERED,
            'returned' => Shipment::STATUS_RETURNED_TO_SENDER,
            'exception', 'failed' => Shipment::STATUS_FAILED,
            default => Shipment::STATUS_IN_TRANSIT,
        };
    }

    protected function mapTrackingStatus(string $easypostStatus): string
    {
        return match ($easypostStatus) {
            'pre_transit' => Shipment::STATUS_SHIPPED,
            'in_transit' => Shipment::STATUS_IN_TRANSIT,
            'out_for_delivery' => Shipment::STATUS_OUT_FOR_DELIVERY,
            'delivered' => Shipment::STATUS_DELIVERED,
            'return_to_sender' => Shipment::STATUS_RETURNED_TO_SENDER,
            'failure' => Shipment::STATUS_FAILED,
            default => Shipment::STATUS_IN_TRANSIT,
        };
    }

    protected function handleDelivery(Shipment $shipment): void
    {
        $order = $shipment->order;

        // Check if all shipments for this order are delivered
        $allDelivered = $order->shipments()
            ->where('id', '!=', $shipment->id)
            ->whereNotIn('status', [Shipment::STATUS_DELIVERED, Shipment::STATUS_CANCELLED])
            ->doesntExist();

        if ($allDelivered) {
            $order->update([
                'status' => Order::STATUS_DELIVERED,
                'delivered_at' => now(),
            ]);
        }
    }

    protected function syncOrderStatus(Order $order): void
    {
        $order->refresh();

        $shipments = $order->shipments;

        if ($shipments->isEmpty()) {
            return;
        }

        $allDelivered = $shipments->every(fn ($s) => in_array($s->status, [Shipment::STATUS_DELIVERED, Shipment::STATUS_CANCELLED]));
        $anyInTransit = $shipments->contains(fn ($s) => in_array($s->status, [Shipment::STATUS_IN_TRANSIT, Shipment::STATUS_OUT_FOR_DELIVERY]));
        $anyShipped = $shipments->contains(fn ($s) => $s->status === Shipment::STATUS_SHIPPED);

        if ($allDelivered && $order->status !== Order::STATUS_DELIVERED) {
            $order->update(['status' => Order::STATUS_DELIVERED, 'delivered_at' => now()]);
        } elseif ($anyInTransit && $order->status !== Order::STATUS_IN_TRANSIT) {
            $order->update(['status' => Order::STATUS_IN_TRANSIT]);
        } elseif ($anyShipped && ! in_array($order->status, [Order::STATUS_IN_TRANSIT, Order::STATUS_DELIVERED])) {
            $order->update(['status' => Order::STATUS_SHIPPED, 'shipped_at' => $order->shipped_at ?? now()]);
        }
    }
}
