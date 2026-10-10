<?php

namespace Database\Seeders;

use App\Models\Catalog\Brand;
use App\Models\Organization\Organization;
use Illuminate\Database\Seeder;

class BrandSeeder extends Seeder
{
    public function run(): void
    {
        $organizations = Organization::all();

        $brands = [
            // NCR and POS Brands (Primary focus)
            [
                'name' => 'NCR',
                'slug' => 'ncr',
                'description' => 'NCR Corporation - Leading provider of point-of-sale systems and business solutions',
                'logo' => 'https://via.placeholder.com/150x60/003366/white?text=NCR',
                'website' => 'https://www.ncr.com',
                'is_active' => true,
                'sort_order' => 1,
                'metadata' => [
                    'primary_categories' => ['NCR Equipment', 'POS Terminals', 'Cash Registers'],
                    'ebay_store_category' => 'pos_equipment',
                    'average_markup' => 25.0,
                    'quality_tier' => 'premium',
                    'market_position' => 'industry_leader',
                ],
            ],
            [
                'name' => 'Aloha',
                'slug' => 'aloha',
                'description' => 'Aloha POS - Restaurant and hospitality point-of-sale solutions',
                'logo' => 'https://via.placeholder.com/150x60/ff6600/white?text=Aloha',
                'website' => 'https://www.alohapos.com',
                'is_active' => true,
                'sort_order' => 2,
                'metadata' => [
                    'primary_categories' => ['POS Terminals', 'Restaurant Equipment'],
                    'market_segment' => 'hospitality',
                ],
            ],
            [
                'name' => 'Micros',
                'slug' => 'micros',
                'description' => 'Oracle Micros - Enterprise point-of-sale and hospitality systems',
                'logo' => 'https://via.placeholder.com/150x60/cc0000/white?text=Micros',
                'website' => 'https://www.oracle.com/industries/food-beverage/micros/',
                'is_active' => true,
                'sort_order' => 3,
            ],
            [
                'name' => 'Squirrel',
                'slug' => 'squirrel',
                'description' => 'Squirrel Systems - Retail point-of-sale solutions',
                'logo' => 'https://via.placeholder.com/150x60/009900/white?text=Squirrel',
                'is_active' => true,
                'sort_order' => 4,
            ],

            // Electronics Brands (For diversified inventory)
            [
                'name' => 'Apple',
                'slug' => 'apple',
                'description' => 'Apple Inc. - Consumer electronics and mobile devices',
                'logo' => 'https://via.placeholder.com/150x60/000000/white?text=Apple',
                'website' => 'https://www.apple.com',
                'is_active' => true,
                'sort_order' => 10,
                'metadata' => [
                    'primary_categories' => ['Mobile Phones', 'Tablets', 'Computers'],
                    'quality_tier' => 'premium',
                    'market_position' => 'luxury',
                    'authentication_required' => true,
                ],
            ],
            [
                'name' => 'Samsung',
                'slug' => 'samsung',
                'description' => 'Samsung Electronics - Mobile devices and consumer electronics',
                'logo' => 'https://via.placeholder.com/150x60/1f4e79/white?text=Samsung',
                'website' => 'https://www.samsung.com',
                'is_active' => true,
                'sort_order' => 11,
            ],
            [
                'name' => 'Motorola',
                'slug' => 'motorola',
                'description' => 'Motorola - Mobile communications and electronics',
                'logo' => 'https://via.placeholder.com/150x60/0066cc/white?text=Motorola',
                'is_active' => true,
                'sort_order' => 12,
            ],
            [
                'name' => 'HP',
                'slug' => 'hp',
                'description' => 'Hewlett-Packard - Computers, printers, and business equipment',
                'logo' => 'https://via.placeholder.com/150x60/0096d6/white?text=HP',
                'website' => 'https://www.hp.com',
                'is_active' => true,
                'sort_order' => 13,
            ],
            [
                'name' => 'Dell',
                'slug' => 'dell',
                'description' => 'Dell Technologies - Business computers and IT solutions',
                'logo' => 'https://via.placeholder.com/150x60/007db8/white?text=Dell',
                'website' => 'https://www.dell.com',
                'is_active' => true,
                'sort_order' => 14,
            ],
            [
                'name' => 'Lenovo',
                'slug' => 'lenovo',
                'description' => 'Lenovo - Personal computers and mobile devices',
                'logo' => 'https://via.placeholder.com/150x60/e2231a/white?text=Lenovo',
                'is_active' => true,
                'sort_order' => 15,
            ],
            [
                'name' => 'Microsoft',
                'slug' => 'microsoft',
                'description' => 'Microsoft Corporation - Software and Surface devices',
                'logo' => 'https://via.placeholder.com/150x60/00bcf2/white?text=Microsoft',
                'website' => 'https://www.microsoft.com',
                'is_active' => true,
                'sort_order' => 16,
            ],

            // Barcode Scanner & POS Accessory Brands
            [
                'name' => 'Symbol',
                'slug' => 'symbol',
                'description' => 'Symbol Technologies (Zebra) - Barcode scanning and data capture',
                'logo' => 'https://via.placeholder.com/150x60/ff6600/black?text=Symbol',
                'is_active' => true,
                'sort_order' => 20,
                'metadata' => [
                    'primary_categories' => ['Barcode Scanners', 'Accessories'],
                    'parent_company' => 'Zebra Technologies',
                ],
            ],
            [
                'name' => 'Honeywell',
                'slug' => 'honeywell',
                'description' => 'Honeywell - Barcode scanners and data collection devices',
                'logo' => 'https://via.placeholder.com/150x60/cc0000/white?text=Honeywell',
                'is_active' => true,
                'sort_order' => 21,
            ],
            [
                'name' => 'Epson',
                'slug' => 'epson',
                'description' => 'Epson - Receipt printers and POS printing solutions',
                'logo' => 'https://via.placeholder.com/150x60/003399/white?text=Epson',
                'website' => 'https://www.epson.com',
                'is_active' => true,
                'sort_order' => 22,
                'metadata' => [
                    'primary_categories' => ['Receipt Printers', 'Accessories'],
                    'specialization' => 'thermal_printing',
                ],
            ],
            [
                'name' => 'Star Micronics',
                'slug' => 'star-micronics',
                'description' => 'Star Micronics - POS printers and kitchen display systems',
                'logo' => 'https://via.placeholder.com/150x60/0066cc/white?text=Star',
                'is_active' => true,
                'sort_order' => 23,
            ],

            // Generic/Unknown Brand for unlabeled items
            [
                'name' => 'Generic',
                'slug' => 'generic',
                'description' => 'Generic or unbranded items',
                'logo' => 'https://via.placeholder.com/150x60/666666/white?text=Generic',
                'is_active' => true,
                'sort_order' => 100,
                'metadata' => [
                    'use_case' => 'unidentified_items',
                    'quality_tier' => 'economy',
                ],
            ],
        ];

        foreach ($organizations as $organization) {
            foreach ($brands as $brandData) {
                Brand::create([
                    'organization_id' => $organization->id,
                    'name' => $brandData['name'],
                    'slug' => $brandData['slug'],
                    'description' => $brandData['description'],
                    'logo' => $brandData['logo'] ?? null,
                    'website' => $brandData['website'] ?? null,
                    'is_active' => $brandData['is_active'],
                    'sort_order' => $brandData['sort_order'],
                    'metadata' => $brandData['metadata'] ?? [],
                    'created_at' => now()->subDays(rand(60, 120)),
                    'updated_at' => now()->subDays(rand(0, 30)),
                ]);
            }
        }

        $totalBrands = count($brands) * $organizations->count();
        $this->command->info("✅ Created {$totalBrands} brand entries across all organizations");
    }
}
