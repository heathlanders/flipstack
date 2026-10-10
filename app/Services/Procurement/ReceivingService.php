<?php

namespace App\Services\Procurement;

use App\Models\Inventory\InventoryLevel;
use App\Models\Inventory\InventoryMovement;
use App\Models\Procurement\PurchaseOrder;
use App\Models\Procurement\PurchaseOrderReceipt;
use App\Models\Procurement\PurchaseOrderReceiptItem;
use App\Services\Inventory\LabelingWorkflowService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ReceivingService
{
    public function receive(PurchaseOrder $po, array $data, int $userId): PurchaseOrderReceipt
    {
        if (! $po->canReceiveItems()) {
            throw new \RuntimeException('Purchase order is not in a receivable status.');
        }

        $receipt = DB::transaction(function () use ($po, $data, $userId) {
            $locationId = $data['location_id'] ?? $po->location_id;

            $receipt = PurchaseOrderReceipt::create([
                'purchase_order_id' => $po->id,
                'received_by' => $userId,
                'location_id' => $locationId,
                'received_at' => now(),
                'notes' => $data['notes'] ?? null,
            ]);

            foreach ($data['items'] as $receiptItemData) {
                $this->receiveItem($receipt, $receiptItemData, $po->organization_id, $locationId, $userId);
            }

            $this->updatePoStatus($po);

            Log::info('Purchase order receipt created', [
                'po_id' => $po->id,
                'receipt_id' => $receipt->id,
                'items_count' => count($data['items']),
            ]);

            return $receipt->fresh(['items', 'purchaseOrder']);
        });

        // Trigger labeling work order creation (outside transaction)
        try {
            app(LabelingWorkflowService::class)->createFromPoReceipt($receipt);
        } catch (\Throwable $e) {
            Log::warning('Failed to create labeling work order from PO receipt', [
                'receipt_id' => $receipt->id,
                'error' => $e->getMessage(),
            ]);
        }

        $po->refresh();
        $itemsReceived = (int) ($receipt->items()->sum('quantity_received') ?? 0);

        app(\App\Services\NotificationService::class)->dispatchEvent(
            eventKey: 'procurement.po.received',
            organizationId: (int) $po->organization_id,
            args: [
                (string) $po->id,
                (string) $po->po_number,
                $po->status === PurchaseOrder::STATUS_RECEIVED,
                $itemsReceived,
            ],
            relatedId: (string) $receipt->id,
        );

        return $receipt;
    }

    protected function receiveItem(
        PurchaseOrderReceipt $receipt,
        array $data,
        string $organizationId,
        ?string $locationId,
        ?int $userId = null
    ): PurchaseOrderReceiptItem {
        $poItem = $receipt->purchaseOrder->items()->findOrFail($data['purchase_order_item_id']);
        $quantityReceived = (int) $data['quantity_received'];

        if ($quantityReceived <= 0) {
            throw new \RuntimeException('Quantity received must be greater than zero.');
        }

        $receiptItem = PurchaseOrderReceiptItem::create([
            'receipt_id' => $receipt->id,
            'purchase_order_item_id' => $poItem->id,
            'product_id' => $poItem->product_id,
            'quantity_received' => $quantityReceived,
            'condition' => $data['condition'] ?? 'good',
            'condition_notes' => $data['condition_notes'] ?? null,
        ]);

        // Update PO item received quantity
        $poItem->increment('quantity_received', $quantityReceived);

        // Update inventory if product is linked
        if ($poItem->product_id && $locationId) {
            $this->updateInventory(
                $organizationId,
                $poItem->product_id,
                $locationId,
                $quantityReceived,
                $poItem->unit_cost,
                $receipt->id,
                $userId
            );
        }

        return $receiptItem;
    }

    protected function updateInventory(
        string $organizationId,
        string $productId,
        string $locationId,
        int $quantity,
        float $unitCost,
        string $referenceId,
        ?int $userId = null
    ): void {
        // Update or create inventory level
        $level = InventoryLevel::firstOrCreate(
            [
                'organization_id' => $organizationId,
                'product_id' => $productId,
                'location_id' => $locationId,
            ],
            [
                'quantity_on_hand' => 0,
                'quantity_reserved' => 0,
            ]
        );

        $previousQty = $level->quantity_on_hand;
        $level->increment('quantity_on_hand', $quantity);

        // Update average cost using weighted average
        if ($unitCost > 0) {
            $totalOldValue = $previousQty * ($level->average_cost ?? 0);
            $totalNewValue = $quantity * $unitCost;
            $newTotalQty = $previousQty + $quantity;
            $level->update([
                'average_cost' => $newTotalQty > 0 ? round(($totalOldValue + $totalNewValue) / $newTotalQty, 2) : $unitCost,
                'last_movement_at' => now(),
            ]);
        }

        // Update product stock quantity
        $product = $level->product;
        if ($product) {
            $product->increment('stock_quantity', $quantity);
            $product->increment('available_quantity', $quantity);
        }

        // Create inventory movement
        InventoryMovement::create([
            'organization_id' => $organizationId,
            'product_id' => $productId,
            'location_id' => $locationId,
            'type' => 'purchase',
            'quantity' => $quantity,
            'unit_cost' => $unitCost,
            'reference_type' => 'purchase_order_receipt',
            'reference_id' => $referenceId,
            'notes' => 'Received from purchase order',
            'created_by' => $userId ?? auth()->id(),
        ]);

        Log::info('Inventory updated from PO receipt', [
            'product_id' => $productId,
            'location_id' => $locationId,
            'quantity' => $quantity,
            'new_on_hand' => $level->fresh()->quantity_on_hand,
        ]);
    }

    protected function updatePoStatus(PurchaseOrder $po): void
    {
        $po->refresh();
        $po->load('items');

        if ($po->isFullyReceived()) {
            $po->update([
                'status' => PurchaseOrder::STATUS_RECEIVED,
                'received_at' => now(),
            ]);
        } else {
            $hasAnyReceived = $po->items->some(fn ($item) => $item->quantity_received > 0);
            if ($hasAnyReceived) {
                $po->update(['status' => PurchaseOrder::STATUS_PARTIALLY_RECEIVED]);
            }
        }
    }
}
