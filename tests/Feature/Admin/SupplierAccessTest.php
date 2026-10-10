<?php

namespace Tests\Feature\Admin;

use App\Models\Inventory\Supplier;
use App\Models\Organization\Organization;
use App\Models\User\Role;
use App\Models\User\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithoutMiddleware;
use Tests\TestCase;

/**
 * Verifies the role-based access tiering on the consolidated supplier admin
 * pages. Manager and up can view + edit basic profile; only owner-tier (and
 * above) can mutate Status / Tier / Commission / Settlement.
 */
class SupplierAccessTest extends TestCase
{
    use RefreshDatabase;
    use WithoutMiddleware;

    protected Organization $organization;

    protected function setUp(): void
    {
        parent::setUp();
        $this->organization = Organization::factory()->create();
    }

    protected function makeUserWithRole(string $slug): User
    {
        $role = Role::factory()->create([
            'name' => ucfirst($slug),
            'slug' => $slug,
            'is_system' => true,
            'permissions' => [],
        ]);

        $user = User::factory()->create([
            'organization_id' => $this->organization->id,
        ]);

        $user->roles()->attach($role->id, [
            'organization_id' => $this->organization->id,
            'team_id' => null,
        ]);

        return $user;
    }

    protected function makeSupplier(array $attrs = []): Supplier
    {
        return Supplier::factory()->create(array_merge([
            'organization_id' => $this->organization->id,
        ], $attrs));
    }

    public function test_manager_can_view_supplier_index(): void
    {
        $manager = $this->makeUserWithRole('manager');

        $this->actingAs($manager)
            ->get(route('admin.suppliers.index'))
            ->assertOk();
    }

    public function test_owner_can_view_supplier_index(): void
    {
        $owner = $this->makeUserWithRole('organization_owner');

        $this->actingAs($owner)
            ->get(route('admin.suppliers.index'))
            ->assertOk();
    }

    public function test_lister_cannot_view_supplier_index(): void
    {
        $lister = $this->makeUserWithRole('lister');

        $this->actingAs($lister)
            ->get(route('admin.suppliers.index'))
            ->assertForbidden();
    }

    public function test_buyer_cannot_view_supplier_index(): void
    {
        $buyer = $this->makeUserWithRole('buyer');

        $this->actingAs($buyer)
            ->get(route('admin.suppliers.index'))
            ->assertForbidden();
    }

    public function test_manager_can_save_profile_fields(): void
    {
        $manager = $this->makeUserWithRole('manager');
        $supplier = $this->makeSupplier(['name' => 'Old', 'company_name' => null]);

        $this->actingAs($manager)
            ->put(route('admin.suppliers.update', $supplier->id), [
                'name' => 'Manager Edited',
                'company_name' => 'Real Co',
                'payment_terms' => 30,
            ])
            ->assertRedirect();

        $supplier->refresh();
        $this->assertSame('Manager Edited', $supplier->name);
        $this->assertSame('Real Co', $supplier->company_name);
        $this->assertSame(30, $supplier->payment_terms);
    }

    public function test_manager_cannot_change_tier_via_update_endpoint(): void
    {
        $manager = $this->makeUserWithRole('manager');
        $supplier = $this->makeSupplier(['current_tier_id' => null]);

        $this->actingAs($manager)
            ->put(route('admin.suppliers.update', $supplier->id), [
                'name' => 'Updated',
                'tier_id' => '00000000-0000-0000-0000-000000000001',
            ]);

        $supplier->refresh();
        $this->assertNull($supplier->current_tier_id, 'Manager should not be able to change tier_id via update().');
    }

    public function test_manager_cannot_hit_update_tier_endpoint(): void
    {
        $manager = $this->makeUserWithRole('manager');
        $supplier = $this->makeSupplier();

        $this->actingAs($manager)
            ->patch(route('admin.suppliers.update-tier', $supplier->id), [
                'tier_id' => '00000000-0000-0000-0000-000000000001',
            ])
            ->assertForbidden();
    }

    public function test_manager_cannot_hit_generate_settlement_endpoint(): void
    {
        $manager = $this->makeUserWithRole('manager');
        $supplier = $this->makeSupplier();

        $this->actingAs($manager)
            ->post(route('admin.suppliers.generate-settlement', $supplier->id), [
                'period_start' => now()->subMonth()->toDateString(),
                'period_end' => now()->toDateString(),
            ])
            ->assertForbidden();
    }

    public function test_legacy_inventory_suppliers_url_redirects_to_admin(): void
    {
        $owner = $this->makeUserWithRole('organization_owner');

        $this->actingAs($owner)
            ->get('/inventory/suppliers')
            ->assertRedirect('/admin/suppliers');
    }
}
