<?php

namespace App\Models\Analytics;

use App\Models\Catalog\Product;
use App\Models\Organization\Organization;
use App\Traits\BelongsToOrganization;
use App\Traits\HasUuid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DemandForecast extends Model
{
    use BelongsToOrganization, HasFactory, HasUuid;

    public const CONFIDENCE_LOW = 'low';

    public const CONFIDENCE_MEDIUM = 'medium';

    public const CONFIDENCE_HIGH = 'high';

    public const ALGORITHM_MOVING_AVERAGE = 'moving_average';

    public const ALGORITHM_WEIGHTED_AVERAGE = 'weighted_average';

    public const ALGORITHM_EXPONENTIAL_SMOOTHING = 'exponential_smoothing';

    protected $fillable = [
        'organization_id',
        'product_id',
        'forecast_date',
        'forecasted_quantity',
        'actual_quantity',
        'confidence_level',
        'algorithm',
        'parameters',
        'generated_at',
    ];

    protected $casts = [
        'forecast_date' => 'date',
        'forecasted_quantity' => 'integer',
        'actual_quantity' => 'integer',
        'parameters' => 'array',
        'generated_at' => 'datetime',
    ];

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
