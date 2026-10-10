<?php

namespace App\Models\Customer;

use App\Models\Fulfillment\Order;
use App\Traits\BelongsToOrganization;
use App\Traits\HasUuid;
use App\Traits\LogsAudit;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Customer extends Model
{
    use BelongsToOrganization, HasFactory, HasUuid, LogsAudit, SoftDeletes;

    protected ?string $auditCategory = 'customer';

    protected $fillable = [
        'organization_id',
        'email',
        'name',
        'phone',
        'marketplace_source',
        'marketplace_customer_id',
        'first_order_at',
        'last_order_at',
        'total_orders',
        'total_spent',
        'average_order_value',
        'tags',
        'notes',
        'is_flagged',
        'flag_reason',
    ];

    protected $casts = [
        'first_order_at' => 'datetime',
        'last_order_at' => 'datetime',
        'total_orders' => 'integer',
        'total_spent' => 'decimal:2',
        'average_order_value' => 'decimal:2',
        'tags' => 'array',
        'is_flagged' => 'boolean',
    ];

    public function addresses(): HasMany
    {
        return $this->hasMany(CustomerAddress::class);
    }

    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }

    public function defaultShippingAddress()
    {
        return $this->addresses()
            ->where('type', 'shipping')
            ->where('is_default', true)
            ->first();
    }

    // Scopes

    public function scopeSearch($query, string $term)
    {
        return $query->where(function ($q) use ($term) {
            $q->where('name', 'ILIKE', "%{$term}%")
                ->orWhere('email', 'ILIKE', "%{$term}%")
                ->orWhere('phone', 'ILIKE', "%{$term}%");
        });
    }

    public function scopeByMarketplace($query, string $marketplace)
    {
        return $query->where('marketplace_source', $marketplace);
    }

    public function scopeFlagged($query)
    {
        return $query->where('is_flagged', true);
    }

    public function scopeTopSpenders($query, int $limit = 10)
    {
        return $query->orderByDesc('total_spent')->limit($limit);
    }

    public function scopeRepeatBuyers($query)
    {
        return $query->where('total_orders', '>', 1);
    }

    // Methods

    public function recalculateStats(): void
    {
        $orders = $this->orders()->whereNotIn('status', ['cancelled'])->get();
        $this->total_orders = $orders->count();
        $this->total_spent = $orders->sum('total_amount');
        $this->average_order_value = $this->total_orders > 0 ? round($this->total_spent / $this->total_orders, 2) : 0;
        $this->first_order_at = $orders->min('created_at');
        $this->last_order_at = $orders->max('created_at');
        $this->saveQuietly();
    }
}
