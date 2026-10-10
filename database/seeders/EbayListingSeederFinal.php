<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class EbayListingSeederFinal extends Seeder
{
    /**
     * 🏪 EBAY LISTING SEEDER - Creates eBay listing records
     */
    public function run(): void
    {
        $eligibleProducts = DB::table('products')
            ->where('status', 'active')
            ->where('ready_for_listing', true)
            ->get();

        foreach ($eligibleProducts as $product) {
            DB::table('ebay_listings')->insert([
                'product_id' => $product->id,
                'organization_id' => $product->organization_id,
                'ebay_item_id' => (string) (1000000000 + abs(crc32($product->id)) % 900000000),
                'ebay_sku' => $product->sku,
                'title' => substr($product->name, 0, 80),
                'category_id' => '11176',
                'condition' => 'Used',
                'listing_format' => 'FixedPriceItem',
                'duration' => 'GTC',
                'quantity' => $product->stock_quantity ?? 1,
                'buy_it_now_price' => $product->selling_price ?? 100,
                'current_price' => $product->selling_price ?? 100,
                'listing_status' => rand(1, 10) <= 7 ? 'Active' : 'Sold',
                'currency' => 'USD',
                'location' => 'Atlanta, GA',
                'sync_status' => 'success',
                'created_at' => now()->subDays(rand(1, 30)),
                'updated_at' => now()->subHours(rand(0, 12)),
            ]);
        }

        $this->command->info('✅ Created '.$eligibleProducts->count().' eBay listings');
    }
}
