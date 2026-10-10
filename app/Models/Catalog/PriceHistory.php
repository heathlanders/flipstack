<?php

namespace App\Models\Catalog;

use App\Models\User\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PriceHistory extends Model
{
    protected $table = 'price_history';

    protected $fillable = [
        'product_id',
        'organization_id',
        'price_field',
        'old_price',
        'new_price',
        'source',
        'changed_by',
    ];

    protected $casts = [
        'old_price' => 'decimal:2',
        'new_price' => 'decimal:2',
    ];

    public const SOURCE_MANUAL = 'manual';

    public const SOURCE_BULK = 'bulk';

    public const SOURCE_IMPORT = 'import';

    public const SOURCE_SYNC = 'sync';

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function changedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'changed_by');
    }

    public function scopeForProduct($query, string $productId)
    {
        return $query->where('product_id', $productId);
    }

    public function scopeForField($query, string $field)
    {
        return $query->where('price_field', $field);
    }

    public function getChangeAmountAttribute(): ?float
    {
        if ($this->old_price === null) {
            return null;
        }

        return round($this->new_price - $this->old_price, 2);
    }

    public function getChangePercentAttribute(): ?float
    {
        if (! $this->old_price || $this->old_price == 0) {
            return null;
        }

        return round((($this->new_price - $this->old_price) / $this->old_price) * 100, 1);
    }
}
