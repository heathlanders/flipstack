<?php

namespace App\Models\Fulfillment;

use App\Models\Catalog\Product;
use App\Models\Ebay\EbayListing;
use App\Models\Inventory\InventoryLocation;
use App\Models\Inventory\Supplier;
use App\Models\User\User;
use App\Traits\HasUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class OrderItem extends Model
{
    use HasUuid;

    protected $guarded = [];

    protected $casts = [
        'marketplace_data' => 'array',
        'picked_at' => 'datetime',
        'quantity' => 'integer',
        'unit_price' => 'decimal:2',
        'total_price' => 'decimal:2',
        'tax_amount' => 'decimal:2',
        'shipping_cost' => 'decimal:2',
        'weight_oz' => 'decimal:2',
    ];

    public const PICK_PENDING = 'pending';

    public const PICK_PICKED = 'picked';

    public const PICK_SHORT = 'short';

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function ebayListing(): BelongsTo
    {
        return $this->belongsTo(EbayListing::class);
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    public function location(): BelongsTo
    {
        return $this->belongsTo(InventoryLocation::class);
    }

    public function pickedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'picked_by');
    }

    public function shipmentItems(): HasMany
    {
        return $this->hasMany(ShipmentItem::class);
    }

    public function isPicked(): bool
    {
        return $this->pick_status === self::PICK_PICKED;
    }

    public function isShort(): bool
    {
        return $this->pick_status === self::PICK_SHORT;
    }

    public function isPending(): bool
    {
        return $this->pick_status === self::PICK_PENDING;
    }
}
