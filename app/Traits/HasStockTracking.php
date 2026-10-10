<?php

namespace App\Traits;

use App\Events\Inventory\LowStockAlert;
use App\Events\Inventory\StockUpdated;
use App\Models\Inventory\InventoryLevel;
use App\Models\Inventory\InventoryMovement;
use Illuminate\Support\Facades\DB;

/**
 * Trait HasStockTracking
 *
 * Add this trait to the Product model to enable stock tracking
 * via the inventory_levels table with real-time event dispatching.
 *
 * Usage:
 * class Product extends Model
 * {
 *     use HasUuids, HasStockTracking;
 * }
 *
 * Note: This trait works with the inventory_levels table schema:
 * - quantity_on_hand: actual stock count
 * - quantity_reserved: reserved for pending orders
 * - quantity_available: computed (on_hand - reserved)
 * - reorder_point: low stock threshold
 * - max_stock_level: overstocked threshold
 */
trait HasStockTracking
{
    /**
     * Get all inventory levels for this product across all locations.
     */
    public function inventoryLevels()
    {
        return $this->hasMany(InventoryLevel::class, 'product_id');
    }

    /**
     * Get inventory movements for this product.
     */
    public function inventoryMovements()
    {
        return $this->hasMany(InventoryMovement::class, 'product_id');
    }

    /**
     * Get the total quantity on hand across all locations.
     */
    public function getTotalQuantityOnHandAttribute(): int
    {
        return $this->inventoryLevels()->sum('quantity_on_hand') ?? 0;
    }

    /**
     * Get the total quantity reserved across all locations.
     */
    public function getTotalQuantityReservedAttribute(): int
    {
        return $this->inventoryLevels()->sum('quantity_reserved') ?? 0;
    }

    /**
     * Get the total quantity available across all locations.
     */
    public function getTotalQuantityAvailableAttribute(): int
    {
        return $this->inventoryLevels()->sum('quantity_available') ?? 0;
    }

    /**
     * Get inventory level at a specific location.
     */
    public function getInventoryAt(string $locationId, ?string $variationId = null): ?InventoryLevel
    {
        return $this->inventoryLevels()
            ->where('location_id', $locationId)
            ->where('product_variation_id', $variationId)
            ->first();
    }

    /**
     * Check if the product is low on stock at any location.
     */
    public function isLowStock(?string $locationId = null): bool
    {
        $query = $this->inventoryLevels()
            ->whereRaw('quantity_available <= reorder_point')
            ->where('reorder_point', '>', 0);

        if ($locationId) {
            $query->where('location_id', $locationId);
        }

        return $query->exists();
    }

    /**
     * Check if the product is out of stock at any/all locations.
     */
    public function isOutOfStock(?string $locationId = null): bool
    {
        $query = $this->inventoryLevels();

        if ($locationId) {
            $level = $query->where('location_id', $locationId)->first();

            return ! $level || $level->quantity_available <= 0;
        }

        // Check if total available is zero
        return $this->total_quantity_available <= 0;
    }

    /**
     * Check if the product is overstocked at any location.
     */
    public function isOverstocked(?string $locationId = null): bool
    {
        $query = $this->inventoryLevels()
            ->whereNotNull('max_stock_level')
            ->whereRaw('quantity_on_hand > max_stock_level');

        if ($locationId) {
            $query->where('location_id', $locationId);
        }

        return $query->exists();
    }

    /**
     * Get the stock status for a specific location or overall.
     */
    public function getStockStatus(?string $locationId = null): string
    {
        if ($this->isOutOfStock($locationId)) {
            return 'out_of_stock';
        }

        if ($this->isLowStock($locationId)) {
            return 'low_stock';
        }

        if ($this->isOverstocked($locationId)) {
            return 'overstocked';
        }

        return 'in_stock';
    }

    /**
     * Adjust stock at a specific location with movement tracking.
     *
     * @param  string  $locationId  Location UUID
     * @param  int  $adjustment  Positive or negative adjustment
     * @param  string  $movementType  Type of movement (see InventoryMovement constants)
     * @param  array  $options  Additional options (reference_type, reference_id, notes, etc.)
     */
    public function adjustStock(
        string $locationId,
        int $adjustment,
        string $movementType = 'adjustment',
        array $options = []
    ): ?InventoryLevel {
        return DB::transaction(function () use ($locationId, $adjustment, $movementType, $options) {
            $organizationId = $this->organization_id;
            $variationId = $options['variation_id'] ?? null;

            // Get or create inventory level
            $level = InventoryLevel::firstOrCreate(
                [
                    'organization_id' => $organizationId,
                    'product_id' => $this->id,
                    'product_variation_id' => $variationId,
                    'location_id' => $locationId,
                ],
                [
                    'quantity_on_hand' => 0,
                    'quantity_reserved' => 0,
                    'reorder_point' => $options['reorder_point'] ?? 5,
                    'reorder_quantity' => $options['reorder_quantity'] ?? 10,
                    'average_cost' => $options['average_cost'] ?? 0,
                ]
            );

            $previousQuantity = $level->quantity_on_hand;
            $newQuantity = max(0, $previousQuantity + $adjustment);

            // Create movement record
            InventoryMovement::create([
                'organization_id' => $organizationId,
                'product_id' => $this->id,
                'product_variation_id' => $variationId,
                'location_id' => $locationId,
                'type' => $movementType,
                'quantity' => abs($adjustment),
                'unit_cost' => $options['unit_cost'] ?? null,
                'total_cost' => isset($options['unit_cost']) ? abs($adjustment) * $options['unit_cost'] : null,
                'reference_type' => $options['reference_type'] ?? null,
                'reference_id' => $options['reference_id'] ?? null,
                'reference_number' => $options['reference_number'] ?? null,
                'reason' => $options['reason'] ?? null,
                'notes' => $options['notes'] ?? null,
                'batch_number' => $options['batch_number'] ?? null,
                'lot_number' => $options['lot_number'] ?? null,
                'expiry_date' => $options['expiry_date'] ?? null,
                'created_by' => $options['created_by'] ?? auth()->id() ?? 1,
                'balance_before' => $previousQuantity,
                'balance_after' => $newQuantity,
            ]);

            // Update inventory level with optimistic locking
            $level->quantity_on_hand = $newQuantity;
            $level->last_movement_at = now();
            $level->version = $level->version + 1;
            $level->save();

            // Dispatch stock updated event
            event(new StockUpdated(
                $this,
                $level,
                $previousQuantity,
                $newQuantity,
                $movementType,
                $options['created_by'] ?? auth()->id()
            ));

            // Check for low stock alert
            $this->checkAndDispatchStockAlert($level, $previousQuantity);

            return $level;
        });
    }

    /**
     * Set stock to a specific quantity at a location.
     */
    public function setStock(
        string $locationId,
        int $quantity,
        string $reason = 'Manual adjustment',
        array $options = []
    ): ?InventoryLevel {
        $level = $this->getInventoryAt($locationId, $options['variation_id'] ?? null);
        $currentQuantity = $level?->quantity_on_hand ?? 0;
        $adjustment = $quantity - $currentQuantity;

        $options['reason'] = $reason;

        return $this->adjustStock($locationId, $adjustment, 'adjustment', $options);
    }

    /**
     * Increment stock (purchase, return, found, transfer_in).
     */
    public function incrementStock(
        string $locationId,
        int $amount,
        string $movementType = 'purchase',
        array $options = []
    ): ?InventoryLevel {
        return $this->adjustStock($locationId, abs($amount), $movementType, $options);
    }

    /**
     * Decrement stock (sale, damaged, lost, transfer_out).
     */
    public function decrementStock(
        string $locationId,
        int $amount,
        string $movementType = 'sale',
        array $options = []
    ): ?InventoryLevel {
        return $this->adjustStock($locationId, -abs($amount), $movementType, $options);
    }

    /**
     * Reserve stock for a pending order.
     */
    public function reserveStock(string $locationId, int $amount, array $options = []): bool
    {
        $level = $this->getInventoryAt($locationId, $options['variation_id'] ?? null);

        if (! $level || $level->quantity_available < $amount) {
            return false;
        }

        $level->quantity_reserved += $amount;
        $level->version += 1;
        $level->save();

        return true;
    }

    /**
     * Release reserved stock (order cancelled or fulfilled).
     */
    public function releaseReservation(string $locationId, int $amount, array $options = []): bool
    {
        $level = $this->getInventoryAt($locationId, $options['variation_id'] ?? null);

        if (! $level) {
            return false;
        }

        $level->quantity_reserved = max(0, $level->quantity_reserved - $amount);
        $level->version += 1;
        $level->save();

        return true;
    }

    /**
     * Fulfill a reservation (convert reserved to sold).
     */
    public function fulfillReservation(
        string $locationId,
        int $amount,
        array $options = []
    ): ?InventoryLevel {
        return DB::transaction(function () use ($locationId, $amount, $options) {
            // Release the reservation
            $this->releaseReservation($locationId, $amount, $options);

            // Decrement the actual stock
            return $this->decrementStock($locationId, $amount, 'sale', $options);
        });
    }

    /**
     * Transfer stock between locations.
     */
    public function transferStock(
        string $fromLocationId,
        string $toLocationId,
        int $quantity,
        array $options = []
    ): bool {
        return DB::transaction(function () use ($fromLocationId, $toLocationId, $quantity, $options) {
            // Check source has enough stock
            $sourceLevel = $this->getInventoryAt($fromLocationId, $options['variation_id'] ?? null);

            if (! $sourceLevel || $sourceLevel->quantity_available < $quantity) {
                return false;
            }

            $transferRef = $options['reference_number'] ?? 'TRF-'.now()->format('YmdHis');

            // Transfer out from source
            $this->decrementStock($fromLocationId, $quantity, 'transfer_out', array_merge($options, [
                'reference_number' => $transferRef,
                'notes' => "Transfer to location: {$toLocationId}",
            ]));

            // Transfer in to destination
            $this->incrementStock($toLocationId, $quantity, 'transfer_in', array_merge($options, [
                'reference_number' => $transferRef,
                'notes' => "Transfer from location: {$fromLocationId}",
            ]));

            return true;
        });
    }

    /**
     * Check if the product can fulfill a given quantity.
     */
    public function canFulfill(int $quantity, ?string $locationId = null): bool
    {
        if ($locationId) {
            $level = $this->getInventoryAt($locationId);

            return $level && $level->quantity_available >= $quantity;
        }

        return $this->total_quantity_available >= $quantity;
    }

    /**
     * Get locations with low stock.
     */
    public function getLowStockLocations(): \Illuminate\Database\Eloquent\Collection
    {
        return $this->inventoryLevels()
            ->whereRaw('quantity_available <= reorder_point')
            ->where('reorder_point', '>', 0)
            ->with('location')
            ->get();
    }

    /**
     * Get locations that are out of stock.
     */
    public function getOutOfStockLocations(): \Illuminate\Database\Eloquent\Collection
    {
        return $this->inventoryLevels()
            ->where('quantity_on_hand', '<=', 0)
            ->with('location')
            ->get();
    }

    /**
     * Check and dispatch stock alert if threshold is crossed.
     */
    protected function checkAndDispatchStockAlert(InventoryLevel $level, int $previousQuantity): void
    {
        $currentAvailable = $level->quantity_available;
        $reorderPoint = $level->reorder_point;
        $maxStock = $level->max_stock_level;

        // Determine previous available (approximate)
        $previousAvailable = $previousQuantity - $level->quantity_reserved;

        // Check for out of stock transition
        if ($currentAvailable <= 0 && $previousAvailable > 0) {
            event(new LowStockAlert($this, $level, 'out_of_stock', $previousQuantity));

            return;
        }

        // Check for low stock transition
        if ($reorderPoint > 0 && $currentAvailable <= $reorderPoint && $previousAvailable > $reorderPoint) {
            event(new LowStockAlert($this, $level, 'low_stock', $previousQuantity));

            return;
        }

        // Check for overstocked transition
        if ($maxStock && $level->quantity_on_hand > $maxStock && $previousQuantity <= $maxStock) {
            event(new LowStockAlert($this, $level, 'overstocked', $previousQuantity));
        }
    }
}
