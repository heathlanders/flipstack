<?php

namespace App\Policies;

use App\Models\Organization\Team;
use App\Models\User\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class TeamPolicy
{
    use HandlesAuthorization;

    /**
     * Determine whether the user can view any teams.
     */
    public function viewAny(User $user): bool
    {
        // User must belong to the same organization
        return $user->hasPermission('teams.view') ||
               $user->hasRole(['organization_owner', 'admin']);
    }

    /**
     * Determine whether the user can view the team.
     */
    public function view(User $user, Team $team): bool
    {
        // User must be in same organization
        if ($user->organization_id !== $team->organization_id) {
            return false;
        }

        // User can view if they have general permission or are team member/lead
        return $user->hasPermission('teams.view') ||
               $user->isMemberOfTeam($team) ||
               $user->isTeamLead($team) ||
               $user->hasRole(['organization_owner', 'admin']);
    }

    /**
     * Determine whether the user can create teams.
     */
    public function create(User $user): bool
    {
        // Check organization limits
        if (! $user->organization->canAddTeam()) {
            return false;
        }

        return $user->hasPermission('teams.create') ||
               $user->hasRole(['organization_owner', 'admin']);
    }

    /**
     * Determine whether the user can update the team.
     */
    public function update(User $user, Team $team): bool
    {
        // User must be in same organization
        if ($user->organization_id !== $team->organization_id) {
            return false;
        }

        return $user->hasPermission('teams.edit') ||
               $user->isTeamLead($team) ||
               $user->hasRole(['organization_owner', 'admin']);
    }

    /**
     * Determine whether the user can delete the team.
     */
    public function delete(User $user, Team $team): bool
    {
        // User must be in same organization
        if ($user->organization_id !== $team->organization_id) {
            return false;
        }

        // Only organization owners and admins can delete teams
        return $user->hasPermission('teams.delete') ||
               $user->hasRole(['organization_owner', 'admin']);
    }

    /**
     * Determine whether the user can add members to the team.
     */
    public function addMember(User $user, Team $team): bool
    {
        // User must be in same organization
        if ($user->organization_id !== $team->organization_id) {
            return false;
        }

        return $user->hasPermission('teams.manage_members') ||
               $user->isTeamLead($team) ||
               $user->hasRole(['organization_owner', 'admin']);
    }

    /**
     * Determine whether the user can remove members from the team.
     */
    public function removeMember(User $user, Team $team): bool
    {
        // User must be in same organization
        if ($user->organization_id !== $team->organization_id) {
            return false;
        }

        return $user->hasPermission('teams.manage_members') ||
               $user->isTeamLead($team) ||
               $user->hasRole(['organization_owner', 'admin']);
    }

    /**
     * Determine whether the user can manage a specific team member.
     */
    public function manageMember(User $user, Team $team, User $member): bool
    {
        // User must be in same organization
        if ($user->organization_id !== $team->organization_id ||
            $user->organization_id !== $member->organization_id) {
            return false;
        }

        // Can't manage organization owners (except by other org owners)
        if ($member->hasRole('organization_owner') && ! $user->hasRole('organization_owner')) {
            return false;
        }

        // Team leads can manage members but not other leads or admins
        if ($user->isTeamLead($team)) {
            return ! $member->hasRole(['organization_owner', 'admin', 'team_lead']);
        }

        return $user->hasPermission('teams.manage_members') ||
               $user->hasRole(['organization_owner', 'admin']);
    }

    /**
     * Determine whether the user can invite members to the team.
     */
    public function invite(User $user, Team $team): bool
    {
        // User must be in same organization
        if ($user->organization_id !== $team->organization_id) {
            return false;
        }

        // Check if team is at capacity
        if ($team->isAtCapacity()) {
            return false;
        }

        return $user->hasPermission('teams.invite') ||
               $user->isTeamLead($team) ||
               $user->hasRole(['organization_owner', 'admin']);
    }

    /**
     * Determine whether the user can view team analytics.
     */
    public function viewAnalytics(User $user, Team $team): bool
    {
        // User must be in same organization
        if ($user->organization_id !== $team->organization_id) {
            return false;
        }

        return $user->hasPermission('analytics.view') ||
               $user->isTeamLead($team) ||
               $user->hasRole(['organization_owner', 'admin']);
    }

    /**
     * Determine whether the user can manage team settings.
     */
    public function manageSettings(User $user, Team $team): bool
    {
        // User must be in same organization
        if ($user->organization_id !== $team->organization_id) {
            return false;
        }

        return $user->hasPermission('teams.manage_settings') ||
               $user->isTeamLead($team) ||
               $user->hasRole(['organization_owner', 'admin']);
    }
}
