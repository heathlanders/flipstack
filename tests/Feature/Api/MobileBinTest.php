<?php

namespace Tests\Feature\Api;

use App\Models\Catalog\Category;
use App\Models\Catalog\Product;
use App\Models\Inventory\InventoryLevel;
use App\Models\Inventory\InventoryLocation;
use App\Models\Inventory\WarehouseBin;
use App\Models\Inventory\WarehouseZone;
use App\Models\Organization\Organization;
use App\Models\User\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\Support\AssertsAudits;
use Tests\TestCase;

class MobileBinTest extends TestCase
{
    use AssertsAudits;
    use RefreshDatabase;

    protected Organization $organization;

    protected User $user;

    protected InventoryLocation $location;

    protected function setUp(): void
    {
        parent::setUp();

        $this->organization = Organization::factory()->create();
        $this->user = User::factory()->create(['organization_id' => $this->organization->id]);
        $this->location = InventoryLocation::factory()->create(['organization_id' => $this->organization->id]);

        Sanctum::actingAs($this->user, ['*']);
    }

    private function bin(array $attrs = [], ?Organization $org = null): WarehouseBin
    {
        $org ??= $this->organization;
        $location = $org === $this->organization
            ? $this->location
            : InventoryLocation::factory()->create(['organization_id' => $org->id]);

        $zone = WarehouseZone::create([
            'organization_id' => $org->id,
            'location_id' => $location->id,
            'name' => 'Storage',
            'code' => 'STG',
            'type' => 'storage',
        ]);

        return WarehouseBin::create(array_merge([
            'zone_id' => $zone->id,
            'code' => 'A1',
            'barcode' => 'BIN-A1',
            'is_active' => true,
            'current_count' => 0,
        ], $attrs));
    }

    private function product(): Product
    {
        $category = Category::factory()->create(['organization_id' => $this->organization->id]);

        return Product::factory()->create([
            'organization_id' => $this->organization->id,
            'category_id' => $category->id,
            'created_by' => $this->user->id,
            'barcode' => 'PRD-123',
        ]);
    }

    public function test_scan_returns_bin_with_contents(): void
    {
        $bin = $this->bin();
        $product = $this->product();
        InventoryLevel::create([
            'organization_id' => $this->organization->id,
            'product_id' => $product->id,
            'location_id' => $this->location->id,
            'bin_id' => $bin->id,
            'quantity_on_hand' => 7,
            'quantity_reserved' => 0,
        ]);

        $this->getJson(route('api.bins.scan', ['barcode' => 'BIN-A1']))
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.full_code', 'STG-A1')
            ->assertJsonPath('data.contents.0.quantity_on_hand', 7);
    }

    public function test_scan_unknown_barcode_returns_404(): void
    {
        $this->getJson(route('api.bins.scan', ['barcode' => 'NOPE']))->assertStatus(404);
    }

    public function test_assign_places_product_and_audits_mobile_source(): void
    {
        $bin = $this->bin();
        $product = $this->product();

        $this->postJson(route('api.bins.assign', $bin), ['barcode' => 'PRD-123'])
            ->assertOk()
            ->assertJsonPath('success', true);

        $this->assertDatabaseHas('inventory_levels', [
            'product_id' => $product->id,
            'location_id' => $this->location->id,
            'bin_id' => $bin->id,
        ]);
        $this->assertDatabaseHas('warehouse_bins', [
            'id' => $bin->id,
            'current_count' => 1,
        ]);

        $this->assertAudited('inventory.bin_assigned', $this->user, ['source' => 'mobile']);
    }

    public function test_assign_rejects_when_bin_at_capacity(): void
    {
        $bin = $this->bin(['max_capacity' => 1, 'current_count' => 1]);
        $product = $this->product();

        $this->postJson(route('api.bins.assign', $bin), ['product_id' => $product->id])
            ->assertStatus(422)
            ->assertJsonPath('success', false);
    }

    public function test_cannot_assign_to_other_orgs_bin(): void
    {
        $otherOrg = Organization::factory()->create();
        $bin = $this->bin(org: $otherOrg);
        $product = $this->product();

        $this->postJson(route('api.bins.assign', $bin), ['product_id' => $product->id])
            ->assertStatus(404);
    }
}
