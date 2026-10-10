<?php

namespace App\Models\Organization;

use App\Models\System\AuditLog;
use App\Models\System\OnboardingProgress;
use App\Models\User\Role;
use App\Models\User\User;
use App\Models\User\UserActivityLog;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class Organization extends Model
{
    use HasFactory, SoftDeletes;

    protected static function newFactory()
    {
        return \Database\Factories\OrganizationFactory::new();
    }

    protected $fillable = [
        'name',
        'slug',
        'subdomain',
        'email',
        'phone',
        'description',
        'website',
        'address',
        'logo_url',
        'settings',
        'branding',
        'timezone',
        'currency',
        'language',
        'status',
        'trial_ends_at',
        'subscription_status',
        'subscription_plan',
        'subscription_expires_at',
        'billing_email',
        'is_active',
        'metadata',
        'sku_prefix',
        'sku_counter',
        'sku_prefix_locked',
        'sku_prefix_set_at',
        'sku_prefix_set_by',
        'owner_id',
    ];

    protected $casts = [
        'settings' => 'array',
        'branding' => 'array',
        'address' => 'array',
        'metadata' => 'array',
        'trial_ends_at' => 'datetime',
        'subscription_expires_at' => 'datetime',
        'suspended_at' => 'datetime',
        'is_active' => 'boolean',
        'sku_prefix_locked' => 'boolean',
        'sku_prefix_set_at' => 'datetime',
        'sku_counter' => 'integer',
    ];

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($organization) {
            if (empty($organization->slug)) {
                $organization->slug = Str::slug($organization->name);
            }

            // Set default settings
            if (empty($organization->settings)) {
                $organization->settings = $organization->getDefaultSettings();
            }

            // Set default status
            if (empty($organization->status)) {
                $organization->status = 'trial';
            }

            // Set trial period
            if (empty($organization->trial_ends_at) && $organization->status === 'trial') {
                $organization->trial_ends_at = now()->addDays(14);
            }
        });
    }

    /**
     * Relationships
     */
    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    public function activeUsers(): HasMany
    {
        return $this->hasMany(User::class)->where('is_active', true);
    }

    public function teams(): HasMany
    {
        return $this->hasMany(Team::class);
    }

    public function activeTeams(): HasMany
    {
        return $this->hasMany(Team::class)->where('is_active', true);
    }

    public function roles(): HasMany
    {
        return $this->hasMany(Role::class);
    }

    public function organizationSettings(): HasMany
    {
        return $this->hasMany(OrganizationSettings::class);
    }

    public function activityLogs(): HasMany
    {
        return $this->hasMany(UserActivityLog::class);
    }

    public function onboardingProgress(): HasMany
    {
        return $this->hasMany(OnboardingProgress::class);
    }

    public function teamInvitations(): HasMany
    {
        return $this->hasMany(TeamInvitation::class);
    }

    // Keep your existing methods
    public function invitations(): HasMany
    {
        return $this->hasMany(OrganizationInvitation::class);
    }

    public function auditLogs(): HasMany
    {
        return $this->hasMany(AuditLog::class);
    }

    public function printers(): HasMany
    {
        return $this->hasMany(\App\Models\Printing\OrganizationPrinter::class);
    }

    public function labelTemplates(): HasMany
    {
        return $this->hasMany(\App\Models\Printing\LabelTemplate::class);
    }

    /**
     * Scopes (Enhanced from your existing)
     */
    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    public function scopeBySubdomain($query, $subdomain)
    {
        return $query->where('subdomain', $subdomain);
    }

    public function scopeWithSubscription($query, $status = 'active')
    {
        return $query->where('subscription_status', $status);
    }

    /**
     * Enhanced Helper Methods
     */
    public function isActive(): bool
    {
        return $this->status === 'active' && $this->is_active;
    }

    public function isTrial(): bool
    {
        return $this->status === 'trial' && $this->trial_ends_at && $this->trial_ends_at->isFuture();
    }

    public function isSuspended(): bool
    {
        return $this->status === 'suspended';
    }

    public function hasSubscription(): bool
    {
        return ! empty($this->subscription_status) &&
               $this->subscription_status !== 'cancelled' &&
               ($this->subscription_expires_at === null ||
                $this->subscription_expires_at->isFuture());
    }

    public function isSubscriptionActive(): bool
    {
        return $this->subscription_status === 'active' && $this->hasSubscription();
    }

    /**
     * Enhanced Settings Management
     */
    public function getSetting(string $key, $default = null)
    {
        $setting = $this->organizationSettings()
            ->where('key', $key)
            ->first();

        return $setting ? $setting->value : ($this->settings[$key] ?? $default);
    }

    public function setSetting(string $key, $value, string $type = 'general'): void
    {
        // Try to use OrganizationSettings model if it exists
        if (class_exists('App\Models\OrganizationSettings')) {
            $this->organizationSettings()->updateOrCreate(
                ['key' => $key],
                ['value' => $value, 'type' => $type]
            );
        } else {
            // Fallback to settings array
            $settings = $this->settings ?? [];
            $settings[$key] = $value;
            $this->update(['settings' => $settings]);
        }
    }

    /**
     * Enhanced Default Settings (builds on your existing)
     */
    public function getDefaultSettings(): array
    {
        return array_merge([
            'features' => [
                'inventory_management' => true,
                'order_processing' => true,
                'multi_platform_sync' => true,
                'analytics' => true,
                'api_access' => false,
                'team_collaboration' => true,
                'user_management' => true,
                'role_management' => true,
            ],
            'limits' => [
                'max_users' => 10,
                'max_products' => 1000,
                'max_orders_per_month' => 500,
                'max_teams' => 5,
                'storage_limit_mb' => 1024,
            ],
            'notifications' => [
                'email_enabled' => true,
                'sms_enabled' => false,
                'slack_webhook' => null,
                'notification_frequency' => 'real_time',
            ],
            'preferences' => [
                'timezone' => 'UTC',
                'date_format' => 'M j, Y',
                'time_format' => '12',
                'currency' => 'USD',
                'language' => 'en',
            ],
            'security' => [
                'require_2fa' => false,
                'password_expiry_days' => 90,
                'session_timeout_minutes' => 480,
                'allowed_domains' => [],
            ],
        ], $this->settings ?? []);
    }

    /**
     * User and Team Analytics
     */
    public function getUserCount(): int
    {
        return $this->users()->count();
    }

    public function getActiveUserCount(): int
    {
        return $this->activeUsers()->count();
    }

    public function getTeamCount(): int
    {
        return $this->teams()->count();
    }

    public function getActiveTeamCount(): int
    {
        return $this->activeTeams()->count();
    }

    public function getOwner(): ?User
    {
        return $this->users()
            ->whereHas('roles', function ($q) {
                $q->where('slug', 'organization_owner');
            })
            ->first();
    }

    public function getAdmins()
    {
        return $this->users()
            ->whereHas('roles', function ($q) {
                $q->whereIn('slug', ['organization_owner', 'admin']);
            })
            ->get();
    }

    /**
     * Usage and Subscription Management
     */
    public function getUsageStats(): array
    {
        return [
            'users' => [
                'total' => $this->getUserCount(),
                'active' => $this->getActiveUserCount(),
                'limit' => $this->getUserLimit(),
            ],
            'teams' => [
                'total' => $this->getTeamCount(),
                'active' => $this->getActiveTeamCount(),
                'limit' => $this->getTeamLimit(),
            ],
            'storage' => [
                'used' => $this->getStorageUsed(),
                'limit' => $this->getStorageLimit(),
            ],
        ];
    }

    public function getUserLimit(): ?int
    {
        $limits = $this->settings['limits'] ?? [];

        return $limits['max_users'] ?? null;
    }

    public function getTeamLimit(): ?int
    {
        $limits = $this->settings['limits'] ?? [];

        return $limits['max_teams'] ?? null;
    }

    public function getStorageLimit(): ?int
    {
        $limits = $this->settings['limits'] ?? [];

        return $limits['storage_limit_mb'] ?? null;
    }

    public function getStorageUsed(): int
    {
        // This would need to be implemented based on your file storage
        return 0;
    }

    public function canAddUser(): bool
    {
        $limit = $this->getUserLimit();

        return $limit === null || $this->getUserCount() < $limit;
    }

    public function canAddTeam(): bool
    {
        $limit = $this->getTeamLimit();

        return $limit === null || $this->getTeamCount() < $limit;
    }

    /**
     * Activity and Analytics
     */
    public function getActivitySummary(int $days = 30): array
    {
        $startDate = now()->subDays($days);

        return [
            'new_users' => $this->users()->where('created_at', '>=', $startDate)->count(),
            'new_teams' => $this->teams()->where('created_at', '>=', $startDate)->count(),
            'total_activities' => $this->activityLogs()->where('created_at', '>=', $startDate)->count(),
            'active_users' => $this->users()
                ->where('last_login_at', '>=', $startDate)
                ->count(),
        ];
    }

    public function canCreateProducts(): bool
    {
        return ! empty($this->sku_prefix) && $this->sku_prefix_locked;
    }

    public function getNextInternalSku(): ?string
    {
        if (! $this->canCreateProducts()) {
            return null;
        }

        return $this->sku_prefix.'-'.str_pad($this->sku_counter + 1, 6, '0', STR_PAD_LEFT);
    }

    public function generateInternalSku(): string
    {
        $skuService = app(\App\Services\OrganizationSkuService::class);

        return $skuService->generateInternalSku($this);
    }
}
