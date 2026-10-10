<?php

namespace Tests\Feature\Inventory;

use App\Models\Catalog\Category;
use App\Models\Catalog\Product;
use App\Models\Inventory\InventoryLocation;
use App\Models\Organization\Organization;
use App\Models\User\Role;
use App\Models\User\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithoutMiddleware;
use Tests\TestCase;

class InventoryOperationsTest extends TestCase
{
    use RefreshDatabase;
    use WithoutMiddleware;

    protected Organization $organization;

    protected User $user;

    protected Role $adminRole;

    protected Category $category;

    protected function setUp(): void
    {
        parent::setUp();

        $this->organization = Organization::factory()->create();

        $this->adminRole = Role::factory()->create([
            'name' => 'Admin',
            'slug' => 'admin',
            'is_system' => true,
            'permissions' => [
                'inventory.view', 'inventory.create', 'inventory.update', 'inventory.delete',
                'inventory.export', 'inventory.import', 'inventory.transfer',
                'locations.view', 'locations.create', 'locations.edit', 'locations.delete',
            ],
        ]);

        $this->user = User::factory()->create([
            'organization_id' => $this->organization->id,
        ]);

        $this->user->roles()->attach($this->adminRole->id, [
            'organization_id' => $this->organization->id,
            'team_id' => null,
        ]);

        $this->category = Category::factory()->create([
            'organization_id' => $this->organization->id,
        ]);
    }

    protected function createLocation(array $attributes = []): InventoryLocation
    {
        return InventoryLocation::factory()->create(array_merge([
            'organization_id' => $this->organization->id,
        ], $attributes));
    }

    protected function createProduct(array $attributes = []): Product
    {
        return Product::factory()->create(array_merge([
            'organization_id' => $this->organization->id,
            'category_id' => $this->category->id,
            'created_by' => $this->user->id,
        ], $attributes));
    }

    // InventoryLocation Model Tests
    public function test_location_has_uuid_primary_key(): void
    {
        $location = $this->createLocation();

        $this->assertIsString($location->id);
        $this->assertEquals(36, strlen($location->id));
    }

    public function test_location_has_organization_relationship(): void
    {
        $location = $this->createLocation();

        $this->assertInstanceOf(Organization::class, $location->organization);
        $this->assertEquals($this->organization->id, $location->organization->id);
    }

    public function test_location_active_scope(): void
    {
        $activeLocation = $this->createLocation(['is_active' => true]);
        $inactiveLocation = $this->createLocation(['is_active' => false]);

        $activeLocations = InventoryLocation::active()->get();

        $this->assertTrue($activeLocations->contains($activeLocation));
        $this->assertFalse($activeLocations->contains($inactiveLocation));
    }

    public function test_location_default_scope(): void
    {
        $defaultLocation = $this->createLocation(['is_default' => true]);
        $nonDefaultLocation = $this->createLocation(['is_default' => false]);

        $defaultLocations = InventoryLocation::default()->get();

        $this->assertTrue($defaultLocations->contains($defaultLocation));
        $this->assertFalse($defaultLocations->contains($nonDefaultLocation));
    }

    public function test_location_by_type_scope(): void
    {
        $warehouse = $this->createLocation(['type' => 'warehouse']);
        $store = $this->createLocation(['type' => 'store']);

        $warehouses = InventoryLocation::byType('warehouse')->get();

        $this->assertTrue($warehouses->contains($warehouse));
        $this->assertFalse($warehouses->contains($store));
    }

    public function test_location_full_address_attribute(): void
    {
        $location = $this->createLocation([
            'address_line1' => '123 Main St',
            'address_line2' => 'Suite 100',
            'city' => 'New York',
            'state' => 'NY',
            'postal_code' => '10001',
            'country' => 'US',
        ]);

        $fullAddress = $location->full_address;

        $this->assertStringContainsString('123 Main St', $fullAddress);
        $this->assertStringContainsString('New York', $fullAddress);
        $this->assertStringContainsString('NY', $fullAddress);
    }

    public function test_location_has_name(): void
    {
        $location = $this->createLocation(['name' => 'Main Warehouse']);

        $this->assertEquals('Main Warehouse', $location->name);
    }

    public function test_location_has_code(): void
    {
        $location = $this->createLocation(['code' => 'WH-001']);

        $this->assertEquals('WH-001', $location->code);
    }

    public function test_location_has_type(): void
    {
        $location = $this->createLocation(['type' => 'supplier']);

        $this->assertEquals('supplier', $location->type);
    }

    public function test_location_has_contact_info(): void
    {
        $location = $this->createLocation([
            'contact_person' => 'John Doe',
            'email' => 'john@example.com',
            'phone' => '555-1234',
        ]);

        $this->assertEquals('John Doe', $location->contact_person);
        $this->assertEquals('john@example.com', $location->email);
        $this->assertEquals('555-1234', $location->phone);
    }

    public function test_location_is_active_cast(): void
    {
        $activeLocation = $this->createLocation(['is_active' => true]);
        $inactiveLocation = $this->createLocation(['is_active' => false]);

        $this->assertTrue($activeLocation->is_active);
        $this->assertFalse($inactiveLocation->is_active);
    }

    public function test_location_is_default_cast(): void
    {
        $defaultLocation = $this->createLocation(['is_default' => true]);
        $nonDefaultLocation = $this->createLocation(['is_default' => false]);

        $this->assertTrue($defaultLocation->is_default);
        $this->assertFalse($nonDefaultLocation->is_default);
    }

    public function test_location_factory_creates_valid_location(): void
    {
        $location = InventoryLocation::factory()->create([
            'organization_id' => $this->organization->id,
        ]);

        $this->assertNotNull($location->id);
        $this->assertNotNull($location->name);
        $this->assertNotNull($location->code);
        $this->assertNotNull($location->type);
    }

    public function test_location_factory_active_state(): void
    {
        $location = InventoryLocation::factory()->active()->create([
            'organization_id' => $this->organization->id,
        ]);

        $this->assertTrue($location->is_active);
    }

    public function test_location_factory_inactive_state(): void
    {
        $location = InventoryLocation::factory()->inactive()->create([
            'organization_id' => $this->organization->id,
        ]);

        $this->assertFalse($location->is_active);
    }

    public function test_location_factory_default_state(): void
    {
        $location = InventoryLocation::factory()->default()->create([
            'organization_id' => $this->organization->id,
        ]);

        $this->assertTrue($location->is_default);
    }

    public function test_location_factory_warehouse_state(): void
    {
        $location = InventoryLocation::factory()->warehouse()->create([
            'organization_id' => $this->organization->id,
        ]);

        $this->assertEquals('warehouse', $location->type);
    }

    public function test_location_factory_store_state(): void
    {
        $location = InventoryLocation::factory()->storeType()->create([
            'organization_id' => $this->organization->id,
        ]);

        $this->assertEquals('store', $location->type);
    }

    public function test_location_fillable_attributes(): void
    {
        $location = $this->createLocation([
            'name' => 'Test Location',
            'code' => 'TST-001',
            'type' => 'virtual',
            'address_line1' => '456 Test St',
            'city' => 'Test City',
            'state' => 'TC',
            'postal_code' => '12345',
            'country' => 'US',
            'is_active' => true,
            'is_default' => false,
        ]);

        $this->assertEquals('Test Location', $location->name);
        $this->assertEquals('TST-001', $location->code);
        $this->assertEquals('virtual', $location->type);
        $this->assertEquals('456 Test St', $location->address_line1);
        $this->assertEquals('Test City', $location->city);
    }

    public function test_location_can_be_deleted(): void
    {
        $location = $this->createLocation();
        $locationId = $location->id;

        $location->delete();

        $this->assertNull(InventoryLocation::find($locationId));
    }

    public function test_multiple_locations_per_organization(): void
    {
        $locations = [];
        for ($i = 0; $i < 3; $i++) {
            $locations[] = $this->createLocation(['name' => "Location $i"]);
        }

        $orgLocations = InventoryLocation::where('organization_id', $this->organization->id)->get();

        $this->assertCount(3, $orgLocations);
    }

    public function test_location_belongs_to_organization_scope(): void
    {
        $otherOrg = Organization::factory()->create();

        $myLocation = $this->createLocation();
        $otherLocation = InventoryLocation::factory()->create([
            'organization_id' => $otherOrg->id,
        ]);

        $orgLocations = InventoryLocation::where('organization_id', $this->organization->id)->get();

        $this->assertTrue($orgLocations->contains($myLocation));
        $this->assertFalse($orgLocations->contains($otherLocation));
    }

    public function test_location_types_are_valid(): void
    {
        $types = ['warehouse', 'store', 'supplier', 'customer', 'virtual'];

        foreach ($types as $type) {
            $location = $this->createLocation(['type' => $type]);
            $this->assertEquals($type, $location->type);
        }
    }

    public function test_get_total_inventory_value_method(): void
    {
        $location = $this->createLocation();

        // The method should return a float even with no inventory
        $value = $location->getTotalInventoryValue();

        $this->assertIsFloat($value);
    }

    public function test_get_total_product_count_method(): void
    {
        $location = $this->createLocation();

        // The method should return an int even with no inventory
        $count = $location->getTotalProductCount();

        $this->assertIsInt($count);
    }
}
