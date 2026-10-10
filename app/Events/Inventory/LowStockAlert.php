<?php

namespace App\Events\Inventory;

use App\Models\Catalog\Product;
use App\Models\Inventory\InventoryLevel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Event dispatched when stock crosses a threshold boundary.
 *
 * Alert types:
 * - 'out_of_stock': quantity_available dropped to 0 or below
 * - 'low_stock': quantity_available dropped below reorder_point
 * - 'overstocked': quantity_on_hand exceeded max_stock_level
 */
class LowStockAlert implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    /**
     * The product instance.
     */
    public Product $product;

    /**
     * The inventory level instance.
     */
    public InventoryLevel $inventoryLevel;

    /**
     * The type of alert (low_stock, out_of_stock, overstocked).
     */
    public string $alertType;

    /**
     * The previous quantity before the change.
     */
    public int $previousQuantity;

    /**
     * Create a new event instance.
     */
    public function __construct(
        Product $product,
        InventoryLevel $inventoryLevel,
        string $alertType,
        int $previousQuantity
    ) {
        $this->product = $product;
        $this->inventoryLevel = $inventoryLevel;
        $this->alertType = $alertType;
        $this->previousQuantity = $previousQuantity;
    }

    /**
     * Get the channels the event should broadcast on.
     *
     * @return array<int, \Illuminate\Broadcasting\Channel>
     */
    public function broadcastOn(): array
    {
        return [
            new PrivateChannel("organization.{$this->product->organization_id}"),
            new PrivateChannel("inventory.alerts.{$this->product->organization_id}"),
            new PrivateChannel("location.{$this->inventoryLevel->location_id}"),
        ];
    }

    /**
     * The event's broadcast name.
     */
    public function broadcastAs(): string
    {
        return 'stock.alert';
    }

    /**
     * Get the data to broadcast.
     *
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        $level = $this->inventoryLevel;

        return [
            'alert_type' => $this->alertType,
            'severity' => $this->getSeverity(),

            // Product info
            'product_id' => $this->product->id,
            'product_name' => $this->product->name,
            'sku' => $this->product->sku,

            // Location info
            'location_id' => $level->location_id,
            'variation_id' => $level->product_variation_id,

            // Current inventory state
            'quantity_on_hand' => $level->quantity_on_hand,
            'quantity_reserved' => $level->quantity_reserved,
            'quantity_available' => $level->quantity_available,

            // Threshold info
            'reorder_point' => $level->reorder_point,
            'reorder_quantity' => $level->reorder_quantity,
            'max_stock_level' => $level->max_stock_level,

            // Change info
            'previous_quantity' => $this->previousQuantity,

            // Timestamps
            'timestamp' => now()->toISOString(),
        ];
    }

    /**
     * Get the severity level of the alert.
     */
    public function getSeverity(): string
    {
        return match ($this->alertType) {
            'out_of_stock' => 'critical',
            'low_stock' => 'warning',
            'overstocked' => 'info',
            default => 'info',
        };
    }

    /**
     * Get the queue name for the broadcast.
     */
    public function broadcastQueue(): string
    {
        return 'broadcasts';
    }

    /**
     * Determine the alert type based on inventory level thresholds.
     *
     * @return string|null Returns alert type or null if no alert needed
     */
    public static function determineAlertType(InventoryLevel $level): ?string
    {
        // Out of stock takes priority
        if ($level->quantity_available <= 0) {
            return 'out_of_stock';
        }

        // Check for low stock
        if ($level->reorder_point > 0 && $level->quantity_available <= $level->reorder_point) {
            return 'low_stock';
        }

        // Check for overstocked
        if ($level->max_stock_level && $level->quantity_on_hand > $level->max_stock_level) {
            return 'overstocked';
        }

        return null;
    }

    /**
     * Determine the alert type for a product based on its inventory levels.
     * Returns the most severe alert type found across all locations.
     */
    public static function determineProductAlertType(Product $product): ?string
    {
        $levels = $product->inventoryLevels;

        $hasOutOfStock = false;
        $hasLowStock = false;
        $hasOverstocked = false;

        foreach ($levels as $level) {
            $alertType = self::determineAlertType($level);

            if ($alertType === 'out_of_stock') {
                $hasOutOfStock = true;
            } elseif ($alertType === 'low_stock') {
                $hasLowStock = true;
            } elseif ($alertType === 'overstocked') {
                $hasOverstocked = true;
            }
        }

        // Return most severe alert
        if ($hasOutOfStock) {
            return 'out_of_stock';
        }
        if ($hasLowStock) {
            return 'low_stock';
        }
        if ($hasOverstocked) {
            return 'overstocked';
        }

        return null;
    }
}
