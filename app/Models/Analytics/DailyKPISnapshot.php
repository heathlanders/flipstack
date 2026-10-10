<?php

namespace App\Models\Analytics;

use App\Traits\BelongsToOrganization;
use App\Traits\HasUuid;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DailyKPISnapshot extends Model
{
    use BelongsToOrganization, HasFactory, HasUuid;

    protected $table = 'daily_kpi_snapshots';

    public const CATEGORY_EXECUTIVE = 'executive';

    public const CATEGORY_FULFILLMENT = 'fulfillment';

    public const CATEGORY_WORKFORCE = 'workforce';

    public const CATEGORY_INVENTORY = 'inventory';

    public const CATEGORY_PROCUREMENT = 'procurement';

    public const CATEGORY_MARKETPLACE = 'marketplace';

    public const CATEGORY_CUSTOMER = 'customer';

    public const CATEGORY_COMMUNICATION = 'communication';

    public const CATEGORY_MARKETING = 'marketing';

    public const CATEGORY_PRICING = 'pricing';

    public const CATEGORIES = [
        self::CATEGORY_EXECUTIVE,
        self::CATEGORY_FULFILLMENT,
        self::CATEGORY_WORKFORCE,
        self::CATEGORY_INVENTORY,
        self::CATEGORY_PROCUREMENT,
        self::CATEGORY_MARKETPLACE,
        self::CATEGORY_CUSTOMER,
        self::CATEGORY_COMMUNICATION,
        self::CATEGORY_MARKETING,
        self::CATEGORY_PRICING,
    ];

    protected $fillable = [
        'organization_id',
        'snapshot_date',
        'category',
        'metrics',
    ];

    protected $casts = [
        'snapshot_date' => 'date',
        'metrics' => 'array',
    ];

    public function scopeForCategory(Builder $query, string $category): Builder
    {
        return $query->where('category', $category);
    }

    public function scopeForDateRange(Builder $query, $startDate, $endDate): Builder
    {
        return $query->whereBetween('snapshot_date', [$startDate, $endDate]);
    }
}
