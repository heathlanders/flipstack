<?php

namespace App\Http\Resources\Inventory;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\ResourceCollection;

class InventoryLevelCollection extends ResourceCollection
{
    public function toArray(Request $request): array
    {
        return [
            'data' => $this->collection,
            'meta' => [
                'total_inventory_value' => $this->collection->sum('total_cost'),
                'total_quantity_on_hand' => $this->collection->sum('quantity_on_hand'),
                'total_quantity_available' => $this->collection->sum('quantity_available'),
                'total_quantity_reserved' => $this->collection->sum('quantity_reserved'),
                'low_stock_items' => $this->collection->filter(fn ($level) => $level->isLowStock())->count(),
                'out_of_stock_items' => $this->collection->filter(fn ($level) => $level->isOutOfStock())->count(),
                'over_stock_items' => $this->collection->filter(fn ($level) => $level->isOverStock())->count(),
                'formatted_total_value' => '$'.number_format($this->collection->sum('total_cost'), 2),
            ],
        ];
    }
}
