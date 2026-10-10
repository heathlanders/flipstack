<?php

namespace App\Jobs;

use App\Models\Catalog\ProductAiAnalysis;
use App\Models\Catalog\ProductIntakeQueue;
use App\Services\BackgroundRemovalService;
use App\Services\BarcodeValidationService;
use App\Services\EbayCategoryService;
use App\Services\EbayRateLimiter;
use App\Services\ProductDataValidator;
use App\Services\ProductResearchOrchestrator;
use App\Services\ShippingClassService;
use App\Services\SupplierPreferenceService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

/**
 * SIMPLIFIED ProcessQueueItem - Works with Enhanced Orchestrator
 *
 * FEATURES:
 * - Simplified research strategy (orchestrator handles comprehensive queries)
 * - Enhanced confidence scoring with completeness
 * - Supplier preference integration
 * - Physical measurement analysis
 * - All enhanced database fields supported
 */
class ProcessQueueItem implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 180;

    public int $tries = 3;

    public array $backoff = [60, 300, 900];

    /**
     * Pristine (pre-background-removal) photo paths, captured before Step 5 so
     * the product detail page can later revert a processed image.
     */
    protected array $originalPhotos = [];

    public function __construct(
        public ProductIntakeQueue $queueItem
    ) {}

    public function handle(
        ProductResearchOrchestrator $orchestrator,
        BackgroundRemovalService $bgRemoval,
        BarcodeValidationService $barcodeValidator,
        ProductDataValidator $validator,
        EbayRateLimiter $rateLimiter,
        ShippingClassService $shippingClassifier,
        SupplierPreferenceService $supplierPrefs,
        EbayCategoryService $categoryService
    ): void {
        try {
            Log::info('SIMPLIFIED: Enhanced processing started', [
                'id' => $this->queueItem->id,
                'supplier_id' => $this->queueItem->supplier_id,
                'comprehensive_data' => [
                    'brand' => $this->queueItem->brand,
                    'model_number' => $this->queueItem->model_number,
                    'manufacturer_part_number' => $this->queueItem->manufacturer_part_number,
                    'product_number' => $this->queueItem->product_number,
                    'user_notes' => $this->queueItem->user_notes,
                ],
                'physical_data' => [
                    'weight' => $this->queueItem->weight_lbs,
                    'dimensions' => $this->queueItem->length_inches.'x'.$this->queueItem->width_inches.'x'.$this->queueItem->height_inches,
                ],
            ]);

            // Update status
            $this->queueItem->update([
                'ai_analysis_status' => 'processing',
                'ai_started_at' => now(),
                'processing_attempts' => ($this->queueItem->processing_attempts ?? 0) + 1,
            ]);

            $sourcesUsed = [];
            $warnings = [];

            // Check if organization can create products (has SKU prefix)
            if (! $this->queueItem->organization->canCreateProducts()) {
                Log::warning('Cannot process queue item - organization needs SKU prefix setup', [
                    'queue_item_id' => $this->queueItem->id,
                    'organization_id' => $this->queueItem->organization_id,
                ]);

                $this->queueItem->update([
                    'processing_status' => 'blocked_sku_prefix',
                    'validation_errors' => ['Organization requires SKU prefix setup before processing products'],
                ]);

                return; // Skip processing
            }

            // ========================================
            // STEP 1: Supplier Context Analysis
            // ========================================
            $supplierContext = $this->analyzeSupplierContext($supplierPrefs);
            if (! empty($supplierContext['supplier_name'])) {
                $sourcesUsed[] = 'supplier_context';
            }

            // ========================================
            // STEP 2: Physical Measurements Analysis
            // ========================================
            $shippingData = $this->analyzePhysicalMeasurements($shippingClassifier);
            if ($shippingData['shipping_class'] !== 'standard') {
                $sourcesUsed[] = 'physical_analysis';
            }

            // ========================================
            // STEP 3: Barcode Validation (Optional)
            // ========================================
            $barcodeData = null;
            if ($this->queueItem->barcode) {
                try {
                    $barcodeData = $barcodeValidator->validate($this->queueItem->barcode);
                    if ($barcodeData && $barcodeData['valid']) {
                        $sourcesUsed[] = 'barcode_validation';
                        Log::info('Barcode validated', [
                            'type' => $barcodeData['type'],
                            'formatted' => $barcodeData['formatted'],
                        ]);
                    }
                } catch (\Exception $e) {
                    Log::warning('Barcode validation failed', ['error' => $e->getMessage()]);
                    $warnings[] = 'Barcode validation error';
                }
            }

            // ========================================
            // STEP 3.5: Preliminary eBay Category Lookup
            // ========================================
            $ebayAspectsContext = null;
            try {
                $prelimCategoryData = [
                    'title' => $this->queueItem->brand.' '.$this->queueItem->model_number.' '.$this->queueItem->manufacturer_part_number,
                    'brand' => $this->queueItem->brand ?? '',
                    'model' => $this->queueItem->model_number ?? '',
                    'category' => $this->queueItem->product_category ?? '',
                ];

                $prelimCategory = $categoryService->findPreliminaryCategory($prelimCategoryData);

                if ($prelimCategory && ! empty($prelimCategory['category_id'])) {
                    $aspectsPromptText = $categoryService->getAspectsForPrompt($prelimCategory['category_id']);

                    if (! empty($aspectsPromptText)) {
                        $ebayAspectsContext = [
                            'category_id' => $prelimCategory['category_id'],
                            'category_name' => $prelimCategory['name'] ?? '',
                            'aspects_prompt' => $aspectsPromptText,
                            'aspects_data' => $categoryService->getRequiredItemSpecifics($prelimCategory['category_id']),
                        ];

                        $sourcesUsed[] = 'ebay_category_aspects';
                        Log::info('Preliminary eBay category found for AI prompting', [
                            'category_id' => $prelimCategory['category_id'],
                            'category_name' => $prelimCategory['name'] ?? '',
                            'aspect_count' => count($ebayAspectsContext['aspects_data']),
                        ]);
                    }
                }
            } catch (\Exception $e) {
                Log::warning('Preliminary category lookup failed (non-blocking)', ['error' => $e->getMessage()]);
            }

            // ========================================
            // STEP 4: SIMPLIFIED Product Research
            // ========================================
            Log::info('SIMPLIFIED: Starting product research (orchestrator handles comprehensive queries)');

            $researchData = null;
            try {
                // Determine research strategy
                $strategy = $this->determineResearchStrategy();

                $researchOptions = [
                    'supplier_context' => $supplierContext,
                    'completeness_data' => $this->queueItem->completeness_data,
                ];

                // Inject eBay aspects context for AI prompting
                if ($ebayAspectsContext) {
                    $researchOptions['ebay_aspects_context'] = $ebayAspectsContext;
                }

                // SIMPLIFIED: Let the enhanced orchestrator handle everything
                if (method_exists($orchestrator, 'researchWithStrategy')) {
                    Log::info('Using enhanced orchestrator with strategy', ['strategy' => $strategy]);
                    $researchData = $orchestrator->researchWithStrategy($this->queueItem, $strategy, $researchOptions);
                } else {
                    Log::info('Using basic research method (enhanced orchestrator will handle comprehensive queries)');
                    $researchData = $orchestrator->research($this->queueItem);
                }

                if ($researchData && ! empty($researchData['product_identity'])) {
                    $sourcesUsed[] = $researchData['data_source'] ?? 'research';
                    Log::info('Product research successful', [
                        'strategy' => $strategy,
                        'data_source' => $researchData['data_source'] ?? 'unknown',
                        'search_method' => $researchData['search_method'] ?? 'unknown',
                        'search_query_used' => $researchData['search_query_used'] ?? 'none',
                        'has_pricing' => ! empty($researchData['market_pricing']),
                    ]);
                } else {
                    $warnings[] = 'Product research returned minimal data';
                    Log::warning('Product research incomplete');
                }
            } catch (\Exception $e) {
                Log::error('Product research failed', ['error' => $e->getMessage()]);
                $warnings[] = 'Product research error: '.$e->getMessage();
            }

            // ========================================
            // STEP 5: Photo Processing (Simplified)
            // ========================================
            $processedPhotos = $this->processPhotos($bgRemoval, $warnings, $sourcesUsed);

            // ========================================
            // STEP 6: Enhanced Confidence & Quality Scoring
            // ========================================
            $scoringResults = $this->calculateEnhancedScoring($researchData, $supplierContext, $validator);

            // ========================================
            // STEP 7: Create AI Analysis Record
            // ========================================
            $aiAnalysisId = null;
            if (! empty($researchData)) {
                try {
                    $aiAnalysis = $this->createAiAnalysisRecord($researchData, $scoringResults, $supplierContext);
                    $aiAnalysisId = $aiAnalysis->id;
                    Log::info('AI analysis record created', ['id' => $aiAnalysisId]);
                } catch (\Exception $e) {
                    Log::error('Failed to create AI analysis record', ['error' => $e->getMessage()]);
                }
            }

            // ========================================
            // STEP 8: Update Queue Item with Enhanced Data
            // ========================================
            $this->updateQueueItemWithResults(
                $researchData,
                $processedPhotos,
                $aiAnalysisId,
                $shippingData,
                $sourcesUsed,
                $warnings,
                $scoringResults,
                $supplierContext,
                $ebayAspectsContext
            );

            Log::info('SIMPLIFIED: Enhanced processing complete', [
                'id' => $this->queueItem->id,
                'confidence' => $scoringResults['confidence_score'],
                'quality_score' => $scoringResults['quality_score'],
                'completeness_score' => $scoringResults['completeness_score'],
                'sources' => $sourcesUsed,
                'processing_time' => now()->diffInSeconds($this->queueItem->ai_started_at),
            ]);

        } catch (\Exception $e) {
            Log::error('SIMPLIFIED: Enhanced processing failed', [
                'id' => $this->queueItem->id,
                'error' => $e->getMessage(),
            ]);

            $this->queueItem->update([
                'ai_analysis_status' => 'failed',
                'processing_status' => 'failed',
                'enriched_data' => [
                    'error' => $e->getMessage(),
                    'failed_at' => now()->toIso8601String(),
                    'attempt' => $this->attempts(),
                ],
            ]);

            throw $e;
        }
    }

    /**
     * SIMPLIFIED: Determine research strategy
     */
    protected function determineResearchStrategy(): string
    {
        if ($this->queueItem->lookup_attempted && ! empty($this->queueItem->existing_product_data)) {
            return 'supplement_existing';
        }

        if ($this->queueItem->manufacturer_part_number || $this->queueItem->product_number || $this->queueItem->brand) {
            return 'comprehensive';
        }

        if ($this->queueItem->barcode) {
            return 'barcode_focused';
        }

        return 'basic';
    }

    /**
     * Analyze supplier context
     */
    protected function analyzeSupplierContext(SupplierPreferenceService $supplierPrefs): array
    {
        if (! $this->queueItem->supplier_id) {
            return ['reliability_boost' => 0, 'priority_boost' => false, 'supplier_name' => null];
        }

        try {
            return $supplierPrefs->getSupplierContext($this->queueItem->supplier_id);
        } catch (\Exception $e) {
            Log::warning('Supplier context analysis failed', ['error' => $e->getMessage()]);

            return ['reliability_boost' => 0, 'priority_boost' => false, 'supplier_name' => null];
        }
    }

    /**
     * Analyze physical measurements for shipping
     */
    protected function analyzePhysicalMeasurements(ShippingClassService $shippingClassifier): array
    {
        if (! $this->queueItem->weight_lbs) {
            return [
                'shipping_class' => 'standard',
                'estimated_cost' => null,
                'fragile' => $this->detectFragileProduct(),
                'oversized' => false,
            ];
        }

        try {
            $dimensions = [
                'length' => $this->queueItem->length_inches,
                'width' => $this->queueItem->width_inches,
                'height' => $this->queueItem->height_inches,
                'weight' => $this->queueItem->weight_lbs,
            ];

            $shippingClass = $shippingClassifier->classify($dimensions);

            return [
                'shipping_class' => $shippingClass,
                'estimated_cost' => $shippingClassifier->estimateCost($dimensions, $shippingClass),
                'dimensions' => $dimensions,
                'fragile' => $this->detectFragileProduct(),
                'oversized' => $shippingClass === 'oversized' || $shippingClass === 'freight',
            ];
        } catch (\Exception $e) {
            Log::warning('Physical measurement analysis failed', ['error' => $e->getMessage()]);

            return [
                'shipping_class' => 'standard',
                'estimated_cost' => null,
                'fragile' => $this->detectFragileProduct(),
                'oversized' => false,
            ];
        }
    }

    /**
     * Detect if product is fragile based on user notes and other indicators
     */
    protected function detectFragileProduct(): bool
    {
        $notes = strtolower($this->queueItem->user_notes ?? '');
        $fragileKeywords = ['fragile', 'glass', 'ceramic', 'delicate', 'handle with care', 'breakable'];

        foreach ($fragileKeywords as $keyword) {
            if (str_contains($notes, $keyword)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Process photos with condition-based presets
     */
    protected function processPhotos(BackgroundRemovalService $bgRemoval, array &$warnings, array &$sourcesUsed): array
    {
        $processedPhotos = $this->queueItem->photos ?? [];

        // Capture pristine originals before removal so revert works post-intake.
        $this->originalPhotos = is_array($processedPhotos) ? $processedPhotos : [];

        if (empty($processedPhotos) || ! $bgRemoval->isAvailable()) {
            return $processedPhotos;
        }

        try {
            // Ensure photos is an array
            if (is_string($processedPhotos)) {
                $processedPhotos = json_decode($processedPhotos, true) ?? [];
            }

            if (is_array($processedPhotos) && count($processedPhotos) > 0) {
                $croppingOption = $this->getPhotoProcessingOptions();

                // Use enhanced cropping if available
                if (method_exists($bgRemoval, 'processMultipleWithCropping')) {
                    $processedPhotos = $bgRemoval->processMultipleWithCropping($processedPhotos, $croppingOption);
                } else {
                    $processedPhotos = $bgRemoval->processMultiple($processedPhotos);
                }

                $sourcesUsed[] = 'background_removal';
                Log::info('Photos processed', [
                    'count' => count($processedPhotos),
                    'cropping_option' => $croppingOption,
                ]);
            }
        } catch (\Exception $e) {
            Log::warning('Photo processing failed', ['error' => $e->getMessage()]);
            $warnings[] = 'Photo processing error';
        }

        return $processedPhotos;
    }

    /**
     * Get photo processing options based on product condition
     */
    protected function getPhotoProcessingOptions(): string
    {
        $condition = strtolower($this->queueItem->condition ?? '');

        if (str_contains($condition, 'new')) {
            return 'tight';
        }

        if (str_contains($condition, 'parts') || str_contains($condition, 'repair')) {
            return 'padded';
        }

        return 'default';
    }

    /**
     * Calculate enhanced confidence and quality scoring
     */
    protected function calculateEnhancedScoring(?array $researchData, array $supplierContext, ProductDataValidator $validator): array
    {
        try {
            // Base confidence from research quality
            $baseConfidence = $this->calculateBaseConfidence($researchData ?? []);

            // Completeness boost
            $completenessScore = $this->getCompletenessScore();
            $completenessBoost = $this->calculateCompletenessBoost($completenessScore);

            // Supplier reliability boost
            $supplierBoost = $supplierContext['reliability_boost'] ?? 0;

            // Final confidence (capped at 100%)
            $finalConfidence = min(1.0, $baseConfidence + $completenessBoost + $supplierBoost);

            // Quality scoring with completeness enhancement
            $baseQualityScore = 50; // Default
            if (! empty($researchData)) {
                try {
                    $flattenedData = $this->flattenForValidation($researchData);
                    $baseQualityScore = $validator->calculateQualityScore($flattenedData);
                } catch (\Exception $e) {
                    Log::warning('Quality score calculation failed', ['error' => $e->getMessage()]);
                }
            }

            // Apply completeness factor to quality score (up to 20% boost)
            $adjustedQualityScore = $baseQualityScore * (1 + ($completenessScore / 100) * 0.2);

            return [
                'confidence_score' => round($finalConfidence, 2),
                'confidence_breakdown' => [
                    'base_confidence' => $baseConfidence,
                    'completeness_boost' => $completenessBoost,
                    'supplier_boost' => $supplierBoost,
                    'final_confidence' => $finalConfidence,
                ],
                'quality_score' => min(100, round($adjustedQualityScore)),
                'completeness_score' => $completenessScore,
            ];

        } catch (\Exception $e) {
            Log::error('Enhanced scoring calculation failed', ['error' => $e->getMessage()]);

            return [
                'confidence_score' => 0.5,
                'confidence_breakdown' => null,
                'quality_score' => 50,
                'completeness_score' => $this->getCompletenessScore(),
            ];
        }
    }

    /**
     * Get completeness score from completeness_data
     */
    protected function getCompletenessScore(): float
    {
        if (! $this->queueItem->completeness_data) {
            return 0;
        }

        $completenessData = $this->queueItem->completeness_data;

        if (isset($completenessData['completeness_score'])) {
            return (float) $completenessData['completeness_score'];
        }

        // Calculate from components
        $totalComponents = 0;
        $foundComponents = 0;

        foreach ($completenessData as $key => $value) {
            if ($key === 'completeness_score') {
                continue;
            }
            $totalComponents++;
            if ($value === true || $value === 'true' || $value === 1) {
                $foundComponents++;
            }
        }

        return $totalComponents > 0 ? round(($foundComponents / $totalComponents) * 100, 1) : 0;
    }

    /**
     * Calculate completeness boost for confidence
     */
    protected function calculateCompletenessBoost(float $completenessScore): float
    {
        if ($completenessScore < 60) {
            return 0;
        }

        return round(($completenessScore - 60) / 400, 3); // Max 0.10 boost at 100%
    }

    /**
     * Calculate base confidence from research data
     */
    protected function calculateBaseConfidence(array $researchData): float
    {
        if (empty($researchData)) {
            return 0.3;
        }

        $confidence = 0.5;

        if (! empty($researchData['product_identity']['brand'])) {
            $confidence += 0.1;
        }
        if (! empty($researchData['product_identity']['model'])) {
            $confidence += 0.1;
        }
        if (! empty($researchData['market_pricing']['recommended_list_price'])) {
            $confidence += 0.1;
        }
        if (! empty($researchData['product_details']['description'])) {
            $confidence += 0.1;
        }

        return round(min(0.9, $confidence), 2);
    }

    /**
     * Flatten data for validation
     */
    protected function flattenForValidation(array $data): array
    {
        return [
            'title' => $data['listing_optimization']['suggested_title'] ?? '',
            'description' => $data['product_details']['description'] ?? '',
            'brand' => $this->queueItem->brand ?? $data['product_identity']['brand'] ?? '',
            'model' => $data['product_identity']['model'] ?? '',
            'mpn' => $this->queueItem->manufacturer_part_number,
            'condition' => $this->queueItem->condition,
            'images' => $this->queueItem->photos ?? [],
            'weight' => $this->queueItem->weight_lbs,
            'completeness_score' => $this->getCompletenessScore(),
        ];
    }

    /**
     * Create merged product fields structure for downstream consumers.
     * This normalizes research data into a consistent format expected by
     * listing jobs and other processors.
     */
    protected function createMergedProductFields(array $researchData): array
    {
        return [
            'product_identity' => [
                'title' => $researchData['product_identity']['title'] ?? null,
                'brand' => $researchData['product_identity']['brand'] ?? null,
                'model' => $researchData['product_identity']['model'] ?? null,
                'category' => $researchData['product_identity']['category'] ?? null,
                'manufacturer_part_number' => $researchData['product_identity']['mpn']
                    ?? $this->queueItem->manufacturer_part_number ?? null,
                'upc' => $this->sanitizeBarcode($researchData['product_identity']['upc']
                    ?? $this->queueItem->barcode ?? null),
                'ean' => $this->sanitizeBarcode($researchData['product_identity']['ean'] ?? null),
            ],
            'product_details' => [
                'description' => $researchData['product_details']['description'] ?? null,
                'key_features' => $researchData['product_details']['key_features'] ?? [],
                'specifications' => $researchData['product_details']['specifications'] ?? [],
            ],
            'listing_optimization' => [
                'suggested_title' => $researchData['listing_optimization']['suggested_title'] ?? null,
                'primary_keywords' => $researchData['listing_optimization']['primary_keywords'] ?? [],
                'bullet_points' => $researchData['listing_optimization']['bullet_points'] ?? [],
            ],
            'market_pricing' => $researchData['market_pricing'] ?? [],
        ];
    }

    private function sanitizeBarcode(?string $value): ?string
    {
        $cleaned = preg_replace('/[^0-9]/', '', (string) $value);

        return ! empty($cleaned) ? $cleaned : null;
    }

    /**
     * Create AI analysis record with enhanced fields
     */
    protected function createAiAnalysisRecord(array $researchData, array $scoringResults, array $supplierContext = []): ProductAiAnalysis
    {
        $identity = $researchData['product_identity'] ?? [];
        $details = $researchData['product_details'] ?? [];

        $createData = [
            'queue_item_id' => $this->queueItem->id,
            'analysis_type' => $researchData['data_source'] ?? 'enhanced_research',
            'ai_provider' => $this->determineProvider($researchData),
            'model_version' => 'simplified_enhanced_v1',
            'detected_category' => $identity['category'] ?? null,
            'detected_brand' => $identity['brand'] ?? $this->queueItem->brand,
            'detected_model' => $identity['model'] ?? $this->queueItem->model_number,
            'ai_description' => $details['description'] ?? null,
            'ai_specifications' => $details['specifications'] ?? [],
            'confidence_score' => $scoringResults['confidence_score'] ?? 0.5,
            'raw_response' => $researchData,
            'verification_status' => 'pending',
        ];

        // Add enhanced fields if columns exist
        if (Schema::hasColumn('product_ai_analyses', 'completeness_score')) {
            $createData['completeness_score'] = $scoringResults['completeness_score'] ?? null;
        }
        if (Schema::hasColumn('product_ai_analyses', 'quality_score')) {
            $createData['quality_score'] = $scoringResults['quality_score'] ?? null;
        }
        if (Schema::hasColumn('product_ai_analyses', 'supplier_context')) {
            $createData['supplier_context'] = ! empty($supplierContext) ? $supplierContext : null;
        }
        if (Schema::hasColumn('product_ai_analyses', 'enhanced_features')) {
            $createData['enhanced_features'] = [
                'fragility_detection' => $this->detectFragileProduct(),
                'condition_based_photos' => true,
                'completeness_boost_applied' => ($scoringResults['completeness_score'] ?? 0) >= 60,
            ];
        }

        return ProductAiAnalysis::create($createData);
    }

    /**
     * Determine AI provider
     */
    protected function determineProvider(array $researchData): string
    {
        $searchMethod = $researchData['search_method'] ?? 'unknown';

        return match ($searchMethod) {
            'comprehensive_ebay' => 'ebay_browse_enhanced',
            'comprehensive_web' => 'google_gemini_enhanced',
            'comprehensive_basic' => 'gemini_basic_enhanced',
            default => 'enhanced_pipeline',
        };
    }

    /**
     * Update queue item with all results
     */
    protected function updateQueueItemWithResults(
        ?array $researchData,
        array $processedPhotos,
        ?int $aiAnalysisId,
        array $shippingData,
        array $sourcesUsed,
        array $warnings,
        array $scoringResults,
        array $supplierContext = [],
        ?array $ebayAspectsContext = null
    ): void {

        $enrichedData = [
            'research_data' => $researchData,
            'merged_product_fields' => $researchData ? $this->createMergedProductFields($researchData) : null,
            'preliminary_ebay_category' => $ebayAspectsContext ? [
                'category_id' => $ebayAspectsContext['category_id'],
                'category_name' => $ebayAspectsContext['category_name'] ?? '',
            ] : null,
            'supplier_context' => $supplierContext,
            'shipping_data' => $shippingData,
            'sources_used' => $sourcesUsed,
            'warnings' => $warnings,
            'confidence_breakdown' => $scoringResults['confidence_breakdown'],
            // Pristine pre-removal photos, parallel to processed photos by index,
            // so ProductImage rows can record metadata.original_url for revert.
            'original_photos' => $this->originalPhotos,
        ];

        DB::transaction(function () use ($enrichedData, $processedPhotos, $aiAnalysisId, $shippingData, $sourcesUsed, $scoringResults, $researchData) {
            $updateData = [
                'ai_analysis_status' => 'complete',
                'ai_completed_at' => now(),
                'ai_confidence_score' => $scoringResults['confidence_score'],
                'enriched_data' => $enrichedData,
                'ai_analysis_id' => $aiAnalysisId,
                'photos' => $processedPhotos,
                'processing_status' => $this->determineProcessingStatus($scoringResults, $researchData),
                'quality_score' => $scoringResults['quality_score'],
                'data_sources' => $sourcesUsed,
                'primary_source' => $sourcesUsed[0] ?? null,
                'last_processed_at' => now(),
                'enriched_at' => now(),
                'validated_at' => now(),
            ];

            // Add enhanced fields if they exist
            if (Schema::hasColumn('product_intake_queue', 'completeness_score')) {
                $updateData['completeness_score'] = $scoringResults['completeness_score'];
            }
            if (Schema::hasColumn('product_intake_queue', 'confidence_breakdown')) {
                $updateData['confidence_breakdown'] = $scoringResults['confidence_breakdown'];
            }
            if (Schema::hasColumn('product_intake_queue', 'suggested_shipping_class')) {
                $updateData['suggested_shipping_class'] = $shippingData['shipping_class'] ?? null;
            }
            if (Schema::hasColumn('product_intake_queue', 'estimated_shipping_cost')) {
                $updateData['estimated_shipping_cost'] = $shippingData['estimated_cost'] ?? null;
            }

            $this->queueItem->update($updateData);
        });
    }

    /**
     * Determine processing status
     */
    protected function determineProcessingStatus(array $scoringResults, ?array $researchData = null): string
    {
        $confidence = $scoringResults['confidence_score'] ?? 0;
        $qualityScore = $scoringResults['quality_score'] ?? 0;

        $status = 'needs_major_review';
        if ($confidence >= 0.8 && $qualityScore >= 80) {
            $status = 'ready_for_listing';
        } elseif ($confidence >= 0.6 && $qualityScore >= 60) {
            $status = 'needs_minor_review';
        } elseif ($confidence >= 0.4) {
            $status = 'needs_review';
        }

        // Guardrail: never let a hallucinated or unverified item auto-pass to listing.
        // If Stage 1 was not confident, the product was not identified, or no relevant
        // images cleared the floor, cap the status at needs_review so a human checks it.
        if ($this->requiresHumanReview($researchData)) {
            $passThrough = ['ready_for_listing', 'needs_minor_review'];
            if (in_array($status, $passThrough, true)) {
                Log::info('Capping processing status to needs_review (low confidence / no relevant images)', [
                    'queue_item_id' => $this->queueItem->id,
                    'would_have_been' => $status,
                ]);

                return 'needs_review';
            }
        }

        return $status;
    }

    /**
     * Decide whether research output must be human-reviewed regardless of numeric score:
     * low/none identification confidence, product not identified, or no relevant images.
     */
    protected function requiresHumanReview(?array $researchData): bool
    {
        if (empty($researchData)) {
            return false;
        }

        $idResult = $researchData['stage1_identification']['identification_results'] ?? [];
        $confidence = strtolower((string) ($idResult['identification_confidence'] ?? ''));
        $productIdentified = $idResult['product_identified'] ?? null;

        if (in_array($confidence, ['low', 'none'], true)) {
            return true;
        }

        if ($productIdentified === false) {
            return true;
        }

        $warnings = $researchData['warnings'] ?? [];
        if (in_array('no_relevant_images_found', $warnings, true)) {
            return true;
        }

        return false;
    }

    /**
     * Handle job failure with enhanced logging
     */
    public function failed(\Throwable $exception): void
    {
        Log::error('SIMPLIFIED: Processing failed permanently', [
            'id' => $this->queueItem->id,
            'attempts' => $this->attempts(),
            'error' => $exception->getMessage(),
            'supplier_id' => $this->queueItem->supplier_id,
            'manufacturer_part_number' => $this->queueItem->manufacturer_part_number,
            'completeness_score' => $this->getCompletenessScore(),
        ]);

        $this->queueItem->update([
            'ai_analysis_status' => 'failed',
            'processing_status' => 'failed',
            'enriched_data' => [
                'error' => $exception->getMessage(),
                'failed_permanently_at' => now()->toIso8601String(),
                'total_attempts' => $this->attempts(),
                'supplier_id' => $this->queueItem->supplier_id,
                'manufacturer_part_number' => $this->queueItem->manufacturer_part_number,
                'completeness_score' => $this->getCompletenessScore(),
            ],
        ]);

        if ($this->queueItem->organization_id) {
            app(\App\Services\NotificationService::class)->dispatchEvent(
                eventKey: 'intake.item.failed',
                organizationId: (int) $this->queueItem->organization_id,
                args: [
                    (string) $this->queueItem->id,
                    $this->queueItem->title ?? $this->queueItem->manufacturer_part_number,
                    (string) $exception->getMessage(),
                ],
                relatedId: (string) $this->queueItem->id,
            );
        }
    }
}
