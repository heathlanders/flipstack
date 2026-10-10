<?php

namespace App\Models\Fulfillment;

use App\Models\User\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class FulfillmentLog extends Model
{
    public $timestamps = false;

    protected $guarded = [];

    protected $casts = [
        'metadata' => 'array',
        'created_at' => 'datetime',
    ];

    // Order lifecycle
    public const ACTION_ORDER_IMPORTED = 'order_imported';

    public const ACTION_ORDER_ASSIGNED = 'order_assigned';

    public const ACTION_ORDER_PRIORITY_CHANGED = 'order_priority_changed';

    public const ACTION_ORDER_HELD = 'order_held';

    public const ACTION_ORDER_RELEASED = 'order_released';

    public const ACTION_ORDER_CANCELLED = 'order_cancelled';

    // Pick workflow
    public const ACTION_PICK_STARTED = 'pick_started';

    public const ACTION_ITEM_PICKED = 'item_picked';

    public const ACTION_PICK_COMPLETED = 'pick_completed';

    public const ACTION_PICK_SHORTED = 'pick_shorted';

    // Pack workflow
    public const ACTION_PACK_STARTED = 'pack_started';

    public const ACTION_RATE_SHOPPED = 'rate_shopped';

    public const ACTION_LABEL_PURCHASED = 'label_purchased';

    public const ACTION_LABEL_VOIDED = 'label_voided';

    public const ACTION_ITEM_PACKED = 'item_packed';

    public const ACTION_PACK_COMPLETED = 'pack_completed';

    // Shipping
    public const ACTION_SHIPMENT_CREATED = 'shipment_created';

    public const ACTION_TRACKING_UPLOADED = 'tracking_uploaded';

    public const ACTION_TRACKING_UPDATED = 'tracking_updated';

    public const ACTION_DELIVERY_CONFIRMED = 'delivery_confirmed';

    // Returns
    public const ACTION_RETURN_REQUESTED = 'return_requested';

    public const ACTION_RETURN_RECEIVED = 'return_received';

    public const ACTION_RETURN_INSPECTED = 'return_inspected';

    public const ACTION_REFUND_PROCESSED = 'refund_processed';

    public const ACTION_ITEM_RESTOCKED = 'item_restocked';

    // Dropship
    public const ACTION_DROPSHIP_ROUTED = 'dropship_routed';

    public const ACTION_DROPSHIP_ACCEPTED = 'dropship_accepted';

    public const ACTION_DROPSHIP_REJECTED = 'dropship_rejected';

    public const ACTION_DROPSHIP_ESCALATED = 'dropship_escalated';

    // Printing
    public const ACTION_LABEL_PRINTED = 'label_printed';

    public const ACTION_PACKING_SLIP_PRINTED = 'packing_slip_printed';

    public const ACTION_PICK_LIST_PRINTED = 'pick_list_printed';

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function loggable(): MorphTo
    {
        return $this->morphTo();
    }

    public function scopeForOrder($query, string $orderId)
    {
        return $query->where('order_id', $orderId);
    }

    public function scopeAction($query, string $action)
    {
        return $query->where('action', $action);
    }

    public function scopeByUser($query, int $userId)
    {
        return $query->where('user_id', $userId);
    }

    public function scopeRecent($query, int $days = 7)
    {
        return $query->where('created_at', '>=', now()->subDays($days));
    }
}
