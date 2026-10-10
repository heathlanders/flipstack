<?php

namespace App\Models\Analytics;

use App\Models\User\User;
use App\Traits\BelongsToOrganization;
use App\Traits\HasUuid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WorkforcePerformanceDaily extends Model
{
    use BelongsToOrganization, HasFactory, HasUuid;

    protected $table = 'workforce_performance_daily';

    protected $fillable = [
        'organization_id',
        'user_id',
        'performance_date',
        'orders_picked',
        'items_picked',
        'pick_errors',
        'avg_pick_duration_seconds',
        'orders_packed',
        'items_packed',
        'pack_errors',
        'avg_pack_duration_seconds',
        'labels_purchased',
        'shipments_created',
        'returns_processed',
        'total_active_seconds',
    ];

    protected $casts = [
        'performance_date' => 'date',
        'orders_picked' => 'integer',
        'items_picked' => 'integer',
        'pick_errors' => 'integer',
        'avg_pick_duration_seconds' => 'integer',
        'orders_packed' => 'integer',
        'items_packed' => 'integer',
        'pack_errors' => 'integer',
        'avg_pack_duration_seconds' => 'integer',
        'labels_purchased' => 'integer',
        'shipments_created' => 'integer',
        'returns_processed' => 'integer',
        'total_active_seconds' => 'integer',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function getPickAccuracyAttribute(): ?float
    {
        $total = $this->items_picked + $this->pick_errors;

        if ($total === 0) {
            return null;
        }

        return $this->items_picked / $total;
    }

    public function getItemsPerHourAttribute(): ?float
    {
        if ($this->total_active_seconds === 0) {
            return null;
        }

        $totalItems = $this->items_picked + $this->items_packed;

        return $totalItems / ($this->total_active_seconds / 3600);
    }
}
