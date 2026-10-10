<?php

namespace App\Models\Organization;

use App\Models\User\Role;
use App\Models\User\User;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;
use Laravel\Jetstream\Jetstream;
use Laravel\Jetstream\TeamInvitation as JetstreamTeamInvitation;

class TeamInvitation extends JetstreamTeamInvitation
{
    /**
     * Enhanced fillable attributes
     */
    protected $fillable = [
        'email',
        'role',
        // Enhanced fields
        'organization_id',
        'invited_by',
        'token',
        'permissions',
        'expires_at',
        'accepted_at',
        'declined_at',
        'cancelled_at',
        'message',
        'metadata',
    ];

    protected $casts = [
        'permissions' => 'array',
        'metadata' => 'array',
        'expires_at' => 'datetime',
        'accepted_at' => 'datetime',
        'declined_at' => 'datetime',
        'cancelled_at' => 'datetime',
    ];

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($invitation) {
            if (! $invitation->token) {
                $invitation->token = Str::random(64);
            }

            if (! $invitation->expires_at) {
                $invitation->expires_at = now()->addDays(7);
            }
        });
    }

    /**
     * Jetstream relationship (keep existing)
     */
    public function team(): BelongsTo
    {
        return $this->belongsTo(Jetstream::teamModel());
    }

    /**
     * Enhanced relationships
     */
    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function inviter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'invited_by');
    }

    public function roleModel(): BelongsTo
    {
        return $this->belongsTo(Role::class, 'role_id');
    }

    /**
     * Enhanced scopes
     */
    public function scopePending($query)
    {
        return $query->whereNull('accepted_at')
            ->whereNull('declined_at')
            ->whereNull('cancelled_at')
            ->where('expires_at', '>', now());
    }

    public function scopeExpired($query)
    {
        return $query->where('expires_at', '<=', now())
            ->whereNull('accepted_at');
    }

    /**
     * Enhanced helper methods
     */
    public function isPending(): bool
    {
        return $this->accepted_at === null &&
               $this->declined_at === null &&
               $this->cancelled_at === null &&
               ! $this->isExpired();
    }

    public function isExpired(): bool
    {
        return $this->expires_at && $this->expires_at->isPast();
    }

    public function getStatus(): string
    {
        if ($this->accepted_at) {
            return 'accepted';
        }
        if ($this->declined_at) {
            return 'declined';
        }
        if ($this->cancelled_at) {
            return 'cancelled';
        }
        if ($this->isExpired()) {
            return 'expired';
        }

        return 'pending';
    }

    public function accept(): bool
    {
        if (! $this->isPending()) {
            return false;
        }

        $this->update(['accepted_at' => now()]);

        // Add user to team if they exist
        $user = User::where('email', $this->email)->first();
        if ($user && $this->team) {
            // Use Jetstream's team member addition
            $this->team->users()->attach($user, ['role' => $this->role]);
        }

        return true;
    }

    public function decline(): bool
    {
        if (! $this->isPending()) {
            return false;
        }

        $this->update(['declined_at' => now()]);

        return true;
    }

    public function getAcceptUrl(): string
    {
        return route('team-invitations.accept', ['token' => $this->token]);
    }
}
