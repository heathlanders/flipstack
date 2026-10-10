<?php

namespace Database\Seeders;

use App\Models\Organization\Organization;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class WorkflowTestSeeder extends Seeder
{
    /**
     * 🔄 WORKFLOW TEST SEEDER - Creates complete end-to-end test scenarios
     *
     * This creates realistic complete workflows that demonstrate the entire
     * eBay automation pipeline from product intake to successful eBay sales
     */
    public function run(): void
    {
        $organizations = Organization::all();

        foreach ($organizations->take(2) as $organization) { // Limit to first 2 orgs for complete workflows
            $this->createCompleteWorkflows($organization);
        }

        $this->command->info('✅ Created complete end-to-end workflow scenarios');
    }

    private function createCompleteWorkflows(Organization $organization): void
    {
        // Create different workflow scenarios
        $this->createSuccessfulNcrWorkflow($organization);
        $this->createSmartphoneResaleWorkflow($organization);
        $this->createBulkPartsWorkflow($organization);
        $this->createHighValueWorkflow($organization);
        $this->createReturnsWorkflow($organization);
    }

    private function createSuccessfulNcrWorkflow(Organization $organization): void
    {
        $user = $this->getRandomUser($organization);
        $capturedAt = now()->subDays(15);

        // STEP 1: Product Intake Queue
        $queueItemId = DB::table('product_intake_queue')->insertGetId([
            'organization_id' => $organization->id,
            'team_id' => 1,
            'captured_by' => $user,
            'captured_at' => $capturedAt,
            'capture_method' => 'mobile_app',
            'barcode' => '884296441123',
            'photos' => json_encode([
                'https://via.placeholder.com/1200x900/ffffff/333333?text=NCR+7167+Main+View',
                'https://via.placeholder.com/1200x900/ffffff/333333?text=NCR+7167+Side+View',
                'https://via.placeholder.com/1200x900/ffffff/333333?text=NCR+7167+Back+Ports',
                'https://via.placeholder.com/1200x900/ffffff/333333?text=NCR+7167+Condition',
            ]),
            'user_notes' => 'NCR 7167-1015 POS terminal system. Complete unit with keyboard and customer display. Excellent working condition, all functions tested.',
            'quick_quantity' => 1,
            'quick_location' => 'receiving-bay',
            'ai_analysis_status' => 'complete',
            'ai_started_at' => $capturedAt->copy()->addMinutes(15),
            'ai_completed_at' => $capturedAt->copy()->addHours(1),
            'ai_confidence_score' => 0.94,
            'enriched_data' => json_encode($this->generateNcrEnrichedData()),
            'processing_status' => 'listed_on_ebay',
            'priority' => 3,
            'manufacturer_part_number' => 'NCR-7167-1015',
            'product_number' => '7167-1015-9001',
            'brand' => 'NCR',
            'keywords' => json_encode(['NCR', 'POS', 'terminal', 'retail', '7167']),
            'processing_attempts' => 1,
            'last_processed_at' => $capturedAt->copy()->addHours(2),
            'enriched_at' => $capturedAt->copy()->addHours(1),
            'validated_at' => $capturedAt->copy()->addHours(3),
            'quality_score' => 92,
            'quality_breakdown' => json_encode([
                'data_completeness' => 95,
                'photo_quality' => 88,
                'description_quality' => 90,
                'pricing_confidence' => 95,
                'category_accuracy' => 94,
            ]),
            'data_sources' => json_encode(['gemini_ai', 'upc_lookup']),
            'primary_source' => 'gemini_ai',
            'condition' => 'USED_EXCELLENT',
            'condition_description' => 'Excellent working condition. Minor cosmetic wear on keyboard keys. All functions tested and verified working.',
            'created_at' => $capturedAt,
            'updated_at' => $capturedAt->copy()->addHours(4),
        ]);

        // STEP 2: AI Analysis Record
        $aiAnalysisId = DB::table('product_ai_analyses')->insertGetId([
            'queue_item_id' => $queueItemId,
            'analysis_type' => 'gemini_research',
            'ai_provider' => 'google_gemini',
            'model_version' => 'gemini-3-flash',
            'input_images' => json_encode([
                [
                    'url' => 'https://via.placeholder.com/1200x900/ffffff/333333?text=NCR+7167+Main+View',
                    'type' => 'main',
                    'processed_at' => $capturedAt->copy()->addMinutes(20)->toIso8601String(),
                ],
            ]),
            'extracted_text' => json_encode([
                'part_number' => 'NCR-7167-1015',
                'model_number' => '7167-1015-9001',
                'brand' => 'NCR Corporation',
            ]),
            'detected_category' => 'POS Terminal',
            'detected_brand' => 'NCR',
            'detected_model' => '7167',
            'ai_description' => 'NCR 7167 Series POS terminal system in excellent working condition. Complete retail point-of-sale solution suitable for restaurants and retail establishments.',
            'ai_specifications' => json_encode([
                'Brand' => 'NCR',
                'Model' => '7167-1015',
                'Type' => 'Point of Sale Terminal',
                'Screen Size' => '15 inch',
                'Condition' => 'Used - Excellent',
                'Application' => 'Retail/Restaurant POS',
            ]),
            'user_description' => 'NCR 7167-1015 POS terminal system. Complete unit with keyboard and customer display.',
            'confidence_score' => 0.94,
            'verification_status' => 'verified',
            'reviewer_notes' => 'High confidence AI analysis. Product identification accurate and complete.',
            'reviewed_by' => $user,
            'reviewed_at' => $capturedAt->copy()->addHours(2),
            'raw_response' => json_encode($this->generateGeminiRawResponse()),
            'created_at' => $capturedAt->copy()->addMinutes(15),
            'updated_at' => $capturedAt->copy()->addHours(1),
        ]);

        // STEP 3: Product Creation
        $productId = Str::uuid();
        DB::table('products')->insertGetId([
            'id' => $productId,
            'organization_id' => $organization->id,
            'category_id' => $this->getCategoryId($organization, 'POS Terminals'),
            'brand_id' => $this->getBrandId($organization, 'NCR'),
            'name' => 'NCR 7167-1015 POS Terminal System - Excellent Condition',
            'slug' => 'ncr-7167-1015-pos-terminal-system-excellent',
            'short_description' => 'Professional NCR point-of-sale terminal system in excellent working condition.',
            'description' => 'NCR 7167-1015 POS terminal system. Complete unit with keyboard and customer display. Excellent working condition, all functions tested. Perfect for retail stores, restaurants, and hospitality businesses.',
            'sku' => 'NCR-7167-1015-001',
            'barcode' => '884296441123',
            'type' => 'terminal',
            'status' => 'active',
            'cost_price' => 285.00,
            'selling_price' => 425.00,
            'msrp' => 1200.00,
            'currency' => 'USD',
            'weight' => 12.5,
            'length' => 16.0,
            'width' => 12.0,
            'height' => 8.0,
            'track_inventory' => true,
            'manage_stock' => true,
            'stock_quantity' => 1,
            'low_stock_threshold' => 1,
            'allow_backorders' => false,
            'requires_shipping' => true,
            'shipping_class' => 'heavy_equipment',
            'seo_title' => 'NCR 7167-1015 POS Terminal - Retail Point of Sale System',
            'seo_description' => 'Buy NCR 7167-1015 POS terminal system in excellent condition. Professional retail point-of-sale equipment with fast shipping.',
            'search_keywords' => 'NCR,POS,terminal,retail,7167,point of sale',
            'attributes' => json_encode([
                'source' => 'product_intake_queue',
                'ai_confidence' => 0.94,
                'brand' => 'NCR',
                'model' => '7167-1015',
                'category' => 'POS Terminal',
            ]),
            'featured_image' => 'https://via.placeholder.com/1200x900/ffffff/333333?text=NCR+7167+Main+View',
            'gallery_images' => json_encode([
                'https://via.placeholder.com/1200x900/ffffff/333333?text=NCR+7167+Main+View',
                'https://via.placeholder.com/1200x900/ffffff/333333?text=NCR+7167+Side+View',
                'https://via.placeholder.com/1200x900/ffffff/333333?text=NCR+7167+Back+Ports',
                'https://via.placeholder.com/1200x900/ffffff/333333?text=NCR+7167+Condition',
            ]),
            'published_at' => $capturedAt->copy()->addHours(6),
            'created_by' => $user,
            'upc' => '884296441123',
            'mpn' => 'NCR-7167-1015',
            'model_number' => '7167-1015',
            'features_bullets' => json_encode([
                'Complete NCR 7167 POS terminal system',
                'Excellent working condition - all functions tested',
                'Suitable for retail and restaurant use',
                'Professional grade commercial equipment',
                'Includes keyboard and customer display',
            ]),
            'specifications_table' => json_encode([
                'Brand' => 'NCR',
                'Model' => '7167-1015',
                'Type' => 'Point of Sale Terminal',
                'Condition' => 'Used - Excellent',
                'Screen Size' => '15 inch',
                'Application' => 'Retail/Restaurant POS',
                'Weight' => '12.5 lbs',
            ]),
            'item_condition' => 'excellent',
            'condition_details' => 'Excellent working condition. Minor cosmetic wear on keyboard keys. All functions tested and verified working.',
            'ready_for_listing' => true,
            'listing_checklist' => json_encode([
                'title' => ['completed' => true, 'value' => 'NCR 7167-1015 POS Terminal System'],
                'description' => ['completed' => true, 'word_count' => 85],
                'price' => ['completed' => true, 'competitive_check' => true],
                'photos' => ['completed' => true, 'count' => 4, 'min_required' => 3],
                'category' => ['completed' => true, 'ebay_category_verified' => true],
                'condition' => ['completed' => true, 'details_provided' => true],
                'overall_score' => 94,
            ]),
            'created_at' => $capturedAt->copy()->addHours(4),
            'updated_at' => $capturedAt->copy()->addHours(5),
        ]);

        // Update queue item with product ID
        DB::table('product_intake_queue')
            ->where('id', $queueItemId)
            ->update(['product_id' => $productId]);

        // STEP 4: eBay Listing Creation
        $ebayListingId = DB::table('ebay_listings')->insertGetId([
            'product_id' => $productId,
            'organization_id' => $organization->id,
            'ebay_item_id' => '334123456789',
            'ebay_sku' => 'NCR-7167-1015-001',
            'title' => 'NCR 7167-1015 POS Terminal System - Retail Point of Sale - Excellent',
            'description' => $this->generateEbayDescription(),
            'category_id' => '11176',
            'category_path' => 'Business & Industrial > Restaurant & Food Service > Point of Sale & Money Handling > Cash Registers',
            'condition' => 'Used',
            'condition_description' => 'Excellent working condition. All functions tested.',
            'listing_format' => 'FixedPriceItem',
            'listing_type' => 'Regular',
            'duration' => 'GTC',
            'quantity' => 1,
            'quantity_sold' => 1, // SOLD!
            'quantity_available' => 0,
            'buy_it_now_price' => 425.00,
            'current_price' => 425.00,
            'shipping_cost' => 45.00,
            'handling_time' => 2,
            'listing_status' => 'Sold',
            'site_id' => 0,
            'currency' => 'USD',
            'country' => 'US',
            'location' => 'Atlanta, GA',
            'postal_code' => '30309',
            'start_time' => $capturedAt->copy()->addHours(8),
            'listing_url' => 'https://www.ebay.com/itm/334123456789',
            'gallery_url' => 'https://via.placeholder.com/1200x900/ffffff/333333?text=NCR+7167+Main+View',
            'item_specifics' => json_encode([
                'Brand' => 'NCR',
                'Model' => '7167-1015',
                'Type' => 'POS Terminal',
                'Condition' => 'Used',
                'UPC' => '884296441123',
                'MPN' => 'NCR-7167-1015',
            ]),
            'best_offer_enabled' => true,
            'hit_count' => 156,
            'watch_count' => 23,
            'question_count' => 2,
            'sync_status' => 'success',
            'listing_fees' => 0.30,
            'final_value_fee' => 42.50, // 10% of sale price
            'total_fees' => 42.80,
            'profit_margin' => 22.9, // (425 - 285 - 42.80) / 425 * 100
            'created_at' => $capturedAt->copy()->addHours(6),
            'updated_at' => $capturedAt->copy()->addDays(8),
            'listed_at' => $capturedAt->copy()->addHours(8),
            'sold_at' => $capturedAt->copy()->addDays(7), // Sold after 7 days
        ]);

        // STEP 5: Inventory Management
        $mainWarehouse = DB::table('inventory_locations')
            ->where('organization_id', $organization->id)
            ->where('is_primary_location', true)
            ->first();

        if ($mainWarehouse) {
            DB::table('inventory_levels')->insert([
                'product_id' => $productId,
                'location_id' => $mainWarehouse->id,
                'quantity_on_hand' => 0, // Sold out
                'quantity_available' => 0,
                'quantity_reserved' => 0,
                'reorder_point' => 1,
                'unit_cost' => 285.00,
                'total_value' => 0.00,
                'stock_status' => 'sold_out',
                'last_sold_at' => $capturedAt->copy()->addDays(7),
                'notes' => 'Item successfully sold on eBay. Excellent workflow completion.',
                'created_at' => $capturedAt->copy()->addHours(5),
                'updated_at' => $capturedAt->copy()->addDays(7),
            ]);
        }

        $this->command->info('✅ Created complete NCR workflow: Queue → AI → Product → eBay → SOLD');
    }

    private function createSmartphoneResaleWorkflow(Organization $organization): void
    {
        $user = $this->getRandomUser($organization);
        $capturedAt = now()->subDays(8);

        // High-volume smartphone resale workflow
        $smartphoneModels = [
            ['name' => 'iPhone 15 Pro', 'barcode' => '190199441947', 'price' => 899.00],
            ['name' => 'iPhone 15', 'barcode' => '190199441948', 'price' => 699.00],
            ['name' => 'Samsung Galaxy S24', 'barcode' => '887276876543', 'price' => 649.00],
        ];

        foreach ($smartphoneModels as $index => $phone) {
            $itemCapturedAt = $capturedAt->copy()->addHours($index * 2);

            // Create queue item
            $queueItemId = DB::table('product_intake_queue')->insertGetId([
                'organization_id' => $organization->id,
                'team_id' => 1,
                'captured_by' => $user,
                'captured_at' => $itemCapturedAt,
                'barcode' => $phone['barcode'],
                'user_notes' => "{$phone['name']} smartphone in very good condition. Unlocked for all carriers.",
                'condition' => 'USED_VERY_GOOD',
                'processing_status' => 'ready',
                'ai_confidence_score' => 0.96,
                'quality_score' => 89,
                'brand' => str_contains($phone['name'], 'iPhone') ? 'Apple' : 'Samsung',
                'created_at' => $itemCapturedAt,
                'updated_at' => $itemCapturedAt->copy()->addHours(1),
            ]);

            // Create product
            $productId = Str::uuid();
            DB::table('products')->insert([
                'id' => $productId,
                'organization_id' => $organization->id,
                'name' => "{$phone['name']} - Unlocked - Very Good Condition",
                'sku' => 'PHONE-'.str_pad($index + 1, 3, '0', STR_PAD_LEFT),
                'barcode' => $phone['barcode'],
                'selling_price' => $phone['price'],
                'cost_price' => $phone['price'] * 0.75,
                'status' => 'active',
                'ready_for_listing' => true,
                'item_condition' => 'very_good',
                'stock_quantity' => 1,
                'created_at' => $itemCapturedAt->copy()->addHours(1),
                'updated_at' => $itemCapturedAt->copy()->addHours(2),
            ]);

            // Update queue with product
            DB::table('product_intake_queue')
                ->where('id', $queueItemId)
                ->update(['product_id' => $productId]);
        }

        $this->command->info('✅ Created smartphone resale workflow scenarios');
    }

    private function createBulkPartsWorkflow(Organization $organization): void
    {
        $user = $this->getRandomUser($organization);
        $capturedAt = now()->subDays(12);

        // Bulk parts processing workflow
        $batchId = Str::uuid();

        $parts = [
            'NCR Keyboard 5977-K400',
            'NCR Power Supply 7167-PWR',
            'NCR Display Cable 5932-CBL',
            'NCR Memory Module 7197-MEM',
            'NCR Printer Cable 5890-PRT',
        ];

        foreach ($parts as $index => $partName) {
            $queueItemId = DB::table('product_intake_queue')->insertGetId([
                'organization_id' => $organization->id,
                'team_id' => 1,
                'captured_by' => $user,
                'captured_at' => $capturedAt,
                'user_notes' => "{$partName} replacement part. New in original packaging.",
                'quick_quantity' => rand(3, 8), // Bulk quantities
                'condition' => 'NEW',
                'processing_status' => 'ready',
                'batch_id' => $batchId,
                'brand' => 'NCR',
                'priority' => 5,
                'created_at' => $capturedAt,
                'updated_at' => $capturedAt->copy()->addHours(6),
            ]);
        }

        $this->command->info('✅ Created bulk parts workflow with batch processing');
    }

    private function createHighValueWorkflow(Organization $organization): void
    {
        $user = $this->getRandomUser($organization);
        $capturedAt = now()->subDays(5);

        // High-value item with additional security and verification
        $queueItemId = DB::table('product_intake_queue')->insertGetId([
            'organization_id' => $organization->id,
            'team_id' => 1,
            'captured_by' => $user,
            'captured_at' => $capturedAt,
            'user_notes' => 'High-value NCR POS system. Complete restaurant solution with multiple terminals and kitchen display system.',
            'condition' => 'USED_EXCELLENT',
            'processing_status' => 'ready',
            'priority' => 1, // High priority
            'quick_location' => 'high-value-secure',
            'ai_confidence_score' => 0.91,
            'quality_score' => 88,
            'brand' => 'NCR',
            'created_at' => $capturedAt,
            'updated_at' => $capturedAt->copy()->addHours(8),
        ]);

        // High-value product
        $productId = Str::uuid();
        DB::table('products')->insert([
            'id' => $productId,
            'organization_id' => $organization->id,
            'name' => 'NCR Complete Restaurant POS System - High Value',
            'sku' => 'NCR-RESTAURANT-SYS-001',
            'selling_price' => 2850.00,
            'cost_price' => 1995.00,
            'status' => 'active',
            'ready_for_listing' => true,
            'item_condition' => 'excellent',
            'attributes' => json_encode([
                'high_value_item' => true,
                'requires_signature' => true,
                'insurance_required' => true,
            ]),
            'created_at' => $capturedAt->copy()->addHours(4),
            'updated_at' => $capturedAt->copy()->addHours(6),
        ]);

        $this->command->info('✅ Created high-value item workflow with enhanced security');
    }

    private function createReturnsWorkflow(Organization $organization): void
    {
        $user = $this->getRandomUser($organization);
        $originalSale = now()->subDays(45);
        $returnReceived = now()->subDays(5);

        // Product that was sold and returned
        $productId = Str::uuid();
        DB::table('products')->insert([
            'id' => $productId,
            'organization_id' => $organization->id,
            'name' => 'NCR Terminal (Customer Return)',
            'sku' => 'NCR-RETURN-001',
            'selling_price' => 350.00,
            'status' => 'active',
            'item_condition' => 'good', // Condition changed due to return
            'attributes' => json_encode([
                'return_reason' => 'Customer purchased wrong model',
                'return_condition' => 'Good - minor cosmetic wear',
                'return_date' => $returnReceived->format('Y-m-d'),
                'original_sale_date' => $originalSale->format('Y-m-d'),
            ]),
            'created_at' => $originalSale,
            'updated_at' => $returnReceived,
        ]);

        // Returns processing location
        $returnsLocation = DB::table('inventory_locations')
            ->where('organization_id', $organization->id)
            ->where('location_type', 'returns')
            ->first();

        if ($returnsLocation) {
            DB::table('inventory_levels')->insert([
                'product_id' => $productId,
                'location_id' => $returnsLocation->id,
                'quantity_on_hand' => 1,
                'quantity_available' => 1,
                'stock_status' => 'return_processing',
                'notes' => 'Customer return - inspected and verified good condition. Ready for resale.',
                'created_at' => $returnReceived,
                'updated_at' => $returnReceived,
            ]);
        }

        $this->command->info('✅ Created returns processing workflow');
    }

    // Helper methods
    private function generateNcrEnrichedData(): array
    {
        return [
            'merged_product_fields' => [
                'product_identity' => [
                    'brand' => 'NCR',
                    'model' => '7167-1015',
                    'manufacturer_part_number' => 'NCR-7167-1015',
                    'upc' => '884296441123',
                    'title' => 'NCR 7167-1015 POS Terminal System',
                    'category' => 'POS Terminal',
                ],
                'product_details' => [
                    'condition' => 'used excellent',
                    'description' => 'Professional NCR 7167 series POS terminal system in excellent working condition.',
                    'key_features' => [
                        'Complete POS terminal system',
                        'Excellent working condition',
                        'Professional grade equipment',
                        'Retail/restaurant suitable',
                    ],
                    'specifications' => [
                        'Brand' => 'NCR',
                        'Model' => '7167-1015',
                        'Type' => 'Point of Sale Terminal',
                        'Screen Size' => '15 inch',
                        'Application' => 'Retail/Restaurant',
                    ],
                ],
                'market_pricing' => [
                    'recommended_list_price' => 425.00,
                    'pricing_confidence' => 'high',
                    'ebay_sold_listings' => [
                        ['price' => 415.00, 'date' => '2024-01-15', 'condition' => 'used'],
                        ['price' => 439.99, 'date' => '2024-01-10', 'condition' => 'used'],
                    ],
                ],
            ],
            'confidence_score' => 0.94,
            'quality_score' => 92,
            'sources_used' => ['gemini_ai', 'upc_lookup'],
            'is_ebay_ready' => true,
        ];
    }

    private function generateGeminiRawResponse(): array
    {
        return [
            'model' => 'gemini-3-flash',
            'response_time_ms' => 3250,
            'tokens_used' => 2150,
            'product_identity' => [
                'brand' => 'NCR',
                'model' => '7167-1015',
                'confidence' => 0.94,
            ],
            'market_analysis' => [
                'average_sold_price' => 427.50,
                'price_range' => ['min' => 385.00, 'max' => 485.00],
                'market_demand' => 'high',
                'competition_level' => 'moderate',
            ],
        ];
    }

    private function generateEbayDescription(): string
    {
        return "<div style='font-family: Arial, sans-serif;'>
            <h2>NCR 7167-1015 POS Terminal System - Professional Grade</h2>
            <p><strong>Condition:</strong> Used - Excellent</p>
            <p>Complete NCR 7167 series point-of-sale terminal system in excellent working condition. Perfect for retail stores, restaurants, and hospitality businesses.</p>
            <h3>Key Features:</h3>
            <ul>
                <li>Complete POS terminal system with keyboard and display</li>
                <li>All functions tested and verified working</li>
                <li>Professional commercial-grade equipment</li>
                <li>Suitable for retail and restaurant applications</li>
            </ul>
            <h3>Specifications:</h3>
            <ul>
                <li>Brand: NCR Corporation</li>
                <li>Model: 7167-1015</li>
                <li>Type: Point of Sale Terminal</li>
                <li>Condition: Used - Excellent</li>
            </ul>
            <p><strong>Fast shipping with tracking. 30-day return policy. Professional packaging.</strong></p>
        </div>";
    }

    private function getCategoryId($organization, string $categoryName): ?string
    {
        return DB::table('categories')
            ->where('organization_id', $organization->id)
            ->where('name', 'LIKE', "%{$categoryName}%")
            ->value('id');
    }

    private function getBrandId($organization, string $brandName): ?string
    {
        return DB::table('brands')
            ->where('organization_id', $organization->id)
            ->where('name', $brandName)
            ->value('id');
    }

    private function getRandomUser(Organization $organization): int
    {
        return DB::table('users')->limit(5)->pluck('id')->random();
    }
}
