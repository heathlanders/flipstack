<?php

namespace App\Services;

use App\Models\Organization\Team;
use App\Models\System\AuditLog;
use App\Models\User\User;
use Illuminate\Support\Facades\DB;

class TeamService
{
    public function createTeam(array $data, User $owner): Team
    {
        return DB::transaction(function () use ($data, $owner) {
            $team = Team::create([
                'name' => $data['name'],
                'organization_id' => $owner->organization_id,
                'user_id' => $owner->id,
                'department' => $data['department'] ?? null,
                'personal_team' => false,
                'settings' => $data['settings'] ?? [],
            ]);

            // Add owner as admin
            $team->users()->attach($owner->id, ['role' => 'admin']);

            $this->logTeamActivity($team, 'team_created', null, $team->toArray());

            return $team;
        });
    }

    public function updateTeam(Team $team, array $data): Team
    {
        $oldData = $team->toArray();

        $team->update($data);

        $this->logTeamActivity($team, 'team_updated', $oldData, $team->fresh()->toArray());

        return $team->fresh();
    }

    public function deleteTeam(Team $team): bool
    {
        if ($team->personal_team) {
            throw new \Exception('Cannot delete personal teams');
        }

        return DB::transaction(function () use ($team) {
            $this->logTeamActivity($team, 'team_deleted', $team->toArray(), null);

            // Remove all members
            $team->users()->detach();

            // Delete team
            return $team->delete();
        });
    }

    public function addMember(Team $team, User $user, string $role = 'member'): void
    {
        if ($team->users()->where('user_id', $user->id)->exists()) {
            throw new \Exception('User is already a member of this team');
        }

        $team->users()->attach($user->id, ['role' => $role]);

        $this->logTeamActivity($team, 'member_added', null, [
            'user_id' => $user->id,
            'user_name' => $user->name,
            'role' => $role,
            'added_by' => auth()->user()?->name,
        ]);
    }

    public function removeMember(Team $team, User $user): void
    {
        if (! $team->users()->where('user_id', $user->id)->exists()) {
            throw new \Exception('User is not a member of this team');
        }

        if ($team->user_id === $user->id) {
            throw new \Exception('Cannot remove team owner');
        }

        $team->users()->detach($user->id);

        $this->logTeamActivity($team, 'member_removed', null, [
            'user_id' => $user->id,
            'user_name' => $user->name,
            'removed_by' => auth()->user()?->name,
        ]);
    }

    public function updateMemberRole(Team $team, User $user, string $role): void
    {
        if (! $team->users()->where('user_id', $user->id)->exists()) {
            throw new \Exception('User is not a member of this team');
        }

        $oldRole = $team->users()->where('user_id', $user->id)->first()->pivot->role;

        $team->users()->updateExistingPivot($user->id, ['role' => $role]);

        $this->logTeamActivity($team, 'member_role_updated',
            ['user_id' => $user->id, 'old_role' => $oldRole],
            ['user_id' => $user->id, 'new_role' => $role, 'updated_by' => auth()->user()?->name]
        );
    }

    public function getTeamStats(Team $team): array
    {
        return [
            'members_count' => $team->users()->count(),
            'admins_count' => $team->users()->wherePivot('role', 'admin')->count(),
            'active_members' => $team->users()->where('is_active', true)->count(),
            'recent_activity' => AuditLog::where('auditable_type', Team::class)
                ->where('auditable_id', $team->id)
                ->where('created_at', '>=', now()->subDays(7))
                ->count(),
        ];
    }

    private function logTeamActivity(Team $team, string $event, ?array $oldValues, ?array $newValues): void
    {
        AuditLog::create([
            'organization_id' => $team->organization_id,
            'user_id' => auth()->user()?->id,
            'event' => $event,
            'auditable_type' => Team::class,
            'auditable_id' => $team->id,
            'old_values' => $oldValues,
            'new_values' => $newValues,
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
        ]);
    }
}
