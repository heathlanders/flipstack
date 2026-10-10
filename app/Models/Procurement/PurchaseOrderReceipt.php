<?php

namespace App\Models\Procurement;

use App\Models\Inventory\InventoryLocation;
use App\Models\User\User;
use App\Traits\HasUuid;
use App\Traits\LogsAudit;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PurchaseOrderReceipt extends Model
{
    use HasFactory, HasUuid, LogsAudit;

    protected ?string $auditCategory = 'procurement';

    protected ?string $auditActionPrefix = 'po.receipt';

    protected $fillable = [
        'purchase_order_id',
        'received_by',
        'location_id',
        'received_at',
        'notes',
    ];

    protected $casts = [
        'received_at' => 'datetime',
    ];

    public function purchaseOrder(): BelongsTo
    {
        return $this->belongsTo(PurchaseOrder::class);
    }

    public function receivedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'received_by');
    }

    public function location(): BelongsTo
    {
        return $this->belongsTo(InventoryLocation::class, 'location_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(PurchaseOrderReceiptItem::class, 'receipt_id');
    }

    public function getTotalQuantityReceivedAttribute(): int
    {
        return $this->items()->sum('quantity_received');
    }
}
