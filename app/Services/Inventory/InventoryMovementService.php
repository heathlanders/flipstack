<?php

namespace App\Services\Inventory;

use App\Events\Inventory\InventoryMovementCreated;
use App\Models\Inventory\InventoryMovement;
use Illuminate\Support\Facades\Event;

class InventoryMovementService
{
    /**
     * Record an inventory movement
     */
    public function recordMovement(array $data): InventoryMovement
    {
        $movement = InventoryMovement::create($data);

        // Dispatch event for real-time updates
        Event::dispatch(new InventoryMovementCreated($movement));

        return $movement;
    }

    /**
     * Get movement history for a product
     */
    public function getMovementHistory(
        string $productId,
        ?string $locationId = null,
        ?string $productVariationId = null,
        ?int $limit = 50
    ): \Illuminate\Database\Eloquent\Collection {
        $query = InventoryMovement::with(['location', 'creator'])
            ->where('product_id', $productId);

        if ($locationId) {
            $query->where('location_id', $locationId);
        }

        if ($productVariationId) {
            $query->where('product_variation_id', $productVariationId);
        }

        return $query->orderBy('created_at', 'desc')
            ->limit($limit)
            ->get();
    }

    /**
     * Get movement summary by type
     */
    public function getMovementSummary(
        string $organizationId,
        ?\Carbon\Carbon $startDate = null,
        ?\Carbon\Carbon $endDate = null
    ): array {
        $query = InventoryMovement::where('organization_id', $organizationId);

        if ($startDate) {
            $query->where('created_at', '>=', $startDate);
        }

        if ($endDate) {
            $query->where('created_at', '<=', $endDate);
        }

        $movements = $query->get();

        $summary = [];
        foreach ($movements as $movement) {
            $type = $movement->type;
            if (! isset($summary[$type])) {
                $summary[$type] = [
                    'count' => 0,
                    'total_quantity' => 0,
                    'total_value' => 0,
                ];
            }

            $summary[$type]['count']++;
            $summary[$type]['total_quantity'] += abs($movement->quantity);
            $summary[$type]['total_value'] += abs($movement->total_cost ?? 0);
        }

        return $summary;
    }
}
