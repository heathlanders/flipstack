<?php

namespace App\Services\Fulfillment;

use App\Jobs\Inventory\PushInventoryToMarketplaces;
use App\Models\Catalog\Product;
use App\Models\Fulfillment\FulfillmentLog;
use App\Models\Fulfillment\Order;
use App\Models\Fulfillment\OrderItem;
use App\Models\Fulfillment\SupplierFulfillmentRequest;
use App\Models\Inventory\InventoryLevel;
use App\Models\Inventory\InventoryLocation;
use App\Models\Inventory\Supplier;
use App\Models\User\User;
use Exception;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class FulfillmentService
{
    protected FulfillmentAuditService $auditService;

    public function __construct(FulfillmentAuditService $auditService)
    {
        $this->auditService = $auditService;
    }

    public function assignOrder(Order $order, InventoryLocation $location, User $actor): void
    {
        DB::transaction(function () use ($order, $location, $actor) {
            $order->update([
                'location_id' => $location->id,
                'status' => Order::STATUS_ASSIGNED,
            ]);

            // Assign items to this location
            $order->items()->update(['location_id' => $location->id]);

            $this->auditService->log(
                FulfillmentLog::ACTION_ORDER_ASSIGNED,
                $order,
                $actor,
                ['location_id' => $location->id, 'location_name' => $location->name]
            );
        });
    }

    public function assignPicker(Order $order, User $picker, User $actor): void
    {
        $order->update(['assigned_picker_id' => $picker->id]);

        $this->auditService->log(
            FulfillmentLog::ACTION_ORDER_ASSIGNED,
            $order,
            $actor,
            ['picker_id' => $picker->id, 'picker_name' => $picker->name, 'role' => 'picker']
        );
    }

    public function assignPacker(Order $order, User $packer, User $actor): void
    {
        $order->update(['assigned_packer_id' => $packer->id]);

        $this->auditService->log(
            FulfillmentLog::ACTION_ORDER_ASSIGNED,
            $order,
            $actor,
            ['packer_id' => $packer->id, 'packer_name' => $packer->name, 'role' => 'packer']
        );
    }

    public function startPicking(Order $order, User $actor): void
    {
        if (! $order->canBePicked()) {
            throw new Exception('Order cannot be picked in its current state.');
        }

        $order->update([
            'status' => Order::STATUS_PICKING,
            'assigned_picker_id' => $order->assigned_picker_id ?? $actor->id,
        ]);

        $this->auditService->log(FulfillmentLog::ACTION_PICK_STARTED, $order, $actor);
    }

    public function pickItem(OrderItem $item, User $actor, ?int $quantity = null): void
    {
        $quantity = $quantity ?? $item->quantity;

        $item->update([
            'pick_status' => OrderItem::PICK_PICKED,
            'picked_at' => now(),
            'picked_by' => $actor->id,
        ]);

        $this->auditService->log(
            FulfillmentLog::ACTION_ITEM_PICKED,
            $item,
            $actor,
            [
                'quantity' => $quantity,
                'sku' => $item->sku,
                'title' => $item->title,
            ],
            $item->order_id
        );
    }

    public function shortItem(OrderItem $item, User $actor, string $reason): void
    {
        $item->update([
            'pick_status' => OrderItem::PICK_SHORT,
            'picked_at' => now(),
            'picked_by' => $actor->id,
        ]);

        $this->auditService->log(
            FulfillmentLog::ACTION_PICK_SHORTED,
            $item,
            $actor,
            ['reason' => $reason, 'sku' => $item->sku, 'title' => $item->title],
            $item->order_id
        );
    }

    public function completePicking(Order $order, User $actor): void
    {
        $allPicked = $order->items->every(function ($item) {
            return in_array($item->pick_status, [OrderItem::PICK_PICKED, OrderItem::PICK_SHORT]);
        });

        if (! $allPicked) {
            throw new Exception('Not all items have been picked or marked short.');
        }

        $order->update(['status' => Order::STATUS_PICKED]);

        $this->auditService->log(
            FulfillmentLog::ACTION_PICK_COMPLETED,
            $order,
            $actor,
            [
                'items_picked' => $order->items->where('pick_status', OrderItem::PICK_PICKED)->count(),
                'items_shorted' => $order->items->where('pick_status', OrderItem::PICK_SHORT)->count(),
            ]
        );
    }

    public function startPacking(Order $order, User $actor): void
    {
        if (! $order->canBePacked()) {
            throw new Exception('Order cannot be packed in its current state.');
        }

        $order->update([
            'status' => Order::STATUS_PACKING,
            'assigned_packer_id' => $order->assigned_packer_id ?? $actor->id,
        ]);

        $this->auditService->log(FulfillmentLog::ACTION_PACK_STARTED, $order, $actor);
    }

    public function completePacking(Order $order, array $packageDimensions, User $actor): void
    {
        $order->update(['status' => Order::STATUS_PACKED]);

        $this->auditService->log(
            FulfillmentLog::ACTION_PACK_COMPLETED,
            $order,
            $actor,
            ['package' => $packageDimensions]
        );
    }

    public function shipOrder(Order $order, User $actor): void
    {
        $order->update([
            'status' => Order::STATUS_SHIPPED,
            'shipped_at' => now(),
        ]);

        $shipment = $order->shipments()->latest('id')->first();
        audit_event(
            action: 'order.shipped',
            description: "Order #{$order->order_number} shipped",
            subject: $order,
            metadata: [
                'order_number' => $order->order_number,
                'marketplace' => $order->marketplace,
                'carrier' => $shipment?->carrier,
                'tracking_number' => $shipment?->tracking_number,
                'ship_to_country' => $order->shipping_country,
                'item_count' => (int) ($order->items_count ?? 0),
                'source' => 'web',
            ],
            severity: 'info',
            category: 'fulfillment',
            actor: $actor,
        );

        $this->dispatchShippedNotifications($order);

        // Decrement inventory
        foreach ($order->items->where('pick_status', OrderItem::PICK_PICKED) as $item) {
            if ($item->product_id && $item->location_id) {
                $inventoryLevel = InventoryLevel::where('product_id', $item->product_id)
                    ->where('location_id', $item->location_id)
                    ->first();

                if ($inventoryLevel) {
                    $inventoryLevel->decrement('quantity_on_hand', $item->quantity);
                    if ($inventoryLevel->quantity_reserved >= $item->quantity) {
                        $inventoryLevel->decrement('quantity_reserved', $item->quantity);
                    }
                }
            }
        }

        // Dispatch tracking upload for each shipment with a tracking number
        try {
            $trackingService = new TrackingService($this->auditService);
            foreach ($order->shipments()->whereNotNull('tracking_number')->get() as $shipment) {
                $trackingService->dispatchTrackingUpload($shipment);
            }
        } catch (\Exception $e) {
            Log::warning('Failed to dispatch tracking upload on ship', [
                'order_id' => $order->id,
                'error' => $e->getMessage(),
            ]);
        }

        $this->auditService->log(FulfillmentLog::ACTION_TRACKING_UPLOADED, $order, $actor);
    }

    protected function dispatchShippedNotifications(Order $order): void
    {
        $shipment = $order->shipments()->whereNotNull('tracking_number')->first();
        $tracking = $shipment?->tracking_number;
        $trackingUrl = $shipment?->tracking_url ?? null;
        $carrier = $shipment?->carrier ?? null;

        $orderNumber = $order->marketplace_order_id ?? (string) $order->id;

        $notificationService = app(\App\Services\NotificationService::class);

        $notificationService->dispatchEvent(
            eventKey: 'fulfillment.order.shipped',
            organizationId: (int) $order->organization_id,
            args: [
                (string) $order->id,
                $orderNumber,
                $order->marketplace,
                $tracking,
            ],
            relatedId: (string) $order->id,
        );

        if ($order->buyer_email) {
            $notificationService->dispatchEvent(
                eventKey: 'fulfillment.order.shipped_to_buyer',
                organizationId: (int) $order->organization_id,
                args: [
                    (int) $order->organization_id,
                    (string) $order->id,
                    $orderNumber,
                    $order->marketplace,
                    $tracking,
                    $trackingUrl,
                    $carrier,
                ],
                context: ['order' => ['buyer_email' => $order->buyer_email]],
                relatedId: 'buyer:'.$order->id,
            );
        }
    }

    public function holdOrder(Order $order, string $reason, User $actor): void
    {
        $previousStatus = $order->status;
        $order->update([
            'status' => Order::STATUS_ON_HOLD,
            'internal_notes' => trim(($order->internal_notes ?? '')."\n[HOLD] {$reason}"),
        ]);

        $this->auditService->log(
            FulfillmentLog::ACTION_ORDER_HELD,
            $order,
            $actor,
            ['reason' => $reason, 'previous_status' => $previousStatus]
        );
    }

    public function releaseOrder(Order $order, User $actor): void
    {
        $order->update(['status' => Order::STATUS_ASSIGNED]);

        $this->auditService->log(FulfillmentLog::ACTION_ORDER_RELEASED, $order, $actor);
    }

    public function cancelOrder(Order $order, string $reason, User $actor): void
    {
        DB::transaction(function () use ($order, $reason, $actor) {
            $order->update([
                'status' => Order::STATUS_CANCELLED,
                'cancelled_at' => now(),
                'internal_notes' => trim(($order->internal_notes ?? '')."\n[CANCELLED] {$reason}"),
            ]);

            // Unreserve inventory and restore available quantity
            $restoredProductIds = [];

            foreach ($order->items as $item) {
                if ($item->product_id && $item->location_id) {
                    $inventoryLevel = InventoryLevel::where('product_id', $item->product_id)
                        ->where('location_id', $item->location_id)
                        ->first();

                    if ($inventoryLevel && $inventoryLevel->quantity_reserved > 0) {
                        $inventoryLevel->decrement('quantity_reserved', min($item->quantity, $inventoryLevel->quantity_reserved));
                    }
                }

                if ($item->product_id && $item->quantity) {
                    $product = Product::find($item->product_id);

                    if ($product && ! in_array($product->id, $restoredProductIds)) {
                        $product->incrementAvailableQuantity((int) $item->quantity);
                        $restoredProductIds[] = $product->id;

                        PushInventoryToMarketplaces::dispatch($product->id);
                    }
                }
            }

            $this->auditService->log(
                FulfillmentLog::ACTION_ORDER_CANCELLED,
                $order,
                $actor,
                ['reason' => $reason]
            );
        });
    }

    public function changePriority(Order $order, string $priority, User $actor): void
    {
        $previous = $order->priority;
        $order->update(['priority' => $priority]);

        $this->auditService->log(
            FulfillmentLog::ACTION_ORDER_PRIORITY_CHANGED,
            $order,
            $actor,
            ['from' => $previous, 'to' => $priority]
        );
    }

    public function autoAssignOrders(Collection $orders): int
    {
        $assigned = 0;

        foreach ($orders as $order) {
            if ($order->status !== Order::STATUS_PENDING) {
                continue;
            }

            // Find best location based on inventory availability
            $location = $this->findBestLocation($order);
            if ($location) {
                $order->update([
                    'location_id' => $location->id,
                    'status' => Order::STATUS_ASSIGNED,
                ]);
                $order->items()->update(['location_id' => $location->id]);
                $assigned++;
            }
        }

        return $assigned;
    }

    public function getPickList(InventoryLocation $location): Collection
    {
        return Order::where('location_id', $location->id)
            ->whereIn('status', [Order::STATUS_ASSIGNED, Order::STATUS_PICKING])
            ->with(['items' => function ($q) {
                $q->where('pick_status', OrderItem::PICK_PENDING);
            }])
            ->orderByRaw("array_position(ARRAY['urgent', 'high', 'standard'], priority)")
            ->orderBy('ship_by_date')
            ->get();
    }

    public function routeToSupplier(Order $order, Supplier $supplier, User $actor): SupplierFulfillmentRequest
    {
        $request = SupplierFulfillmentRequest::create([
            'organization_id' => $order->organization_id,
            'order_id' => $order->id,
            'supplier_id' => $supplier->id,
            'status' => SupplierFulfillmentRequest::STATUS_PENDING,
            'sla_deadline' => now()->addHours($supplier->dropship_sla_hours ?? 48),
        ]);

        $order->update([
            'fulfillment_mode' => Order::FULFILLMENT_DROPSHIP,
            'supplier_id' => $supplier->id,
        ]);

        $this->auditService->log(
            FulfillmentLog::ACTION_DROPSHIP_ROUTED,
            $request,
            $actor,
            ['supplier_id' => $supplier->id, 'supplier_name' => $supplier->name, 'sla_hours' => $supplier->dropship_sla_hours ?? 48],
            $order->id
        );

        return $request;
    }

    protected function findBestLocation(Order $order): ?InventoryLocation
    {
        $productIds = $order->items->pluck('product_id')->filter()->toArray();

        if (empty($productIds)) {
            // No products matched, use default location
            return InventoryLocation::where('organization_id', $order->organization_id)
                ->where('is_active', true)
                ->first();
        }

        // Find location with most items in stock
        $location = InventoryLocation::where('organization_id', $order->organization_id)
            ->where('is_active', true)
            ->withCount(['inventoryLevels as matching_items' => function ($q) use ($productIds) {
                $q->whereIn('product_id', $productIds)
                    ->where('quantity_on_hand', '>', 0);
            }])
            ->orderByDesc('matching_items')
            ->first();

        if ($location && $location->matching_items > 0) {
            return $location;
        }

        // Fall back to the org's first active location so the order auto-assigns
        // even when inventory_levels haven't been seeded for these products yet.
        return InventoryLocation::where('organization_id', $order->organization_id)
            ->where('is_active', true)
            ->orderBy('created_at')
            ->first();
    }
}
