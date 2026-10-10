<?php

namespace App\Models\Printing;

use App\Models\Catalog\Product;
use App\Models\User\User;
use App\Traits\BelongsToOrganization;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PrintJob extends Model
{
    use BelongsToOrganization, HasFactory;

    protected $fillable = [
        'organization_id',
        'printer_id',
        'template_id',
        'product_id',
        'created_by',
        'job_number',
        'job_type',
        'status',
        'priority',
        'quantity',
        'labels_printed',
        'label_data',
        'print_settings',
        'error_message',
        'started_at',
        'completed_at',
        'printed_at',
    ];

    protected $casts = [
        'label_data' => 'array',
        'print_settings' => 'array',
        'quantity' => 'integer',
        'labels_printed' => 'integer',
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
        'printed_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::creating(function (PrintJob $job) {
            if (empty($job->job_number)) {
                $year = now()->format('Y');
                $latest = static::where('job_number', 'like', "PJ-{$year}-%")
                    ->orderByRaw("CAST(SUBSTRING(job_number FROM 'PJ-\\d{4}-(\\d+)') AS INTEGER) DESC")
                    ->first();

                $nextNumber = $latest
                    ? ((int) substr($latest->job_number, strrpos($latest->job_number, '-') + 1)) + 1
                    : 1;

                $job->job_number = sprintf('PJ-%s-%03d', $year, $nextNumber);
            }
        });
    }

    public function printer(): BelongsTo
    {
        return $this->belongsTo(OrganizationPrinter::class, 'printer_id');
    }

    public function template(): BelongsTo
    {
        return $this->belongsTo(LabelTemplate::class, 'template_id');
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    public function scopeCompleted($query)
    {
        return $query->where('status', 'completed');
    }

    public function scopeFailed($query)
    {
        return $query->where('status', 'failed');
    }

    public function scopeToday($query)
    {
        return $query->whereDate('created_at', today());
    }
}
