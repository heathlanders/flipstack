<?php

namespace App\Policies\Inventory;

use App\Models\Catalog\Category;
use App\Models\User\User;

class CategoryPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission('inventory.categories.view');
    }

    public function view(User $user, Category $category): bool
    {
        return $user->hasPermission('inventory.categories.view') &&
               $user->current_organization_id === $category->organization_id;
    }

    public function create(User $user): bool
    {
        return $user->hasPermission('inventory.categories.create');
    }

    public function update(User $user, Category $category): bool
    {
        return $user->hasPermission('inventory.categories.update') &&
               $user->current_organization_id === $category->organization_id;
    }

    public function delete(User $user, Category $category): bool
    {
        return $user->hasPermission('inventory.categories.delete') &&
               $user->current_organization_id === $category->organization_id;
    }
}
