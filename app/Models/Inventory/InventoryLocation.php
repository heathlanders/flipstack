<?php

namespace App\Models\Inventory;

use App\Models\Organization\Organization;
use App\Traits\BelongsToOrganization;
use App\Traits\HasUuid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class InventoryLocation extends Model
{
    use BelongsToOrganization, HasFactory, HasUuid;

    protected $fillable = [
        'organization_id',
        'name',
        'code',
        'type',
        'address_line1',
        'address_line2',
        'city',
        'state',
        'postal_code',
        'country',
        'contact_person',
        'email',
        'phone',
        'is_active',
        'is_default',
        // Shipping config
        'easypost_address_id',
        'is_ship_from',
        'ship_from_verified',
        'default_weight_oz',
        'default_length',
        'default_width',
        'default_height',
        // Printer assignments
        'shipping_label_printer_id',
        'packing_slip_printer_id',
        'pick_list_printer_id',
        'auto_print_shipping_label',
        'auto_print_packing_slip',
        'auto_print_pick_list',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'is_default' => 'boolean',
        'is_ship_from' => 'boolean',
        'ship_from_verified' => 'boolean',
        'auto_print_shipping_label' => 'boolean',
        'auto_print_packing_slip' => 'boolean',
        'auto_print_pick_list' => 'boolean',
        'default_weight_oz' => 'decimal:2',
        'default_length' => 'decimal:2',
        'default_width' => 'decimal:2',
        'default_height' => 'decimal:2',
    ];

    // Relationships
    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function inventoryLevels(): HasMany
    {
        return $this->hasMany(InventoryLevel::class, 'location_id');
    }

    public function inventoryMovements(): HasMany
    {
        return $this->hasMany(InventoryMovement::class, 'location_id');
    }

    // Scopes
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeDefault($query)
    {
        return $query->where('is_default', true);
    }

    public function scopeByType($query, string $type)
    {
        return $query->where('type', $type);
    }

    public function scopeShipFrom($query)
    {
        return $query->where('is_ship_from', true);
    }

    public function shippingLabelPrinter(): BelongsTo
    {
        return $this->belongsTo(\App\Models\Printing\OrganizationPrinter::class, 'shipping_label_printer_id');
    }

    public function packingSlipPrinter(): BelongsTo
    {
        return $this->belongsTo(\App\Models\Printing\OrganizationPrinter::class, 'packing_slip_printer_id');
    }

    public function pickListPrinter(): BelongsTo
    {
        return $this->belongsTo(\App\Models\Printing\OrganizationPrinter::class, 'pick_list_printer_id');
    }

    public function orders(): HasMany
    {
        return $this->hasMany(\App\Models\Fulfillment\Order::class, 'location_id');
    }

    // Helper methods
    public function getFullAddressAttribute(): string
    {
        $parts = array_filter([
            $this->address_line1,
            $this->address_line2,
            $this->city,
            $this->state,
            $this->postal_code,
            $this->country,
        ]);

        return implode(', ', $parts);
    }

    public function getTotalInventoryValue(): float
    {
        return $this->inventoryLevels()
            ->sum(\DB::raw('quantity_on_hand * average_cost'));
    }

    public function getTotalProductCount(): int
    {
        return $this->inventoryLevels()
            ->where('quantity_on_hand', '>', 0)
            ->count();
    }
}
