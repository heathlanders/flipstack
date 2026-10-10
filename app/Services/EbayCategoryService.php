<?php

namespace App\Services;

use App\Models\Ebay\EbayCategory;
use App\Models\Integration\IntegrationCredential;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * COMPREHENSIVE eBay Category Management System
 *
 * Features:
 * - Full eBay category tree sync from API
 * - Intelligent category matching by keywords
 * - Required item specifics per category
 * - Fallback category logic
 * - Category validation and suggestions
 * - Auto-refresh category data
 */
class EbayCategoryService
{
    protected EbayConfigService $config;

    protected string $baseUrl;

    protected EbayRateLimiter $rateLimiter;

    public function __construct(EbayRateLimiter $rateLimiter, ?string $environment = 'production')
    {
        $this->rateLimiter = $rateLimiter;
        $this->config = app(EbayConfigService::class);
        $this->setEnvironment();
    }

    protected function setEnvironment(): void
    {
        $this->baseUrl = $this->config->getApiBaseUrl();
    }

    public function isAvailable(): bool
    {
        return $this->config->isConfigured();
    }

    /**
     * Sync ALL eBay categories from API (run this weekly)
     */
    public function syncAllCategories(): array
    {
        if (! $this->isAvailable()) {
            throw new \Exception('eBay credentials not available for category sync');
        }

        Log::info('Starting full eBay category sync');

        $stats = [
            'total_synced' => 0,
            'categories_added' => 0,
            'categories_updated' => 0,
            'leaf_categories' => 0,
            'errors' => 0,
        ];

        try {
            // Get OAuth token
            $token = $this->getOAuthToken();

            // Sync root categories first
            $rootCategories = $this->fetchCategoriesFromApi($token);

            foreach ($rootCategories as $category) {
                $this->syncCategoryTree($token, $category, null, $stats);
            }

            Log::info('eBay category sync completed', $stats);

            // Cache sync timestamp
            Cache::put('ebay_categories_last_sync', now(), now()->addDays(7));

            return $stats;

        } catch (\Exception $e) {
            Log::error('eBay category sync failed', [
                'error' => $e->getMessage(),
                'stats' => $stats,
            ]);
            throw $e;
        }
    }

    /**
     * Find best matching category for product data
     */
    public function findBestCategory(array $productData): array
    {
        $searchTerms = $this->extractSearchTerms($productData);

        Log::info('Finding best eBay category', [
            'search_terms' => $searchTerms,
            'brand' => $productData['brand'] ?? null,
        ]);

        // Strategy 1: eBay API Category Suggestions (most accurate, eBay's own ML)
        $apiResult = $this->findByApiSuggestion($productData);
        if ($apiResult) {
            Log::info('Found category via eBay API suggestions', ['category' => $apiResult]);

            return $this->formatCategoryResult($apiResult, 95);
        }

        // Strategy 2: Exact brand + model match in local DB
        if (! empty($productData['brand']) && ! empty($productData['model'])) {
            $exactMatch = $this->findByExactMatch($productData['brand'], $productData['model']);
            if ($exactMatch) {
                Log::info('Found exact brand+model match', ['category' => $exactMatch]);

                return $this->formatCategoryResult($exactMatch, 95);
            }
        }

        // Strategy 3: Search by combined keywords in local DB
        $keywordMatch = $this->searchByKeywords($searchTerms);
        if ($keywordMatch) {
            Log::info('Found keyword match', ['category' => $keywordMatch]);

            return $this->formatCategoryResult($keywordMatch, 85);
        }

        // Strategy 4: Brand-specific category
        if (! empty($productData['brand'])) {
            $brandMatch = $this->findByBrand($productData['brand']);
            if ($brandMatch) {
                Log::info('Found brand-specific category', ['category' => $brandMatch]);

                return $this->formatCategoryResult($brandMatch, 75);
            }
        }

        // Strategy 5: Product type fallback
        $typeMatch = $this->findByProductType($productData);
        if ($typeMatch) {
            Log::info('Using product type fallback', ['category' => $typeMatch]);

            return $this->formatCategoryResult($typeMatch, 60);
        }

        // Strategy 6: Default fallback
        $fallback = $this->getDefaultCategory();
        Log::warning('Using default category fallback', ['category' => $fallback]);

        return $this->formatCategoryResult($fallback, 30);
    }

    /**
     * Get required item specifics for a category
     */
    public function getRequiredItemSpecifics(string $categoryId): array
    {
        $cacheKey = "ebay_category_specifics_{$categoryId}";

        $cached = Cache::get($cacheKey);
        if ($cached !== null) {
            return $cached;
        }

        try {
            $token = $this->getOAuthToken();

            $result = $this->rateLimiter->executeWithRateLimit(
                'ebay_category_specifics',
                function () use ($token, $categoryId) {
                    return $this->fetchCategorySpecifics($token, $categoryId);
                },
                3
            );

            // Only cache non-empty results to avoid poisoning cache on API failures
            if (! empty($result)) {
                Cache::put($cacheKey, $result, 60 * 60 * 24);
            }

            return $result;
        } catch (\Exception $e) {
            Log::error('Failed to get category specifics', [
                'category_id' => $categoryId,
                'error' => $e->getMessage(),
            ]);

            return [];
        }
    }

    /**
     * Validate category selection and get suggestions
     */
    public function validateCategoryChoice(string $categoryId, array $productData): array
    {
        $category = EbayCategory::where('category_id', $categoryId)->first();

        if (! $category) {
            return [
                'valid' => false,
                'error' => 'Category not found',
                'suggestions' => $this->findBestCategory($productData),
            ];
        }

        if (! $category->is_leaf) {
            return [
                'valid' => false,
                'error' => 'Category is not a leaf category (cannot list in parent categories)',
                'suggestions' => $this->getChildCategories($categoryId),
            ];
        }

        // Get required specifics
        $requiredSpecifics = $this->getRequiredItemSpecifics($categoryId);
        $missingSpecifics = $this->checkMissingSpecifics($productData, $requiredSpecifics);

        return [
            'valid' => empty($missingSpecifics),
            'category' => $category,
            'required_specifics' => $requiredSpecifics,
            'missing_specifics' => $missingSpecifics,
            'warnings' => $this->getCategoryWarnings($category, $productData),
        ];
    }

    /**
     * Get category tree path (e.g. "Electronics > Computer Components > Memory")
     */
    public function getCategoryPath(string $categoryId): string
    {
        $category = EbayCategory::where('category_id', $categoryId)->first();

        if (! $category) {
            return 'Unknown Category';
        }

        $path = [$category->name];
        $current = $category;

        while ($current->parent_id) {
            $parent = EbayCategory::where('category_id', $current->parent_id)->first();
            if (! $parent) {
                break;
            }

            array_unshift($path, $parent->name);
            $current = $parent;
        }

        return implode(' > ', $path);
    }

    /**
     * Search categories by text
     */
    public function searchCategories(string $query, int $limit = 20): array
    {
        $results = EbayCategory::where('is_leaf', true)
            ->where(function ($q) use ($query) {
                $q->where('name', 'LIKE', "%{$query}%")
                    ->orWhere('keywords', 'LIKE', "%{$query}%")
                    ->orWhere('category_path', 'LIKE', "%{$query}%");
            })
            ->orderByRaw("
                CASE 
                    WHEN name LIKE '{$query}%' THEN 1
                    WHEN name LIKE '%{$query}%' THEN 2
                    WHEN keywords LIKE '%{$query}%' THEN 3
                    ELSE 4
                END
            ")
            ->limit($limit)
            ->get();

        return $results->map(function ($category) {
            return [
                'category_id' => $category->category_id,
                'name' => $category->name,
                'path' => $this->getCategoryPath($category->category_id),
                'level' => $category->level,
                'listing_count' => $category->item_count ?? 0,
            ];
        })->toArray();
    }

    // ============================================================================
    // EBAY API CATEGORY SUGGESTIONS
    // ============================================================================

    /**
     * Get category suggestions from eBay Taxonomy API
     */
    public function getCategorySuggestionsFromApi(string $query): array
    {
        if (empty($query) || ! $this->isAvailable()) {
            return [];
        }

        $cacheKey = 'ebay_cat_suggestions_'.md5($query);

        return Cache::remember($cacheKey, 60 * 60 * 24, function () use ($query) {
            try {
                $token = $this->getOAuthToken();

                return $this->rateLimiter->executeWithRateLimit(
                    'ebay_category_suggestions',
                    function () use ($token, $query) {
                        $response = Http::withToken($token)
                            ->timeout(15)
                            ->get($this->baseUrl.'/commerce/taxonomy/v1/category_tree/0/get_category_suggestions', [
                                'q' => $query,
                            ]);

                        if (! $response->successful()) {
                            Log::warning('eBay category suggestions API failed', [
                                'status' => $response->status(),
                                'query' => $query,
                            ]);

                            return [];
                        }

                        return $response->json('categorySuggestions', []);
                    },
                    2
                );
            } catch (\Exception $e) {
                Log::warning('eBay category suggestions error', [
                    'query' => $query,
                    'error' => $e->getMessage(),
                ]);

                return [];
            }
        });
    }

    /**
     * Find category using eBay API suggestions as primary strategy
     */
    protected function findByApiSuggestion(array $productData): ?EbayCategory
    {
        // Build query from brand + model + title
        $queryParts = array_filter([
            $productData['brand'] ?? '',
            $productData['model'] ?? '',
            $productData['title'] ?? $productData['name'] ?? '',
        ]);

        if (empty($queryParts)) {
            return null;
        }

        $query = implode(' ', array_slice($queryParts, 0, 3));
        $suggestions = $this->getCategorySuggestionsFromApi($query);

        if (empty($suggestions)) {
            return null;
        }

        // Use first leaf category suggestion
        foreach ($suggestions as $suggestion) {
            $category = $suggestion['category'] ?? [];
            $categoryId = $category['categoryId'] ?? null;

            if (! $categoryId) {
                continue;
            }

            // Build path from ancestors
            $ancestors = $suggestion['categoryTreeNodeAncestors'] ?? [];
            $pathParts = [];
            // Ancestors are ordered from immediate parent to root, so reverse
            foreach (array_reverse($ancestors) as $ancestor) {
                $pathParts[] = $ancestor['categoryName'] ?? '';
            }
            $pathParts[] = $category['categoryName'] ?? '';
            $categoryPath = implode(' > ', array_filter($pathParts));

            // Upsert to local DB for future lookups
            $localCategory = $this->upsertCategoryFromApi([
                'category_id' => $categoryId,
                'name' => $category['categoryName'] ?? '',
                'category_path' => $categoryPath,
                'parent_id' => $ancestors[0]['categoryId'] ?? null,
                'is_leaf' => true,
            ]);

            if ($localCategory) {
                return $localCategory;
            }
        }

        return null;
    }

    /**
     * Save an API-returned category to the local DB for future offline lookups
     */
    protected function upsertCategoryFromApi(array $apiResult): ?EbayCategory
    {
        try {
            return EbayCategory::updateOrCreate(
                ['category_id' => $apiResult['category_id']],
                [
                    'name' => $apiResult['name'],
                    'category_path' => $apiResult['category_path'] ?? $apiResult['name'],
                    'parent_id' => $apiResult['parent_id'] ?? null,
                    'level' => $this->calculateLevel($apiResult['parent_id'] ?? null),
                    'is_leaf' => $apiResult['is_leaf'] ?? true,
                    'keywords' => $this->generateKeywords($apiResult['name']),
                    'last_synced_at' => now(),
                    'is_active' => true,
                ]
            );
        } catch (\Exception $e) {
            Log::warning('Failed to upsert category from API', [
                'category_id' => $apiResult['category_id'],
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    // ============================================================================
    // PROTECTED METHODS - CORE IMPLEMENTATION
    // ============================================================================

    protected function extractSearchTerms(array $productData): array
    {
        $terms = [];

        // Primary identifiers
        if (! empty($productData['brand'])) {
            $terms[] = $productData['brand'];
        }
        if (! empty($productData['model'])) {
            $terms[] = $productData['model'];
        }

        // Category hints
        if (! empty($productData['category'])) {
            $terms[] = $productData['category'];
        }
        if (! empty($productData['subcategory'])) {
            $terms[] = $productData['subcategory'];
        }

        // Product type from title/description
        $title = $productData['title'] ?? $productData['name'] ?? '';
        $description = $productData['description'] ?? '';

        // Extract key product terms
        $productTerms = $this->extractProductTerms($title.' '.$description);
        $terms = array_merge($terms, $productTerms);

        // Keywords if available
        if (! empty($productData['keywords'])) {
            if (is_array($productData['keywords'])) {
                $terms = array_merge($terms, $productData['keywords']);
            }
        }

        return array_unique(array_filter($terms));
    }

    protected function extractProductTerms(string $text): array
    {
        $text = strtolower($text);
        $terms = [];

        $allTerms = [
            // Computers
            'laptop', 'notebook', 'chromebook', 'ultrabook', 'gaming laptop',
            'desktop', 'workstation', 'all-in-one', 'mini pc',
            'tablet', 'ipad',
            // POS
            'terminal', 'pos', 'point of sale', 'receipt printer', 'cash drawer',
            'barcode scanner', 'cash register', 'payment terminal', 'pos system',
            'pos printer', 'pos terminal', 'point-of-sale',
            // Peripherals
            'monitor', 'display', 'touchscreen', 'printer', 'scanner',
            'projector', 'camera',
            // Networking
            'server', 'networking', 'router', 'switch', 'firewall',
            'access point',
            // Components
            'computer', 'cable', 'adapter', 'power supply', 'motherboard',
            'processor', 'memory', 'storage', 'hard drive', 'ssd',
            'graphics card', 'sound card', 'network card',
            // Phones
            'smartphone', 'cell phone', 'iphone',
            // Apparel & Fashion
            'dress', 'shirt', 'jacket', 'pants', 'jeans', 'shoes', 'boots',
            'sneakers', 'sandals', 'handbag', 'purse', 'wallet', 'watch',
            'sunglasses', 'hat', 'coat', 'sweater',
            // Home & Garden
            'furniture', 'lamp', 'rug', 'mattress', 'cookware', 'blender',
            'vacuum', 'garden', 'power tool', 'drill', 'saw',
            // Sports & Outdoors
            'bicycle', 'bike', 'golf', 'fishing', 'camping', 'tent',
            'kayak', 'skateboard', 'treadmill',
            // Musical Instruments
            'guitar', 'piano', 'keyboard', 'drums', 'violin', 'amplifier',
            'microphone', 'synthesizer', 'ukulele',
            // Toys & Collectibles
            'lego', 'action figure', 'board game', 'puzzle', 'doll',
            'trading card', 'vinyl record', 'antique', 'vintage',
            // Auto Parts
            'headlight', 'bumper', 'exhaust', 'brake', 'alternator', 'radiator',
        ];

        foreach ($allTerms as $term) {
            if (str_contains($text, $term)) {
                $terms[] = $term;
            }
        }

        return $terms;
    }

    protected function findByExactMatch(string $brand, string $model): ?EbayCategory
    {
        return EbayCategory::where('is_leaf', true)
            ->where(function ($q) use ($brand, $model) {
                $q->where('keywords', 'LIKE', "%{$brand}%")
                    ->where('keywords', 'LIKE', "%{$model}%");
            })
            ->orderBy('item_count', 'desc')
            ->first();
    }

    protected function searchByKeywords(array $terms): ?EbayCategory
    {
        if (empty($terms)) {
            return null;
        }

        $query = EbayCategory::where('is_leaf', true);

        foreach ($terms as $term) {
            $query->where(function ($q) use ($term) {
                $q->where('name', 'LIKE', "%{$term}%")
                    ->orWhere('keywords', 'LIKE', "%{$term}%")
                    ->orWhere('category_path', 'LIKE', "%{$term}%");
            });
        }

        return $query->orderBy('item_count', 'desc')->first();
    }

    protected function findByBrand(string $brand): ?EbayCategory
    {
        // Brand-specific category mapping (only brands with known primary categories)
        $brandCategories = [
            'NCR' => ['46530', '11176'], // POS Terminals, Cash Registers
            'IBM' => ['46530'],
            'EPSON' => ['46530'],
            'CISCO' => ['175700', '182094', '175701'], // Routers, Switches, Firewalls
        ];

        $categoryIds = $brandCategories[strtoupper($brand)] ?? null;

        if ($categoryIds) {
            foreach ($categoryIds as $categoryId) {
                $category = EbayCategory::where('category_id', $categoryId)
                    ->where('is_leaf', true)
                    ->first();
                if ($category) {
                    return $category;
                }
            }
        }

        // No POS fallback -- return null so the next strategy runs
        return null;
    }

    protected function findByProductType(array $productData): ?EbayCategory
    {
        $title = strtolower($productData['title'] ?? $productData['name'] ?? '');
        $description = strtolower($productData['description'] ?? '');
        $text = $title.' '.$description;

        // Map to known leaf category IDs only
        $typeMap = [
            // Computers
            'laptop' => '175672',
            'notebook' => '175672',
            'chromebook' => '175672',
            'ultrabook' => '175672',
            'desktop' => '179',
            'workstation' => '179',
            'all-in-one' => '179',
            'tablet' => '171485',
            'ipad' => '171485',

            // POS Equipment
            'pos terminal' => '46530',
            'point of sale' => '46530',
            'cash register' => '11176',
            'pos' => '46530',

            // Networking
            'router' => '175700',
            'switch' => '182094',
            'firewall' => '175701',

            // Peripherals
            'monitor' => '80053',
            'printer' => '171961',
            'scanner' => '171961',

            // Test equipment
            'oscilloscope' => '104247',

            // Phones
            'smartphone' => '9355',
            'cell phone' => '9355',
            'iphone' => '9355',

            // Musical Instruments
            'guitar' => '33034',
            'piano' => '180061',
            'drums' => '180012',

            // Clothing
            'dress' => '63861',
            'shoes' => '95672',
            'boots' => '11498',
            'jacket' => '57988',
            'jeans' => '11483',

            // Home & Garden
            'vacuum' => '20614',
            'power tool' => '631',
            'drill' => '631',

            // Sports
            'golf' => '1513',
            'bicycle' => '177831',
            'fishing' => '1492',

            // Toys & Collectibles
            'lego' => '19006',
            'action figure' => '246',
            'trading card' => '183454',
        ];

        foreach ($typeMap as $keyword => $categoryId) {
            if (str_contains($text, $keyword)) {
                $category = EbayCategory::where('category_id', $categoryId)
                    ->where('is_leaf', true)
                    ->first();
                if ($category) {
                    return $category;
                }
            }
        }

        return null;
    }

    protected function getDefaultCategory(): EbayCategory
    {
        // Generic leaf category -- only reached when ALL strategies (including API) fail
        return EbayCategory::where('category_id', '10290')
            ->where('is_leaf', true)
            ->first()
            ?? new EbayCategory([
                'category_id' => '10290',
                'name' => 'Other',
                'category_path' => 'Everything Else > Other',
                'is_leaf' => true,
                'level' => 2,
            ]);
    }

    protected function formatCategoryResult(EbayCategory $category, int $confidence): array
    {
        return [
            'category_id' => $category->category_id,
            'name' => $category->name,
            'path' => $this->getCategoryPath($category->category_id),
            'confidence' => $confidence,
            'is_leaf' => $category->is_leaf,
            'level' => $category->level,
            'item_count' => $category->item_count ?? 0,
        ];
    }

    protected function fetchCategoriesFromApi(string $token, ?string $parentId = null): array
    {
        return $this->rateLimiter->executeWithRateLimit(
            'ebay_category_api',
            function () use ($token) {
                $url = $this->baseUrl.'/commerce/taxonomy/v1/category_tree/0';

                $response = Http::withToken($token)
                    ->timeout(30)
                    ->get($url);

                if (! $response->successful()) {
                    throw new \Exception('eBay Category API error: '.$response->body());
                }

                $data = $response->json();

                // eBay returns categories inside rootCategoryNode.childCategoryTreeNodes
                return $data['rootCategoryNode']['childCategoryTreeNodes'] ?? [];
            },
            3
        );
    }

    protected function syncCategoryTree(string $token, array $categoryData, ?string $parentId, array &$stats): void
    {
        try {
            // eBay uses nested structure: categoryData.category.categoryId
            $category_info = $categoryData['category'] ?? [];
            $categoryId = $category_info['categoryId'] ?? null;
            $categoryName = $category_info['categoryName'] ?? null;

            if (! $categoryId || ! $categoryName) {
                Log::warning('Missing category data', ['data' => $categoryData]);

                return;
            }

            $category = EbayCategory::updateOrCreate(
                ['category_id' => $categoryId],
                [
                    'name' => $categoryName,
                    'parent_id' => $parentId,
                    'level' => $this->calculateLevel($parentId),
                    'is_leaf' => $categoryData['leafCategoryTreeNode'] ?? false, // eBay uses leafCategoryTreeNode
                    'category_path' => $this->buildCategoryPath($categoryName, $parentId),
                    'keywords' => $this->generateKeywords($categoryName),
                    'last_synced_at' => now(),
                ]
            );

            $stats['total_synced']++;
            if ($category->wasRecentlyCreated) {
                $stats['categories_added']++;
            } else {
                $stats['categories_updated']++;
            }

            if ($category->is_leaf) {
                $stats['leaf_categories']++;
            }

            // Sync children recursively - eBay uses childCategoryTreeNodes
            if (! empty($categoryData['childCategoryTreeNodes'])) {
                foreach ($categoryData['childCategoryTreeNodes'] as $child) {
                    $this->syncCategoryTree($token, $child, $categoryId, $stats);
                }
            }

        } catch (\Exception $e) {
            $stats['errors']++;
            Log::error('Failed to sync category', [
                'category_data' => $categoryData,
                'error' => $e->getMessage(),
            ]);
        }
    }

    protected function getOAuthToken(): string
    {
        // Get application token for category APIs
        $cacheKey = 'ebay_app_token_categories';

        return Cache::remember($cacheKey, 60 * 110, function () {
            return $this->rateLimiter->executeWithRateLimit(
                'ebay_oauth_app',
                function () {
                    $response = Http::asForm()
                        ->withBasicAuth(
                            $this->config->getClientId(),
                            $this->config->getClientSecret()
                        )
                        ->post($this->baseUrl.'/identity/v1/oauth2/token', [
                            'grant_type' => 'client_credentials',
                            'scope' => 'https://api.ebay.com/oauth/api_scope',
                        ]);

                    if (! $response->successful()) {
                        throw new \Exception('Failed to get eBay app token: '.$response->body());
                    }

                    return $response->json('access_token');
                }
            );
        });
    }

    // Additional helper methods...
    protected function calculateLevel(?string $parentId): int
    {
        if (! $parentId) {
            return 1;
        }

        $parent = EbayCategory::where('category_id', $parentId)->first();

        return $parent ? $parent->level + 1 : 1;
    }

    protected function buildCategoryPath(string $name, ?string $parentId): string
    {
        if (! $parentId) {
            return $name;
        }

        $parent = EbayCategory::where('category_id', $parentId)->first();

        return $parent ? $parent->category_path.' > '.$name : $name;
    }

    protected function generateKeywords(string $categoryName): string
    {
        // Generate searchable keywords from category name
        $keywords = [];
        $keywords[] = strtolower($categoryName);
        $keywords[] = str_replace(['&', ' and '], ' ', strtolower($categoryName));
        $words = explode(' ', strtolower($categoryName));
        $keywords = array_merge($keywords, $words);

        return implode(' ', array_unique(array_filter($keywords)));
    }

    protected function fetchCategorySpecifics(string $token, string $categoryId): array
    {
        $url = $this->baseUrl.'/commerce/taxonomy/v1/category_tree/0/get_item_aspects_for_category';

        $response = Http::withToken($token)
            ->timeout(30)
            ->get($url, ['category_id' => $categoryId]);

        if (! $response->successful()) {
            Log::warning('Failed to get category specifics', [
                'category_id' => $categoryId,
                'status' => $response->status(),
            ]);

            return [];
        }

        // Normalize to internal shape with full metadata for downstream use
        return collect($response->json('aspects', []))
            ->map(fn ($aspect) => [
                'name' => $aspect['localizedAspectName'] ?? '',
                'cardinality' => ($aspect['aspectConstraint']['aspectRequired'] ?? false)
                                    ? 'REQUIRED'
                                    : 'OPTIONAL',
                'mode' => $aspect['aspectConstraint']['aspectMode'] ?? 'FREE_TEXT',
                'allowed_values' => collect($aspect['aspectValues'] ?? [])
                    ->pluck('localizedValue')
                    ->filter()
                    ->values()
                    ->all(),
            ])
            ->filter(fn ($a) => ! empty($a['name']))
            ->values()
            ->all();
    }

    /**
     * Build a concise aspect summary for AI prompt injection.
     * Returns a text block describing required/recommended aspects with allowed values.
     */
    public function getAspectsForPrompt(string $categoryId): string
    {
        $aspects = $this->getRequiredItemSpecifics($categoryId);

        if (empty($aspects)) {
            return '';
        }

        $lines = [];
        foreach ($aspects as $aspect) {
            $name = $aspect['name'];
            $required = $aspect['cardinality'] === 'REQUIRED' ? 'REQUIRED' : 'RECOMMENDED';
            $mode = $aspect['mode'] ?? 'FREE_TEXT';
            $allowed = $aspect['allowed_values'] ?? [];

            $line = "- {$name} ({$required})";
            if ($mode === 'SELECTION_ONLY' && ! empty($allowed)) {
                // Limit to 15 values to keep prompt manageable
                $sample = array_slice($allowed, 0, 15);
                $line .= ': '.implode(', ', $sample);
                if (count($allowed) > 15) {
                    $line .= ' ... ('.count($allowed).' total options)';
                }
            } elseif (! empty($allowed)) {
                $sample = array_slice($allowed, 0, 10);
                $line .= ' [examples: '.implode(', ', $sample).']';
            }

            $lines[] = $line;
        }

        return implode("\n", $lines);
    }

    /**
     * Find the closest valid eBay condition within the same condition family.
     *
     * Only falls back within the same family (Used, Refurbished, New).
     * Cross-family mapping is never performed because it would misrepresent the
     * item. Returns null when no within-family match exists.
     */
    public function findClosestValidCondition(string $requested, array $validConditions): ?string
    {
        $adjacents = [
            'USED_EXCELLENT' => ['USED_VERY_GOOD', 'USED_GOOD', 'USED_ACCEPTABLE'],
            'USED_VERY_GOOD' => ['USED_GOOD', 'USED_EXCELLENT', 'USED_ACCEPTABLE'],
            'USED_GOOD' => ['USED_VERY_GOOD', 'USED_ACCEPTABLE', 'USED_EXCELLENT'],
            'USED_ACCEPTABLE' => ['USED_GOOD', 'USED_VERY_GOOD', 'USED_EXCELLENT'],
            'EXCELLENT_REFURBISHED' => ['VERY_GOOD_REFURBISHED', 'GOOD_REFURBISHED'],
            'VERY_GOOD_REFURBISHED' => ['GOOD_REFURBISHED', 'EXCELLENT_REFURBISHED'],
            'GOOD_REFURBISHED' => ['VERY_GOOD_REFURBISHED', 'EXCELLENT_REFURBISHED'],
            'CERTIFIED_REFURBISHED' => ['MANUFACTURER_REFURBISHED', 'SELLER_REFURBISHED', 'EXCELLENT_REFURBISHED', 'VERY_GOOD_REFURBISHED', 'GOOD_REFURBISHED'],
            'MANUFACTURER_REFURBISHED' => ['CERTIFIED_REFURBISHED', 'SELLER_REFURBISHED', 'EXCELLENT_REFURBISHED', 'VERY_GOOD_REFURBISHED', 'GOOD_REFURBISHED'],
            'SELLER_REFURBISHED' => ['CERTIFIED_REFURBISHED', 'MANUFACTURER_REFURBISHED', 'EXCELLENT_REFURBISHED', 'VERY_GOOD_REFURBISHED', 'GOOD_REFURBISHED'],
            'NEW' => ['NEW_OTHER', 'NEW_WITH_DEFECTS'],
            'NEW_OTHER' => ['NEW', 'NEW_WITH_DEFECTS'],
            'NEW_WITH_DEFECTS' => ['NEW_OTHER', 'NEW'],
            'FOR_PARTS_OR_NOT_WORKING' => [],
        ];

        $chain = $adjacents[$requested] ?? [];

        foreach ($chain as $candidate) {
            if (in_array($candidate, $validConditions)) {
                return $candidate;
            }
        }

        return null;
    }

    /**
     * Return the condition family name for a given eBay condition enum.
     */
    public function getConditionFamily(string $condition): string
    {
        return match (true) {
            in_array($condition, ['NEW', 'NEW_OTHER', 'NEW_WITH_DEFECTS']) => 'new',
            in_array($condition, ['CERTIFIED_REFURBISHED', 'MANUFACTURER_REFURBISHED', 'SELLER_REFURBISHED', 'EXCELLENT_REFURBISHED', 'VERY_GOOD_REFURBISHED', 'GOOD_REFURBISHED']) => 'refurbished',
            in_array($condition, ['USED_EXCELLENT', 'USED_VERY_GOOD', 'USED_GOOD', 'USED_ACCEPTABLE']) => 'used',
            $condition === 'FOR_PARTS_OR_NOT_WORKING' => 'parts',
            default => 'unknown',
        };
    }

    /**
     * Perform a preliminary category lookup for AI prompting.
     * Lighter than the full findBestCategory -- just needs a rough match.
     */
    public function findPreliminaryCategory(array $searchData): ?array
    {
        try {
            $result = $this->findBestCategory($searchData);

            if (! empty($result['category_id'])) {
                return $result;
            }
        } catch (\Exception $e) {
            Log::warning('Preliminary category lookup failed', ['error' => $e->getMessage()]);
        }

        return null;
    }

    /**
     * Get valid condition enums for a given eBay category.
     * Returns empty array on any failure (fail-open: allows listing to proceed).
     * Results cached in Redis for 7 days.
     */
    public function getValidConditionsForCategory(
        string $categoryId,
        string $marketplaceId = 'EBAY_US',
        ?IntegrationCredential $credential = null
    ): array {
        $cacheKey = "ebay_condition_policies_{$marketplaceId}_{$categoryId}";

        $cached = Cache::get($cacheKey);
        if (! empty($cached)) {
            return $cached;
        }

        try {
            $token = null;
            if ($credential) {
                $userToken = $credential->getActiveEbayToken();
                $token = $userToken?->decrypted_access_token;
            }
            $token ??= $this->getOAuthToken();

            $response = Http::withToken($token)
                ->timeout(15)
                ->get($this->baseUrl.'/sell/metadata/v1/marketplace/'.$marketplaceId.'/get_item_condition_policies', [
                    'filter' => "categoryIds:{{$categoryId}}",
                ]);

            if (! $response->successful()) {
                Log::warning('eBay condition policies fetch failed', [
                    'category_id' => $categoryId,
                    'status' => $response->status(),
                    'body' => $response->body(),
                    'used_user_token' => $credential !== null,
                ]);

                return [];
            }

            foreach ($response->json('itemConditionPolicies', []) as $policy) {
                if ((string) ($policy['categoryId'] ?? '') === (string) $categoryId) {
                    $conditions = collect($policy['itemConditions'] ?? [])
                        ->map(fn ($c) => $this->mapConditionIdToEnum($c['conditionId'] ?? ''))
                        ->filter()
                        ->values()
                        ->all();

                    if (! empty($conditions)) {
                        Cache::put($cacheKey, $conditions, 60 * 60 * 24 * 7);
                    }

                    return $conditions;
                }
            }

            Log::warning('eBay condition policies: category not found in API response', [
                'category_id' => $categoryId,
                'marketplace_id' => $marketplaceId,
                'returned_categories' => collect(
                    $response->json('itemConditionPolicies', [])
                )->pluck('categoryId')->all(),
            ]);
        } catch (\Exception $e) {
            Log::warning('eBay condition policies exception', [
                'error' => $e->getMessage(),
                'used_user_token' => $credential !== null,
            ]);
        }

        return [];
    }

    protected function checkMissingSpecifics(array $productData, array $requiredSpecifics): array
    {
        $missing = [];

        foreach ($requiredSpecifics as $specific) {
            $name = $specific['name'] ?? '';
            $required = $specific['cardinality'] === 'REQUIRED';

            if ($required && empty($productData['aspects'][$name])) {
                $missing[] = $name;
            }
        }

        return $missing;
    }

    protected function getCategoryWarnings(EbayCategory $category, array $productData): array
    {
        $warnings = [];

        // Check if category seems like a good match
        $categoryName = strtolower($category->name);
        $productBrand = strtolower($productData['brand'] ?? '');

        if ($productBrand === 'ncr' && ! str_contains($categoryName, 'business') && ! str_contains($categoryName, 'point of sale')) {
            $warnings[] = 'NCR products typically belong in Business & Industrial categories';
        }

        // Warn when generic "Everything Else" default is used
        if ($category->category_id === '10290') {
            $warnings[] = 'Product was assigned the generic "Everything Else > Other" category. Consider selecting a more specific category for better visibility.';
        }

        return $warnings;
    }

    protected function getChildCategories(string $parentId): array
    {
        return EbayCategory::where('parent_id', $parentId)
            ->where('is_leaf', true)
            ->orderBy('item_count', 'desc')
            ->limit(10)
            ->get()
            ->map(function ($cat) {
                return $this->formatCategoryResult($cat, 70);
            })
            ->toArray();
    }

    private function mapConditionIdToEnum(string $conditionId): ?string
    {
        $mapping = [
            '1000' => 'NEW',
            '1500' => 'NEW_OTHER',
            '1750' => 'NEW_WITH_DEFECTS',
            '2000' => 'CERTIFIED_REFURBISHED',
            '2010' => 'EXCELLENT_REFURBISHED',
            '2020' => 'VERY_GOOD_REFURBISHED',
            '2030' => 'GOOD_REFURBISHED',
            '2500' => 'SELLER_REFURBISHED',
            '3000' => 'USED_EXCELLENT',
            '4000' => 'USED_VERY_GOOD',
            '5000' => 'USED_GOOD',
            '6000' => 'USED_ACCEPTABLE',
            '7000' => 'FOR_PARTS_OR_NOT_WORKING',
        ];

        return $mapping[$conditionId] ?? null;
    }
}
