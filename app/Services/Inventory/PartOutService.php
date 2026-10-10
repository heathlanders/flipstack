<?php

namespace App\Services\Inventory;

use App\Models\Catalog\Product;
use App\Models\Inventory\InventoryLevel;
use App\Models\Inventory\InventoryMovement;
use App\Models\Inventory\PartOutTemplate;
use App\Models\Inventory\PartOutTemplateItem;
use App\Models\Inventory\PartOutWorkOrder;
use App\Models\Inventory\PartOutWorkOrderItem;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class PartOutService
{
    public function generateWorkOrderNumber(string $organizationId): string
    {
        $count = PartOutWorkOrder::withoutGlobalScopes()
            ->where('organization_id', $organizationId)
            ->count();

        return sprintf('WO-%s-%04d', now()->format('Ymd'), $count + 1);
    }

    public function createTemplate(array $data, string $organizationId): PartOutTemplate
    {
        return DB::transaction(function () use ($data, $organizationId) {
            $template = PartOutTemplate::create([
                'organization_id' => $organizationId,
                'name' => $data['name'],
                'description' => $data['description'] ?? null,
                'source_product_id' => $data['source_product_id'] ?? null,
                'cost_allocation_method' => $data['cost_allocation_method'] ?? PartOutTemplate::METHOD_EVEN,
                'is_active' => $data['is_active'] ?? true,
                'times_used' => 0,
                'created_by' => $data['created_by'] ?? auth()->id(),
            ]);

            if (! empty($data['items'])) {
                foreach ($data['items'] as $idx => $item) {
                    PartOutTemplateItem::create([
                        'template_id' => $template->id,
                        'component_product_id' => $item['component_product_id'] ?? null,
                        'name' => $item['name'],
                        'expected_quantity' => $item['expected_quantity'] ?? 1,
                        'cost_allocation_pct' => $item['cost_allocation_pct'] ?? null,
                        'sort_order' => $item['sort_order'] ?? $idx,
                        'is_optional' => $item['is_optional'] ?? false,
                        'default_condition' => $item['default_condition'] ?? null,
                        'part_number' => $item['part_number'] ?? null,
                        'manufacturer' => $item['manufacturer'] ?? null,
                        'category' => $item['category'] ?? null,
                        'suggested_selling_price' => $item['suggested_selling_price'] ?? null,
                    ]);
                }
            }

            Log::info('Part-out template created', [
                'template_id' => $template->id,
                'name' => $template->name,
                'items_count' => count($data['items'] ?? []),
            ]);

            return $template->fresh(['items', 'sourceProduct']);
        });
    }

    public function createTemplateFromProduct(Product $product, string $organizationId, int $userId): PartOutTemplate
    {
        $breakdown = $product->component_breakdown ?? [];

        $items = [];
        foreach ($breakdown as $idx => $component) {
            $items[] = [
                'name' => $component['name'] ?? 'Component '.($idx + 1),
                'expected_quantity' => $component['quantity'] ?? 1,
                'cost_allocation_pct' => $component['cost_allocation_pct'] ?? null,
                'part_number' => $component['part_number'] ?? null,
                'manufacturer' => $component['manufacturer'] ?? null,
                'category' => $component['category'] ?? null,
                'suggested_selling_price' => $component['suggested_selling_price'] ?? null,
                'default_condition' => $component['condition'] ?? null,
                'is_optional' => $component['is_optional'] ?? false,
            ];
        }

        return $this->createTemplate([
            'name' => 'Part-Out: '.$product->name,
            'description' => 'Auto-generated template from product component breakdown',
            'source_product_id' => $product->id,
            'cost_allocation_method' => PartOutTemplate::METHOD_EVEN,
            'created_by' => $userId,
            'items' => $items,
        ], $organizationId);
    }

    public function updateTemplate(PartOutTemplate $template, array $data): PartOutTemplate
    {
        return DB::transaction(function () use ($template, $data) {
            $template->update([
                'name' => $data['name'] ?? $template->name,
                'description' => $data['description'] ?? $template->description,
                'source_product_id' => $data['source_product_id'] ?? $template->source_product_id,
                'cost_allocation_method' => $data['cost_allocation_method'] ?? $template->cost_allocation_method,
                'is_active' => $data['is_active'] ?? $template->is_active,
            ]);

            if (array_key_exists('items', $data)) {
                // Delete existing items and recreate
                $template->items()->delete();

                foreach ($data['items'] as $idx => $item) {
                    PartOutTemplateItem::create([
                        'template_id' => $template->id,
                        'component_product_id' => $item['component_product_id'] ?? null,
                        'name' => $item['name'],
                        'expected_quantity' => $item['expected_quantity'] ?? 1,
                        'cost_allocation_pct' => $item['cost_allocation_pct'] ?? null,
                        'sort_order' => $item['sort_order'] ?? $idx,
                        'is_optional' => $item['is_optional'] ?? false,
                        'default_condition' => $item['default_condition'] ?? null,
                        'part_number' => $item['part_number'] ?? null,
                        'manufacturer' => $item['manufacturer'] ?? null,
                        'category' => $item['category'] ?? null,
                        'suggested_selling_price' => $item['suggested_selling_price'] ?? null,
                    ]);
                }
            }

            Log::info('Part-out template updated', [
                'template_id' => $template->id,
                'name' => $template->name,
            ]);

            return $template->fresh(['items', 'sourceProduct']);
        });
    }

    public function createWorkOrder(array $data, string $organizationId): PartOutWorkOrder
    {
        return DB::transaction(function () use ($data, $organizationId) {
            $sourceProduct = Product::findOrFail($data['source_product_id']);
            $sourceQuantity = (int) ($data['source_quantity'] ?? 1);
            $sourceUnitCost = (float) ($data['source_unit_cost'] ?? $sourceProduct->cost_price ?? 0);
            $sourceTotalCost = $sourceUnitCost * $sourceQuantity;

            $workOrder = PartOutWorkOrder::create([
                'organization_id' => $organizationId,
                'template_id' => $data['template_id'] ?? null,
                'work_order_number' => $this->generateWorkOrderNumber($organizationId),
                'source_product_id' => $sourceProduct->id,
                'source_quantity' => $sourceQuantity,
                'source_unit_cost' => $sourceUnitCost,
                'source_total_cost' => $sourceTotalCost,
                'remaining_parent_cost' => $data['remaining_parent_cost'] ?? 0,
                'cost_allocation_method' => $data['cost_allocation_method'] ?? PartOutTemplate::METHOD_EVEN,
                'location_id' => $data['location_id'] ?? null,
                'status' => PartOutWorkOrder::STATUS_DRAFT,
                'parent_continues' => $data['parent_continues'] ?? false,
                'parent_new_name' => $data['parent_new_name'] ?? null,
                'notes' => $data['notes'] ?? null,
                'started_at' => now(),
                'created_by' => $data['created_by'] ?? auth()->id(),
            ]);

            if (! empty($data['items'])) {
                foreach ($data['items'] as $item) {
                    PartOutWorkOrderItem::create([
                        'work_order_id' => $workOrder->id,
                        'template_item_id' => $item['template_item_id'] ?? null,
                        'component_product_id' => $item['component_product_id'] ?? null,
                        'name' => $item['name'],
                        'part_number' => $item['part_number'] ?? null,
                        'manufacturer' => $item['manufacturer'] ?? null,
                        'category' => $item['category'] ?? null,
                        'quantity' => $item['quantity'] ?? 1,
                        'allocated_cost' => $item['allocated_cost'] ?? 0,
                        'allocated_cost_per_unit' => $item['allocated_cost_per_unit'] ?? 0,
                        'cost_allocation_pct' => $item['cost_allocation_pct'] ?? null,
                        'suggested_selling_price' => $item['suggested_selling_price'] ?? null,
                        'condition' => $item['condition'] ?? null,
                        'notes' => $item['notes'] ?? null,
                    ]);
                }
            }

            Log::info('Part-out work order created', [
                'work_order_id' => $workOrder->id,
                'work_order_number' => $workOrder->work_order_number,
                'source_product_id' => $sourceProduct->id,
                'items_count' => count($data['items'] ?? []),
            ]);

            return $workOrder->fresh(['items', 'sourceProduct', 'template']);
        });
    }

    public function executeWorkOrder(PartOutWorkOrder $workOrder, int $userId): PartOutWorkOrder
    {
        return DB::transaction(function () use ($workOrder, $userId) {
            // Lock source product for update
            $sourceProduct = Product::lockForUpdate()->findOrFail($workOrder->source_product_id);

            // Validate work order can be executed
            if (! $workOrder->canExecute()) {
                throw new \RuntimeException(
                    "Work order {$workOrder->work_order_number} cannot be executed (status: {$workOrder->status})."
                );
            }

            // Validate sufficient source quantity
            if ($sourceProduct->available_quantity < $workOrder->source_quantity) {
                throw new \RuntimeException(
                    "Insufficient source quantity. Available: {$sourceProduct->available_quantity}, required: {$workOrder->source_quantity}."
                );
            }

            // Filter out skipped items (mobile extraction tracking)
            // Skipped components are not created as products
            $skippedItems = $workOrder->items()
                ->where('extraction_status', PartOutWorkOrderItem::EXTRACTION_SKIPPED)
                ->get();

            if ($skippedItems->isNotEmpty()) {
                Log::info('Part-out: excluding skipped items from execution', [
                    'work_order_id' => $workOrder->id,
                    'skipped_count' => $skippedItems->count(),
                ]);
            }

            // Step 1: Allocate costs (only across non-skipped items)
            $this->allocateCosts($workOrder);
            $workOrder->refresh();
            $workOrder->load('items');

            // Step 2: Create component products for each non-skipped WO item
            $activeItems = $workOrder->items
                ->where('extraction_status', '!=', PartOutWorkOrderItem::EXTRACTION_SKIPPED);

            foreach ($activeItems->values() as $idx => $woItem) {
                $componentProduct = $this->createComponentProduct(
                    $workOrder,
                    $woItem,
                    $sourceProduct,
                    $idx
                );

                // Update WO item with the created product
                $woItem->update(['component_product_id' => $componentProduct->id]);

                // Step 4: Create partout_produce movement for the component
                $this->createProduceMovement($workOrder, $componentProduct, $woItem);

                // Create inventory level for the component product
                if ($workOrder->location_id) {
                    $this->createComponentInventoryLevel($workOrder, $componentProduct, $woItem);
                }
            }

            // Step 3: Create partout_consume movement on source product
            $this->createConsumeMovement($workOrder, $sourceProduct);

            // Step 5: Update parent product
            $this->updateParentProduct($workOrder, $sourceProduct);

            // Step 6: Complete the work order
            $workOrder->update([
                'status' => PartOutWorkOrder::STATUS_COMPLETED,
                'completed_at' => now(),
                'completed_by' => $userId,
            ]);

            // Increment template times_used if applicable
            if ($workOrder->template_id) {
                $workOrder->template()->increment('times_used');
            }

            Log::info('Part-out work order executed', [
                'work_order_id' => $workOrder->id,
                'work_order_number' => $workOrder->work_order_number,
                'source_product_id' => $sourceProduct->id,
                'components_created' => $workOrder->items->count(),
                'completed_by' => $userId,
            ]);

            return $workOrder->fresh(['items.componentProduct', 'sourceProduct', 'template']);
        });
    }

    public function cancelWorkOrder(PartOutWorkOrder $workOrder): PartOutWorkOrder
    {
        if (! $workOrder->canCancel()) {
            throw new \RuntimeException(
                "Work order {$workOrder->work_order_number} cannot be cancelled (status: {$workOrder->status})."
            );
        }

        $workOrder->update([
            'status' => PartOutWorkOrder::STATUS_CANCELLED,
            'cancelled_at' => now(),
        ]);

        Log::info('Part-out work order cancelled', [
            'work_order_id' => $workOrder->id,
            'work_order_number' => $workOrder->work_order_number,
        ]);

        return $workOrder->fresh();
    }

    public function allocateCosts(PartOutWorkOrder $workOrder): void
    {
        $workOrder->load('items');

        // Exclude skipped items from cost allocation (mobile extraction tracking)
        // Cost is distributed only across items that were actually extracted
        $activeItems = $workOrder->items
            ->where('extraction_status', '!=', PartOutWorkOrderItem::EXTRACTION_SKIPPED);

        // If no active items, nothing to allocate
        if ($activeItems->isEmpty()) {
            return;
        }

        $costToAllocate = (float) $workOrder->source_total_cost - (float) ($workOrder->remaining_parent_cost ?? 0);

        if ($costToAllocate <= 0) {
            return;
        }

        switch ($workOrder->cost_allocation_method) {
            case PartOutTemplate::METHOD_MARKET_VALUE:
                $this->allocateByMarketValue($workOrder, $costToAllocate);
                break;

            case PartOutTemplate::METHOD_PERCENTAGE:
                $this->allocateByPercentage($workOrder, $costToAllocate);
                break;

            case PartOutTemplate::METHOD_EVEN:
            default:
                $this->allocateEvenly($workOrder, $costToAllocate);
                break;
        }
    }

    public function previewCostAllocation(array $data): array
    {
        $sourceTotalCost = (float) ($data['source_total_cost'] ?? 0);
        $remainingParentCost = (float) ($data['remaining_parent_cost'] ?? 0);
        $costToAllocate = $sourceTotalCost - $remainingParentCost;
        $method = $data['cost_allocation_method'] ?? PartOutTemplate::METHOD_EVEN;
        $items = $data['items'] ?? [];

        if ($costToAllocate <= 0 || empty($items)) {
            return [
                'cost_to_allocate' => $costToAllocate,
                'remaining_parent_cost' => $remainingParentCost,
                'items' => [],
            ];
        }

        $preview = [];

        switch ($method) {
            case PartOutTemplate::METHOD_MARKET_VALUE:
                $totalMarketValue = 0;
                foreach ($items as $item) {
                    $totalMarketValue += ((float) ($item['suggested_selling_price'] ?? 0)) * ((int) ($item['quantity'] ?? 1));
                }

                foreach ($items as $item) {
                    $qty = (int) ($item['quantity'] ?? 1);
                    $marketValue = ((float) ($item['suggested_selling_price'] ?? 0)) * $qty;
                    $weight = $totalMarketValue > 0 ? $marketValue / $totalMarketValue : 0;
                    $allocated = round($costToAllocate * $weight, 2);
                    $preview[] = [
                        'name' => $item['name'] ?? '',
                        'quantity' => $qty,
                        'allocated_cost' => $allocated,
                        'allocated_cost_per_unit' => $qty > 0 ? round($allocated / $qty, 2) : 0,
                        'cost_allocation_pct' => round($weight * 100, 2),
                    ];
                }
                break;

            case PartOutTemplate::METHOD_PERCENTAGE:
                foreach ($items as $item) {
                    $qty = (int) ($item['quantity'] ?? 1);
                    $pct = (float) ($item['cost_allocation_pct'] ?? 0);
                    $allocated = round($costToAllocate * ($pct / 100), 2);
                    $preview[] = [
                        'name' => $item['name'] ?? '',
                        'quantity' => $qty,
                        'allocated_cost' => $allocated,
                        'allocated_cost_per_unit' => $qty > 0 ? round($allocated / $qty, 2) : 0,
                        'cost_allocation_pct' => $pct,
                    ];
                }
                break;

            case PartOutTemplate::METHOD_EVEN:
            default:
                $totalQuantity = 0;
                foreach ($items as $item) {
                    $totalQuantity += (int) ($item['quantity'] ?? 1);
                }

                foreach ($items as $item) {
                    $qty = (int) ($item['quantity'] ?? 1);
                    $weight = $totalQuantity > 0 ? $qty / $totalQuantity : 0;
                    $allocated = round($costToAllocate * $weight, 2);
                    $preview[] = [
                        'name' => $item['name'] ?? '',
                        'quantity' => $qty,
                        'allocated_cost' => $allocated,
                        'allocated_cost_per_unit' => $qty > 0 ? round($allocated / $qty, 2) : 0,
                        'cost_allocation_pct' => round($weight * 100, 2),
                    ];
                }
                break;
        }

        return [
            'cost_to_allocate' => $costToAllocate,
            'remaining_parent_cost' => $remainingParentCost,
            'items' => $preview,
        ];
    }

    public function getStats(string $organizationId): array
    {
        $base = PartOutWorkOrder::withoutGlobalScopes()->where('organization_id', $organizationId);

        $totalComponentsCreated = Product::withoutGlobalScopes()
            ->whereIn('part_out_work_order_id', function ($query) use ($organizationId) {
                $query->select('id')
                    ->from('part_out_work_orders')
                    ->where('organization_id', $organizationId);
            })
            ->count();

        return [
            'total' => (clone $base)->count(),
            'draft' => (clone $base)->where('status', PartOutWorkOrder::STATUS_DRAFT)->count(),
            'in_progress' => (clone $base)->where('status', PartOutWorkOrder::STATUS_IN_PROGRESS)->count(),
            'completed' => (clone $base)->where('status', PartOutWorkOrder::STATUS_COMPLETED)->count(),
            'cancelled' => (clone $base)->where('status', PartOutWorkOrder::STATUS_CANCELLED)->count(),
            'total_components_created' => $totalComponentsCreated,
        ];
    }

    // -------------------------------------------------------------------------
    // Private helpers
    // -------------------------------------------------------------------------

    protected function getActiveItems(PartOutWorkOrder $workOrder)
    {
        return $workOrder->items->where('extraction_status', '!=', PartOutWorkOrderItem::EXTRACTION_SKIPPED);
    }

    protected function allocateByMarketValue(PartOutWorkOrder $workOrder, float $costToAllocate): void
    {
        $items = $this->getActiveItems($workOrder);
        $totalMarketValue = 0;
        foreach ($items as $item) {
            $totalMarketValue += ((float) $item->suggested_selling_price) * $item->quantity;
        }

        foreach ($items as $item) {
            $marketValue = ((float) $item->suggested_selling_price) * $item->quantity;
            $weight = $totalMarketValue > 0 ? $marketValue / $totalMarketValue : 0;
            $allocated = round($costToAllocate * $weight, 2);

            $item->update([
                'allocated_cost' => $allocated,
                'allocated_cost_per_unit' => $item->quantity > 0 ? round($allocated / $item->quantity, 2) : 0,
                'cost_allocation_pct' => round($weight * 100, 2),
            ]);
        }
    }

    protected function allocateByPercentage(PartOutWorkOrder $workOrder, float $costToAllocate): void
    {
        $items = $this->getActiveItems($workOrder);
        // Recalculate percentages proportionally if some items were skipped
        $totalPct = $items->sum('cost_allocation_pct');
        $scaleFactor = $totalPct > 0 ? 100 / $totalPct : 1;

        foreach ($items as $item) {
            $pct = (float) $item->cost_allocation_pct * $scaleFactor;
            $allocated = round($costToAllocate * ($pct / 100), 2);

            $item->update([
                'allocated_cost' => $allocated,
                'allocated_cost_per_unit' => $item->quantity > 0 ? round($allocated / $item->quantity, 2) : 0,
                'cost_allocation_pct' => round($pct, 2),
            ]);
        }
    }

    protected function allocateEvenly(PartOutWorkOrder $workOrder, float $costToAllocate): void
    {
        $items = $this->getActiveItems($workOrder);
        $totalQuantity = $items->sum('quantity');

        foreach ($items as $item) {
            $weight = $totalQuantity > 0 ? $item->quantity / $totalQuantity : 0;
            $allocated = round($costToAllocate * $weight, 2);

            $item->update([
                'allocated_cost' => $allocated,
                'allocated_cost_per_unit' => $item->quantity > 0 ? round($allocated / $item->quantity, 2) : 0,
                'cost_allocation_pct' => round($weight * 100, 2),
            ]);
        }
    }

    protected function generateComponentSku(?string $category): string
    {
        $prefix = $category ? strtoupper(substr($category, 0, 3)) : 'CMP';

        return $prefix.'-'.substr(Str::uuid()->toString(), 0, 8);
    }

    protected function createComponentProduct(
        PartOutWorkOrder $workOrder,
        PartOutWorkOrderItem $woItem,
        Product $sourceProduct,
        int $index
    ): Product {
        $totalQty = $woItem->quantity * $workOrder->source_quantity;

        return Product::create([
            'organization_id' => $workOrder->organization_id,
            'name' => $woItem->name,
            'sku' => $this->generateComponentSku($woItem->category),
            'cost_price' => (float) $woItem->allocated_cost_per_unit,
            'condition' => $woItem->condition,
            'available_quantity' => $totalQty,
            'stock_quantity' => $totalQty,
            'parted_from_product_id' => $sourceProduct->id,
            'part_out_work_order_id' => $workOrder->id,
        ]);
    }

    protected function createConsumeMovement(PartOutWorkOrder $workOrder, Product $sourceProduct): void
    {
        InventoryMovement::create([
            'organization_id' => $workOrder->organization_id,
            'product_id' => $sourceProduct->id,
            'location_id' => $workOrder->location_id,
            'type' => InventoryMovement::TYPE_PARTOUT_CONSUME,
            'quantity' => -$workOrder->source_quantity,
            'unit_cost' => (float) $workOrder->source_unit_cost,
            'reference_type' => 'part_out_work_order',
            'reference_id' => $workOrder->id,
            'reference_number' => $workOrder->work_order_number,
            'notes' => 'Source product consumed by part-out',
            'created_by' => $workOrder->completed_by ?? $workOrder->created_by,
            'balance_before' => $sourceProduct->available_quantity,
            'balance_after' => $sourceProduct->available_quantity - $workOrder->source_quantity,
        ]);
    }

    protected function createProduceMovement(
        PartOutWorkOrder $workOrder,
        Product $componentProduct,
        PartOutWorkOrderItem $woItem
    ): void {
        $totalQty = $woItem->quantity * $workOrder->source_quantity;

        InventoryMovement::create([
            'organization_id' => $workOrder->organization_id,
            'product_id' => $componentProduct->id,
            'location_id' => $workOrder->location_id,
            'type' => InventoryMovement::TYPE_PARTOUT_PRODUCE,
            'quantity' => $totalQty,
            'unit_cost' => (float) $woItem->allocated_cost_per_unit,
            'reference_type' => 'part_out_work_order',
            'reference_id' => $workOrder->id,
            'reference_number' => $workOrder->work_order_number,
            'notes' => "Component produced from part-out of {$woItem->name}",
            'created_by' => $workOrder->completed_by ?? $workOrder->created_by,
            'balance_before' => 0,
            'balance_after' => $totalQty,
        ]);
    }

    protected function createComponentInventoryLevel(
        PartOutWorkOrder $workOrder,
        Product $componentProduct,
        PartOutWorkOrderItem $woItem
    ): void {
        $totalQty = $woItem->quantity * $workOrder->source_quantity;

        InventoryLevel::create([
            'organization_id' => $workOrder->organization_id,
            'product_id' => $componentProduct->id,
            'location_id' => $workOrder->location_id,
            'quantity_on_hand' => $totalQty,
            'quantity_reserved' => 0,
            'average_cost' => (float) $woItem->allocated_cost_per_unit,
            'last_movement_at' => now(),
        ]);
    }

    protected function updateParentProduct(PartOutWorkOrder $workOrder, Product $sourceProduct): void
    {
        if ($workOrder->parent_continues) {
            // Parent product continues to exist with adjusted cost
            $updates = [];

            if ($workOrder->parent_new_name) {
                $updates['name'] = $workOrder->parent_new_name;
            }

            $remainingCost = (float) ($workOrder->remaining_parent_cost ?? 0);
            if ($workOrder->source_quantity > 0) {
                $updates['cost_price'] = round($remainingCost / $workOrder->source_quantity, 2);
            }

            if (! empty($updates)) {
                $sourceProduct->update($updates);
            }
        } else {
            // Parent product is consumed
            $sourceProduct->decrementAvailableQuantity($workOrder->source_quantity);

            // Refresh to check current quantity
            $sourceProduct->refresh();
            if ($sourceProduct->available_quantity <= 0) {
                $sourceProduct->update(['is_parted_out' => true]);
            }
        }

        // Update inventory level for source product at this location
        if ($workOrder->location_id && ! $workOrder->parent_continues) {
            $level = InventoryLevel::where('product_id', $sourceProduct->id)
                ->where('location_id', $workOrder->location_id)
                ->first();

            if ($level) {
                $level->decrement('quantity_on_hand', $workOrder->source_quantity);
                $level->update(['last_movement_at' => now()]);
            }
        }
    }
}
