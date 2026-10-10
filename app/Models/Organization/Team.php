<?php

namespace App\Models\Organization;

use App\Models\Catalog\Product;
use App\Models\Catalog\ProductIntakeQueue;
use App\Models\User\Role;
use App\Models\User\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Team extends Model
{
    use HasFactory, SoftDeletes;

    protected static function newFactory()
    {
        return \Database\Factories\TeamFactory::new();
    }

    protected $fillable = [
        'name',
        'slug',
        'description',
        'department',
        'organization_id',
        'user_id',
        'personal_team',
        'team_lead_id',
        'settings',
        'permissions',
        'is_active',
        'max_members',
        'created_by',
        'performance_metrics',
        'goals',
        'budget',
        'contact_info',
    ];

    protected $casts = [
        'settings' => 'array',
        'permissions' => 'array',
        'performance_metrics' => 'array',
        'goals' => 'array',
        'contact_info' => 'array',
        'is_active' => 'boolean',
        'budget' => 'decimal:2',
    ];

    /**
     * Relationships
     */
    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function teamLead(): BelongsTo
    {
        return $this->belongsTo(User::class, 'team_lead_id');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'team_user')
            ->withPivot('role')  // Match the actual table structure
            ->withTimestamps();
    }

    public function activeUsers(): BelongsToMany
    {
        return $this->users()->wherePivot('is_active', true);
    }

    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(Role::class, 'team_roles')
            ->withPivot('is_default')
            ->withTimestamps();
    }

    public function products(): HasMany
    {
        return $this->hasMany(Product::class);
    }

    public function intakeQueueItems(): HasMany
    {
        return $this->hasMany(ProductIntakeQueue::class);
    }

    public function teamInvitations(): HasMany
    {
        return $this->hasMany(TeamInvitation::class);
    }

    /**
     * Scopes
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeByDepartment($query, $department)
    {
        return $query->where('department', $department);
    }

    public function scopeForOrganization($query, $organizationId)
    {
        return $query->where('organization_id', $organizationId);
    }

    /**
     * Helper Methods
     */
    public function getMemberCount(): int
    {
        return $this->activeUsers()->count();
    }

    public function getProductivityScore(): float
    {
        // Calculate based on team performance metrics
        $metrics = $this->performance_metrics ?? [];

        if (empty($metrics)) {
            return 0.0;
        }

        // Example calculation - customize based on your business logic
        $scores = [];

        if (isset($metrics['products_processed'])) {
            $scores[] = min(100, ($metrics['products_processed'] / 10) * 10);
        }

        if (isset($metrics['completion_rate'])) {
            $scores[] = $metrics['completion_rate'];
        }

        if (isset($metrics['quality_score'])) {
            $scores[] = $metrics['quality_score'];
        }

        return empty($scores) ? 0.0 : round(array_sum($scores) / count($scores), 1);
    }

    public function updatePerformanceMetrics(array $metrics): void
    {
        $current = $this->performance_metrics ?? [];
        $this->update([
            'performance_metrics' => array_merge($current, $metrics),
        ]);
    }

    public function addUser(User $user, Role $role, array $permissions = []): void
    {
        $this->users()->attach($user->id, [
            'role_id' => $role->id,
            'joined_at' => now(),
            'is_active' => true,
            'permissions' => $permissions,
        ]);
    }

    public function removeUser(User $user): void
    {
        $this->users()->detach($user->id);
    }

    public function updateUserRole(User $user, Role $role): void
    {
        $this->users()->updateExistingPivot($user->id, [
            'role_id' => $role->id,
            'updated_at' => now(),
        ]);
    }

    public function deactivateUser(User $user): void
    {
        $this->users()->updateExistingPivot($user->id, [
            'is_active' => false,
            'updated_at' => now(),
        ]);
    }

    public function reactivateUser(User $user): void
    {
        $this->users()->updateExistingPivot($user->id, [
            'is_active' => true,
            'updated_at' => now(),
        ]);
    }

    public function hasUser(User $user): bool
    {
        return $this->users()->where('user_id', $user->id)->exists();
    }

    public function getUserRole(User $user): ?Role
    {
        $pivot = $this->users()->where('user_id', $user->id)->first()?->pivot;

        return $pivot ? Role::find($pivot->role_id) : null;
    }

    public function hasPermission(string $permission): bool
    {
        $permissions = $this->permissions ?? [];

        return in_array($permission, $permissions);
    }

    public function grantPermission(string $permission): void
    {
        $permissions = $this->permissions ?? [];
        if (! in_array($permission, $permissions)) {
            $permissions[] = $permission;
            $this->update(['permissions' => $permissions]);
        }
    }

    public function revokePermission(string $permission): void
    {
        $permissions = $this->permissions ?? [];
        $permissions = array_values(array_diff($permissions, [$permission]));
        $this->update(['permissions' => $permissions]);
    }

    public function getDepartmentColor(): string
    {
        return match ($this->department) {
            'sourcing' => 'blue',
            'listing' => 'emerald',
            'fulfillment' => 'orange',
            'management' => 'purple',
            'support' => 'pink',
            default => 'gray'
        };
    }

    public function purge(): void
    {
        $this->users()->detach();
        $this->delete();
    }

    public function isAtCapacity(): bool
    {
        return $this->max_members && $this->getMemberCount() >= $this->max_members;
    }

    public function getRemainingCapacity(): int
    {
        if (! $this->max_members) {
            return PHP_INT_MAX;
        }

        return max(0, $this->max_members - $this->getMemberCount());
    }

    /**
     * Generate slug from name
     */
    protected static function boot()
    {
        parent::boot();

        static::creating(function ($team) {
            if (! $team->slug) {
                $baseSlug = \Str::slug($team->name);
                $slug = $baseSlug;
                $counter = 1;

                while (static::where('organization_id', $team->organization_id)->where('slug', $slug)->exists()) {
                    $slug = $baseSlug.'-'.$counter;
                    $counter++;
                }

                $team->slug = $slug;
            }
        });
    }
}
