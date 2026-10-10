<?php

namespace Database\Seeders;

use App\Models\Organization\Organization;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class EdgeCaseSeeder extends Seeder
{
    /**
     * 🧪 EDGE CASE SEEDER - Creates error conditions and testing scenarios
     *
     * This creates realistic edge cases, error conditions, and boundary scenarios
     * to thoroughly test the eBay automation pipeline's error handling and recovery
     */
    public function run(): void
    {
        $organizations = Organization::all();

        foreach ($organizations as $organization) {
            $this->createEdgeCasesForOrganization($organization);
        }

        $this->command->info('✅ Created comprehensive edge cases and error scenarios');
    }

    private function createEdgeCasesForOrganization(Organization $organization): void
    {
        // Create different types of edge cases
        $this->createProcessingFailures($organization);
        $this->createDataQualityIssues($organization);
        $this->createEbayApiErrors($organization);
        $this->createInventoryDiscrepancies($organization);
        $this->createValidationFailures($organization);
        $this->createTimeoutScenarios($organization);
        $this->createBoundaryConditions($organization);
        $this->createIntegrationFailures($organization);
    }

    private function createProcessingFailures(Organization $organization): void
    {
        $user = $this->getRandomUser($organization);

        // Queue item stuck in processing
        $stuckItemId = DB::table('product_intake_queue')->insertGetId([
            'organization_id' => $organization->id,
            'team_id' => 1,
            'captured_by' => $user,
            'captured_at' => now()->subHours(6),
            'capture_method' => 'mobile_app',
            'user_notes' => 'Complex NCR terminal system with multiple components. AI processing has been stuck for hours.',
            'quick_quantity' => 1,
            'ai_analysis_status' => 'processing',
            'processing_status' => 'processing',
            'ai_started_at' => now()->subHours(4),
            'processing_attempts' => 5,
            'last_processed_at' => now()->subHours(2),
            'validation_errors' => json_encode([
                'timeout_error' => 'AI analysis exceeded maximum processing time',
                'retry_limit_reached' => 'Maximum retry attempts exceeded',
            ]),
            'missing_fields' => json_encode(['product_identification', 'category_classification']),
            'priority' => 1, // High priority stuck item
            'created_at' => now()->subHours(6),
            'updated_at' => now()->subHours(1),
        ]);

        // AI analysis failure record
        DB::table('product_ai_analyses')->insert([
            'queue_item_id' => $stuckItemId,
            'analysis_type' => 'gemini_research',
            'ai_provider' => 'google_gemini',
            'model_version' => 'gemini-3-flash',
            'confidence_score' => 0.15,
            'verification_status' => 'failed',
            'raw_response' => json_encode([
                'error' => 'Unable to identify product from provided images',
                'issues' => [
                    'Image quality too poor for OCR',
                    'No clear brand or model markings visible',
                    'Multiple disconnected components',
                ],
                'suggestions' => [
                    'Retake photos with better lighting',
                    'Include close-up of any labels or markings',
                    'Separate individual components',
                ],
            ]),
            'created_at' => now()->subHours(4),
            'updated_at' => now()->subHours(1),
        ]);
    }

    private function createDataQualityIssues(Organization $organization): void
    {
        $user = $this->getRandomUser($organization);

        // Poor quality images and data
        $poorQualityId = DB::table('product_intake_queue')->insertGetId([
            'organization_id' => $organization->id,
            'team_id' => 1,
            'captured_by' => $user,
            'captured_at' => now()->subDays(2),
            'capture_method' => 'webcam',
            'photos' => json_encode([
                'https://via.placeholder.com/400x300/333333/666666?text=BLURRY+IMAGE',
                'https://via.placeholder.com/400x300/222222/555555?text=POOR+LIGHTING',
            ]),
            'user_notes' => 'unknown electronic device found in storage',
            'quick_quantity' => 1,
            'ai_analysis_status' => 'complete',
            'processing_status' => 'needs_review',
            'ai_started_at' => now()->subDays(2),
            'ai_completed_at' => now()->subDays(2)->addHours(2),
            'ai_confidence_score' => 0.25,
            'quality_score' => 15,
            'quality_breakdown' => json_encode([
                'image_quality' => 10,
                'text_clarity' => 20,
                'product_identification' => 5,
                'completeness' => 15,
                'accuracy' => 25,
            ]),
            'validation_errors' => json_encode([
                'poor_image_quality' => 'Images are too blurry for analysis',
                'insufficient_description' => 'User notes lack detail',
                'no_identifiers' => 'No UPC, model numbers, or brand visible',
                'category_unknown' => 'Cannot determine product category',
            ]),
            'missing_fields' => json_encode([
                'brand', 'model', 'category', 'condition', 'specifications',
            ]),
            'created_at' => now()->subDays(2),
            'updated_at' => now()->subHours(6),
        ]);

        // Corresponding low-confidence AI analysis
        DB::table('product_ai_analyses')->insert([
            'queue_item_id' => $poorQualityId,
            'analysis_type' => 'product_identification',
            'ai_provider' => 'local_ocr',
            'model_version' => 'tesseract_5.3',
            'extracted_text' => json_encode([
                'visible_text' => ['ELECTRONIC', 'DEVICE'],
                'confidence_scores' => [0.3, 0.4],
            ]),
            'detected_category' => 'Unknown Electronics',
            'ai_description' => 'Unidentified electronic device. Poor image quality prevents accurate analysis.',
            'confidence_score' => 0.25,
            'verification_status' => 'needs_manual_review',
            'reviewer_notes' => 'Requires better photos and additional information for proper identification',
            'raw_response' => json_encode([
                'ocr_confidence' => 0.25,
                'image_quality_score' => 15,
                'identified_features' => [],
                'recommendations' => [
                    'Retake photos with proper lighting',
                    'Include any visible labels or markings',
                    'Provide more detailed description',
                ],
            ]),
            'created_at' => now()->subDays(2),
            'updated_at' => now()->subDays(2)->addHours(1),
        ]);
    }

    private function createEbayApiErrors(Organization $organization): void
    {
        // Create a product that failed eBay listing
        $productId = Str::uuid();

        DB::table('products')->insert([
            'id' => $productId,
            'organization_id' => $organization->id,
            'name' => 'eBay API Error Test Product',
            'slug' => 'ebay-api-error-test-product',
            'sku' => 'EBAY-ERROR-001',
            'description' => 'Product created to test eBay API error handling',
            'status' => 'draft',
            'ready_for_listing' => true,
            'selling_price' => 150.00,
            'cost_price' => 100.00,
            'stock_quantity' => 1,
            'item_condition' => 'used',
            'created_at' => now()->subDays(1),
            'updated_at' => now()->subHours(2),
        ]);

        // Failed eBay listing with API errors
        DB::table('ebay_listings')->insert([
            'product_id' => $productId,
            'organization_id' => $organization->id,
            'title' => 'Test Product with eBay API Issues',
            'category_id' => '11176',
            'listing_status' => 'Failed',
            'sync_status' => 'error',
            'sync_errors' => json_encode([
                [
                    'error_code' => '21919303',
                    'error_message' => 'Item specifics required for this category',
                    'severity' => 'Error',
                    'classification' => 'RequestError',
                    'subdomain' => 'Selling',
                ],
                [
                    'error_code' => '21916803',
                    'error_message' => 'Return policy is required for this category',
                    'severity' => 'Error',
                    'classification' => 'RequestError',
                    'subdomain' => 'Selling',
                ],
            ]),
            'last_synced' => now()->subHours(1),
            'created_at' => now()->subDays(1),
            'updated_at' => now()->subHours(1),
        ]);

        // eBay API rate limit scenario
        DB::table('ebay_sync_logs')->insert([
            'organization_id' => $organization->id,
            'operation_type' => 'create_listing',
            'ebay_item_id' => null,
            'product_id' => $productId,
            'status' => 'rate_limited',
            'request_data' => json_encode(['title' => 'Rate Limit Test Product']),
            'response_data' => json_encode([
                'error' => [
                    'error_code' => '21919061',
                    'error_message' => 'Daily limit for API calls exceeded',
                    'severity' => 'Error',
                    'classification' => 'RequestError',
                ],
            ]),
            'processing_time_ms' => 1500,
            'retry_count' => 3,
            'next_retry_at' => now()->addHours(1),
            'created_at' => now()->subMinutes(30),
        ]);
    }

    private function createInventoryDiscrepancies(Organization $organization): void
    {
        // Product with inventory discrepancies
        $productId = Str::uuid();

        DB::table('products')->insert([
            'id' => $productId,
            'organization_id' => $organization->id,
            'name' => 'Inventory Discrepancy Test Item',
            'slug' => 'inventory-discrepancy-test-item',
            'sku' => 'DISC-001',
            'stock_quantity' => 5,
            'reserved_quantity' => 2,
            'available_quantity' => 3,
            'status' => 'active',
            'selling_price' => 75.00,
            'created_at' => now()->subDays(5),
            'updated_at' => now()->subHours(3),
        ]);

        // Create inventory levels that don't match product stock
        $locations = DB::table('inventory_locations')
            ->where('organization_id', $organization->id)
            ->limit(3)
            ->get();

        $totalInventory = 0;
        foreach ($locations as $location) {
            $quantity = rand(1, 3);
            $totalInventory += $quantity;

            DB::table('inventory_levels')->insert([
                'product_id' => $productId,
                'location_id' => $location->id,
                'quantity_on_hand' => $quantity,
                'quantity_available' => $quantity - rand(0, 1),
                'quantity_reserved' => rand(0, 1),
                'stock_status' => 'discrepancy_detected',
                'notes' => 'Physical count does not match system records',
                'last_counted_at' => now()->subDays(1),
                'created_at' => now()->subDays(3),
                'updated_at' => now()->subHours(2),
            ]);
        }

        // Total inventory levels don't match product stock_quantity (discrepancy)
    }

    private function createValidationFailures(Organization $organization): void
    {
        $user = $this->getRandomUser($organization);

        // Queue item with validation failures
        DB::table('product_intake_queue')->insert([
            'organization_id' => $organization->id,
            'team_id' => 1,
            'captured_by' => $user,
            'captured_at' => now()->subHours(8),
            'user_notes' => 'This is a restricted item that cannot be sold on eBay due to policy violations',
            'condition' => 'NEW',
            'ai_analysis_status' => 'complete',
            'processing_status' => 'failed',
            'ai_confidence_score' => 0.85,
            'enriched_data' => json_encode([
                'restricted_item' => true,
                'policy_violations' => [
                    'prohibited_electronics',
                    'missing_certifications',
                    'restricted_category',
                ],
            ]),
            'validation_errors' => json_encode([
                'policy_violation' => 'Item violates eBay prohibited items policy',
                'missing_certification' => 'FCC certification required but not provided',
                'restricted_category' => 'Category restricted for this seller level',
                'title_violation' => 'Title contains prohibited keywords',
            ]),
            'quality_score' => 85,
            'created_at' => now()->subHours(8),
            'updated_at' => now()->subHours(4),
        ]);
    }

    private function createTimeoutScenarios(Organization $organization): void
    {
        $user = $this->getRandomUser($organization);

        // Long-running processing with timeouts
        DB::table('product_intake_queue')->insert([
            'organization_id' => $organization->id,
            'team_id' => 1,
            'captured_by' => $user,
            'captured_at' => now()->subHours(12),
            'barcode' => '1234567890999', // Invalid UPC that causes lookup timeouts
            'user_notes' => 'Vintage electronic equipment - difficult to identify',
            'ai_analysis_status' => 'timeout',
            'processing_status' => 'failed',
            'ai_started_at' => now()->subHours(10),
            'processing_attempts' => 8,
            'last_processed_at' => now()->subHours(2),
            'validation_errors' => json_encode([
                'upc_lookup_timeout' => 'UPC database lookup exceeded timeout limit',
                'gemini_api_timeout' => 'AI analysis request timed out after 60 seconds',
                'network_timeout' => 'Network request timeout during processing',
            ]),
            'data_sources' => json_encode([]), // Empty due to timeouts
            'created_at' => now()->subHours(12),
            'updated_at' => now()->subHours(1),
        ]);
    }

    private function createBoundaryConditions(Organization $organization): void
    {
        $user = $this->getRandomUser($organization);

        // Extremely long product description (boundary test)
        $longDescription = str_repeat('This is an extremely long product description that tests the system boundaries for text length handling. ', 50);

        DB::table('product_intake_queue')->insert([
            'organization_id' => $organization->id,
            'team_id' => 1,
            'captured_by' => $user,
            'captured_at' => now()->subDays(1),
            'user_notes' => $longDescription,
            'quick_quantity' => 999, // Max quantity boundary test
            'ai_analysis_status' => 'complete',
            'processing_status' => 'needs_review',
            'validation_errors' => json_encode([
                'description_too_long' => 'Description exceeds maximum allowed length',
                'quantity_exceeds_limit' => 'Quantity exceeds system maximum',
            ]),
            'created_at' => now()->subDays(1),
            'updated_at' => now()->subHours(8),
        ]);

        // Zero/negative price boundary test
        $productId = Str::uuid();
        DB::table('products')->insert([
            'id' => $productId,
            'organization_id' => $organization->id,
            'name' => 'Zero Price Boundary Test',
            'slug' => 'zero-price-boundary-test',
            'sku' => 'ZERO-PRICE-001',
            'selling_price' => 0.00,
            'cost_price' => -5.00, // Invalid negative cost
            'status' => 'draft',
            'ready_for_listing' => false,
            'created_at' => now()->subHours(6),
            'updated_at' => now()->subHours(6),
        ]);
    }

    private function createIntegrationFailures(Organization $organization): void
    {
        // OAuth token expiration scenario
        DB::table('oauth_tokens')->insert([
            'organization_id' => $organization->id,
            'service_name' => 'ebay',
            'token_type' => 'access',
            'access_token' => 'expired_token_123456789',
            'refresh_token' => 'refresh_token_987654321',
            'expires_at' => now()->subDays(7), // Expired 7 days ago
            'scopes' => json_encode(['sell.account', 'sell.inventory']),
            'is_active' => false,
            'last_refreshed_at' => now()->subDays(14),
            'created_at' => now()->subDays(30),
            'updated_at' => now()->subDays(7),
        ]);

        // Integration failure logs
        DB::table('integration_logs')->insert([
            'organization_id' => $organization->id,
            'service_name' => 'ebay_api',
            'operation' => 'get_categories',
            'status' => 'failed',
            'error_message' => 'OAuth token has expired and refresh failed',
            'request_data' => json_encode(['category_id' => '11176']),
            'response_code' => 401,
            'response_data' => json_encode([
                'error' => 'Unauthorized',
                'error_description' => 'Token has expired',
            ]),
            'processing_time_ms' => 250,
            'created_at' => now()->subHours(2),
        ]);

        // Database connection failure simulation
        DB::table('system_health_logs')->insert([
            'check_name' => 'database_connection',
            'status' => 'failed',
            'response_time_ms' => null,
            'error_message' => 'Connection to PostgreSQL database failed',
            'metadata' => json_encode([
                'host' => 'db',
                'port' => 5432,
                'database' => 'ecommerce_saas',
                'error_code' => 'CONNECTION_REFUSED',
            ]),
            'checked_at' => now()->subMinutes(5),
        ]);
    }

    private function getRandomUser(Organization $organization): int
    {
        // Get a random user from this organization
        $users = DB::table('users')
            ->where('email', 'like', '%@%') // Simple filter for org users
            ->limit(5)
            ->pluck('id');

        return $users->isNotEmpty() ? $users->random() : 1;
    }
}
