<?php

namespace App\Models\User;

use App\Enums\Supplier\SupplierStatus;
use App\Models\Catalog\Product;
use App\Models\Catalog\ProductIntakeQueue;
use App\Models\Inventory\Supplier;
use App\Models\Organization\Organization;
use App\Models\Organization\Team;
use App\Models\Organization\TeamInvitation;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Fortify\TwoFactorAuthenticatable;
use Laravel\Jetstream\HasProfilePhoto;
use Laravel\Jetstream\HasTeams;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable implements MustVerifyEmail
{
    use HasApiTokens;
    use HasFactory;
    use HasProfilePhoto;
    use HasTeams;
    use Notifiable;
    use SoftDeletes;
    use TwoFactorAuthenticatable;

    protected static function newFactory()
    {
        return \Database\Factories\UserFactory::new();
    }

    protected $fillable = [
        'name',
        'email',
        'password',
        'password_change_required',
        'organization_id',
        'current_team_id',
        'employee_id',
        'phone',
        'department',
        'job_title',
        'hire_date',
        'is_active',
        'last_login_at',
        'password_expires_at',
        'preferences',
        'permissions',
        'bio',
        'timezone',
        'language',
        'notification_settings',
        'metadata',
        'is_supplier',
        'supplier_id',
        'supplier_status',
    ];

    protected $hidden = [
        'password',
        'remember_token',
        'two_factor_recovery_codes',
        'two_factor_secret',
    ];

    protected $casts = [
        'email_verified_at' => 'datetime',
        'hire_date' => 'date',
        'last_login_at' => 'datetime',
        'is_active' => 'boolean',
        'password_change_required' => 'boolean',
        'preferences' => 'array',
        'permissions' => 'array',
        'notification_settings' => 'array',
        'is_supplier' => 'boolean',
        'supplier_status' => SupplierStatus::class,
    ];

    protected $appends = [
        'profile_photo_url',
    ];

    /**
     * Enhanced Relationships
     */
    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    public function managedTeams(): HasMany
    {
        return $this->hasMany(Team::class, 'team_lead_id');
    }

    public function createdTeams(): HasMany
    {
        return $this->hasMany(Team::class, 'created_by');
    }

    public function userTeams(): BelongsToMany
    {
        return $this->belongsToMany(Team::class, 'user_teams')
            ->withPivot('role_id', 'joined_at', 'is_active', 'permissions')
            ->withTimestamps();
    }

    public function activeTeams(): BelongsToMany
    {
        return $this->userTeams()->wherePivot('is_active', true);
    }

    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(Role::class, 'user_roles')
            ->withPivot('organization_id', 'team_id', 'expires_at')
            ->withTimestamps();
    }

    public function assignRole(Role $role, ?int $organizationId = null, ?int $teamId = null): void
    {
        if (! $this->roles()->wherePivot('role_id', $role->id)->wherePivot('organization_id', $organizationId)->wherePivot('team_id', $teamId)->exists()) {
            $this->roles()->attach($role->id, [
                'organization_id' => $organizationId,
                'team_id' => $teamId,
            ]);

            $this->dispatchRoleChange($role, 'assigned', $organizationId);
        }
    }

    public function removeRole(Role $role, ?int $organizationId = null): void
    {
        $detached = $this->roles()->wherePivot('organization_id', $organizationId)->detach($role->id);
        if ($detached > 0) {
            $this->dispatchRoleChange($role, 'removed', $organizationId);
        }
    }

    protected function dispatchRoleChange(Role $role, string $action, ?int $organizationId): void
    {
        $orgId = $organizationId ?? $this->organization_id;
        if (! $orgId) {
            return;
        }

        $actor = auth()->user();
        $actorName = $actor && $actor->id !== $this->id ? $actor->name : null;

        app(\App\Services\NotificationService::class)->dispatchEvent(
            eventKey: 'security.role.changed',
            organizationId: (int) $orgId,
            args: [
                (string) ($this->name ?? $this->email),
                $action,
                (string) $role->name,
                $actorName,
            ],
            relatedId: $this->id.':'.$role->id.':'.$action,
        );
    }

    public function activityLogs(): HasMany
    {
        return $this->hasMany(UserActivityLog::class);
    }

    public function deviceTokens(): HasMany
    {
        return $this->hasMany(\App\Models\Notification\DeviceToken::class);
    }

    public function sentTeamInvitations(): HasMany
    {
        return $this->hasMany(TeamInvitation::class, 'invited_by');
    }

    public function createdProducts(): HasMany
    {
        return $this->hasMany(Product::class, 'created_by');
    }

    public function capturedIntakeItems(): HasMany
    {
        return $this->hasMany(ProductIntakeQueue::class, 'captured_by');
    }

    public function processedIntakeItems(): HasMany
    {
        return $this->hasMany(ProductIntakeQueue::class, 'processed_by');
    }

    /**
     * Team Management Methods
     */
    public function joinTeam(Team $team, Role $role, array $permissions = []): bool
    {
        if ($this->isMemberOfTeam($team)) {
            return false;
        }

        if ($team->isAtCapacity()) {
            return false;
        }

        $this->userTeams()->attach($team->id, [
            'role_id' => $role->id,
            'joined_at' => now(),
            'is_active' => true,
            'permissions' => $permissions,
        ]);

        $this->logActivity('team_joined', "Joined team: {$team->name}", [
            'team_id' => $team->id,
            'role_id' => $role->id,
        ]);

        return true;
    }

    public function leaveTeam(Team $team): bool
    {
        if (! $this->isMemberOfTeam($team)) {
            return false;
        }

        $this->userTeams()->detach($team->id);

        $this->logActivity('team_left', "Left team: {$team->name}", [
            'team_id' => $team->id,
        ]);

        return true;
    }

    public function isMemberOfTeam(Team $team): bool
    {
        return $this->userTeams()->where('team_id', $team->id)->exists();
    }

    public function isActiveInTeam(Team $team): bool
    {
        return $this->activeTeams()->where('team_id', $team->id)->exists();
    }

    public function getTeamRole(Team $team): ?Role
    {
        $pivot = $this->userTeams()->where('team_id', $team->id)->first()?->pivot;

        return $pivot ? Role::find($pivot->role_id) : null;
    }

    public function changeTeamRole(Team $team, Role $newRole): bool
    {
        if (! $this->isMemberOfTeam($team)) {
            return false;
        }

        $oldRole = $this->getTeamRole($team);

        $this->userTeams()->updateExistingPivot($team->id, [
            'role_id' => $newRole->id,
            'updated_at' => now(),
        ]);

        $this->logActivity('role_changed', "Role changed in team: {$team->name}", [
            'team_id' => $team->id,
            'old_role_id' => $oldRole?->id,
            'new_role_id' => $newRole->id,
        ]);

        return true;
    }

    public function deactivateFromTeam(Team $team): bool
    {
        if (! $this->isMemberOfTeam($team)) {
            return false;
        }

        $this->userTeams()->updateExistingPivot($team->id, [
            'is_active' => false,
            'updated_at' => now(),
        ]);

        return true;
    }

    public function reactivateInTeam(Team $team): bool
    {
        if (! $this->isMemberOfTeam($team)) {
            return false;
        }

        $this->userTeams()->updateExistingPivot($team->id, [
            'is_active' => true,
            'updated_at' => now(),
        ]);

        return true;
    }

    /**
     * Enhanced Role & Permission Methods (updating existing methods)
     */
    public function getPrimaryRole(): ?string
    {
        // Try exact match first (team-specific role)
        $role = $this->roles()
            ->wherePivot('organization_id', $this->organization_id)
            ->wherePivot('team_id', $this->current_team_id)
            ->first();

        // Fallback: organization-wide role (team_id = null)
        if (! $role) {
            $role = $this->roles()
                ->wherePivot('organization_id', $this->organization_id)
                ->wherePivot('team_id', null)
                ->first();
        }

        // Last resort: any role for organization
        if (! $role) {
            $role = $this->roles()
                ->wherePivot('organization_id', $this->organization_id)
                ->first();
        }

        return $role?->slug;
    }

    public function getDashboardType(): string
    {
        $role = $this->getPrimaryRole();

        return match ($role) {
            'organization_owner', 'admin', 'manager' => 'owner',
            'buyer', 'sourcing_specialist' => 'buyer',
            'lister', 'listing_specialist' => 'lister',
            'fulfillment_manager', 'shipper' => 'fulfillment',
            'support_agent' => 'support',
            default => 'basic'
        };
    }

    public function getEffectivePermissions(): array
    {
        $permissions = [];

        // Get permissions from roles
        $roles = $this->roles()
            ->wherePivot('organization_id', $this->organization_id)
            ->get();

        foreach ($roles as $role) {
            if ($role->permissions) {
                $permissions = array_merge($permissions, $role->permissions);
            }
        }

        // Add user-specific permissions
        if ($this->permissions) {
            $permissions = array_merge($permissions, $this->permissions);
        }

        // Add team-specific permissions
        $teamPermissions = $this->activeTeams()
            ->get()
            ->pluck('pivot.permissions')
            ->flatten()
            ->filter()
            ->toArray();

        $permissions = array_merge($permissions, $teamPermissions);

        return array_unique($permissions);
    }

    public function hasPermission(string $permission, ?int $organizationId = null): bool
    {
        // Organization owners and admins have all permissions
        $primaryRole = $this->getPrimaryRole();
        if (in_array($primaryRole, ['organization_owner', 'admin'])) {
            return true;
        }

        $effectivePermissions = $this->getEffectivePermissions();

        return in_array($permission, $effectivePermissions);
    }

    /**
     * Whether the user can see the supplier section at all (page + sidebar link).
     * Manager-tier and up. Lister/Buyer/Fulfillment/Shipper/Employee/Support are
     * intentionally excluded — supplier data is operational management info, not
     * a fulfillment-floor concern.
     */
    public function canViewSuppliers(): bool
    {
        return in_array($this->getPrimaryRole(), [
            'super_admin', 'organization_owner', 'admin', 'manager',
        ], true);
    }

    /**
     * Whether the user can edit Admin Configuration on a supplier (Status, Tier,
     * Commission Structure, Settlement Frequency). Owner-tier only — payout and
     * tier policy are not delegated below ownership.
     */
    public function canManageSupplierAdminConfig(): bool
    {
        return in_array($this->getPrimaryRole(), [
            'super_admin', 'organization_owner',
        ], true);
    }

    public function getQuickActions(): array
    {
        $dashboardType = $this->getDashboardType();

        return match ($dashboardType) {
            'owner' => [
                ['label' => 'View Reports', 'route' => 'inventory.reports.analytics', 'icon' => 'chart-bar'],
                ['label' => 'Manage Teams', 'route' => 'teams.index', 'icon' => 'users'],
                ['label' => 'Organization Settings', 'route' => 'settings.organization', 'icon' => 'cog'],
            ],
            'buyer' => [
                ['label' => 'ROI Analysis', 'route' => 'inventory.reports.analytics', 'icon' => 'calculator'],
                ['label' => 'Add Products', 'route' => 'inventory.products.create', 'icon' => 'plus'],
                ['label' => 'Suppliers', 'route' => 'admin.suppliers.index', 'icon' => 'truck'],
            ],
            'lister' => [
                ['label' => 'Intake Queue', 'route' => 'intake-queue.index', 'icon' => 'inbox'],
                ['label' => 'Create Listing', 'route' => 'inventory.products.create', 'icon' => 'plus'],
                ['label' => 'Templates', 'route' => 'settings.templates', 'icon' => 'copy'],
            ],
            'fulfillment' => [
                ['label' => 'Orders', 'route' => 'orders.index', 'icon' => 'shopping-cart'],
                ['label' => 'Shipping', 'route' => 'shipping.index', 'icon' => 'truck'],
                ['label' => 'Returns', 'route' => 'returns.index', 'icon' => 'undo'],
            ],
            'support' => [
                ['label' => 'Messages', 'route' => 'messages.index', 'icon' => 'envelope'],
                ['label' => 'Orders', 'route' => 'orders.index', 'icon' => 'shopping-cart'],
                ['label' => 'Returns', 'route' => 'returns.index', 'icon' => 'undo'],
            ],
            default => [
                ['label' => 'Dashboard', 'route' => 'dashboard', 'icon' => 'tachometer-alt'],
                ['label' => 'Profile', 'route' => 'profile.show', 'icon' => 'user'],
            ]
        };
    }

    /**
     * Activity Logging
     */
    public function logActivity(string $action, string $description, array $metadata = []): void
    {
        $this->activityLogs()->create([
            'organization_id' => $this->organization_id,
            'team_id' => $this->current_team_id,
            'action' => $action,
            'description' => $description,
            'metadata' => $metadata,
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
        ]);
    }

    /**
     * Team Leadership Methods
     */
    public function canManageTeam(Team $team): bool
    {
        // Team lead or organization owner
        return $team->team_lead_id === $this->id ||
               $this->hasPermission('teams.manage') ||
               $this->getPrimaryRole() === 'organization_owner';
    }

    public function canInviteToTeam(Team $team): bool
    {
        return $this->canManageTeam($team) ||
               $this->hasPermission('teams.invite') ||
               $this->isMemberOfTeam($team);
    }

    public function getTeamStats(): array
    {
        return [
            'teams_count' => $this->activeTeams()->count(),
            'teams_led' => $this->managedTeams()->count(),
            'teams_created' => $this->createdTeams()->count(),
            'invitations_sent' => $this->sentTeamInvitations()->count(),
        ];
    }

    /**
     * Scopes
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeInOrganization($query, $organizationId)
    {
        return $query->where('organization_id', $organizationId);
    }

    public function scopeInTeam($query, $teamId)
    {
        return $query->whereHas('userTeams', function ($q) use ($teamId) {
            $q->where('team_id', $teamId)->where('is_active', true);
        });
    }

    public function scopeWithRole($query, $roleSlug)
    {
        return $query->whereHas('roles', function ($q) use ($roleSlug) {
            $q->where('slug', $roleSlug);
        });
    }

    /**
     * Route notifications for SMS channel.
     */
    public function routeNotificationForSms($notification)
    {
        return $this->phone; // Uses your existing 'phone' column
    }

    /**
     * Route notifications for Slack channel (optional).
     */
    public function routeNotificationForSlack($notification)
    {
        // If you add slack_webhook_url column later, uncomment below
        // return $this->slack_webhook_url ?? config('services.slack.webhook_url');

        return config('services.slack.webhook_url');
    }

    public function hasRole($role, ?int $organizationId = null, ?int $teamId = null): bool
    {
        // Handle arrays - if user passes ['admin', 'manager'], check if user has ANY of those
        if (is_array($role)) {
            foreach ($role as $singleRole) {
                if ($this->hasRole($singleRole, $organizationId, $teamId)) {
                    return true;
                }
            }

            return false;
        }

        // Handle single string role
        $organizationId = $organizationId ?? $this->organization_id;
        $teamId = $teamId ?? $this->current_team_id;

        // Check for team-specific role first
        if ($teamId) {
            $hasRole = $this->roles()
                ->where('slug', $role)
                ->wherePivot('organization_id', $organizationId)
                ->wherePivot('team_id', $teamId)
                ->exists();

            if ($hasRole) {
                return true;
            }
        }

        // Check for organization-wide role
        $hasRole = $this->roles()
            ->where('slug', $role)
            ->wherePivot('organization_id', $organizationId)
            ->wherePivot('team_id', null)
            ->exists();

        if ($hasRole) {
            return true;
        }

        // Check primary role as fallback
        return $this->getPrimaryRole() === $role;
    }

    /**
     * Check if the user's password has expired or needs to be changed.
     */
    public function isPasswordExpired(): bool
    {
        return $this->password_change_required ?? false;
    }

    /**
     * Check if the user is a super admin (system-level admin).
     */
    public function isSuperAdmin(): bool
    {
        return $this->hasRole('super_admin');
    }
}
