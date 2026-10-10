<?php

namespace App\Models\Integration;

use App\Models\Organization\Organization;
use App\Models\Organization\Team;
use App\Models\User\User;
use App\Traits\LogsAudit;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Crypt;

class OAuthToken extends Model
{
    use LogsAudit;

    protected ?string $auditCategory = 'security';

    protected ?string $auditSeverity = 'warning';

    protected ?string $auditActionPrefix = 'credential.oauth';

    protected array $auditExclude = ['access_token', 'refresh_token', 'last_refreshed_at'];

    protected $table = 'oauth_tokens';

    protected $fillable = [
        'team_id',
        'organization_id',
        'user_id',
        'integration_credential_id',
        'platform',
        'environment',
        'access_token',
        'refresh_token',
        'expires_at',
        'scopes',
        'metadata',
        'is_active',
        'last_refreshed_at',
        'last_used_at',
    ];

    protected $casts = [
        'scopes' => 'array',
        'metadata' => 'array',
        'is_active' => 'boolean',
        'expires_at' => 'datetime',
        'last_refreshed_at' => 'datetime',
        'last_used_at' => 'datetime',
    ];

    protected $hidden = [
        'access_token',
        'refresh_token',
    ];

    /**
     * Boot the model
     */
    protected static function boot()
    {
        parent::boot();

        // Encrypt tokens before saving
        static::saving(function ($token) {
            if ($token->isDirty('access_token')) {
                $token->access_token = Crypt::encryptString($token->access_token);
            }
            if ($token->isDirty('refresh_token') && ! empty($token->refresh_token)) {
                $token->refresh_token = Crypt::encryptString($token->refresh_token);
            }
        });
    }

    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function integrationCredential(): BelongsTo
    {
        return $this->belongsTo(IntegrationCredential::class);
    }

    /**
     * Get decrypted access token
     */
    public function getDecryptedAccessTokenAttribute(): string
    {
        try {
            return Crypt::decryptString($this->access_token);
        } catch (\Exception $e) {
            return $this->access_token;
        }
    }

    /**
     * Get decrypted refresh token
     */
    public function getDecryptedRefreshTokenAttribute(): ?string
    {
        if (empty($this->refresh_token)) {
            return null;
        }

        try {
            return Crypt::decryptString($this->refresh_token);
        } catch (\Exception $e) {
            return $this->refresh_token;
        }
    }

    /**
     * Check if token is expired
     */
    public function isExpired(): bool
    {
        if (! $this->expires_at) {
            return false;
        }

        return Carbon::now()->greaterThanOrEqualTo($this->expires_at);
    }

    /**
     * Check if token is expiring soon (within 30 minutes)
     */
    public function isExpiringSoon(): bool
    {
        if (! $this->expires_at) {
            return false;
        }

        return Carbon::now()->addMinutes(30)->greaterThanOrEqualTo($this->expires_at);
    }

    /**
     * Check if token needs refresh
     */
    public function needsRefresh(): bool
    {
        return $this->is_active &&
               ! empty($this->refresh_token) &&
               $this->isExpiringSoon();
    }

    /**
     * Mark token as used
     */
    public function markAsUsed(): void
    {
        $this->update(['last_used_at' => now()]);
    }

    /**
     * Deactivate this token
     */
    public function deactivate(): void
    {
        $this->update(['is_active' => false]);
    }

    /**
     * Check if this is a team-level token
     */
    public function isTeamLevel(): bool
    {
        return $this->user_id === null;
    }

    /**
     * Check if this is a user-level token
     */
    public function isUserLevel(): bool
    {
        return $this->user_id !== null;
    }

    /**
     * Scope for active tokens
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope for expired tokens
     */
    public function scopeExpired($query)
    {
        return $query->where('expires_at', '<=', now());
    }

    /**
     * Scope for tokens needing refresh
     */
    public function scopeNeedsRefresh($query)
    {
        return $query->active()
            ->whereNotNull('refresh_token')
            ->where('expires_at', '<=', now()->addMinutes(30));
    }

    /**
     * Scope for team-level tokens
     */
    public function scopeTeamLevel($query)
    {
        return $query->whereNull('user_id');
    }

    /**
     * Scope for user-level tokens
     */
    public function scopeUserLevel($query)
    {
        return $query->whereNotNull('user_id');
    }

    /**
     * Scope for specific platform
     */
    public function scopePlatform($query, string $platform)
    {
        return $query->where('platform', $platform);
    }

    /**
     * Scope for specific environment
     */
    public function scopeEnvironment($query, string $environment)
    {
        return $query->where('environment', $environment);
    }

    /**
     * Get the active token for an organization/user
     */
    public static function getActiveToken(int $organizationId, string $platform, string $environment, ?int $userId = null): ?self
    {
        $query = static::where('organization_id', $organizationId)
            ->where('platform', $platform)
            ->where('environment', $environment)
            ->active();

        // First, try to get user-specific token if user_id provided
        if ($userId !== null) {
            $userToken = (clone $query)->where('user_id', $userId)->first();
            if ($userToken && ! $userToken->isExpired()) {
                return $userToken;
            }
        }

        // Fall back to org-level token
        $orgToken = $query->whereNull('user_id')->first();
        if ($orgToken && ! $orgToken->isExpired()) {
            return $orgToken;
        }

        return null;
    }
}
