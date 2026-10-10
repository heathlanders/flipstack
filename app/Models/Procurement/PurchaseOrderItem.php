<?php

namespace App\Models\Procurement;

use App\Models\Catalog\Product;
use App\Models\Inventory\SupplierProduct;
use App\Traits\HasUuid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PurchaseOrderItem extends Model
{
    use HasFactory, HasUuid;

    protected $fillable = [
        'purchase_order_id',
        'product_id',
        'supplier_product_id',
        'description',
        'sku',
        'quantity_ordered',
        'quantity_received',
        'unit_cost',
        'total_cost',
        'notes',
    ];

    protected $casts = [
        'quantity_ordered' => 'integer',
        'quantity_received' => 'integer',
        'unit_cost' => 'decimal:2',
        'total_cost' => 'decimal:2',
    ];

    protected static function booted(): void
    {
        static::saving(function (self $item) {
            $item->total_cost = $item->quantity_ordered * $item->unit_cost;
        });

        static::saved(function (self $item) {
            $item->purchaseOrder?->recalculateTotals();
        });

        static::deleted(function (self $item) {
            $item->purchaseOrder?->recalculateTotals();
        });
    }

    public function purchaseOrder(): BelongsTo
    {
        return $this->belongsTo(PurchaseOrder::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function supplierProduct(): BelongsTo
    {
        return $this->belongsTo(SupplierProduct::class);
    }

    public function receiptItems(): HasMany
    {
        return $this->hasMany(PurchaseOrderReceiptItem::class, 'purchase_order_item_id');
    }

    public function isFullyReceived(): bool
    {
        return $this->quantity_received >= $this->quantity_ordered;
    }

    public function isPartiallyReceived(): bool
    {
        return $this->quantity_received > 0 && $this->quantity_received < $this->quantity_ordered;
    }

    public function getRemainingQuantityAttribute(): int
    {
        return max(0, $this->quantity_ordered - $this->quantity_received);
    }
}
