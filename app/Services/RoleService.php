<?php

namespace App\Services;

use App\Models\System\AuditLog;
use App\Models\User\Role;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class RoleService
{
    public function createRole(array $data): Role
    {
        return DB::transaction(function () use ($data) {
            $role = Role::create([
                'name' => $data['name'],
                'slug' => $data['slug'] ?? Str::slug($data['name']),
                'description' => $data['description'] ?? null,
                'scope' => $data['scope'] ?? 'organization',
                'is_system' => false,
                'permissions' => $data['permissions'] ?? [],
            ]);

            $this->logRoleActivity($role, 'role_created', null, $role->toArray());

            return $role;
        });
    }

    public function updateRole(Role $role, array $data): Role
    {
        if ($role->is_system) {
            throw new \Exception('Cannot modify system roles');
        }

        $oldData = $role->toArray();

        $role->update($data);

        $this->logRoleActivity($role, 'role_updated', $oldData, $role->fresh()->toArray());

        return $role->fresh();
    }

    public function deleteRole(Role $role): bool
    {
        if ($role->is_system) {
            throw new \Exception('Cannot delete system roles');
        }

        if ($role->users()->exists()) {
            throw new \Exception('Cannot delete role that is assigned to users');
        }

        $this->logRoleActivity($role, 'role_deleted', $role->toArray(), null);

        return $role->delete();
    }

    public function duplicateRole(Role $role, string $newName): Role
    {
        return $this->createRole([
            'name' => $newName,
            'slug' => Str::slug($newName),
            'description' => "Copy of {$role->name}",
            'scope' => $role->scope,
            'permissions' => $role->permissions,
        ]);
    }

    public function getAvailablePermissions(): array
    {
        return [
            'organization' => [
                'organization.manage' => 'Manage organization settings',
                'organization.view' => 'View organization information',
                'organization.update_settings' => 'Update organization settings',
                'organization.manage_billing' => 'Manage billing and subscription',
                'organization.manage_subscription' => 'Manage subscription plans',
            ],
            'users' => [
                'users.create' => 'Create new users',
                'users.view' => 'View user information',
                'users.update' => 'Update user information',
                'users.delete' => 'Delete users',
                'users.manage_roles' => 'Assign and remove user roles',
                'users.invite' => 'Invite new users',
            ],
            'teams' => [
                'teams.create' => 'Create new teams',
                'teams.view' => 'View team information',
                'teams.update' => 'Update team information',
                'teams.delete' => 'Delete teams',
                'teams.manage_members' => 'Add and remove team members',
            ],
            'inventory' => [
                'inventory.create' => 'Create new inventory items',
                'inventory.view' => 'View inventory information',
                'inventory.update' => 'Update inventory items',
                'inventory.delete' => 'Delete inventory items',
                'inventory.import' => 'Import inventory data',
                'inventory.export' => 'Export inventory data',
                'inventory.manage_categories' => 'Manage inventory categories',
            ],
            'orders' => [
                'orders.create' => 'Create new orders',
                'orders.view' => 'View order information',
                'orders.update' => 'Update order information',
                'orders.delete' => 'Delete orders',
                'orders.process' => 'Process orders',
                'orders.refund' => 'Process refunds',
                'orders.manage_shipping' => 'Manage shipping',
            ],
            'analytics' => [
                'analytics.view' => 'View analytics and reports',
                'analytics.export' => 'Export analytics data',
                'reports.view' => 'View reports',
                'reports.create' => 'Create custom reports',
                'reports.export' => 'Export reports',
            ],
            'settings' => [
                'settings.view' => 'View system settings',
                'settings.update' => 'Update system settings',
                'settings.manage_integrations' => 'Manage integrations',
                'settings.manage_api_keys' => 'Manage API keys',
            ],
            'integrations' => [
                'integrations.view' => 'View integrations',
                'integrations.manage' => 'Manage integrations',
                'integrations.connect' => 'Connect new integrations',
                'integrations.sync' => 'Sync integration data',
            ],
        ];
    }

    private function logRoleActivity(Role $role, string $event, ?array $oldValues, ?array $newValues): void
    {
        AuditLog::create([
            'organization_id' => auth()->user()?->organization_id,
            'user_id' => auth()->user()?->id,
            'event' => $event,
            'auditable_type' => Role::class,
            'auditable_id' => $role->id,
            'old_values' => $oldValues,
            'new_values' => $newValues,
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
        ]);
    }
}
