<?php

namespace App\Models\Catalog;

use App\Models\Organization\Organization;
use App\Traits\BelongsToOrganization;
use App\Traits\HasUuid;
use App\Traits\LogsAudit;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ProductBundle extends Model
{
    use BelongsToOrganization, HasFactory, HasUuid, LogsAudit;

    protected ?string $auditCategory = 'product';

    protected ?string $auditActionPrefix = 'bundle';

    protected $fillable = [
        'organization_id',
        'product_id',
        'name',
        'description',
        'bundle_type',
        'pick_n_quantity',
        'is_active',
    ];

    protected $casts = [
        'pick_n_quantity' => 'integer',
        'is_active' => 'boolean',
    ];

    public const TYPE_FIXED = 'fixed';

    public const TYPE_PICK_N = 'pick_n';

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(ProductBundleItem::class, 'bundle_id');
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Calculate available quantity based on component stock.
     * For fixed bundles: MIN(component.available_quantity / component.quantity)
     * For pick_n bundles: sum-based calculation across optional components.
     */
    public function calculateAvailableQuantity(): int
    {
        $this->loadMissing('items.componentProduct');

        if ($this->items->isEmpty()) {
            return 0;
        }

        if ($this->bundle_type === self::TYPE_PICK_N) {
            $available = $this->items
                ->filter(fn ($item) => $item->componentProduct)
                ->map(fn ($item) => intdiv($item->componentProduct->available_quantity ?? 0, max($item->quantity, 1)));

            return $available->isEmpty() ? 0 : $available->sort()->values()->slice(0, $this->pick_n_quantity ?? 1)->min() ?? 0;
        }

        // Fixed bundle: limited by the scarcest required component
        $requiredItems = $this->items->filter(fn ($item) => $item->is_required && $item->componentProduct);

        if ($requiredItems->isEmpty()) {
            return 0;
        }

        return $requiredItems
            ->map(fn ($item) => intdiv($item->componentProduct->available_quantity ?? 0, max($item->quantity, 1)))
            ->min() ?? 0;
    }
}
