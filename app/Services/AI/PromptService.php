<?php

namespace App\Services\AI;

use App\Models\AI\AiCategoryPrompt;
use App\Models\Integration\IntegrationCredential;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;

class PromptService
{
    protected const CACHE_TTL = 60 * 60; // 1 hour

    protected const TEST_RATE_LIMIT_KEY = 'ai_prompt_test';

    protected const TEST_RATE_LIMIT_MAX = 10; // 10 tests per minute

    protected const TEST_RATE_LIMIT_DECAY = 60; // Reset every minute

    /**
     * Get prompt for a specific category and type with fallback chain
     *
     * Fallback order:
     * 1. Organization + Category specific
     * 2. Organization default (category_slug = null)
     * 3. System category-specific
     * 4. System default (category_slug = null)
     * 5. Return null (use hardcoded fallback)
     */
    public function getPromptForCategory(string $type, string $categorySlug, ?int $orgId = null): ?AiCategoryPrompt
    {
        $cacheKey = $this->buildCacheKey($type, $categorySlug, $orgId);

        return Cache::remember($cacheKey, self::CACHE_TTL, function () use ($type, $categorySlug, $orgId) {
            return $this->resolvePrompt($type, $categorySlug, $orgId);
        });
    }

    /**
     * Resolve prompt using fallback chain
     */
    protected function resolvePrompt(string $type, string $categorySlug, ?int $orgId): ?AiCategoryPrompt
    {
        // 1. Organization + Category specific
        if ($orgId) {
            $prompt = AiCategoryPrompt::active()
                ->where('organization_id', $orgId)
                ->where('category_slug', $categorySlug)
                ->where('prompt_type', $type)
                ->orderByDesc('priority')
                ->first();

            if ($prompt) {
                Log::debug('PromptService: Found org-specific prompt', [
                    'prompt_id' => $prompt->id,
                    'category' => $categorySlug,
                    'type' => $type,
                ]);

                return $prompt;
            }

            // 2. Organization default (category_slug matches 'default' or general)
            $prompt = AiCategoryPrompt::active()
                ->where('organization_id', $orgId)
                ->whereIn('category_slug', ['default', 'general', 'advanced_general_product'])
                ->where('prompt_type', $type)
                ->orderByDesc('priority')
                ->first();

            if ($prompt) {
                Log::debug('PromptService: Found org default prompt', [
                    'prompt_id' => $prompt->id,
                    'type' => $type,
                ]);

                return $prompt;
            }
        }

        // 3. System category-specific
        $prompt = AiCategoryPrompt::active()
            ->system()
            ->where('category_slug', $categorySlug)
            ->where('prompt_type', $type)
            ->orderByDesc('priority')
            ->first();

        if ($prompt) {
            Log::debug('PromptService: Found system category prompt', [
                'prompt_id' => $prompt->id,
                'category' => $categorySlug,
                'type' => $type,
            ]);

            return $prompt;
        }

        // 4. System default
        $prompt = AiCategoryPrompt::active()
            ->system()
            ->whereIn('category_slug', ['default', 'general', 'advanced_general_product'])
            ->where('prompt_type', $type)
            ->orderByDesc('priority')
            ->first();

        if ($prompt) {
            Log::debug('PromptService: Found system default prompt', [
                'prompt_id' => $prompt->id,
                'type' => $type,
            ]);

            return $prompt;
        }

        // 5. No prompt found - will use hardcoded fallback
        Log::debug('PromptService: No prompt found, using hardcoded fallback', [
            'category' => $categorySlug,
            'type' => $type,
            'org_id' => $orgId,
        ]);

        return null;
    }

    /**
     * Render a prompt with variable substitution
     */
    public function renderPrompt(AiCategoryPrompt $prompt, array $variables): string
    {
        return $prompt->renderPrompt($variables);
    }

    /**
     * Render a prompt string directly with variable substitution
     */
    public function renderPromptString(string $content, array $variables): string
    {
        foreach ($variables as $key => $value) {
            $content = str_replace('{'.$key.'}', (string) $value, $content);
        }

        return $content;
    }

    /**
     * Get default variables available for prompts
     */
    public function getDefaultVariables(): array
    {
        return AiCategoryPrompt::getStandardVariables();
    }

    /**
     * Get prompt templates for creating new prompts
     */
    public function getPromptTemplates(): array
    {
        return [
            'basic' => [
                'name' => 'Basic Template',
                'description' => 'A simple prompt template for general product identification',
                'content' => $this->getBasicTemplate(),
            ],
            'detailed' => [
                'name' => 'Detailed Analysis Template',
                'description' => 'Comprehensive prompt for detailed product analysis',
                'content' => $this->getDetailedTemplate(),
            ],
            'ebay' => [
                'name' => 'eBay Enrichment Template',
                'description' => 'Template optimized for eBay listing creation',
                'content' => $this->getEbayTemplate(),
            ],
        ];
    }

    /**
     * Clear cache for specific organization/category
     */
    public function clearCache(?int $orgId = null, ?string $categorySlug = null): void
    {
        if ($orgId && $categorySlug) {
            foreach (array_keys(AiCategoryPrompt::PROMPT_TYPES) as $type) {
                Cache::forget($this->buildCacheKey($type, $categorySlug, $orgId));
            }
        } elseif ($orgId) {
            // Clear all prompts for organization
            $categories = AiCategoryPrompt::where('organization_id', $orgId)
                ->distinct('category_slug')
                ->pluck('category_slug');

            foreach ($categories as $category) {
                foreach (array_keys(AiCategoryPrompt::PROMPT_TYPES) as $type) {
                    Cache::forget($this->buildCacheKey($type, $category, $orgId));
                }
            }
        } else {
            // Clear all prompt caches (use cache tags if available, otherwise clear by pattern)
            Cache::flush(); // In production, use more targeted clearing
        }

        Log::info('PromptService: Cache cleared', [
            'org_id' => $orgId,
            'category_slug' => $categorySlug,
        ]);
    }

    /**
     * Test a prompt with the Gemini API
     */
    public function testPromptWithApi(string $promptContent, array $sampleData): array
    {
        // Rate limiting
        if (RateLimiter::tooManyAttempts(self::TEST_RATE_LIMIT_KEY, self::TEST_RATE_LIMIT_MAX)) {
            $seconds = RateLimiter::availableIn(self::TEST_RATE_LIMIT_KEY);

            return [
                'success' => false,
                'error' => "Rate limit exceeded. Try again in {$seconds} seconds.",
                'retry_after' => $seconds,
            ];
        }

        RateLimiter::hit(self::TEST_RATE_LIMIT_KEY, self::TEST_RATE_LIMIT_DECAY);

        // Get API credentials
        $credential = IntegrationCredential::where('platform', 'gemini')
            ->where('environment', 'production')
            ->where('is_active', true)
            ->first();

        if (! $credential) {
            return [
                'success' => false,
                'error' => 'Gemini API credentials not configured',
            ];
        }

        $apiKey = $credential->decrypted_client_secret;

        // Render prompt with sample data
        $renderedPrompt = $this->renderPromptString($promptContent, $sampleData);

        try {
            $response = Http::withHeaders([
                'x-goog-api-key' => $apiKey,
                'Content-Type' => 'application/json',
            ])->timeout(60)->post('https://generativelanguage.googleapis.com/v1beta/models/gemini-2.5-flash:generateContent', [
                'contents' => [[
                    'parts' => [['text' => $renderedPrompt]],
                ]],
                'generationConfig' => [
                    'temperature' => 0.1,
                    'topP' => 0.7,
                    'topK' => 20,
                ],
            ]);

            if (! $response->successful()) {
                Log::error('PromptService: API test failed', [
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);

                return [
                    'success' => false,
                    'error' => 'API request failed: '.$response->status(),
                    'details' => $response->json(),
                ];
            }

            $content = $response->json('candidates.0.content.parts.0.text');

            return [
                'success' => true,
                'rendered_prompt' => $renderedPrompt,
                'response' => $content,
                'usage' => $response->json('usageMetadata'),
            ];

        } catch (\Exception $e) {
            Log::error('PromptService: API test exception', [
                'error' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'error' => 'API error: '.$e->getMessage(),
            ];
        }
    }

    /**
     * Get sample data for testing prompts
     */
    public function getSampleData(): array
    {
        return [
            'brand' => 'Fortinet',
            'manufacturer_part_number' => 'FS-148F-FPOE',
            'product_number' => 'FS-148F-FPOE',
            'model_number' => 'FortiSwitch-148F-FPOE',
            'user_notes' => '48-port PoE switch, rack mounted, good condition',
            'keywords' => 'network switch, PoE, managed switch, ethernet',
            'condition' => 'Used - Good',
            'product_category' => 'network_equipment',
            'product_type' => 'complete_unit',
            'user_type_description' => 'Complete, standalone system or device that operates independently',
        ];
    }

    /**
     * Get all category slugs with their display names
     */
    public function getCategorySlugs(): array
    {
        return [
            'industrial_automation_motion_controls' => 'Industrial Automation & Motion Controls',
            'hydraulics_pneumatics' => 'Hydraulics & Pneumatics',
            'electrical_equipment_supplies' => 'Electrical Equipment & Supplies',
            'test_measurement_equipment' => 'Test & Measurement Equipment',
            'cnc_metalworking' => 'CNC & Metalworking',
            'healthcare_medical_equipment' => 'Healthcare & Medical Equipment',
            'computer_enterprise_it' => 'Computer & Enterprise IT',
            'vintage_computing' => 'Vintage Computing',
            'consumer_electronics' => 'Consumer Electronics',
            'automotive_parts_motors' => 'Automotive Parts & Motors',
            'musical_instruments_audio' => 'Musical Instruments & Audio',
            'cameras_photography' => 'Cameras & Photography',
            'hvac_refrigeration' => 'HVAC & Refrigeration',
            'restaurant_food_service' => 'Restaurant & Food Service',
            'material_handling' => 'Material Handling',
            'heavy_equipment' => 'Heavy Equipment',
            'printing_graphic_arts' => 'Printing & Graphic Arts',
            'pos_retail_systems' => 'POS & Retail Systems',
            'agriculture_forestry' => 'Agriculture & Forestry',
            'video_games_consoles' => 'Video Games & Consoles',
            'sporting_goods_fitness' => 'Sporting Goods & Fitness',
            'toys_collectibles' => 'Toys & Collectibles',
            'crafts_hobby' => 'Crafts & Hobby',
            'books_magazines' => 'Books & Magazines',
            'art_antiques' => 'Art & Antiques',
            'cell_phones_accessories' => 'Cell Phones & Accessories',
            'home_garden' => 'Home & Garden',
            'health_beauty' => 'Health & Beauty',
            'pet_supplies' => 'Pet Supplies',
            'advanced_general_product' => 'General Product (Default)',
        ];
    }

    /**
     * Build cache key for prompts
     */
    protected function buildCacheKey(string $type, string $categorySlug, ?int $orgId): string
    {
        return 'ai_prompt:'.$type.':'.$categorySlug.':'.($orgId ?? 'system');
    }

    /**
     * Get basic template content
     */
    protected function getBasicTemplate(): string
    {
        return <<<'PROMPT'
PRODUCT IDENTIFICATION

COMPONENT INFORMATION:
- Manufacturer/Brand: {brand}
- Part Number/Model: {manufacturer_part_number}
- Product Number: {product_number}
- Model Number: {model_number}
- Component Context: {user_notes}
- Additional Identifiers: {keywords}
- Condition: {condition}

USER CLASSIFICATION:
- Category: {product_category}
- Type: {product_type}
- Expected: {user_type_description}

Please identify this product and provide your analysis in JSON format:

{
  "identification_results": {
    "product_identified": true/false,
    "identification_confidence": "high/medium/low/none",
    "most_likely_product": {
      "complete_part_number": "",
      "product_name": "",
      "manufacturer": "",
      "product_type": ""
    },
    "reasoning": "Your analysis explanation"
  }
}

Return ONLY valid JSON.
PROMPT;
    }

    /**
     * Get detailed template content
     */
    protected function getDetailedTemplate(): string
    {
        return <<<'PROMPT'
EXPERT PRODUCT REVERSE ENGINEERING

SPECIALIST CONTEXT: You are a technical product specialist with extensive experience in product identification and classification.

COMPONENT INFORMATION:
- Manufacturer/Brand: {brand}
- Part Number/Model: {manufacturer_part_number}
- Product Number: {product_number}
- Model Number: {model_number}
- Component Context: {user_notes}
- Additional Identifiers: {keywords}
- Condition: {condition}

USER CLASSIFICATION CONTEXT:
- User Category: {product_category}
- User Type: {product_type}
- Expected: {user_type_description}

ANALYSIS REQUIREMENTS:
1. Identify the exact product based on part numbers and specifications
2. Validate the user's product type classification against technical evidence
3. Determine if this is a complete unit, replacement part, or accessory
4. Provide confidence levels for your identification

Return your expert analysis in this JSON structure:

{
  "identification_results": {
    "product_identified": true/false,
    "identification_confidence": "high/medium/low/none",
    "user_type_validation": {
      "user_expected_type": "{product_type}",
      "ai_determined_type": "",
      "type_classification_matches": true/false,
      "type_confidence": "high/medium/low",
      "type_reasoning": ""
    },
    "most_likely_product": {
      "complete_part_number": "",
      "product_name": "",
      "manufacturer": "",
      "product_type": "",
      "specifications": {}
    },
    "reasoning": "",
    "confidence_factors": []
  },
  "technical_specifications": {},
  "search_strategy_for_stage2": {
    "primary_search_terms": [],
    "recommended_approach": ""
  }
}

Return ONLY valid JSON.
PROMPT;
    }

    /**
     * Get eBay enrichment template content
     */
    protected function getEbayTemplate(): string
    {
        return <<<'PROMPT'
EBAY LISTING ENRICHMENT

PRODUCT INFORMATION:
- Brand: {brand}
- Part Number: {manufacturer_part_number}
- Model: {model_number}
- Condition: {condition}
- Notes: {user_notes}

Create optimized eBay listing content:

{
  "listing_title": "60 character max, include brand, model, key features",
  "item_specifics": {
    "Brand": "",
    "MPN": "",
    "Model": "",
    "Type": "",
    "Condition": ""
  },
  "description_sections": {
    "overview": "",
    "specifications": "",
    "condition_details": "",
    "whats_included": ""
  },
  "suggested_categories": [],
  "keywords": []
}

Return ONLY valid JSON.
PROMPT;
    }
}
