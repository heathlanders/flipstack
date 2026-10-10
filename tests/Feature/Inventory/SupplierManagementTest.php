<?php

namespace Tests\Feature\Inventory;

use App\Models\Inventory\Supplier;
use App\Models\Organization\Organization;
use App\Models\User\Role;
use App\Models\User\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithoutMiddleware;
use Tests\TestCase;

class SupplierManagementTest extends TestCase
{
    use RefreshDatabase;
    use WithoutMiddleware;

    protected Organization $organization;

    protected User $user;

    protected Role $adminRole;

    protected function setUp(): void
    {
        parent::setUp();

        $this->organization = Organization::factory()->create();

        $this->adminRole = Role::factory()->create([
            'name' => 'Admin',
            'slug' => 'admin',
            'is_system' => true,
            'permissions' => [
                'suppliers.view', 'suppliers.create', 'suppliers.edit', 'suppliers.delete',
                'suppliers.manage', 'inventory.view',
            ],
        ]);

        $this->user = User::factory()->create([
            'organization_id' => $this->organization->id,
        ]);

        $this->user->roles()->attach($this->adminRole->id, [
            'organization_id' => $this->organization->id,
            'team_id' => null,
        ]);
    }

    protected function createSupplier(array $attributes = []): Supplier
    {
        return Supplier::factory()->create(array_merge([
            'organization_id' => $this->organization->id,
            'created_by' => $this->user->id,
        ], $attributes));
    }

    public function test_supplier_has_uuid_primary_key(): void
    {
        $supplier = $this->createSupplier();

        $this->assertIsString($supplier->id);
        $this->assertEquals(36, strlen($supplier->id));
    }

    public function test_supplier_has_organization_relationship(): void
    {
        $supplier = $this->createSupplier();

        $this->assertInstanceOf(Organization::class, $supplier->organization);
        $this->assertEquals($this->organization->id, $supplier->organization->id);
    }

    public function test_supplier_has_creator_relationship(): void
    {
        $supplier = $this->createSupplier();

        $this->assertInstanceOf(User::class, $supplier->creator);
        $this->assertEquals($this->user->id, $supplier->creator->id);
    }

    public function test_supplier_active_scope(): void
    {
        $activeSupplier = $this->createSupplier(['is_active' => true]);
        $inactiveSupplier = $this->createSupplier(['is_active' => false]);

        $activeSuppliers = Supplier::active()->get();

        $this->assertTrue($activeSuppliers->contains($activeSupplier));
        $this->assertFalse($activeSuppliers->contains($inactiveSupplier));
    }

    public function test_supplier_preferred_scope(): void
    {
        $preferredSupplier = $this->createSupplier(['is_preferred' => true]);
        $regularSupplier = $this->createSupplier(['is_preferred' => false]);

        $preferredSuppliers = Supplier::preferred()->get();

        $this->assertTrue($preferredSuppliers->contains($preferredSupplier));
        $this->assertFalse($preferredSuppliers->contains($regularSupplier));
    }

    public function test_supplier_by_performance_scope(): void
    {
        $highPerformance = $this->createSupplier(['performance_rating' => 5.0]);
        $lowPerformance = $this->createSupplier(['performance_rating' => 3.0]);

        $suppliers = Supplier::byPerformance()->get();

        $this->assertEquals($highPerformance->id, $suppliers->first()->id);
    }

    public function test_supplier_portal_enabled_scope(): void
    {
        $portalEnabled = $this->createSupplier(['portal_enabled' => true]);
        $portalDisabled = $this->createSupplier(['portal_enabled' => false]);

        $enabledSuppliers = Supplier::portalEnabled()->get();

        $this->assertTrue($enabledSuppliers->contains($portalEnabled));
        $this->assertFalse($enabledSuppliers->contains($portalDisabled));
    }

    public function test_supplier_full_address_attribute(): void
    {
        $supplier = $this->createSupplier([
            'address_line1' => '789 Supplier Ave',
            'address_line2' => 'Building B',
            'city' => 'Chicago',
            'state' => 'IL',
            'postal_code' => '60601',
            'country' => 'US',
        ]);

        $fullAddress = $supplier->full_address;

        $this->assertStringContainsString('789 Supplier Ave', $fullAddress);
        $this->assertStringContainsString('Chicago', $fullAddress);
        $this->assertStringContainsString('IL', $fullAddress);
    }

    public function test_supplier_display_name_attribute(): void
    {
        $supplierWithCompany = $this->createSupplier([
            'name' => 'John Supplier',
            'company_name' => 'ABC Company',
        ]);

        $supplierWithoutCompany = $this->createSupplier([
            'name' => 'Jane Supplier',
            'company_name' => null,
        ]);

        $this->assertEquals('ABC Company', $supplierWithCompany->display_name);
        $this->assertEquals('Jane Supplier', $supplierWithoutCompany->display_name);
    }

    public function test_supplier_has_name(): void
    {
        $supplier = $this->createSupplier(['name' => 'Test Supplier']);

        $this->assertEquals('Test Supplier', $supplier->name);
    }

    public function test_supplier_has_company_name(): void
    {
        $supplier = $this->createSupplier(['company_name' => 'Test Company Inc']);

        $this->assertEquals('Test Company Inc', $supplier->company_name);
    }

    public function test_supplier_has_business_type(): void
    {
        $supplier = $this->createSupplier(['business_type' => 'manufacturer']);

        $this->assertEquals('manufacturer', $supplier->business_type);
    }

    public function test_supplier_has_contact_info(): void
    {
        $supplier = $this->createSupplier([
            'contact_name' => 'Contact Person',
            'email' => 'contact@supplier.com',
            'phone' => '555-9876',
        ]);

        $this->assertEquals('Contact Person', $supplier->contact_name);
        $this->assertEquals('contact@supplier.com', $supplier->email);
        $this->assertEquals('555-9876', $supplier->phone);
    }

    public function test_supplier_has_code(): void
    {
        $supplier = $this->createSupplier(['code' => 'SUP-001']);

        $this->assertEquals('SUP-001', $supplier->code);
    }

    public function test_supplier_has_payment_terms(): void
    {
        $supplier = $this->createSupplier(['payment_terms' => 30]);

        $this->assertEquals(30, $supplier->payment_terms);
    }

    public function test_supplier_has_currency(): void
    {
        $supplier = $this->createSupplier(['currency' => 'USD']);

        $this->assertEquals('USD', $supplier->currency);
    }

    public function test_supplier_has_minimum_order_amount(): void
    {
        $supplier = $this->createSupplier(['minimum_order_amount' => 500.00]);

        $this->assertEquals(500.00, $supplier->minimum_order_amount);
    }

    public function test_supplier_has_lead_time_days(): void
    {
        $supplier = $this->createSupplier(['lead_time_days' => 7]);

        $this->assertEquals(7, $supplier->lead_time_days);
    }

    public function test_supplier_has_performance_rating(): void
    {
        $supplier = $this->createSupplier(['performance_rating' => 4.5]);

        $this->assertEquals(4.5, $supplier->performance_rating);
    }

    public function test_supplier_has_total_orders(): void
    {
        $supplier = $this->createSupplier(['total_orders' => 100]);

        $this->assertEquals(100, $supplier->total_orders);
    }

    public function test_supplier_has_on_time_delivery_rate(): void
    {
        $supplier = $this->createSupplier(['on_time_delivery_rate' => 0.95]);

        $this->assertEquals(0.95, $supplier->on_time_delivery_rate);
    }

    public function test_supplier_is_active_cast(): void
    {
        $activeSupplier = $this->createSupplier(['is_active' => true]);
        $inactiveSupplier = $this->createSupplier(['is_active' => false]);

        $this->assertTrue($activeSupplier->is_active);
        $this->assertFalse($inactiveSupplier->is_active);
    }

    public function test_supplier_is_preferred_cast(): void
    {
        $preferredSupplier = $this->createSupplier(['is_preferred' => true]);
        $regularSupplier = $this->createSupplier(['is_preferred' => false]);

        $this->assertTrue($preferredSupplier->is_preferred);
        $this->assertFalse($regularSupplier->is_preferred);
    }

    public function test_supplier_portal_enabled_cast(): void
    {
        $portalEnabled = $this->createSupplier(['portal_enabled' => true]);
        $portalDisabled = $this->createSupplier(['portal_enabled' => false]);

        $this->assertTrue($portalEnabled->portal_enabled);
        $this->assertFalse($portalDisabled->portal_enabled);
    }

    public function test_supplier_onboarding_complete_cast(): void
    {
        $complete = $this->createSupplier(['onboarding_complete' => true]);
        $incomplete = $this->createSupplier(['onboarding_complete' => false]);

        $this->assertTrue($complete->onboarding_complete);
        $this->assertFalse($incomplete->onboarding_complete);
    }

    public function test_supplier_uses_soft_deletes(): void
    {
        $supplier = $this->createSupplier();
        $supplierId = $supplier->id;

        $supplier->delete();

        $this->assertNull(Supplier::find($supplierId));
        $this->assertNotNull(Supplier::withTrashed()->find($supplierId));
    }

    public function test_supplier_can_be_force_deleted(): void
    {
        $supplier = $this->createSupplier();
        $supplierId = $supplier->id;

        $supplier->forceDelete();

        $this->assertNull(Supplier::withTrashed()->find($supplierId));
    }

    public function test_supplier_can_be_restored(): void
    {
        $supplier = $this->createSupplier();
        $supplierId = $supplier->id;

        $supplier->delete();
        $this->assertNull(Supplier::find($supplierId));

        Supplier::withTrashed()->find($supplierId)->restore();
        $this->assertNotNull(Supplier::find($supplierId));
    }

    public function test_supplier_factory_creates_valid_supplier(): void
    {
        $supplier = Supplier::factory()->create([
            'organization_id' => $this->organization->id,
            'created_by' => $this->user->id,
        ]);

        $this->assertNotNull($supplier->id);
        $this->assertNotNull($supplier->name);
        $this->assertNotNull($supplier->code);
        $this->assertNotNull($supplier->email);
    }

    public function test_supplier_factory_active_state(): void
    {
        $supplier = Supplier::factory()->active()->create([
            'organization_id' => $this->organization->id,
            'created_by' => $this->user->id,
        ]);

        $this->assertTrue($supplier->is_active);
    }

    public function test_supplier_factory_inactive_state(): void
    {
        $supplier = Supplier::factory()->inactive()->create([
            'organization_id' => $this->organization->id,
            'created_by' => $this->user->id,
        ]);

        $this->assertFalse($supplier->is_active);
    }

    public function test_supplier_factory_preferred_state(): void
    {
        $supplier = Supplier::factory()->preferred()->create([
            'organization_id' => $this->organization->id,
            'created_by' => $this->user->id,
        ]);

        $this->assertTrue($supplier->is_preferred);
    }

    public function test_supplier_factory_with_portal_access_state(): void
    {
        $supplier = Supplier::factory()->withPortalAccess()->create([
            'organization_id' => $this->organization->id,
            'created_by' => $this->user->id,
        ]);

        $this->assertTrue($supplier->portal_enabled);
        $this->assertNotNull($supplier->portal_activated_at);
    }

    public function test_supplier_factory_onboarding_state(): void
    {
        $supplier = Supplier::factory()->onboarding()->create([
            'organization_id' => $this->organization->id,
            'created_by' => $this->user->id,
        ]);

        $this->assertFalse($supplier->onboarding_complete);
    }

    public function test_supplier_fillable_attributes(): void
    {
        $supplier = $this->createSupplier([
            'name' => 'Test Supplier Name',
            'company_name' => 'Test Company',
            'business_type' => 'wholesaler',
            'contact_name' => 'Test Contact',
            'code' => 'TST-001',
            'email' => 'test@test.com',
            'currency' => 'EUR',
            'payment_terms' => 45,
        ]);

        $this->assertEquals('Test Supplier Name', $supplier->name);
        $this->assertEquals('Test Company', $supplier->company_name);
        $this->assertEquals('wholesaler', $supplier->business_type);
        $this->assertEquals('Test Contact', $supplier->contact_name);
        $this->assertEquals('TST-001', $supplier->code);
        $this->assertEquals('test@test.com', $supplier->email);
        $this->assertEquals('EUR', $supplier->currency);
        $this->assertEquals(45, $supplier->payment_terms);
    }

    public function test_multiple_suppliers_per_organization(): void
    {
        $suppliers = [];
        for ($i = 0; $i < 5; $i++) {
            $suppliers[] = $this->createSupplier(['name' => "Supplier $i"]);
        }

        $orgSuppliers = Supplier::where('organization_id', $this->organization->id)->get();

        $this->assertCount(5, $orgSuppliers);
    }

    public function test_supplier_belongs_to_organization_scope(): void
    {
        $otherOrg = Organization::factory()->create();
        $otherUser = User::factory()->create(['organization_id' => $otherOrg->id]);

        $mySupplier = $this->createSupplier();
        $otherSupplier = Supplier::factory()->create([
            'organization_id' => $otherOrg->id,
            'created_by' => $otherUser->id,
        ]);

        $orgSuppliers = Supplier::where('organization_id', $this->organization->id)->get();

        $this->assertTrue($orgSuppliers->contains($mySupplier));
        $this->assertFalse($orgSuppliers->contains($otherSupplier));
    }

    public function test_supplier_business_types_are_valid(): void
    {
        $types = ['manufacturer', 'distributor', 'wholesaler', 'dropshipper'];

        foreach ($types as $type) {
            $supplier = $this->createSupplier(['business_type' => $type]);
            $this->assertEquals($type, $supplier->business_type);
        }
    }

    public function test_get_effective_commission_rate_method(): void
    {
        $supplier = $this->createSupplier(['custom_commission_rate' => 15.00]);

        $rate = $supplier->getEffectiveCommissionRate();

        $this->assertEquals(15.00, $rate);
    }

    public function test_get_effective_commission_rate_without_custom_rate(): void
    {
        $supplier = $this->createSupplier(['custom_commission_rate' => null]);

        $rate = $supplier->getEffectiveCommissionRate();

        $this->assertIsFloat($rate);
    }
}
