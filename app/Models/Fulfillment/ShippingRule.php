<?php

namespace App\Models\Fulfillment;

use App\Traits\BelongsToOrganization;
use App\Traits\HasUuid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ShippingRule extends Model
{
    use BelongsToOrganization, HasFactory, HasUuid;

    public const CARRIER_USPS = 'USPS';

    public const CARRIER_UPS = 'UPS';

    public const CARRIER_FEDEX = 'FedEx';

    public const CARRIER_DHL = 'DHL';

    protected $fillable = [
        'organization_id',
        'name',
        'priority',
        'conditions',
        'action_carrier',
        'action_service',
        'action_package_preset_id',
        'is_active',
    ];

    protected $casts = [
        'conditions' => 'array',
        'is_active' => 'boolean',
        'priority' => 'integer',
    ];

    public static function carriers(): array
    {
        return [
            self::CARRIER_USPS,
            self::CARRIER_UPS,
            self::CARRIER_FEDEX,
            self::CARRIER_DHL,
        ];
    }

    public function packagePreset(): BelongsTo
    {
        return $this->belongsTo(PackagePreset::class, 'action_package_preset_id');
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeOrdered($query)
    {
        return $query->orderBy('priority', 'asc');
    }
}
