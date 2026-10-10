<?php

namespace App\Services;

use App\Models\Integration\IntegrationCredential;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class EbayFindingService
{
    protected ?string $appId = null;

    protected string $findingApiUrl = 'https://svcs.ebay.com/services/search/FindingService/v1';

    protected EbayRateLimiter $rateLimiter;

    protected EbayConfigService $ebayConfig;

    public function __construct(EbayRateLimiter $rateLimiter, EbayConfigService $ebayConfig)
    {
        $this->rateLimiter = $rateLimiter;
        $this->ebayConfig = $ebayConfig;
        $this->loadCredentials();
    }

    protected function loadCredentials(): void
    {
        // Finding API has no sandbox endpoint; only ever loads production creds.
        $credential = IntegrationCredential::where('platform', 'ebay')
            ->where('environment', 'production')
            ->where('is_active', true)
            ->first();

        if ($credential) {
            $this->appId = $credential->decrypted_client_id;
        }
    }

    public function isAvailable(): bool
    {
        return ! empty($this->appId);
    }

    public function search(array $identifiers): EbaySearchResult
    {
        // eBay Finding API has no sandbox. On non-prod envs, skip to avoid leaking calls to prod.
        if ($this->ebayConfig->isSandbox()) {
            Log::warning('EbayFindingService skipped: no sandbox endpoint exists for Finding API');

            return new EbaySearchResult([], []);
        }

        if (! $this->isAvailable()) {
            Log::warning('eBay Finding API unavailable - no credentials');

            return new EbaySearchResult([], []);
        }

        $keywords = array_filter([
            $identifiers['manufacturer_part_number'] ?? null,
            $identifiers['product_number'] ?? null,
            $identifiers['barcode'] ?? null,
        ]);

        if (empty($keywords)) {
            return new EbaySearchResult([], []);
        }

        $cacheKey = 'ebay_search_'.md5(implode('_', $keywords));

        return Cache::remember($cacheKey, 60 * 6, function () use ($keywords) {
            $soldListings = $this->searchSoldListings($keywords);
            $activeListings = $this->searchActiveListings($keywords);

            return new EbaySearchResult($soldListings, $activeListings);
        });
    }

    protected function searchSoldListings(array $keywords): array
    {
        $results = [];

        foreach ($keywords as $keyword) {
            $items = $this->rateLimiter->executeWithRateLimit(
                'ebay_finding_sold',
                function () use ($keyword) {
                    return $this->callFindingApi('findCompletedItems', [
                        'keywords' => $keyword,
                        'sortOrder' => 'EndTimeSoonest',
                        'itemFilter' => [
                            ['name' => 'SoldItemsOnly', 'value' => 'true'],
                        ],
                        'paginationInput' => [
                            'entriesPerPage' => 100,
                            'pageNumber' => 1,
                        ],
                    ]);
                },
                3
            );

            if ($items && isset($items['searchResult']['item'])) {
                $itemList = $items['searchResult']['item'];
                if (! isset($itemList[0])) {
                    $itemList = [$itemList];
                }

                foreach ($itemList as $item) {
                    $results[] = $this->formatSoldListing($item);
                }
            }
        }

        return $this->deduplicateListings($results);
    }

    protected function searchActiveListings(array $keywords): array
    {
        $results = [];

        foreach ($keywords as $keyword) {
            $items = $this->rateLimiter->executeWithRateLimit(
                'ebay_finding_active',
                function () use ($keyword) {
                    return $this->callFindingApi('findItemsAdvanced', [
                        'keywords' => $keyword,
                        'sortOrder' => 'PricePlusShippingLowest',
                        'itemFilter' => [
                            ['name' => 'ListingType', 'value' => ['FixedPrice', 'AuctionWithBIN']],
                        ],
                        'paginationInput' => [
                            'entriesPerPage' => 100,
                            'pageNumber' => 1,
                        ],
                    ]);
                },
                3
            );

            if ($items && isset($items['searchResult']['item'])) {
                $itemList = $items['searchResult']['item'];
                if (! isset($itemList[0])) {
                    $itemList = [$itemList];
                }

                foreach ($itemList as $item) {
                    $results[] = $this->formatActiveListing($item);
                }
            }
        }

        return $this->deduplicateListings($results);
    }

    protected function callFindingApi(string $operation, array $params): ?array
    {
        try {
            $response = Http::timeout(30)->get($this->findingApiUrl, array_merge([
                'OPERATION-NAME' => $operation,
                'SERVICE-VERSION' => '1.0.0',
                'SECURITY-APPNAME' => $this->appId,
                'RESPONSE-DATA-FORMAT' => 'JSON',
                'REST-PAYLOAD' => '',
            ], $this->flattenParams($params)));

            if ($response->successful()) {
                $data = $response->json();

                return $data[$operation.'Response'][0] ?? null;
            }

            if ($response->status() === 500) {
                $body = $response->json();
                $errorId = $body['errorMessage'][0]['error'][0]['errorId'][0] ?? null;

                if ($errorId === '10001') {
                    Log::warning('eBay rate limit hit', ['operation' => $operation]);
                    throw new \Exception('Rate limit hit');
                }
            }

            Log::error('eBay Finding API error', [
                'status' => $response->status(),
                'body' => $response->body(),
            ]);

            return null;
        } catch (\Exception $e) {
            Log::error('eBay Finding API exception', [
                'operation' => $operation,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    protected function flattenParams(array $params, string $prefix = ''): array
    {
        $result = [];

        foreach ($params as $key => $value) {
            $paramKey = $prefix ? "{$prefix}.{$key}" : $key;

            if (is_array($value)) {
                if (isset($value[0])) {
                    foreach ($value as $index => $item) {
                        if (is_array($item)) {
                            $result = array_merge($result, $this->flattenParams($item, "{$paramKey}({$index})"));
                        } else {
                            $result["{$paramKey}({$index})"] = $item;
                        }
                    }
                } else {
                    $result = array_merge($result, $this->flattenParams($value, $paramKey));
                }
            } else {
                $result[$paramKey] = $item;
            }
        }

        return $result;
    }

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
            'link' => $item['viewItemURL'][0] ?? null,
            'image' => $item['galleryURL'][0] ?? null,
            'seller' => $item['sellerInfo'][0]['sellerUserName'][0] ?? null,
            'feedback' => $item['sellerInfo'][0]['feedbackScore'][0] ?? null,
        ];
    }

    protected function formatActiveListing(array $item): array
    {
        return [
            'item_id' => $item['itemId'][0] ?? null,
            'title' => $item['title'][0] ?? '',
            'price' => (float) ($item['sellingStatus'][0]['currentPrice'][0]['__value__'] ?? 0),
            'currency' => $item['sellingStatus'][0]['currentPrice'][0]['@currencyId'] ?? 'USD',
            'condition' => $item['condition'][0]['conditionDisplayName'][0] ?? 'Unknown',
            'shipping' => (float) ($item['shippingInfo'][0]['shippingServiceCost'][0]['__value__'] ?? 0),
            'link' => $item['viewItemURL'][0] ?? null,
            'image' => $item['galleryURL'][0] ?? null,
            'seller' => $item['sellerInfo'][0]['sellerUserName'][0] ?? null,
            'feedback' => $item['sellerInfo'][0]['feedbackScore'][0] ?? null,
            'buy_it_now' => ($item['listingInfo'][0]['listingType'][0] ?? '') === 'FixedPrice',
        ];
    }

    protected function deduplicateListings(array $listings): array
    {
        $unique = [];

        foreach ($listings as $listing) {
            $itemId = $listing['item_id'] ?? null;
            if ($itemId && ! isset($unique[$itemId])) {
                $unique[$itemId] = $listing;
            }
        }

        return array_values($unique);
    }
}
