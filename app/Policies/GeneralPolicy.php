<?php

namespace App\Policies;

use App\Models\User\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class GeneralPolicy
{
    use HandlesAuthorization;

    /**
     * Determine whether the user has admin access.
     */
    public function adminAccess(User $user): bool
    {
        return $user->hasPermission('admin.access') ||
               $user->hasRole(['organization_owner', 'admin']);
    }

    /**
     * Determine whether the user can view analytics.
     */
    public function viewAnalytics(User $user): bool
    {
        return $user->hasPermission('analytics.view') ||
               $user->hasRole(['organization_owner', 'admin', 'team_lead']);
    }

    /**
     * Determine whether the user can export analytics.
     */
    public function exportAnalytics(User $user): bool
    {
        return $user->hasPermission('analytics.export') ||
               $user->hasRole(['organization_owner', 'admin']);
    }

    /**
     * Determine whether the user can view reports.
     */
    public function viewReports(User $user): bool
    {
        return $user->hasPermission('reports.view') ||
               $user->hasRole(['organization_owner', 'admin', 'team_lead']);
    }

    /**
     * Determine whether the user can create reports.
     */
    public function createReports(User $user): bool
    {
        return $user->hasPermission('reports.create') ||
               $user->hasRole(['organization_owner', 'admin']);
    }

    /**
     * Determine whether the user can export reports.
     */
    public function exportReports(User $user): bool
    {
        return $user->hasPermission('reports.export') ||
               $user->hasRole(['organization_owner', 'admin']);
    }

    /**
     * Determine whether the user can view settings.
     */
    public function viewSettings(User $user): bool
    {
        return $user->hasPermission('settings.view') ||
               $user->hasRole(['organization_owner', 'admin']);
    }

    /**
     * Determine whether the user can edit settings.
     */
    public function editSettings(User $user): bool
    {
        return $user->hasPermission('settings.edit') ||
               $user->hasRole(['organization_owner', 'admin']);
    }

    /**
     * Determine whether the user can manage billing.
     */
    public function manageBilling(User $user): bool
    {
        return $user->hasPermission('billing.manage') ||
               $user->hasRole('organization_owner');
    }

    /**
     * Determine whether the user can view billing.
     */
    public function viewBilling(User $user): bool
    {
        return $user->hasPermission('billing.view') ||
               $user->hasRole('organization_owner');
    }

    /**
     * Determine whether the user can manage integrations.
     */
    public function manageIntegrations(User $user): bool
    {
        return $user->hasPermission('integrations.manage') ||
               $user->hasRole(['organization_owner', 'admin']);
    }

    /**
     * Determine whether the user can view integrations.
     */
    public function viewIntegrations(User $user): bool
    {
        return $user->hasPermission('integrations.view') ||
               $user->hasRole(['organization_owner', 'admin']);
    }

    /**
     * Determine whether the user can export data.
     */
    public function exportData(User $user, string $type = 'general'): bool
    {
        $basePermission = $user->hasPermission('data.export') ||
                         $user->hasRole(['organization_owner', 'admin']);

        // Type-specific export permissions
        switch ($type) {
            case 'users':
                return $basePermission && (
                    $user->hasPermission('users.export') ||
                    $user->hasRole(['organization_owner', 'admin'])
                );

            case 'teams':
                return $basePermission && (
                    $user->hasPermission('teams.export') ||
                    $user->hasRole(['organization_owner', 'admin'])
                );

            case 'analytics':
                return $basePermission && (
                    $user->hasPermission('analytics.export') ||
                    $user->hasRole(['organization_owner', 'admin'])
                );

            case 'audit_log':
                return $basePermission && (
                    $user->hasPermission('audit_log.export') ||
                    $user->hasRole(['organization_owner', 'admin'])
                );

            case 'inventory':
                return $basePermission && (
                    $user->hasPermission('inventory.export') ||
                    $user->hasRole(['organization_owner', 'admin', 'buyer'])
                );

            case 'products':
                return $basePermission && (
                    $user->hasPermission('products.export') ||
                    $user->hasRole(['organization_owner', 'admin', 'buyer', 'lister'])
                );

            default:
                return $basePermission;
        }
    }

    /**
     * Determine whether the user can import data.
     */
    public function importData(User $user, string $type = 'general'): bool
    {
        $basePermission = $user->hasPermission('data.import') ||
                         $user->hasRole(['organization_owner', 'admin']);

        // Type-specific import permissions
        switch ($type) {
            case 'users':
                return $basePermission && (
                    $user->hasPermission('users.import') ||
                    $user->hasRole(['organization_owner', 'admin'])
                );

            case 'inventory':
                return $basePermission && (
                    $user->hasPermission('inventory.import') ||
                    $user->hasRole(['organization_owner', 'admin', 'buyer'])
                );

            case 'products':
                return $basePermission && (
                    $user->hasPermission('products.import') ||
                    $user->hasRole(['organization_owner', 'admin', 'buyer'])
                );

            default:
                return $basePermission;
        }
    }

    /**
     * Determine whether the user can manage backups.
     */
    public function manageBackups(User $user): bool
    {
        return $user->hasPermission('backup.manage') ||
               $user->hasRole('organization_owner');
    }

    /**
     * Determine whether the user can view audit logs.
     */
    public function viewAuditLog(User $user): bool
    {
        return $user->hasPermission('audit_log.view') ||
               $user->hasRole(['organization_owner', 'admin']);
    }

    /**
     * Determine whether the user can export audit logs.
     */
    public function exportAuditLog(User $user): bool
    {
        return $user->hasPermission('audit_log.export') ||
               $user->hasRole(['organization_owner', 'admin']);
    }

    /**
     * Determine whether the user can manage roles.
     */
    public function manageRoles(User $user): bool
    {
        return $user->hasPermission('roles.manage') ||
               $user->hasRole(['organization_owner', 'admin']);
    }

    /**
     * Determine whether the user can view roles.
     */
    public function viewRoles(User $user): bool
    {
        return $user->hasPermission('roles.view') ||
               $user->hasRole(['organization_owner', 'admin']);
    }

    /**
     * Determine whether the user can assign roles.
     */
    public function assignRoles(User $user): bool
    {
        return $user->hasPermission('roles.assign') ||
               $user->hasRole(['organization_owner', 'admin']);
    }

    /**
     * Determine whether the user can manage permissions.
     */
    public function managePermissions(User $user): bool
    {
        return $user->hasPermission('permissions.manage') ||
               $user->hasRole('organization_owner');
    }

    /**
     * Determine whether the user can access developer features.
     */
    public function accessDeveloperFeatures(User $user): bool
    {
        return $user->hasPermission('developer.access') ||
               $user->hasRole('organization_owner');
    }

    /**
     * Determine whether the user can manage API tokens.
     */
    public function manageApiTokens(User $user): bool
    {
        return $user->hasPermission('api.manage') ||
               $user->hasRole(['organization_owner', 'admin']);
    }

    /**
     * Determine whether the user can view system health.
     */
    public function viewSystemHealth(User $user): bool
    {
        return $user->hasPermission('system.health') ||
               $user->hasRole(['organization_owner', 'admin']);
    }

    /**
     * Determine whether the user can manage webhooks.
     */
    public function manageWebhooks(User $user): bool
    {
        return $user->hasPermission('webhooks.manage') ||
               $user->hasRole(['organization_owner', 'admin']);
    }

    /**
     * Determine whether the user can access advanced features.
     */
    public function accessAdvancedFeatures(User $user): bool
    {
        return $user->hasPermission('advanced.access') ||
               $user->hasRole(['organization_owner', 'admin']);
    }
}
