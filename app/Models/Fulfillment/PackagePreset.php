<?php

namespace App\Models\Fulfillment;

use App\Traits\BelongsToOrganization;
use App\Traits\HasUuid;
use Illuminate\Database\Eloquent\Model;

class PackagePreset extends Model
{
    use BelongsToOrganization, HasUuid;

    protected $guarded = [];

    protected $casts = [
        'length' => 'decimal:2',
        'width' => 'decimal:2',
        'height' => 'decimal:2',
        'weight_oz' => 'decimal:2',
        'is_default' => 'boolean',
        'is_active' => 'boolean',
        'sort_order' => 'integer',
    ];

    public const TYPE_BOX = 'box';

    public const TYPE_ENVELOPE = 'envelope';

    public const TYPE_SOFT_PACK = 'soft_pack';

    public const TYPE_CUSTOM = 'custom';

    public static function types(): array
    {
        return [
            self::TYPE_BOX,
            self::TYPE_ENVELOPE,
            self::TYPE_SOFT_PACK,
            self::TYPE_CUSTOM,
        ];
    }

    protected static function boot()
    {
        parent::boot();

        static::saving(function ($model) {
            if ($model->is_default) {
                static::where('organization_id', $model->organization_id)
                    ->where('id', '!=', $model->id ?: '')
                    ->update(['is_default' => false]);
            }
        });
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeDefault($query)
    {
        return $query->where('is_default', true);
    }

    public function scopeOrdered($query)
    {
        return $query->orderBy('sort_order')->orderBy('name');
    }

    public function getDimensionsLabelAttribute(): string
    {
        return "{$this->length}\" x {$this->width}\" x {$this->height}\"";
    }
}
