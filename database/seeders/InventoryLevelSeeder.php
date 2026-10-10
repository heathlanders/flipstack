<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class InventoryLevelSeeder extends Seeder
{
    public function run(): void
    {
        $products = DB::table('products')->get();
        $locations = DB::table('inventory_locations')->get();

        foreach ($products as $product) {
            $this->createInventoryLevelsForProduct($product, $locations);
        }

        $this->command->info('✅ Created inventory levels for all products across locations');
    }

    private function createInventoryLevelsForProduct($product, $locations): void
    {
        $orgLocations = $locations->where('organization_id', $product->organization_id);
        $totalQuantity = $product->stock_quantity ?? rand(1, 20);
        $remainingQuantity = $totalQuantity;

        foreach ($orgLocations as $index => $location) {
            $isLastLocation = ($index === $orgLocations->count() - 1);

            if ($isLastLocation) {
                $locationQuantity = $remainingQuantity;
            } else {
                $locationQuantity = rand(0, $remainingQuantity);
                $remainingQuantity -= $locationQuantity;
            }

            if ($locationQuantity <= 0) {
                continue;
            }

            $reserved = rand(0, max(0, intval($locationQuantity * 0.2)));

            // Check if record already exists
            $exists = DB::table('inventory_levels')
                ->where('product_id', $product->id)
                ->where('location_id', $location->id)
                ->exists();

            if ($exists) {
                continue;
            }

            DB::table('inventory_levels')->insert([
                'id' => \Illuminate\Support\Str::uuid(),
                'organization_id' => $product->organization_id,
                'product_id' => $product->id,
                'product_variation_id' => null,
                'location_id' => $location->id,
                'quantity_on_hand' => $locationQuantity,
                'quantity_reserved' => $reserved,
                'reorder_point' => rand(1, 5),
                'reorder_quantity' => rand(5, 20),
                'max_stock_level' => rand(50, 200),
                'average_cost' => $product->cost_price ?? rand(10, 500),
                'last_movement_at' => now()->subDays(rand(0, 30)),
                'last_count_at' => now()->subDays(rand(7, 60)),
                'version' => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }
}
