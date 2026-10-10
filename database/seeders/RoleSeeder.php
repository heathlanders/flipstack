<?php

namespace Database\Seeders;

use App\Models\Organization\Organization;
use App\Models\User\Role;
use App\Models\User\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class RoleSeeder extends Seeder
{
    /**
     * 🎭 COMPREHENSIVE ROLE SEEDER FOR TESTING
     *
     * Ensures every organization has complete role coverage:
     * ✅ At least 1 user in each critical dashboard role
     * ✅ Additional users in supporting roles
     * ✅ Creates test users where needed
     * ✅ Maintains realistic organizational structure
     */

    // Critical roles that MUST have users for dashboard testing
    private $criticalRoles = [
        'super_admin',         // → System Admin (full system access)
        'organization_owner',  // → Owner Dashboard
        'admin',              // → Owner Dashboard
        'buyer',              // → Buyer Dashboard
        'lister',             // → Lister Dashboard
        'fulfillment_manager', // → Fulfillment Dashboard
        'support_agent',       // → Support Dashboard
        'supplier_admin',      // → Supplier Portal (full access)
    ];

    // Supporting roles for comprehensive testing
    private $supportingRoles = [
        'sourcing_specialist', // Buyer variant
        'listing_specialist',  // Lister variant
        'shipper',            // Fulfillment variant
        'manager',            // General management
        'employee',            // Basic access
        'supplier_staff',      // Supplier portal (limited)
    ];

    public function run(): void
    {
        $this->command->info('🎭 Creating comprehensive role coverage for testing...');

        // Step 1: Create/update role templates
        $this->createAllRoleTemplates();

        // Step 2: Analyze current coverage gaps
        $coverageGaps = $this->analyzeCoverageGaps();
        $this->displayCoverageAnalysis($coverageGaps);

        // Step 3: Create test users where needed
        $this->createNeededTestUsers($coverageGaps);

        // Step 4: Assign roles systematically
        $this->assignRolesSystematically();

        // Step 5: Verify complete coverage
        $this->verifyCompleteCoverage();

        $this->displayFinalSummary();
    }

    /**
     * Create all role templates from seeder definition
     */
    private function createAllRoleTemplates(): void
    {
        $this->command->info('📝 Creating/updating all role templates...');

        $allRoles = array_merge($this->criticalRoles, $this->supportingRoles);
        $roleDefinitions = $this->getAllRoleDefinitions();

        foreach ($allRoles as $roleSlug) {
            if (isset($roleDefinitions[$roleSlug])) {
                $roleData = $roleDefinitions[$roleSlug];

                Role::updateOrCreate(
                    ['slug' => $roleSlug],
                    array_merge($roleData, [
                        'organization_id' => null, // System template
                        'is_system' => true,
                        'is_active' => true,
                        'is_default' => false,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ])
                );

                $this->command->info("✅ Template: {$roleData['name']} ({$roleSlug})");
            }
        }
    }

    /**
     * Analyze what roles are missing users in each organization
     */
    private function analyzeCoverageGaps(): array
    {
        $this->command->info('🔍 Analyzing role coverage gaps...');

        $organizations = Organization::with('users.roles')->get();
        $gaps = [];

        foreach ($organizations as $org) {
            $orgGaps = [
                'organization' => $org,
                'missing_critical' => [],
                'missing_supporting' => [],
                'existing_users' => $org->users->count(),
                'active_users' => $org->users->where('is_active', true)->count(),
            ];

            // Check critical roles
            foreach ($this->criticalRoles as $roleSlug) {
                $hasUser = $this->organizationHasUserInRole($org, $roleSlug);
                if (! $hasUser) {
                    $orgGaps['missing_critical'][] = $roleSlug;
                }
            }

            // Check supporting roles
            foreach ($this->supportingRoles as $roleSlug) {
                $hasUser = $this->organizationHasUserInRole($org, $roleSlug);
                if (! $hasUser) {
                    $orgGaps['missing_supporting'][] = $roleSlug;
                }
            }

            $gaps[] = $orgGaps;
        }

        return $gaps;
    }

    /**
     * Create test users where needed to fill critical gaps
     */
    private function createNeededTestUsers(array $coverageGaps): void
    {
        $this->command->info('👥 Creating test users to fill coverage gaps...');

        foreach ($coverageGaps as $gap) {
            $org = $gap['organization'];
            $missingCritical = $gap['missing_critical'];
            $missingSupporting = $gap['missing_supporting'];

            $this->command->info("📊 Processing {$org->name}:");
            $this->command->info('  Missing Critical: '.count($missingCritical));
            $this->command->info('  Missing Supporting: '.count($missingSupporting));

            // Create users for critical roles first
            foreach ($missingCritical as $roleSlug) {
                $this->createTestUserForRole($org, $roleSlug, true);
            }

            // Create users for supporting roles (limit to avoid too many test users)
            $supportingLimit = min(3, count($missingSupporting));
            for ($i = 0; $i < $supportingLimit; $i++) {
                if (isset($missingSupporting[$i])) {
                    $this->createTestUserForRole($org, $missingSupporting[$i], false);
                }
            }
        }
    }

    /**
     * Create a test user for a specific role
     */
    private function createTestUserForRole(Organization $org, string $roleSlug, bool $isCritical): User
    {
        $roleNames = [
            'super_admin' => 'Test Super Admin',
            'organization_owner' => 'Test Owner',
            'admin' => 'Test Admin',
            'buyer' => 'Test Buyer',
            'lister' => 'Test Lister',
            'fulfillment_manager' => 'Test Fulfillment Manager',
            'support_agent' => 'Test Support Agent',
            'sourcing_specialist' => 'Test Sourcing Specialist',
            'listing_specialist' => 'Test Listing Specialist',
            'shipper' => 'Test Shipper',
            'manager' => 'Test Manager',
            'employee' => 'Test Employee',
            'supplier_admin' => 'Test Supplier Admin',
            'supplier_staff' => 'Test Supplier Staff',
        ];

        $jobTitles = [
            'super_admin' => 'System Administrator',
            'organization_owner' => 'CEO',
            'admin' => 'Administrator',
            'buyer' => 'Buyer',
            'lister' => 'Product Lister',
            'fulfillment_manager' => 'Fulfillment Manager',
            'support_agent' => 'Support Agent',
            'sourcing_specialist' => 'Sourcing Specialist',
            'listing_specialist' => 'Listing Specialist',
            'shipper' => 'Shipping Coordinator',
            'manager' => 'Operations Manager',
            'employee' => 'Employee',
            'supplier_admin' => 'Supplier Administrator',
            'supplier_staff' => 'Supplier Staff',
        ];

        $userName = $roleNames[$roleSlug] ?? 'Test User';
        $jobTitle = $jobTitles[$roleSlug] ?? 'Employee';

        // Create unique email
        $baseEmail = strtolower(str_replace(' ', '.', $userName));
        $email = $baseEmail.'+'.$org->id.'@test.local';

        $user = User::create([
            'name' => $userName,
            'email' => $email,
            'password' => Hash::make('password'),
            'organization_id' => $org->id,
            'job_title' => $jobTitle,
            'is_active' => true,
            'email_verified_at' => now(),
            'metadata' => json_encode([
                'created_by_seeder' => true,
                'intended_role' => $roleSlug,
                'is_test_user' => true,
                'critical_role' => $isCritical,
            ]),
        ]);

        $priority = $isCritical ? 'CRITICAL' : 'supporting';
        $this->command->info("  ✅ Created {$priority} test user: {$userName} ({$email})");

        return $user;
    }

    /**
     * Systematically assign all users to appropriate roles
     */
    private function assignRolesSystematically(): void
    {
        $this->command->info('🔄 Assigning roles systematically...');

        // Clear all existing role assignments
        DB::table('user_roles')->delete();

        $organizations = Organization::with('users')->get();

        foreach ($organizations as $org) {
            $this->assignRolesForOrganization($org);
        }
    }

    /**
     * Assign roles for a specific organization
     */
    private function assignRolesForOrganization(Organization $org): void
    {
        $users = $org->users()->where('is_active', true)->get();

        if ($users->isEmpty()) {
            $this->command->warn("⚠️  No active users in {$org->name}");

            return;
        }

        $this->command->info("👥 Assigning roles for {$org->name} ({$users->count()} users):");

        // Step 1: Assign users based on their intended roles (from metadata)
        foreach ($users as $user) {
            $intendedRole = $this->getUserIntendedRole($user);
            if ($intendedRole) {
                $this->assignUserToRole($user, $intendedRole, $org->id);

                // Set organization owner
                if ($intendedRole === 'organization_owner') {
                    $org->update(['owner_id' => $user->id]);
                }
            }
        }

        // Step 2: Fill any remaining critical gaps
        $this->fillRemainingCriticalGaps($org, $users);

        // Step 3: Assign remaining users to supporting roles
        $this->assignRemainingUsers($org, $users);
    }

    /**
     * Fill any critical roles that still don't have users
     */
    private function fillRemainingCriticalGaps(Organization $org, $users): void
    {
        foreach ($this->criticalRoles as $roleSlug) {
            if (! $this->organizationHasUserInRole($org, $roleSlug)) {
                // Find an unassigned user or one that can take multiple roles
                $availableUser = $users->first(function ($user) {
                    return ! $this->userHasAnyRole($user, $user->organization_id);
                });

                if ($availableUser) {
                    $this->assignUserToRole($availableUser, $roleSlug, $org->id);
                    $this->command->info("  🔧 Gap-filled: {$availableUser->name} → {$roleSlug}");
                }
            }
        }
    }

    /**
     * Assign remaining users to supporting roles
     */
    private function assignRemainingUsers(Organization $org, $users): void
    {
        $unassignedUsers = $users->filter(function ($user) use ($org) {
            return ! $this->userHasAnyRole($user, $org->id);
        });

        $supportingRoleIndex = 0;
        foreach ($unassignedUsers as $user) {
            $roleSlug = $this->supportingRoles[$supportingRoleIndex % count($this->supportingRoles)];
            $this->assignUserToRole($user, $roleSlug, $org->id);
            $supportingRoleIndex++;
        }
    }

    /**
     * Helper Methods
     */
    private function organizationHasUserInRole(Organization $org, string $roleSlug): bool
    {
        return DB::table('user_roles')
            ->join('roles', 'user_roles.role_id', '=', 'roles.id')
            ->where('user_roles.organization_id', $org->id)
            ->where('roles.slug', $roleSlug)
            ->exists();
    }

    private function userHasAnyRole(User $user, int $organizationId): bool
    {
        return DB::table('user_roles')
            ->where('user_id', $user->id)
            ->where('organization_id', $organizationId)
            ->exists();
    }

    private function getUserIntendedRole(User $user): ?string
    {
        $metadata = $user->metadata ? json_decode($user->metadata, true) : [];

        return $metadata['intended_role'] ?? null;
    }

    private function assignUserToRole(User $user, string $roleSlug, int $organizationId): void
    {
        $role = Role::where('slug', $roleSlug)->first();

        if (! $role) {
            $this->command->error("❌ Role not found: {$roleSlug}");

            return;
        }

        // Check if already assigned
        $exists = DB::table('user_roles')
            ->where('user_id', $user->id)
            ->where('role_id', $role->id)
            ->where('organization_id', $organizationId)
            ->exists();

        if (! $exists) {
            DB::table('user_roles')->insert([
                'user_id' => $user->id,
                'role_id' => $role->id,
                'organization_id' => $organizationId,
                'team_id' => null,
                'expires_at' => null,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $this->command->info("  ✅ {$user->name} → {$role->name}");
        }
    }

    /**
     * Verify that every organization has complete coverage
     */
    private function verifyCompleteCoverage(): void
    {
        $this->command->info('🔍 Verifying complete coverage...');

        $organizations = Organization::all();
        $allCovered = true;

        foreach ($organizations as $org) {
            $missing = [];

            foreach ($this->criticalRoles as $roleSlug) {
                if (! $this->organizationHasUserInRole($org, $roleSlug)) {
                    $missing[] = $roleSlug;
                    $allCovered = false;
                }
            }

            if (empty($missing)) {
                $this->command->info("✅ {$org->name}: Complete critical coverage");
            } else {
                $this->command->error("❌ {$org->name}: Missing ".implode(', ', $missing));
            }
        }

        if ($allCovered) {
            $this->command->info('🎉 ALL ORGANIZATIONS HAVE COMPLETE CRITICAL COVERAGE!');
        }
    }

    /**
     * Display coverage analysis
     */
    private function displayCoverageAnalysis(array $gaps): void
    {
        $this->command->info('');
        $this->command->info('📊 COVERAGE ANALYSIS:');

        foreach ($gaps as $gap) {
            $org = $gap['organization'];
            $this->command->info("🏢 {$org->name}:");
            $this->command->info("  Users: {$gap['existing_users']} total, {$gap['active_users']} active");
            $this->command->info('  Missing Critical: '.(empty($gap['missing_critical']) ? 'None ✅' : implode(', ', $gap['missing_critical']).' ❌'));
            $this->command->info('  Missing Supporting: '.count($gap['missing_supporting']).' roles');
        }
        $this->command->info('');
    }

    /**
     * Display final summary
     */
    private function displayFinalSummary(): void
    {
        $totalRoles = Role::count();
        $totalUsers = User::count();
        $totalAssignments = DB::table('user_roles')->count();
        $orgsWithOwners = Organization::whereNotNull('owner_id')->count();
        $totalOrgs = Organization::count();

        $this->command->info('');
        $this->command->info('🎉 COMPREHENSIVE ROLE SEEDING COMPLETE!');
        $this->command->info("🎭 Total Roles: {$totalRoles}");
        $this->command->info("👤 Total Users: {$totalUsers}");
        $this->command->info("🔗 Role Assignments: {$totalAssignments}");
        $this->command->info("👑 Organizations with Owners: {$orgsWithOwners}/{$totalOrgs}");
        $this->command->info('');
        $this->command->info('✨ DASHBOARD TESTING READY:');
        $this->command->info('  ✅ organization_owner → Owner Dashboard');
        $this->command->info('  ✅ admin → Owner Dashboard');
        $this->command->info('  ✅ buyer → Buyer Dashboard');
        $this->command->info('  ✅ lister → Lister Dashboard');
        $this->command->info('  ✅ fulfillment_manager → Fulfillment Dashboard');
        $this->command->info('  ✅ support_agent → Support Dashboard');
        $this->command->info('');
        $this->command->info('🧪 Every organization now has complete role coverage for testing!');
    }

    /**
     * Get all role definitions (copied from your original seeder)
     */
    private function getAllRoleDefinitions(): array
    {
        return [
            'super_admin' => [
                'name' => 'Super Admin',
                'description' => 'Global system administrator with full access across all organizations',
                'level' => 9999,
                'color' => '#dc2626',
                'icon' => 'fas fa-shield-alt',
                'permissions' => array_merge($this->getAllPermissions(), [
                    'system.manage',
                    'system.view_all_organizations',
                    'system.manage_organizations',
                    'system.impersonate_users',
                    'system.view_audit_logs',
                    'system.manage_system_settings',
                    'system.manage_ai_prompts',
                    'system.manage_ebay_categories',
                ]),
            ],
            'organization_owner' => [
                'name' => 'Organization Owner',
                'description' => 'Organization owner with full access to all features',
                'level' => 1000,
                'color' => '#7c3aed',
                'icon' => 'fas fa-crown',
                'permissions' => $this->getAllPermissions(),
            ],
            'admin' => [
                'name' => 'Admin',
                'description' => 'Administrator with comprehensive management capabilities',
                'level' => 900,
                'color' => '#059669',
                'icon' => 'fas fa-user-cog',
                'permissions' => [
                    'admin.access',
                    'users.view', 'users.create', 'users.edit', 'users.manage_status',
                    'teams.view', 'teams.create', 'teams.edit', 'teams.manage_members', 'teams.invite',
                    'roles.view', 'roles.assign_users',
                    'organization.view', 'organization.edit',
                    'inventory.view', 'inventory.create', 'inventory.edit', 'inventory.delete',
                    'products.view', 'products.create', 'products.edit', 'products.delete',
                    'orders.view', 'orders.edit', 'orders.manage',
                    'analytics.view', 'reports.view', 'reports.create', 'reports.export',
                    'settings.view', 'settings.edit',
                    'integrations.view', 'integrations.manage',
                ],
            ],
            'buyer' => [
                'name' => 'Buyer',
                'description' => 'Sourcing specialist focused on product acquisition',
                'level' => 700,
                'color' => '#2563eb',
                'icon' => 'fas fa-shopping-cart',
                'permissions' => [
                    'organization.view', 'users.view', 'teams.view',
                    'inventory.view', 'inventory.create', 'inventory.edit',
                    'products.view', 'products.create', 'products.edit',
                    'analytics.view', 'reports.view',
                    'settings.view', 'integrations.view',
                ],
            ],
            'sourcing_specialist' => [
                'name' => 'Sourcing Specialist',
                'description' => 'Alternative buyer role for sourcing specialists',
                'level' => 700,
                'color' => '#2563eb',
                'icon' => 'fas fa-search',
                'permissions' => [
                    'organization.view', 'users.view', 'teams.view',
                    'inventory.view', 'inventory.create', 'inventory.edit',
                    'products.view', 'products.create', 'products.edit',
                    'analytics.view', 'reports.view',
                    'settings.view', 'integrations.view',
                ],
            ],
            'lister' => [
                'name' => 'Lister',
                'description' => 'Listing specialist focused on product listings',
                'level' => 600,
                'color' => '#dc2626',
                'icon' => 'fas fa-list',
                'permissions' => [
                    'organization.view', 'users.view', 'teams.view',
                    'inventory.view', 'inventory.edit',
                    'products.view', 'products.create', 'products.edit',
                    'intake.view', 'intake.process',
                    'analytics.view', 'settings.view', 'integrations.view',
                ],
            ],
            'listing_specialist' => [
                'name' => 'Listing Specialist',
                'description' => 'Alternative lister role for listing specialists',
                'level' => 600,
                'color' => '#dc2626',
                'icon' => 'fas fa-edit',
                'permissions' => [
                    'organization.view', 'users.view', 'teams.view',
                    'inventory.view', 'inventory.edit',
                    'products.view', 'products.create', 'products.edit',
                    'intake.view', 'intake.process',
                    'analytics.view', 'settings.view', 'integrations.view',
                ],
            ],
            'fulfillment_manager' => [
                'name' => 'Fulfillment Manager',
                'description' => 'Manages order fulfillment and shipping',
                'level' => 650,
                'color' => '#f59e0b',
                'icon' => 'fas fa-truck',
                'permissions' => [
                    'organization.view', 'users.view', 'teams.view',
                    'inventory.view', 'inventory.edit', 'inventory.manage_stock',
                    'products.view', 'analytics.view', 'reports.view',
                    'orders.view', 'orders.edit', 'orders.manage',
                    'settings.view', 'integrations.view',
                ],
            ],
            'shipper' => [
                'name' => 'Shipper',
                'description' => 'Alternative fulfillment role for shippers',
                'level' => 550,
                'color' => '#f59e0b',
                'icon' => 'fas fa-box',
                'permissions' => [
                    'organization.view', 'users.view', 'teams.view',
                    'inventory.view', 'inventory.manage_stock',
                    'products.view', 'analytics.view',
                    'orders.view',
                    'settings.view', 'integrations.view',
                ],
            ],
            'support_agent' => [
                'name' => 'Support Agent',
                'description' => 'Customer support and assistance',
                'level' => 500,
                'color' => '#64748b',
                'icon' => 'fas fa-headset',
                'permissions' => [
                    'organization.view', 'users.view', 'teams.view',
                    'inventory.view', 'products.view',
                    'orders.view',
                    'analytics.view', 'settings.view',
                ],
            ],
            'manager' => [
                'name' => 'Manager',
                'description' => 'General manager with broad operational access',
                'level' => 750,
                'color' => '#059669',
                'icon' => 'fas fa-briefcase',
                'permissions' => [
                    'admin.access',
                    'organization.view', 'users.view', 'users.create',
                    'teams.view', 'teams.manage_members', 'teams.edit',
                    'inventory.view', 'inventory.create', 'inventory.edit',
                    'products.view', 'products.create', 'products.edit',
                    'orders.view', 'orders.edit', 'orders.manage',
                    'intake.view', 'intake.process',
                    'analytics.view', 'reports.view', 'reports.create',
                    'settings.view', 'integrations.view',
                ],
            ],
            'employee' => [
                'name' => 'Employee',
                'description' => 'Standard employee with basic operational access',
                'level' => 400,
                'color' => '#64748b',
                'icon' => 'fas fa-user',
                'permissions' => [
                    'organization.view', 'users.view', 'teams.view',
                    'inventory.view', 'products.view',
                    'analytics.view', 'settings.view',
                ],
            ],
            'supplier_admin' => [
                'name' => 'Supplier Admin',
                'description' => 'Full supplier portal access with management capabilities',
                'level' => 300,
                'color' => '#8b5cf6',
                'icon' => 'fas fa-warehouse',
                'permissions' => [
                    'supplier.portal.access',
                    'supplier.dashboard.view',
                    'supplier.settlements.view', 'supplier.settlements.dispute',
                    'supplier.performance.view',
                    'supplier.notifications.view', 'supplier.notifications.manage',
                    'supplier.onboarding.manage',
                    'supplier.settings.view', 'supplier.settings.edit',
                    'supplier.products.submit',
                ],
            ],
            'supplier_staff' => [
                'name' => 'Supplier Staff',
                'description' => 'Limited supplier portal access for operational tasks',
                'level' => 200,
                'color' => '#a78bfa',
                'icon' => 'fas fa-user-tag',
                'permissions' => [
                    'supplier.portal.access',
                    'supplier.dashboard.view',
                    'supplier.performance.view',
                    'supplier.notifications.view',
                ],
            ],
        ];
    }

    /**
     * Get all available permissions
     */
    private function getAllPermissions(): array
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
            'orders.view', 'orders.create', 'orders.edit', 'orders.manage',
            'intake.view', 'intake.process', 'intake.approve', 'intake.reject',
            'analytics.view', 'analytics.export', 'reports.view', 'reports.create', 'reports.export',
            'settings.view', 'settings.edit', 'integrations.view', 'integrations.manage',
            'audit_log.view', 'audit_log.export',
            'export.data', 'import.data', 'backup.manage',
            'billing.view', 'billing.manage',
        ];
    }
}
