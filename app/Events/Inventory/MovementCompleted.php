<?php

namespace App\Events\Inventory;

use App\Models\Inventory\InventoryMovement;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Event dispatched when an inventory movement is completed.
 *
 * This provides real-time updates about inventory movements
 * such as purchases, sales, transfers, adjustments, etc.
 */
class MovementCompleted implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    /**
     * The inventory movement instance.
     */
    public InventoryMovement $movement;

    /**
     * Create a new event instance.
     */
    public function __construct(InventoryMovement $movement)
    {
        $this->movement = $movement;
    }

    /**
     * Get the channels the event should broadcast on.
     *
     * @return array<int, \Illuminate\Broadcasting\Channel>
     */
    public function broadcastOn(): array
    {
        return [
            new PrivateChannel("organization.{$this->movement->organization_id}"),
            new PrivateChannel("product.{$this->movement->product_id}"),
            new PrivateChannel("location.{$this->movement->location_id}"),
        ];
    }

    /**
     * The event's broadcast name.
     */
    public function broadcastAs(): string
    {
        return 'movement.completed';
    }

    /**
     * Get the data to broadcast.
     *
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        $movement = $this->movement;

        // Load relationships if not already loaded
        $movement->loadMissing(['product', 'location', 'creator']);

        return [
            'movement_id' => $movement->id,
            'movement_type' => $movement->type,
            'movement_direction' => $this->getMovementDirection(),
            'quantity' => $movement->quantity,

            // Product info
            'product_id' => $movement->product_id,
            'product_name' => $movement->product?->name,
            'sku' => $movement->product?->sku,
            'variation_id' => $movement->product_variation_id,

            // Location info
            'location_id' => $movement->location_id,
            'location_name' => $movement->location?->name,

            // Reference info
            'reference_type' => $movement->reference_type,
            'reference_id' => $movement->reference_id,
            'reference_number' => $movement->reference_number,

            // Balance info
            'balance_before' => $movement->balance_before,
            'balance_after' => $movement->balance_after,

            // Cost info
            'unit_cost' => $movement->unit_cost,
            'total_cost' => $movement->total_cost,

            // User info
            'user_id' => $movement->created_by,
            'user_name' => $movement->creator?->name,

            // Notes
            'reason' => $movement->reason,
            'notes' => $movement->notes,

            // Batch/Lot tracking
            'batch_number' => $movement->batch_number,
            'lot_number' => $movement->lot_number,

            // Timestamp
            'timestamp' => $movement->created_at?->toISOString(),
        ];
    }

    /**
     * Get the movement direction (in/out).
     */
    protected function getMovementDirection(): string
    {
        return match ($this->movement->type) {
            'purchase', 'return', 'found', 'transfer_in' => 'in',
            'sale', 'damaged', 'lost', 'transfer_out' => 'out',
            'adjustment' => $this->movement->balance_after >= $this->movement->balance_before ? 'in' : 'out',
            default => 'unknown',
        };
    }

    /**
     * Get the queue name for the broadcast.
     */
    public function broadcastQueue(): string
    {
        return 'broadcasts';
    }
}
