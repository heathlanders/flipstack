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
 * Event dispatched when stock quantity changes at a location.
 *
 * Broadcasts to:
 * - organization.{id} - for organization-wide dashboards
 * - product.{id} - for product-specific listeners
 * - location.{id} - for location-specific monitoring
 */
class StockUpdated implements ShouldBroadcast
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
     * The previous stock quantity (quantity_on_hand).
     */
    public int $previousQuantity;

    /**
     * The new stock quantity (quantity_on_hand).
     */
    public int $newQuantity;

    /**
     * The type of movement that caused the update.
     */
    public ?string $movementType;

    /**
     * The user who initiated the update.
     */
    public ?int $userId;

    /**
     * Create a new event instance.
     */
    public function __construct(
        Product $product,
        InventoryLevel $inventoryLevel,
        int $previousQuantity,
        int $newQuantity,
        ?string $movementType = null,
        ?int $userId = null
    ) {
        $this->product = $product;
        $this->inventoryLevel = $inventoryLevel;
        $this->previousQuantity = $previousQuantity;
        $this->newQuantity = $newQuantity;
        $this->movementType = $movementType;
        $this->userId = $userId ?? auth()->id();
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
            new PrivateChannel("product.{$this->product->id}"),
            new PrivateChannel("location.{$this->inventoryLevel->location_id}"),
        ];
    }

    /**
     * The event's broadcast name.
     */
    public function broadcastAs(): string
    {
        return 'stock.updated';
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
            // Product info
            'product_id' => $this->product->id,
            'product_name' => $this->product->name,
            'sku' => $this->product->sku,

            // Location info
            'location_id' => $level->location_id,
            'variation_id' => $level->product_variation_id,

            // Quantity changes
            'previous_quantity' => $this->previousQuantity,
            'new_quantity' => $this->newQuantity,
            'change' => $this->newQuantity - $this->previousQuantity,

            // Current inventory state
            'quantity_on_hand' => $level->quantity_on_hand,
            'quantity_reserved' => $level->quantity_reserved,
            'quantity_available' => $level->quantity_available,

            // Threshold info
            'reorder_point' => $level->reorder_point,
            'max_stock_level' => $level->max_stock_level,

            // Status flags
            'is_low_stock' => $level->quantity_available <= $level->reorder_point && $level->reorder_point > 0,
            'is_out_of_stock' => $level->quantity_available <= 0,
            'is_overstocked' => $level->max_stock_level && $level->quantity_on_hand > $level->max_stock_level,

            // Movement info
            'movement_type' => $this->movementType,
            'user_id' => $this->userId,

            // Timestamps
            'timestamp' => now()->toISOString(),
        ];
    }

    /**
     * Determine if this event should broadcast.
     */
    public function broadcastWhen(): bool
    {
        // Always broadcast stock updates
        return true;
    }

    /**
     * Get the queue name for the broadcast.
     */
    public function broadcastQueue(): string
    {
        return 'broadcasts';
    }
}
