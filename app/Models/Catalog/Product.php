<?php

namespace App\Models\Catalog;

use App\Models\Amazon\AmazonListing;
use App\Models\Ebay\EbayListing;
use App\Models\Organization\Organization;
use App\Models\Shopify\ShopifyListing;
use App\Models\User\User;
use App\Traits\LogsAudit;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class Product extends Model
{
    use HasFactory, HasUuids, LogsAudit, SoftDeletes;

    protected ?string $auditCategory = 'product';

    protected array $auditExclude = ['featured_image', 'gallery_images', 'search_keywords', 'attributes', 'last_synced_at'];

    protected $fillable = [
        'organization_id',
        'category_id',
        'brand_id',
        'name',
        'slug',
        'short_description',
        'description',
        'sku',
        'barcode',
        'type',
        'status',
        'cost_price',
        'selling_price',
        'msrp',
        'currency',
        'weight',
        'length',
        'width',
        'height',
        'track_inventory',
        'manage_stock',
        'stock_quantity',
        'low_stock_threshold',
        'allow_backorders',
        'tax_class',
        'requires_shipping',
        'shipping_class',
        'seo_title',
        'seo_description',
        'search_keywords',
        'attributes',
        'featured_image',
        'gallery_images',
        'average_rating',
        'review_count',
        'total_sales',
        'view_count',
        'published_at',
        'created_by',
        'condition',
        'condition_description',
        'upc',
        'ean',
        'mpn',
        'quantity',
        'available_quantity',
        'reserved_quantity',
        'marketplace_overrides',
        'is_parted_out',
        'labeling_status',
        'labels_printed_count',
        'last_label_printed_at',
        'last_label_printed_by',
        'component_breakdown',
        'parted_from_product_id',
        'part_out_work_order_id',
    ];

    protected $casts = [
        'cost_price' => 'decimal:2',
        'selling_price' => 'decimal:2',
        'msrp' => 'decimal:2',
        'weight' => 'decimal:3',
        'length' => 'decimal:2',
        'width' => 'decimal:2',
        'height' => 'decimal:2',
        'track_inventory' => 'boolean',
        'manage_stock' => 'boolean',
        'allow_backorders' => 'boolean',
        'requires_shipping' => 'boolean',
        'attributes' => 'array',
        'gallery_images' => 'array',
        'marketplace_overrides' => 'array',
        'average_rating' => 'decimal:2',
        'published_at' => 'datetime',
        'is_parted_out' => 'boolean',
        'component_breakdown' => 'array',
        'last_label_printed_at' => 'datetime',
    ];

    // Relationships
    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function brand(): BelongsTo
    {
        return $this->belongsTo(Brand::class);
    }

    public function images(): HasMany
    {
        return $this->hasMany(ProductImage::class)->orderBy('sort_order');
    }

    public function primaryImage(): HasOne
    {
        return $this->hasOne(ProductImage::class)->where('is_primary', true);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function lastLabelPrintedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'last_label_printed_by');
    }

    public function inventoryMovements(): HasMany
    {
        return $this->hasMany(\App\Models\Inventory\InventoryMovement::class);
    }

    public function inventoryLevels(): HasMany
    {
        return $this->hasMany(\App\Models\Inventory\InventoryLevel::class);
    }

    // Accessors
    public function getDiscountPercentageAttribute(): ?float
    {
        if ($this->msrp && $this->msrp > $this->selling_price) {
            return round((($this->msrp - $this->selling_price) / $this->msrp) * 100);
        }

        return null;
    }

    public function getIsInStockAttribute(): bool
    {
        if (! $this->manage_stock) {
            return true;
        }

        return $this->stock_quantity > 0 || $this->allow_backorders;
    }

    public function getIsOnSaleAttribute(): bool
    {
        return $this->msrp && $this->msrp > $this->selling_price;
    }

    public function getFormattedPriceAttribute(): string
    {
        return '$'.number_format($this->selling_price, 2);
    }

    public function getPrimaryImageUrlAttribute(): ?string
    {
        // primaryImage() is a HasOne -- the property is already the model or null.
        // (Calling ->first() on it threw a fatal error when no primary image existed.)
        if ($this->primaryImage) {
            return $this->primaryImage->url;
        }

        // Fall back to featured_image
        return $this->featured_image;
    }

    // Scopes
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', 'active')
            ->where(function ($q) {
                $q->whereNull('published_at')
                    ->orWhere('published_at', '<=', now());
            });
    }

    public function scopeInStock(Builder $query): Builder
    {
        return $query->where(function ($q) {
            $q->where('manage_stock', false)
                ->orWhere('stock_quantity', '>', 0)
                ->orWhere('allow_backorders', true);
        });
    }

    public function scopePriceRange(Builder $query, $min, $max): Builder
    {
        return $query->whereBetween('selling_price', [$min, $max]);
    }

    public function scopeForOrganization(Builder $query, $organizationId): Builder
    {
        return $query->where('organization_id', $organizationId);
    }

    public function scopeSearch(Builder $query, string $term): Builder
    {
        return $query->whereRaw(
            "to_tsvector('english', name || ' ' || COALESCE(description, '') || ' ' || COALESCE(search_keywords, '')) @@ plainto_tsquery('english', ?)",
            [$term]
        );
    }

    public function scopeByType(Builder $query, string $type): Builder
    {
        return $query->where('type', $type);
    }

    public function scopeByCategory(Builder $query, $categoryId): Builder
    {
        return $query->where('category_id', $categoryId);
    }

    public function scopeByBrand(Builder $query, $brandId): Builder
    {
        return $query->where('brand_id', $brandId);
    }

    public function scopeFeatured(Builder $query): Builder
    {
        return $query->where('attributes->featured', true);
    }

    public function scopeByAnyIdentifier(Builder $query, string $value): Builder
    {
        return $query->whereHas('identifiers', function ($q) use ($value) {
            $q->where('value', $value);
        });
    }

    /**
     * Decrement available_quantity by the given amount, flooring at 0.
     * Uses a DB transaction with pessimistic lock to prevent race conditions.
     */
    public function decrementAvailableQuantity(int $qty): void
    {
        DB::transaction(function () use ($qty) {
            $product = static::lockForUpdate()->find($this->id);

            if (! $product) {
                return;
            }

            $current = (int) ($product->available_quantity ?? 0);

            if ($current < $qty) {
                Log::warning('Oversell detected: available_quantity insufficient', [
                    'product_id' => $this->id,
                    'sku' => $this->sku,
                    'available' => $current,
                    'requested' => $qty,
                    'shortfall' => $qty - $current,
                ]);
            }

            $newQty = max(0, $current - $qty);

            $product->update(['available_quantity' => $newQty]);

            // Keep local instance in sync
            $this->available_quantity = $newQty;
        });
    }

    /**
     * Increment available_quantity by the given amount.
     * Uses a DB transaction with pessimistic lock to prevent race conditions.
     */
    public function incrementAvailableQuantity(int $qty): void
    {
        DB::transaction(function () use ($qty) {
            $product = static::lockForUpdate()->find($this->id);

            if (! $product) {
                return;
            }

            $current = (int) ($product->available_quantity ?? 0);
            $newQty = $current + $qty;

            $product->update(['available_quantity' => $newQty]);

            // Keep local instance in sync
            $this->available_quantity = $newQty;
        });
    }

    public function incrementViews(): void
    {
        $this->increment('view_count');
    }

    public function incrementSales(int $quantity = 1): void
    {
        $this->increment('total_sales', $quantity);

        if ($this->manage_stock) {
            $this->decrement('stock_quantity', $quantity);
        }
    }

    public function isLowStock(): bool
    {
        if (! $this->manage_stock) {
            return false;
        }

        return $this->stock_quantity <= $this->low_stock_threshold;
    }

    public function identifiers(): HasMany
    {
        return $this->hasMany(ProductIdentifier::class)->orderBy('is_primary', 'desc');
    }

    public function primaryIdentifier(): HasOne
    {
        return $this->hasOne(ProductIdentifier::class)->where('is_primary', true);
    }

    public function getIdentifier(string $type): ?string
    {
        return $this->identifiers()->where('type', $type)->value('value');
    }

    public function addIdentifier(string $type, string $value, array $options = []): ProductIdentifier
    {
        $defaults = [
            'is_primary' => false,
            'verified' => false,
            'source' => 'manual_entry',
            'confidence_score' => null,
            'metadata' => [],
            'created_by' => auth()->id(),
        ];

        $data = array_merge($defaults, $options, [
            'type' => $type,
            'value' => $value,
        ]);

        return $this->identifiers()->create($data);
    }

    public function getConditionLabelAttribute(): string
    {
        $labels = [
            'NEW' => 'Brand New',
            'NEW_OTHER' => 'New (Other)',
            'NEW_WITH_DEFECTS' => 'New with Defects',
            'MANUFACTURER_REFURBISHED' => 'Manufacturer Refurbished',
            'CERTIFIED_REFURBISHED' => 'Certified Refurbished',
            'USED_EXCELLENT' => 'Used - Excellent',
            'USED_VERY_GOOD' => 'Used - Very Good',
            'USED_GOOD' => 'Used - Good',
            'USED_ACCEPTABLE' => 'Used - Acceptable',
            'FOR_PARTS_OR_NOT_WORKING' => 'For Parts or Not Working',
        ];

        return $labels[$this->condition] ?? $this->condition;
    }

    public static function getConditionOptions(): array
    {
        return [
            'NEW' => 'Brand New',
            'NEW_OTHER' => 'New (Other)',
            'NEW_WITH_DEFECTS' => 'New with Defects',
            'MANUFACTURER_REFURBISHED' => 'Manufacturer Refurbished',
            'CERTIFIED_REFURBISHED' => 'Certified Refurbished',
            'USED_EXCELLENT' => 'Used - Excellent',
            'USED_VERY_GOOD' => 'Used - Very Good',
            'USED_GOOD' => 'Used - Good',
            'USED_ACCEPTABLE' => 'Used - Acceptable',
            'FOR_PARTS_OR_NOT_WORKING' => 'For Parts or Not Working',
        ];
    }

    /**
     * Get eBay listings for this product
     */
    public function ebayListings(): HasMany
    {
        return $this->hasMany(EbayListing::class);
    }

    /**
     * Get the active eBay listing for a specific credential
     */
    public function ebayListingFor(int $credentialId): ?EbayListing
    {
        return $this->ebayListings()
            ->where('integration_credential_id', $credentialId)
            ->whereNotIn('status', ['deleted'])
            ->first();
    }

    /**
     * Get the first eBay listing for this product
     */
    public function ebayListing(): HasOne
    {
        return $this->hasOne(EbayListing::class)->latest();
    }

    /**
     * Get supplier products linked to this product
     */
    public function supplierProducts(): HasMany
    {
        return $this->hasMany(\App\Models\Inventory\SupplierProduct::class);
    }

    /**
     * Check if product is listed on eBay
     */
    public function isListedOnEbay(?int $credentialId = null): bool
    {
        $query = $this->ebayListings()->where('status', 'published');

        if ($credentialId) {
            $query->where('integration_credential_id', $credentialId);
        }

        return $query->exists();
    }

    /**
     * Get eBay listing status for a credential
     */
    public function getEbayStatus(?int $credentialId = null): ?string
    {
        $query = $this->ebayListings();

        if ($credentialId) {
            $query->where('integration_credential_id', $credentialId);
        }

        return $query->value('status');
    }

    /**
     * Scope for products ready to list on eBay
     */
    public function scopeReadyForEbay(Builder $query): Builder
    {
        return $query->where('status', 'active')
            ->where('ready_for_listing', true)
            ->where('stock_quantity', '>', 0)
            ->whereNotNull('condition');
    }

    /**
     * Scope for products not yet on eBay
     */
    public function scopeNotOnEbay(Builder $query, int $credentialId): Builder
    {
        return $query->whereDoesntHave('ebayListings', function ($q) use ($credentialId) {
            $q->where('integration_credential_id', $credentialId)
                ->whereNotIn('status', ['deleted', 'error']);
        });
    }

    /**
     * Scope for products listed on eBay
     */
    public function scopeOnEbay(Builder $query, int $credentialId): Builder
    {
        return $query->whereHas('ebayListings', function ($q) use ($credentialId) {
            $q->where('integration_credential_id', $credentialId)
                ->where('status', 'published');
        });
    }

    /**
     * Get Shopify listings for this product
     */
    public function shopifyListings(): HasMany
    {
        return $this->hasMany(ShopifyListing::class);
    }

    /**
     * Get price history for this product
     */
    public function priceHistory(): HasMany
    {
        return $this->hasMany(\App\Models\Catalog\PriceHistory::class)->orderBy('created_at', 'desc');
    }

    // Part-Out relationships

    public function partOutWorkOrders(): HasMany
    {
        return $this->hasMany(\App\Models\Inventory\PartOutWorkOrder::class, 'source_product_id');
    }

    public function partedFromProduct(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parted_from_product_id');
    }

    public function partedOutComponents(): HasMany
    {
        return $this->hasMany(self::class, 'parted_from_product_id');
    }

    public function partOutWorkOrder(): BelongsTo
    {
        return $this->belongsTo(\App\Models\Inventory\PartOutWorkOrder::class, 'part_out_work_order_id');
    }

    /**
     * Get the active Shopify listing for a specific credential
     */
    public function shopifyListingFor(int $credentialId): ?ShopifyListing
    {
        return $this->shopifyListings()
            ->where('integration_credential_id', $credentialId)
            ->whereNotIn('status', ['deleted'])
            ->first();
    }

    /**
     * Check if product is listed on Shopify
     */
    public function isListedOnShopify(?int $credentialId = null): bool
    {
        $query = $this->shopifyListings()->where('status', 'active');

        if ($credentialId) {
            $query->where('integration_credential_id', $credentialId);
        }

        return $query->exists();
    }

    /**
     * Generate Shopify SKU (reuses eBay SKU logic)
     */
    public function generateShopifySku(): string
    {
        return $this->generateEbaySku();
    }

    /**
     * Scope for products ready to list on Shopify
     */
    public function scopeReadyForShopify(Builder $query): Builder
    {
        return $query->where('status', 'active')
            ->where('stock_quantity', '>', 0);
    }

    /**
     * Scope for products listed on Shopify
     */
    public function scopeOnShopify(Builder $query, int $credentialId): Builder
    {
        return $query->whereHas('shopifyListings', function ($q) use ($credentialId) {
            $q->where('integration_credential_id', $credentialId)
                ->where('status', 'active');
        });
    }

    /**
     * Scope for products not yet on Shopify
     */
    public function scopeNotOnShopify(Builder $query, int $credentialId): Builder
    {
        return $query->whereDoesntHave('shopifyListings', function ($q) use ($credentialId) {
            $q->where('integration_credential_id', $credentialId)
                ->whereNotIn('status', ['deleted', 'error']);
        });
    }

    public function amazonListings(): HasMany
    {
        return $this->hasMany(AmazonListing::class);
    }

    public function amazonListingFor(int $credentialId): ?AmazonListing
    {
        return $this->amazonListings()
            ->where('integration_credential_id', $credentialId)
            ->whereNotIn('status', [AmazonListing::STATUS_DELETED])
            ->first();
    }

    public function isListedOnAmazon(?int $credentialId = null): bool
    {
        $query = $this->amazonListings()->where('status', AmazonListing::STATUS_ACTIVE);

        if ($credentialId) {
            $query->where('integration_credential_id', $credentialId);
        }

        return $query->exists();
    }

    public function scopeOnAmazon(Builder $query, int $credentialId): Builder
    {
        return $query->whereHas('amazonListings', function ($q) use ($credentialId) {
            $q->where('integration_credential_id', $credentialId)
                ->where('status', AmazonListing::STATUS_ACTIVE);
        });
    }

    public function scopeNotOnAmazon(Builder $query, int $credentialId): Builder
    {
        return $query->whereDoesntHave('amazonListings', function ($q) use ($credentialId) {
            $q->where('integration_credential_id', $credentialId)
                ->whereNotIn('status', [AmazonListing::STATUS_DELETED, AmazonListing::STATUS_ERROR]);
        });
    }

    /**
     * Generate eBay SKU from product data
     * Override this method if you want a different SKU format
     */
    public function generateEbaySku(): string
    {
        // Use existing SKU if it's short enough and unique
        if ($this->sku && strlen($this->sku) <= 50) {
            return $this->sku;
        }

        // Generate from product attributes
        $parts = [];

        if ($this->brand) {
            $parts[] = substr(strtoupper($this->brand->name), 0, 10);
        }

        if ($this->mpn) {
            $parts[] = substr($this->mpn, 0, 20);
        } elseif ($this->model_number) {
            $parts[] = substr($this->model_number, 0, 20);
        }

        $parts[] = substr($this->id, 0, 8); // Use part of UUID for uniqueness

        return implode('-', array_filter($parts));
    }

    /**
     * Get data formatted for eBay inventory item
     */
    public function toEbayInventoryData(): array
    {
        // Read the JSON `attributes` column via getAttribute() to avoid the
        // Eloquent collision with the model's internal $attributes property.
        $attrs = $this->getAttribute('attributes') ?? [];

        return [
            'title' => substr($this->name, 0, 80), // eBay title limit
            'description' => $this->description ?? $this->short_description ?? $this->name,
            'quantity' => $this->available_quantity ?? $this->stock_quantity,
            'condition' => $this->condition ?? 'NEW',
            'condition_description' => $this->condition_description,
            'price' => (string) $this->selling_price,
            'currency' => $this->currency ?? 'USD',
            'sku' => $this->generateEbaySku(),
            'brand' => $this->brand?->name ?? ($attrs['brand'] ?? null),
            'mpn' => $this->mpn ?? ($attrs['mpn'] ?? null),
            'model' => $attrs['model'] ?? null,
            'upc' => $this->upc ?? ($attrs['upc'] ?? null),
            'ean' => $this->ean ?? ($attrs['ean'] ?? null),
            'isbn' => $this->isbn,
            'weight_lbs' => $this->weight_lbs ?? $this->weight,
            'length' => $this->package_length ?? $this->length,
            'width' => $this->package_width ?? $this->width,
            'height' => $this->package_height ?? $this->height,
            // Images + structured specifics so create/sync pushes them and a
            // full inventory replace never strips them.
            'images' => $this->gallery_images ?: [],
            'specifications' => $attrs['specifications'] ?? [],
            'attributes' => $attrs,
        ];
    }
}
