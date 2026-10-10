<?php

namespace Database\Seeders;

use App\Models\Organization\Organization;
use App\Models\User\User;
use Faker\Factory as Faker;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    private $faker;

    public function __construct()
    {
        $this->faker = Faker::create();
    }

    /**
     * 👥 SIMPLIFIED USER SEEDER FOR COMPREHENSIVE SYSTEM
     *
     * Creates users with proper metadata but NO role/team assignments.
     * Role assignments are handled by RoleSeeder.
     * Team assignments are handled by TeamSeeder.
     * This prevents conflicts and ensures clean separation of concerns.
     */
    public function run(): void
    {
        $this->command->info('👥 Creating users (role assignment handled by RoleSeeder)...');

        // Step 1: Create foundational users
        $this->createFoundationalUsers();

        // Step 2: Create additional test users for each organization
        $this->createAdditionalUsers();

        // Step 3: Create edge case users for testing
        $this->createEdgeCaseUsers();

        $this->displayCreationSummary();
    }

    /**
     * Create foundational users with clear role intentions
     */
    private function createFoundationalUsers(): void
    {
        $this->command->info('👥 Creating foundational users...');

        $organizations = Organization::all();

        $foundationalUsers = [
            // Super Admin for system-wide access
            [
                'name' => 'Super Admin',
                'email' => 'admin@example.com',
                'intended_role' => 'super_admin',
                'organization_slug' => 'demo-reseller-pro',
                'employee_id' => 'SYS-001',
                'department' => 'System',
                'job_title' => 'System Administrator',
                'hire_date' => '2020-01-01',
                'mfa_enabled' => false,
            ],

            // Demo Reseller Pro Users
            [
                'name' => 'Mike Johnson',
                'email' => 'mike@demoreseller.test',
                'intended_role' => 'organization_owner',
                'organization_slug' => 'demo-reseller-pro',
                'employee_id' => 'NCR-001',
                'department' => 'Executive',
                'job_title' => 'Chief Executive Officer',
                'hire_date' => '2020-01-15',
                'mfa_enabled' => true,
            ],
            [
                'name' => 'Sarah Chen',
                'email' => 'sarah@demoreseller.test',
                'intended_role' => 'manager',
                'organization_slug' => 'demo-reseller-pro',
                'employee_id' => 'NCR-002',
                'department' => 'Operations',
                'job_title' => 'Operations Manager',
                'hire_date' => '2021-03-10',
            ],
            [
                'name' => 'David Rodriguez',
                'email' => 'david@demoreseller.test',
                'intended_role' => 'employee',
                'organization_slug' => 'demo-reseller-pro',
                'employee_id' => 'NCR-003',
                'department' => 'Processing',
                'job_title' => 'Product Processor',
                'hire_date' => '2022-06-01',
            ],

            // TechFlow Solutions Users
            [
                'name' => 'Jennifer Park',
                'email' => 'jennifer@techflow.com',
                'intended_role' => 'organization_owner',
                'organization_slug' => 'techflow-solutions',
                'employee_id' => 'TFS-001',
                'department' => 'Executive',
                'job_title' => 'Founder & CEO',
                'hire_date' => '2019-08-01',
                'mfa_enabled' => true,
            ],
            [
                'name' => 'Robert Kim',
                'email' => 'robert@techflow.com',
                'intended_role' => 'manager',
                'organization_slug' => 'techflow-solutions',
                'employee_id' => 'TFS-002',
                'department' => 'Technology',
                'job_title' => 'Technology Manager',
                'hire_date' => '2020-11-15',
            ],

            // RetailTech Depot Users
            [
                'name' => 'James Thompson',
                'email' => 'james@retailtech.com',
                'intended_role' => 'organization_owner',
                'organization_slug' => 'retailtech-depot',
                'employee_id' => 'RTD-001',
                'department' => 'Executive',
                'job_title' => 'Owner & Technical Director',
                'hire_date' => '2018-12-01',
                'mfa_enabled' => true,
            ],

            // Global Parts Exchange Users
            [
                'name' => 'Alex Turner',
                'email' => 'alex@globalparts.com',
                'intended_role' => 'organization_owner',
                'organization_slug' => 'global-parts-exchange',
                'employee_id' => 'GPE-001',
                'department' => 'Executive',
                'job_title' => 'Managing Director',
                'hire_date' => '2017-04-01',
                'mfa_enabled' => true,
            ],

            // Demo Test Users
            [
                'name' => 'Test Admin',
                'email' => 'admin@test.com',
                'intended_role' => 'organization_owner',
                'organization_slug' => 'demo-test-org',
                'employee_id' => 'TEST-001',
                'department' => 'Testing',
                'job_title' => 'Test Administrator',
                'hire_date' => '2023-01-01',
                'mfa_enabled' => true,
            ],
        ];

        foreach ($foundationalUsers as $userData) {
            $organization = $organizations->where('slug', $userData['organization_slug'])->first();

            if (! $organization) {
                $this->command->warn("⚠️  Organization not found: {$userData['organization_slug']}");

                continue;
            }

            $user = $this->createUserWithData($userData, $organization);
            $this->command->info("✅ Created: {$userData['name']} (intended: {$userData['intended_role']})");
        }
    }

    /**
     * Create additional users for comprehensive testing
     */
    private function createAdditionalUsers(): void
    {
        $this->command->info('🧪 Creating additional test users...');

        $organizations = Organization::where('slug', '!=', 'demo-test-org')->get();

        foreach ($organizations as $org) {
            $this->createOrganizationStaff($org);
        }
    }

    /**
     * Create staff for each organization
     */
    private function createOrganizationStaff(Organization $org): void
    {
        $orgPrefix = strtoupper(str_replace(['-', '_'], '', substr($org->slug, 0, 3)));

        // Create various role types to ensure comprehensive coverage
        $staffTypes = [
            ['role' => 'admin', 'count' => 1, 'title' => 'Administrator'],
            ['role' => 'manager', 'count' => 2, 'title' => 'Manager'],
            ['role' => 'buyer', 'count' => 2, 'title' => 'Buyer'],
            ['role' => 'lister', 'count' => 2, 'title' => 'Lister'],
            ['role' => 'fulfillment_manager', 'count' => 1, 'title' => 'Fulfillment Manager'],
            ['role' => 'support_agent', 'count' => 1, 'title' => 'Support Agent'],
            ['role' => 'employee', 'count' => 3, 'title' => 'Employee'],
        ];

        $employeeCounter = 100;

        foreach ($staffTypes as $staffType) {
            for ($i = 1; $i <= $staffType['count']; $i++) {
                $employeeCounter++;

                $userData = [
                    'name' => $this->faker->name()." ({$staffType['title']})",
                    'email' => $this->generateOrgEmail($org, strtolower($staffType['role']).$i),
                    'intended_role' => $staffType['role'],
                    'organization_slug' => $org->slug,
                    'employee_id' => $orgPrefix.'-'.str_pad($employeeCounter, 3, '0', STR_PAD_LEFT),
                    'department' => $this->mapRoleToDepartment($staffType['role']),
                    'job_title' => $staffType['title'],
                    'hire_date' => $this->faker->dateTimeBetween('-3 years', '-1 month')->format('Y-m-d'),
                    'mfa_enabled' => rand(0, 1),
                ];

                $this->createUserWithData($userData, $org);
            }
        }

        $this->command->info("  ✅ Created staff for {$org->name}");
    }

    /**
     * Create edge case users for testing
     */
    private function createEdgeCaseUsers(): void
    {
        $this->command->info('⚠️  Creating edge case users...');

        $demoOrg = Organization::where('slug', 'demo-test-org')->first();

        if (! $demoOrg) {
            return;
        }

        $edgeCases = [
            [
                'name' => 'Inactive Test User',
                'email' => 'inactive@test.local',
                'intended_role' => 'employee',
                'is_active' => false,
                'employee_id' => 'TEST-INACTIVE',
            ],
            [
                'name' => 'Unverified Email User',
                'email' => 'unverified@test.local',
                'intended_role' => 'employee',
                'email_verified_at' => null,
                'employee_id' => 'TEST-UNVERIFIED',
            ],
            [
                'name' => 'MFA Required User',
                'email' => 'mfa@test.local',
                'intended_role' => 'manager',
                'mfa_enabled' => true,
                'employee_id' => 'TEST-MFA',
            ],
        ];

        foreach ($edgeCases as $userData) {
            $userData['organization_slug'] = $demoOrg->slug;
            $userData['hire_date'] = $userData['hire_date'] ?? '2023-01-01';
            $userData['department'] = $userData['department'] ?? 'Testing';
            $userData['job_title'] = $userData['job_title'] ?? 'Test User';

            $user = $this->createUserWithData($userData, $demoOrg);
            $this->command->info("  ✅ {$userData['name']} (edge case)");
        }
    }

    /**
     * Create a user with the provided data
     */
    private function createUserWithData(array $userData, Organization $organization): User
    {
        $defaults = [
            'email_verified_at' => $userData['email_verified_at'] ?? now(),
            'password' => Hash::make('password'),
            'organization_id' => $organization->id,
            'current_team_id' => null,
            'profile_photo_path' => null,
            'created_at' => now()->subDays(rand(1, 90)),
            'updated_at' => now()->subDays(rand(0, 30)),
            'is_active' => $userData['is_active'] ?? true,
            'mfa_enabled' => $userData['mfa_enabled'] ?? false,
            'last_login_at' => $this->faker->optional(0.8)->dateTimeBetween('-30 days', 'now'),
            'preferences' => json_encode([
                'theme' => $this->faker->randomElement(['light', 'dark']),
                'language' => 'en',
                'timezone' => $this->faker->timezone(),
                'dashboard_layout' => 'default',
            ]),
            'notification_settings' => json_encode([
                'email' => ['enabled' => true, 'frequency' => 'daily'],
                'browser' => ['enabled' => true],
            ]),
        ];

        // Store intended role in metadata for RoleSeeder to use
        $metadata = [
            'intended_role' => $userData['intended_role'],
            'created_by_seeder' => 'user_seeder',
            'is_foundational_user' => isset($userData['employee_id']) && str_contains($userData['employee_id'], '-001'),
        ];

        $userData['metadata'] = json_encode($metadata);

        // Remove non-database fields
        unset($userData['intended_role'], $userData['organization_slug']);

        $finalUserData = array_merge($defaults, $userData);

        return User::create($finalUserData);
    }

    /**
     * Generate organization-specific email
     */
    private function generateOrgEmail(Organization $org, string $prefix): string
    {
        $domain = match ($org->slug) {
            'demo-reseller-pro' => 'demoreseller.test',
            'techflow-solutions' => 'techflow.com',
            'retailtech-depot' => 'retailtech.com',
            'global-parts-exchange' => 'globalparts.com',
            default => str_replace(['-', '_'], '', $org->slug).'.local'
        };

        return $prefix.'@'.$domain;
    }

    /**
     * Map role to department
     */
    private function mapRoleToDepartment(string $role): string
    {
        return match ($role) {
            'super_admin' => 'System',
            'organization_owner' => 'Executive',
            'admin' => 'Administration',
            'manager' => 'Operations',
            'buyer', 'sourcing_specialist' => 'Sourcing',
            'lister', 'listing_specialist' => 'Listing',
            'fulfillment_manager', 'shipper' => 'Fulfillment',
            'support_agent' => 'Customer Service',
            default => 'Operations'
        };
    }

    /**
     * Display creation summary
     */
    private function displayCreationSummary(): void
    {
        $userCount = User::count();

        $this->command->info('');
        $this->command->info('🎉 USER CREATION COMPLETE!');
        $this->command->info("👥 Total Users: {$userCount}");
        $this->command->info('🔑 All users password: password');
        $this->command->info('');
        $this->command->info('📋 Role assignment will be handled by RoleSeeder');
        $this->command->info('🏢 Team assignment will be handled by TeamSeeder');
        $this->command->info('');
        $this->command->info('✅ Users ready for role and team assignment!');
    }
}
