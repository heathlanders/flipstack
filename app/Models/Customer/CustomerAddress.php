<?php

namespace App\Models\Customer;

use App\Traits\HasUuid;
use App\Traits\LogsAudit;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CustomerAddress extends Model
{
    use HasFactory, HasUuid, LogsAudit;

    protected ?string $auditCategory = 'customer';

    protected ?string $auditActionPrefix = 'customer.address';

    protected $fillable = [
        'customer_id',
        'type',
        'is_default',
        'name',
        'line1',
        'line2',
        'city',
        'state',
        'postal_code',
        'country',
    ];

    protected $casts = [
        'is_default' => 'boolean',
    ];

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function getFullAddressAttribute(): string
    {
        return collect([$this->line1, $this->line2, $this->city, $this->state, $this->postal_code, $this->country])
            ->filter()
            ->implode(', ');
    }
}
