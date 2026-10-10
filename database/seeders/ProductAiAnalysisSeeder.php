<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class ProductAiAnalysisSeeder extends Seeder
{
    /**
     * 🤖 AI ANALYSIS RECORDS SEEDER
     *
     * Creates realistic AI analysis records that correspond to processed queue items.
     * Matches the exact structure expected by ProcessQueueItem job.
     */
    public function run(): void
    {
        // Get all processed queue items (those with analysis)
        $processedItems = DB::table('product_intake_queue')
            ->whereNotIn('processing_status', ['pending', 'needs_review'])
            ->whereNotNull('ai_confidence_score')
            ->get();

        if ($processedItems->isEmpty()) {
            $this->command->warn('⚠️  No processed queue items found. Run ProductIntakeQueueSeeder first.');

            return;
        }

        $created = 0;
        $skipped = 0;

        foreach ($processedItems as $queueItem) {
            // Check if analysis already exists (idempotent)
            $existing = DB::table('product_ai_analyses')
                ->where('queue_item_id', $queueItem->id)
                ->exists();

            if ($existing) {
                $skipped++;

                continue;
            }

            $this->createAiAnalysis($queueItem);
            $created++;
        }

        $this->command->info("✅ Created {$created} AI analysis records");
        if ($skipped > 0) {
            $this->command->info("   ↻ Skipped {$skipped} existing records");
        }
    }

    private function createAiAnalysis($queueItem): void
    {
        // Determine analysis type based on available data
        $analysisType = $this->determineAnalysisType($queueItem);
        $aiProvider = $this->getAiProvider($analysisType);

        // Create the analysis record
        DB::table('product_ai_analyses')->insert([
            'product_id' => $queueItem->product_id,
            'queue_item_id' => $queueItem->id,
            'analysis_type' => $analysisType,
            'input_images' => json_encode($this->getInputImages($queueItem)),
            'ai_provider' => $aiProvider,
            'model_version' => $this->getModelVersion($aiProvider),
            'extracted_text' => json_encode($this->generateExtractedText($queueItem)),
            'detected_category' => $this->extractDetectedCategory($queueItem),
            'detected_brand' => $queueItem->brand,
            'detected_model' => $this->extractDetectedModel($queueItem),
            'ai_description' => $this->generateAiDescription($queueItem),
            'ai_specifications' => json_encode($this->generateAiSpecifications($queueItem)),
            'user_description' => $queueItem->user_notes,
            'user_corrections' => json_encode($this->generateUserCorrections($queueItem)),
            'confidence_score' => $queueItem->ai_confidence_score,
            'verification_status' => $this->getVerificationStatus($queueItem),
            'reviewer_notes' => $this->generateReviewerNotes($queueItem),
            'reviewed_by' => $this->getReviewedBy($queueItem),
            'reviewed_at' => $this->getReviewedAt($queueItem),
            'raw_response' => json_encode($this->generateRawResponse($queueItem, $analysisType)),
            'created_at' => $queueItem->ai_completed_at ?? $queueItem->updated_at,
            'updated_at' => $queueItem->updated_at,
        ]);
    }

    private function determineAnalysisType($queueItem): string
    {
        // Priority: Gemini > UPC Lookup > Local OCR
        if ($queueItem->manufacturer_part_number || $queueItem->product_number) {
            return 'gemini_research';
        }

        if ($queueItem->barcode) {
            return 'upc_lookup';
        }

        return 'product_identification';
    }

    private function getAiProvider(string $analysisType): string
    {
        return match ($analysisType) {
            'gemini_research' => 'google_gemini',
            'upc_lookup' => 'upc_database_api',
            'product_identification' => 'local_ocr',
            default => 'unknown',
        };
    }

    private function getModelVersion(string $aiProvider): string
    {
        return match ($aiProvider) {
            'google_gemini' => 'gemini-3-flash',
            'upc_database_api' => 'api_v2.1',
            'local_ocr' => 'tesseract_5.3',
            default => 'v1.0',
        };
    }

    private function getInputImages($queueItem): array
    {
        $photos = json_decode($queueItem->photos ?? '[]', true);

        return array_map(function ($photo, $index) {
            return [
                'url' => $photo,
                'type' => $index === 0 ? 'main' : 'detail',
                'processed_at' => now()->subMinutes(rand(5, 60))->toIso8601String(),
            ];
        }, $photos, array_keys($photos));
    }

    private function generateExtractedText($queueItem): array
    {
        $extractedTexts = [];

        // Simulate OCR text extraction from images
        if ($queueItem->manufacturer_part_number) {
            $extractedTexts['part_number'] = $queueItem->manufacturer_part_number;
        }

        if ($queueItem->barcode) {
            $extractedTexts['barcode'] = $queueItem->barcode;
        }

        // Extract brand from user notes
        if ($queueItem->brand) {
            $extractedTexts['brand'] = $queueItem->brand;
        }

        // Simulate model number extraction
        if (preg_match('/(\d{4}[-\s]?\d{4})/', $queueItem->user_notes, $matches)) {
            $extractedTexts['model_number'] = $matches[1];
        }

        return $extractedTexts;
    }

    private function extractDetectedCategory($queueItem): ?string
    {
        $notes = strtolower($queueItem->user_notes ?? '');

        if (str_contains($notes, 'terminal') || str_contains($notes, 'pos')) {
            return 'POS Terminal';
        }
        if (str_contains($notes, 'iphone') || str_contains($notes, 'smartphone')) {
            return 'Smartphone';
        }
        if (str_contains($notes, 'laptop') || str_contains($notes, 'computer')) {
            return 'Computer';
        }
        if (str_contains($notes, 'scanner')) {
            return 'Barcode Scanner';
        }
        if (str_contains($notes, 'printer')) {
            return 'Receipt Printer';
        }
        if (str_contains($notes, 'keyboard')) {
            return 'Computer Accessory';
        }

        return 'Electronic Equipment';
    }

    private function extractDetectedModel($queueItem): ?string
    {
        $notes = $queueItem->user_notes ?? '';
        $ncrId = $queueItem->manufacturer_part_number ?? '';

        // Extract NCR model numbers
        if (preg_match('/NCR[- ]?(\d{4})/', $ncrId.' '.$notes, $matches)) {
            return $matches[1];
        }

        // Extract iPhone models
        if (preg_match('/(iPhone \d+[^,\s]*)/i', $notes, $matches)) {
            return trim($matches[1]);
        }

        // Extract Samsung models
        if (preg_match('/(Galaxy S\d+)/i', $notes, $matches)) {
            return trim($matches[1]);
        }

        // Extract laptop models
        if (preg_match('/(EliteBook \d+)/i', $notes, $matches)) {
            return trim($matches[1]);
        }

        if (preg_match('/(Latitude \d+)/i', $notes, $matches)) {
            return trim($matches[1]);
        }

        return null;
    }

    private function generateAiDescription($queueItem): string
    {
        $brand = $queueItem->brand ?? 'Unknown';
        $category = $this->extractDetectedCategory($queueItem);
        $model = $this->extractDetectedModel($queueItem);
        $condition = $queueItem->condition ?? 'USED_GOOD';

        $conditionText = str_replace('_', ' ', strtolower($condition));

        if ($model) {
            return "{$brand} {$model} {$category} in {$conditionText} condition. ".
                   'Professional grade equipment suitable for commercial use.';
        }

        return "{$brand} {$category} in {$conditionText} condition. ".
               'Commercial grade equipment tested for functionality.';
    }

    private function generateAiSpecifications($queueItem): array
    {
        $specs = [
            'Brand' => $queueItem->brand ?? 'Unknown',
            'Condition' => $queueItem->condition ?? 'Unknown',
            'Category' => $this->extractDetectedCategory($queueItem),
        ];

        if ($queueItem->manufacturer_part_number) {
            $specs['Part Number'] = $queueItem->manufacturer_part_number;
        }

        if ($queueItem->barcode) {
            $specs['UPC/Barcode'] = $queueItem->barcode;
        }

        if ($model = $this->extractDetectedModel($queueItem)) {
            $specs['Model'] = $model;
        }

        // Add category-specific specifications
        $category = strtolower($this->extractDetectedCategory($queueItem) ?? '');

        if (str_contains($category, 'smartphone')) {
            $specs['Type'] = 'Smartphone';
            $specs['Operating System'] = str_contains($queueItem->user_notes, 'iPhone') ? 'iOS' : 'Android';
        }

        if (str_contains($category, 'terminal') || str_contains($category, 'pos')) {
            $specs['Type'] = 'Point of Sale Terminal';
            $specs['Application'] = 'Retail/Restaurant';
        }

        if (str_contains($category, 'laptop') || str_contains($category, 'computer')) {
            $specs['Type'] = 'Business Computer';
            $specs['Form Factor'] = str_contains($queueItem->user_notes, 'laptop') ? 'Laptop' : 'Desktop';
        }

        return $specs;
    }

    private function generateUserCorrections($queueItem): array
    {
        // Simulate occasional user corrections for testing
        if (rand(1, 10) <= 2) { // 20% chance of corrections
            return [
                'corrected_brand' => $queueItem->brand,
                'corrected_condition' => $queueItem->condition,
                'correction_notes' => 'User verified brand and condition details',
                'corrected_by' => $queueItem->captured_by,
                'corrected_at' => $queueItem->updated_at,
            ];
        }

        return [];
    }

    private function getVerificationStatus($queueItem): string
    {
        // Based on processing status and confidence
        $status = $queueItem->processing_status;
        $confidence = $queueItem->ai_confidence_score ?? 0;

        if ($status === 'listed_on_ebay') {
            return 'verified';
        }

        if ($status === 'ready' && $confidence >= 0.9) {
            return 'auto_verified';
        }

        if ($status === 'ready' && $confidence >= 0.7) {
            return 'pending_review';
        }

        return 'pending';
    }

    private function generateReviewerNotes($queueItem): ?string
    {
        $verificationStatus = $this->getVerificationStatus($queueItem);

        if ($verificationStatus === 'verified') {
            return 'Product verified and successfully listed on eBay. All details accurate.';
        }

        if ($verificationStatus === 'auto_verified') {
            return 'High confidence AI analysis. Auto-approved based on quality score.';
        }

        if (($queueItem->ai_confidence_score ?? 0) < 0.8) {
            return 'Lower confidence analysis. May require manual review before listing.';
        }

        return null;
    }

    private function getReviewedBy($queueItem): ?int
    {
        $verificationStatus = $this->getVerificationStatus($queueItem);

        if (in_array($verificationStatus, ['verified', 'manual_verified'])) {
            // Return a random user ID for manual review
            return $queueItem->captured_by;
        }

        return null;
    }

    private function getReviewedAt($queueItem)
    {
        if ($this->getReviewedBy($queueItem)) {
            return $queueItem->validated_at ?? $queueItem->updated_at;
        }

        return null;
    }

    private function generateRawResponse($queueItem, string $analysisType): array
    {
        return match ($analysisType) {
            'gemini_research' => $this->generateGeminiResponse($queueItem),
            'upc_lookup' => $this->generateUpcResponse($queueItem),
            'product_identification' => $this->generateOcrResponse($queueItem),
            default => [],
        };
    }

    private function generateGeminiResponse($queueItem): array
    {
        return [
            'model' => 'gemini-3-flash',
            'response_time_ms' => rand(2000, 8000),
            'tokens_used' => rand(1500, 3500),
            'product_identity' => [
                'brand' => $queueItem->brand,
                'model' => $this->extractDetectedModel($queueItem),
                'manufacturer_part_number' => $queueItem->manufacturer_part_number ?? $queueItem->product_number,
                'title' => $this->generateTitle($queueItem),
                'category' => $this->extractDetectedCategory($queueItem),
                'confidence' => $queueItem->ai_confidence_score ?? 0.85,
            ],
            'product_details' => [
                'description' => $this->generateAiDescription($queueItem),
                'condition' => strtolower(str_replace('_', ' ', $queueItem->condition ?? 'used')),
                'key_features' => $this->generateKeyFeatures($queueItem),
                'specifications' => $this->generateAiSpecifications($queueItem),
            ],
            'market_pricing' => [
                'recommended_list_price' => $this->generatePrice($queueItem),
                'price_range' => [
                    'min' => $this->generatePrice($queueItem) * 0.8,
                    'max' => $this->generatePrice($queueItem) * 1.2,
                ],
                'pricing_confidence' => 'high',
            ],
            'data_sources' => ['web_search', 'product_database', 'market_analysis'],
            'processing_metadata' => [
                'search_queries' => [
                    $queueItem->manufacturer_part_number ?? $queueItem->product_number ?? 'unknown',
                    ($queueItem->brand ?? '').' '.($this->extractDetectedModel($queueItem) ?? ''),
                ],
                'confidence_factors' => [
                    'part_number_match' => $queueItem->manufacturer_part_number ? 0.95 : 0.0,
                    'brand_recognition' => $queueItem->brand ? 0.9 : 0.5,
                    'market_data_available' => 0.8,
                    'image_quality' => 0.85,
                ],
            ],
        ];
    }

    private function generateUpcResponse($queueItem): array
    {
        return [
            'source' => 'upc_database_api',
            'barcode' => $queueItem->barcode,
            'lookup_success' => true,
            'response_time_ms' => rand(500, 2000),
            'product_info' => [
                'title' => $this->generateTitle($queueItem),
                'brand' => $queueItem->brand,
                'model' => $this->extractDetectedModel($queueItem),
                'category' => $this->extractDetectedCategory($queueItem),
                'description' => $this->generateAiDescription($queueItem),
            ],
            'market_data' => [
                'average_price' => $this->generatePrice($queueItem),
                'price_history' => $this->generatePriceHistory($queueItem),
            ],
            'data_quality' => [
                'completeness' => rand(80, 95),
                'accuracy' => rand(85, 98),
                'freshness' => rand(70, 90),
            ],
        ];
    }

    private function generateOcrResponse($queueItem): array
    {
        return [
            'ocr_engine' => 'tesseract_5.3',
            'processing_time_ms' => rand(3000, 12000),
            'images_processed' => count(json_decode($queueItem->photos ?? '[]', true)),
            'extracted_text' => $this->generateExtractedText($queueItem),
            'text_regions' => [
                [
                    'text' => $queueItem->manufacturer_part_number ?? 'Unknown Part',
                    'confidence' => 0.92,
                    'region' => 'label_area',
                ],
                [
                    'text' => $queueItem->brand ?? 'Brand Name',
                    'confidence' => 0.88,
                    'region' => 'brand_marking',
                ],
            ],
            'image_analysis' => [
                'quality_score' => rand(70, 95),
                'blur_detected' => false,
                'text_regions_found' => rand(2, 8),
                'preprocessing_applied' => ['contrast_enhancement', 'noise_reduction'],
            ],
        ];
    }

    // Helper methods
    private function generateTitle($queueItem): string
    {
        $brand = $queueItem->brand ?? 'Electronic';
        $model = $this->extractDetectedModel($queueItem);
        $category = $this->extractDetectedCategory($queueItem);

        if ($model) {
            return "{$brand} {$model} {$category}";
        }

        return "{$brand} {$category}";
    }

    private function generatePrice($queueItem): float
    {
        // Base pricing logic similar to ProductIntakeQueueSeeder
        $brand = $queueItem->brand ?? '';
        $condition = $queueItem->condition ?? 'USED_GOOD';

        $basePrices = [
            'NCR' => 450.00,
            'Apple' => 650.00,
            'Samsung' => 400.00,
            'HP' => 350.00,
            'Dell' => 320.00,
        ];

        $basePrice = $basePrices[$brand] ?? 150.00;

        $conditionMultipliers = [
            'NEW' => 1.0,
            'USED_EXCELLENT' => 0.85,
            'USED_VERY_GOOD' => 0.75,
            'USED_GOOD' => 0.65,
            'USED_ACCEPTABLE' => 0.45,
        ];

        $multiplier = $conditionMultipliers[$condition] ?? 0.65;

        return round($basePrice * $multiplier, 2);
    }

    private function generateKeyFeatures($queueItem): array
    {
        $brand = $queueItem->brand ?? '';
        $category = strtolower($this->extractDetectedCategory($queueItem) ?? '');

        if (str_contains($category, 'terminal') || str_contains($category, 'pos')) {
            return [
                'Commercial grade point-of-sale terminal',
                'Tested and verified working condition',
                'Suitable for retail and restaurant use',
                'Professional equipment with warranty support',
            ];
        }

        if (str_contains($category, 'smartphone')) {
            return [
                'Unlocked smartphone for all carriers',
                'Tested functionality and performance',
                'Original accessories may be included',
                'Ready to use out of the box',
            ];
        }

        return [
            'Professional grade equipment',
            'Tested working condition',
            'Commercial application ready',
            'Quality assured product',
        ];
    }

    private function generatePriceHistory($queueItem): array
    {
        $currentPrice = $this->generatePrice($queueItem);

        return [
            [
                'date' => now()->subDays(30)->format('Y-m-d'),
                'price' => $currentPrice + rand(-20, 20),
            ],
            [
                'date' => now()->subDays(60)->format('Y-m-d'),
                'price' => $currentPrice + rand(-30, 30),
            ],
            [
                'date' => now()->subDays(90)->format('Y-m-d'),
                'price' => $currentPrice + rand(-25, 35),
            ],
        ];
    }
}
