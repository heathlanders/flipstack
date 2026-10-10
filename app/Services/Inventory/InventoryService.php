<?php

// app/Services/Inventory/InventoryService.php

namespace App\Services\Inventory;

use App\Exceptions\InsufficientStockException;
use App\Exceptions\InventoryException;
use App\Models\Catalog\Product;
use App\Models\Inventory\InventoryLevel;
use App\Models\Inventory\InventoryMovement;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class InventoryService
{
    public function __construct(
        private InventoryMovementService $movementService
    ) {}

    /**
     * Adjust inventory levels with proper event sourcing
     */
    public function adjustStock(
        string $productId,
        string $locationId,
        int $quantity,
        string $reason = 'Manual adjustment',
        ?string $productVariationId = null,
        ?float $unitCost = null,
        ?array $metadata = []
    ): InventoryMovement {
        return DB::transaction(function () use (
            $productId, $locationId, $quantity, $reason, $productVariationId, $unitCost, $metadata
        ) {
            // Get current inventory level
            $inventoryLevel = $this->getOrCreateInventoryLevel($productId, $locationId, $productVariationId);

            // Record the movement
            $movement = $this->movementService->recordMovement([
                'organization_id' => $inventoryLevel->organization_id,
                'product_id' => $productId,
                'product_variation_id' => $productVariationId,
                'location_id' => $locationId,
                'type' => InventoryMovement::TYPE_ADJUSTMENT,
                'quantity' => $quantity,
                'unit_cost' => $unitCost,
                'total_cost' => $unitCost ? $unitCost * abs($quantity) : null,
                'reason' => $reason,
                'notes' => json_encode($metadata),
                'balance_before' => $inventoryLevel->quantity_on_hand,
                'balance_after' => $inventoryLevel->quantity_on_hand + $quantity,
                'created_by' => auth()->id(),
            ]);

            // Update average cost if provided
            if ($unitCost !== null && $quantity > 0) {
                $this->updateAverageCost($inventoryLevel, $quantity, $unitCost);
            }

            return $movement;
        });
    }

    /**
     * Transfer stock between locations
     */
    public function transferStock(
        string $productId,
        string $fromLocationId,
        string $toLocationId,
        int $quantity,
        ?string $productVariationId = null,
        ?string $reason = 'Stock transfer'
    ): array {
        return DB::transaction(function () use (
            $productId, $fromLocationId, $toLocationId, $quantity, $productVariationId, $reason
        ) {
            // Validate source location has sufficient stock
            $fromLevel = $this->getInventoryLevel($productId, $fromLocationId, $productVariationId);

            if (! $fromLevel || $fromLevel->quantity_available < $quantity) {
                throw new InsufficientStockException(
                    "Insufficient stock for transfer. Available: {$fromLevel?->quantity_available}, Required: {$quantity}"
                );
            }

            // Get or create destination inventory level
            $toLevel = $this->getOrCreateInventoryLevel($productId, $toLocationId, $productVariationId);

            // Record outbound movement
            $outMovement = $this->movementService->recordMovement([
                'organization_id' => $fromLevel->organization_id,
                'product_id' => $productId,
                'product_variation_id' => $productVariationId,
                'location_id' => $fromLocationId,
                'type' => InventoryMovement::TYPE_TRANSFER_OUT,
                'quantity' => -$quantity,
                'unit_cost' => $fromLevel->average_cost,
                'total_cost' => $fromLevel->average_cost * $quantity,
                'reason' => $reason,
                'reference_type' => 'transfer',
                'reference_id' => $toLocationId,
                'balance_before' => $fromLevel->quantity_on_hand,
                'balance_after' => $fromLevel->quantity_on_hand - $quantity,
                'created_by' => auth()->id(),
            ]);

            // Record inbound movement
            $inMovement = $this->movementService->recordMovement([
                'organization_id' => $toLevel->organization_id,
                'product_id' => $productId,
                'product_variation_id' => $productVariationId,
                'location_id' => $toLocationId,
                'type' => InventoryMovement::TYPE_TRANSFER_IN,
                'quantity' => $quantity,
                'unit_cost' => $fromLevel->average_cost,
                'total_cost' => $fromLevel->average_cost * $quantity,
                'reason' => $reason,
                'reference_type' => 'transfer',
                'reference_id' => $fromLocationId,
                'balance_before' => $toLevel->quantity_on_hand,
                'balance_after' => $toLevel->quantity_on_hand + $quantity,
                'created_by' => auth()->id(),
            ]);

            // Update average cost at destination
            $this->updateAverageCost($toLevel, $quantity, $fromLevel->average_cost);

            return [$outMovement, $inMovement];
        });
    }

    /**
     * Reserve stock for orders
     */
    public function reserveStock(
        string $productId,
        string $locationId,
        int $quantity,
        string $referenceType,
        string $referenceId,
        ?string $productVariationId = null
    ): bool {
        return DB::transaction(function () use (
            $productId, $locationId, $quantity, $referenceType, $referenceId, $productVariationId
        ) {
            $inventoryLevel = $this->getInventoryLevel($productId, $locationId, $productVariationId);

            if (! $inventoryLevel || ! $inventoryLevel->canReserve($quantity)) {
                throw new InsufficientStockException(
                    "Cannot reserve {$quantity} items. Available: {$inventoryLevel?->quantity_available}"
                );
            }

            // Use optimistic locking to prevent race conditions
            $updated = InventoryLevel::where('id', $inventoryLevel->id)
                ->where('version', $inventoryLevel->version)
                ->where('quantity_on_hand - quantity_reserved', '>=', $quantity)
                ->update([
                    'quantity_reserved' => DB::raw('quantity_reserved + '.$quantity),
                    'version' => DB::raw('version + 1'),
                    'updated_at' => now(),
                ]);

            if (! $updated) {
                throw new InventoryException('Stock reservation failed due to concurrent modification');
            }

            // Log the reservation
            Log::info('Stock reserved', [
                'product_id' => $productId,
                'variation_id' => $productVariationId,
                'location_id' => $locationId,
                'quantity' => $quantity,
                'reference_type' => $referenceType,
                'reference_id' => $referenceId,
            ]);

            return true;
        });
    }

    /**
     * Release reserved stock
     */
    public function releaseReservedStock(
        string $productId,
        string $locationId,
        int $quantity,
        ?string $productVariationId = null
    ): bool {
        return DB::transaction(function () use ($productId, $locationId, $quantity, $productVariationId) {
            $inventoryLevel = $this->getInventoryLevel($productId, $locationId, $productVariationId);

            if (! $inventoryLevel || $inventoryLevel->quantity_reserved < $quantity) {
                throw new InventoryException(
                    "Cannot release {$quantity} reserved items. Reserved: {$inventoryLevel?->quantity_reserved}"
                );
            }

            $updated = InventoryLevel::where('id', $inventoryLevel->id)
                ->where('version', $inventoryLevel->version)
                ->where('quantity_reserved', '>=', $quantity)
                ->update([
                    'quantity_reserved' => DB::raw('quantity_reserved - '.$quantity),
                    'version' => DB::raw('version + 1'),
                    'updated_at' => now(),
                ]);

            if (! $updated) {
                throw new InventoryException('Stock release failed due to concurrent modification');
            }

            return true;
        });
    }

    /**
     * Commit reserved stock (convert reservation to sale)
     */
    public function commitReservedStock(
        string $productId,
        string $locationId,
        int $quantity,
        string $referenceType,
        string $referenceId,
        ?string $productVariationId = null
    ): InventoryMovement {
        return DB::transaction(function () use (
            $productId, $locationId, $quantity, $referenceType, $referenceId, $productVariationId
        ) {
            $inventoryLevel = $this->getInventoryLevel($productId, $locationId, $productVariationId);

            if (! $inventoryLevel || $inventoryLevel->quantity_reserved < $quantity) {
                throw new InventoryException(
                    "Cannot commit {$quantity} reserved items. Reserved: {$inventoryLevel?->quantity_reserved}"
                );
            }

            // Record the sale movement
            $movement = $this->movementService->recordMovement([
                'organization_id' => $inventoryLevel->organization_id,
                'product_id' => $productId,
                'product_variation_id' => $productVariationId,
                'location_id' => $locationId,
                'type' => InventoryMovement::TYPE_SALE,
                'quantity' => -$quantity,
                'unit_cost' => $inventoryLevel->average_cost,
                'total_cost' => $inventoryLevel->average_cost * $quantity,
                'reference_type' => $referenceType,
                'reference_id' => $referenceId,
                'balance_before' => $inventoryLevel->quantity_on_hand,
                'balance_after' => $inventoryLevel->quantity_on_hand - $quantity,
                'created_by' => auth()->id(),
            ]);

            // Update inventory level (this will be handled by the trigger)
            // But we also need to reduce reserved quantity
            InventoryLevel::where('id', $inventoryLevel->id)
                ->update([
                    'quantity_reserved' => DB::raw('quantity_reserved - '.$quantity),
                    'version' => DB::raw('version + 1'),
                ]);

            return $movement;
        });
    }

    /**
     * Get low stock alerts
     */
    public function getLowStockAlerts(string $organizationId): \Illuminate\Database\Eloquent\Collection
    {
        return InventoryLevel::with(['product', 'productVariation', 'location'])
            ->where('organization_id', $organizationId)
            ->whereColumn('quantity_available', '<=', 'reorder_point')
            ->whereHas('product', function ($query) {
                $query->where('status', 'active')
                    ->where('manage_stock', true);
            })
            ->orderBy('quantity_available')
            ->get();
    }

    /**
     * Calculate inventory valuation
     */
    public function calculateInventoryValuation(string $organizationId, ?string $locationId = null): array
    {
        $query = InventoryLevel::where('organization_id', $organizationId)
            ->where('quantity_on_hand', '>', 0);

        if ($locationId) {
            $query->where('location_id', $locationId);
        }

        $levels = $query->get();

        $totalValue = $levels->sum('total_cost');
        $totalQuantity = $levels->sum('quantity_on_hand');
        $productCount = $levels->count();

        return [
            'total_value' => $totalValue,
            'total_quantity' => $totalQuantity,
            'product_count' => $productCount,
            'average_value_per_unit' => $totalQuantity > 0 ? $totalValue / $totalQuantity : 0,
        ];
    }

    /**
     * Get or create inventory level
     */
    private function getOrCreateInventoryLevel(
        string $productId,
        string $locationId,
        ?string $productVariationId = null
    ): InventoryLevel {
        $product = Product::findOrFail($productId);

        return InventoryLevel::firstOrCreate([
            'product_id' => $productId,
            'product_variation_id' => $productVariationId,
            'location_id' => $locationId,
        ], [
            'organization_id' => $product->organization_id,
            'quantity_on_hand' => 0,
            'quantity_reserved' => 0,
            'reorder_point' => 0,
            'reorder_quantity' => 0,
            'average_cost' => 0,
        ]);
    }

    /**
     * Get existing inventory level
     */
    private function getInventoryLevel(
        string $productId,
        string $locationId,
        ?string $productVariationId = null
    ): ?InventoryLevel {
        return InventoryLevel::where('product_id', $productId)
            ->where('location_id', $locationId)
            ->where('product_variation_id', $productVariationId)
            ->first();
    }

    /**
     * Update average cost using weighted average
     */
    private function updateAverageCost(InventoryLevel $inventoryLevel, int $quantity, float $unitCost): void
    {
        if ($quantity <= 0) {
            return;
        }

        $currentValue = $inventoryLevel->quantity_on_hand * $inventoryLevel->average_cost;
        $newValue = $quantity * $unitCost;
        $totalQuantity = $inventoryLevel->quantity_on_hand + $quantity;

        $newAverageCost = $totalQuantity > 0 ? ($currentValue + $newValue) / $totalQuantity : 0;

        $inventoryLevel->update(['average_cost' => $newAverageCost]);
    }
}
