<?php

namespace App\Models\Pricing;

use App\Traits\BelongsToOrganization;
use App\Traits\HasUuid;
use App\Traits\LogsAudit;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class RepricingRule extends Model
{
    use BelongsToOrganization, HasFactory, HasUuid, LogsAudit;

    protected ?string $auditCategory = 'pricing';

    protected ?string $auditActionPrefix = 'pricing.rule';

    protected array $auditExclude = ['last_run_at'];

    protected $fillable = [
        'organization_id', 'name', 'marketplace', 'scope_type', 'scope_ids',
        'strategy', 'parameters', 'is_active', 'priority', 'last_run_at',
    ];

    protected $casts = [
        'scope_ids' => 'array',
        'parameters' => 'array',
        'is_active' => 'boolean',
        'priority' => 'integer',
        'last_run_at' => 'datetime',
    ];

    public const STRATEGY_MATCH_LOWEST = 'match_lowest';

    public const STRATEGY_BEAT_BY_AMOUNT = 'beat_by_amount';

    public const STRATEGY_BEAT_BY_PERCENT = 'beat_by_percent';

    public const STRATEGY_COST_PLUS = 'cost_plus';

    public const STRATEGY_MAP_FLOOR = 'map_floor';

    /** Match Amazon Buy Box price exactly. Requires marketplace = 'amazon'. */
    public const STRATEGY_MATCH_BUY_BOX = 'match_buy_box';

    /** Beat Amazon Buy Box price by amount or percent. Requires marketplace = 'amazon'. */
    public const STRATEGY_BEAT_BUY_BOX = 'beat_buy_box';

    public function logs(): HasMany
    {
        return $this->hasMany(RepricingLog::class);
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true)->orderBy('priority');
    }

    public function scopeForMarketplace($query, string $marketplace)
    {
        return $query->where(function ($q) use ($marketplace) {
            $q->where('marketplace', $marketplace)->orWhere('marketplace', '*');
        });
    }

    public function getMinPrice(): ?float
    {
        return $this->parameters['min_price'] ?? null;
    }

    public function getMaxPrice(): ?float
    {
        return $this->parameters['max_price'] ?? null;
    }
}
