<?php

namespace App\Http\Resources\Inventory;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\ResourceCollection;

class CategoryCollection extends ResourceCollection
{
    public function toArray(Request $request): array
    {
        return [
            'data' => $this->collection,
            'meta' => [
                'total_categories' => $this->collection->count(),
                'root_categories' => $this->collection->filter(fn ($cat) => $cat->isRoot())->count(),
                'active_categories' => $this->collection->filter(fn ($cat) => $cat->is_active)->count(),
                'max_depth' => $this->collection->max('depth') ?? 0,
            ],
        ];
    }
}
