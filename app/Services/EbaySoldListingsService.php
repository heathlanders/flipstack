<?php

namespace App\Services;

use App\Models\Integration\IntegrationCredential;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class EbaySoldListingsService
{
    protected ?string $appId = null;

    protected string $findingApiUrl = 'https://svcs.ebay.com/services/search/FindingService/v1';

    protected EbayRateLimiter $rateLimiter;

    public function __construct(EbayRateLimiter $rateLimiter)
    {
        $this->rateLimiter = $rateLimiter;
        $this->loadCredentials();
    }

    protected function loadCredentials(): void
    {
        try {
            $credential = IntegrationCredential::where('platform', 'ebay')
                ->where('environment', 'production')
                ->where('is_active', true)
                ->first();

            if ($credential) {
                $this->appId = $credential->decrypted_client_id;
            }
        } catch (\Throwable $e) {
            Log::warning('Failed to load eBay Finding credentials', ['error' => $e->getMessage()]);
        }
    }

    public function isAvailable(): bool
    {
        return ! empty($this->appId);
    }

    /**
     * Search for sold listings using SIMPLE smart query (same strategy as Browse API)
     */
    public function search(array $identifiers): array
    {
        if (! $this->isAvailable()) {
            Log::warning('eBay Finding API unavailable - no credentials');

            return [];
        }

        // Build ONE smart query (same strategy as Browse API)
        $query = $this->buildSmartQuery($identifiers);

        Log::info('eBay sold listings: single smart search', [
            'query' => $query,
        ]);

        // Cache for 6 hours
        $cacheKey = 'ebay_sold_smart_'.md5($query);

        $results = Cache::remember($cacheKey, 60 * 6, function () use ($query) {
            return $this->searchSoldListings($query);
        });

        Log::info('eBay sold search complete', [
            'total_sold_listings' => count($results),
            'api_calls' => 1,
        ]);

        return $results;
    }

    /**
     * Build ONE smart query (matches Browse API strategy)
     */
    protected function buildSmartQuery(array $identifiers): string
    {
        $brand = $identifiers['brand'] ?? 'NCR';
        $ncrId = $identifiers['manufacturer_part_number'] ?? null;
        $keywords = $identifiers['keywords'] ?? [];

        // Extract model from NCR ID (e.g., "7703" from "7703-1000-8801")
        $model = null;
        if ($ncrId) {
            $model = substr(str_replace('-', '', $ncrId), 0, 4);
        }

        // Build query: Brand + Model + Primary Keywords
        $parts = [$brand];

        if ($model) {
            $parts[] = $model;
        }

        // Add top 2 keywords only
        if (! empty($keywords)) {
            $parts[] = $keywords[0];
            if (isset($keywords[1])) {
                $parts[] = $keywords[1];
            }
        }

        return implode(' ', $parts);
    }

    /**
     * Search for sold listings via Finding API
     */
    protected function searchSoldListings(string $query): array
    {
        return $this->rateLimiter->executeWithRateLimit(
            'ebay_sold_listings',
            function () use ($query) {
                try {
                    $response = Http::timeout(30)->get($this->findingApiUrl, [
                        'OPERATION-NAME' => 'findCompletedItems',
                        'SERVICE-VERSION' => '1.0.0',
                        'SECURITY-APPNAME' => $this->appId,
                        'RESPONSE-DATA-FORMAT' => 'JSON',
                        'keywords' => $query,
                        'sortOrder' => 'EndTimeSoonest',
                        'itemFilter(0).name' => 'SoldItemsOnly',
                        'itemFilter(0).value' => 'true',
                        'paginationInput.entriesPerPage' => 100,  // Max results
                    ]);

                    if (! $response->successful()) {
                        if ($response->status() === 500) {
                            $body = $response->json();
                            $errorId = $body['errorMessage'][0]['error'][0]['errorId'][0] ?? null;

                            if ($errorId === '10001') {
                                Log::warning('eBay Finding API rate limit hit for sold listings');
                                throw new \Exception('Rate limit hit');
                            }
                        }

                        Log::error('eBay sold listings API error', [
                            'status' => $response->status(),
                            'body' => $response->body(),
                        ]);

                        return [];
                    }

                    $data = $response->json();
                    $items = $data['findCompletedItemsResponse'][0]['searchResult'][0]['item'] ?? [];

                    if (! empty($items) && ! isset($items[0])) {
                        $items = [$items];
                    }

                    $results = [];
                    foreach ($items as $item) {
                        $results[] = $this->formatSoldListing($item);
                    }

                    return $results;

                } catch (\Exception $e) {
                    Log::error('eBay sold listings exception', ['error' => $e->getMessage()]);
                    throw $e; // Let rate limiter handle retry
                }
            },
            3 // max attempts with backoff
        );
    }

    /**
     * Format sold listing data
     */
    protected function formatSoldListing(array $item): array
    {
        return [
            'item_id' => $item['itemId'][0] ?? null,
            'title' => $item['title'][0] ?? '',
            'price' => (float) ($item['sellingStatus'][0]['currentPrice'][0]['__value__'] ?? 0),
            'currency' => $item['sellingStatus'][0]['currentPrice'][0]['@currencyId'] ?? 'USD',
            'condition' => $item['condition'][0]['conditionDisplayName'][0] ?? 'Unknown',
            'sold_date' => $item['listingInfo'][0]['endTime'][0] ?? null,
            'shipping' => (float) ($item['shippingInfo'][0]['shippingServiceCost'][0]['__value__'] ?? 0),
            'total_price' => (float) ($item['sellingStatus'][0]['currentPrice'][0]['__value__'] ?? 0) +
                            (float) ($item['shippingInfo'][0]['shippingServiceCost'][0]['__value__'] ?? 0),
            'link' => $item['viewItemURL'][0] ?? null,
            'image' => $item['galleryURL'][0] ?? null,
            'seller' => $item['sellerInfo'][0]['sellerUserName'][0] ?? null,
            'feedback' => $item['sellerInfo'][0]['feedbackScore'][0] ?? null,
        ];
    }
}
