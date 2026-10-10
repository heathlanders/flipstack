<?php

namespace App\Services;

use App\Models\Catalog\ProductIntakeQueue;
use Illuminate\Support\Facades\Log;

/**
 * ENHANCED Two-Stage Product Research Orchestrator
 *
 * This orchestrator coordinates a sophisticated two-stage research process:
 *
 * STAGE 1: Product Identification & Verification
 * - Identifies what the product actually is using category-specific prompts
 * - Provides reasoning and confidence levels
 * - Finds source links and references
 * - Determines optimal search strategy for Stage 2
 *
 * STAGE 2: Comprehensive Research & Listing Optimization
 * - Uses Stage 1 identification to guide targeted research
 * - Focuses on pricing, market data, and listing optimization
 * - Integrates eBay data when available
 * - Produces listing-ready content
 *
 * Key Improvements:
 * - Category-specific identification strategies
 * - Confidence-based routing between research methods
 * - Integration of identification results into comprehensive research
 * - Enhanced search strategies based on product category
 * - Detailed reasoning and source validation
 */
class ProductResearchOrchestrator
{
    public function __construct(
        protected EbayBrowseService $ebayBrowse,
        protected EbaySoldListingsService $ebaySold,
        protected ProductIdentificationService $identification,
        protected GeminiProductResearch $research,
        protected ProductDataValidator $validator
    ) {}

    /**
     * MAIN ENTRY POINT: Two-Stage Research Process
     */
    public function researchWithTwoStageProcess(ProductIntakeQueue $item, array $options = []): array
    {
        Log::info('Starting Two-Stage Enhanced Product Research', [
            'queue_item_id' => $item->id,
            'brand' => $item->brand,
            'manufacturer_part_number' => $item->manufacturer_part_number,
            'has_existing_data' => ! empty($item->existing_product_data),
        ]);

        // ==============================================
        // STAGE 1: Product Identification & Verification
        // ==============================================

        Log::info('STAGE 1: Product Identification Starting');

        // Ground identification in reality: search the real marketplace by the raw
        // identifier FIRST, then feed those listing titles into Stage 1 as evidence.
        // This is category-agnostic and free — it keeps the model from hallucinating
        // an identity from a similarly-named brand before it has seen any real data.
        $marketEvidence = $this->gatherMarketEvidence($item);

        $identificationResults = null;
        if ($this->identification->isAvailable()) {
            try {
                $identificationResults = $this->identification->identify($item, $marketEvidence);

                if ($identificationResults) {
                    $confidence = $identificationResults['identification_results']['identification_confidence'] ?? 'none';
                    $identified = $identificationResults['identification_results']['product_identified'] ?? false;

                    Log::info('STAGE 1: Identification Complete', [
                        'product_identified' => $identified,
                        'confidence' => $confidence,
                        'category' => $identificationResults['product_category'] ?? 'unknown',
                    ]);
                } else {
                    Log::warning('STAGE 1: Identification returned no results');
                }
            } catch (\Exception $e) {
                Log::error('STAGE 1: Identification failed', ['error' => $e->getMessage()]);
            }
        } else {
            Log::warning('STAGE 1: Identification service unavailable');
        }

        // ==============================================
        // STAGE 2: Comprehensive Research & Optimization
        // ==============================================

        Log::info('STAGE 2: Comprehensive Research Starting');

        // Gather enhanced identifiers using Stage 1 results
        $identifiers = $this->gatherEnhancedIdentifiers($item, $identificationResults);

        // Choose research strategy based on identification confidence and eBay availability
        $researchResult = $this->executeStage2Research($item, $identifiers, $identificationResults, $options);

        // ==============================================
        // FINAL PROCESSING & VALIDATION
        // ==============================================

        $finalResult = $this->finalizeResults($researchResult, $identificationResults, $item, $options);

        Log::info('Two-Stage Research Complete', [
            'stage1_confidence' => $identificationResults['identification_results']['identification_confidence'] ?? 'none',
            'stage2_method' => $finalResult['search_method'] ?? 'unknown',
            'final_quality_score' => $finalResult['quality_score'] ?? 0,
            'is_listing_ready' => $finalResult['is_ebay_ready'] ?? false,
        ]);

        return $finalResult;
    }

    /**
     * STAGE 2: Execute comprehensive research based on identification results
     */
    protected function executeStage2Research(ProductIntakeQueue $item, array $identifiers, ?array $identificationResults, array $options): array
    {
        $confidence = $identificationResults['identification_results']['identification_confidence'] ?? 'none';
        $productIdentified = $identificationResults['identification_results']['product_identified'] ?? false;

        Log::info('STAGE 2: Choosing research strategy', [
            'identification_confidence' => $confidence,
            'product_identified' => $productIdentified,
            'ebay_available' => $this->ebayBrowse->isAvailable(),
        ]);

        // STRATEGY 1: High confidence identification + eBay search
        if ($confidence === 'high' && $productIdentified && $this->ebayBrowse->isAvailable()) {
            Log::info('STRATEGY 1: High-confidence eBay search');

            $ebayResult = $this->executeHighConfidenceEbaySearch($item, $identifiers, $identificationResults, $options);
            if ($ebayResult) {
                $ebayResult['search_method'] = 'high_confidence_ebay';

                return $ebayResult;
            }
        }

        // STRATEGY 2: Medium/High confidence + Comprehensive web research
        if (in_array($confidence, ['high', 'medium']) && $productIdentified) {
            Log::info('STRATEGY 2: Confident web research');

            $webResult = $this->executeConfidentWebResearch($item, $identifiers, $identificationResults, $options);
            if ($webResult && $this->hasSubstantialData($webResult)) {
                $webResult['search_method'] = 'confident_web_research';

                return $webResult;
            }
        }

        // STRATEGY 3: Any identification + Standard eBay approach
        if ($productIdentified && $this->ebayBrowse->isAvailable()) {
            Log::info('STRATEGY 3: Standard eBay with identification');

            $standardResult = $this->executeStandardEbaySearch($item, $identifiers, $identificationResults, $options);
            if ($standardResult) {
                $standardResult['search_method'] = 'standard_ebay_with_id';

                return $standardResult;
            }
        }

        // STRATEGY 4: Fallback web research (even with low/no confidence)
        if ($this->research->isAvailable()) {
            Log::info('STRATEGY 4: Fallback web research');

            $fallbackResult = $this->executeFallbackWebResearch($item, $identifiers, $identificationResults, $options);
            if ($fallbackResult) {
                $fallbackResult['search_method'] = 'fallback_web_research';

                return $fallbackResult;
            }
        }

        // STRATEGY 5: Final fallback - enhanced template
        Log::warning('STRATEGY 5: All strategies failed - creating enhanced template');

        return $this->createEnhancedTemplate($identifiers, $identificationResults, $item);
    }

    /**
     * High confidence eBay search using identification results
     */
    protected function executeHighConfidenceEbaySearch(ProductIntakeQueue $item, array $identifiers, array $identificationResults, array $options = []): ?array
    {
        try {
            // Use Stage 1 search strategy if available
            $searchStrategy = $identificationResults['search_strategy_for_stage2'] ?? [];
            $primaryTerms = $searchStrategy['primary_search_terms'] ?? [];

            // Build optimized search query
            $searchQuery = $this->buildOptimizedSearchQuery($item, $identificationResults, $primaryTerms);

            Log::info('High-confidence eBay search', [
                'search_query' => $searchQuery,
                'primary_terms' => $primaryTerms,
            ]);

            $activeListings = $this->ebayBrowse->search([
                'query' => $searchQuery,
                'brand' => $item->brand,
                'keywords' => $item->keywords,
            ]);

            if ($activeListings->hasListings()) {
                $ebayData = [
                    'sold_listings' => [],
                    'active_listings' => $activeListings->activeListings,
                    'sold_count' => 0,
                    'active_count' => $activeListings->getActiveCount(),
                ];

                $ebayAspectsContext = $options['ebay_aspects_context'] ?? null;

                return $this->research->enrichWithEbayDataAndIdentification(
                    $identificationResults,
                    $identifiers,
                    $ebayData,
                    $this->detectProductType($item->user_notes),
                    $item->brand,
                    $item->keywords,
                    $ebayAspectsContext
                );
            }
        } catch (\Exception $e) {
            Log::error('High-confidence eBay search failed', ['error' => $e->getMessage()]);
        }

        return null;
    }

    /**
     * Confident web research using identification results
     */
    protected function executeConfidentWebResearch(ProductIntakeQueue $item, array $identifiers, array $identificationResults, array $options = []): ?array
    {
        try {
            $ebayAspectsContext = $options['ebay_aspects_context'] ?? null;

            return $this->research->researchWithIdentification(
                $identificationResults,
                $identifiers,
                $this->detectProductType($item->user_notes),
                $item->brand,
                $item->keywords,
                $ebayAspectsContext
            );
        } catch (\Exception $e) {
            Log::error('Confident web research failed', ['error' => $e->getMessage()]);

            return null;
        }
    }

    /**
     * Standard eBay search with identification context
     */
    protected function executeStandardEbaySearch(ProductIntakeQueue $item, array $identifiers, array $identificationResults, array $options = []): ?array
    {
        try {
            $searchQuery = $identifiers['comprehensive_query'] ?? $identifiers['manufacturer_part_number'] ?? '';

            $activeListings = $this->ebayBrowse->search([
                'query' => $searchQuery,
                'brand' => $item->brand,
                'keywords' => $item->keywords,
            ]);

            if ($activeListings->hasListings()) {
                $ebayData = [
                    'sold_listings' => [],
                    'active_listings' => $activeListings->activeListings,
                    'sold_count' => 0,
                    'active_count' => $activeListings->getActiveCount(),
                ];

                $ebayAspectsContext = $options['ebay_aspects_context'] ?? null;

                return $this->research->enrichWithEbayDataAndIdentification(
                    $identificationResults,
                    $identifiers,
                    $ebayData,
                    $this->detectProductType($item->user_notes),
                    $item->brand,
                    $item->keywords,
                    $ebayAspectsContext
                );
            }
        } catch (\Exception $e) {
            Log::error('Standard eBay search failed', ['error' => $e->getMessage()]);
        }

        return null;
    }

    /**
     * Fallback web research
     */
    protected function executeFallbackWebResearch(ProductIntakeQueue $item, array $identifiers, ?array $identificationResults, array $options = []): ?array
    {
        try {
            $ebayAspectsContext = $options['ebay_aspects_context'] ?? null;

            return $this->research->researchWithIdentification(
                $identificationResults,
                $identifiers,
                $this->detectProductType($item->user_notes),
                $item->brand,
                $item->keywords,
                $ebayAspectsContext
            );
        } catch (\Exception $e) {
            Log::error('Fallback web research failed', ['error' => $e->getMessage()]);

            return null;
        }
    }

    /**
     * Build optimized search query using identification results
     */
    protected function buildOptimizedSearchQuery(ProductIntakeQueue $item, array $identificationResults, array $primaryTerms): string
    {
        $queryParts = [];

        // Add brand first if available
        if (! empty($item->brand)) {
            $queryParts[] = $item->brand;
        }

        // Add primary terms from identification
        foreach ($primaryTerms as $term) {
            if (! in_array($term, $queryParts, true)) {
                $queryParts[] = $term;
            }
        }

        // Add identified complete part number if available
        $mostLikely = $identificationResults['identification_results']['most_likely_product'] ?? [];
        if (! empty($mostLikely['complete_part_number'])) {
            $queryParts[] = $mostLikely['complete_part_number'];
        }

        // Add original identifiers as fallback
        foreach (['manufacturer_part_number', 'product_number', 'model_number'] as $field) {
            if (! empty($item->$field) && ! in_array($item->$field, $queryParts, true)) {
                $queryParts[] = $item->$field;
            }
        }

        return implode(' ', array_filter($queryParts));
    }

    /**
     * Pre-Stage-1 grounding: search eBay by the raw exact identifier and return the
     * real active-listing rows (title + image). Used as authoritative evidence for
     * identification so the model identifies from reality, not parametric memory.
     */
    protected function gatherMarketEvidence(ProductIntakeQueue $item): array
    {
        if (! $this->ebayBrowse->isAvailable()) {
            return [];
        }

        // No identifier to search means no reliable evidence — skip rather than guess.
        if (empty($item->manufacturer_part_number) && empty($item->product_number)
            && empty($item->model_number) && empty($item->barcode)) {
            return [];
        }

        try {
            $result = $this->ebayBrowse->search([
                'brand' => $item->brand,
                'manufacturer_part_number' => $item->manufacturer_part_number,
                'product_number' => $item->product_number,
                'model_number' => $item->model_number,
                'keywords' => $item->keywords ?? [],
            ]);

            $listings = $result->activeListings ?? [];

            Log::info('Pre-Stage-1 market evidence gathered', [
                'queue_item_id' => $item->id,
                'evidence_count' => count($listings),
            ]);

            return $listings;
        } catch (\Exception $e) {
            Log::warning('Market evidence gathering failed (non-blocking)', ['error' => $e->getMessage()]);

            return [];
        }
    }

    /**
     * Gather enhanced identifiers using Stage 1 results
     */
    protected function gatherEnhancedIdentifiers(ProductIntakeQueue $item, ?array $identificationResults): array
    {
        $identifiers = array_filter([
            'manufacturer_part_number' => $item->manufacturer_part_number,
            'product_number' => $item->product_number,
            'barcode' => $item->barcode,
            'user_notes' => $item->user_notes,
            'brand' => $item->brand,
            'model_number' => $item->model_number ?? null,
            'series' => $item->series ?? null,
        ]);

        // Enhanced with Stage 1 identification results
        if (! empty($identificationResults)) {
            $mostLikely = $identificationResults['identification_results']['most_likely_product'] ?? [];

            // Add confirmed identifiers from Stage 1
            if (! empty($mostLikely['complete_part_number'])) {
                $identifiers['confirmed_part_number'] = $mostLikely['complete_part_number'];
            }

            if (! empty($mostLikely['product_name'])) {
                $identifiers['confirmed_product_name'] = $mostLikely['product_name'];
            }

            // Use Stage 1 search strategy
            $searchStrategy = $identificationResults['search_strategy_for_stage2'] ?? [];
            if (! empty($searchStrategy['primary_search_terms'])) {
                $identifiers['stage1_primary_terms'] = $searchStrategy['primary_search_terms'];
            }
        }

        // Build comprehensive search query
        $searchParts = [];

        if (! empty($item->brand)) {
            $searchParts[] = trim($item->brand);
        }

        // Prioritize confirmed data from Stage 1
        if (! empty($identifiers['confirmed_part_number'])) {
            $searchParts[] = $identifiers['confirmed_part_number'];
        } elseif (! empty($item->manufacturer_part_number)) {
            $searchParts[] = trim($item->manufacturer_part_number);
        }

        if (! empty($item->model_number)) {
            $searchParts[] = trim($item->model_number);
        }

        if (! empty($item->product_number)) {
            $searchParts[] = trim($item->product_number);
        }

        // Add Stage 1 primary terms
        if (! empty($identifiers['stage1_primary_terms'])) {
            foreach ($identifiers['stage1_primary_terms'] as $term) {
                if (! in_array($term, $searchParts, true)) {
                    $searchParts[] = $term;
                }
            }
        }

        $identifiers['comprehensive_query'] = implode(' ', array_unique(array_filter($searchParts)));

        Log::info('Enhanced identifiers built', [
            'comprehensive_query' => $identifiers['comprehensive_query'],
            'has_stage1_data' => ! empty($identificationResults),
            'confirmed_part_number' => $identifiers['confirmed_part_number'] ?? 'none',
        ]);

        return $identifiers;
    }

    /**
     * Finalize results with validation and metadata
     */
    protected function finalizeResults(array $researchResult, ?array $identificationResults, ProductIntakeQueue $item, array $options): array
    {
        // Add Stage 1 identification results if not already included
        if (! empty($identificationResults) && empty($researchResult['stage1_identification'])) {
            $researchResult['stage1_identification'] = $identificationResults;
        }

        // Add research metadata
        $researchResult['two_stage_process'] = true;
        $researchResult['research_timestamp'] = now()->toISOString();
        $researchResult['queue_item_id'] = $item->id;

        // Enrich/sanitize UPC — scanned barcode takes priority, then eBay listings
        $researchResult = $this->enrichUpcFromSources($researchResult, $item);

        // Enhanced context from options
        if (! empty($options['supplier_context'])) {
            $researchResult = $this->enhanceWithSupplierContext($researchResult, $options['supplier_context']);
        }

        if (! empty($options['completeness_data'])) {
            $researchResult = $this->enhanceWithCompletenessData($researchResult, $options['completeness_data']);
        }

        // Validate results
        if ($this->validator) {
            try {
                $validation = $this->validator->validateForEbay($researchResult);
                $qualityScore = $this->validator->calculateQualityScore($researchResult);

                $researchResult['validation'] = $validation->toArray();
                $researchResult['quality_score'] = $qualityScore;
                $researchResult['is_ebay_ready'] = $validation->isComplete();

                Log::info('Final validation complete', [
                    'quality_score' => $qualityScore,
                    'is_complete' => $validation->isComplete(),
                    'missing_fields' => count($validation->getMissingRequired()),
                ]);
            } catch (\Exception $e) {
                Log::warning('Validation failed', ['error' => $e->getMessage()]);
            }
        }

        return $researchResult;
    }

    /**
     * Enrich UPC/EAN from trusted sources with priority ordering:
     * 1. Scanned barcode from queue item (highest trust — user confirmed)
     * 2. eBay listing item specifics
     * 3. Sanitize/nullify any garbage placeholder text from Gemini
     */
    private function enrichUpcFromSources(array $result, ProductIntakeQueue $item): array
    {
        // Priority 1: scanned barcode is locked in — always use it
        $scannedBarcode = preg_replace('/[^0-9]/', '', (string) ($item->barcode ?? ''));
        if (! empty($scannedBarcode)) {
            $result['product_identity']['upc'] = $scannedBarcode;
            $result['product_identity']['upc_source'] = 'scanned_barcode';

            return $result;
        }

        // Priority 2: check if Gemini returned a valid numeric UPC
        $geminiUpc = preg_replace('/[^0-9]/', '', (string) ($result['product_identity']['upc'] ?? ''));
        if (! empty($geminiUpc)) {
            $result['product_identity']['upc'] = $geminiUpc;

            return $result;
        }

        // Priority 3: scan eBay active listings for a UPC from item specifics
        $listings = $result['market_pricing']['ebay_active_listings'] ?? [];
        foreach ($listings as $listing) {
            $listingUpc = preg_replace('/[^0-9]/', '', (string) ($listing['upc'] ?? ''));
            if (! empty($listingUpc)) {
                $result['product_identity']['upc'] = $listingUpc;
                $result['product_identity']['upc_source'] = 'ebay_listing';

                return $result;
            }
        }

        // No valid UPC found — nullify any garbage placeholder text
        $result['product_identity']['upc'] = null;

        return $result;
    }

    /**
     * Create enhanced template with available identification data
     */
    protected function createEnhancedTemplate(array $identifiers, ?array $identificationResults, ProductIntakeQueue $item): array
    {
        $template = [
            'product_identity' => [
                'brand' => $item->brand ?? 'Unknown',
                'model' => $item->model_number ?? '',
                'manufacturer_part_number' => $identifiers['manufacturer_part_number'] ?? '',
                'title' => 'Product Requires Manual Research',
                'category' => '',
                'subcategory' => '',
            ],
            'product_details' => [
                'condition' => $item->condition ?? 'unknown',
                'description' => 'Product information not available through automated research',
                'key_features' => [],
                'specifications' => [],
                'user_notes' => $item->user_notes ?? '',
            ],
            'market_pricing' => [
                'ebay_sold_listings' => [],
                'ebay_active_listings' => [],
                'recommended_list_price' => 0,
                'pricing_confidence' => 'none',
            ],
            'listing_optimization' => [
                'suggested_title' => ($identifiers['comprehensive_query'] ?? 'Unknown Product').' - Manual Research Required',
                'primary_keywords' => $identifiers['stage1_primary_terms'] ?? [],
                'bullet_points' => [],
            ],
            'data_sources' => [],
            'data_source' => 'enhanced_template',
            'search_method' => 'two_stage_template',
            'user_action_required' => true,
            'manual_research_needed' => true,
        ];

        // Enhance with Stage 1 identification data if available
        if (! empty($identificationResults)) {
            $template['stage1_identification'] = $identificationResults;

            $idResult = $identificationResults['identification_results'] ?? [];
            $mostLikely = $idResult['most_likely_product'] ?? [];

            if (! empty($mostLikely['product_name'])) {
                $template['product_identity']['title'] = $mostLikely['product_name'].' - Requires Verification';
            }

            if (! empty($mostLikely['complete_part_number'])) {
                $template['product_identity']['manufacturer_part_number'] = $mostLikely['complete_part_number'];
            }

            if (! empty($identificationResults['source_links'])) {
                $template['identification_sources'] = $identificationResults['source_links'];
            }

            $template['identification_available'] = true;
            $template['identification_confidence'] = $idResult['identification_confidence'] ?? 'none';
        }

        return $template;
    }

    // Helper methods (same as before but enhanced)
    protected function detectProductType(?string $userNotes): string
    {
        if (! $userNotes) {
            return 'complete_unit';
        }

        $notes = strtolower($userNotes);

        if (preg_match('/\b(complete|full|entire|whole|unit|system|terminal|assembled)\b/', $notes)) {
            return 'complete_unit';
        }

        if (preg_match('/\b(part|component|board|motherboard|replacement|spare|module|card)\b/', $notes)) {
            return 'replacement_part';
        }

        if (preg_match('/\b(accessory|cable|adapter|mount|stand|bracket|cover)\b/', $notes)) {
            return 'accessory';
        }

        return 'complete_unit';
    }

    protected function hasSubstantialData(?array $data): bool
    {
        if (! $data) {
            return false;
        }

        return ! empty($data['product_identity']['brand'])
            || ! empty($data['product_identity']['model'])
            || ! empty($data['product_identity']['title'])
            || ! empty($data['product_details']['description'])
            || ! empty($data['market_pricing']['ebay_sold_listings'])
            || ! empty($data['market_pricing']['ebay_active_listings'])
            || ! empty($data['market_pricing']['recommended_list_price']);
    }

    protected function enhanceWithSupplierContext(array $data, array $supplierContext): array
    {
        $data['supplier_context'] = [
            'supplier_name' => $supplierContext['supplier_name'] ?? 'Unknown',
            'reliability_score' => $supplierContext['reliability_score'] ?? 0,
            'priority_supplier' => $supplierContext['priority_boost'] ?? false,
        ];

        if (($supplierContext['reliability_score'] ?? 0) >= 80) {
            $data['supplier_confidence_boost'] = 'high';
        }

        return $data;
    }

    protected function enhanceWithCompletenessData(array $data, array $completenessData): array
    {
        $data['product_details']['completeness_data'] = $completenessData;
        $data['product_details']['completeness_score'] = $completenessData['completeness_score'] ?? 0;

        $completenessScore = $completenessData['completeness_score'] ?? 0;
        if ($completenessScore < 60) {
            $data['confidence_impact'] = 'negative';
            $data['confidence_notes'] = 'Incomplete product - may affect listing quality';
        } elseif ($completenessScore >= 90) {
            $data['confidence_impact'] = 'positive';
            $data['confidence_notes'] = 'Complete product - excellent for listing';
        }

        return $data;
    }

    // Backward compatibility methods
    public function research(ProductIntakeQueue $item): array
    {
        return $this->researchWithTwoStageProcess($item);
    }

    public function researchWithEnhancedContext(ProductIntakeQueue $item, array $options = []): array
    {
        return $this->researchWithTwoStageProcess($item, $options);
    }

    public function researchWithStrategy(ProductIntakeQueue $item, string $strategy, array $options = []): array
    {
        // Route different strategies to the appropriate two-stage variant
        return $this->researchWithTwoStageProcess($item, array_merge($options, ['strategy' => $strategy]));
    }
}
