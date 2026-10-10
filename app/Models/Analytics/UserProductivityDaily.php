<?php

namespace App\Models\Analytics;

use App\Models\Organization\Organization;
use App\Models\User\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserProductivityDaily extends Model
{
    protected $table = 'user_productivity_daily';

    protected $guarded = [];

    protected $casts = [
        'performance_date' => 'date',
    ];

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function scopeForOrganization($query, $organizationId)
    {
        return $query->where('organization_id', $organizationId);
    }

    public function scopeBetween($query, $from, $to)
    {
        return $query->whereBetween('performance_date', [$from, $to]);
    }
}
