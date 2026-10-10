<?php

namespace App\Services\Procurement;

use App\Models\Inventory\SupplierProduct;
use App\Models\Procurement\PurchaseOrder;
use App\Models\Procurement\PurchaseOrderItem;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class PurchaseOrderService
{
    public function generatePoNumber(string $organizationId): string
    {
        $count = PurchaseOrder::withoutGlobalScopes()
            ->where('organization_id', $organizationId)
            ->count();

        return sprintf('PO-%06d', $count + 1);
    }

    public function create(array $data, string $organizationId, int $userId): PurchaseOrder
    {
        return DB::transaction(function () use ($data, $organizationId, $userId) {
            $po = PurchaseOrder::create([
                'organization_id' => $organizationId,
                'supplier_id' => $data['supplier_id'] ?? null,
                'location_id' => $data['location_id'] ?? null,
                'po_number' => $this->generatePoNumber($organizationId),
                'status' => PurchaseOrder::STATUS_DRAFT,
                'order_date' => $data['order_date'] ?? now()->toDateString(),
                'expected_delivery_date' => $data['expected_delivery_date'] ?? null,
                'notes' => $data['notes'] ?? null,
                'tax_amount' => $data['tax_amount'] ?? 0,
                'shipping_cost' => $data['shipping_cost'] ?? 0,
                'created_by' => $userId,
            ]);

            if (! empty($data['items'])) {
                foreach ($data['items'] as $item) {
                    $this->addItem($po, $item);
                }
            }

            Log::info('Purchase order created', ['po_id' => $po->id, 'po_number' => $po->po_number]);

            $fresh = $po->fresh(['items.product', 'supplier', 'location']);

            app(\App\Services\NotificationService::class)->dispatchEvent(
                eventKey: 'procurement.po.created',
                organizationId: (int) $organizationId,
                args: [
                    (string) $fresh->id,
                    (string) $fresh->po_number,
                    $fresh->supplier?->name,
                    (float) ($fresh->total_amount ?? 0),
                ],
                relatedId: (string) $fresh->id,
            );

            return $fresh;
        });
    }

    public function update(PurchaseOrder $po, array $data): PurchaseOrder
    {
        if (! $po->canBeEdited()) {
            throw new \RuntimeException('Purchase order cannot be edited in its current status.');
        }

        return DB::transaction(function () use ($po, $data) {
            $po->update([
                'supplier_id' => $data['supplier_id'] ?? $po->supplier_id,
                'location_id' => $data['location_id'] ?? $po->location_id,
                'order_date' => $data['order_date'] ?? $po->order_date,
                'expected_delivery_date' => $data['expected_delivery_date'] ?? $po->expected_delivery_date,
                'notes' => $data['notes'] ?? $po->notes,
                'tax_amount' => $data['tax_amount'] ?? $po->tax_amount,
                'shipping_cost' => $data['shipping_cost'] ?? $po->shipping_cost,
            ]);

            if (isset($data['items'])) {
                $this->syncItems($po, $data['items']);
            }

            $po->recalculateTotals();

            Log::info('Purchase order updated', ['po_id' => $po->id]);

            return $po->fresh(['items.product', 'supplier', 'location']);
        });
    }

    public function addItem(PurchaseOrder $po, array $itemData): PurchaseOrderItem
    {
        $description = $itemData['description'] ?? null;
        $sku = $itemData['sku'] ?? null;
        $unitCost = $itemData['unit_cost'] ?? 0;

        // Auto-populate from SupplierProduct if available
        if (! empty($itemData['supplier_product_id'])) {
            $sp = SupplierProduct::find($itemData['supplier_product_id']);
            if ($sp) {
                $unitCost = $unitCost ?: $sp->cost_price;
                $sku = $sku ?: $sp->supplier_sku;
                $description = $description ?: $sp->supplier_name;
            }
        }

        return PurchaseOrderItem::create([
            'purchase_order_id' => $po->id,
            'product_id' => $itemData['product_id'] ?? null,
            'supplier_product_id' => $itemData['supplier_product_id'] ?? null,
            'description' => $description,
            'sku' => $sku,
            'quantity_ordered' => $itemData['quantity_ordered'] ?? 1,
            'unit_cost' => $unitCost,
            'notes' => $itemData['notes'] ?? null,
        ]);
    }

    public function syncItems(PurchaseOrder $po, array $items): void
    {
        $existingIds = collect($items)->pluck('id')->filter()->toArray();

        // Delete removed items
        $po->items()->whereNotIn('id', $existingIds)->delete();

        foreach ($items as $itemData) {
            if (! empty($itemData['id'])) {
                $item = PurchaseOrderItem::find($itemData['id']);
                if ($item) {
                    $item->update([
                        'product_id' => $itemData['product_id'] ?? $item->product_id,
                        'quantity_ordered' => $itemData['quantity_ordered'] ?? $item->quantity_ordered,
                        'unit_cost' => $itemData['unit_cost'] ?? $item->unit_cost,
                        'notes' => $itemData['notes'] ?? $item->notes,
                    ]);
                }
            } else {
                $this->addItem($po, $itemData);
            }
        }
    }

    public function submit(PurchaseOrder $po, ?int $approverId = null): PurchaseOrder
    {
        if (! $po->canBeSubmitted()) {
            throw new \RuntimeException('Purchase order cannot be submitted. Ensure it is in draft status and has items.');
        }

        $po->update([
            'status' => PurchaseOrder::STATUS_SUBMITTED,
            'submitted_at' => now(),
            'approved_by' => $approverId,
            'approved_at' => $approverId ? now() : null,
        ]);

        Log::info('Purchase order submitted', ['po_id' => $po->id, 'po_number' => $po->po_number]);

        $fresh = $po->fresh(['supplier', 'approver']);
        $approverName = $approverId
            ? (\App\Models\User\User::find($approverId)?->name)
            : null;

        audit_event(
            action: 'po.submitted',
            description: "PO {$fresh->po_number} submitted",
            subject: $fresh,
            metadata: [
                'po_number' => $fresh->po_number,
                'supplier_id' => $fresh->supplier_id,
                'supplier_name' => $fresh->supplier?->name,
                'total_amount' => (float) ($fresh->total_amount ?? 0),
                'approver_id' => $approverId,
                'source' => 'web',
            ],
            severity: 'info',
            category: 'procurement',
        );

        app(\App\Services\NotificationService::class)->dispatchEvent(
            eventKey: 'procurement.po.submitted',
            organizationId: (int) $fresh->organization_id,
            args: [
                (string) $fresh->id,
                (string) $fresh->po_number,
                $fresh->supplier?->name,
                (float) ($fresh->total_amount ?? 0),
                $approverName,
            ],
            relatedId: (string) $fresh->id,
        );

        return $fresh;
    }

    public function cancel(PurchaseOrder $po, ?string $reason = null): PurchaseOrder
    {
        if (! $po->canBeCancelled()) {
            throw new \RuntimeException('Purchase order cannot be cancelled in its current status.');
        }

        $po->update([
            'status' => PurchaseOrder::STATUS_CANCELLED,
            'cancelled_at' => now(),
            'cancel_reason' => $reason,
        ]);

        Log::info('Purchase order cancelled', ['po_id' => $po->id, 'reason' => $reason]);

        return $po->fresh();
    }

    public function getStats(string $organizationId): array
    {
        $base = PurchaseOrder::withoutGlobalScopes()->where('organization_id', $organizationId);

        return [
            'total' => (clone $base)->count(),
            'draft' => (clone $base)->where('status', PurchaseOrder::STATUS_DRAFT)->count(),
            'submitted' => (clone $base)->where('status', PurchaseOrder::STATUS_SUBMITTED)->count(),
            'partially_received' => (clone $base)->where('status', PurchaseOrder::STATUS_PARTIALLY_RECEIVED)->count(),
            'received' => (clone $base)->where('status', PurchaseOrder::STATUS_RECEIVED)->count(),
            'overdue' => (clone $base)->whereIn('status', [PurchaseOrder::STATUS_SUBMITTED, PurchaseOrder::STATUS_PARTIALLY_RECEIVED])
                ->whereNotNull('expected_delivery_date')
                ->where('expected_delivery_date', '<', now())
                ->count(),
            'total_value_open' => (clone $base)->whereIn('status', [PurchaseOrder::STATUS_DRAFT, PurchaseOrder::STATUS_SUBMITTED, PurchaseOrder::STATUS_PARTIALLY_RECEIVED])
                ->sum('total_amount'),
        ];
    }

    public function getSupplierProducts(string $supplierId): array
    {
        return SupplierProduct::where('supplier_id', $supplierId)
            ->where('is_active', true)
            ->with('product:id,name,sku')
            ->get()
            ->map(fn ($sp) => [
                'id' => $sp->id,
                'product_id' => $sp->product_id,
                'product_name' => $sp->product?->name ?? $sp->supplier_name,
                'sku' => $sp->supplier_sku ?? $sp->product?->sku,
                'cost_price' => $sp->cost_price,
                'minimum_order_quantity' => $sp->minimum_order_quantity,
            ])
            ->toArray();
    }
}
