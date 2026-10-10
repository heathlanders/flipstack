<?php

namespace App\Models\Inventory;

use App\Models\Catalog\Product;
use App\Models\Organization\Organization;
use App\Models\User\User;
use App\Traits\BelongsToOrganization;
use App\Traits\HasUuid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PartOutWorkOrder extends Model
{
    use BelongsToOrganization, HasFactory, HasUuid;

    public const STATUS_DRAFT = 'draft';

    public const STATUS_IN_PROGRESS = 'in_progress';

    public const STATUS_COMPLETED = 'completed';

    public const STATUS_CANCELLED = 'cancelled';

    protected $fillable = [
        'organization_id',
        'template_id',
        'work_order_number',
        'source_product_id',
        'source_quantity',
        'source_unit_cost',
        'source_total_cost',
        'remaining_parent_cost',
        'cost_allocation_method',
        'location_id',
        'status',
        'parent_continues',
        'parent_new_name',
        'notes',
        'started_at',
        'completed_at',
        'cancelled_at',
        'created_by',
        'completed_by',
        'assigned_to',
    ];

    protected $casts = [
        'source_quantity' => 'integer',
        'source_unit_cost' => 'decimal:2',
        'source_total_cost' => 'decimal:2',
        'remaining_parent_cost' => 'decimal:2',
        'parent_continues' => 'boolean',
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
        'cancelled_at' => 'datetime',
    ];

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function template(): BelongsTo
    {
        return $this->belongsTo(PartOutTemplate::class, 'template_id');
    }

    public function sourceProduct(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'source_product_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(PartOutWorkOrderItem::class, 'work_order_id');
    }

    public function location(): BelongsTo
    {
        return $this->belongsTo(InventoryLocation::class, 'location_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function completedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'completed_by');
    }

    public function createdProducts(): HasMany
    {
        return $this->hasMany(Product::class, 'part_out_work_order_id');
    }

    public function canExecute(): bool
    {
        return in_array($this->status, [self::STATUS_DRAFT, self::STATUS_IN_PROGRESS]);
    }

    public function canCancel(): bool
    {
        return in_array($this->status, [self::STATUS_DRAFT, self::STATUS_IN_PROGRESS]);
    }

    public function assignedTo(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    public function getExtractionProgress(): array
    {
        $items = $this->items;
        $total = $items->count();
        $extracted = $items->where('extraction_status', PartOutWorkOrderItem::EXTRACTION_EXTRACTED)->count();
        $skipped = $items->where('extraction_status', PartOutWorkOrderItem::EXTRACTION_SKIPPED)->count();

        return [
            'total' => $total,
            'extracted' => $extracted,
            'skipped' => $skipped,
            'pending' => $total - $extracted - $skipped,
        ];
    }

    public function getTotalAllocatedCost(): float
    {
        return (float) $this->items()->sum('allocated_cost');
    }

    public function getUnallocatedCost(): float
    {
        $allocated = $this->getTotalAllocatedCost();
        $remaining = (float) ($this->remaining_parent_cost ?? 0);

        return (float) $this->source_total_cost - $allocated - $remaining;
    }

    public function getRemainingParentCost(): float
    {
        return (float) ($this->remaining_parent_cost ?? 0);
    }

    public function scopeByStatus($query, string $status)
    {
        return $query->where('status', $status);
    }
}
