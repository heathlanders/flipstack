<?php

namespace App\Models\Fulfillment;

use App\Models\Organization\Organization;
use App\Traits\BelongsToOrganization;
use App\Traits\HasUuid;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FulfillmentRoutingRule extends Model
{
    use BelongsToOrganization, HasFactory, HasUuid;

    public const TYPE_PROXIMITY = 'proximity';

    public const TYPE_LOWEST_COST = 'lowest_cost';

    public const TYPE_HIGHEST_STOCK = 'highest_stock';

    public const TYPE_ROUND_ROBIN = 'round_robin';

    public const TYPE_SPECIFIC_LOCATION = 'specific_location';

    public const RULE_TYPES = [
        self::TYPE_PROXIMITY,
        self::TYPE_LOWEST_COST,
        self::TYPE_HIGHEST_STOCK,
        self::TYPE_ROUND_ROBIN,
        self::TYPE_SPECIFIC_LOCATION,
    ];

    protected $fillable = [
        'organization_id',
        'name',
        'priority',
        'rule_type',
        'parameters',
        'is_active',
    ];

    protected $casts = [
        'parameters' => 'array',
        'is_active' => 'boolean',
        'priority' => 'integer',
    ];

    // Scopes

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function scopeOrdered(Builder $query): Builder
    {
        return $query->orderBy('priority', 'asc');
    }

    // Relationships

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    // Helpers

    public static function typeLabel(string $type): string
    {
        return match ($type) {
            self::TYPE_PROXIMITY => 'Nearest Warehouse',
            self::TYPE_LOWEST_COST => 'Lowest Shipping Cost',
            self::TYPE_HIGHEST_STOCK => 'Highest Stock',
            self::TYPE_ROUND_ROBIN => 'Round Robin',
            self::TYPE_SPECIFIC_LOCATION => 'Fixed Location',
            default => $type,
        };
    }
}
