<?php

namespace App\Http\Resources\Inventory;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\ResourceCollection;

class LocationCollection extends ResourceCollection
{
    public function toArray(Request $request): array
    {
        return [
            'data' => $this->collection,
            'meta' => [
                'total_locations' => $this->collection->count(),
                'active_locations' => $this->collection->filter(fn ($location) => $location->is_active)->count(),
                'warehouses' => $this->collection->filter(fn ($location) => $location->type === 'warehouse')->count(),
                'stores' => $this->collection->filter(fn ($location) => $location->type === 'store')->count(),
                'default_location' => $this->collection->firstWhere('is_default', true)?->name,
            ],
        ];
    }
}
