<?php

namespace Tests\Feature\Api;

use App\Models\Catalog\Category;
use App\Models\Catalog\Product;
use App\Models\Inventory\InventoryLocation;
use App\Models\Inventory\Supplier;
use App\Models\Organization\Organization;
use App\Models\Procurement\PurchaseOrder;
use App\Models\Procurement\PurchaseOrderItem;
use App\Models\User\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\Support\AssertsAudits;
use Tests\TestCase;

class MobileReceivingTest extends TestCase
{
    use AssertsAudits;
    use RefreshDatabase;

    protected Organization $organization;

    protected User $user;

    protected Supplier $supplier;

    protected InventoryLocation $location;

    protected function setUp(): void
    {
        parent::setUp();

        $this->organization = Organization::factory()->create();
        $this->user = User::factory()->create(['organization_id' => $this->organization->id]);
        $this->supplier = Supplier::factory()->create([
            'organization_id' => $this->organization->id,
            'created_by' => $this->user->id,
        ]);
        $this->location = InventoryLocation::factory()->create([
            'organization_id' => $this->organization->id,
        ]);

        Sanctum::actingAs($this->user, ['*']);
    }

    private function submittedPo(): PurchaseOrder
    {
        return PurchaseOrder::factory()->submitted()->create([
            'organization_id' => $this->organization->id,
            'supplier_id' => $this->supplier->id,
            'location_id' => $this->location->id,
            'created_by' => $this->user->id,
        ]);
    }

    private function lineFor(PurchaseOrder $po, int $ordered = 10, int $received = 0): PurchaseOrderItem
    {
        $category = Category::factory()->create(['organization_id' => $this->organization->id]);
        $product = Product::factory()->create([
            'organization_id' => $this->organization->id,
            'category_id' => $category->id,
            'created_by' => $this->user->id,
            'barcode' => '0123456789012',
        ]);

        return PurchaseOrderItem::factory()->create([
            'purchase_order_id' => $po->id,
            'product_id' => $product->id,
            'quantity_ordered' => $ordered,
            'quantity_received' => $received,
        ]);
    }

    public function test_index_lists_open_purchase_orders(): void
    {
        $po = $this->submittedPo();
        $this->lineFor($po);

        $this->getJson(route('api.receiving.index'))
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.0.po_number', $po->po_number)
            ->assertJsonPath('stats.submitted', 1);
    }

    public function test_receive_item_increments_received_and_audits_mobile_source(): void
    {
        $po = $this->submittedPo();
        $line = $this->lineFor($po, ordered: 10);

        $this->postJson(route('api.receiving.item.receive', [$po, $line]), [
            'quantity' => 4,
            'barcode' => '0123456789012',
            'location_id' => $this->location->id,
            'condition' => 'good',
        ])->assertOk()->assertJsonPath('success', true);

        $this->assertDatabaseHas('purchase_order_items', [
            'id' => $line->id,
            'quantity_received' => 4,
        ]);

        $po->refresh();
        $this->assertSame(PurchaseOrder::STATUS_PARTIALLY_RECEIVED, $po->status);

        $this->assertAudited('po.received', $this->user, ['source' => 'mobile']);
    }

    public function test_receive_item_rejects_mismatched_barcode(): void
    {
        $po = $this->submittedPo();
        $line = $this->lineFor($po);

        $this->postJson(route('api.receiving.item.receive', [$po, $line]), [
            'quantity' => 1,
            'barcode' => 'WRONG-CODE',
        ])->assertStatus(422)->assertJsonPath('success', false);

        $this->assertDatabaseHas('purchase_order_items', [
            'id' => $line->id,
            'quantity_received' => 0,
        ]);
    }

    public function test_complete_receives_all_remaining_and_marks_received(): void
    {
        $po = $this->submittedPo();
        $line = $this->lineFor($po, ordered: 6, received: 2);

        $this->postJson(route('api.receiving.complete', $po), [
            'location_id' => $this->location->id,
        ])->assertOk()->assertJsonPath('success', true);

        $this->assertDatabaseHas('purchase_order_items', [
            'id' => $line->id,
            'quantity_received' => 6,
        ]);

        $po->refresh();
        $this->assertSame(PurchaseOrder::STATUS_RECEIVED, $po->status);

        $this->assertAudited('po.received', $this->user, ['source' => 'mobile']);
    }

    public function test_cannot_receive_against_other_orgs_po(): void
    {
        $otherOrg = Organization::factory()->create();
        $po = PurchaseOrder::factory()->submitted()->create([
            'organization_id' => $otherOrg->id,
            'created_by' => $this->user->id,
        ]);
        $line = PurchaseOrderItem::factory()->create([
            'purchase_order_id' => $po->id,
            'quantity_ordered' => 5,
        ]);

        $this->postJson(route('api.receiving.item.receive', [$po, $line]), [
            'quantity' => 1,
        ])->assertStatus(404);
    }
}
