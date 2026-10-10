<?php

namespace App\Services\Fulfillment;

use App\Models\Fulfillment\FulfillmentLog;
use App\Models\Fulfillment\Order;
use App\Models\Fulfillment\Shipment;
use App\Models\Fulfillment\ShipmentItem;
use App\Models\Inventory\InventoryLocation;
use App\Models\Organization\Organization;
use App\Models\Organization\OrganizationSettings;
use App\Models\User\User;
use Exception;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class ShipmentService
{
    protected FulfillmentAuditService $auditService;

    public function __construct(FulfillmentAuditService $auditService)
    {
        $this->auditService = $auditService;
    }

    protected function getShippingService(Organization $organization): EasyPostService|ShipStationService|ShippingEasyService
    {
        $provider = OrganizationSettings::get(
            $organization->id,
            'fulfillment_shipping_provider',
            'easypost'
        );

        return match ($provider) {
            'shipstation' => new ShipStationService($organization),
            'shippingeasy' => new ShippingEasyService($organization),
            default => new EasyPostService($organization),
        };
    }

    public function createShipment(Order $order, array $items, InventoryLocation $location): Shipment
    {
        return DB::transaction(function () use ($order, $items, $location) {
            $shipment = Shipment::create([
                'organization_id' => $order->organization_id,
                'order_id' => $order->id,
                'location_id' => $location->id,
                'shipment_number' => Shipment::generateShipmentNumber(),
                'status' => Shipment::STATUS_PENDING,
                'ship_from_name' => $location->contact_person ?? $location->name,
                'ship_from_address_line1' => $location->address_line1,
                'ship_from_city' => $location->city,
                'ship_from_state' => $location->state,
                'ship_from_postal_code' => $location->postal_code,
                'ship_from_country' => $location->country ?? 'US',
                'package_weight_oz' => $location->default_weight_oz,
                'package_length' => $location->default_length,
                'package_width' => $location->default_width,
                'package_height' => $location->default_height,
            ]);

            foreach ($items as $itemData) {
                ShipmentItem::create([
                    'shipment_id' => $shipment->id,
                    'order_item_id' => $itemData['order_item_id'],
                    'quantity' => $itemData['quantity'],
                ]);
            }

            return $shipment;
        });
    }

    public function getRates(Shipment $shipment): Collection
    {
        $order = $shipment->order;
        $location = $shipment->location;
        $organization = $order->organization;

        $shippingService = $this->getShippingService($organization);

        $rates = $shippingService->getRates($order, $location);

        $this->auditService->log(
            FulfillmentLog::ACTION_RATE_SHOPPED,
            $shipment,
            auth()->user(),
            ['rates_count' => $rates->count()],
            $order->id
        );

        return $rates;
    }

    public function purchaseLabel(Shipment $shipment, string $rateId, User $actor): Shipment
    {
        $order = $shipment->order;
        $organization = $order->organization;

        $shippingService = $this->getShippingService($organization);

        $labelData = $shippingService->purchaseLabel($order, $rateId, [
            'weight_oz' => $shipment->package_weight_oz,
            'length' => $shipment->package_length,
            'width' => $shipment->package_width,
            'height' => $shipment->package_height,
        ]);

        $isShipStation = $shippingService instanceof ShipStationService;
        $isShippingEasy = $shippingService instanceof ShippingEasyService;

        $updateData = [
            'status' => Shipment::STATUS_LABEL_PURCHASED,
            'shipping_provider' => $isShipStation ? 'shipstation' : ($isShippingEasy ? 'shippingeasy' : 'easypost'),
            'tracking_number' => $labelData['tracking_number'],
            'tracking_url' => $labelData['tracking_url'],
            'label_url' => $labelData['label_url'],
            'label_format' => $labelData['label_format'],
            'carrier' => $labelData['carrier'],
            'service' => $labelData['service'],
            'label_cost' => $labelData['label_cost'],
            'total_shipping_cost' => $labelData['label_cost'],
            'estimated_delivery_date' => $labelData['estimated_delivery_date'] ?? null,
            'label_purchased_at' => now(),
        ];

        if ($isShipStation) {
            $updateData['shipstation_shipment_id'] = $labelData['shipstation_shipment_id'] ?? null;
            $updateData['shipstation_order_id'] = $labelData['shipstation_order_id'] ?? null;
        } elseif ($isShippingEasy) {
            $updateData['shippingeasy_shipment_id'] = $labelData['shippingeasy_shipment_id'] ?? null;
            $updateData['shippingeasy_order_id'] = $labelData['shippingeasy_order_id'] ?? null;
        } else {
            $updateData['easypost_shipment_id'] = $labelData['easypost_shipment_id'];
            $updateData['easypost_tracker_id'] = $labelData['easypost_tracker_id'];
        }

        $shipment->update($updateData);

        $this->auditService->log(
            FulfillmentLog::ACTION_LABEL_PURCHASED,
            $shipment,
            $actor,
            [
                'carrier' => $labelData['carrier'],
                'service' => $labelData['service'],
                'cost' => $labelData['label_cost'],
                'tracking_number' => $labelData['tracking_number'],
            ],
            $order->id
        );

        return $shipment->fresh();
    }

    public function voidLabel(Shipment $shipment, User $actor): void
    {
        $organization = $shipment->order->organization;

        $shippingService = match (true) {
            $shipment->isShipStation() => new ShipStationService($organization),
            $shipment->isShippingEasy() => new ShippingEasyService($organization),
            default => new EasyPostService($organization),
        };

        $voided = $shippingService->voidLabel($shipment);

        if ($voided) {
            $shipment->update([
                'status' => Shipment::STATUS_CANCELLED,
                'tracking_number' => null,
                'tracking_url' => null,
                'label_url' => null,
            ]);

            $this->auditService->log(
                FulfillmentLog::ACTION_LABEL_VOIDED,
                $shipment,
                $actor,
                ['label_cost_refunded' => $shipment->label_cost],
                $shipment->order_id
            );
        } else {
            throw new Exception('Failed to void label with carrier.');
        }
    }

    public function markShipped(Shipment $shipment, User $actor): void
    {
        $shipment->update([
            'status' => Shipment::STATUS_SHIPPED,
            'shipped_by' => $actor->id,
            'shipped_at' => now(),
        ]);

        $this->auditService->log(
            FulfillmentLog::ACTION_SHIPMENT_CREATED,
            $shipment,
            $actor,
            ['tracking_number' => $shipment->tracking_number],
            $shipment->order_id
        );
    }

    public function manualShip(Order $order, array $data, User $actor): Shipment
    {
        return DB::transaction(function () use ($order, $data, $actor) {
            $shipment = $order->shipments()
                ->whereNotIn('status', [Shipment::STATUS_SHIPPED, Shipment::STATUS_DELIVERED, Shipment::STATUS_CANCELLED])
                ->first();

            if (! $shipment) {
                $location = $order->location;
                if (! $location) {
                    throw new Exception('Order must be assigned to a location before manual shipping.');
                }

                $items = $order->items->map(fn ($item) => [
                    'order_item_id' => $item->id,
                    'quantity' => $item->quantity,
                ])->all();

                $shipment = $this->createShipment($order, $items, $location);
            }

            $shippedAt = ! empty($data['shipped_at']) ? \Carbon\Carbon::parse($data['shipped_at']) : now();

            $shipment->update([
                'status' => Shipment::STATUS_SHIPPED,
                'shipping_provider' => 'manual',
                'carrier' => $data['carrier'],
                'service' => $data['service'] ?? null,
                'tracking_number' => $data['tracking_number'],
                'tracking_url' => $data['tracking_url'] ?? null,
                'shipped_by' => $actor->id,
                'shipped_at' => $shippedAt,
            ]);

            $this->auditService->log(
                FulfillmentLog::ACTION_SHIPMENT_CREATED,
                $shipment,
                $actor,
                [
                    'manual' => true,
                    'carrier' => $data['carrier'],
                    'tracking_number' => $data['tracking_number'],
                ],
                $order->id
            );

            return $shipment->fresh();
        });
    }

    public function markPacked(Shipment $shipment, User $actor, array $dimensions = []): void
    {
        $updateData = [
            'status' => Shipment::STATUS_PACKED,
            'packed_by' => $actor->id,
            'packed_at' => now(),
        ];

        if (! empty($dimensions)) {
            $updateData = array_merge($updateData, array_filter([
                'package_weight_oz' => $dimensions['weight_oz'] ?? null,
                'package_length' => $dimensions['length'] ?? null,
                'package_width' => $dimensions['width'] ?? null,
                'package_height' => $dimensions['height'] ?? null,
            ]));
        }

        $shipment->update($updateData);
    }

    public function splitShipment(Order $order, array $itemGroups, User $actor): Collection
    {
        $location = $order->location;
        if (! $location) {
            throw new Exception('Order must be assigned to a location before splitting shipments.');
        }

        $shipments = collect();

        DB::transaction(function () use ($order, $itemGroups, $location, &$shipments) {
            foreach ($itemGroups as $group) {
                $shipment = $this->createShipment($order, $group['items'], $location);
                $shipments->push($shipment);
            }
        });

        return $shipments;
    }

    public function updateDimensions(Shipment $shipment, array $dimensions): Shipment
    {
        $shipment->update(array_filter([
            'package_weight_oz' => $dimensions['weight_oz'] ?? null,
            'package_length' => $dimensions['length'] ?? null,
            'package_width' => $dimensions['width'] ?? null,
            'package_height' => $dimensions['height'] ?? null,
        ]));

        return $shipment->fresh();
    }
}
