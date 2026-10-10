<?php

namespace App\Models\Organization;

use App\Models\User\Role;
use App\Models\User\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class OrganizationInvitation extends Model
{
    use HasFactory;

    protected $fillable = [
        'organization_id',
        'invited_by_user_id',
        'email',
        'roles',
        'token',
        'expires_at',
        'accepted_at',
    ];

    protected $casts = [
        'roles' => 'array',
        'expires_at' => 'datetime',
        'accepted_at' => 'datetime',
    ];

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($invitation) {
            if (empty($invitation->token)) {
                $invitation->token = Str::random(40);
            }
            if (empty($invitation->expires_at)) {
                $invitation->expires_at = now()->addDays(7);
            }
        });
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function invitedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'invited_by_user_id');
    }

    // Helper methods
    public function isExpired(): bool
    {
        return $this->expires_at->isPast();
    }

    public function isAccepted(): bool
    {
        return ! is_null($this->accepted_at);
    }

    public function isForOrganizationOwner(): bool
    {
        if (empty($this->roles)) {
            return false;
        }

        return Role::whereIn('id', $this->roles)
            ->where('slug', 'organization_owner')
            ->exists();
    }

    public function accept(): void
    {
        $this->update(['accepted_at' => now()]);
    }
}
