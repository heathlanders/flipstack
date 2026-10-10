<?php

namespace App\Models\Ebay;

use App\Models\Integration\IntegrationCredential;
use App\Models\Organization\Organization;
use App\Traits\BelongsToOrganization;
use App\Traits\HasUuid;
use App\Traits\LogsAudit;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class EbayPromotedCampaign extends Model
{
    use BelongsToOrganization, HasFactory, HasUuid, LogsAudit;

    protected ?string $auditCategory = 'marketplace';

    protected ?string $auditActionPrefix = 'promoted.campaign';

    public const STATUS_ACTIVE = 'active';

    public const STATUS_PAUSED = 'paused';

    public const STATUS_ENDED = 'ended';

    public const TYPE_STANDARD = 'standard';

    public const TYPE_ADVANCED = 'advanced';

    protected $table = 'ebay_promoted_campaigns';

    protected $fillable = [
        'organization_id',
        'integration_credential_id',
        'ebay_campaign_id',
        'name',
        'status',
        'campaign_type',
        'budget_daily',
        'budget_total',
        'start_date',
        'end_date',
    ];

    protected $casts = [
        'budget_daily' => 'decimal:2',
        'budget_total' => 'decimal:2',
        'start_date' => 'date',
        'end_date' => 'date',
    ];

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function integrationCredential(): BelongsTo
    {
        return $this->belongsTo(IntegrationCredential::class);
    }

    public function promotedListings(): HasMany
    {
        return $this->hasMany(EbayPromotedListing::class, 'campaign_id');
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_ACTIVE);
    }
}
