<?php

namespace App\Services;

use App\Models\Catalog\Product;
use App\Models\Catalog\ProductIdentifier;

class ProductLookupService
{
    public function lookupByIdentifier(string $identifier, string $organizationId): array
    {
        $identifier = trim($identifier);

        // Search by ProductIdentifier value
        $product = Product::where('organization_id', $organizationId)
            ->whereHas('identifiers', function ($query) use ($identifier) {
                $query->where('value', $identifier);
            })
            ->with(['brand', 'category'])
            ->first();

        // Fallback: search by barcode or SKU directly
        if (! $product) {
            $product = Product::where('organization_id', $organizationId)
                ->where(function ($query) use ($identifier) {
                    $query->where('barcode', $identifier)
                        ->orWhere('sku', $identifier);
                })
                ->with(['brand', 'category'])
                ->first();
        }

        if (! $product) {
            return ['found_existing' => false];
        }

        // Get existing conditions for this product name/brand combination
        $existingConditions = Product::where('organization_id', $organizationId)
            ->where('name', $product->name)
            ->whereNotNull('condition')
            ->pluck('condition')
            ->unique()
            ->values()
            ->toArray();

        return [
            'found_existing' => true,
            'name' => $product->name,
            'brand' => $product->brand?->name,
            'existing_conditions' => $existingConditions,
            'has_new_condition' => in_array('NEW', $existingConditions),
            'suggested_category' => $product->category?->name,
            'suggested_type' => $product->type ?? null,
        ];
    }
}
