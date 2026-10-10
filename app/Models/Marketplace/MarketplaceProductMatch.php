<?php

namespace App\Models\Marketplace;

use App\Models\Catalog\Product;
use App\Models\Integration\IntegrationCredential;
use App\Models\System\BulkOperationJob;
use App\Models\User\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MarketplaceProductMatch extends Model
{
    protected $fillable = [
        'organization_id',
        'bulk_operation_job_id',
        'product_id',
        'source_marketplace',
        'source_listing_id',
        'source_variant_id',
        'source_inventory_item_id',
        'source_sku',
        'source_barcode',
        'source_title',
        'source_price',
        'source_quantity',
        'source_data',
        'match_type',
        'confidence',
        'status',
        'integration_credential_id',
        'reviewed_by',
        'reviewed_at',
    ];

    protected $casts = [
        'source_data' => 'array',
        'source_price' => 'decimal:2',
        'reviewed_at' => 'datetime',
    ];

    const STATUS_PENDING = 'pending';

    const STATUS_CONFIRMED = 'confirmed';

    const STATUS_REJECTED = 'rejected';

    const STATUS_IGNORED = 'ignored';

    const STATUS_LINKED = 'linked';

    const MATCH_SKU = 'sku';

    const MATCH_UPC = 'upc';

    const MATCH_EAN = 'ean';

    const MATCH_BARCODE = 'barcode';

    const MATCH_TITLE = 'title';

    const MATCH_MANUAL = 'manual';

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function bulkOperationJob(): BelongsTo
    {
        return $this->belongsTo(BulkOperationJob::class);
    }

    public function integrationCredential(): BelongsTo
    {
        return $this->belongsTo(IntegrationCredential::class);
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function scopePending(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_PENDING);
    }

    public function scopeConfirmed(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_CONFIRMED);
    }

    public function scopeLinked(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_LINKED);
    }

    public function scopeUnmatched(Builder $query): Builder
    {
        return $query->whereNull('product_id')->where('status', self::STATUS_PENDING);
    }

    public function scopeMatched(Builder $query): Builder
    {
        return $query->whereNotNull('product_id')->where('status', self::STATUS_PENDING);
    }

    public function scopeHighConfidence(Builder $query, int $threshold = 85): Builder
    {
        return $query->whereNotNull('product_id')
            ->where('status', self::STATUS_PENDING)
            ->where('confidence', '>=', $threshold);
    }

    public function scopeLowConfidence(Builder $query, int $threshold = 85): Builder
    {
        return $query->whereNotNull('product_id')
            ->where('status', self::STATUS_PENDING)
            ->where('confidence', '<', $threshold);
    }

    public function scopeForSession(Builder $query, int $bulkJobId): Builder
    {
        return $query->where('bulk_operation_job_id', $bulkJobId);
    }

    public function hasQuantityDiscrepancy(): bool
    {
        if (! $this->product) {
            return false;
        }

        return $this->source_quantity !== null
            && $this->source_quantity !== $this->product->available_quantity;
    }
}
