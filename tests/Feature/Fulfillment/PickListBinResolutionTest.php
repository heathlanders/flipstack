<?php

namespace Tests\Feature\Fulfillment;

use App\Models\Catalog\Product;
use App\Models\Fulfillment\Order;
use App\Models\Fulfillment\OrderItem;
use App\Models\Inventory\InventoryLevel;
use App\Models\Inventory\InventoryLocation;
use App\Models\Inventory\WarehouseBin;
use App\Models\Inventory\WarehouseZone;
use App\Models\Organization\Organization;
use App\Models\Organization\Team;
use App\Services\Fulfillment\FulfillmentPrintService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithoutMiddleware;
use ReflectionMethod;
use Tests\TestCase;

class PickListBinResolutionTest extends TestCase
{
    use RefreshDatabase;
    use WithoutMiddleware;

    protected Organization $organization;

    protected Team $team;

    protected function setUp(): void
    {
        parent::setUp();

        $this->organization = Organization::factory()->create([
            'sku_prefix' => 'PLB',
            'sku_prefix_locked' => true,
        ]);

        $this->team = Team::factory()->create([
            'organization_id' => $this->organization->id,
        ]);
    }

    public function test_pick_label_uses_inventory_level_bin_at_orders_location(): void
    {
        $location = InventoryLocation::create([
            'organization_id' => $this->organization->id,
            'name' => 'Main Warehouse',
            'code' => 'MAIN',
            'is_active' => true,
        ]);

        $zone = WarehouseZone::create([
            'organization_id' => $this->organization->id,
            'location_id' => $location->id,
            'name' => 'Zone A',
            'code' => 'A',
            'is_active' => true,
        ]);

        $bin = WarehouseBin::create([
            'zone_id' => $zone->id,
            'code' => '12-3',
            'aisle' => 'A',
            'rack' => '12',
            'shelf' => '3',
            'is_active' => true,
        ]);

        $product = Product::create([
            'organization_id' => $this->organization->id,
            'team_id' => $this->team->id,
            'name' => 'Test Widget',
            'sku' => 'PLB-001',
            'status' => 'active',
        ]);

        InventoryLevel::create([
            'organization_id' => $this->organization->id,
            'product_id' => $product->id,
            'location_id' => $location->id,
            'bin_id' => $bin->id,
            'quantity_on_hand' => 5,
            'quantity_reserved' => 0,
        ]);

        $order = Order::create([
            'organization_id' => $this->organization->id,
            'team_id' => $this->team->id,
            'marketplace' => 'ebay',
            'marketplace_order_id' => 'TEST-001',
            'status' => Order::STATUS_ASSIGNED,
            'fulfillment_mode' => 'in_house',
            'buyer_username' => 'test_buyer',
            'buyer_name' => 'Test Buyer',
            'shipping_address_name' => 'Test Buyer',
            'shipping_address_line1' => '123 Main St',
            'shipping_city' => 'Springfield',
            'shipping_state' => 'IL',
            'shipping_postal_code' => '62701',
            'location_id' => $location->id,
            'priority' => 'standard',
            'subtotal' => 10,
            'total_amount' => 10,
            'currency' => 'USD',
        ]);

        $item = OrderItem::create([
            'order_id' => $order->id,
            'product_id' => $product->id,
            'sku' => $product->sku,
            'title' => 'Test Widget',
            'quantity' => 1,
            'unit_price' => 10,
            'total_price' => 10,
            'pick_status' => 'pending',
        ]);

        $service = app(FulfillmentPrintService::class);

        $method = new ReflectionMethod($service, 'resolvePickBinLabel');
        $method->setAccessible(true);
        $label = $method->invoke($service, $item, $order->location_id);

        $this->assertSame('A-12-3', $label);
    }

    public function test_pick_label_falls_back_to_location_code_when_no_bin(): void
    {
        $location = InventoryLocation::create([
            'organization_id' => $this->organization->id,
            'name' => 'Backroom',
            'code' => 'BACK',
            'is_active' => true,
        ]);

        $product = Product::create([
            'organization_id' => $this->organization->id,
            'team_id' => $this->team->id,
            'name' => 'Bin-less Widget',
            'sku' => 'PLB-002',
            'status' => 'active',
        ]);

        InventoryLevel::create([
            'organization_id' => $this->organization->id,
            'product_id' => $product->id,
            'location_id' => $location->id,
            'bin_id' => null,
            'quantity_on_hand' => 3,
            'quantity_reserved' => 0,
        ]);

        $order = Order::create([
            'organization_id' => $this->organization->id,
            'team_id' => $this->team->id,
            'marketplace' => 'ebay',
            'marketplace_order_id' => 'TEST-002',
            'status' => Order::STATUS_ASSIGNED,
            'fulfillment_mode' => 'in_house',
            'buyer_username' => 'test_buyer',
            'buyer_name' => 'Test Buyer',
            'shipping_address_name' => 'Test Buyer',
            'shipping_address_line1' => '123 Main St',
            'shipping_city' => 'Springfield',
            'shipping_state' => 'IL',
            'shipping_postal_code' => '62701',
            'location_id' => $location->id,
            'priority' => 'standard',
            'subtotal' => 5,
            'total_amount' => 5,
            'currency' => 'USD',
        ]);

        $item = OrderItem::create([
            'order_id' => $order->id,
            'product_id' => $product->id,
            'sku' => $product->sku,
            'title' => 'Bin-less Widget',
            'quantity' => 1,
            'unit_price' => 5,
            'total_price' => 5,
            'pick_status' => 'pending',
        ]);

        $service = app(FulfillmentPrintService::class);

        $method = new ReflectionMethod($service, 'resolvePickBinLabel');
        $method->setAccessible(true);
        $label = $method->invoke($service, $item, $order->location_id);

        $this->assertSame('BACK', $label);
    }

    public function test_pick_label_returns_null_when_product_has_no_inventory_level(): void
    {
        $product = Product::create([
            'organization_id' => $this->organization->id,
            'team_id' => $this->team->id,
            'name' => 'Orphan Widget',
            'sku' => 'PLB-003',
            'status' => 'active',
        ]);

        $order = Order::create([
            'organization_id' => $this->organization->id,
            'team_id' => $this->team->id,
            'marketplace' => 'ebay',
            'marketplace_order_id' => 'TEST-003',
            'status' => Order::STATUS_ASSIGNED,
            'fulfillment_mode' => 'in_house',
            'buyer_username' => 'test_buyer',
            'buyer_name' => 'Test Buyer',
            'shipping_address_name' => 'Test Buyer',
            'shipping_address_line1' => '123 Main St',
            'shipping_city' => 'Springfield',
            'shipping_state' => 'IL',
            'shipping_postal_code' => '62701',
            'priority' => 'standard',
            'subtotal' => 1,
            'total_amount' => 1,
            'currency' => 'USD',
        ]);

        $item = OrderItem::create([
            'order_id' => $order->id,
            'product_id' => $product->id,
            'sku' => $product->sku,
            'title' => 'Orphan',
            'quantity' => 1,
            'unit_price' => 1,
            'total_price' => 1,
            'pick_status' => 'pending',
        ]);

        $service = app(FulfillmentPrintService::class);

        $method = new ReflectionMethod($service, 'resolvePickBinLabel');
        $method->setAccessible(true);
        $label = $method->invoke($service, $item, null);

        $this->assertNull($label);
    }
}
