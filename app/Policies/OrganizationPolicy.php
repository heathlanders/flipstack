<?php

namespace App\Policies;

use App\Models\Organization\Organization;
use App\Models\User\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class OrganizationPolicy
{
    use HandlesAuthorization;

    /**
     * Determine whether the user can view the organization.
     */
    public function view(User $user, Organization $organization): bool
    {
        // User must belong to the organization
        return $user->organization_id === $organization->id;
    }

    /**
     * Determine whether the user can update the organization.
     */
    public function update(User $user, Organization $organization): bool
    {
        // User must belong to the organization
        if ($user->organization_id !== $organization->id) {
            return false;
        }

        return $user->hasPermission('organization.edit') ||
               $user->hasRole(['organization_owner', 'admin']);
    }

    /**
     * Determine whether the user can manage the organization.
     */
    public function manageOrganization(User $user, Organization $organization): bool
    {
        // User must belong to the organization
        if ($user->organization_id !== $organization->id) {
            return false;
        }

        return $user->hasPermission('organization.manage') ||
               $user->hasRole('organization_owner');
    }

    /**
     * Determine whether the user can view organization analytics.
     */
    public function viewOrganizationAnalytics(User $user, Organization $organization): bool
    {
        // User must belong to the organization
        if ($user->organization_id !== $organization->id) {
            return false;
        }

        return $user->hasPermission('analytics.view') ||
               $user->hasRole(['organization_owner', 'admin']);
    }

    /**
     * Determine whether the user can view organization audit log.
     */
    public function viewOrganizationAuditLog(User $user, Organization $organization): bool
    {
        // User must belong to the organization
        if ($user->organization_id !== $organization->id) {
            return false;
        }

        return $user->hasPermission('audit_log.view') ||
               $user->hasRole(['organization_owner', 'admin']);
    }

    /**
     * Determine whether the user can manage users in the organization.
     */
    public function manageUsers(User $user, Organization $organization): bool
    {
        // User must belong to the organization
        if ($user->organization_id !== $organization->id) {
            return false;
        }

        return $user->hasPermission('users.view') ||
               $user->hasRole(['organization_owner', 'admin']);
    }

    /**
     * Determine whether the user can create users in the organization.
     */
    public function createUser(User $user, Organization $organization): bool
    {
        // User must belong to the organization
        if ($user->organization_id !== $organization->id) {
            return false;
        }

        // Check organization user limits
        if (! $organization->canAddUser()) {
            return false;
        }

        return $user->hasPermission('users.create') ||
               $user->hasRole(['organization_owner', 'admin']);
    }

    /**
     * Determine whether the user can perform bulk user actions.
     */
    public function bulkUserActions(User $user, Organization $organization): bool
    {
        // User must belong to the organization
        if ($user->organization_id !== $organization->id) {
            return false;
        }

        return $user->hasPermission('users.bulk_actions') ||
               $user->hasRole(['organization_owner', 'admin']);
    }

    /**
     * Determine whether the user can manage organization settings.
     */
    public function manageSettings(User $user, Organization $organization): bool
    {
        // User must belong to the organization
        if ($user->organization_id !== $organization->id) {
            return false;
        }

        return $user->hasPermission('settings.edit') ||
               $user->hasRole(['organization_owner', 'admin']);
    }

    /**
     * Determine whether the user can manage organization branding.
     */
    public function manageBranding(User $user, Organization $organization): bool
    {
        // User must belong to the organization
        if ($user->organization_id !== $organization->id) {
            return false;
        }

        return $user->hasPermission('organization.branding') ||
               $user->hasRole(['organization_owner', 'admin']);
    }

    /**
     * Determine whether the user can view organization billing.
     */
    public function viewBilling(User $user, Organization $organization): bool
    {
        // User must belong to the organization
        if ($user->organization_id !== $organization->id) {
            return false;
        }

        return $user->hasPermission('billing.view') ||
               $user->hasRole('organization_owner');
    }

    /**
     * Determine whether the user can manage organization billing.
     */
    public function manageBilling(User $user, Organization $organization): bool
    {
        // User must belong to the organization
        if ($user->organization_id !== $organization->id) {
            return false;
        }

        return $user->hasPermission('billing.manage') ||
               $user->hasRole('organization_owner');
    }

    /**
     * Determine whether the user can manage organization integrations.
     */
    public function manageIntegrations(User $user, Organization $organization): bool
    {
        // User must belong to the organization
        if ($user->organization_id !== $organization->id) {
            return false;
        }

        return $user->hasPermission('integrations.manage') ||
               $user->hasRole(['organization_owner', 'admin']);
    }

    /**
     * Determine whether the user can delete the organization.
     */
    public function delete(User $user, Organization $organization): bool
    {
        // User must belong to the organization
        if ($user->organization_id !== $organization->id) {
            return false;
        }

        // Only organization owners can delete the organization
        return $user->hasRole('organization_owner');
    }

    /**
     * Determine whether the user can manage organization subscription.
     */
    public function manageSubscription(User $user, Organization $organization): bool
    {
        // User must belong to the organization
        if ($user->organization_id !== $organization->id) {
            return false;
        }

        return $user->hasPermission('billing.manage') ||
               $user->hasRole('organization_owner');
    }

    /**
     * Determine whether the user can view organization usage stats.
     */
    public function viewUsageStats(User $user, Organization $organization): bool
    {
        // User must belong to the organization
        if ($user->organization_id !== $organization->id) {
            return false;
        }

        return $user->hasPermission('analytics.view') ||
               $user->hasRole(['organization_owner', 'admin']);
    }
}
