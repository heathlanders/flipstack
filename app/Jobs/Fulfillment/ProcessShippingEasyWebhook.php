<?php

namespace App\Jobs\Fulfillment;

use App\Models\Fulfillment\Shipment;
use App\Services\Fulfillment\TrackingService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class ProcessShippingEasyWebhook implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $timeout = 30;

    public function __construct(
        protected string $eventType,
        protected array $payload,
        protected ?int $organizationId = null
    ) {
        $this->onQueue('fulfillment');
    }

    public function handle(TrackingService $trackingService): void
    {
        try {
            match ($this->eventType) {
                'shipment.shipped' => $this->handleTrackingEvent($trackingService),
                'shipment.cancelled' => $this->handleCancelled(),
                'label.purchased' => $this->handleLabelPurchased(),
                'label.failed' => $this->handleLabelFailed(),
                default => Log::info('Unhandled ShippingEasy event', ['event_type' => $this->eventType]),
            };
        } catch (\Exception $e) {
            Log::error('Failed to process ShippingEasy webhook', [
                'error' => $e->getMessage(),
                'event_type' => $this->eventType,
                'org_id' => $this->organizationId,
            ]);
            throw $e;
        }
    }

    /**
     * Extract shipment data from the payload.
     * SE callback sends a top-level "shipment" object.
     */
    protected function getShipmentData(): array
    {
        return $this->payload['shipment'] ?? $this->payload['data'] ?? $this->payload;
    }

    protected function findShipment(): ?Shipment
    {
        $shipmentData = $this->getShipmentData();

        // SE callback: shipment.id is the SE shipment ID, orders are nested
        $seShipmentId = (string) ($shipmentData['id'] ?? $shipmentData['shipment_id'] ?? '');
        $trackingNumber = $shipmentData['tracking_number'] ?? null;

        // Extract SE order ID from nested orders array (actual SE format) or flat field
        $seOrderId = '';
        if (! empty($shipmentData['orders'][0]['id'])) {
            $seOrderId = (string) $shipmentData['orders'][0]['id'];
        } elseif (! empty($shipmentData['order_id'])) {
            $seOrderId = (string) $shipmentData['order_id'];
        }

        $baseQuery = Shipment::query();
        if ($this->organizationId) {
            $baseQuery->where('organization_id', $this->organizationId);
        }

        // Try matching in priority order: shipment ID -> order ID -> tracking number
        $shipment = null;

        if ($seShipmentId) {
            $shipment = (clone $baseQuery)->where('shippingeasy_shipment_id', $seShipmentId)->first();
        }

        if (! $shipment && $seOrderId) {
            $shipment = (clone $baseQuery)->where('shippingeasy_order_id', $seOrderId)->first();
        }

        if (! $shipment && $trackingNumber) {
            $shipment = (clone $baseQuery)->where('tracking_number', $trackingNumber)->first();
        }

        if (! $shipment) {
            Log::info('ShippingEasy webhook for unknown shipment', [
                'tracking_number' => $trackingNumber,
                'shippingeasy_order_id' => $seOrderId,
                'shippingeasy_shipment_id' => $seShipmentId,
                'event_type' => $this->eventType,
            ]);
        }

        return $shipment;
    }

    protected function handleTrackingEvent(TrackingService $trackingService): void
    {
        $shipment = $this->findShipment();

        if (! $shipment) {
            return;
        }

        $shipmentData = $this->getShipmentData();

        $trackingService->processShippingEasyUpdate([
            'tracking_code' => $shipmentData['tracking_number'] ?? null,
            'status' => $shipmentData['workflow_state'] ?? $shipmentData['status'] ?? $this->eventType,
            'carrier' => $shipmentData['carrier_key'] ?? $shipmentData['carrier'] ?? null,
            'shipment' => $shipment,
        ]);
    }

    protected function handleLabelPurchased(): void
    {
        $shipment = $this->findShipment();

        if (! $shipment) {
            return;
        }

        $shipmentData = $this->getShipmentData();

        $updateData = [];

        if (! $shipment->tracking_number && ! empty($shipmentData['tracking_number'])) {
            $updateData['tracking_number'] = $shipmentData['tracking_number'];
        }

        if (! $shipment->tracking_url && ! empty($shipmentData['tracking_url'])) {
            $updateData['tracking_url'] = $shipmentData['tracking_url'];
        }

        if (! $shipment->label_url && ! empty($shipmentData['label_url'])) {
            $updateData['label_url'] = $shipmentData['label_url'];
        }

        // SE callback uses "id" for shipment ID at top level
        $seShipmentId = $shipmentData['id'] ?? $shipmentData['shipment_id'] ?? null;
        if (! $shipment->shippingeasy_shipment_id && $seShipmentId) {
            $updateData['shippingeasy_shipment_id'] = (string) $seShipmentId;
        }

        if (empty($shipment->label_purchased_at)) {
            $updateData['label_purchased_at'] = now();
            $updateData['status'] = Shipment::STATUS_LABEL_PURCHASED;
        }

        // SE callback sends shipment_cost in cents
        $cost = $shipmentData['shipment_cost'] ?? $shipmentData['cost'] ?? null;
        if ($cost !== null) {
            // Convert cents to dollars if the value looks like cents (> 100)
            $costValue = (float) $cost;
            $updateData['label_cost'] = $costValue > 100 ? $costValue / 100 : $costValue;
        }

        if (! empty($shipmentData['carrier_key'])) {
            $updateData['carrier'] = $shipmentData['carrier_key'];
        }

        if (! empty($shipmentData['carrier_service_key'])) {
            $updateData['service'] = $shipmentData['carrier_service_key'];
        }

        if (! empty($updateData)) {
            $shipment->update($updateData);

            Log::info('ShippingEasy label.purchased updated shipment', [
                'shipment_id' => $shipment->id,
                'fields_updated' => array_keys($updateData),
            ]);
        }
    }

    protected function handleLabelFailed(): void
    {
        $shipment = $this->findShipment();

        if (! $shipment) {
            return;
        }

        $shipmentData = $this->getShipmentData();
        $errorMessage = $shipmentData['error_message'] ?? $shipmentData['message'] ?? 'Label purchase failed';

        Log::error('ShippingEasy label purchase failed', [
            'shipment_id' => $shipment->id,
            'error' => $errorMessage,
            'org_id' => $this->organizationId,
        ]);

        $shipment->update([
            'status' => Shipment::STATUS_FAILED,
            'marketplace_data' => array_merge($shipment->marketplace_data ?? [], [
                'label_error' => $errorMessage,
                'label_failed_at' => now()->toIso8601String(),
            ]),
        ]);
    }

    protected function handleCancelled(): void
    {
        $shipment = $this->findShipment();

        if (! $shipment) {
            return;
        }

        Log::info('ShippingEasy shipment cancelled via webhook', [
            'shipment_id' => $shipment->id,
            'org_id' => $this->organizationId,
        ]);

        $shipment->update([
            'status' => Shipment::STATUS_CANCELLED,
            'marketplace_data' => array_merge($shipment->marketplace_data ?? [], [
                'cancelled_at' => now()->toIso8601String(),
                'cancelled_via' => 'shippingeasy_webhook',
            ]),
        ]);
    }
}
