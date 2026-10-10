<?php

namespace App\Models\Printing;

use App\Traits\BelongsToOrganization;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class OrganizationPrinter extends Model
{
    use BelongsToOrganization, HasFactory;

    protected $fillable = [
        'organization_id',
        'name',
        'printer_type',
        'connection_string',
        'driver',
        'is_active',
        'is_default',
        'default_template_id',
        'print_settings',
        'status',
        'last_used_at',
        'total_jobs',
        'successful_jobs',
        'failed_jobs',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'is_default' => 'boolean',
        'print_settings' => 'array',
        'last_used_at' => 'datetime',
        'total_jobs' => 'integer',
        'successful_jobs' => 'integer',
        'failed_jobs' => 'integer',
    ];

    public function defaultTemplate(): BelongsTo
    {
        return $this->belongsTo(LabelTemplate::class, 'default_template_id');
    }

    public function printJobs(): HasMany
    {
        return $this->hasMany(PrintJob::class, 'printer_id');
    }

    public function getSuccessRateAttribute(): float
    {
        if ($this->total_jobs === 0) {
            return 0;
        }

        return round(($this->successful_jobs / $this->total_jobs) * 100, 2);
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeOnline($query)
    {
        return $query->where('status', 'online');
    }
}
