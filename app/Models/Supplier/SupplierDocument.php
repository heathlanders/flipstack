<?php

namespace App\Models\Supplier;

use App\Models\Inventory\Supplier;
use App\Models\Organization\Organization;
use App\Models\User\User;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class SupplierDocument extends Model
{
    use HasFactory, HasUuids, SoftDeletes;

    protected $fillable = [
        'supplier_id',
        'organization_id',
        'name',
        'document_type',
        'description',
        'file_path',
        'file_name',
        'mime_type',
        'file_size',
        'original_name',
        'category',
        'valid_from',
        'valid_until',
        'is_expired',
        'requires_renewal',
        'renewal_notice_days',
        'verification_status',
        'verified_by',
        'verified_at',
        'verification_notes',
        'supplier_visible',
        'is_confidential',
        'uploaded_by',
    ];

    protected function casts(): array
    {
        return [
            'valid_from' => 'date',
            'valid_until' => 'date',
            'verified_at' => 'datetime',
            'is_expired' => 'boolean',
            'requires_renewal' => 'boolean',
            'supplier_visible' => 'boolean',
            'is_confidential' => 'boolean',
            'file_size' => 'integer',
            'renewal_notice_days' => 'integer',
        ];
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function verifiedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'verified_by');
    }

    public function uploadedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }
}
