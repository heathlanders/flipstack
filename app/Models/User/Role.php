<?php

namespace App\Models\User;

use App\Models\Organization\Organization;
use App\Models\Organization\Team;
use App\Models\Organization\TeamInvitation;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Role extends Model
{
    use HasFactory, SoftDeletes;

    protected static function newFactory()
    {
        return \Database\Factories\RoleFactory::new();
    }

    protected $fillable = [
        'name',
        'slug',
        'description',
        'scope',
        'is_system',
        'permissions',
        'organization_id',  // NEW: For organization-specific roles
        'is_active',        // NEW: Role status
        'is_default',       // NEW: Default role flag
        'color',            // NEW: UI color
        'icon',             // NEW: UI icon
        'level',            // NEW: Hierarchy level
        'metadata',          // NEW: Additional data
    ];

    protected $casts = [
        'permissions' => 'array',
        'is_system' => 'boolean',
        'is_active' => 'boolean',
        'is_default' => 'boolean',
        'metadata' => 'array',
        'level' => 'integer',
    ];

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($role) {
            if (! $role->slug) {
                $role->slug = \Str::slug($role->name);
            }
        });

        static::updating(function ($role) {
            if ($role->isDirty('name') && ! $role->isDirty('slug')) {
                $role->slug = \Str::slug($role->name);
            }
        });
    }

    /**
     * Relationships
     */
    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'user_roles')
            ->withPivot(['organization_id', 'team_id', 'expires_at'])
            ->withTimestamps();
    }

    public function teams(): BelongsToMany
    {
        return $this->belongsToMany(Team::class, 'team_roles')
            ->withPivot('is_default')
            ->withTimestamps();
    }

    public function userRoles(): HasMany
    {
        return $this->hasMany(UserRole::class);
    }

    public function teamInvitations(): HasMany
    {
        return $this->hasMany(TeamInvitation::class);
    }

    /**
     * Scopes (Enhanced from existing)
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeDefault($query)
    {
        return $query->where('is_default', true);
    }

    public function scopeByScope($query, string $scope)
    {
        return $query->where('scope', $scope);
    }

    public function scopeSystem($query)
    {
        return $query->where('is_system', true);
    }

    public function scopeCustom($query)
    {
        return $query->where('is_system', false);
    }

    public function scopeForOrganization($query, $organizationId)
    {
        return $query->where(function ($q) use ($organizationId) {
            $q->whereNull('organization_id')  // System roles
                ->orWhere('organization_id', $organizationId);  // Organization roles
        });
    }

    public function scopeByLevel($query, $level)
    {
        return $query->where('level', $level);
    }

    /**
     * Enhanced Helper Methods
     */
    public function isSystem(): bool
    {
        return $this->is_system;
    }

    public function isCustom(): bool
    {
        return ! $this->is_system;
    }

    public function hasPermission(string $permission): bool
    {
        $permissions = $this->permissions ?? [];

        return in_array($permission, $permissions);
    }

    public function addPermission(string $permission): void
    {
        $permissions = $this->permissions ?? [];
        if (! in_array($permission, $permissions)) {
            $permissions[] = $permission;
            $this->update(['permissions' => $permissions]);
        }
    }

    public function removePermission(string $permission): void
    {
        $permissions = array_values(array_diff($this->permissions ?? [], [$permission]));
        $this->update(['permissions' => $permissions]);
    }

    public function syncPermissions(array $permissions): void
    {
        $this->update(['permissions' => array_unique($permissions)]);
    }

    public function grantPermission(string $permission): void
    {
        $this->addPermission($permission);
    }

    public function revokePermission(string $permission): void
    {
        $this->removePermission($permission);
    }

    /**
     * Enhanced Permission Management
     */
    public function getPermissionCategories(): array
    {
        $permissions = $this->permissions ?? [];
        $categories = [];

        foreach ($permissions as $permission) {
            $parts = explode('.', $permission);
            $category = $parts[0] ?? 'general';

            if (! isset($categories[$category])) {
                $categories[$category] = [];
            }
            $categories[$category][] = $permission;
        }

        return $categories;
    }

    /**
     * User and Analytics Methods
     */
    public function getUserCount(): int
    {
        return $this->users()->count();
    }

    public function getActiveUserCount(): int
    {
        return $this->users()
            ->where('users.is_active', true)
            ->count();
    }

    public function getOrganizationUserCount(?int $organizationId = null): int
    {
        $query = $this->users();

        if ($organizationId) {
            $query->where('users.organization_id', $organizationId);
        }

        return $query->count();
    }

    public function getTeamCount(): int
    {
        return $this->teams()->count();
    }

    /**
     * UI and Display Methods
     */
    public function getDisplayName(): string
    {
        return $this->name;
    }

    public function getColorClass(): string
    {
        return $this->color ?? match ($this->slug) {
            'organization_owner' => 'purple',
            'admin' => 'red',
            'buyer', 'sourcing_specialist' => 'blue',
            'lister', 'listing_specialist' => 'emerald',
            'fulfillment_manager', 'shipper' => 'orange',
            'support_agent' => 'pink',
            default => 'gray'
        };
    }

    public function getIconClass(): string
    {
        return $this->icon ?? match ($this->slug) {
            'organization_owner' => 'fas fa-crown',
            'admin' => 'fas fa-user-cog',
            'buyer', 'sourcing_specialist' => 'fas fa-shopping-cart',
            'lister', 'listing_specialist' => 'fas fa-list-alt',
            'fulfillment_manager', 'shipper' => 'fas fa-truck',
            'support_agent' => 'fas fa-headset',
            default => 'fas fa-user'
        };
    }

    public function getLevel(): int
    {
        return $this->level ?? match ($this->slug) {
            'organization_owner' => 100,
            'admin' => 90,
            'manager' => 80,
            'team_lead' => 70,
            'senior_specialist' => 60,
            'specialist' => 50,
            'junior' => 40,
            'intern' => 30,
            default => 50
        };
    }

    public function isHigherThan(Role $otherRole): bool
    {
        return $this->getLevel() > $otherRole->getLevel();
    }

    public function canManageRole(Role $otherRole): bool
    {
        // System roles can manage custom roles
        if ($this->isSystem() && $otherRole->isCustom()) {
            return true;
        }

        // Higher level roles can manage lower level roles
        return $this->isHigherThan($otherRole);
    }

    /**
     * Dashboard and Feature Methods
     */
    public function getDashboardType(): string
    {
        return match ($this->slug) {
            'organization_owner', 'admin' => 'owner',
            'buyer', 'sourcing_specialist' => 'buyer',
            'lister', 'listing_specialist' => 'lister',
            'fulfillment_manager', 'shipper' => 'fulfillment',
            'support_agent' => 'support',
            default => 'basic'
        };
    }

    /**
     * Permission Templates and Defaults
     */
    public function getDefaultPermissions(): array
    {
        return match ($this->slug) {
            'organization_owner' => $this->getAllPermissions(),
            'admin' => $this->getAdminPermissions(),
            'buyer', 'sourcing_specialist' => $this->getBuyerPermissions(),
            'lister', 'listing_specialist' => $this->getListerPermissions(),
            'fulfillment_manager', 'shipper' => $this->getFulfillmentPermissions(),
            'support_agent' => $this->getSupportPermissions(),
            default => $this->getBasicPermissions()
        };
    }

    private function getAllPermissions(): array
    {
        return [
            // User Management
            'users.view', 'users.create', 'users.edit', 'users.delete',
            'users.manage_status', 'users.reset_password', 'users.view_activity',
            'users.impersonate', 'users.bulk_actions',

            // Team Management
            'teams.view', 'teams.create', 'teams.edit', 'teams.delete',
            'teams.manage_members', 'teams.invite',

            // Role Management
            'roles.view', 'roles.create', 'roles.edit', 'roles.delete',
            'roles.manage_permissions', 'roles.assign_users',

            // Organization
            'organization.view', 'organization.edit', 'organization.manage',

            // Analytics & Reports
            'analytics.view', 'analytics.export', 'reports.view',
            'reports.create', 'reports.export',

            // Settings
            'settings.view', 'settings.edit', 'integrations.view',
            'integrations.manage', 'billing.view', 'billing.manage',

            // AI Prompts Management
            'ai_prompts.view', 'ai_prompts.create', 'ai_prompts.edit', 'ai_prompts.delete',

            // Administrative
            'admin.access', 'audit_log.view', 'audit_log.export',
        ];
    }

    private function getAdminPermissions(): array
    {
        return [
            'users.view', 'users.create', 'users.edit', 'users.manage_status',
            'teams.view', 'teams.create', 'teams.edit', 'teams.manage_members',
            'teams.invite', 'roles.view', 'roles.assign_users',
            'organization.view', 'analytics.view', 'reports.view',
            'settings.view', 'settings.edit',
        ];
    }

    private function getBuyerPermissions(): array
    {
        return [
            'inventory.view', 'inventory.create', 'inventory.edit',
            'products.view', 'products.create', 'products.edit',
            'analytics.view', 'reports.view',
        ];
    }

    private function getListerPermissions(): array
    {
        return [
            'inventory.view', 'products.view', 'products.edit',
            'intake.view', 'intake.process', 'analytics.view',
        ];
    }

    private function getFulfillmentPermissions(): array
    {
        return [
            'inventory.view', 'products.view', 'orders.view',
            'orders.process', 'analytics.view',
        ];
    }

    private function getSupportPermissions(): array
    {
        return [
            'users.view', 'teams.view', 'inventory.view',
            'products.view', 'analytics.view',
        ];
    }

    private function getBasicPermissions(): array
    {
        return [
            'inventory.view', 'products.view',
        ];
    }

    public function applyDefaultPermissions(): void
    {
        $this->syncPermissions($this->getDefaultPermissions());
    }

    /**
     * Static Helper Methods
     */
    public static function createForOrganization(array $attributes, ?int $organizationId = null): self
    {
        $attributes['organization_id'] = $organizationId;
        $attributes['slug'] = \Str::slug($attributes['name']);

        $role = static::create($attributes);

        // Apply default permissions if not provided
        if (! isset($attributes['permissions']) || empty($attributes['permissions'])) {
            $role->applyDefaultPermissions();
        }

        return $role;
    }

    public static function getSystemRoles(): \Illuminate\Database\Eloquent\Collection
    {
        return static::whereNull('organization_id')
            ->orWhere('is_system', true)
            ->get();
    }

    public static function getOrganizationRoles(int $organizationId): \Illuminate\Database\Eloquent\Collection
    {
        return static::where('organization_id', $organizationId)->get();
    }

    public static function findBySlug(string $slug): ?self
    {
        return static::where('slug', $slug)->first();
    }

    public static function getDefaultRole(?int $organizationId = null): ?self
    {
        $query = static::where('is_default', true);

        if ($organizationId) {
            $query->where(function ($q) use ($organizationId) {
                $q->where('organization_id', $organizationId)
                    ->orWhereNull('organization_id');
            });
        } else {
            $query->whereNull('organization_id');
        }

        return $query->first();
    }
}
