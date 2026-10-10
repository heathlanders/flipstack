<?php

namespace App\Services;

use App\Models\Integration\IntegrationCredential;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * STAGE 2: Enhanced Product Research Service
 *
 * This service handles the SECOND stage of product research:
 * - Uses Stage 1 identification results to guide research
 * - Focuses on listing optimization, pricing, and market data
 * - Leverages confirmed product identity for better search accuracy
 * - Integrates eBay data when available
 */
class GeminiProductResearch
{
    /**
     * Minimum listing-relevance score required to attach an image to a product.
     * 30 = a full MPN/part-number title match. Below this, images are not trusted.
     */
    protected const MIN_IMAGE_RELEVANCE = 30;

    protected ?string $apiKey = null;

    protected string $baseUrl = 'https://generativelanguage.googleapis.com/v1beta/models';

    public function __construct()
    {
        $this->loadCredentials();
    }

    protected function loadCredentials(): void
    {
        try {
            $credential = IntegrationCredential::where('platform', 'gemini')
                ->where('environment', 'production')
                ->where('is_active', true)
                ->first();

            if ($credential) {
                $this->apiKey = $credential->decrypted_client_secret;
            }
        } catch (\Throwable $e) {
            Log::warning('Failed to load Gemini credentials', ['error' => $e->getMessage()]);
        }
    }

    public function isAvailable(): bool
    {
        return ! empty($this->apiKey);
    }

    /**
     * STAGE 2 - Main research method using Stage 1 identification results
     */
    public function researchWithIdentification(?array $identificationResults, array $identifiers, string $productType = 'complete_unit', ?string $brand = null, ?array $keywords = null, ?array $ebayAspectsContext = null): ?array
    {
        if (! $this->isAvailable()) {
            Log::warning('Stage 2 research unavailable - no API key configured');

            return null;
        }

        // Build cache key including identification results and aspects context
        $cacheKey = 'stage2_research_'.md5(
            json_encode($identificationResults).
            json_encode($identifiers).
            $productType.
            ($brand ?? '').
            implode(',', $keywords ?? []).
            ($ebayAspectsContext['category_id'] ?? '')
        );

        return Cache::remember($cacheKey, 60 * 24 * 3, function () use ($identificationResults, $identifiers, $productType, $brand, $keywords, $ebayAspectsContext) {
            return $this->performStage2Research($identificationResults, $identifiers, $productType, $brand, $keywords, $ebayAspectsContext);
        });
    }

    /**
     * STAGE 2 - Enhanced research with eBay data integration
     */
    public function enrichWithEbayDataAndIdentification(?array $identificationResults, array $identifiers, array $ebayData, string $productType, ?string $brand = null, ?array $keywords = null, ?array $ebayAspectsContext = null): ?array
    {
        if (! $this->isAvailable()) {
            Log::warning('Stage 2 enrichment unavailable');

            return null;
        }

        // Extract pricing from eBay data
        $activeListings = $ebayData['active_listings'] ?? [];
        $soldListings = $ebayData['sold_listings'] ?? [];

        $pricingData = $this->calculatePricingStats($activeListings, $soldListings);
        $images = $this->extractImages($activeListings, $identifiers, $identificationResults);
        $categoryData = $this->extractCategory($activeListings);

        Log::info('Stage 2: eBay data processing with identification', [
            'identification_confidence' => $identificationResults['identification_results']['identification_confidence'] ?? 'none',
            'product_identified' => $identificationResults['identification_results']['product_identified'] ?? false,
            'recommended_price' => $pricingData['recommended_price'],
            'images_found' => count($images),
        ]);

        // Build enhanced prompt using identification results
        $prompt = $this->buildEnhancedEnrichmentPrompt($identificationResults, $identifiers, $ebayData, $productType, $brand, $keywords, $pricingData, $categoryData, $ebayAspectsContext);

        try {
            Log::info('Stage 2: Gemini enrichment with identification context');

            $response = Http::withHeaders([
                'x-goog-api-key' => $this->apiKey,
                'Content-Type' => 'application/json',
            ])->retry(3, fn (int $attempt) => $attempt * 2000, function ($exception) {
                // Gemini 503 (overloaded) / 429 (rate limit) are transient; retry with backoff
                // instead of dropping the item straight to the manual-research template.
                return $exception instanceof ConnectionException
                    || in_array($exception->response?->status(), [429, 503], true);
            }, throw: false)->timeout(90)->post("{$this->baseUrl}/gemini-2.5-flash:generateContent", [
                'contents' => [[
                    'parts' => [['text' => $prompt]],
                ]],
                'generationConfig' => [
                    // Low temperature to keep Stage 2 from re-inventing the Stage 1 identity.
                    'temperature' => 0.1,
                    'topP' => 0.7,
                    'topK' => 20,
                ],
            ]);

            if (! $response->successful()) {
                Log::error('Stage 2 enrichment failed', [
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);

                return null;
            }

            $content = $response->json('candidates.0.content.parts.0.text');
            $jsonContent = $this->extractJson($content);
            $data = json_decode($jsonContent, true);

            if (json_last_error() !== JSON_ERROR_NONE) {
                Log::error('Failed to parse Stage 2 enrichment JSON', [
                    'error' => json_last_error_msg(),
                ]);

                return null;
            }

            // Merge Stage 1 identification results
            $data = $this->mergeIdentificationResults($data, $identificationResults);

            // Add calculated pricing data
            $data['market_pricing']['recommended_list_price'] = $pricingData['recommended_price'];
            $data['market_pricing']['average_price'] = $pricingData['average_price'];
            $data['market_pricing']['median_price'] = $pricingData['median_price'];
            $data['market_pricing']['price_range'] = $pricingData['price_range'];
            $data['market_pricing']['lowest_price'] = $pricingData['lowest_price'];
            $data['market_pricing']['highest_price'] = $pricingData['highest_price'];
            $data['market_pricing']['pricing_confidence'] = count($activeListings) >= 10 ? 'high' : 'medium';
            $data['market_pricing']['sample_size'] = count($activeListings);

            // Add full eBay data
            $data['market_pricing']['ebay_sold_listings'] = $soldListings;
            $data['market_pricing']['ebay_active_listings'] = $activeListings;

            // Add images. If listings existed but none cleared the relevance floor,
            // attach nothing and flag it so the item routes to review instead of
            // getting irrelevant images.
            $data['images'] = $images;
            if (empty($images) && ! empty($activeListings)) {
                $data['warnings'] = array_values(array_unique(array_merge(
                    $data['warnings'] ?? [],
                    ['no_relevant_images_found']
                )));
                Log::warning('No eBay listing cleared the image relevance floor', [
                    'active_listings' => count($activeListings),
                ]);
            }

            // Override category if better one detected
            if (! empty($categoryData['category']) && empty($data['product_identity']['category'])) {
                $data['product_identity']['category'] = $categoryData['category'];
            }

            $data['data_source'] = 'stage2_ebay_enrichment';
            $data['research_stage'] = 'stage_2_complete';

            Log::info('Stage 2: Enhanced eBay enrichment successful', [
                'price' => $data['market_pricing']['recommended_list_price'],
                'images' => count($data['images']),
                'stage1_confidence' => $identificationResults['identification_results']['identification_confidence'] ?? 'none',
            ]);

            return $data;

        } catch (\Exception $e) {
            Log::error('Stage 2 enrichment exception', [
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    /**
     * STAGE 2 - Full web research using identification results
     */
    protected function performStage2Research(?array $identificationResults, array $identifiers, string $productType, ?string $brand, ?array $keywords, ?array $ebayAspectsContext = null): ?array
    {
        $prompt = $this->buildStage2WebResearchPrompt($identificationResults, $identifiers, $productType, $brand, $keywords, $ebayAspectsContext);

        try {
            Log::info('Stage 2: Full web research with identification context', [
                'has_identification' => ! empty($identificationResults),
                'identification_confidence' => $identificationResults['identification_results']['identification_confidence'] ?? 'none',
            ]);

            $response = Http::withHeaders([
                'x-goog-api-key' => $this->apiKey,
                'Content-Type' => 'application/json',
            ])->retry(3, fn (int $attempt) => $attempt * 2000, function ($exception) {
                // Gemini 503 (overloaded) / 429 (rate limit) are transient; retry with backoff
                // instead of dropping the item straight to the manual-research template.
                return $exception instanceof ConnectionException
                    || in_array($exception->response?->status(), [429, 503], true);
            }, throw: false)->timeout(90)->post("{$this->baseUrl}/gemini-2.5-flash:generateContent", [
                'contents' => [[
                    'parts' => [['text' => $prompt]],
                ]],
                'generationConfig' => [
                    // Low temperature to keep Stage 2 from re-inventing the Stage 1 identity.
                    'temperature' => 0.1,
                    'topP' => 0.7,
                    'topK' => 20,
                ],
            ]);

            if (! $response->successful()) {
                Log::error('Stage 2 web research failed', [
                    'status' => $response->status(),
                ]);

                return null;
            }

            $content = $response->json('candidates.0.content.parts.0.text');
            $jsonContent = $this->extractJson($content);
            $data = json_decode($jsonContent, true);

            if (json_last_error() !== JSON_ERROR_NONE) {
                Log::error('Failed to parse Stage 2 web research JSON', [
                    'error' => json_last_error_msg(),
                ]);

                return null;
            }

            // Merge Stage 1 identification results
            $data = $this->mergeIdentificationResults($data, $identificationResults);
            $data['data_source'] = 'stage2_web_research';
            $data['research_stage'] = 'stage_2_complete';

            Log::info('Stage 2: Web research successful');

            return $data;

        } catch (\Exception $e) {
            Log::error('Stage 2 web research exception', [
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    /**
     * Build enhanced enrichment prompt using Stage 1 identification results
     */
    protected function buildEnhancedEnrichmentPrompt(?array $identificationResults, array $identifiers, array $ebayData, string $productType, ?string $brand, ?array $keywords, array $pricingData, array $categoryData, ?array $ebayAspectsContext = null): string
    {
        $productContext = match ($productType) {
            'complete_unit' => 'Complete standalone product',
            'replacement_part' => 'Replacement part or component',
            'accessory' => 'Accessory or add-on',
            default => '',
        };

        $soldCount = count($ebayData['sold_listings'] ?? []);
        $activeCount = count($ebayData['active_listings'] ?? []);

        $brandText = $brand ?: 'Unknown';
        $keywordsText = $keywords ? implode(', ', $keywords) : '';
        $ncrId = $identifiers['manufacturer_part_number'] ?? '';

        // Extract identification information
        $identificationInfo = '';
        if (! empty($identificationResults)) {
            $idResult = $identificationResults['identification_results'] ?? [];
            $mostLikely = $idResult['most_likely_product'] ?? [];

            $identificationInfo = 'STAGE 1 IDENTIFICATION RESULTS:
Product Identified: '.($idResult['product_identified'] ? 'YES' : 'NO').'
Confidence Level: '.($idResult['identification_confidence'] ?? 'none').'
Most Likely Product: '.($mostLikely['product_name'] ?? 'Unknown').'
Complete Part Number: '.($mostLikely['complete_part_number'] ?? 'N/A').'
Product Type: '.($mostLikely['product_type'] ?? 'Unknown').'
Reasoning: '.($idResult['reasoning'] ?? 'No reasoning provided').'

RECOMMENDED SEARCH STRATEGY:
'.json_encode($identificationResults['search_strategy_for_stage2'] ?? [], JSON_PRETTY_PRINT);
        }

        $ebayAspectsSection = $this->buildEbayAspectsPromptSection($ebayAspectsContext);

        return <<<PROMPT
STAGE 2: COMPREHENSIVE PRODUCT RESEARCH & LISTING OPTIMIZATION

{$identificationInfo}

CURRENT IDENTIFIERS:
Brand: {$brandText}
Part Number: {$ncrId}
Keywords: {$keywordsText}
Product Type: {$productContext}

EBAY MARKET DATA FOUND:
- {$soldCount} sold listings analyzed
- {$activeCount} active listings found
- Calculated recommended price: {$pricingData['recommended_price']}
{$ebayAspectsSection}

IDENTITY LOCK (CRITICAL): Stage 1 already determined WHAT this product is. Do NOT change
the product identity, manufacturer, or part number. Do NOT reinterpret the brand as a
different company. Your job is to enrich (specs, description, pricing, listing copy) the
product Stage 1 identified — not to re-identify it. If Stage 1 confidence was low, keep it
low; do not invent a confident identity.

TASK: Using the Stage 1 identification results above, provide comprehensive listing optimization and enhanced product details. Focus on:

1. ENHANCED PRODUCT IDENTITY (use Stage 1 results to improve accuracy)
2. DETAILED PRODUCT SPECIFICATIONS
3. LISTING OPTIMIZATION for eBay/Amazon/etc
4. COMPETITIVE ANALYSIS
5. MARKET POSITIONING RECOMMENDATIONS

IMPORTANT: For upc and ean fields, only provide confirmed numeric digits (e.g. "012345678901"). If you cannot find a verified barcode, return an empty string "". Never return "N/A", "unknown", "varies", or any placeholder text.

Return in this JSON structure:

{
  "product_identity": {
    "brand": "{$brandText}",
    "model": "",
    "manufacturer_part_number": "{$ncrId}",
    "verified_complete_part_number": "",
    "upc": "",
    "ean": "",
    "title": "",
    "category": "",
    "subcategory": "",
    "identification_confidence_boost": "Whether Stage 1 results improved confidence"
  },
  "product_details": {
    "condition": "new/used/refurbished/discontinued",
    "description": "Comprehensive product description for listings",
    "key_features": ["Feature 1", "Feature 2"],
    "specifications": {
      "key": "value for each specification as individual entries, e.g. Processor, RAM Size, Screen Size, Storage Capacity etc.",
      "example_format": "Use the exact eBay aspect names from the EBAY ITEM SPECIFICS section above as keys when available"
    },
    "weight": "",
    "dimensions": "",
    "package_contents": [],
    "warranty": "",
    "technical_documentation_links": []
  },
  "listing_optimization": {
    "suggested_title": "SEO-optimized title",
    "primary_keywords": ["keyword1", "keyword2"],
    "secondary_keywords": ["secondary1", "secondary2"],
    "bullet_points": [
      "Compelling feature 1",
      "Compelling feature 2"
    ],
    "description_template": "Full listing description template",
    "platform_specific_tips": {
      "ebay": {
        "best_listing_format": "fixed_price",
        "optimal_duration": "30 days",
        "category_suggestions": [],
        "item_specifics": {
          "AspectName": "value for each eBay item specific from the EBAY ITEM SPECIFICS section above"
        }
      },
      "amazon": {
        "product_category": "",
        "required_attributes": {},
        "search_terms": []
      }
    }
  },
  "competitive_analysis": {
    "similar_products": [
      {
        "product_name": "",
        "price_range": "",
        "key_differentiators": []
      }
    ],
    "market_positioning": {
      "suggested_positioning": "",
      "target_customer": "",
      "unique_selling_points": []
    },
    "pricing_strategy": {
      "competitive_advantage": "",
      "pricing_recommendations": ""
    }
  },
  "stage1_integration": {
    "identification_used": true/false,
    "confidence_improvement": "",
    "additional_details_found": "",
    "verification_status": "confirmed/partial/conflicted"
  }
}

CRITICAL REQUIREMENTS:
1. Integrate Stage 1 identification results to improve accuracy
2. Create compelling, SEO-optimized listing content
3. Provide platform-specific optimization tips
4. Include competitive positioning advice
5. Enhance product details using confirmed identification
6. If this is a complete unit, include a "component_breakdown" array (see below)

COMPLETE UNIT COMPONENT BREAKDOWN:
If the product type is "Complete standalone product" AND it is a device/machine/system that contains removable/sellable internal components (e.g., servers, computers, networking equipment, medical devices), include a "component_breakdown" array at the root level of your JSON response. This identifies all internal parts that could be individually sold if the unit were disassembled.

For each component, provide:
- "name": Full descriptive name (e.g., "Samsung 32GB DDR4-2933 ECC RDIMM")
- "part_number": Exact manufacturer part number if known (e.g., "M393A4K40CB2-CVF")
- "manufacturer": Component manufacturer
- "quantity": How many are installed in this unit
- "category": Component type (CPU, RAM, HDD, SSD, PSU, GPU, NIC, RAID, Chassis, Motherboard, etc.)
- "specs": Object with key specifications (capacity, speed, form factor, interface, etc.)

Example for a server:
"component_breakdown": [
  {"name": "Intel Xeon Gold 6248 20-Core 2.5GHz", "part_number": "CD8069504194301", "manufacturer": "Intel", "quantity": 2, "category": "CPU", "specs": {"cores": 20, "speed": "2.5GHz", "socket": "LGA3647"}},
  {"name": "Samsung 32GB DDR4-2933 ECC RDIMM", "part_number": "M393A4K40CB2-CVF", "manufacturer": "Samsung", "quantity": 8, "category": "RAM", "specs": {"capacity": "32GB", "type": "DDR4-2933", "form_factor": "RDIMM"}}
]

If the product is NOT a complete unit with internal components (e.g., it is a single part, accessory, cable, or simple consumer product), omit the "component_breakdown" field entirely.

Return ONLY valid JSON (no markdown, no explanations).
PROMPT;
    }

    /**
     * Build Stage 2 web research prompt using identification results
     */
    protected function buildStage2WebResearchPrompt(?array $identificationResults, array $identifiers, string $productType, ?string $brand, ?array $keywords, ?array $ebayAspectsContext = null): string
    {
        $productContext = match ($productType) {
            'complete_unit' => 'Complete standalone product',
            'replacement_part' => 'Replacement part or component',
            'accessory' => 'Accessory or add-on',
            default => '',
        };

        $brandText = $brand ?: '';
        $keywordsText = $keywords ? implode(', ', $keywords) : '';
        $searchTerm = $identifiers['comprehensive_query'] ?? $identifiers['manufacturer_part_number'] ?? '';

        // Extract identification information and search strategy
        $identificationInfo = '';
        $searchStrategy = [];
        if (! empty($identificationResults)) {
            $idResult = $identificationResults['identification_results'] ?? [];
            $mostLikely = $idResult['most_likely_product'] ?? [];
            $searchStrategy = $identificationResults['search_strategy_for_stage2'] ?? [];

            $identificationInfo = 'STAGE 1 IDENTIFICATION RESULTS:
Product Identified: '.($idResult['product_identified'] ? 'YES' : 'NO').'
Confidence: '.($idResult['identification_confidence'] ?? 'none').'
Product Name: '.($mostLikely['product_name'] ?? 'Unknown').'
Complete Part Number: '.($mostLikely['complete_part_number'] ?? 'N/A').'
Reasoning: '.($idResult['reasoning'] ?? '').'

RECOMMENDED SEARCH APPROACH:
Primary Terms: '.implode(', ', $searchStrategy['primary_search_terms'] ?? []).'
Secondary Terms: '.implode(', ', $searchStrategy['secondary_search_terms'] ?? []).'
Strategy: '.($searchStrategy['recommended_approach'] ?? 'standard web search');
        }

        $ebayAspectsSection = $this->buildEbayAspectsPromptSection($ebayAspectsContext);

        return <<<PROMPT
STAGE 2: COMPREHENSIVE WEB RESEARCH & MARKET ANALYSIS

{$identificationInfo}

SEARCH PARAMETERS:
Primary Search Term: {$searchTerm}
Brand: {$brandText}
Keywords: {$keywordsText}
Product Type: {$productContext}
{$ebayAspectsSection}

TASK: Using Stage 1 identification results, perform comprehensive web research to gather:
1. Complete product specifications and documentation
2. Current market pricing from multiple sources
3. eBay sold/active listings with direct links
4. Listing optimization recommendations
5. Competitive analysis

IMPORTANT: For upc and ean fields, only provide confirmed numeric digits (e.g. "012345678901"). If you cannot find a verified barcode, return an empty string "". Never return "N/A", "unknown", "varies", or any placeholder text.

Search the web extensively using the Stage 1 recommended approach and return comprehensive data:

{
  "product_identity": {
    "brand": "{$brandText}",
    "model": "",
    "manufacturer_part_number": "",
    "verified_complete_part_number": "",
    "upc": "",
    "ean": "",
    "title": "",
    "category": "",
    "subcategory": ""
  },
  "product_details": {
    "condition": "new/used/refurbished/discontinued",
    "description": "",
    "key_features": [],
    "specifications": {
      "key": "value for each specification as individual entries, use eBay aspect names from EBAY ITEM SPECIFICS section as keys when available"
    },
    "weight": "",
    "dimensions": "",
    "package_contents": [],
    "warranty": ""
  },
  "market_pricing": {
    "ebay_sold_listings": [
      {
        "title": "",
        "price": 0,
        "condition": "",
        "sold_date": "",
        "shipping": 0,
        "total_price": 0,
        "link": "https://www.ebay.com/itm/...",
        "seller_feedback": ""
      }
    ],
    "ebay_active_listings": [
      {
        "title": "",
        "price": 0,
        "condition": "",
        "seller_name": "",
        "link": "https://www.ebay.com/itm/...",
        "thumbnail": ""
      }
    ],
    "other_sources": [
      {
        "source": "Amazon/etc",
        "price": 0,
        "condition": "",
        "link": ""
      }
    ],
    "recommended_list_price": 0,
    "pricing_confidence": "high/medium/low",
    "pricing_analysis": ""
  },
  "listing_optimization": {
    "suggested_title": "",
    "primary_keywords": [],
    "bullet_points": [],
    "platform_specific_tips": {}
  },
  "verification_against_stage1": {
    "stage1_confirmation": "confirmed/partial/conflicted",
    "additional_findings": "",
    "confidence_assessment": ""
  },
  "data_sources": [],
  "search_metadata": {
    "search_performed_at": "",
    "total_sources_checked": 0,
    "stage1_integration": true
  }
}

CRITICAL REQUIREMENTS:
1. Use Stage 1 identification results to guide search
2. Include direct links to ALL eBay listings found
3. Verify findings against Stage 1 results
4. Focus on confirmed product identity from Stage 1
5. Provide comprehensive market data
6. If this is a complete unit, include a "component_breakdown" array (see below)

COMPLETE UNIT COMPONENT BREAKDOWN:
If the product type is "Complete standalone product" AND it is a device/machine/system that contains removable/sellable internal components (e.g., servers, computers, networking equipment, medical devices), include a "component_breakdown" array at the root level of your JSON response. This identifies all internal parts that could be individually sold if the unit were disassembled.

For each component, provide:
- "name": Full descriptive name (e.g., "Samsung 32GB DDR4-2933 ECC RDIMM")
- "part_number": Exact manufacturer part number if known (e.g., "M393A4K40CB2-CVF")
- "manufacturer": Component manufacturer
- "quantity": How many are installed in this unit
- "category": Component type (CPU, RAM, HDD, SSD, PSU, GPU, NIC, RAID, Chassis, Motherboard, etc.)
- "specs": Object with key specifications (capacity, speed, form factor, interface, etc.)

If the product is NOT a complete unit with internal components, omit the "component_breakdown" field entirely.

Return ONLY valid JSON (no markdown, no explanations).
PROMPT;
    }

    /**
     * Build the eBay item specifics prompt section from aspects context.
     */
    protected function buildEbayAspectsPromptSection(?array $ebayAspectsContext): string
    {
        if (empty($ebayAspectsContext) || empty($ebayAspectsContext['aspects_prompt'])) {
            return '';
        }

        $categoryName = $ebayAspectsContext['category_name'] ?? 'Unknown';

        return <<<SECTION

EBAY ITEM SPECIFICS REQUIREMENTS:
The product will be listed in eBay category: "{$categoryName}"
This category requires/recommends the following item specifics. You MUST populate the "item_specifics" field in your response with accurate values for as many of these as possible. When allowed values are listed, choose the closest matching option. Use your product knowledge to determine correct values.

{$ebayAspectsContext['aspects_prompt']}

IMPORTANT: The "item_specifics" object in your response should use the EXACT aspect names listed above as keys, with string values. For example: {"Brand": "Dell", "Processor": "Intel Core i7-1365U", "RAM Size": "16 GB"}

VALUE RULES (eBay enforces these -- violating them fails the listing):
- Each value MUST be 65 characters or fewer.
- Use concise, literal values only. NO parenthetical explanations, caveats, or sentences. Write "16 GB", not "16 GB (upgradeable to 32 GB on this model)".
- If you do not know a value, OMIT that aspect entirely. Do NOT output "Not specified", "Unknown", "N/A", "Varies", or any explanatory placeholder.
SECTION;
    }

    /**
     * Merge Stage 1 identification results into Stage 2 data
     */
    protected function mergeIdentificationResults(array $stage2Data, ?array $identificationResults): array
    {
        if (empty($identificationResults)) {
            return $stage2Data;
        }

        // Add Stage 1 results to the response
        $stage2Data['stage1_identification'] = $identificationResults;

        // Enhance product identity with Stage 1 findings
        $idResult = $identificationResults['identification_results'] ?? [];
        $mostLikely = $idResult['most_likely_product'] ?? [];

        if (! empty($mostLikely)) {
            // Override with more confident Stage 1 data where applicable
            if (! empty($mostLikely['complete_part_number']) && empty($stage2Data['product_identity']['manufacturer_part_number'])) {
                $stage2Data['product_identity']['manufacturer_part_number'] = $mostLikely['complete_part_number'];
            }

            if (! empty($mostLikely['product_name']) && empty($stage2Data['product_identity']['title'])) {
                $stage2Data['product_identity']['title'] = $mostLikely['product_name'];
            }

            if (! empty($mostLikely['manufacturer']) && empty($stage2Data['product_identity']['brand'])) {
                $stage2Data['product_identity']['brand'] = $mostLikely['manufacturer'];
            }

            if (! empty($mostLikely['product_type']) && empty($stage2Data['product_identity']['category'])) {
                $stage2Data['product_identity']['category'] = $mostLikely['product_type'];
            }
        }

        // Add identification confidence and reasoning
        $stage2Data['identification_confidence'] = $idResult['identification_confidence'] ?? 'none';
        $stage2Data['identification_reasoning'] = $idResult['reasoning'] ?? '';

        // Add source links from Stage 1
        if (! empty($identificationResults['source_links'])) {
            $stage2Data['identification_sources'] = $identificationResults['source_links'];
        }

        return $stage2Data;
    }

    /**
     * Calculate pricing statistics from eBay listings (same as original)
     */
    protected function calculatePricingStats(array $activeListings, array $soldListings): array
    {
        $prices = [];

        foreach ($activeListings as $listing) {
            if (isset($listing['price']) && $listing['price'] > 0) {
                $prices[] = (float) $listing['price'];
            }
        }

        if (empty($prices)) {
            return [
                'recommended_price' => 0,
                'average_price' => 0,
                'median_price' => 0,
                'lowest_price' => 0,
                'highest_price' => 0,
                'price_range' => '0',
            ];
        }

        sort($prices);

        $count = count($prices);
        $sum = array_sum($prices);
        $average = round($sum / $count, 2);

        $middle = floor($count / 2);
        if ($count % 2 == 0) {
            $median = round(($prices[$middle - 1] + $prices[$middle]) / 2, 2);
        } else {
            $median = round($prices[$middle], 2);
        }

        $lowest = min($prices);
        $highest = max($prices);
        $recommended = $median;

        return [
            'recommended_price' => $recommended,
            'average_price' => $average,
            'median_price' => $median,
            'lowest_price' => $lowest,
            'highest_price' => $highest,
            'price_range' => "$lowest - $highest",
        ];
    }

    /**
     * Extract image URLs from eBay listings, sorted by title relevance to the confirmed product.
     * This ensures the reference image shown on Step 1 is from the most closely matching listing.
     */
    protected function extractImages(array $activeListings, array $identifiers = [], ?array $identificationResults = null): array
    {
        if (empty($activeListings)) {
            return [];
        }

        // Score each listing by how well its title matches the confirmed product
        $scored = array_map(function (array $listing) use ($identifiers, $identificationResults) {
            return [
                'listing' => $listing,
                'score' => $this->scoreListingRelevance($listing['title'] ?? '', $identifiers, $identificationResults),
            ];
        }, $activeListings);

        // Sort descending by relevance score; stable sort preserves original order for ties
        usort($scored, fn ($a, $b) => $b['score'] <=> $a['score']);

        // Hard relevance floor: only attach images from listings that actually match the
        // product. Better to attach NO images than to attach a sandal to a furnace part.
        $images = [];
        foreach ($scored as $entry) {
            if (($entry['score'] ?? 0) < self::MIN_IMAGE_RELEVANCE) {
                continue;
            }
            $image = $entry['listing']['image'] ?? null;
            if (! empty($image) && count($images) < 12) {
                $images[] = $image;
            }
        }

        return array_values(array_unique($images));
    }

    /**
     * Score a listing title against the confirmed product identifiers.
     * Higher score = better match = image should appear earlier in the reference list.
     */
    protected function scoreListingRelevance(string $title, array $identifiers, ?array $identificationResults): int
    {
        if (empty($title)) {
            return 0;
        }

        $titleLower = strtolower($title);
        $score = 0;

        // Confirmed part number from Stage 1 is the strongest signal
        $confirmedPartNumber = $identificationResults['identification_results']['most_likely_product']['complete_part_number'] ?? null;
        if ($confirmedPartNumber && stripos($titleLower, strtolower($confirmedPartNumber)) !== false) {
            $score += 50;
        }

        // Confirmed product name from Stage 1
        $confirmedProductName = $identificationResults['identification_results']['most_likely_product']['product_name'] ?? null;
        if ($confirmedProductName) {
            $nameParts = array_filter(explode(' ', strtolower($confirmedProductName)));
            $matchedParts = 0;
            foreach ($nameParts as $part) {
                if (strlen($part) > 2 && stripos($titleLower, $part) !== false) {
                    $matchedParts++;
                }
            }
            if (count($nameParts) > 0) {
                $score += (int) round(20 * ($matchedParts / count($nameParts)));
            }
        }

        // Brand match
        $brand = $identificationResults['identification_results']['most_likely_product']['manufacturer'] ?? $identifiers['brand'] ?? null;
        if ($brand && stripos($titleLower, strtolower($brand)) !== false) {
            $score += 15;
        }

        // MPN / product number from queue item
        $mpn = $identifiers['manufacturer_part_number'] ?? $identifiers['confirmed_part_number'] ?? null;
        if ($mpn && stripos($titleLower, strtolower($mpn)) !== false) {
            $score += 30;
        }

        // Model number
        $model = $identifiers['model_number'] ?? null;
        if ($model && stripos($titleLower, strtolower($model)) !== false) {
            $score += 10;
        }

        // Stage 1 primary search terms
        $primaryTerms = $identificationResults['search_strategy_for_stage2']['primary_search_terms'] ?? [];
        foreach ($primaryTerms as $term) {
            if (strlen($term) > 2 && stripos($titleLower, strtolower($term)) !== false) {
                $score += 5;
            }
        }

        return $score;
    }

    /**
     * Extract category information from eBay listings (same as original)
     */
    protected function extractCategory(array $activeListings): array
    {
        if (empty($activeListings)) {
            return ['category' => '', 'subcategory' => ''];
        }

        $categoryPatterns = [
            'pos' => ['category' => 'Business & Industrial', 'subcategory' => 'Retail & Services > Point of Sale', 'keywords' => ['pos ', 'pos,', 'point of sale', 'cash register', 'receipt printer', 'cash drawer']],
            'laptop' => ['category' => 'Computers/Tablets & Networking', 'subcategory' => 'Laptops & Netbooks', 'keywords' => ['laptop', 'notebook', 'chromebook', 'ultrabook']],
            'desktop' => ['category' => 'Computers/Tablets & Networking', 'subcategory' => 'Desktops & All-In-Ones', 'keywords' => ['desktop', 'workstation', 'all-in-one', 'tower pc', 'mini pc']],
            'tablet' => ['category' => 'Computers/Tablets & Networking', 'subcategory' => 'Tablets & eBook Readers', 'keywords' => ['tablet', 'ipad']],
            'phone' => ['category' => 'Cell Phones & Accessories', 'subcategory' => 'Cell Phones & Smartphones', 'keywords' => ['iphone', 'smartphone', 'cell phone', 'samsung galaxy', 'android phone']],
            'networking' => ['category' => 'Computers/Tablets & Networking', 'subcategory' => 'Enterprise Networking, Servers', 'keywords' => ['router', 'switch', 'firewall', 'access point', 'network']],
            'printer' => ['category' => 'Computers/Tablets & Networking', 'subcategory' => 'Printers, Scanners & Supplies', 'keywords' => ['printer', 'scanner', 'copier']],
            'monitor' => ['category' => 'Computers/Tablets & Networking', 'subcategory' => 'Monitors, Projectors & Accessories', 'keywords' => ['monitor', 'display', 'projector']],
            'test_equipment' => ['category' => 'Business & Industrial', 'subcategory' => 'Test, Measurement & Inspection', 'keywords' => ['oscilloscope', 'multimeter', 'signal generator']],
            'server' => ['category' => 'Computers/Tablets & Networking', 'subcategory' => 'Enterprise Networking, Servers', 'keywords' => ['server', 'rack mount', 'blade server']],
            'clothing' => ['category' => 'Clothing, Shoes & Accessories', 'subcategory' => 'Clothing', 'keywords' => ['dress', 'shirt', 'pants', 'jeans', 'jacket', 'coat', 'sweater', 'blouse', 'skirt']],
            'shoes' => ['category' => 'Clothing, Shoes & Accessories', 'subcategory' => 'Shoes', 'keywords' => ['shoes', 'boots', 'sneakers', 'sandals', 'heels', 'loafers']],
            'handbags' => ['category' => 'Clothing, Shoes & Accessories', 'subcategory' => 'Handbags & Accessories', 'keywords' => ['handbag', 'purse', 'wallet', 'backpack', 'tote bag']],
            'watches' => ['category' => 'Jewelry & Watches', 'subcategory' => 'Watches', 'keywords' => ['watch', 'wristwatch', 'smartwatch']],
            'jewelry' => ['category' => 'Jewelry & Watches', 'subcategory' => 'Jewelry', 'keywords' => ['necklace', 'bracelet', 'ring', 'earring', 'pendant']],
            'musical_instruments' => ['category' => 'Musical Instruments & Gear', 'subcategory' => 'Instruments', 'keywords' => ['guitar', 'piano', 'keyboard', 'drums', 'violin', 'amplifier', 'synthesizer', 'ukulele']],
            'home_garden' => ['category' => 'Home & Garden', 'subcategory' => 'Home Improvement', 'keywords' => ['furniture', 'lamp', 'rug', 'mattress', 'cookware', 'vacuum', 'garden']],
            'toys' => ['category' => 'Toys & Hobbies', 'subcategory' => 'Toys', 'keywords' => ['lego', 'action figure', 'doll', 'board game', 'puzzle', 'toy']],
            'sports' => ['category' => 'Sporting Goods', 'subcategory' => 'Sports Equipment', 'keywords' => ['golf', 'fishing', 'bicycle', 'bike', 'camping', 'kayak', 'treadmill']],
            'auto_parts' => ['category' => 'eBay Motors', 'subcategory' => 'Parts & Accessories', 'keywords' => ['car part', 'headlight', 'bumper', 'brake pad', 'alternator', 'radiator', 'exhaust']],
            'collectibles' => ['category' => 'Collectibles', 'subcategory' => 'Collectibles', 'keywords' => ['antique', 'vintage', 'collectible', 'trading card', 'coin', 'stamp']],
            'power_tools' => ['category' => 'Home & Garden', 'subcategory' => 'Tools & Workshop Equipment', 'keywords' => ['power tool', 'drill', 'saw', 'sander', 'grinder', 'wrench']],
        ];

        // Vote-count across all listings
        $votes = array_fill_keys(array_keys($categoryPatterns), 0);

        foreach ($activeListings as $listing) {
            $title = strtolower($listing['title'] ?? '');
            if (empty($title)) {
                continue;
            }

            foreach ($categoryPatterns as $type => $pattern) {
                foreach ($pattern['keywords'] as $keyword) {
                    if (str_contains($title, $keyword)) {
                        $votes[$type]++;
                        break;
                    }
                }
            }
        }

        // Find the type with the most votes
        arsort($votes);
        $bestType = array_key_first($votes);

        if ($votes[$bestType] > 0) {
            return [
                'category' => $categoryPatterns[$bestType]['category'],
                'subcategory' => $categoryPatterns[$bestType]['subcategory'],
            ];
        }

        return ['category' => '', 'subcategory' => ''];
    }

    /**
     * Extract JSON from Gemini response (same as original)
     */
    protected function extractJson(string $content): string
    {
        $content = preg_replace('/```json\s*/i', '', $content);
        $content = preg_replace('/```\s*$/i', '', $content);

        return trim($content);
    }

    // Keep backward compatibility with original methods
    public function research(string $searchTerm, string $searchType = 'part_number', ?string $userNotes = null, string $productType = 'complete_unit', ?string $brand = null, ?array $keywords = null): ?array
    {
        // Fallback to Stage 2 research without Stage 1 results
        return $this->researchWithIdentification(null, [
            'manufacturer_part_number' => $searchTerm,
            'user_notes' => $userNotes,
        ], $productType, $brand, $keywords);
    }

    public function enrichWithEbayData(array $identifiers, array $ebayData, string $productType, ?string $brand = null, ?array $keywords = null): ?array
    {
        // Fallback to Stage 2 enrichment without Stage 1 results
        return $this->enrichWithEbayDataAndIdentification(null, $identifiers, $ebayData, $productType, $brand, $keywords);
    }
}
