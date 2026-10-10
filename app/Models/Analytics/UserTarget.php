<?php

namespace App\Models\Analytics;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class UserTarget extends Model
{
    use HasFactory, HasUuids, SoftDeletes;

    public const SCOPE_USER = 'user';

    public const SCOPE_ROLE = 'role';

    public const SCOPE_TEAM = 'team';

    public const SCOPE_ORG = 'organization';

    public const PERIOD_DAILY = 'daily';

    public const PERIOD_WEEKLY = 'weekly';

    public const PERIOD_MONTHLY = 'monthly';

    public const METRICS = [
        'items_picked',
        'items_packed',
        'shipments_created',
        'returns_processed',
        'pick_errors',
        'pack_errors',
        'orders_picked',
        'orders_packed',
    ];

    protected $fillable = [
        'organization_id',
        'scope_type',
        'scope_id',
        'metric',
        'period',
        'target_value',
        'effective_from',
        'effective_to',
        'notes',
        'created_by',
    ];

    protected $casts = [
        'effective_from' => 'date',
        'effective_to' => 'date',
        'target_value' => 'decimal:2',
    ];
}
