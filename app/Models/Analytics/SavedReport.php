<?php

namespace App\Models\Analytics;

use App\Models\User\User;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SavedReport extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'organization_id',
        'owner_id',
        'name',
        'view',
        'filters',
        'is_shared',
    ];

    protected $casts = [
        'filters' => 'array',
        'is_shared' => 'boolean',
    ];

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }
}
