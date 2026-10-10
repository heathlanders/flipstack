<?php

namespace App\Http\Resources\Inventory;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\ResourceCollection;

class InventoryMovementCollection extends ResourceCollection
{
    public function toArray(Request $request): array
    {
        $increases = $this->collection->filter(fn ($movement) => $movement->isIncrease());
        $decreases = $this->collection->filter(fn ($movement) => $movement->isDecrease());

        return [
            'data' => $this->collection,
            'meta' => [
                'total_movements' => $this->collection->count(),
                'total_increases' => $increases->count(),
                'total_decreases' => $decreases->count(),
                'net_quantity_change' => $this->collection->sum('quantity'),
                'total_value_in' => $increases->sum('total_cost'),
                'total_value_out' => abs($decreases->sum('total_cost')),
                'movement_types' => $this->collection->groupBy('type')->map->count(),
                'date_range' => [
                    'earliest' => $this->collection->min('created_at'),
                    'latest' => $this->collection->max('created_at'),
                ],
            ],
        ];
    }
}
