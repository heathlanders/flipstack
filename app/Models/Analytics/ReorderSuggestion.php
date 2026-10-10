<?php

namespace App\Models\Analytics;

use App\Models\Catalog\Product;
use App\Models\Inventory\Supplier;
use App\Models\Organization\Organization;
use App\Models\Procurement\PurchaseOrder;
use App\Traits\BelongsToOrganization;
use App\Traits\HasUuid;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ReorderSuggestion extends Model
{
    use BelongsToOrganization, HasFactory, HasUuid;

    public const REASON_LOW_STOCK_FORECAST = 'low_stock_forecast';

    public const REASON_SEASONAL_TREND = 'seasonal_trend';

    public const REASON_VELOCITY_INCREASE = 'velocity_increase';

    public const PRIORITY_LOW = 'low';

    public const PRIORITY_MEDIUM = 'medium';

    public const PRIORITY_HIGH = 'high';

    public const PRIORITY_CRITICAL = 'critical';

    public const STATUS_PENDING = 'pending';

    public const STATUS_ACCEPTED = 'accepted';

    public const STATUS_DISMISSED = 'dismissed';

    protected $fillable = [
        'organization_id',
        'product_id',
        'supplier_id',
        'suggested_quantity',
        'reason',
        'estimated_stockout_date',
        'priority',
        'status',
        'accepted_at',
        'purchase_order_id',
    ];

    protected $casts = [
        'estimated_stockout_date' => 'date',
        'accepted_at' => 'datetime',
        'suggested_quantity' => 'integer',
    ];

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    public function purchaseOrder(): BelongsTo
    {
        return $this->belongsTo(PurchaseOrder::class);
    }

    public function scopePending(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_PENDING);
    }

    public function scopeCritical(Builder $query): Builder
    {
        return $query->where('priority', self::PRIORITY_CRITICAL);
    }

    public function scopeByPriority(Builder $query): Builder
    {
        return $query->orderByRaw("CASE priority WHEN 'critical' THEN 1 WHEN 'high' THEN 2 WHEN 'medium' THEN 3 WHEN 'low' THEN 4 END");
    }
}
