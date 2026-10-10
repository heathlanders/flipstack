<?php

namespace App\Observers\Inventory;

use App\Jobs\Inventory\UpdateInventoryFromMovement;
use App\Models\Inventory\InventoryMovement;

class InventoryMovementObserver
{
    public function created(InventoryMovement $movement): void
    {
        // Dispatch job to update inventory levels
        UpdateInventoryFromMovement::dispatch($movement->id);
    }
}
