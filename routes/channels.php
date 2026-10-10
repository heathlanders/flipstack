<?php

use App\Models\Catalog\Product;
use App\Models\Inventory\InventoryLocation;
use App\Models\User\User;
use Illuminate\Support\Facades\Broadcast;

/*
|--------------------------------------------------------------------------
| Broadcast Channels
|--------------------------------------------------------------------------
|
| Here you may register all of the event broadcasting channels that your
| application supports. The given channel authorization callbacks are
| used to check if an authenticated user can listen to the channel.
|
*/

/**
 * Organization channel - user must belong to the organization.
 *
 * Used for broadcasting organization-wide events like:
 * - Stock alerts
 * - Bulk operation completions
 * - Movement notifications
 *
 * Note: organization_id is bigint
 */
Broadcast::channel('organization.{organizationId}', function (User $user, int $organizationId) {
    // Get the user's organization ID (supports both single-org and multi-org patterns)
    $userOrgId = $user->organization_id ?? $user->current_organization_id;

    // Verify user belongs to the organization
    if ($userOrgId === $organizationId) {
        return [
            'id' => $user->id,
            'name' => $user->name,
        ];
    }

    // Also check team memberships if using Jetstream teams
    if (method_exists($user, 'belongsToTeam')) {
        $team = \App\Models\Organization\Team::find($organizationId);
        if ($team && $user->belongsToTeam($team)) {
            return [
                'id' => $user->id,
                'name' => $user->name,
            ];
        }
    }

    return false;
});

/**
 * Product channel - user must have access to the product's organization.
 *
 * Used for broadcasting product-specific events like:
 * - Stock updates for a specific product
 * - Movement completions for a product
 *
 * Note: Product IDs are UUIDs (string), not integers
 */
Broadcast::channel('product.{productId}', function (User $user, string $productId) {
    $product = Product::find($productId);

    if (! $product) {
        return false;
    }

    // Get the user's organization ID
    $userOrgId = $user->organization_id ?? $user->current_organization_id;

    // Verify user belongs to the product's organization
    if ($product->organization_id === $userOrgId) {
        return [
            'id' => $user->id,
            'name' => $user->name,
        ];
    }

    // Check team memberships
    if (method_exists($user, 'belongsToTeam')) {
        $team = \App\Models\Organization\Team::find($product->organization_id);
        if ($team && $user->belongsToTeam($team)) {
            return [
                'id' => $user->id,
                'name' => $user->name,
            ];
        }
    }

    return false;
});

/**
 * User-specific private channel.
 *
 * Used for broadcasting events only to a specific user:
 * - Bulk operation completions they initiated
 * - Personal notifications
 *
 * Note: User IDs are bigint (int)
 */
Broadcast::channel('user.{userId}', function (User $user, int $userId) {
    if ($user->id === $userId) {
        return [
            'id' => $user->id,
            'name' => $user->name,
        ];
    }

    return false;
});

/**
 * Inventory alerts channel - for users with inventory permissions.
 *
 * Used for broadcasting inventory-related alerts to all
 * users with the inventory.view permission.
 *
 * Note: organization_id is bigint
 */
Broadcast::channel('inventory.alerts.{organizationId}', function (User $user, int $organizationId) {
    // Check organization membership
    $userOrgId = $user->organization_id ?? $user->current_organization_id;

    if ($userOrgId !== $organizationId) {
        // Check team memberships
        if (method_exists($user, 'belongsToTeam')) {
            $team = \App\Models\Organization\Team::find($organizationId);
            if (! $team || ! $user->belongsToTeam($team)) {
                return false;
            }
        } else {
            return false;
        }
    }

    // Check for inventory view permission
    if (method_exists($user, 'hasPermission')) {
        return $user->hasPermission('inventory.view') ? [
            'id' => $user->id,
            'name' => $user->name,
        ] : false;
    }

    // Default to true if no permission system
    return [
        'id' => $user->id,
        'name' => $user->name,
    ];
});

/**
 * Presence channel for inventory dashboard.
 *
 * Tracks which users are currently viewing inventory.
 * Useful for collaborative editing features.
 *
 * Note: organization_id is bigint
 */
Broadcast::channel('inventory.presence.{organizationId}', function (User $user, int $organizationId) {
    $userOrgId = $user->organization_id ?? $user->current_organization_id;

    if ($userOrgId === $organizationId) {
        return [
            'id' => $user->id,
            'name' => $user->name,
            'avatar' => $user->profile_photo_url ?? null,
        ];
    }

    return false;
});

/**
 * Queue item channel - for real-time updates to intake queue items.
 * Used by the Chrome extension research flow.
 */
Broadcast::channel('queue-item.{queueItemId}', function (User $user, int $queueItemId) {
    $queueItem = \App\Models\Catalog\ProductIntakeQueue::find($queueItemId);

    if (! $queueItem) {
        return false;
    }

    if (method_exists($user, 'belongsToTeam')) {
        $team = \App\Models\Organization\Team::find($queueItem->team_id);
        if ($team && $user->belongsToTeam($team)) {
            return [
                'id' => $user->id,
                'name' => $user->name,
            ];
        }
    }

    return false;
});

/**
 * Location-specific channel for warehouse/location updates.
 *
 * Note: Location IDs are UUIDs (string), not integers
 */
Broadcast::channel('location.{locationId}', function (User $user, string $locationId) {
    $location = InventoryLocation::find($locationId);

    if (! $location) {
        return false;
    }

    $userOrgId = $user->organization_id ?? $user->current_organization_id;

    return $location->organization_id === $userOrgId ? [
        'id' => $user->id,
        'name' => $user->name,
    ] : false;
});
