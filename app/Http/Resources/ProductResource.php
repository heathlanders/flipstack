<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ProductResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'organization_id' => $this->organization_id,

            // Basic Information
            'name' => $this->name,
            'slug' => $this->slug,
            'short_description' => $this->short_description,
            'description' => $this->description,
            'sku' => $this->sku,
            'barcode' => $this->barcode,
            'type' => $this->type,
            'status' => $this->status,
            'status_label' => $this->getStatusLabel(),

            // Pricing
            'cost_price' => $this->cost_price,
            'selling_price' => $this->selling_price,
            'msrp' => $this->msrp,
            'currency' => $this->currency ?? 'USD',
            'formatted_cost_price' => $this->formatPrice($this->cost_price),
            'formatted_selling_price' => $this->formatPrice($this->selling_price),
            'formatted_msrp' => $this->formatPrice($this->msrp),
            'profit_margin' => $this->calculateProfitMargin(),

            // Inventory
            'track_inventory' => $this->track_inventory,
            'manage_stock' => $this->manage_stock,
            'stock_quantity' => $this->stock_quantity ?? 0,
            'available_quantity' => $this->available_quantity ?? 0,
            'low_stock_threshold' => $this->low_stock_threshold ?? 5,
            'allow_backorders' => $this->allow_backorders,
            'stock_status' => $this->getStockStatus(),
            'stock_status_color' => $this->getStockStatusColor(),

            // Physical Properties
            'weight' => $this->weight,
            'length' => $this->length,
            'width' => $this->width,
            'height' => $this->height,
            'dimensions' => $this->getDimensionsFormatted(),

            // Shipping
            'requires_shipping' => $this->requires_shipping,
            'shipping_class' => $this->shipping_class,
            'tax_class' => $this->tax_class,

            // Condition
            'condition' => $this->condition,
            'condition_label' => $this->getConditionLabel(),
            'condition_description' => $this->condition_description,

            // SEO
            'seo_title' => $this->seo_title,
            'seo_description' => $this->seo_description,
            'search_keywords' => $this->search_keywords,

            // Attributes
            'attributes' => $this->attributes ?? [],

            // Images
            'featured_image' => $this->featured_image,
            'featured_image_url' => $this->getFeaturedImageUrl(),
            'gallery_images' => $this->gallery_images ?? [],
            'images' => $this->whenLoaded('images', fn () => $this->images->map(fn ($img) => [
                'id' => $img->id,
                'url' => $img->url ? (str_starts_with($img->url, 'http') ? $img->url : asset('storage/'.$img->url)) : null,
                'thumbnail_url' => $img->thumbnail_url ? (str_starts_with($img->thumbnail_url, 'http') ? $img->thumbnail_url : asset('storage/'.$img->thumbnail_url)) : null,
                'alt_text' => $img->alt_text,
                'sort_order' => $img->sort_order,
                'is_primary' => (bool) $img->is_primary,
                'bg_removed' => (bool) ($img->metadata['bg_removed'] ?? false),
                'has_original' => ! empty($img->metadata['original_url'] ?? null),
            ])),
            'images_count' => $this->whenCounted('images', $this->images_count),

            // Relations
            'category' => $this->whenLoaded('category', fn () => [
                'id' => $this->category->id,
                'name' => $this->category->name,
                'slug' => $this->category->slug,
                'parent_id' => $this->category->parent_id,
            ]),
            'brand' => $this->whenLoaded('brand', fn () => [
                'id' => $this->brand->id,
                'name' => $this->brand->name,
                'slug' => $this->brand->slug,
            ]),
            'creator' => $this->whenLoaded('creator', fn () => [
                'id' => $this->creator->id,
                'name' => $this->creator->name,
            ]),

            // Labeling audit
            'labeling_status' => $this->labeling_status,
            'labels_printed_count' => $this->labels_printed_count ?? 0,
            'last_label_printed_at' => $this->last_label_printed_at?->toISOString(),
            'last_label_printed_at_human' => $this->last_label_printed_at?->diffForHumans(),
            'last_label_printed_by' => $this->whenLoaded('lastLabelPrintedBy', fn () => $this->lastLabelPrintedBy ? [
                'id' => $this->lastLabelPrintedBy->id,
                'name' => $this->lastLabelPrintedBy->name,
            ] : null),
            'inventory_levels' => $this->whenLoaded('inventoryLevels', fn () => $this->inventoryLevels->map(fn ($level) => [
                'id' => $level->id,
                'location_id' => $level->location_id,
                'location_name' => $level->location?->name,
                'bin_id' => $level->bin_id,
                'bin' => $level->relationLoaded('bin') && $level->bin ? [
                    'id' => $level->bin->id,
                    'code' => $level->bin->code,
                    'name' => $level->bin->name,
                    'zone' => $level->bin->relationLoaded('zone') && $level->bin->zone ? [
                        'id' => $level->bin->zone->id,
                        'code' => $level->bin->zone->code,
                        'name' => $level->bin->zone->name,
                    ] : null,
                ] : null,
                'quantity_on_hand' => $level->quantity_on_hand,
                'quantity_reserved' => $level->quantity_reserved,
                'quantity_available' => $level->quantity_available,
            ])),
            'suppliers' => $this->whenLoaded('supplierProducts', fn () => $this->supplierProducts
                ->filter(fn ($sp) => $sp->supplier !== null)
                ->map(fn ($sp) => [
                    'id' => $sp->supplier->id,
                    'code' => $sp->supplier->code,
                    'name' => $sp->supplier->name,
                    'status' => $sp->supplier->supplier_status,
                    'is_consignor' => (bool) ($sp->supplier->meta_data['is_consignor'] ?? false),
                    'supplier_sku' => $sp->supplier_sku,
                    'cost_price' => $sp->cost_price,
                ])
                ->values()
            ),
            'parsed_warehouse_location' => array_filter([
                'consignor_code' => $this->attributes['consignor_code'] ?? null,
                'consignor_item_id' => $this->attributes['consignor_item_id'] ?? null,
                'room' => $this->attributes['warehouse_room'] ?? null,
                'shelf' => $this->attributes['warehouse_shelf'] ?? null,
                'container_type' => $this->attributes['warehouse_container_type'] ?? null,
                'container_name' => $this->attributes['warehouse_container_name'] ?? null,
                'level' => $this->attributes['warehouse_level'] ?? null,
                'special_area' => $this->attributes['warehouse_special_area'] ?? null,
            ], fn ($v) => $v !== null && $v !== ''),
            'inventory_movements' => $this->whenLoaded('inventoryMovements', fn () => $this->inventoryMovements->map(fn ($movement) => [
                'id' => $movement->id,
                'type' => $movement->type,
                'quantity' => $movement->quantity,
                'reason' => $movement->reason,
                'notes' => $movement->notes,
                'location' => $movement->location ? [
                    'id' => $movement->location->id,
                    'name' => $movement->location->name,
                ] : null,
                'created_at' => $movement->created_at?->toISOString(),
            ])),

            // Marketplace Listings
            'ebay_listings' => $this->whenLoaded('ebayListings', fn () => $this->ebayListings->map(function ($listing) {
                $credLabel = null;
                try {
                    if ($listing->relationLoaded('integrationCredential') && $listing->integrationCredential) {
                        $cred = $listing->integrationCredential;
                        $credLabel = ucfirst($cred->platform).($cred->seller_username ? " ({$cred->seller_username})" : '');
                    }
                } catch (\Throwable $e) {
                    // Credential accessor may throw on encrypted fields
                }

                return [
                    'id' => $listing->id,
                    'ebay_listing_id' => $listing->ebay_listing_id,
                    'ebay_sku' => $listing->ebay_sku,
                    'credential_id' => $listing->integration_credential_id,
                    'credential_label' => $credLabel,
                    'status' => $listing->status,
                    'status_label' => $listing->status_label,
                    'status_color' => $listing->status_color,
                    'listed_price' => $listing->listed_price,
                    'available_quantity' => $listing->available_quantity,
                    'ebay_url' => $listing->ebay_url,
                    'last_synced_at' => $listing->last_synced_at?->toISOString(),
                    'last_synced_at_human' => $listing->last_synced_at?->diffForHumans(),
                    'last_error_message' => $listing->last_error_message,
                ];
            })),
            'shopify_listings' => $this->whenLoaded('shopifyListings', fn () => $this->shopifyListings->map(function ($listing) {
                $credLabel = null;
                $shopifyUrl = null;
                try {
                    if ($listing->relationLoaded('integrationCredential') && $listing->integrationCredential) {
                        $cred = $listing->integrationCredential;
                        $credLabel = 'Shopify'.($cred->seller_username ? " ({$cred->seller_username})" : '');
                        $shopDomain = $cred->config['shop_domain'] ?? null;
                        if ($shopDomain && $listing->shopify_product_id) {
                            $shopifyUrl = "https://{$shopDomain}/admin/products/{$listing->shopify_product_id}";
                        }
                    }
                } catch (\Throwable $e) {
                    // Credential accessor may throw on encrypted fields
                }

                return [
                    'id' => $listing->id,
                    'shopify_product_id' => $listing->shopify_product_id,
                    'credential_id' => $listing->integration_credential_id,
                    'credential_label' => $credLabel,
                    'status' => $listing->status,
                    'status_label' => $listing->status_label,
                    'status_color' => $listing->status_color,
                    'listed_price' => $listing->listed_price,
                    'inventory_quantity' => $listing->inventory_quantity,
                    'shopify_url' => $shopifyUrl,
                    'last_synced_at' => $listing->last_synced_at?->toISOString(),
                    'last_synced_at_human' => $listing->last_synced_at?->diffForHumans(),
                    'last_error_message' => $listing->last_error_message,
                ];
            })),

            // Statistics
            'average_rating' => $this->average_rating,
            'review_count' => $this->review_count ?? 0,
            'total_sales' => $this->total_sales ?? 0,
            'view_count' => $this->view_count ?? 0,

            // Timestamps
            'published_at' => $this->published_at?->toISOString(),
            'published_at_formatted' => $this->published_at?->format('M d, Y'),
            'created_at' => $this->created_at?->toISOString(),
            'created_at_formatted' => $this->created_at?->format('M d, Y H:i'),
            'updated_at' => $this->updated_at?->toISOString(),
            'updated_at_formatted' => $this->updated_at?->format('M d, Y H:i'),
            'created_at_human' => $this->created_at?->diffForHumans(),
            'updated_at_human' => $this->updated_at?->diffForHumans(),
        ];
    }

    /**
     * Format price with currency symbol.
     */
    protected function formatPrice(?float $price): ?string
    {
        if ($price === null) {
            return null;
        }

        $currency = $this->currency ?? 'USD';
        $symbol = match ($currency) {
            'USD' => '$',
            'EUR' => '€',
            'GBP' => '£',
            'CAD' => 'CA$',
            'AUD' => 'A$',
            default => $currency.' ',
        };

        return $symbol.number_format($price, 2);
    }

    /**
     * Calculate profit margin percentage.
     */
    protected function calculateProfitMargin(): ?float
    {
        if (! $this->cost_price || ! $this->selling_price || $this->selling_price == 0) {
            return null;
        }

        return round((($this->selling_price - $this->cost_price) / $this->selling_price) * 100, 2);
    }

    /**
     * Get stock status label.
     */
    protected function getStockStatus(): string
    {
        if (! $this->manage_stock) {
            return 'in_stock';
        }

        // Use available_quantity (sellable, decremented on order import) so the
        // hub badge reflects what marketplaces show, not the legacy
        // physical-on-hand stock_quantity.
        $quantity = $this->available_quantity ?? 0;
        $threshold = $this->low_stock_threshold ?? 5;

        if ($quantity <= 0) {
            return $this->allow_backorders ? 'backorder' : 'out_of_stock';
        }

        if ($quantity <= $threshold) {
            return 'low_stock';
        }

        if ($quantity > $threshold * 3) {
            return 'overstocked';
        }

        return 'in_stock';
    }

    /**
     * Get stock status color for UI.
     */
    protected function getStockStatusColor(): string
    {
        return match ($this->getStockStatus()) {
            'in_stock' => 'green',
            'low_stock' => 'yellow',
            'out_of_stock' => 'red',
            'backorder' => 'orange',
            'overstocked' => 'blue',
            default => 'gray',
        };
    }

    /**
     * Get status label.
     */
    protected function getStatusLabel(): string
    {
        return match ($this->status) {
            'draft' => 'Draft',
            'active' => 'Active',
            'inactive' => 'Inactive',
            'archived' => 'Archived',
            default => ucfirst($this->status ?? 'Unknown'),
        };
    }

    /**
     * Get condition label.
     */
    protected function getConditionLabel(): ?string
    {
        if (! $this->condition) {
            return null;
        }

        return match ($this->condition) {
            'new' => 'New',
            'like_new' => 'Like New',
            'excellent' => 'Excellent',
            'very_good' => 'Very Good',
            'good' => 'Good',
            'acceptable' => 'Acceptable',
            'for_parts' => 'For Parts',
            'refurbished' => 'Refurbished',
            default => ucfirst(str_replace('_', ' ', $this->condition)),
        };
    }

    /**
     * Get dimensions formatted.
     */
    protected function getDimensionsFormatted(): ?string
    {
        if (! $this->length && ! $this->width && ! $this->height) {
            return null;
        }

        $parts = [];
        if ($this->length) {
            $parts[] = $this->length;
        }
        if ($this->width) {
            $parts[] = $this->width;
        }
        if ($this->height) {
            $parts[] = $this->height;
        }

        return implode(' × ', $parts).' in';
    }

    /**
     * Get featured image URL.
     */
    protected function getFeaturedImageUrl(): ?string
    {
        if ($this->featured_image) {
            // Check if it's already a full URL
            if (str_starts_with($this->featured_image, 'http')) {
                return $this->featured_image;
            }

            return asset('storage/'.$this->featured_image);
        }

        // Fall back to first gallery image
        if ($this->relationLoaded('images') && $this->images->isNotEmpty()) {
            $url = $this->images->first()->url;
            if (str_starts_with($url, 'http')) {
                return $url;
            }

            return asset('storage/'.$url);
        }

        // Return placeholder
        return null;
    }
}
