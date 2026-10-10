<?php

namespace App\Models\Fulfillment;

use App\Models\Customer\Customer;
use App\Models\Integration\IntegrationCredential;
use App\Models\Inventory\InventoryLocation;
use App\Models\Inventory\Supplier;
use App\Models\Organization\Team;
use App\Models\User\User;
use App\Traits\BelongsToOrganization;
use App\Traits\HasUuid;
use App\Traits\LogsAudit;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

class Order extends Model
{
    use BelongsToOrganization, HasUuid, LogsAudit, SoftDeletes;

    protected ?string $auditCategory = 'fulfillment';

    protected array $auditExclude = ['marketplace_data', 'last_synced_at'];

    protected $guarded = [];

    protected $casts = [
        'marketplace_data' => 'array',
        'ship_by_date' => 'datetime',
        'paid_at' => 'datetime',
        'shipped_at' => 'datetime',
        'delivered_at' => 'datetime',
        'cancelled_at' => 'datetime',
        'last_synced_at' => 'datetime',
        'subtotal' => 'decimal:2',
        'shipping_cost' => 'decimal:2',
        'tax_amount' => 'decimal:2',
        'marketplace_fees' => 'decimal:2',
        'total_amount' => 'decimal:2',
        'items_count' => 'integer',
    ];

    public const STATUS_PENDING = 'pending';

    public const STATUS_ASSIGNED = 'assigned';

    public const STATUS_PICKING = 'picking';

    public const STATUS_PICKED = 'picked';

    public const STATUS_PACKING = 'packing';

    public const STATUS_PACKED = 'packed';

    public const STATUS_SHIPPED = 'shipped';

    public const STATUS_IN_TRANSIT = 'in_transit';

    public const STATUS_DELIVERED = 'delivered';

    public const STATUS_CANCELLED = 'cancelled';

    public const STATUS_ON_HOLD = 'on_hold';

    public const STATUS_RETURN_INITIATED = 'return_initiated';

    public const STATUS_RETURNED = 'returned';

    public const PRIORITY_STANDARD = 'standard';

    public const PRIORITY_HIGH = 'high';

    public const PRIORITY_URGENT = 'urgent';

    public const FULFILLMENT_ORG = 'org_fulfilled';

    public const FULFILLMENT_DROPSHIP = 'supplier_dropship';

    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    public function credential(): BelongsTo
    {
        return $this->belongsTo(IntegrationCredential::class, 'credential_id');
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    public function location(): BelongsTo
    {
        return $this->belongsTo(InventoryLocation::class);
    }

    public function assignedPicker(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_picker_id');
    }

    public function assignedPacker(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_packer_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    public function shipments(): HasMany
    {
        return $this->hasMany(Shipment::class);
    }

    public function shippingRates(): HasMany
    {
        return $this->hasMany(ShippingRate::class);
    }

    public function fulfillmentLogs(): HasMany
    {
        return $this->hasMany(FulfillmentLog::class);
    }

    public function returnRequests(): HasMany
    {
        return $this->hasMany(ReturnRequest::class);
    }

    public function supplierFulfillmentRequest(): HasOne
    {
        return $this->hasOne(SupplierFulfillmentRequest::class);
    }

    public function scopeStatus($query, string $status)
    {
        return $query->where('status', $status);
    }

    public function scopePriority($query, string $priority)
    {
        return $query->where('priority', $priority);
    }

    public function scopeMarketplace($query, string $marketplace)
    {
        return $query->where('marketplace', $marketplace);
    }

    public function scopeNeedsShipping($query)
    {
        return $query->whereIn('status', [self::STATUS_PENDING, self::STATUS_ASSIGNED, self::STATUS_PICKING, self::STATUS_PICKED, self::STATUS_PACKING, self::STATUS_PACKED]);
    }

    public function scopeOverdue($query)
    {
        return $query->whereNotNull('ship_by_date')
            ->where('ship_by_date', '<', now())
            ->whereNotIn('status', [self::STATUS_SHIPPED, self::STATUS_IN_TRANSIT, self::STATUS_DELIVERED, self::STATUS_CANCELLED]);
    }

    public function scopeUrgent($query)
    {
        return $query->where('priority', self::PRIORITY_URGENT);
    }

    public function isOverdue(): bool
    {
        return $this->ship_by_date && $this->ship_by_date->isPast() && ! in_array($this->status, [self::STATUS_SHIPPED, self::STATUS_IN_TRANSIT, self::STATUS_DELIVERED, self::STATUS_CANCELLED]);
    }

    public function canBePicked(): bool
    {
        return in_array($this->status, [self::STATUS_ASSIGNED, self::STATUS_PICKING]);
    }

    public function canBePacked(): bool
    {
        return in_array($this->status, [self::STATUS_PICKED, self::STATUS_PACKING]);
    }

    public function canBeShipped(): bool
    {
        return in_array($this->status, [self::STATUS_PACKED]);
    }

    public function getShippingAddressAttribute(): array
    {
        return [
            'name' => $this->shipping_address_name,
            'line1' => $this->shipping_address_line1,
            'line2' => $this->shipping_address_line2,
            'city' => $this->shipping_city,
            'state' => $this->shipping_state,
            'postal_code' => $this->shipping_postal_code,
            'country' => $this->shipping_country,
        ];
    }

    public function getFormattedShippingAddressAttribute(): string
    {
        $parts = array_filter([
            $this->shipping_address_name,
            $this->shipping_address_line1,
            $this->shipping_address_line2,
            "{$this->shipping_city}, {$this->shipping_state} {$this->shipping_postal_code}",
            $this->shipping_country !== 'US' ? $this->shipping_country : null,
        ]);

        return implode("\n", $parts);
    }
}
