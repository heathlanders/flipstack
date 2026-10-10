<?php

namespace App\Models\Fulfillment;

use App\Traits\HasUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ShipmentTrackingEvent extends Model
{
    use HasUuid;

    protected $guarded = [];

    protected $casts = [
        'raw' => 'array',
        'occurred_at' => 'datetime',
    ];

    public const STATUS_PRE_TRANSIT = 'pre_transit';

    public const STATUS_IN_TRANSIT = 'in_transit';

    public const STATUS_OUT_FOR_DELIVERY = 'out_for_delivery';

    public const STATUS_DELIVERED = 'delivered';

    public const STATUS_RETURNED = 'returned';

    public const STATUS_FAILURE = 'failure';

    public const STATUS_UNKNOWN = 'unknown';

    public function shipment(): BelongsTo
    {
        return $this->belongsTo(Shipment::class);
    }
}
