<?php

namespace App\Models\Fulfillment;

use App\Traits\BelongsToOrganization;
use App\Traits\HasUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ShippingRate extends Model
{
    use BelongsToOrganization, HasUuid;

    protected $guarded = [];

    protected $casts = [
        'metadata' => 'array',
        'rate' => 'decimal:2',
        'estimated_days' => 'integer',
        'delivery_date' => 'date',
        'delivery_guarantee' => 'boolean',
        'selected' => 'boolean',
        'expires_at' => 'datetime',
    ];

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function isExpired(): bool
    {
        return $this->expires_at->isPast();
    }

    public function scopeValid($query)
    {
        return $query->where('expires_at', '>', now());
    }

    public function scopeSelected($query)
    {
        return $query->where('selected', true);
    }
}
