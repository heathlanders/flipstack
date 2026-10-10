<?php

namespace App\Models\Analytics;

use App\Models\Fulfillment\Order;
use App\Traits\BelongsToOrganization;
use App\Traits\HasUuid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OrderFinancial extends Model
{
    use BelongsToOrganization, HasFactory, HasUuid;

    protected $fillable = [
        'organization_id',
        'order_id',
        'revenue',
        'cost_of_goods',
        'marketplace_fees',
        'shipping_label_cost',
        'shipping_charged',
        'tax_collected',
        'net_profit',
        'profit_margin_pct',
        'calculated_at',
    ];

    protected $casts = [
        'revenue' => 'decimal:2',
        'cost_of_goods' => 'decimal:2',
        'marketplace_fees' => 'decimal:2',
        'shipping_label_cost' => 'decimal:2',
        'shipping_charged' => 'decimal:2',
        'tax_collected' => 'decimal:2',
        'net_profit' => 'decimal:2',
        'profit_margin_pct' => 'decimal:2',
        'calculated_at' => 'datetime',
    ];

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }
}
