<?php

namespace App\Http\Resources\Inventory;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\ResourceCollection;

class ProductCollection extends ResourceCollection
{
    public function toArray(Request $request): array
    {
        return [
            'data' => $this->collection,
            'meta' => [
                'total_value' => $this->collection->sum(fn ($product) => $product->selling_price * $product->getAvailableStock()),
                'in_stock_count' => $this->collection->filter(fn ($product) => $product->isInStock())->count(),
                'low_stock_count' => $this->collection->filter(fn ($product) => $product->isLowStock())->count(),
                'out_of_stock_count' => $this->collection->filter(fn ($product) => ! $product->isInStock())->count(),
            ],
        ];
    }
}
