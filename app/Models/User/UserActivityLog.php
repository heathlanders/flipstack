<?php

namespace App\Models\User;

use App\Models\Organization\Organization;
use App\Models\Organization\Team;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserActivityLog extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'organization_id',
        'team_id',
        'action',
        'description',
        'metadata',
        'ip_address',
        'user_agent',
        'session_id',
        'model_type',
        'model_id',
        'severity',
        'category',
    ];

    protected $casts = [
        'metadata' => 'array',
    ];

    /**
     * Relationships
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    public function model()
    {
        return $this->morphTo();
    }

    /**
     * Scopes
     */
    public function scopeForUser($query, $userId)
    {
        return $query->where('user_id', $userId);
    }

    public function scopeForOrganization($query, $organizationId)
    {
        return $query->where('organization_id', $organizationId);
    }

    public function scopeForTeam($query, $teamId)
    {
        return $query->where('team_id', $teamId);
    }

    public function scopeByAction($query, $action)
    {
        return $query->where('action', $action);
    }

    public function scopeByCategory($query, $category)
    {
        return $query->where('category', $category);
    }

    public function scopeBySeverity($query, $severity)
    {
        return $query->where('severity', $severity);
    }

    public function scopeRecent($query, $hours = 24)
    {
        return $query->where('created_at', '>=', now()->subHours($hours));
    }

    /**
     * Static methods for logging
     */
    public static function log(
        string $action,
        string $description,
        ?User $user = null,
        array $metadata = [],
        string $severity = 'info',
        string $category = 'general'
    ): self {
        $user = $user ?? auth()->user();

        return static::create([
            'user_id' => $user?->id,
            'organization_id' => $user?->organization_id,
            'team_id' => null,
            'action' => $action,
            'description' => $description,
            'metadata' => $metadata,
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
            'session_id' => session()->getId(),
            'severity' => $severity,
            'category' => $category,
        ]);
    }

    public static function logUserAction(
        User $user,
        string $action,
        string $description,
        array $metadata = [],
        string $severity = 'info'
    ): self {
        return static::log($action, $description, $user, $metadata, $severity, 'user');
    }

    public static function logTeamAction(
        Team $team,
        string $action,
        string $description,
        ?User $user = null,
        array $metadata = []
    ): self {
        $user = $user ?? auth()->user();

        return static::create([
            'user_id' => $user?->id,
            'organization_id' => $team->organization_id,
            'team_id' => $team->id,
            'action' => $action,
            'description' => $description,
            'metadata' => array_merge($metadata, ['team_name' => $team->name]),
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
            'session_id' => session()->getId(),
            'model_type' => Team::class,
            'model_id' => $team->id,
            'severity' => 'info',
            'category' => 'team',
        ]);
    }

    public static function logOrganizationAction(
        string $action,
        string $description,
        ?User $user = null,
        array $metadata = []
    ): self {
        return static::log($action, $description, $user, $metadata, 'info', 'organization');
    }

    public static function logSecurityAction(
        string $action,
        string $description,
        ?User $user = null,
        array $metadata = []
    ): self {
        return static::log($action, $description, $user, $metadata, 'warning', 'security');
    }

    /**
     * Helper methods
     */
    public function getFormattedTimestamp(): string
    {
        return $this->created_at->format('M j, Y g:i A');
    }

    public function getTimeAgo(): string
    {
        return $this->created_at->diffForHumans();
    }

    public function getSeverityColor(): string
    {
        return match ($this->severity) {
            'critical' => 'red',
            'error' => 'red',
            'warning' => 'yellow',
            'info' => 'blue',
            'debug' => 'gray',
            default => 'gray'
        };
    }

    public function getSeverityIcon(): string
    {
        return match ($this->severity) {
            'critical' => 'fas fa-exclamation-triangle',
            'error' => 'fas fa-times-circle',
            'warning' => 'fas fa-exclamation-circle',
            'info' => 'fas fa-info-circle',
            'debug' => 'fas fa-bug',
            default => 'fas fa-circle'
        };
    }

    public function getCategoryIcon(): string
    {
        return match ($this->category) {
            'user' => 'fas fa-user',
            'team' => 'fas fa-users',
            'organization' => 'fas fa-building',
            'security' => 'fas fa-shield-alt',
            'system' => 'fas fa-cog',
            'inventory' => 'fas fa-box',
            'product' => 'fas fa-cube',
            'order' => 'fas fa-shopping-cart',
            default => 'fas fa-circle'
        };
    }

    public function getActionColor(): string
    {
        return match ($this->action) {
            'login', 'user_created', 'team_created' => 'green',
            'logout', 'user_deleted', 'team_deleted' => 'red',
            'user_updated', 'team_updated', 'settings_updated' => 'blue',
            'permission_granted', 'role_assigned' => 'purple',
            'permission_revoked', 'role_removed' => 'orange',
            'failed_login', 'unauthorized_access' => 'red',
            default => 'gray'
        };
    }

    /**
     * Activity analysis methods
     */
    public static function getMostActiveUsers(int $organizationId, int $days = 30, int $limit = 10)
    {
        return static::forOrganization($organizationId)
            ->where('created_at', '>=', now()->subDays($days))
            ->select('user_id', \DB::raw('count(*) as activity_count'))
            ->with('user:id,name,email')
            ->groupBy('user_id')
            ->orderBy('activity_count', 'desc')
            ->limit($limit)
            ->get();
    }

    public static function getActivityByAction(int $organizationId, int $days = 30)
    {
        return static::forOrganization($organizationId)
            ->where('created_at', '>=', now()->subDays($days))
            ->select('action', \DB::raw('count(*) as count'))
            ->groupBy('action')
            ->orderBy('count', 'desc')
            ->get()
            ->pluck('count', 'action');
    }

    public static function getDailyActivityCount(int $organizationId, int $days = 30)
    {
        return static::forOrganization($organizationId)
            ->where('created_at', '>=', now()->subDays($days))
            ->select(\DB::raw('DATE(created_at) as date'), \DB::raw('count(*) as count'))
            ->groupBy('date')
            ->orderBy('date')
            ->get();
    }

    public static function getSecurityEvents(int $organizationId, int $days = 7)
    {
        return static::forOrganization($organizationId)
            ->byCategory('security')
            ->where('created_at', '>=', now()->subDays($days))
            ->with('user:id,name,email')
            ->orderBy('created_at', 'desc')
            ->get();
    }

    public static function getUserLoginHistory(int $userId, int $days = 30)
    {
        return static::forUser($userId)
            ->whereIn('action', ['login', 'logout', 'failed_login'])
            ->where('created_at', '>=', now()->subDays($days))
            ->orderBy('created_at', 'desc')
            ->get();
    }

    public static function getTeamActivitySummary(int $teamId, int $days = 30)
    {
        return static::forTeam($teamId)
            ->where('created_at', '>=', now()->subDays($days))
            ->select('action', \DB::raw('count(*) as count'))
            ->groupBy('action')
            ->get()
            ->pluck('count', 'action');
    }

    /**
     * Cleanup methods
     */
    public static function cleanupOldLogs(int $daysToKeep = 90): int
    {
        return static::where('created_at', '<', now()->subDays($daysToKeep))->delete();
    }

    public static function archiveOldLogs(int $daysToKeep = 365): int
    {
        // This would move old logs to an archive table
        // Implementation depends on your archival strategy
        return 0;
    }
}
