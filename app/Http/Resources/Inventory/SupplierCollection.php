<?php

namespace App\Http\Resources\Inventory;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\ResourceCollection;

class SupplierCollection extends ResourceCollection
{
    public function toArray(Request $request): array
    {
        return [
            'data' => $this->collection,
            'meta' => [
                'total_suppliers' => $this->collection->count(),
                'active_suppliers' => $this->collection->filter(fn ($supplier) => $supplier->is_active)->count(),
                'preferred_suppliers' => $this->collection->filter(fn ($supplier) => $supplier->is_preferred)->count(),
                'average_rating' => $this->collection->avg('performance_rating'),
                'top_rated_count' => $this->collection->filter(fn ($supplier) => $supplier->performance_rating >= 4.0)->count(),
            ],
        ];
    }
}
