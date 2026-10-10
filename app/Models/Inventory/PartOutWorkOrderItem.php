<?php

namespace App\Models\Inventory;

use App\Models\Catalog\Product;
use App\Models\User\User;
use App\Traits\HasUuid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PartOutWorkOrderItem extends Model
{
    use HasFactory, HasUuid;

    public const EXTRACTION_PENDING = 'pending';

    public const EXTRACTION_EXTRACTED = 'extracted';

    public const EXTRACTION_SKIPPED = 'skipped';

    protected $fillable = [
        'work_order_id',
        'template_item_id',
        'component_product_id',
        'name',
        'part_number',
        'manufacturer',
        'category',
        'quantity',
        'allocated_cost',
        'allocated_cost_per_unit',
        'cost_allocation_pct',
        'suggested_selling_price',
        'condition',
        'notes',
        'extraction_status',
        'extracted_at',
        'extracted_by',
        'extraction_notes',
        'barcode_verified',
        'scanned_barcode',
    ];

    protected $casts = [
        'quantity' => 'integer',
        'allocated_cost' => 'decimal:2',
        'allocated_cost_per_unit' => 'decimal:2',
        'cost_allocation_pct' => 'decimal:2',
        'suggested_selling_price' => 'decimal:2',
        'extracted_at' => 'datetime',
        'barcode_verified' => 'boolean',
    ];

    public function isPending(): bool
    {
        return $this->extraction_status === self::EXTRACTION_PENDING;
    }

    public function isExtracted(): bool
    {
        return $this->extraction_status === self::EXTRACTION_EXTRACTED;
    }

    public function isSkipped(): bool
    {
        return $this->extraction_status === self::EXTRACTION_SKIPPED;
    }

    public function extractedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'extracted_by');
    }

    public function workOrder(): BelongsTo
    {
        return $this->belongsTo(PartOutWorkOrder::class, 'work_order_id');
    }

    public function templateItem(): BelongsTo
    {
        return $this->belongsTo(PartOutTemplateItem::class, 'template_item_id');
    }

    public function componentProduct(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'component_product_id');
    }
}
