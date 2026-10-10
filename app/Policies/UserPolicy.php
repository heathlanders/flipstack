<?php

namespace App\Policies;

use App\Models\User\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class UserPolicy
{
    use HandlesAuthorization;

    /**
     * Determine whether the user can view any users.
     */
    public function viewAny(User $user): bool
    {
        // User can view users in their organization if they have permission
        return $user->hasPermission('users.view') ||
               $user->hasRole(['organization_owner', 'admin', 'team_lead']);
    }

    /**
     * Determine whether the user can view the specific user.
     */
    public function viewUser(User $user, User $targetUser): bool
    {
        // Can always view own profile
        if ($user->id === $targetUser->id) {
            return true;
        }

        // Must be in same organization
        if ($user->organization_id !== $targetUser->organization_id) {
            return false;
        }

        // Can view team members if you're a team lead
        if ($user->hasRole('team_lead')) {
            return $user->leadsAnyTeamWith($targetUser);
        }

        return $user->hasPermission('users.view') ||
               $user->hasRole(['organization_owner', 'admin']);
    }

    /**
     * Determine whether the user can create users.
     */
    public function create(User $user): bool
    {
        // Check organization user limits
        if (! $user->organization->canAddUser()) {
            return false;
        }

        return $user->hasPermission('users.create') ||
               $user->hasRole(['organization_owner', 'admin']);
    }

    /**
     * Determine whether the user can edit the user.
     */
    public function editUser(User $user, User $targetUser): bool
    {
        // Can edit own profile (basic info only)
        if ($user->id === $targetUser->id) {
            return true;
        }

        // Must be in same organization
        if ($user->organization_id !== $targetUser->organization_id) {
            return false;
        }

        // Cannot edit organization owners unless you are one
        if ($targetUser->hasRole('organization_owner') && ! $user->hasRole('organization_owner')) {
            return false;
        }

        // Cannot edit admins unless you are org owner or admin
        if ($targetUser->hasRole('admin') && ! $user->hasRole(['organization_owner', 'admin'])) {
            return false;
        }

        return $user->hasPermission('users.edit') ||
               $user->hasRole(['organization_owner', 'admin']);
    }

    /**
     * Determine whether the user can delete the user.
     */
    public function delete(User $user, User $targetUser): bool
    {
        // Cannot delete self
        if ($user->id === $targetUser->id) {
            return false;
        }

        // Must be in same organization
        if ($user->organization_id !== $targetUser->organization_id) {
            return false;
        }

        // Cannot delete organization owners
        if ($targetUser->hasRole('organization_owner')) {
            return false;
        }

        // Cannot delete admins unless you are org owner
        if ($targetUser->hasRole('admin') && ! $user->hasRole('organization_owner')) {
            return false;
        }

        return $user->hasPermission('users.delete') ||
               $user->hasRole(['organization_owner', 'admin']);
    }

    /**
     * Determine whether the user can activate/deactivate users.
     */
    public function deactivateUser(User $user, User $targetUser): bool
    {
        // Cannot deactivate self
        if ($user->id === $targetUser->id) {
            return false;
        }

        // Must be in same organization
        if ($user->organization_id !== $targetUser->organization_id) {
            return false;
        }

        // Cannot deactivate organization owners
        if ($targetUser->hasRole('organization_owner')) {
            return false;
        }

        // Cannot deactivate admins unless you are org owner
        if ($targetUser->hasRole('admin') && ! $user->hasRole('organization_owner')) {
            return false;
        }

        return $user->hasPermission('users.manage_status') ||
               $user->hasRole(['organization_owner', 'admin']);
    }

    /**
     * Determine whether the user can reactivate users.
     */
    public function reactivateUser(User $user, User $targetUser): bool
    {
        // Must be in same organization
        if ($user->organization_id !== $targetUser->organization_id) {
            return false;
        }

        return $user->hasPermission('users.manage_status') ||
               $user->hasRole(['organization_owner', 'admin']);
    }

    /**
     * Determine whether the user can manage user status.
     */
    public function manageUserStatus(User $user, User $targetUser): bool
    {
        return $this->deactivateUser($user, $targetUser) ||
               $this->reactivateUser($user, $targetUser);
    }

    /**
     * Determine whether the user can reset another user's password.
     */
    public function resetUserPassword(User $user, User $targetUser): bool
    {
        // Cannot reset own password through admin function
        if ($user->id === $targetUser->id) {
            return false;
        }

        // Must be in same organization
        if ($user->organization_id !== $targetUser->organization_id) {
            return false;
        }

        // Cannot reset organization owner passwords unless you are one
        if ($targetUser->hasRole('organization_owner') && ! $user->hasRole('organization_owner')) {
            return false;
        }

        return $user->hasPermission('users.reset_password') ||
               $user->hasRole(['organization_owner', 'admin']);
    }

    /**
     * Determine whether the user can manage another user's team memberships.
     */
    public function manageUserTeams(User $user, User $targetUser): bool
    {
        // Cannot manage own teams through admin function
        if ($user->id === $targetUser->id) {
            return false;
        }

        // Must be in same organization
        if ($user->organization_id !== $targetUser->organization_id) {
            return false;
        }

        // Cannot manage organization owner team memberships unless you are one
        if ($targetUser->hasRole('organization_owner') && ! $user->hasRole('organization_owner')) {
            return false;
        }

        return $user->hasPermission('users.manage_teams') ||
               $user->hasRole(['organization_owner', 'admin']);
    }

    /**
     * Determine whether the user can view another user's activity.
     */
    public function viewUserActivity(User $user, User $targetUser): bool
    {
        // Can view own activity
        if ($user->id === $targetUser->id) {
            return true;
        }

        // Must be in same organization
        if ($user->organization_id !== $targetUser->organization_id) {
            return false;
        }

        // Team leads can view their team members' activity
        if ($user->hasRole('team_lead')) {
            return $user->leadsAnyTeamWith($targetUser);
        }

        return $user->hasPermission('users.view_activity') ||
               $user->hasRole(['organization_owner', 'admin']);
    }

    /**
     * Determine whether the user can impersonate another user.
     */
    public function impersonate(User $user, User $targetUser): bool
    {
        // Cannot impersonate self
        if ($user->id === $targetUser->id) {
            return false;
        }

        // Must be in same organization
        if ($user->organization_id !== $targetUser->organization_id) {
            return false;
        }

        // Cannot impersonate organization owners
        if ($targetUser->hasRole('organization_owner')) {
            return false;
        }

        // Only organization owners can impersonate (for security)
        return $user->hasPermission('users.impersonate') &&
               $user->hasRole('organization_owner');
    }

    /**
     * Determine whether the user can perform bulk user actions.
     */
    public function bulkActions(User $user): bool
    {
        return $user->hasPermission('users.bulk_actions') ||
               $user->hasRole(['organization_owner', 'admin']);
    }
}
