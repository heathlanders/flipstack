<?php

namespace App\Models\Fulfillment;

use App\Traits\BelongsToOrganization;
use App\Traits\HasUuid;
use Illuminate\Database\Eloquent\Model;

class CarrierAccount extends Model
{
    use BelongsToOrganization, HasUuid;

    protected $guarded = [];

    protected $casts = [
        'credentials' => 'array',
        'is_active' => 'boolean',
        'is_default' => 'boolean',
    ];

    protected $hidden = ['credentials'];

    public const CARRIER_USPS = 'USPS';

    public const CARRIER_UPS = 'UPS';

    public const CARRIER_FEDEX = 'FedEx';

    public const CARRIER_DHL = 'DHL';

    public static function carriers(): array
    {
        return [
            self::CARRIER_USPS,
            self::CARRIER_UPS,
            self::CARRIER_FEDEX,
            self::CARRIER_DHL,
        ];
    }

    protected static function boot()
    {
        parent::boot();

        static::saving(function ($model) {
            if ($model->is_default) {
                static::where('organization_id', $model->organization_id)
                    ->where('carrier', $model->carrier)
                    ->where('id', '!=', $model->id)
                    ->update(['is_default' => false]);
            }
        });
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeCarrier($query, string $carrier)
    {
        return $query->where('carrier', $carrier);
    }

    public function scopeDefault($query)
    {
        return $query->where('is_default', true);
    }
}
