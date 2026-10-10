<?php

namespace Database\Seeders\Production;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class SystemEbayCategorySeeder extends Seeder
{
    /**
     * Seed real eBay category reference data.
     *
     * This seeds common eBay categories used for listing products.
     * Uses updateOrInsert for idempotency.
     */
    public function run(): void
    {
        $this->command->info('Seeding eBay categories...');

        $categories = $this->getCategoryData();

        foreach ($categories as $category) {
            DB::table('ebay_categories')->updateOrInsert(
                ['category_id' => $category['category_id']],
                array_merge($category, [
                    'created_at' => now(),
                    'updated_at' => now(),
                    'last_synced_at' => now(),
                ])
            );
        }

        $this->command->info('eBay categories seeded: '.count($categories).' categories');
    }

    /**
     * Get eBay category data.
     */
    protected function getCategoryData(): array
    {
        return [
            // Business & Industrial Top Level
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

            // Restaurant & Food Service
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

            // Point of Sale & Money Handling
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

            // Cash Registers
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

            // Computers/Tablets & Networking Top Level
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

            // Point of Sale Equipment
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

            // POS Terminals & Parts
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

            // Enterprise Networking
            [
                'category_id' => '175698',
                'name' => 'Enterprise Networking, Servers',
                'category_path' => 'Computers/Tablets & Networking > Enterprise Networking, Servers',
                'level' => 2,
                'parent_id' => '58058',
                'is_leaf' => false,
                'keywords' => 'enterprise networking servers data center',
                'item_count' => 120000,
                'metadata' => json_encode([]),
                'is_active' => true,
            ],

            // Switches & Hubs
            [
                'category_id' => '182094',
                'name' => 'Switches & Hubs',
                'category_path' => 'Computers/Tablets & Networking > Enterprise Networking, Servers > Switches & Hubs',
                'level' => 3,
                'parent_id' => '175698',
                'is_leaf' => true,
                'keywords' => 'network switch hub ethernet managed unmanaged',
                'item_count' => 35000,
                'metadata' => json_encode([
                    'auto_pay_enabled' => true,
                    'best_offer_enabled' => true,
                    'required_item_specifics' => ['Brand', 'Model', 'MPN', 'Number of LAN Ports'],
                    'recommended_item_specifics' => ['Network Management Type', 'Max. Data Transfer Rate'],
                    'condition_values' => ['New', 'Used', 'Refurbished', 'For parts or not working'],
                ]),
                'is_active' => true,
            ],

            // Routers
            [
                'category_id' => '175700',
                'name' => 'Routers',
                'category_path' => 'Computers/Tablets & Networking > Enterprise Networking, Servers > Routers',
                'level' => 3,
                'parent_id' => '175698',
                'is_leaf' => true,
                'keywords' => 'router enterprise network cisco juniper',
                'item_count' => 25000,
                'metadata' => json_encode([
                    'auto_pay_enabled' => true,
                    'best_offer_enabled' => true,
                    'required_item_specifics' => ['Brand', 'Model', 'MPN'],
                    'recommended_item_specifics' => ['Network Connectivity', 'Max. Wireless Data Rate'],
                    'condition_values' => ['New', 'Used', 'Refurbished', 'For parts or not working'],
                ]),
                'is_active' => true,
            ],

            // Firewalls
            [
                'category_id' => '175701',
                'name' => 'Firewall & VPN Devices',
                'category_path' => 'Computers/Tablets & Networking > Enterprise Networking, Servers > Firewall & VPN Devices',
                'level' => 3,
                'parent_id' => '175698',
                'is_leaf' => true,
                'keywords' => 'firewall vpn security fortinet palo alto cisco',
                'item_count' => 18000,
                'metadata' => json_encode([
                    'auto_pay_enabled' => true,
                    'best_offer_enabled' => true,
                    'required_item_specifics' => ['Brand', 'Model', 'MPN'],
                    'recommended_item_specifics' => ['Firewall Throughput', 'Number of LAN Ports'],
                    'condition_values' => ['New', 'Used', 'Refurbished', 'For parts or not working'],
                ]),
                'is_active' => true,
            ],

            // Industrial Automation
            [
                'category_id' => '12576',
                'name' => 'Automation, Motors & Drives',
                'category_path' => 'Business & Industrial > Automation, Motors & Drives',
                'level' => 2,
                'parent_id' => '11116',
                'is_leaf' => false,
                'keywords' => 'industrial automation motors drives plc',
                'item_count' => 180000,
                'metadata' => json_encode([]),
                'is_active' => true,
            ],

            // PLCs & HMIs
            [
                'category_id' => '181407',
                'name' => 'PLCs & HMIs',
                'category_path' => 'Business & Industrial > Automation, Motors & Drives > PLCs & HMIs',
                'level' => 3,
                'parent_id' => '12576',
                'is_leaf' => true,
                'keywords' => 'plc hmi allen bradley siemens automation controller',
                'item_count' => 45000,
                'metadata' => json_encode([
                    'auto_pay_enabled' => true,
                    'best_offer_enabled' => true,
                    'required_item_specifics' => ['Brand', 'MPN', 'Model'],
                    'recommended_item_specifics' => ['Type', 'Voltage'],
                    'condition_values' => ['New', 'Used', 'Refurbished', 'For parts or not working'],
                ]),
                'is_active' => true,
            ],

            // Test, Measurement & Inspection
            [
                'category_id' => '92074',
                'name' => 'Test, Measurement & Inspection',
                'category_path' => 'Business & Industrial > Test, Measurement & Inspection',
                'level' => 2,
                'parent_id' => '11116',
                'is_leaf' => false,
                'keywords' => 'test measurement inspection equipment lab',
                'item_count' => 95000,
                'metadata' => json_encode([]),
                'is_active' => true,
            ],

            // Oscilloscopes & Vectorscopes
            [
                'category_id' => '104247',
                'name' => 'Oscilloscopes & Vectorscopes',
                'category_path' => 'Business & Industrial > Test, Measurement & Inspection > Oscilloscopes & Vectorscopes',
                'level' => 3,
                'parent_id' => '92074',
                'is_leaf' => true,
                'keywords' => 'oscilloscope vectorscope tektronix keysight',
                'item_count' => 12000,
                'metadata' => json_encode([
                    'auto_pay_enabled' => true,
                    'best_offer_enabled' => true,
                    'required_item_specifics' => ['Brand', 'Model', 'MPN'],
                    'recommended_item_specifics' => ['Number of Channels', 'Bandwidth'],
                    'condition_values' => ['New', 'Used', 'Refurbished', 'For parts or not working'],
                ]),
                'is_active' => true,
            ],

            // Consumer Electronics
            [
                'category_id' => '293',
                'name' => 'Consumer Electronics',
                'category_path' => 'Consumer Electronics',
                'level' => 1,
                'parent_id' => null,
                'is_leaf' => false,
                'keywords' => 'electronics consumer audio video',
                'item_count' => 1200000,
                'metadata' => json_encode([]),
                'is_active' => true,
            ],

            // Cell Phones & Smartphones
            [
                'category_id' => '9355',
                'name' => 'Cell Phones & Smartphones',
                'category_path' => 'Cell Phones & Accessories > Cell Phones & Smartphones',
                'level' => 2,
                'parent_id' => '15032',
                'is_leaf' => true,
                'keywords' => 'cell phone smartphone iphone samsung android',
                'item_count' => 450000,
                'metadata' => json_encode([
                    'auto_pay_enabled' => true,
                    'best_offer_enabled' => true,
                    'required_item_specifics' => ['Brand', 'Model', 'Storage Capacity', 'Network'],
                    'recommended_item_specifics' => ['Color', 'RAM', 'Screen Size'],
                    'condition_values' => ['New', 'Used', 'Refurbished', 'For parts or not working'],
                ]),
                'is_active' => true,
            ],

            // Cell Phones & Accessories
            [
                'category_id' => '15032',
                'name' => 'Cell Phones & Accessories',
                'category_path' => 'Cell Phones & Accessories',
                'level' => 1,
                'parent_id' => null,
                'is_leaf' => false,
                'keywords' => 'cell phone mobile accessories cases',
                'item_count' => 2500000,
                'metadata' => json_encode([]),
                'is_active' => true,
            ],

            // Laptops & Netbooks
            [
                'category_id' => '175672',
                'name' => 'Laptops & Netbooks',
                'category_path' => 'Computers/Tablets & Networking > Laptops & Netbooks',
                'level' => 2,
                'parent_id' => '58058',
                'is_leaf' => true,
                'keywords' => 'laptop netbook notebook chromebook ultrabook gaming laptop computer portable',
                'item_count' => 350000,
                'metadata' => json_encode([
                    'auto_pay_enabled' => true,
                    'best_offer_enabled' => true,
                    'required_item_specifics' => ['Brand', 'Model', 'Screen Size', 'Processor', 'RAM Size', 'Storage Type', 'SSD Capacity'],
                    'recommended_item_specifics' => ['Operating System', 'GPU', 'Color', 'Maximum Resolution'],
                    'condition_values' => ['New', 'Used', 'Refurbished', 'For parts or not working'],
                ]),
                'is_active' => true,
            ],

            // Desktops & All-In-Ones
            [
                'category_id' => '179',
                'name' => 'Desktops & All-In-Ones',
                'category_path' => 'Computers/Tablets & Networking > Desktops & All-In-Ones',
                'level' => 2,
                'parent_id' => '58058',
                'is_leaf' => true,
                'keywords' => 'desktop computer all-in-one workstation tower gaming pc',
                'item_count' => 180000,
                'metadata' => json_encode([
                    'auto_pay_enabled' => true,
                    'best_offer_enabled' => true,
                    'required_item_specifics' => ['Brand', 'Model', 'Processor', 'RAM Size', 'Storage Type'],
                    'recommended_item_specifics' => ['Operating System', 'GPU', 'Form Factor'],
                    'condition_values' => ['New', 'Used', 'Refurbished', 'For parts or not working'],
                ]),
                'is_active' => true,
            ],

            // Tablets & eBook Readers
            [
                'category_id' => '171485',
                'name' => 'Tablets & eBook Readers',
                'category_path' => 'Computers/Tablets & Networking > Tablets & eBook Readers',
                'level' => 2,
                'parent_id' => '58058',
                'is_leaf' => true,
                'keywords' => 'tablet ipad android surface ebook reader kindle',
                'item_count' => 220000,
                'metadata' => json_encode([
                    'auto_pay_enabled' => true,
                    'best_offer_enabled' => true,
                    'required_item_specifics' => ['Brand', 'Model', 'Screen Size', 'Storage Capacity'],
                    'recommended_item_specifics' => ['Operating System', 'Color', 'Connectivity'],
                    'condition_values' => ['New', 'Used', 'Refurbished', 'For parts or not working'],
                ]),
                'is_active' => true,
            ],

            // Monitors, Projectors & Accessories
            [
                'category_id' => '80053',
                'name' => 'Monitors, Projectors & Accessories',
                'category_path' => 'Computers/Tablets & Networking > Monitors, Projectors & Accessories',
                'level' => 2,
                'parent_id' => '58058',
                'is_leaf' => true,
                'keywords' => 'monitor display projector screen lcd led 4k ultrawide',
                'item_count' => 150000,
                'metadata' => json_encode([
                    'auto_pay_enabled' => true,
                    'best_offer_enabled' => true,
                    'required_item_specifics' => ['Brand', 'Model', 'Screen Size', 'Maximum Resolution'],
                    'recommended_item_specifics' => ['Response Time', 'Refresh Rate', 'Panel Type'],
                    'condition_values' => ['New', 'Used', 'Refurbished', 'For parts or not working'],
                ]),
                'is_active' => true,
            ],

            // Printers, Scanners & Supplies
            [
                'category_id' => '171961',
                'name' => 'Printers, Scanners & Supplies',
                'category_path' => 'Computers/Tablets & Networking > Printers, Scanners & Supplies',
                'level' => 2,
                'parent_id' => '58058',
                'is_leaf' => true,
                'keywords' => 'printer scanner inkjet laser multifunction copier',
                'item_count' => 200000,
                'metadata' => json_encode([
                    'auto_pay_enabled' => true,
                    'best_offer_enabled' => true,
                    'required_item_specifics' => ['Brand', 'Model', 'Type', 'Connectivity'],
                    'recommended_item_specifics' => ['Color Output', 'Maximum Resolution'],
                    'condition_values' => ['New', 'Used', 'Refurbished', 'For parts or not working'],
                ]),
                'is_active' => true,
            ],

            // =====================================================================
            // GENERIC & NON-ELECTRONICS TOP-LEVEL CATEGORIES
            // =====================================================================

            // Everything Else (parent for generic default)
            [
                'category_id' => '99',
                'name' => 'Everything Else',
                'category_path' => 'Everything Else',
                'level' => 1,
                'parent_id' => null,
                'is_leaf' => false,
                'keywords' => 'everything else other miscellaneous general',
                'item_count' => 500000,
                'metadata' => json_encode([]),
                'is_active' => true,
            ],

            // Everything Else > Other (generic leaf default)
            [
                'category_id' => '10290',
                'name' => 'Other',
                'category_path' => 'Everything Else > Other',
                'level' => 2,
                'parent_id' => '99',
                'is_leaf' => true,
                'keywords' => 'other miscellaneous general uncategorized',
                'item_count' => 100000,
                'metadata' => json_encode([
                    'auto_pay_enabled' => true,
                    'best_offer_enabled' => true,
                    'required_item_specifics' => ['Brand'],
                    'recommended_item_specifics' => [],
                    'condition_values' => ['New', 'Used', 'Refurbished', 'For parts or not working'],
                ]),
                'is_active' => true,
            ],

            // Clothing, Shoes & Accessories
            [
                'category_id' => '11450',
                'name' => 'Clothing, Shoes & Accessories',
                'category_path' => 'Clothing, Shoes & Accessories',
                'level' => 1,
                'parent_id' => null,
                'is_leaf' => false,
                'keywords' => 'clothing shoes accessories apparel fashion dress shirt pants jeans jacket coat sweater boots sneakers',
                'item_count' => 5000000,
                'metadata' => json_encode([]),
                'is_active' => true,
            ],

            // Home & Garden
            [
                'category_id' => '11700',
                'name' => 'Home & Garden',
                'category_path' => 'Home & Garden',
                'level' => 1,
                'parent_id' => null,
                'is_leaf' => false,
                'keywords' => 'home garden furniture lamp rug mattress cookware vacuum power tool drill',
                'item_count' => 4000000,
                'metadata' => json_encode([]),
                'is_active' => true,
            ],

            // Sporting Goods
            [
                'category_id' => '888',
                'name' => 'Sporting Goods',
                'category_path' => 'Sporting Goods',
                'level' => 1,
                'parent_id' => null,
                'is_leaf' => false,
                'keywords' => 'sporting goods sports golf fishing bicycle bike camping kayak treadmill skateboard',
                'item_count' => 3000000,
                'metadata' => json_encode([]),
                'is_active' => true,
            ],

            // Musical Instruments & Gear
            [
                'category_id' => '619',
                'name' => 'Musical Instruments & Gear',
                'category_path' => 'Musical Instruments & Gear',
                'level' => 1,
                'parent_id' => null,
                'is_leaf' => false,
                'keywords' => 'musical instruments gear guitar piano keyboard drums violin amplifier synthesizer ukulele microphone',
                'item_count' => 2000000,
                'metadata' => json_encode([]),
                'is_active' => true,
            ],

            // Toys & Hobbies
            [
                'category_id' => '220',
                'name' => 'Toys & Hobbies',
                'category_path' => 'Toys & Hobbies',
                'level' => 1,
                'parent_id' => null,
                'is_leaf' => false,
                'keywords' => 'toys hobbies lego action figure board game puzzle doll toy',
                'item_count' => 3500000,
                'metadata' => json_encode([]),
                'is_active' => true,
            ],

            // Collectibles
            [
                'category_id' => '1',
                'name' => 'Collectibles',
                'category_path' => 'Collectibles',
                'level' => 1,
                'parent_id' => null,
                'is_leaf' => false,
                'keywords' => 'collectibles antique vintage trading card coin stamp memorabilia',
                'item_count' => 4000000,
                'metadata' => json_encode([]),
                'is_active' => true,
            ],

            // Jewelry & Watches
            [
                'category_id' => '281',
                'name' => 'Jewelry & Watches',
                'category_path' => 'Jewelry & Watches',
                'level' => 1,
                'parent_id' => null,
                'is_leaf' => false,
                'keywords' => 'jewelry watches necklace bracelet ring earring pendant watch wristwatch',
                'item_count' => 3000000,
                'metadata' => json_encode([]),
                'is_active' => true,
            ],
        ];
    }
}
