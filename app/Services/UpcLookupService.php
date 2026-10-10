<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class UpcLookupService
{
    public function __construct(protected EbayConfigService $ebayConfig) {}

    /**
     * Lookup product data by UPC/EAN barcode
     * Priority: eBay API > UPC Database > Google Search
     */
    public function lookup(string $barcode): ?array
    {
        if (empty($barcode)) {
            return null;
        }

        // Check cache first (cache for 30 days)
        $cacheKey = "upc_lookup_{$barcode}";
        if ($cached = Cache::get($cacheKey)) {
            Log::info('UPC lookup cache hit', ['barcode' => $barcode]);

            return $cached;
        }

        try {
            // Try eBay Product Catalog first (best data)
            $ebayData = $this->lookupEbay($barcode);
            if ($ebayData) {
                Cache::put($cacheKey, $ebayData, now()->addDays(30));

                return $ebayData;
            }

            // Fallback to UPC Database
            $upcDbData = $this->lookupUpcDatabase($barcode);
            if ($upcDbData) {
                Cache::put($cacheKey, $upcDbData, now()->addDays(30));

                return $upcDbData;
            }

            Log::warning('No UPC lookup data found', ['barcode' => $barcode]);

            return null;

        } catch (\Exception $e) {
            Log::error('UPC lookup failed', [
                'barcode' => $barcode,
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    private function lookupEbay(string $barcode): ?array
    {
        $env = $this->ebayConfig->getEnvironment();

        // Match credential to current environment (sandbox on develop, production on prod)
        $credential = \App\Models\IntegrationCredential::where('platform', 'ebay')
            ->where('is_active', true)
            ->where('environment', $env)
            ->first();

        if (! $credential) {
            Log::info('eBay credentials not found for env, skipping', ['env' => $env]);

            return null;
        }

        if ($this->ebayConfig->isSandbox()) {
            Log::info('UPC lookup hitting eBay sandbox - Browse API returns limited/stub data', [
                'barcode' => $barcode,
            ]);
        }

        try {
            // Get application token
            $tokenResponse = Http::asForm()
                ->withBasicAuth(
                    $credential->decrypted_client_id,
                    $credential->decrypted_client_secret
                )
                ->post($this->ebayConfig->getOAuthTokenUrl(), [
                    'grant_type' => 'client_credentials',
                    'scope' => 'https://api.ebay.com/oauth/api_scope',
                ]);

            if (! $tokenResponse->successful()) {
                return null;
            }

            $token = $tokenResponse->json('access_token');

            // Step 1: Browse API to get ePID and basic info
            $browseResponse = Http::withToken($token)
                ->withHeaders(['X-EBAY-C-MARKETPLACE-ID' => 'EBAY_US'])
                ->get($this->ebayConfig->getApiBaseUrl().'/buy/browse/v1/item_summary/search', [
                    'gtin' => $barcode,
                    'limit' => 1,
                ]);

            if (! $browseResponse->successful() || empty($browseResponse->json('itemSummaries'))) {
                return null;
            }

            $item = $browseResponse->json('itemSummaries.0');
            $epid = $item['epid'] ?? null;

            // Step 2: Scrape product page for full item specifics
            $brand = null;
            $model = null;
            $mpn = null;
            $itemSpecifics = [];
            $fullDescription = null;

            if ($epid) {
                $scraper = new \App\Services\EbayProductScraper;
                $productDetails = $scraper->getProductDetails($epid);

                if ($productDetails) {
                    $brand = $productDetails['brand'];
                    $model = $productDetails['model'];
                    $mpn = $productDetails['mpn'];
                    $itemSpecifics = $productDetails['item_specifics'] ?? [];
                    $fullDescription = $productDetails['description'];
                }
            }

            // Merge Browse API data with scraped data
            return [
                'source' => 'ebay_full',
                'epid' => $epid,
                'title' => $item['title'] ?? null,
                'brand' => $brand,
                'model' => $model,
                'mpn' => $mpn,
                'category_id' => $item['leafCategoryIds'][0] ?? null,
                'category_name' => $item['categories'][0]['categoryName'] ?? null,
                'category_path' => $this->buildCategoryPath($item['categories'] ?? []),
                'item_specifics' => $itemSpecifics,
                'description' => $fullDescription ?? $item['shortDescription'] ?? null,
                'short_description' => isset($item['shortDescription']) ? substr($item['shortDescription'], 0, 200) : null,
                'specifications' => $itemSpecifics,
                'condition' => $item['condition'] ?? 'New',
                'images' => isset($item['image']['imageUrl']) ? [$item['image']['imageUrl']] : [],
                'additional_images' => array_map(fn ($img) => $img['imageUrl'], $item['additionalImages'] ?? []),
                'market_price_avg' => $item['price']['value'] ?? null,
                'currency' => $item['price']['currency'] ?? 'USD',
                'upc' => $barcode,
                'item_web_url' => $item['itemWebUrl'] ?? null,
                'retrieved_at' => now()->toIso8601String(),
            ];

        } catch (\Exception $e) {
            Log::warning('eBay lookup failed', ['error' => $e->getMessage()]);

            return null;
        }
    }

    /**
     * Get detailed eBay product information
     */
    private function getEbayProductDetails(array $item): array
    {
        // Extract from item specifics
        $specifics = [];
        $brand = null;
        $model = null;
        $mpn = null;

        if (isset($item['localizedAspects'])) {
            foreach ($item['localizedAspects'] as $aspect) {
                $name = $aspect['name'];
                $value = $aspect['value'];

                // Extract key fields
                if (stripos($name, 'brand') !== false) {
                    $brand = $value;
                }
                if (stripos($name, 'model') !== false) {
                    $model = $value;
                }
                if (stripos($name, 'mpn') !== false || stripos($name, 'manufacturer part') !== false) {
                    $mpn = $value;
                }

                $specifics[$name] = $value;
            }
        }

        return [
            'brand' => $brand,
            'model' => $model,
            'mpn' => $mpn,
            'item_specifics' => $specifics,
            'description' => $item['shortDescription'] ?? null,
            'short_description' => substr($item['shortDescription'] ?? '', 0, 200),
            'specifications' => $specifics,
        ];
    }

    /**
     * Build category path string
     */
    private function buildCategoryPath(array $categories): ?string
    {
        if (empty($categories)) {
            return null;
        }

        $path = array_map(fn ($cat) => $cat['categoryName'], $categories);

        return implode(' > ', $path);
    }

    /**
     * Get eBay OAuth access token
     */
    private function getEbayAccessToken(): string
    {
        // Check cache first
        if ($token = Cache::get('ebay_upc_lookup_token')) {
            return $token;
        }

        $env = $this->ebayConfig->getEnvironment();

        // Try to get active OAuth token from database
        $oauthToken = \App\Models\Integration\OAuthToken::where('platform', 'ebay')
            ->where('environment', $env)
            ->active()
            ->where('expires_at', '>', now())
            ->first();

        if ($oauthToken) {
            $token = $oauthToken->decrypted_access_token;
            Cache::put('ebay_upc_lookup_token', $token, $oauthToken->expires_at->subMinutes(5));

            return $token;
        }

        // Fallback: Get application token using credentials from database
        $credential = \App\Models\IntegrationCredential::where('platform', 'ebay')
            ->where('environment', $env)
            ->where('is_active', true)
            ->first();

        if (! $credential) {
            throw new \Exception('No active eBay credentials found');
        }

        $response = Http::asForm()
            ->withBasicAuth(
                $credential->decrypted_client_id,
                $credential->decrypted_client_secret
            )
            ->post($this->ebayConfig->getOAuthTokenUrl(), [
                'grant_type' => 'client_credentials',
                'scope' => 'https://api.ebay.com/oauth/api_scope',
            ]);

        if (! $response->successful()) {
            Log::error('Failed to get eBay app token', [
                'status' => $response->status(),
                'body' => $response->body(),
            ]);
            throw new \Exception('Failed to get eBay access token');
        }

        $token = $response->json('access_token');
        $expiresIn = $response->json('expires_in', 7200);

        Cache::put('ebay_upc_lookup_token', $token, now()->addSeconds($expiresIn - 300));

        return $token;
    }

    /**
     * Lookup product via UPC Database API
     * Fallback when eBay doesn't have data
     */
    private function lookupUpcDatabase(string $barcode): ?array
    {
        // Check if UPC Database is configured
        $apiKey = config('services.upc_database.api_key');
        if (! $apiKey) {
            Log::info('UPC Database not configured, skipping');

            return null;
        }

        try {
            // Using barcodelookup.com API (free tier: 100/day)
            $response = Http::get('https://api.barcodelookup.com/v3/products', [
                'barcode' => $barcode,
                'key' => $apiKey,
            ]);

            if (! $response->successful() || empty($response->json('products'))) {
                return null;
            }

            $product = $response->json('products.0');

            return [
                'source' => 'upc_database',
                'title' => $product['title'] ?? $product['product_name'] ?? null,
                'brand' => $product['brand'] ?? null,
                'model' => $product['model'] ?? null,
                'mpn' => $product['mpn'] ?? null,
                'category_name' => $product['category'] ?? null,
                'description' => $product['description'] ?? null,
                'images' => $product['images'] ?? [],
                'upc' => $barcode,
                'ean' => $product['ean'] ?? null,
                'asin' => $product['asin'] ?? null,
                'specifications' => $this->extractSpecifications($product),
                'retrieved_at' => now()->toIso8601String(),
            ];

        } catch (\Exception $e) {
            Log::warning('UPC Database lookup failed', [
                'barcode' => $barcode,
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    /**
     * Extract specifications from product data
     */
    private function extractSpecifications(array $product): array
    {
        $specs = [];

        // Common fields
        if (! empty($product['dimensions'])) {
            $specs['dimensions'] = $product['dimensions'];
        }
        if (! empty($product['weight'])) {
            $specs['weight'] = $product['weight'];
        }
        if (! empty($product['color'])) {
            $specs['color'] = $product['color'];
        }
        if (! empty($product['size'])) {
            $specs['size'] = $product['size'];
        }

        // Custom attributes
        if (! empty($product['attributes'])) {
            foreach ($product['attributes'] as $key => $value) {
                $specs[$key] = $value;
            }
        }

        return $specs;
    }

    /**
     * Test UPC lookup
     */
    public function test(string $barcode): array
    {
        return [
            'barcode' => $barcode,
            'ebay_configured' => ! empty(config('services.ebay.app_id')),
            'upc_db_configured' => ! empty(config('services.upc_database.api_key')),
            'lookup_result' => $this->lookup($barcode),
        ];
    }
}
