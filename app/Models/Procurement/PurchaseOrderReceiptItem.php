<?php

namespace App\Models\Procurement;

use App\Models\Catalog\Product;
use App\Traits\HasUuid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PurchaseOrderReceiptItem extends Model
{
    use HasFactory, HasUuid;

    protected $fillable = [
        'receipt_id',
        'purchase_order_item_id',
        'product_id',
        'quantity_received',
        'condition',
        'condition_notes',
    ];

    protected $casts = [
        'quantity_received' => 'integer',
    ];

    public function receipt(): BelongsTo
    {
        return $this->belongsTo(PurchaseOrderReceipt::class, 'receipt_id');
    }

    public function purchaseOrderItem(): BelongsTo
    {
        return $this->belongsTo(PurchaseOrderItem::class, 'purchase_order_item_id');
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
