<?php

namespace App\Models\Inventory;

use App\Models\Organization\Organization;
use App\Models\Procurement\PurchaseOrder;
use App\Traits\BelongsToOrganization;
use App\Traits\HasUuid;
use App\Traits\LogsAudit;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class InventoryBatch extends Model
{
    use BelongsToOrganization, HasFactory, HasUuid, LogsAudit, SoftDeletes;

    protected ?string $auditCategory = 'inventory';

    protected ?string $auditActionPrefix = 'inventory.batch';

    protected $table = 'inventory_batches';

    protected $fillable = [
        'organization_id',
        'batch_number',
        'supplier_id',
        'purchase_order_id',
        'name',
        'description',
        'acquisition_date',
        'acquisition_cost',
        'item_count',
        'cost_per_item',
        'source_description',
        'notes',
        'status',
    ];

    protected $casts = [
        'acquisition_date' => 'date',
        'acquisition_cost' => 'decimal:2',
        'item_count' => 'integer',
        'cost_per_item' => 'decimal:2',
    ];

    public const STATUS_OPEN = 'open';

    public const STATUS_CLOSED = 'closed';

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    public function purchaseOrder(): BelongsTo
    {
        return $this->belongsTo(PurchaseOrder::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(InventoryBatchItem::class, 'batch_id');
    }

    public function scopeOpen($query)
    {
        return $query->where('status', self::STATUS_OPEN);
    }

    public function scopeClosed($query)
    {
        return $query->where('status', self::STATUS_CLOSED);
    }
}
