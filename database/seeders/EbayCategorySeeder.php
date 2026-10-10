<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class EbayCategorySeeder extends Seeder
{
    public function run(): void
    {
        $categories = [
            [
                'category_id' => '11176',
                'name' => 'Cash Registers',
                'category_path' => 'Business & Industrial > Restaurant & Food Service > Point of Sale & Money Handling > Cash Registers',
                'level' => 4,
                'parent_id' => '11174',
                'is_leaf' => true,
                'keywords' => 'cash register pos point of sale',
                'item_count' => 1500,
                'metadata' => json_encode([
                    'auto_pay_enabled' => true,
                    'best_offer_enabled' => true,
                    'required_item_specifics' => ['Brand', 'Model', 'Type', 'Condition'],
                    'recommended_item_specifics' => ['Color', 'Year Manufactured'],
                    'condition_values' => ['New', 'Used', 'Refurbished', 'For parts or not working'],
                ]),
                'is_active' => true,
            ],
            [
                'category_id' => '11174',
                'name' => 'Point of Sale & Money Handling',
                'category_path' => 'Business & Industrial > Restaurant & Food Service > Point of Sale & Money Handling',
                'level' => 3,
                'parent_id' => '11172',
                'is_leaf' => false,
                'keywords' => 'pos point of sale money handling',
                'item_count' => 5000,
                'metadata' => json_encode([]),
                'is_active' => true,
            ],
            [
                'category_id' => '11172',
                'name' => 'Restaurant & Food Service',
                'category_path' => 'Business & Industrial > Restaurant & Food Service',
                'level' => 2,
                'parent_id' => '11116',
                'is_leaf' => false,
                'keywords' => 'restaurant food service commercial kitchen',
                'item_count' => 25000,
                'metadata' => json_encode([]),
                'is_active' => true,
            ],
            [
                'category_id' => '11116',
                'name' => 'Business & Industrial',
                'category_path' => 'Business & Industrial',
                'level' => 1,
                'parent_id' => null,
                'is_leaf' => false,
                'keywords' => 'business industrial commercial',
                'item_count' => 500000,
                'metadata' => json_encode([]),
                'is_active' => true,
            ],
            [
                'category_id' => '58058',
                'name' => 'Computers/Tablets & Networking',
                'category_path' => 'Computers/Tablets & Networking',
                'level' => 1,
                'parent_id' => null,
                'is_leaf' => false,
                'keywords' => 'computers tablets networking electronics',
                'item_count' => 800000,
                'metadata' => json_encode([]),
                'is_active' => true,
            ],
            [
                'category_id' => '171957',
                'name' => 'Point of Sale Equipment',
                'category_path' => 'Computers/Tablets & Networking > Point of Sale Equipment',
                'level' => 2,
                'parent_id' => '58058',
                'is_leaf' => false,
                'keywords' => 'pos point of sale terminal',
                'item_count' => 15000,
                'metadata' => json_encode([]),
                'is_active' => true,
            ],
            [
                'category_id' => '46530',
                'name' => 'POS Terminals & Parts',
                'category_path' => 'Computers/Tablets & Networking > Point of Sale Equipment > POS Terminals & Parts',
                'level' => 3,
                'parent_id' => '171957',
                'is_leaf' => true,
                'keywords' => 'pos terminal ncr verifone parts',
                'item_count' => 8000,
                'metadata' => json_encode([
                    'auto_pay_enabled' => true,
                    'best_offer_enabled' => true,
                    'required_item_specifics' => ['Brand', 'Model', 'MPN'],
                    'recommended_item_specifics' => ['Type', 'Connectivity'],
                    'condition_values' => ['New', 'Used', 'Refurbished', 'For parts or not working'],
                ]),
                'is_active' => true,
            ],
        ];

        foreach ($categories as $category) {
            DB::table('ebay_categories')->updateOrInsert(
                ['category_id' => $category['category_id']],
                array_merge($category, [
                    'created_at' => now()->subDays(rand(30, 120)),
                    'updated_at' => now()->subDays(rand(0, 15)),
                    'last_synced_at' => now()->subDays(rand(0, 7)),
                ])
            );
        }

        $this->command->info('Seeded '.count($categories).' eBay categories');
    }
}
