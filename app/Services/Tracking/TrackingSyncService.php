<?php

namespace App\Services\Tracking;

use App\Models\Fulfillment\Order;
use App\Models\Fulfillment\Shipment;
use App\Models\Fulfillment\ShipmentTrackingEvent;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class TrackingSyncService
{
    public function __construct(private TrackingProviderRegistry $registry) {}

    /**
     * Pull tracking events for a single shipment, persist new ones,
     * and roll the parent shipment + order forward as appropriate.
     *
     * Returns count of newly-persisted events.
     */
    public function sync(Shipment $shipment): int
    {
        $provider = $this->registry->resolve($shipment);
        if (! $provider) {
            return 0;
        }

        try {
            $result = $provider->fetch($shipment);
        } catch (\Throwable $e) {
            Log::warning('tracking sync failed', [
                'shipment_id' => $shipment->id,
                'provider' => $provider->name(),
                'error' => $e->getMessage(),
            ]);

            return 0;
        }

        // Only mark synced when we got useful data. An empty result (e.g. eBay
        // hasn't populated ActualDeliveryTime yet) must NOT bump
        // last_tracking_synced_at — otherwise the 25-min staleness gate in
        // SyncAllActiveShipmentTracking would block retries until the cache
        // expires. Shipments stay eligible on the next cron tick.
        if ($result->isEmpty()) {
            return 0;
        }

        $shipment->forceFill([
            'last_tracking_synced_at' => now(),
            'tracking_provider' => $provider->name(),
        ])->save();

        $created = 0;
        DB::transaction(function () use ($shipment, $result, $provider, &$created) {
            foreach ($result->events as $event) {
                $exists = ShipmentTrackingEvent::where('shipment_id', $shipment->id)
                    ->where('source', $provider->name())
                    ->where('source_event_id', $event['source_event_id'] ?? null)
                    ->exists();

                if ($exists) {
                    continue;
                }

                ShipmentTrackingEvent::create([
                    'shipment_id' => $shipment->id,
                    'source' => $provider->name(),
                    'source_event_id' => $event['source_event_id'] ?? null,
                    'status' => $event['status'],
                    'description' => $event['description'] ?? null,
                    'city' => $event['city'] ?? null,
                    'state' => $event['state'] ?? null,
                    'postal_code' => $event['postal_code'] ?? null,
                    'country' => $event['country'] ?? null,
                    'occurred_at' => $event['occurred_at'],
                    'raw' => $event['raw'] ?? null,
                ]);

                $created++;
            }

            $this->rollUpShipmentStatus($shipment, $result);
        });

        return $created;
    }

    protected function rollUpShipmentStatus(Shipment $shipment, TrackingResult $result): void
    {
        $update = [];

        if ($result->estimatedDeliveryAt && ! $shipment->estimated_delivery_date) {
            $update['estimated_delivery_date'] = $result->estimatedDeliveryAt;
        }

        if ($result->deliveredAt && ! $shipment->actual_delivery_date) {
            $update['actual_delivery_date'] = $result->deliveredAt;
        }

        $statusMap = [
            ShipmentTrackingEvent::STATUS_PRE_TRANSIT => Shipment::STATUS_LABEL_PURCHASED,
            ShipmentTrackingEvent::STATUS_IN_TRANSIT => Shipment::STATUS_IN_TRANSIT,
            ShipmentTrackingEvent::STATUS_OUT_FOR_DELIVERY => Shipment::STATUS_OUT_FOR_DELIVERY,
            ShipmentTrackingEvent::STATUS_DELIVERED => Shipment::STATUS_DELIVERED,
            ShipmentTrackingEvent::STATUS_RETURNED => Shipment::STATUS_RETURNED_TO_SENDER,
            ShipmentTrackingEvent::STATUS_FAILURE => Shipment::STATUS_FAILED,
        ];

        if ($result->latestStatus && isset($statusMap[$result->latestStatus])) {
            $newStatus = $statusMap[$result->latestStatus];
            // Don't downgrade — once shipped/in_transit, never go back to pending.
            if ($this->statusRank($newStatus) > $this->statusRank($shipment->status)) {
                $update['status'] = $newStatus;
            }
        }

        if (! empty($update)) {
            $shipment->forceFill($update)->save();
        }

        if ($result->latestStatus === ShipmentTrackingEvent::STATUS_DELIVERED && $shipment->order) {
            if ($shipment->order->status !== Order::STATUS_DELIVERED) {
                $shipment->order->forceFill([
                    'status' => Order::STATUS_DELIVERED,
                    'delivered_at' => $result->deliveredAt ?? now(),
                ])->save();
            }
        } elseif ($result->latestStatus === ShipmentTrackingEvent::STATUS_IN_TRANSIT && $shipment->order
            && in_array($shipment->order->status, [Order::STATUS_SHIPPED], true)) {
            $shipment->order->forceFill(['status' => Order::STATUS_IN_TRANSIT])->save();
        }
    }

    protected function statusRank(?string $status): int
    {
        return match ($status) {
            Shipment::STATUS_PENDING => 0,
            Shipment::STATUS_LABEL_PURCHASED => 1,
            Shipment::STATUS_PACKED => 2,
            Shipment::STATUS_SHIPPED => 3,
            Shipment::STATUS_IN_TRANSIT => 4,
            Shipment::STATUS_OUT_FOR_DELIVERY => 5,
            Shipment::STATUS_DELIVERED => 6,
            Shipment::STATUS_RETURNED_TO_SENDER, Shipment::STATUS_FAILED, Shipment::STATUS_CANCELLED => 7,
            default => 0,
        };
    }
}
