<?php

namespace App\Models\Notification;

use App\Models\Organization\Organization;
use App\Models\User\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DeviceToken extends Model
{
    protected $fillable = [
        'user_id',
        'organization_id',
        'fcm_token',
        'platform',
        'device_name',
        'app_version',
        'last_seen_at',
        'revoked_at',
    ];

    protected $casts = [
        'last_seen_at' => 'datetime',
        'revoked_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function scopeActive(Builder $q): Builder
    {
        return $q->whereNull('revoked_at');
    }

    public function revoke(): void
    {
        $this->update(['revoked_at' => now()]);
    }
}
