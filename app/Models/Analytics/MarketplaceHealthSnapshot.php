<?php

namespace App\Models\Analytics;

use App\Traits\BelongsToOrganization;
use App\Traits\HasUuid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MarketplaceHealthSnapshot extends Model
{
    use BelongsToOrganization, HasFactory, HasUuid;

    protected $fillable = [
        'organization_id',
        'marketplace',
        'snapshot_date',
        'total_listings',
        'active_listings',
        'error_listings',
        'sync_attempts',
        'sync_successes',
        'sync_failures',
        'avg_response_time_ms',
        'p95_response_time_ms',
        'total_revenue',
        'total_fees',
        'orders_imported',
    ];

    protected $casts = [
        'snapshot_date' => 'date',
        'total_listings' => 'integer',
        'active_listings' => 'integer',
        'error_listings' => 'integer',
        'sync_attempts' => 'integer',
        'sync_successes' => 'integer',
        'sync_failures' => 'integer',
        'avg_response_time_ms' => 'integer',
        'p95_response_time_ms' => 'integer',
        'total_revenue' => 'decimal:2',
        'total_fees' => 'decimal:2',
        'orders_imported' => 'integer',
    ];

    public function getSyncSuccessRateAttribute(): ?float
    {
        if ($this->sync_attempts === 0) {
            return null;
        }

        return ($this->sync_successes / $this->sync_attempts) * 100;
    }

    public function getFeeRateAttribute(): ?float
    {
        if ((float) $this->total_revenue === 0.0) {
            return null;
        }

        return ((float) $this->total_fees / (float) $this->total_revenue) * 100;
    }
}
