<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class EbayBrowseService
{
    protected ?string $clientId = null;

    protected ?string $clientSecret = null;

    protected string $browseApiUrl;

    protected string $oauthUrl;

    protected bool $isSandbox = false;

    protected EbayRateLimiter $rateLimiter;

    public function __construct(EbayRateLimiter $rateLimiter, EbayConfigService $config)
    {
        $this->rateLimiter = $rateLimiter;
        $this->browseApiUrl = $config->getApiBaseUrl().'/buy/browse/v1';
        $this->oauthUrl = $config->getOAuthTokenUrl();
        $this->isSandbox = $config->isSandbox();
        $this->loadCredentials($config);
    }

    protected function loadCredentials(EbayConfigService $config): void
    {
        try {
            $this->clientId = $config->getClientId();
            $this->clientSecret = $config->getClientSecret();
        } catch (\Throwable $e) {
            Log::warning('Failed to load eBay Browse credentials', ['error' => $e->getMessage()]);
        }
    }

    public function isAvailable(): bool
    {
        return ! empty($this->clientId) && ! empty($this->clientSecret);
    }

    /**
     * Get OAuth 2.0 access token (cached for 2 hours)
     */
    protected function getAccessToken(): ?string
    {
        return Cache::remember('ebay_oauth_token', 60 * 110, function () {
            return $this->rateLimiter->executeWithRateLimit(
                'ebay_oauth',
                function () {
                    try {
                        $response = Http::asForm()
                            ->withBasicAuth($this->clientId, $this->clientSecret)
                            ->post($this->oauthUrl, [
                                'grant_type' => 'client_credentials',
                                'scope' => 'https://api.ebay.com/oauth/api_scope',
                            ]);

                        if ($response->successful()) {
                            return $response->json('access_token');
                        }

                        Log::error('eBay OAuth failed', [
                            'status' => $response->status(),
                            'body' => $response->body(),
                        ]);

                        return null;
                    } catch (\Exception $e) {
                        Log::error('eBay OAuth exception', ['error' => $e->getMessage()]);

                        return null;
                    }
                }
            );
        });
    }

    public function search(array $identifiers): EbaySearchResult
    {
        if (! $this->isAvailable()) {
            Log::warning('eBay Browse API unavailable - no credentials');

            return new EbaySearchResult([], []);
        }

        $token = $this->getAccessToken();
        if (! $token) {
            Log::error('Failed to get eBay OAuth token');

            return new EbaySearchResult([], []);
        }

        // Build ONE smart query instead of cascading
        $query = $this->buildSmartQuery($identifiers);

        Log::info('eBay smart search', ['query' => $query, 'sandbox' => $this->isSandbox]);

        if ($this->isSandbox) {
            Log::warning('eBay Browse sandbox returns limited/stub data; results unreliable on develop');
        }

        $cacheKey = 'ebay_browse_smart_'.($this->isSandbox ? 'sbx_' : '').md5($query);

        $results = Cache::remember($cacheKey, 60 * 6, function () use ($token, $query) {
            return $this->searchActiveListings($token, $query);
        });

        Log::info('eBay search complete', [
            'total_listings' => count($results),
            'api_calls' => 1, // Only 1 call!
        ]);

        return new EbaySearchResult([], $results);
    }

    /**
     * Build ONE smart query that gets best results.
     *
     * Prefers a query already optimized upstream (orchestrator); otherwise builds
     * from the real brand + FULL part number + top keywords. The part number is the
     * universal key for any product, so it must not be truncated or brand-defaulted.
     */
    protected function buildSmartQuery(array $identifiers): string
    {
        // Use a prebuilt query from the orchestrator when provided
        // (accepts 'search_query' or the legacy 'query' key).
        $prebuilt = trim((string) ($identifiers['search_query'] ?? $identifiers['query'] ?? ''));
        if ($prebuilt !== '') {
            return $prebuilt;
        }

        $brand = trim((string) ($identifiers['brand'] ?? ''));
        $partNumber = $identifiers['manufacturer_part_number']
            ?? $identifiers['product_number']
            ?? $identifiers['model_number']
            ?? null;
        $keywords = $identifiers['keywords'] ?? [];

        $parts = [];

        // Real brand only; never default to a hardcoded vendor.
        if ($brand !== '') {
            $parts[] = $brand;
        }

        // FULL part number (dashes normalized to spaces for eBay's tokenizer).
        if (! empty($partNumber)) {
            $parts[] = str_replace('-', ' ', trim((string) $partNumber));
        }

        // Add top 2 keywords only.
        if (! empty($keywords)) {
            $parts[] = $keywords[0];
            if (isset($keywords[1])) {
                $parts[] = $keywords[1];
            }
        }

        return trim(implode(' ', array_filter($parts)));
    }

    /**
     * Build cascading search strategies from most precise to broadest
     */
    protected function buildSearchStrategies(array $identifiers): array
    {
        $strategies = [];

        $brand = $identifiers['brand'] ?? null;
        $ncrId = $identifiers['manufacturer_part_number'] ?? null;
        $productNumber = $identifiers['product_number'] ?? null;
        $barcode = $identifiers['barcode'] ?? null;
        $keywords = $identifiers['keywords'] ?? [];

        // STAGE 1: Exact part number with dashes
        if ($ncrId) {
            $strategies['Stage 1: Exact part number'] = $ncrId;
        }

        // STAGE 2: Part number variations (no dashes, no spaces)
        if ($ncrId) {
            $noDashes = str_replace('-', ' ', $ncrId);
            $strategies['Stage 2a: Part number (no dashes)'] = $noDashes;

            $noSpaces = str_replace(['-', ' '], '', $ncrId);
            $strategies['Stage 2b: Part number (no spaces)'] = $noSpaces;
        }

        // Also try product number if different
        if ($productNumber && $productNumber !== $ncrId) {
            $strategies['Stage 2c: Product number'] = $productNumber;
        }

        // STAGE 3: Brand + exact identifiers
        if ($brand) {
            if ($ncrId) {
                $strategies['Stage 3a: Brand + Part number'] = "{$brand} {$ncrId}";
            }
            if ($productNumber && $productNumber !== $ncrId) {
                $strategies['Stage 3b: Brand + Product number'] = "{$brand} {$productNumber}";
            }
            if ($barcode) {
                $strategies['Stage 3c: Brand + Barcode'] = "{$brand} {$barcode}";
            }
        }

        // STAGE 4: Brand + keywords (extract model from keywords or NCR ID)
        if ($brand && ! empty($keywords)) {
            $keywordString = implode(' ', $keywords);
            $strategies['Stage 4: Brand + Keywords'] = "{$brand} {$keywordString}";
        }

        // STAGE 5: Brand + model only (extract from NCR ID - first 4 digits often are model)
        if ($brand && $ncrId) {
            $model = substr(str_replace('-', '', $ncrId), 0, 4); // e.g., "7703"
            $strategies['Stage 5: Brand + Model'] = "{$brand} {$model}";
        }

        // STAGE 6: Just brand + first keyword (broadest)
        if ($brand && ! empty($keywords)) {
            $strategies['Stage 6: Brand + Primary keyword'] = "{$brand} {$keywords[0]}";
        }

        return $strategies;
    }

    /**
     * Wrap API calls with rate limiter
     */
    protected function searchActiveListings(string $token, string $query): array
    {
        return $this->rateLimiter->executeWithRateLimit(
            'ebay_browse_search',
            function () use ($token, $query) {
                try {
                    $response = Http::withToken($token)
                        ->timeout(30)
                        ->get("{$this->browseApiUrl}/item_summary/search", [
                            'q' => $query,
                            'limit' => 200,
                            'filter' => 'buyingOptions:{FIXED_PRICE}',
                        ]);

                    if (! $response->successful()) {
                        Log::error('eBay Browse API error', [
                            'status' => $response->status(),
                            'body' => $response->body(),
                        ]);

                        return [];
                    }

                    $data = $response->json();
                    $items = $data['itemSummaries'] ?? [];

                    $results = [];
                    foreach ($items as $item) {
                        $results[] = $this->formatActiveListing($item);
                    }

                    return $results;

                } catch (\Exception $e) {
                    Log::error('eBay Browse API exception', ['error' => $e->getMessage()]);

                    return [];
                }
            },
            3 // max attempts
        );
    }

    protected function formatActiveListing(array $item): array
    {
        // Extract item ID from itemWebUrl
        $itemId = null;
        if (isset($item['itemWebUrl'])) {
            preg_match('/\/itm\/(\d+)/', $item['itemWebUrl'], $matches);
            $itemId = $matches[1] ?? null;
        }

        return [
            'item_id' => $itemId ?? $item['itemId'] ?? null,
            'title' => $item['title'] ?? '',
            'price' => (float) ($item['price']['value'] ?? 0),
            'currency' => $item['price']['currency'] ?? 'USD',
            'condition' => $item['condition'] ?? 'Unknown',
            'shipping' => (float) ($item['shippingOptions'][0]['shippingCost']['value'] ?? 0),
            'link' => $item['itemWebUrl'] ?? null,
            'image' => $item['image']['imageUrl'] ?? ($item['thumbnailImages'][0]['imageUrl'] ?? null),
            'seller' => $item['seller']['username'] ?? null,
            'feedback' => $item['seller']['feedbackScore'] ?? null,
            'buy_it_now' => true,
            'upc' => $this->extractAspect($item, ['UPC', 'upc']),
            'ean' => $this->extractAspect($item, ['EAN', 'ean', 'EAN/UPC']),
            'gtin' => $this->extractAspect($item, ['GTIN', 'gtin']),
        ];
    }

    private function extractAspect(array $item, array $keys): ?string
    {
        foreach ($item['localizedAspects'] ?? [] as $aspect) {
            if (in_array($aspect['name'] ?? '', $keys, true)) {
                $cleaned = preg_replace('/[^0-9]/', '', (string) ($aspect['value'] ?? ''));

                return ! empty($cleaned) ? $cleaned : null;
            }
        }

        return null;
    }
}
