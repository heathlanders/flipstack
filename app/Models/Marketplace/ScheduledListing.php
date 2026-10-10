<?php

namespace App\Models\Marketplace;

use App\Models\Catalog\Product;
use App\Models\Integration\IntegrationCredential;
use App\Models\Organization\Organization;
use App\Models\User\User;
use App\Traits\BelongsToOrganization;
use App\Traits\HasUuid;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ScheduledListing extends Model
{
    use BelongsToOrganization, HasFactory, HasUuid;

    protected $table = 'scheduled_listings';

    const STATUS_PENDING = 'pending';

    const STATUS_PROCESSING = 'processing';

    const STATUS_PUBLISHED = 'published';

    const STATUS_FAILED = 'failed';

    const STATUS_CANCELLED = 'cancelled';

    protected $fillable = [
        'organization_id',
        'product_id',
        'marketplace',
        'integration_credential_id',
        'scheduled_at',
        'status',
        'listing_data',
        'error_message',
        'published_at',
        'created_by',
    ];

    protected $casts = [
        'listing_data' => 'array',
        'scheduled_at' => 'datetime',
        'published_at' => 'datetime',
    ];

    /**
     * Relationships
     */
    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function integrationCredential(): BelongsTo
    {
        return $this->belongsTo(IntegrationCredential::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Scopes
     */
    public function scopePending(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_PENDING);
    }

    public function scopeDue(Builder $query): Builder
    {
        return $query->where('scheduled_at', '<=', now())
            ->where('status', self::STATUS_PENDING);
    }
}
