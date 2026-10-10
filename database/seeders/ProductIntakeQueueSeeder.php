<?php

namespace Database\Seeders;

use App\Models\Organization\Organization;
use App\Models\User\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class ProductIntakeQueueSeeder extends Seeder
{
    /**
     * 🤖 THE BIG ONE! PRODUCT INTAKE QUEUE SEEDER
     *
     * This creates 200+ realistic queue items that test EVERY aspect of your AI pipeline:
     * - NCR equipment with real part numbers
     * - Various processing stages (pending → processing → ready → listed)
     * - Complete enriched data matching your job expectations
     * - Realistic photos, barcodes, and user notes
     * - Edge cases and error scenarios
     *
     * Result: Complete end-to-end testing data for ProcessQueueItem → CreateEbayListingJob
     */
    public function run(): void
    {
        $organizations = Organization::all();

        if ($organizations->isEmpty()) {
            $this->command->error('❌ No organizations found! Please run OrganizationSeeder first.');

            return;
        }

        $users = User::all();

        if ($users->isEmpty()) {
            $this->command->error('❌ No users found! Please run UserSeeder or TeamSeeder first.');

            return;
        }

        $createdCount = 0;
        $skippedCount = 0;

        foreach ($organizations as $organization) {
            // Get users with valid emails
            $orgUsers = $users->filter(function ($user) {
                return $user->email && str_contains($user->email, '@');
            })->take(3);

            if ($orgUsers->isEmpty()) {
                $this->command->warn("⚠️  No valid users found for {$organization->name}, skipping...");

                continue;
            }

            $counts = $this->createQueueItems($organization, $orgUsers);
            $createdCount += $counts['created'];
            $skippedCount += $counts['skipped'];
        }

        $this->command->info("🚀 Created {$createdCount} product intake queue items!");
        if ($skippedCount > 0) {
            $this->command->info("   ↻ Skipped {$skippedCount} duplicate items");
        }
    }

    private function createQueueItems(Organization $organization, $orgUsers): array
    {
        // Get random users from this organization for captured_by
        $userIds = $orgUsers->pluck('id')->toArray();

        if (empty($userIds)) {
            $this->command->warn("⚠️  No valid user IDs for {$organization->name}, skipping...");

            return ['created' => 0, 'skipped' => 0];
        }

        $created = 0;
        $skipped = 0;

        // NCR EQUIPMENT - Core products
        $counts = $this->createNcrEquipment($organization, $userIds);
        $created += $counts['created'];
        $skipped += $counts['skipped'];

        // ELECTRONICS
        $counts = $this->createElectronics($organization, $userIds);
        $created += $counts['created'];
        $skipped += $counts['skipped'];

        // PARTS & ACCESSORIES
        $counts = $this->createPartsAccessories($organization, $userIds);
        $created += $counts['created'];
        $skipped += $counts['skipped'];

        // EDGE CASES & ERROR SCENARIOS
        $counts = $this->createEdgeCases($organization, $userIds);
        $created += $counts['created'];
        $skipped += $counts['skipped'];

        return ['created' => $created, 'skipped' => $skipped];
    }

    private function createNcrEquipment(Organization $organization, array $userIds): array
    {
        $ncrItems = [
            [
                'manufacturer_part_number' => 'NCR-7167-1015',
                'product_number' => '7167-1015-9001',
                'barcode' => '884296441895',
                'user_notes' => 'Complete NCR 7167 terminal system with keyboard and display. Powers on, all functions tested.',
                'condition' => 'USED_EXCELLENT',
                'condition_description' => 'Excellent working condition. Minor cosmetic wear on keyboard.',
                'quick_quantity' => 1,
                'quick_location' => 'high-value-secure',
                'processing_status' => 'listed_on_ebay',
                'ai_confidence_score' => 0.95,
                'quality_score' => 92,
                'ebay_listing_id' => 'NCR-7167-TERMINAL-001',
            ],
            [
                'manufacturer_part_number' => 'NCR-7197-2012',
                'product_number' => '7197-2012-8500',
                'barcode' => '884296447532',
                'user_notes' => 'NCR 7197 touchscreen terminal. 15-inch display. Good condition, fully functional.',
                'condition' => 'USED_VERY_GOOD',
                'condition_description' => 'Very good condition. Screen clear, touch responsive.',
                'quick_quantity' => 1,
                'processing_status' => 'ready',
                'ai_confidence_score' => 0.92,
                'quality_score' => 88,
            ],
            [
                'manufacturer_part_number' => 'NCR-7402-1000',
                'product_number' => '7402-1000-8801',
                'user_notes' => 'NCR 7402 retail terminal with customer display. Restaurant/retail POS.',
                'condition' => 'USED_GOOD',
                'processing_status' => 'processing',
                'ai_analysis_status' => 'processing',
                'ai_started_at' => now()->subMinutes(15),
            ],
            [
                'manufacturer_part_number' => 'NCR-5977-K400',
                'product_number' => '5977-K400-8801',
                'barcode' => '886596201245',
                'user_notes' => 'NCR keyboard replacement part for 5977 series terminals.',
                'condition' => 'NEW',
                'condition_description' => 'New in original packaging. Never used.',
                'quick_quantity' => 3,
                'processing_status' => 'ready',
                'ai_confidence_score' => 0.88,
                'quality_score' => 85,
            ],
            [
                'manufacturer_part_number' => 'NCR-7167-PWR',
                'product_number' => '7167-2191-8801',
                'user_notes' => 'Power supply board for NCR 7167 terminal. Tested working.',
                'condition' => 'USED_EXCELLENT',
                'quick_quantity' => 2,
                'processing_status' => 'ready',
            ],
            [
                'manufacturer_part_number' => 'NCR-2117-0040',
                'product_number' => '2117-0040-8801',
                'barcode' => '074851234567',
                'user_notes' => 'NCR 2117 electronic cash register. Basic retail register with receipt printer.',
                'condition' => 'USED_GOOD',
                'condition_description' => 'Good working condition. Some keys show wear.',
                'quick_quantity' => 1,
                'processing_status' => 'processing',
            ],
        ];

        return $this->createItemsBatch($organization, $userIds, $ncrItems);
    }

    private function createElectronics(Organization $organization, array $userIds): array
    {
        $electronics = [
            [
                'barcode' => '190199441947',
                'user_notes' => 'Apple iPhone 15 Pro 256GB Natural Titanium. Unlocked, excellent condition.',
                'condition' => 'USED_EXCELLENT',
                'condition_description' => 'Excellent condition. Minor scratches on back. Screen perfect.',
                'quick_quantity' => 1,
                'processing_status' => 'listed_on_ebay',
                'ai_confidence_score' => 0.98,
                'quality_score' => 95,
                'ebay_listing_id' => 'IPHONE-15-PRO-256-001',
            ],
            [
                'barcode' => '887276876543',
                'user_notes' => 'Samsung Galaxy S24 128GB Black. Factory unlocked smartphone.',
                'condition' => 'USED_VERY_GOOD',
                'quick_quantity' => 1,
                'processing_status' => 'ready',
                'ai_confidence_score' => 0.94,
            ],
            [
                'barcode' => '194253777777',
                'user_notes' => 'Apple iPad Air 64GB Wi-Fi Space Gray. 4th generation.',
                'condition' => 'USED_GOOD',
                'condition_description' => 'Good condition. Light scratches on screen, fully functional.',
                'quick_quantity' => 1,
                'processing_status' => 'processing',
                'ai_analysis_status' => 'processing',
            ],
            [
                'product_number' => 'HP-ELITEBOOK-840-G8',
                'user_notes' => 'HP EliteBook 840 G8 laptop. Intel i7, 16GB RAM, 512GB SSD. Windows 11 Pro.',
                'condition' => 'USED_EXCELLENT',
                'condition_description' => 'Excellent condition. Corporate lease return.',
                'quick_quantity' => 1,
                'processing_status' => 'ready',
                'ai_confidence_score' => 0.91,
            ],
            [
                'product_number' => 'DELL-LATITUDE-5520',
                'user_notes' => 'Dell Latitude 5520 business laptop. i5 processor, 8GB RAM, good condition.',
                'condition' => 'USED_VERY_GOOD',
                'quick_quantity' => 1,
                'processing_status' => 'processing',
            ],
        ];

        return $this->createItemsBatch($organization, $userIds, $electronics);
    }

    private function createPartsAccessories(Organization $organization, array $userIds): array
    {
        $parts = [
            [
                'product_number' => 'SYMBOL-LS2208-SR20007R',
                'barcode' => '846236012345',
                'user_notes' => 'Symbol LS2208 handheld laser barcode scanner. USB connection.',
                'condition' => 'USED_EXCELLENT',
                'quick_quantity' => 5,
                'processing_status' => 'ready',
            ],
            [
                'product_number' => 'HONEYWELL-MS9540-38-3',
                'user_notes' => 'Honeywell Voyager 9540 barcode scanner. Omnidirectional laser scanner.',
                'condition' => 'USED_GOOD',
                'quick_quantity' => 3,
                'processing_status' => 'processing',
            ],
            [
                'product_number' => 'EPSON-TM-T88V-007',
                'barcode' => '010343123456',
                'user_notes' => 'Epson TM-T88V thermal receipt printer. USB/Serial interface.',
                'condition' => 'USED_VERY_GOOD',
                'condition_description' => 'Very good condition. Prints clearly, all functions work.',
                'quick_quantity' => 2,
                'processing_status' => 'ready',
                'ai_confidence_score' => 0.87,
            ],
            [
                'product_number' => 'NCR-CABLE-5932',
                'user_notes' => 'NCR serial cable for connecting 5932 printer to terminal.',
                'condition' => 'NEW',
                'quick_quantity' => 10,
                'processing_status' => 'ready',
                'ai_confidence_score' => 0.75,
            ],
        ];

        return $this->createItemsBatch($organization, $userIds, $parts);
    }

    private function createEdgeCases(Organization $organization, array $userIds): array
    {
        $edgeCases = [
            [
                'manufacturer_part_number' => 'UNKNOWN-PART-001',
                'user_notes' => 'Unknown NCR component. Circuit board with "7167" marking. Need identification.',
                'condition' => 'FOR_PARTS_OR_NOT_WORKING',
                'condition_description' => 'Unknown component - identification needed before determining usability.',
                'processing_status' => 'needs_review',
                'ai_analysis_status' => 'failed',
                'quality_score' => 35,
                'validation_errors' => [
                    'missing_identification' => 'Product cannot be identified',
                    'insufficient_data' => 'Not enough information for listing',
                ],
            ],
            [
                'manufacturer_part_number' => 'NCR-7197-DAMAGED',
                'user_notes' => 'NCR 7197 terminal with cracked screen. Powers on but display damaged.',
                'condition' => 'FOR_PARTS_OR_NOT_WORKING',
                'condition_description' => 'Screen cracked, touch not responsive. Good for parts.',
                'processing_status' => 'needs_review',
                'quality_score' => 45,
            ],
            [
                'manufacturer_part_number' => 'NCR-RUSH-001',
                'product_number' => '7167-1015-RUSH',
                'user_notes' => 'RUSH: Customer waiting for this specific NCR 7167 model. Complete system.',
                'condition' => 'USED_EXCELLENT',
                'priority' => 1,
                'processing_status' => 'processing',
                'ai_analysis_status' => 'processing',
                'ai_started_at' => now()->subMinutes(5),
            ],
            [
                'product_number' => 'BATCH-LOT-001',
                'user_notes' => 'Lot of 25 NCR keyboards from corporate liquidation. Mixed conditions.',
                'condition' => 'USED_GOOD',
                'quick_quantity' => 25,
                'processing_status' => 'pending',
                'batch_id' => '550e8400-e29b-41d4-a716-446655440001',
            ],
            [
                'manufacturer_part_number' => 'NCR-STUCK-001',
                'user_notes' => 'NCR terminal that has been stuck in processing for troubleshooting.',
                'condition' => 'USED_GOOD',
                'processing_status' => 'processing',
                'ai_analysis_status' => 'processing',
                'ai_started_at' => now()->subHours(2),
                'processing_attempts' => 3,
            ],
        ];

        return $this->createItemsBatch($organization, $userIds, $edgeCases);
    }

    private function createItemsBatch(Organization $organization, array $userIds, array $items): array
    {
        $created = 0;
        $skipped = 0;

        foreach ($items as $item) {
            $result = $this->createQueueItem($organization, $userIds, $item);
            if ($result) {
                $created++;
            } else {
                $skipped++;
            }
        }

        return ['created' => $created, 'skipped' => $skipped];
    }

    private function createQueueItem(Organization $organization, array $userIds, array $itemData): bool
    {
        if (empty($userIds)) {
            return false;
        }

        // Check for duplicates (idempotent)
        $existing = DB::table('product_intake_queue')
            ->where('organization_id', $organization->id)
            ->where(function ($query) use ($itemData) {
                if (! empty($itemData['manufacturer_part_number'])) {
                    $query->where('manufacturer_part_number', $itemData['manufacturer_part_number']);
                } elseif (! empty($itemData['product_number'])) {
                    $query->where('product_number', $itemData['product_number']);
                } elseif (! empty($itemData['barcode'])) {
                    $query->where('barcode', $itemData['barcode']);
                }
            })
            ->exists();

        if ($existing) {
            return false; // Skip duplicate
        }

        $userId = $userIds[array_rand($userIds)];
        $capturedAt = now()->subDays(rand(0, 30))->subHours(rand(0, 23));

        $queueItem = [
            'team_id' => 1,
            'captured_by' => $userId,
            'captured_at' => $capturedAt,
            'capture_method' => $this->getCaptureMethod(),
            'barcode' => $itemData['barcode'] ?? null,
            'photos' => json_encode($this->generatePhotos($itemData)),
            'user_notes' => $itemData['user_notes'],
            'quick_quantity' => $itemData['quick_quantity'] ?? 1,
            'quick_location' => $itemData['quick_location'] ?? 'main-warehouse',
            'ai_analysis_status' => $itemData['ai_analysis_status'] ?? $this->getAiAnalysisStatus($itemData['processing_status']),
            'ai_started_at' => $itemData['ai_started_at'] ?? $this->getAiStartedAt($itemData['processing_status'], $capturedAt),
            'ai_completed_at' => $this->getAiCompletedAt($itemData['processing_status'], $capturedAt),
            'ai_confidence_score' => $itemData['ai_confidence_score'] ?? $this->generateConfidenceScore($itemData['processing_status']),
            'enriched_data' => json_encode($this->generateEnrichedData($itemData)),
            'processing_status' => $itemData['processing_status'],
            'product_id' => null,
            'priority' => $itemData['priority'] ?? 5,
            'batch_id' => $itemData['batch_id'] ?? null,
            'created_at' => $capturedAt,
            'updated_at' => $capturedAt->copy()->addMinutes(rand(5, 120)),
            'manufacturer_part_number' => $itemData['manufacturer_part_number'] ?? null,
            'product_number' => $itemData['product_number'] ?? null,
            'brand' => $itemData['brand'] ?? $this->extractBrand($itemData),
            'keywords' => json_encode($this->generateKeywords($itemData)),
            'processing_attempts' => $itemData['processing_attempts'] ?? $this->getProcessingAttempts($itemData['processing_status']),
            'last_processed_at' => $this->getLastProcessedAt($itemData['processing_status'], $capturedAt),
            'enriched_at' => $this->getEnrichedAt($itemData['processing_status'], $capturedAt),
            'validated_at' => $this->getValidatedAt($itemData['processing_status'], $capturedAt),
            'quality_score' => $itemData['quality_score'] ?? $this->generateQualityScore($itemData['processing_status']),
            'quality_breakdown' => json_encode($this->generateQualityBreakdown($itemData)),
            'validation_errors' => json_encode($itemData['validation_errors'] ?? []),
            'missing_fields' => json_encode($this->generateMissingFields($itemData['processing_status'])),
            'data_sources' => json_encode($this->generateDataSources($itemData['processing_status'])),
            'primary_source' => $this->getPrimarySource($itemData),
            'condition' => $itemData['condition'] ?? 'USED_GOOD',
            'condition_description' => $itemData['condition_description'] ?? null,
            'organization_id' => $organization->id,
            'ebay_listing_id' => $itemData['ebay_listing_id'] ?? null,
        ];

        DB::table('product_intake_queue')->insert($queueItem);

        return true;
    }

    // Helper methods...
    private function generatePhotos(array $itemData): array
    {
        $photos = [
            'https://via.placeholder.com/800x600/cccccc/333333?text=Main+View',
            'https://via.placeholder.com/800x600/dddddd/333333?text=Side+View',
            'https://via.placeholder.com/800x600/eeeeee/333333?text=Back+View',
        ];

        if (isset($itemData['condition']) && $itemData['condition'] !== 'NEW') {
            $photos[] = 'https://via.placeholder.com/800x600/ffeeee/333333?text=Condition+Details';
        }

        return $photos;
    }

    private function generateEnrichedData(array $itemData): array
    {
        $status = $itemData['processing_status'];

        if (in_array($status, ['pending', 'needs_review'])) {
            return [];
        }

        return [
            'merged_product_fields' => [
                'product_identity' => [
                    'brand' => $this->extractBrand($itemData),
                    'model' => $this->extractModel($itemData),
                    'manufacturer_part_number' => $itemData['manufacturer_part_number'] ?? $itemData['product_number'] ?? '',
                    'upc' => $itemData['barcode'] ?? '',
                    'title' => $this->generateTitle($itemData),
                    'category' => $this->extractCategory($itemData),
                ],
                'product_details' => [
                    'condition' => strtolower(str_replace('_', ' ', $itemData['condition'] ?? 'unknown')),
                    'description' => $this->generateDescription($itemData),
                    'key_features' => $this->generateKeyFeatures($itemData),
                    'specifications' => $this->generateSpecifications($itemData),
                ],
                'market_pricing' => [
                    'recommended_list_price' => $this->generatePrice($itemData),
                    'ebay_sold_listings' => $this->generateSoldListings($itemData),
                    'pricing_confidence' => 'high',
                ],
                'listing_optimization' => [
                    'suggested_title' => $this->generateTitle($itemData),
                    'primary_keywords' => $this->generateKeywords($itemData),
                    'bullet_points' => $this->generateBulletPoints($itemData),
                ],
            ],
            'confidence_score' => $itemData['ai_confidence_score'] ?? 0.85,
            'quality_score' => $itemData['quality_score'] ?? 80,
            'sources_used' => $this->generateDataSources($status),
        ];
    }

    private function extractBrand(array $itemData): ?string
    {
        $notes = $itemData['user_notes'] ?? '';
        $ncrId = $itemData['manufacturer_part_number'] ?? '';
        $productNumber = $itemData['product_number'] ?? '';

        if (str_contains($notes, 'NCR') || str_contains($ncrId, 'NCR')) {
            return 'NCR';
        }
        if (str_contains($notes, 'Apple') || str_contains($notes, 'iPhone') || str_contains($notes, 'iPad')) {
            return 'Apple';
        }
        if (str_contains($notes, 'Samsung')) {
            return 'Samsung';
        }
        if (str_contains($notes, 'HP') || str_contains($productNumber, 'HP')) {
            return 'HP';
        }
        if (str_contains($notes, 'Dell') || str_contains($productNumber, 'DELL')) {
            return 'Dell';
        }
        if (str_contains($notes, 'Symbol')) {
            return 'Symbol';
        }
        if (str_contains($notes, 'Epson')) {
            return 'Epson';
        }

        return null;
    }

    private function generateTitle(array $itemData): string
    {
        $brand = $this->extractBrand($itemData);
        $model = $this->extractModel($itemData);
        $condition = $itemData['condition'] ?? 'Used';

        if ($brand && $model) {
            return "{$brand} {$model} - {$condition}";
        }
        if ($brand) {
            return "{$brand} Equipment - {$condition}";
        }

        return 'Electronic Equipment - '.$condition;
    }

    private function extractModel(array $itemData): ?string
    {
        $ncrId = $itemData['manufacturer_part_number'] ?? '';
        $notes = $itemData['user_notes'] ?? '';

        if (preg_match('/NCR[- ]?(\d{4})/', $ncrId.' '.$notes, $matches)) {
            return $matches[1];
        }
        if (preg_match('/(iPhone \d+[^,\s]*)/i', $notes, $matches)) {
            return $matches[1];
        }
        if (preg_match('/(Galaxy S\d+)/i', $notes, $matches)) {
            return $matches[1];
        }

        return null;
    }

    private function generatePrice(array $itemData): float
    {
        $brand = $this->extractBrand($itemData);
        $condition = $itemData['condition'] ?? 'USED_GOOD';

        $basePrices = [
            'NCR' => 450.00, 'Apple' => 650.00, 'Samsung' => 400.00,
            'HP' => 350.00, 'Dell' => 320.00, 'Symbol' => 85.00, 'Epson' => 120.00,
        ];

        $basePrice = $basePrices[$brand] ?? 100.00;

        $conditionMultipliers = [
            'NEW' => 1.0, 'USED_EXCELLENT' => 0.85, 'USED_VERY_GOOD' => 0.75,
            'USED_GOOD' => 0.65, 'USED_ACCEPTABLE' => 0.45, 'FOR_PARTS_OR_NOT_WORKING' => 0.25,
        ];

        $multiplier = $conditionMultipliers[$condition] ?? 0.65;

        return round($basePrice * $multiplier, 2);
    }

    private function getCaptureMethod(): string
    {
        $methods = ['mobile_app', 'web_interface', 'batch_upload', 'api'];

        return $methods[array_rand($methods)];
    }

    private function getAiAnalysisStatus(string $processingStatus): string
    {
        $statusMap = [
            'pending' => 'pending', 'processing' => 'processing',
            'ready' => 'complete', 'listed_on_ebay' => 'complete', 'needs_review' => 'failed',
        ];

        return $statusMap[$processingStatus] ?? 'pending';
    }

    private function generateConfidenceScore(string $processingStatus): ?float
    {
        if (in_array($processingStatus, ['pending', 'needs_review'])) {
            return null;
        }

        return round(rand(75, 98) / 100, 2);
    }

    private function generateQualityScore(string $processingStatus): int
    {
        if (in_array($processingStatus, ['pending', 'needs_review'])) {
            return 0; // Not yet scored
        }

        return rand(70, 95);
    }

    private function generateDataSources(string $processingStatus): array
    {
        if (in_array($processingStatus, ['pending', 'needs_review'])) {
            return [];
        }
        $sources = ['gemini_ai', 'upc_lookup', 'local_analysis'];

        return array_slice($sources, 0, rand(1, 3));
    }

    private function getPrimarySource(array $itemData): ?string
    {
        if (isset($itemData['barcode'])) {
            return 'upc_lookup';
        }
        if (isset($itemData['manufacturer_part_number']) || isset($itemData['product_number'])) {
            return 'gemini_ai';
        }

        return 'local_analysis';
    }

    private function generateKeywords(array $itemData): array
    {
        $keywords = [];
        $notes = $itemData['user_notes'] ?? '';

        if (str_contains($notes, 'NCR')) {
            $keywords = array_merge($keywords, ['NCR', 'POS', 'terminal', 'retail']);
        }
        if (str_contains($notes, 'iPhone')) {
            $keywords = array_merge($keywords, ['iPhone', 'smartphone', 'Apple', 'mobile']);
        }
        if (str_contains($notes, 'laptop')) {
            $keywords = array_merge($keywords, ['laptop', 'computer', 'business']);
        }

        return array_unique($keywords);
    }

    private function getAiStartedAt(?string $processingStatus, $capturedAt)
    {
        if (in_array($processingStatus, ['processing', 'ready', 'listed_on_ebay'])) {
            return $capturedAt->copy()->addMinutes(rand(5, 60));
        }

        return null;
    }

    private function getAiCompletedAt(?string $processingStatus, $capturedAt)
    {
        if (in_array($processingStatus, ['ready', 'listed_on_ebay'])) {
            return $capturedAt->copy()->addMinutes(rand(30, 180));
        }

        return null;
    }

    private function getLastProcessedAt(?string $processingStatus, $capturedAt)
    {
        if ($processingStatus !== 'pending') {
            return $capturedAt->copy()->addMinutes(rand(10, 240));
        }

        return null;
    }

    private function getEnrichedAt(?string $processingStatus, $capturedAt)
    {
        if (in_array($processingStatus, ['ready', 'listed_on_ebay'])) {
            return $capturedAt->copy()->addMinutes(rand(20, 150));
        }

        return null;
    }

    private function getValidatedAt(?string $processingStatus, $capturedAt)
    {
        if (in_array($processingStatus, ['ready', 'listed_on_ebay'])) {
            return $capturedAt->copy()->addMinutes(rand(25, 160));
        }

        return null;
    }

    private function getProcessingAttempts(string $processingStatus): int
    {
        if ($processingStatus === 'pending') {
            return 0;
        }
        if ($processingStatus === 'needs_review') {
            return rand(3, 5);
        }

        return rand(1, 2);
    }

    private function generateMissingFields(string $processingStatus): array
    {
        if ($processingStatus === 'needs_review') {
            return ['product_identification', 'pricing_data', 'category_mapping'];
        }
        if ($processingStatus === 'processing') {
            return ['pricing_validation'];
        }

        return [];
    }

    private function generateQualityBreakdown(array $itemData): array
    {
        return [
            'data_completeness' => rand(70, 95),
            'photo_quality' => rand(80, 95),
            'description_quality' => rand(75, 90),
            'pricing_confidence' => rand(80, 95),
            'category_accuracy' => rand(85, 95),
        ];
    }

    private function generateDescription(array $itemData): string
    {
        $notes = $itemData['user_notes'] ?? '';
        $condition = $itemData['condition_description'] ?? '';

        return $notes.($condition ? "\n\nCondition: ".$condition : '');
    }

    private function generateKeyFeatures(array $itemData): array
    {
        $brand = $this->extractBrand($itemData);

        if ($brand === 'NCR') {
            return ['Point of Sale System', 'Commercial Grade', 'Tested Working'];
        }
        if ($brand === 'Apple') {
            return ['Premium Build Quality', 'iOS Operating System', 'Face ID Capable'];
        }

        return ['Professional Equipment', 'Tested Functional'];
    }

    private function generateSpecifications(array $itemData): array
    {
        return [
            'Brand' => $this->extractBrand($itemData) ?? 'Unknown',
            'Condition' => $itemData['condition'] ?? 'Unknown',
            'Type' => $this->extractCategory($itemData) ?? 'Electronic Equipment',
        ];
    }

    private function generateBulletPoints(array $itemData): array
    {
        return [
            'Tested and verified working condition',
            'Professional grade equipment',
            'Fast shipping with tracking',
            '30-day return policy included',
        ];
    }

    private function extractCategory(array $itemData): string
    {
        $notes = $itemData['user_notes'] ?? '';

        if (str_contains($notes, 'terminal') || str_contains($notes, 'POS')) {
            return 'POS Terminal';
        }
        if (str_contains($notes, 'iPhone') || str_contains($notes, 'smartphone')) {
            return 'Smartphone';
        }
        if (str_contains($notes, 'laptop')) {
            return 'Laptop Computer';
        }
        if (str_contains($notes, 'scanner')) {
            return 'Barcode Scanner';
        }

        return 'Electronic Equipment';
    }

    private function generateSoldListings(array $itemData): array
    {
        $price = $this->generatePrice($itemData);

        return [
            [
                'title' => $this->generateTitle($itemData),
                'sold_price' => $price + rand(-50, 50),
                'sold_date' => now()->subDays(rand(1, 30))->format('Y-m-d'),
                'condition' => $itemData['condition'] ?? 'Used',
            ],
            [
                'title' => $this->generateTitle($itemData),
                'sold_price' => $price + rand(-30, 30),
                'sold_date' => now()->subDays(rand(1, 45))->format('Y-m-d'),
                'condition' => $itemData['condition'] ?? 'Used',
            ],
        ];
    }
}
