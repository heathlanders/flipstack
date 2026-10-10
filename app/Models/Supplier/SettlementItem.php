<?php

namespace App\Models\Supplier;

use App\Models\Catalog\Product;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SettlementItem extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'settlement_id',
        'product_id',
        'order_id',
        'order_item_id',
        'sale_date',
        'sale_reference',
        'marketplace',
        'sale_price',
        'original_cost',
        'shipping_collected',
        'tax_collected',
        'marketplace_fees',
        'net_sale_amount',
        'commission_structure_id',
        'commission_type',
        'commission_rate',
        'commission_amount',
        'tier_adjustment',
        'bonus_amount',
        'final_commission',
        'supplier_payout',
        'status',
        'is_returned',
        'return_date',
        'refund_amount',
        'return_reason',
        'notes',
        'metadata',
    ];

    protected $casts = [
        'sale_date' => 'datetime',
        'sale_price' => 'decimal:2',
        'original_cost' => 'decimal:2',
        'shipping_collected' => 'decimal:2',
        'tax_collected' => 'decimal:2',
        'marketplace_fees' => 'decimal:2',
        'net_sale_amount' => 'decimal:2',
        'commission_rate' => 'decimal:2',
        'commission_amount' => 'decimal:2',
        'tier_adjustment' => 'decimal:2',
        'bonus_amount' => 'decimal:2',
        'final_commission' => 'decimal:2',
        'supplier_payout' => 'decimal:2',
        'is_returned' => 'boolean',
        'return_date' => 'datetime',
        'refund_amount' => 'decimal:2',
        'metadata' => 'array',
    ];

    // Relationships
    public function settlement(): BelongsTo
    {
        return $this->belongsTo(Settlement::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function commissionStructure(): BelongsTo
    {
        return $this->belongsTo(CommissionStructure::class);
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(\App\Models\Fulfillment\Order::class);
    }

    public function orderItem(): BelongsTo
    {
        return $this->belongsTo(\App\Models\Fulfillment\OrderItem::class);
    }

    // Scopes
    public function scopeForSettlement($query, $settlementId)
    {
        return $query->where('settlement_id', $settlementId);
    }

    public function scopeStatus($query, $status)
    {
        return $query->where('status', $status);
    }

    public function scopeReturned($query)
    {
        return $query->where('is_returned', true);
    }

    public function scopeNotReturned($query)
    {
        return $query->where('is_returned', false);
    }

    public function scopeForMarketplace($query, $marketplace)
    {
        return $query->where('marketplace', $marketplace);
    }

    public function scopeInDateRange($query, $start, $end)
    {
        return $query->whereBetween('sale_date', [$start, $end]);
    }

    // Methods
    public function calculateSupplierPayout(): float
    {
        // Supplier payout = Sale Price - Commission - Marketplace Fees
        return $this->net_sale_amount - $this->final_commission;
    }

    public function markAsReturned(float $refundAmount, ?string $reason = null): self
    {
        $this->update([
            'is_returned' => true,
            'return_date' => now(),
            'refund_amount' => $refundAmount,
            'return_reason' => $reason,
            'status' => 'adjusted',
        ]);

        // Recalculate settlement totals
        $this->settlement->recalculateTotals();

        return $this;
    }

    public function approve(): self
    {
        $this->update(['status' => 'approved']);

        return $this;
    }

    public function dispute(?string $reason = null): self
    {
        $this->update([
            'status' => 'disputed',
            'notes' => $reason,
        ]);

        return $this;
    }

    public function void(): self
    {
        $this->update(['status' => 'voided']);
        $this->settlement->recalculateTotals();

        return $this;
    }

    public function recalculateCommission(): self
    {
        if (! $this->commissionStructure) {
            return $this;
        }

        $result = $this->commissionStructure->calculateCommission(
            $this->sale_price,
            $this->product?->category_id,
            $this->settlement->supplier->current_tier_id
        );

        $this->update([
            'commission_amount' => $result['commission'],
            'final_commission' => $result['commission'] + $this->tier_adjustment + $this->bonus_amount,
            'supplier_payout' => $this->net_sale_amount - $result['commission'] - $this->tier_adjustment - $this->bonus_amount,
        ]);

        return $this;
    }

    // Accessors
    public function getProfitMarginAttribute(): float
    {
        if (! $this->original_cost || $this->original_cost == 0) {
            return 0;
        }

        return (($this->sale_price - $this->original_cost) / $this->sale_price) * 100;
    }

    public function getEffectiveCommissionRateAttribute(): float
    {
        if ($this->sale_price == 0) {
            return 0;
        }

        return ($this->final_commission / $this->sale_price) * 100;
    }

    // Static Methods
    public static function createFromSale(
        Settlement $settlement,
        Product $product,
        array $saleData,
        ?CommissionStructure $commissionStructure = null
    ): self {
        $salePrice = $saleData['sale_price'];
        $marketplaceFees = $saleData['marketplace_fees'] ?? 0;
        $shippingCollected = $saleData['shipping_collected'] ?? 0;
        $taxCollected = $saleData['tax_collected'] ?? 0;

        $netSaleAmount = $salePrice + $shippingCollected + $taxCollected - $marketplaceFees;

        // Calculate commission
        $commissionResult = ['commission' => 0, 'breakdown' => []];
        if ($commissionStructure) {
            $commissionResult = $commissionStructure->calculateCommission(
                $salePrice,
                $product->category_id,
                $settlement->supplier->current_tier_id
            );
        }

        $finalCommission = $commissionResult['commission'];
        $supplierPayout = $netSaleAmount - $finalCommission;

        return self::create([
            'settlement_id' => $settlement->id,
            'product_id' => $product->id,
            'order_id' => $saleData['order_id'] ?? null,
            'order_item_id' => $saleData['order_item_id'] ?? null,
            'sale_date' => $saleData['sale_date'] ?? now(),
            'sale_reference' => $saleData['sale_reference'] ?? null,
            'marketplace' => $saleData['marketplace'] ?? 'direct',
            'sale_price' => $salePrice,
            'original_cost' => $product->cost_price,
            'shipping_collected' => $shippingCollected,
            'tax_collected' => $taxCollected,
            'marketplace_fees' => $marketplaceFees,
            'net_sale_amount' => $netSaleAmount,
            'commission_structure_id' => $commissionStructure?->id,
            'commission_type' => $commissionStructure?->type->value ?? 'percentage',
            'commission_rate' => $commissionStructure?->base_percentage ?? 0,
            'commission_amount' => $commissionResult['commission'],
            'tier_adjustment' => 0,
            'bonus_amount' => 0,
            'final_commission' => $finalCommission,
            'supplier_payout' => $supplierPayout,
            'status' => 'pending',
            'metadata' => [
                'commission_breakdown' => $commissionResult['breakdown'] ?? [],
            ],
        ]);
    }
}
