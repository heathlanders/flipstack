<?php

namespace App\Policies\Inventory;

use App\Models\Inventory\InventoryLocation;
use App\Models\User\User;

class InventoryLocationPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission('inventory.locations.view');
    }

    public function view(User $user, InventoryLocation $inventoryLocation): bool
    {
        return $user->hasPermission('inventory.locations.view') &&
               $user->current_organization_id === $inventoryLocation->organization_id;
    }

    public function create(User $user): bool
    {
        return $user->hasPermission('inventory.locations.create');
    }

    public function update(User $user, InventoryLocation $inventoryLocation): bool
    {
        return $user->hasPermission('inventory.locations.update') &&
               $user->current_organization_id === $inventoryLocation->organization_id;
    }

    public function delete(User $user, InventoryLocation $inventoryLocation): bool
    {
        return $user->hasPermission('inventory.locations.delete') &&
               $user->current_organization_id === $inventoryLocation->organization_id;
    }

    public function restore(User $user, InventoryLocation $inventoryLocation): bool
    {
        return $user->hasPermission('inventory.locations.delete') &&
               $user->current_organization_id === $inventoryLocation->organization_id;
    }

    public function forceDelete(User $user, InventoryLocation $inventoryLocation): bool
    {
        return $user->hasPermission('inventory.locations.delete') &&
               $user->current_organization_id === $inventoryLocation->organization_id;
    }
}
