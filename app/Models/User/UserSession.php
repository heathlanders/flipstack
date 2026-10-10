<?php

namespace App\Models\User;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserSession extends Model
{
    protected $primaryKey = 'id';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'id',
        'user_id',
        'ip_address',
        'user_agent',
        'payload',
        'last_activity',
        'expires_at',
        'is_current',
    ];

    protected $casts = [
        'last_activity' => 'integer',
        'expires_at' => 'datetime',
        'is_current' => 'boolean',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    // Helper methods
    public function isExpired(): bool
    {
        return $this->expires_at && $this->expires_at->isPast();
    }

    public function markAsCurrent(): void
    {
        // Mark all other sessions as not current
        static::where('user_id', $this->user_id)->update(['is_current' => false]);

        // Mark this session as current
        $this->update(['is_current' => true]);
    }
}
