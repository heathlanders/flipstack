<?php

namespace App\Providers;

use App\Models\AI\AiCategoryPrompt;
use App\Models\Organization\Organization;
use App\Models\User\Role;
use App\Models\User\User;
use App\Policies\AI\AiCategoryPromptPolicy;
use Illuminate\Foundation\Support\Providers\AuthServiceProvider as ServiceProvider;
use Illuminate\Support\Facades\Gate;

class AuthServiceProvider extends ServiceProvider
{
    /**
     * The model to policy mappings for the application.
     *
     * @var array<class-string, class-string>
     */
    protected $policies = [
        AiCategoryPrompt::class => AiCategoryPromptPolicy::class,
    ];

    /**
     * Register any authentication / authorization services.
     */
    public function boot(): void
    {
        $this->registerPolicies();

        // ================================
        // SUPPLIER GATES (role-slug based, see User::canViewSuppliers etc.)
        // ================================
        Gate::define('view-suppliers', function (User $user) {
            return $user->canViewSuppliers();
        });

        Gate::define('manage-supplier-admin-config', function (User $user) {
            return $user->canManageSupplierAdminConfig();
        });

        // ================================
        // USER MANAGEMENT GATES - UPDATED TO DOT NOTATION
        // ================================
        Gate::define('users.view', function (User $user) {
            return $user->hasPermission('users.view');
        });

        Gate::define('users.create', function (User $user) {
            return $user->hasPermission('users.create');
        });

        Gate::define('users.edit', function (User $user) {
            return $user->hasPermission('users.edit');
        });

        Gate::define('users.delete', function (User $user) {
            return $user->hasPermission('users.delete');
        });

        Gate::define('view-user', function (User $user, User $targetUser) {
            return $user->hasPermission('users.view') || $user->id === $targetUser->id;
        });

        Gate::define('edit-user', function (User $user, User $targetUser) {
            return $user->hasPermission('users.edit') || $user->id === $targetUser->id;
        });

        Gate::define('delete-user', function (User $user, User $targetUser) {
            return $user->hasPermission('users.delete') && $user->id !== $targetUser->id;
        });

        // ================================
        // TEAM MANAGEMENT GATES - UPDATED TO DOT NOTATION
        // ================================
        Gate::define('teams.view', function (User $user) {
            return $user->hasPermission('teams.view');
        });

        Gate::define('teams.create', function (User $user) {
            return $user->hasPermission('teams.create');
        });

        Gate::define('teams.edit', function (User $user) {
            return $user->hasPermission('teams.edit');
        });

        Gate::define('teams.delete', function (User $user) {
            return $user->hasPermission('teams.delete');
        });

        Gate::define('view-team', function (User $user, $team = null) {
            if ($team && method_exists($user, 'isMemberOfTeam') && $user->isMemberOfTeam($team)) {
                return true;
            }

            return $user->hasPermission('teams.view');
        });

        Gate::define('manage-team', function (User $user, $team = null) {
            if ($team && isset($team->team_lead_id) && $team->team_lead_id === $user->id) {
                return true;
            }

            return $user->hasPermission('teams.edit');
        });

        Gate::define('view-teams', function (User $user) {
            return $user->hasPermission('teams.view');
        });

        Gate::define('create-teams', function (User $user) {
            return $user->hasPermission('teams.create');
        });

        Gate::define('delete-team', function (User $user, $team = null) {
            if ($team && $user->organization_id !== $team->organization_id) {
                return false;
            }

            return $user->hasPermission('teams.delete');
        });

        Gate::define('join-team', function (User $user, $team = null) {
            if (! $team) {
                return false;
            }
            if ($user->organization_id !== $team->organization_id) {
                return false;
            }
            if ($user->isMemberOfTeam($team)) {
                return false;
            }

            return true;
        });

        Gate::define('leave-team', function (User $user, $team = null) {
            if (! $team) {
                return false;
            }
            if (! $user->isMemberOfTeam($team)) {
                return false;
            }

            return true;
        });

        // ================================
        // ROLE MANAGEMENT GATES - UPDATED TO DOT NOTATION
        // ================================
        Gate::define('roles.view', function (User $user) {
            return $user->hasPermission('roles.view');
        });

        Gate::define('roles.create', function (User $user) {
            return $user->hasPermission('roles.create');
        });

        Gate::define('roles.edit', function (User $user) {
            return $user->hasPermission('roles.edit');
        });

        Gate::define('roles.delete', function (User $user) {
            return $user->hasPermission('roles.delete');
        });

        Gate::define('view-role', function (User $user, ?Role $role = null) {
            return $user->hasPermission('roles.view');
        });

        Gate::define('edit-role', function (User $user, Role $role) {
            // Organization owners can edit any role
            if ($user->hasPermission('roles.edit_system') || $user->getPrimaryRole() === 'organization_owner') {
                return true;
            }

            // Users can edit roles if they have permission
            if (! $user->hasPermission('roles.edit')) {
                return false;
            }

            // Can't edit system roles unless you're an organization owner
            if ($role->is_system && $user->getPrimaryRole() !== 'organization_owner') {
                return false;
            }

            // Can edit roles in same organization
            return $role->organization_id === $user->organization_id;
        });

        Gate::define('delete-role', function (User $user, Role $role) {
            // Organization owners can delete any non-default role
            if ($user->getPrimaryRole() === 'organization_owner') {
                return ! $role->is_default;
            }

            return $user->hasPermission('roles.delete') &&
                   $role->organization_id === $user->organization_id &&
                   ! $role->is_default &&
                   ! $role->is_system;
        });

        Gate::define('manage-permissions', function (User $user, Role $role) {
            // Organization owners can manage all permissions
            if ($user->getPrimaryRole() === 'organization_owner') {
                return true;
            }

            return $user->hasPermission('roles.edit') &&
                   $role->organization_id === $user->organization_id &&
                   ! $role->is_system;
        });

        // ================================
        // AI PROMPTS MANAGEMENT GATES
        // ================================
        Gate::define('ai_prompts.view', function (User $user) {
            return $user->hasPermission('ai_prompts.view');
        });

        Gate::define('ai_prompts.create', function (User $user) {
            return $user->hasPermission('ai_prompts.create');
        });

        Gate::define('ai_prompts.edit', function (User $user) {
            return $user->hasPermission('ai_prompts.edit');
        });

        Gate::define('ai_prompts.delete', function (User $user) {
            return $user->hasPermission('ai_prompts.delete');
        });

        // ================================
        // ORGANIZATION MANAGEMENT GATES - UPDATED TO DOT NOTATION
        // ================================
        Gate::define('organization.view', function (User $user) {
            return $user->hasPermission('organization.view');
        });

        Gate::define('organization.edit', function (User $user) {
            return $user->hasPermission('organization.edit');
        });

        Gate::define('organization.manage', function (User $user) {
            return $user->hasPermission('organization.manage') || $user->hasPermission('organization.edit');
        });

        // ================================
        // FEATURE DOMAIN GATES
        // ================================
        Gate::define('inventory.view', fn (User $user) => $user->hasPermission('inventory.view'));
        Gate::define('inventory.create', fn (User $user) => $user->hasPermission('inventory.create'));
        Gate::define('inventory.edit', fn (User $user) => $user->hasPermission('inventory.edit'));
        Gate::define('inventory.delete', fn (User $user) => $user->hasPermission('inventory.delete'));
        Gate::define('inventory.manage_stock', fn (User $user) => $user->hasPermission('inventory.manage_stock'));

        Gate::define('products.view', fn (User $user) => $user->hasPermission('products.view'));
        Gate::define('products.create', fn (User $user) => $user->hasPermission('products.create'));
        Gate::define('products.edit', fn (User $user) => $user->hasPermission('products.edit'));
        Gate::define('products.delete', fn (User $user) => $user->hasPermission('products.delete'));

        Gate::define('orders.view', fn (User $user) => $user->hasPermission('orders.view'));
        Gate::define('orders.create', fn (User $user) => $user->hasPermission('orders.create'));
        Gate::define('orders.edit', fn (User $user) => $user->hasPermission('orders.edit'));

        Gate::define('analytics.view', fn (User $user) => $user->hasPermission('analytics.view'));
        Gate::define('analytics.export', fn (User $user) => $user->hasPermission('analytics.export'));

        Gate::define('settings.view', fn (User $user) => $user->hasPermission('settings.view'));
        Gate::define('settings.edit', fn (User $user) => $user->hasPermission('settings.edit'));

        Gate::define('reports.view', fn (User $user) => $user->hasPermission('reports.view'));

        Gate::define('intake.view', fn (User $user) => $user->hasPermission('intake.view'));
        Gate::define('intake.process', fn (User $user) => $user->hasPermission('intake.process'));

        Gate::define('integrations.view', fn (User $user) => $user->hasPermission('integrations.view'));
        Gate::define('integrations.manage', fn (User $user) => $user->hasPermission('integrations.manage'));

        Gate::define('admin.access', fn (User $user) => $user->hasPermission('admin.access'));

        // ================================
        // DASHBOARD GATES - UPDATED TO USE ADMIN.ACCESS
        // ================================
        Gate::define('dashboard.owner', function (User $user) {
            return $user->hasPermission('admin.access') ||
                   in_array($user->getPrimaryRole(), ['organization_owner', 'admin', 'manager']);
        });

        Gate::define('dashboard.buyer', function (User $user) {
            return $user->hasPermission('inventory.view') ||
                   in_array($user->getPrimaryRole(), ['buyer', 'sourcing_specialist']);
        });

        Gate::define('dashboard.lister', function (User $user) {
            return $user->hasPermission('products.view') ||
                   in_array($user->getPrimaryRole(), ['lister', 'listing_specialist']);
        });

        Gate::define('dashboard.fulfillment', function (User $user) {
            return $user->hasPermission('inventory.view') ||
                   in_array($user->getPrimaryRole(), ['fulfillment_manager', 'shipper']);
        });

        Gate::define('dashboard.support', function (User $user) {
            return in_array($user->getPrimaryRole(), ['support_agent']);
        });

        // ================================
        // WILDCARD GATE FOR SUPER ADMINS
        // ================================
        Gate::before(function (User $user, string $ability) {
            // Super admins bypass all gates (check role directly)
            if ($user->hasRole('super_admin')) {
                return true;
            }

            // Users with admin.access AND wildcard permission bypass all gates
            if ($user->hasPermission('admin.access') && $user->hasPermission('*')) {
                return true;
            }

            // Organization owners have most permissions
            if ($user->getPrimaryRole() === 'organization_owner') {
                // Let specific gates handle role management logic
                if (in_array($ability, ['edit-role', 'delete-role', 'manage-permissions'])) {
                    return null;
                }

                return true;
            }

            return null; // Continue to normal gate checking
        });

        // ================================
        // ADDITIONAL USER MANAGEMENT GATES
        // ================================
        Gate::define('manageUsers', function (User $user, Organization $organization) {
            return $user->organization_id === $organization->id &&
                $user->hasPermission('users.view');
        });

        Gate::define('createUser', function (User $user, Organization $organization) {
            return $user->organization_id === $organization->id &&
                $user->hasPermission('users.create');
        });

        Gate::define('viewUser', function (User $user, User $targetUser) {
            return $user->organization_id === $targetUser->organization_id &&
                ($user->hasPermission('users.view') || $user->id === $targetUser->id);
        });

        Gate::define('editUser', function (User $user, User $targetUser) {
            return $user->organization_id === $targetUser->organization_id &&
                ($user->hasPermission('users.edit') || $user->id === $targetUser->id);
        });

        Gate::define('deactivateUser', function (User $user, User $targetUser) {
            return $user->organization_id === $targetUser->organization_id &&
                $user->hasPermission('users.manage') &&
                $user->id !== $targetUser->id;
        });

        Gate::define('reactivateUser', function (User $user, User $targetUser) {
            return $user->organization_id === $targetUser->organization_id &&
                $user->hasPermission('users.manage');
        });

        Gate::define('resetUserPassword', function (User $user, User $targetUser) {
            return $user->organization_id === $targetUser->organization_id &&
                $user->hasPermission('users.manage');
        });

        Gate::define('manageUserTeams', function (User $user, User $targetUser) {
            return $user->organization_id === $targetUser->organization_id &&
                ($user->hasPermission('teams.manage') || $user->hasPermission('users.manage'));
        });

        Gate::define('viewUserActivity', function (User $user, User $targetUser) {
            return $user->organization_id === $targetUser->organization_id &&
                ($user->hasPermission('users.view_activity') || $user->id === $targetUser->id);
        });

        Gate::define('bulkUserActions', function (User $user, Organization $organization) {
            return $user->organization_id === $organization->id &&
                $user->hasPermission('users.bulk_actions');
        });

        // ================================
        // ORGANIZATION-SPECIFIC GATES
        // ================================
        Gate::define('viewRoles', function (User $user, Organization $organization) {
            return $user->organization_id === $organization->id &&
                $user->hasPermission('roles.view');
        });

        Gate::define('createRole', function (User $user, Organization $organization) {
            return $user->organization_id === $organization->id &&
                $user->hasPermission('roles.create');
        });

        Gate::define('deleteRole', function (User $user, Role $role) {
            return $role->organization_id === $user->organization_id &&
                ! $role->is_default &&
                $user->hasPermission('roles.delete');
        });

        Gate::define('managePermissions', function (User $user, Role $role) {
            return $user->hasPermission('roles.manage_permissions') &&
                ($role->organization_id === $user->organization_id || $user->hasPermission('roles.edit_system'));
        });
    }
}
