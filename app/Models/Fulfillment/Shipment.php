<?php

namespace App\Models\Fulfillment;

use App\Models\Inventory\InventoryLocation;
use App\Models\User\User;
use App\Support\CarrierTrackingUrl;
use App\Traits\BelongsToOrganization;
use App\Traits\HasUuid;
use App\Traits\LogsAudit;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Shipment extends Model
{
    use BelongsToOrganization, HasUuid, LogsAudit;

    protected ?string $auditCategory = 'fulfillment';

    protected array $auditExclude = ['marketplace_data', 'last_tracking_synced_at'];

    protected $guarded = [];

    protected $appends = ['tracking_link'];

    protected $casts = [
        'marketplace_data' => 'array',
        'label_purchased_at' => 'datetime',
        'packed_at' => 'datetime',
        'shipped_at' => 'datetime',
        'tracking_uploaded_at' => 'datetime',
        'last_tracking_synced_at' => 'datetime',
        'estimated_delivery_date' => 'date',
        'actual_delivery_date' => 'date',
        'label_cost' => 'decimal:2',
        'insurance_cost' => 'decimal:2',
        'total_shipping_cost' => 'decimal:2',
        'package_weight_oz' => 'decimal:2',
        'package_length' => 'decimal:2',
        'package_width' => 'decimal:2',
        'package_height' => 'decimal:2',
        'tracking_uploaded_to_marketplace' => 'boolean',
    ];

    public const STATUS_PENDING = 'pending';

    public const STATUS_LABEL_PURCHASED = 'label_purchased';

    public const STATUS_PACKED = 'packed';

    public const STATUS_SHIPPED = 'shipped';

    public const STATUS_IN_TRANSIT = 'in_transit';

    public const STATUS_OUT_FOR_DELIVERY = 'out_for_delivery';

    public const STATUS_DELIVERED = 'delivered';

    public const STATUS_FAILED = 'failed';

    public const STATUS_RETURNED_TO_SENDER = 'returned_to_sender';

    public const STATUS_CANCELLED = 'cancelled';

    public function isShipStation(): bool
    {
        return $this->shipping_provider === 'shipstation';
    }

    public function isEasyPost(): bool
    {
        return $this->shipping_provider === 'easypost';
    }

    public function isShippingEasy(): bool
    {
        return $this->shipping_provider === 'shippingeasy';
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function location(): BelongsTo
    {
        return $this->belongsTo(InventoryLocation::class);
    }

    public function packedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'packed_by');
    }

    public function shippedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'shipped_by');
    }

    public function shipmentItems(): HasMany
    {
        return $this->hasMany(ShipmentItem::class);
    }

    public function trackingEvents(): HasMany
    {
        return $this->hasMany(ShipmentTrackingEvent::class)->orderByDesc('occurred_at');
    }

    public function hasLabel(): bool
    {
        return ! empty($this->label_url);
    }

    public function hasTracking(): bool
    {
        return ! empty($this->tracking_number);
    }

    public function isTrackingUploaded(): bool
    {
        return $this->tracking_uploaded_to_marketplace;
    }

    /**
     * Resolved deeplink to the tracking page: prefers a provider-supplied
     * tracking_url (EasyPost, ShipStation, etc.), falls back to a public
     * carrier deeplink built from carrier + tracking_number.
     */
    public function getTrackingLinkAttribute(): ?string
    {
        if (! empty($this->tracking_url)) {
            return $this->tracking_url;
        }

        return CarrierTrackingUrl::for($this->carrier, $this->tracking_number);
    }

    /**
     * Normalize tracking numbers on the way in: strip whitespace and collapse
     * a value that's the same string repeated twice (a known UI paste bug).
     */
    public function setTrackingNumberAttribute(?string $value): void
    {
        $this->attributes['tracking_number'] = self::normalizeTrackingNumber($value);
    }

    public static function normalizeTrackingNumber(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $clean = preg_replace('/\s+/', '', $value) ?? '';
        if ($clean === '') {
            return null;
        }

        $len = strlen($clean);
        if ($len % 2 === 0) {
            $half = (int) ($len / 2);
            $left = substr($clean, 0, $half);
            $right = substr($clean, $half);
            if ($left === $right) {
                return $left;
            }
        }

        return $clean;
    }

    public function getFormattedShipFromAttribute(): string
    {
        return implode(', ', array_filter([
            $this->ship_from_address_line1,
            $this->ship_from_city,
            "{$this->ship_from_state} {$this->ship_from_postal_code}",
        ]));
    }

    public function getPackageDimensionsAttribute(): ?array
    {
        if (! $this->package_length && ! $this->package_width && ! $this->package_height) {
            return null;
        }

        return [
            'length' => $this->package_length,
            'width' => $this->package_width,
            'height' => $this->package_height,
            'weight_oz' => $this->package_weight_oz,
        ];
    }

    public static function generateShipmentNumber(): string
    {
        $date = now()->format('Ymd');
        $sequence = static::whereDate('created_at', today())->count() + 1;

        return sprintf('SHP-%s-%04d', $date, $sequence);
    }
}
