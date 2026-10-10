<?php

namespace Tests\Feature\Seeders;

use App\Models\Integration\IntegrationCredential;
use App\Models\Inventory\InventoryLocation;
use App\Models\Inventory\Supplier;
use App\Models\Inventory\WarehouseBin;
use App\Models\Inventory\WarehouseZone;
use App\Models\Organization\Organization;
use App\Models\System\OnboardingProgress;
use App\Models\User\User;
use Database\Seeders\DevelopTestingSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class DevelopTestingSeederTest extends TestCase
{
    use RefreshDatabase;

    protected array $expectedRoles = [
        'super_admin', 'organization_owner', 'admin', 'manager',
        'buyer', 'sourcing_specialist', 'lister', 'listing_specialist',
        'fulfillment_manager', 'shipper', 'support_agent', 'employee',
        'supplier_admin', 'supplier_staff',
    ];

    public function test_it_seeds_org_users_roles_and_inventory_layout(): void
    {
        $this->seed(DevelopTestingSeeder::class);

        $org = Organization::where('slug', DevelopTestingSeeder::ORG_SLUG)->first();
        $this->assertNotNull($org, 'FlipStack Testing org should exist');
        $this->assertSame('active', $org->status);

        // 14 users, one per role
        foreach ($this->expectedRoles as $slug) {
            $email = "{$slug}tester@".DevelopTestingSeeder::EMAIL_DOMAIN;
            $user = User::where('email', $email)->first();
            $this->assertNotNull($user, "user {$email} should exist");
            $this->assertSame($org->id, $user->organization_id);
            $this->assertTrue($user->is_active);
            $this->assertNotNull($user->email_verified_at);
            $this->assertTrue(Hash::check(DevelopTestingSeeder::SHARED_PASSWORD, $user->password));

            // role pivot
            $hasRole = DB::table('user_roles')
                ->join('roles', 'user_roles.role_id', '=', 'roles.id')
                ->where('user_roles.user_id', $user->id)
                ->where('user_roles.organization_id', $org->id)
                ->where('roles.slug', $slug)
                ->exists();
            $this->assertTrue($hasRole, "user {$email} should have role {$slug}");
        }

        // Owner set
        $owner = User::where('email', 'organization_ownertester@'.DevelopTestingSeeder::EMAIL_DOMAIN)->first();
        $this->assertSame($owner->id, $org->fresh()->owner_id);

        // Onboarding completed for non-supplier users
        $nonSupplierUserIds = User::where('organization_id', $org->id)
            ->where('is_supplier', false)
            ->pluck('id');
        $completedCount = OnboardingProgress::whereIn('user_id', $nonSupplierUserIds)
            ->whereNotNull('completed_at')
            ->count();
        $this->assertSame($nonSupplierUserIds->count(), $completedCount);

        // Supplier link
        $supplier = Supplier::where('organization_id', $org->id)->where('code', 'TEST-SUP')->first();
        $this->assertNotNull($supplier);
        foreach (['supplier_admin', 'supplier_staff'] as $slug) {
            $u = User::where('email', "{$slug}tester@".DevelopTestingSeeder::EMAIL_DOMAIN)->first();
            $this->assertTrue($u->is_supplier);
            $this->assertSame($supplier->id, $u->supplier_id);
        }

        // Inventory layout
        $location = InventoryLocation::where('organization_id', $org->id)->where('code', 'WH-MAIN')->first();
        $this->assertNotNull($location);
        $zone = WarehouseZone::where('location_id', $location->id)->where('code', 'A')->first();
        $this->assertNotNull($zone);
        $this->assertSame(3, WarehouseBin::where('zone_id', $zone->id)->count());

        // Integration cred stubs (3 platforms, sandbox env, inactive)
        $creds = IntegrationCredential::where('organization_id', $org->id)
            ->where('environment', 'sandbox')
            ->get();
        $this->assertCount(3, $creds);
        $this->assertSame(['amazon', 'ebay', 'shopify'], $creds->pluck('platform')->sort()->values()->all());
        foreach ($creds as $c) {
            $this->assertFalse((bool) $c->is_active);
        }
    }

    public function test_it_is_idempotent(): void
    {
        $this->seed(DevelopTestingSeeder::class);
        $this->seed(DevelopTestingSeeder::class);

        $email = 'admintester@'.DevelopTestingSeeder::EMAIL_DOMAIN;
        $this->assertSame(1, User::where('email', $email)->count());
        $this->assertSame(1, Organization::where('slug', DevelopTestingSeeder::ORG_SLUG)->count());
    }

    public function test_reset_removes_org_and_users(): void
    {
        $this->seed(DevelopTestingSeeder::class);
        DevelopTestingSeeder::reset();

        $this->assertNull(Organization::where('slug', DevelopTestingSeeder::ORG_SLUG)->first());
        $this->assertSame(0, User::where('email', 'like', '%tester@'.DevelopTestingSeeder::EMAIL_DOMAIN)->count());
    }
}
