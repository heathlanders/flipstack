<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class CategorySeeder extends Seeder
{
    public function run(): void
    {
        $organizations = DB::table('organizations')->get();

        foreach ($organizations as $organization) {
            $this->createCategoriesForOrganization($organization);
        }

        $this->command->info('✅ Created categories with nested set structure for all organizations');
    }

    private function createCategoriesForOrganization($organization): void
    {
        // Simple flat categories for now (we can make hierarchical later)
        $categories = [
            [
                'name' => 'NCR Equipment',
                'description' => 'NCR POS systems, terminals, and hardware',
                'metadata' => [
                    'ebay_category_id' => '11176',
                    'primary_focus' => true,
                    'item_specifics' => ['Brand', 'Model', 'Condition'],
                ],
            ],
            [
                'name' => 'POS Terminals',
                'description' => 'Point-of-sale terminal systems',
                'metadata' => [
                    'ebay_category_id' => '11176',
                    'parent_category' => 'NCR Equipment',
                ],
            ],
            [
                'name' => 'Electronics',
                'description' => 'Consumer electronics and devices',
                'metadata' => [
                    'ebay_category_id' => '293',
                    'broad_category' => true,
                ],
            ],
            [
                'name' => 'Mobile Phones',
                'description' => 'Smartphones and cell phones',
                'metadata' => [
                    'ebay_category_id' => '9355',
                    'high_volume' => true,
                    'authentication_required' => true,
                ],
            ],
            [
                'name' => 'Computer Parts',
                'description' => 'Computer components and accessories',
                'metadata' => [
                    'ebay_category_id' => '175673',
                    'technical_category' => true,
                ],
            ],
            [
                'name' => 'Barcode Scanners',
                'description' => 'POS barcode scanning equipment',
                'metadata' => [
                    'ebay_category_id' => '172008',
                    'business_equipment' => true,
                ],
            ],
        ];

        $lftCounter = 1;

        foreach ($categories as $index => $categoryData) {
            $categoryId = Str::uuid();
            $slug = Str::slug($categoryData['name'].'-'.$organization->id);

            // Nested set values (simple flat structure)
            $lft = $lftCounter;
            $rgt = $lftCounter + 1;
            $lftCounter += 2;

            // ltree path (simple single level)
            $path = Str::slug($categoryData['name'], '_');

            DB::table('categories')->insert([
                'id' => $categoryId,
                'organization_id' => $organization->id,
                'parent_id' => null, // Flat structure for now
                'name' => $categoryData['name'],
                'slug' => $slug,
                'description' => $categoryData['description'],
                'image' => null,
                'lft' => $lft,
                'rgt' => $rgt,
                'depth' => 0, // Root level
                'sort_order' => $index + 1,
                'is_active' => true,
                'metadata' => json_encode($categoryData['metadata']),
                'path' => $path, // ltree format
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }
}
