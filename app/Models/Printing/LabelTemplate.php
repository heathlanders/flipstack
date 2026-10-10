<?php

namespace App\Models\Printing;

use App\Traits\BelongsToOrganization;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class LabelTemplate extends Model
{
    use BelongsToOrganization, HasFactory;

    protected $fillable = [
        'organization_id',
        'name',
        'description',
        'width_mm',
        'height_mm',
        'is_active',
        'is_default',
        'template_data',
        'usage_count',
    ];

    protected $casts = [
        'width_mm' => 'decimal:2',
        'height_mm' => 'decimal:2',
        'is_active' => 'boolean',
        'is_default' => 'boolean',
        'template_data' => 'array',
        'usage_count' => 'integer',
    ];

    public function printJobs(): HasMany
    {
        return $this->hasMany(PrintJob::class, 'template_id');
    }

    public function printers(): HasMany
    {
        return $this->hasMany(OrganizationPrinter::class, 'default_template_id');
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeDefault($query)
    {
        return $query->where('is_default', true);
    }
}
