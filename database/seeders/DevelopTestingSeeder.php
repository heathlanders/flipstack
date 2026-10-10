<?php

namespace Database\Seeders;

use App\Enums\Supplier\SupplierStatus;
use App\Models\Integration\IntegrationCredential;
use App\Models\Inventory\InventoryLocation;
use App\Models\Inventory\Supplier;
use App\Models\Inventory\WarehouseBin;
use App\Models\Inventory\WarehouseZone;
use App\Models\Organization\Organization;
use App\Models\Organization\Team;
use App\Models\Supplier\SupplierOnboarding;
use App\Models\System\OnboardingProgress;
use App\Models\User\Role;
use App\Models\User\User;
use App\Services\Amazon\AmazonConfigService;
use App\Services\EbayConfigService;
use App\Services\Shopify\ShopifyConfigService;
use Database\Seeders\Production\SystemRoleSeeder;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;

class DevelopTestingSeeder extends Seeder
{
    public const ORG_NAME = 'FlipStack Testing';

    public const ORG_SLUG = 'flipstack-testing';

    public const ORG_EMAIL = 'qa@flipstack.app';

    public const SHARED_PASSWORD = 'FlipStackQA2026!';

    public const EMAIL_DOMAIN = 'flipstack.app';

    public const SEEDER_TAG = 'develop_testing_seeder';

    /**
     * Roles to seed users for. Order matters: organization_owner first
     * so the org gets an owner_id before other users assign their roles.
     */
    protected array $roleSlugs = [
        'organization_owner',
        'super_admin',
        'admin',
        'manager',
        'buyer',
        'sourcing_specialist',
        'lister',
        'listing_specialist',
        'fulfillment_manager',
        'shipper',
        'support_agent',
        'employee',
        'supplier_admin',
        'supplier_staff',
    ];

    public function run(): void
    {
        Notification::fake();

        $this->command?->info('Ensuring system roles exist...');
        $this->call(SystemRoleSeeder::class);
        $this->ensureSupplierRoles();

        $this->command?->info('Seeding org: '.self::ORG_NAME);
        $org = $this->seedOrganization();

        $supplier = $this->seedSupplier($org);
        $this->seedSupplierOnboarding($supplier, $org);

        $created = [];
        foreach ($this->roleSlugs as $slug) {
            $created[$slug] = $this->seedUserForRole($org, $slug, $supplier);
        }

        // Set org owner to organization_owner user
        $owner = $created['organization_owner'] ?? null;
        if ($owner && $org->owner_id !== $owner->id) {
            $org->update(['owner_id' => $owner->id]);
        }

        $this->seedInventoryLayout($org);
        $this->seedIntegrationStubs($org, $owner);

        $this->printSummary($org, $created);
    }

    /**
     * SystemRoleSeeder does not include supplier portal roles. Make sure
     * supplier_admin/supplier_staff exist as system templates.
     */
    protected function ensureSupplierRoles(): void
    {
        $defs = [
            'supplier_admin' => [
                'name' => 'Supplier Admin',
                'description' => 'Full supplier portal access with management capabilities',
                'level' => 300,
                'color' => '#8b5cf6',
                'icon' => 'fas fa-warehouse',
                'permissions' => [
                    'supplier.portal.access', 'supplier.dashboard.view',
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
                    'supplier.portal.access', 'supplier.dashboard.view',
                    'supplier.performance.view', 'supplier.notifications.view',
                ],
            ],
        ];

        foreach ($defs as $slug => $data) {
            Role::updateOrCreate(
                ['slug' => $slug, 'organization_id' => null],
                array_merge($data, [
                    'is_system' => true,
                    'is_active' => true,
                    'is_default' => false,
                ])
            );
        }
    }

    protected function seedOrganization(): Organization
    {
        return Organization::updateOrCreate(
            ['slug' => self::ORG_SLUG],
            [
                'name' => self::ORG_NAME,
                'subdomain' => self::ORG_SLUG,
                'email' => self::ORG_EMAIL,
                'description' => 'QA testing playground org for develop.flipstack.app',
                'timezone' => 'UTC',
                'currency' => 'USD',
                'status' => 'active',
                'trial_ends_at' => null,
                'settings' => (new Organization)->getDefaultSettings(),
            ]
        );
    }

    protected function seedSupplier(Organization $org): Supplier
    {
        return Supplier::updateOrCreate(
            ['organization_id' => $org->id, 'code' => 'TEST-SUP'],
            [
                'name' => 'Test Supplier Co',
                'email' => 'supplier@flipstack.app',
                'currency' => 'USD',
                'payment_terms' => 30,
                'lead_time_days' => 7,
                'country' => 'US',
                'supplier_status' => SupplierStatus::ACTIVE,
                'status_changed_at' => now(),
                'onboarding_complete' => true,
                'onboarding_completed_at' => now(),
            ]
        );
    }

    protected function seedSupplierOnboarding(Supplier $supplier, Organization $org): void
    {
        SupplierOnboarding::updateOrCreate(
            ['supplier_id' => $supplier->id],
            [
                'organization_id' => $org->id,
                'status' => 'completed',
                'step1_application_complete' => true,
                'step1_completed_at' => now(),
                'step2_documentation_complete' => true,
                'step2_completed_at' => now(),
                'business_license_verified' => true,
                'tax_documents_verified' => true,
                'insurance_verified' => true,
                'identity_verified' => true,
                'step3_commission_complete' => true,
                'step3_completed_at' => now(),
                'settlement_preference' => 'monthly',
                'step4_portal_complete' => true,
                'step4_completed_at' => now(),
                'portal_credentials_sent' => true,
                'portal_first_login' => now(),
                'tutorial_completed' => true,
                'step5_submission_complete' => true,
                'step5_completed_at' => now(),
                'submission_approved' => true,
                'current_step' => 5,
                'completion_percentage' => 100,
                'started_at' => now(),
                'completed_at' => now(),
            ]
        );
    }

    protected function seedUserForRole(Organization $org, string $slug, Supplier $supplier): User
    {
        $email = "{$slug}tester@".self::EMAIL_DOMAIN;
        $isSupplier = in_array($slug, ['supplier_admin', 'supplier_staff'], true);

        $user = User::withTrashed()->where('email', $email)->first();

        $attrs = [
            'name' => $this->displayName($slug),
            'organization_id' => $org->id,
            'job_title' => $this->jobTitle($slug),
            'is_active' => true,
            'email_verified_at' => now(),
            'password_change_required' => false,
            'preferences' => [],
            'permissions' => [],
            'notification_settings' => [],
            'metadata' => json_encode([
                self::SEEDER_TAG => true,
                'intended_role' => $slug,
            ]),
        ];

        if ($isSupplier) {
            $attrs['is_supplier'] = true;
            $attrs['supplier_id'] = $supplier->id;
            $attrs['supplier_status'] = 'active';
        }

        if ($user) {
            if ($user->trashed()) {
                $user->restore();
            }
            $user->fill($attrs);
            $user->password = Hash::make(self::SHARED_PASSWORD);
            $user->save();
        } else {
            $user = User::create(array_merge($attrs, [
                'email' => $email,
                'password' => Hash::make(self::SHARED_PASSWORD),
            ]));
        }

        // Personal team (non-supplier users use team-aware features)
        if (! $isSupplier && ! $user->current_team_id) {
            $team = Team::firstOrCreate(
                ['user_id' => $user->id, 'organization_id' => $org->id, 'personal_team' => true],
                ['name' => $user->name."'s Team"]
            );
            $user->update(['current_team_id' => $team->id]);
        }

        // Role pivot
        $role = Role::where('slug', $slug)->whereNull('organization_id')->first();
        if ($role) {
            $exists = DB::table('user_roles')
                ->where('user_id', $user->id)
                ->where('role_id', $role->id)
                ->where('organization_id', $org->id)
                ->exists();
            if (! $exists) {
                DB::table('user_roles')->insert([
                    'user_id' => $user->id,
                    'role_id' => $role->id,
                    'organization_id' => $org->id,
                    'team_id' => null,
                    'expires_at' => null,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }

        // Skip onboarding wizard
        if (! $isSupplier) {
            OnboardingProgress::updateOrCreate(
                ['user_id' => $user->id, 'organization_id' => $org->id],
                [
                    'steps_completed' => ['business_info', 'inventory_setup', 'integrations', 'team_setup'],
                    'current_step' => 'completed',
                    'started_at' => now(),
                    'completed_at' => now(),
                    'completion_time_minutes' => 0,
                    'metadata' => [self::SEEDER_TAG => true],
                ]
            );
        }

        return $user->fresh();
    }

    protected function seedInventoryLayout(Organization $org): void
    {
        $location = InventoryLocation::updateOrCreate(
            ['organization_id' => $org->id, 'code' => 'WH-MAIN'],
            [
                'name' => 'Main Warehouse',
                'type' => 'warehouse',
                'is_active' => true,
                'country' => 'US',
            ]
        );

        $zone = WarehouseZone::updateOrCreate(
            ['location_id' => $location->id, 'code' => 'A'],
            [
                'organization_id' => $org->id,
                'name' => 'Zone A',
                'type' => 'storage',
                'is_active' => true,
            ]
        );

        foreach (['A-01', 'A-02', 'A-03'] as $binCode) {
            WarehouseBin::updateOrCreate(
                ['zone_id' => $zone->id, 'code' => $binCode],
                [
                    'label' => "Bin {$binCode}",
                    'is_active' => true,
                    'barcode' => "BIN-{$binCode}",
                ]
            );
        }
    }

    protected function seedIntegrationStubs(Organization $org, ?User $owner): void
    {
        if (! $owner || ! $owner->current_team_id) {
            return;
        }

        $platforms = [
            'ebay' => [
                'name' => 'eBay Sandbox',
                'client_id' => app(EbayConfigService::class)->getClientId(),
            ],
            'shopify' => [
                'name' => 'Shopify Dev',
                'client_id' => app(ShopifyConfigService::class)->getApiKey(),
            ],
            'amazon' => [
                'name' => 'Amazon Sandbox',
                'client_id' => app(AmazonConfigService::class)->getLwaClientId(),
            ],
        ];

        foreach ($platforms as $platform => $meta) {
            IntegrationCredential::updateOrCreate(
                [
                    'team_id' => $owner->current_team_id,
                    'platform' => $platform,
                    'environment' => 'sandbox',
                ],
                [
                    'organization_id' => $org->id,
                    'name' => $meta['name'],
                    'client_id' => $meta['client_id'] ?: 'PLACEHOLDER_CLIENT_ID',
                    'client_secret' => 'PLACEHOLDER_CLIENT_SECRET',
                    'is_active' => false,
                    'config' => ['seeded_by' => self::SEEDER_TAG, 'placeholder' => true],
                ]
            );
        }
    }

    protected function displayName(string $slug): string
    {
        return ucwords(str_replace('_', ' ', $slug)).' Tester';
    }

    protected function jobTitle(string $slug): string
    {
        return [
            'super_admin' => 'System Administrator',
            'organization_owner' => 'CEO',
            'admin' => 'Administrator',
            'manager' => 'Operations Manager',
            'buyer' => 'Buyer',
            'sourcing_specialist' => 'Sourcing Specialist',
            'lister' => 'Product Lister',
            'listing_specialist' => 'Listing Specialist',
            'fulfillment_manager' => 'Fulfillment Manager',
            'shipper' => 'Shipping Coordinator',
            'support_agent' => 'Support Agent',
            'employee' => 'Employee',
            'supplier_admin' => 'Supplier Administrator',
            'supplier_staff' => 'Supplier Staff',
        ][$slug] ?? 'Tester';
    }

    public static function reset(): void
    {
        DB::transaction(function () {
            $org = Organization::where('slug', self::ORG_SLUG)->first();
            if (! $org) {
                return;
            }

            $userIds = User::withTrashed()
                ->where('organization_id', $org->id)
                ->where('email', 'like', '%tester@'.self::EMAIL_DOMAIN)
                ->pluck('id');

            DB::table('user_roles')->whereIn('user_id', $userIds)->delete();
            OnboardingProgress::whereIn('user_id', $userIds)->delete();
            Team::whereIn('user_id', $userIds)->delete();
            User::withTrashed()->whereIn('id', $userIds)->forceDelete();

            // Tear down inventory layout + integrations + supplier scoped to this org
            $locationIds = InventoryLocation::where('organization_id', $org->id)->pluck('id');
            $zoneIds = WarehouseZone::whereIn('location_id', $locationIds)->pluck('id');
            WarehouseBin::whereIn('zone_id', $zoneIds)->delete();
            WarehouseZone::whereIn('id', $zoneIds)->delete();
            InventoryLocation::whereIn('id', $locationIds)->delete();

            IntegrationCredential::where('organization_id', $org->id)->delete();

            $supplierIds = Supplier::withTrashed()->where('organization_id', $org->id)->pluck('id');
            SupplierOnboarding::whereIn('supplier_id', $supplierIds)->delete();
            Supplier::withTrashed()->whereIn('id', $supplierIds)->forceDelete();

            $org->update(['owner_id' => null]);
            $org->forceDelete();
        });
    }

    protected function printSummary(Organization $org, array $created): void
    {
        $cmd = $this->command;
        if (! $cmd) {
            return;
        }

        $cmd->newLine();
        $cmd->info('=== FlipStack Testing Seeder Complete ===');
        $cmd->info("Org: {$org->name} (id={$org->id}, slug={$org->slug})");
        $cmd->info('Shared password: '.self::SHARED_PASSWORD);
        $cmd->newLine();

        $rows = [];
        foreach ($created as $slug => $user) {
            $rows[] = [$slug, $user->email, $user->name];
        }
        $cmd->table(['Role', 'Email', 'Name'], $rows);
    }
}
