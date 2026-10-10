<?php

namespace Database\Seeders\Production;

use App\Models\User\Role;
use Illuminate\Database\Seeder;

class SystemRoleSeeder extends Seeder
{
    /**
     * Seed system-wide roles with organization_id = NULL.
     *
     * These are template roles that can be used across all organizations.
     * Each role includes level, permissions, color code, and icon.
     */
    public function run(): void
    {
        $this->command->info('Seeding system roles...');

        $roles = $this->getRoleDefinitions();

        foreach ($roles as $slug => $roleData) {
            Role::updateOrCreate(
                [
                    'slug' => $slug,
                    'organization_id' => null,
                ],
                array_merge($roleData, [
                    'is_system' => true,
                    'is_active' => true,
                    'is_default' => $slug === 'employee',
                ])
            );

            $this->command->info("  Created/Updated role: {$roleData['name']} ({$slug})");
        }

        $this->command->info('System roles seeded: '.count($roles).' roles');
    }

    /**
     * Get all role definitions.
     */
    protected function getRoleDefinitions(): array
    {
        return [
            // Super Admin - Global system administrator
            'super_admin' => [
                'name' => 'Super Admin',
                'description' => 'Global system administrator with full access across all organizations',
                'level' => 9999,
                'color' => '#dc2626', // Red
                'icon' => 'fas fa-shield-alt',
                'permissions' => $this->getSuperAdminPermissions(),
            ],

            // Organization Owner
            'organization_owner' => [
                'name' => 'Organization Owner',
                'description' => 'Organization owner with full access to all features',
                'level' => 1000,
                'color' => '#7c3aed', // Purple
                'icon' => 'fas fa-crown',
                'permissions' => $this->getAllOrganizationPermissions(),
            ],

            // Admin
            'admin' => [
                'name' => 'Admin',
                'description' => 'Administrator with comprehensive management capabilities',
                'level' => 900,
                'color' => '#059669', // Green
                'icon' => 'fas fa-user-cog',
                'permissions' => [
                    'admin.access',
                    'users.view', 'users.create', 'users.edit', 'users.manage_status',
                    'teams.view', 'teams.create', 'teams.edit', 'teams.manage_members', 'teams.invite',
                    'roles.view', 'roles.assign_users',
                    'organization.view', 'organization.edit',
                    'inventory.view', 'inventory.create', 'inventory.edit', 'inventory.delete',
                    'products.view', 'products.create', 'products.edit', 'products.delete',
                    'analytics.view', 'reports.view', 'reports.create', 'reports.export',
                    'settings.view', 'settings.edit',
                    'integrations.view', 'integrations.manage',
                ],
            ],

            // Manager
            'manager' => [
                'name' => 'Manager',
                'description' => 'General manager with broad operational access',
                'level' => 750,
                'color' => '#059669', // Green
                'icon' => 'fas fa-briefcase',
                'permissions' => [
                    'organization.view', 'users.view', 'users.create',
                    'teams.view', 'teams.manage_members', 'teams.edit',
                    'inventory.view', 'inventory.create', 'inventory.edit',
                    'products.view', 'products.create', 'products.edit',
                    'intake.view', 'intake.process',
                    'analytics.view', 'reports.view', 'reports.create',
                    'settings.view', 'integrations.view',
                ],
            ],

            // Buyer
            'buyer' => [
                'name' => 'Buyer',
                'description' => 'Sourcing specialist focused on product acquisition',
                'level' => 700,
                'color' => '#2563eb', // Blue
                'icon' => 'fas fa-shopping-cart',
                'permissions' => [
                    'organization.view', 'users.view', 'teams.view',
                    'inventory.view', 'inventory.create', 'inventory.edit',
                    'products.view', 'products.create', 'products.edit',
                    'analytics.view', 'reports.view',
                    'settings.view', 'integrations.view',
                ],
            ],

            // Sourcing Specialist
            'sourcing_specialist' => [
                'name' => 'Sourcing Specialist',
                'description' => 'Alternative buyer role for sourcing specialists',
                'level' => 700,
                'color' => '#2563eb', // Blue
                'icon' => 'fas fa-search',
                'permissions' => [
                    'organization.view', 'users.view', 'teams.view',
                    'inventory.view', 'inventory.create', 'inventory.edit',
                    'products.view', 'products.create', 'products.edit',
                    'analytics.view', 'reports.view',
                    'settings.view', 'integrations.view',
                ],
            ],

            // Fulfillment Manager
            'fulfillment_manager' => [
                'name' => 'Fulfillment Manager',
                'description' => 'Manages order fulfillment and shipping',
                'level' => 650,
                'color' => '#f59e0b', // Amber
                'icon' => 'fas fa-truck',
                'permissions' => [
                    'organization.view', 'users.view', 'teams.view',
                    'inventory.view', 'inventory.edit', 'inventory.manage_stock',
                    'products.view', 'analytics.view', 'reports.view',
                    'settings.view', 'integrations.view',
                ],
            ],

            // Lister
            'lister' => [
                'name' => 'Lister',
                'description' => 'Listing specialist focused on product listings',
                'level' => 600,
                'color' => '#dc2626', // Red
                'icon' => 'fas fa-list',
                'permissions' => [
                    'organization.view', 'users.view', 'teams.view',
                    'inventory.view', 'inventory.edit',
                    'products.view', 'products.create', 'products.edit',
                    'intake.view', 'intake.process',
                    'analytics.view', 'settings.view', 'integrations.view',
                ],
            ],

            // Listing Specialist
            'listing_specialist' => [
                'name' => 'Listing Specialist',
                'description' => 'Alternative lister role for listing specialists',
                'level' => 600,
                'color' => '#dc2626', // Red
                'icon' => 'fas fa-edit',
                'permissions' => [
                    'organization.view', 'users.view', 'teams.view',
                    'inventory.view', 'inventory.edit',
                    'products.view', 'products.create', 'products.edit',
                    'intake.view', 'intake.process',
                    'analytics.view', 'settings.view', 'integrations.view',
                ],
            ],

            // Shipper
            'shipper' => [
                'name' => 'Shipper',
                'description' => 'Alternative fulfillment role for shippers',
                'level' => 550,
                'color' => '#f59e0b', // Amber
                'icon' => 'fas fa-box',
                'permissions' => [
                    'organization.view', 'users.view', 'teams.view',
                    'inventory.view', 'inventory.manage_stock',
                    'products.view', 'analytics.view',
                    'settings.view', 'integrations.view',
                ],
            ],

            // Support Agent
            'support_agent' => [
                'name' => 'Support Agent',
                'description' => 'Customer support and assistance',
                'level' => 500,
                'color' => '#64748b', // Slate
                'icon' => 'fas fa-headset',
                'permissions' => [
                    'organization.view', 'users.view', 'teams.view',
                    'inventory.view', 'products.view',
                    'analytics.view', 'settings.view',
                ],
            ],

            // Employee
            'employee' => [
                'name' => 'Employee',
                'description' => 'Standard employee with basic operational access',
                'level' => 400,
                'color' => '#64748b', // Slate
                'icon' => 'fas fa-user',
                'permissions' => [
                    'organization.view', 'users.view', 'teams.view',
                    'inventory.view', 'products.view',
                    'analytics.view', 'settings.view',
                ],
            ],
        ];
    }

    /**
     * Get super admin permissions (all permissions).
     */
    protected function getSuperAdminPermissions(): array
    {
        return array_merge(
            $this->getAllOrganizationPermissions(),
            [
                // System-level permissions
                'system.manage',
                'system.view_all_organizations',
                'system.manage_organizations',
                'system.impersonate_users',
                'system.view_audit_logs',
                'system.manage_system_settings',
                'system.manage_ai_prompts',
                'system.manage_ebay_categories',
            ]
        );
    }

    /**
     * Get all organization-level permissions.
     */
    protected function getAllOrganizationPermissions(): array
    {
        return [
            'admin.access',
            'users.view', 'users.create', 'users.edit', 'users.delete', 'users.manage_status',
            'users.reset_password', 'users.view_activity', 'users.impersonate', 'users.bulk_actions',
            'teams.view', 'teams.create', 'teams.edit', 'teams.delete', 'teams.manage_members', 'teams.invite',
            'roles.view', 'roles.create', 'roles.edit', 'roles.delete', 'roles.manage_permissions', 'roles.assign_users',
            'organization.view', 'organization.edit', 'organization.manage',
            'inventory.view', 'inventory.create', 'inventory.edit', 'inventory.delete', 'inventory.manage_stock',
            'products.view', 'products.create', 'products.edit', 'products.delete', 'products.import', 'products.export',
            'intake.view', 'intake.process', 'intake.approve', 'intake.reject',
            'analytics.view', 'analytics.export', 'reports.view', 'reports.create', 'reports.export',
            'settings.view', 'settings.edit', 'integrations.view', 'integrations.manage',
            'audit_log.view', 'audit_log.export',
            'export.data', 'import.data', 'backup.manage',
            'billing.view', 'billing.manage',
            'ai_prompts.view', 'ai_prompts.create', 'ai_prompts.edit', 'ai_prompts.delete',
            'suppliers.view', 'suppliers.create', 'suppliers.edit', 'suppliers.delete',
            'supplier_tiers.view', 'supplier_tiers.manage',
            'notifications.view', 'notifications.manage',
        ];
    }
}
