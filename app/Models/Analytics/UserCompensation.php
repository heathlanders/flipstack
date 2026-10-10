<?php

namespace App\Models\Analytics;

use App\Models\User\User;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class UserCompensation extends Model
{
    use HasFactory, HasUuids, SoftDeletes;

    protected $table = 'user_compensations';

    protected $fillable = [
        'organization_id',
        'user_id',
        'hourly_rate',
        'minutes_per_listing',
        'minutes_per_ticket',
        'effective_from',
        'effective_to',
        'notes',
        'created_by',
    ];

    protected $casts = [
        'effective_from' => 'date',
        'effective_to' => 'date',
        'hourly_rate' => 'decimal:2',
        'minutes_per_listing' => 'integer',
        'minutes_per_ticket' => 'integer',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
