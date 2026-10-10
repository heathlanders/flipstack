<?php

namespace App\Services\Inventory;

use App\Models\Integration\IntegrationCredential;
use App\Services\EbayBrowseService;
use App\Services\EbaySoldListingsService;
use Illuminate\Support\Facades\Log;

class PartOutPricingService
{
    /**
     * Price all components in a part-out breakdown using eBay market data.
     */
    public function priceComponents(array $componentBreakdown, int $organizationId): array
    {
        $pricedComponents = [];

        foreach ($componentBreakdown as $component) {
            $name = $component['name'] ?? '';
            $partNumber = $component['part_number'] ?? null;

            $marketValue = $this->getComponentMarketValue($name, $partNumber, $organizationId);

            $pricedComponents[] = array_merge($component, $marketValue);
        }

        return $pricedComponents;
    }

    /**
     * Get market value for a single component from eBay active and sold listings.
     */
    public function getComponentMarketValue(string $name, ?string $partNumber, int $organizationId): array
    {
        $default = [
            'median_sold' => 0.0,
            'median_active' => 0.0,
            'price_range' => 'N/A',
            'num_sold' => 0,
            'num_active' => 0,
            'last_updated' => now(),
        ];

        try {
            $credential = IntegrationCredential::where('organization_id', $organizationId)
                ->where('platform', 'ebay')
                ->where('is_active', true)
                ->first();

            if (! $credential) {
                Log::warning('PartOutPricingService: No active eBay credential found', [
                    'organization_id' => $organizationId,
                ]);

                return $default;
            }

            $searchQuery = $partNumber ?: $name;
            $identifiers = [
                'keywords' => explode(' ', $searchQuery),
                'manufacturer_part_number' => $partNumber,
            ];

            /** @var EbayBrowseService $browseService */
            $browseService = app(EbayBrowseService::class);

            /** @var EbaySoldListingsService $soldService */
            $soldService = app(EbaySoldListingsService::class);

            // Fetch active listings
            $activePrices = [];
            if ($browseService->isAvailable()) {
                $activeResult = $browseService->search($identifiers);

                foreach ($activeResult->activeListings as $listing) {
                    $price = (float) ($listing['price'] ?? 0);
                    if ($price > 0) {
                        $activePrices[] = $price;
                    }
                }
            }

            // Fetch sold listings
            $soldPrices = [];
            if ($soldService->isAvailable()) {
                $soldListings = $soldService->search($identifiers);

                foreach ($soldListings as $listing) {
                    $price = (float) ($listing['price'] ?? 0);
                    if ($price > 0) {
                        $soldPrices[] = $price;
                    }
                }
            }

            $medianSold = $this->calculateMedian($soldPrices);
            $medianActive = $this->calculateMedian($activePrices);

            $allPrices = array_merge($soldPrices, $activePrices);
            $priceRange = ! empty($allPrices)
                ? '$'.number_format(min($allPrices), 2).' - $'.number_format(max($allPrices), 2)
                : 'N/A';

            return [
                'median_sold' => $medianSold,
                'median_active' => $medianActive,
                'price_range' => $priceRange,
                'num_sold' => count($soldPrices),
                'num_active' => count($activePrices),
                'last_updated' => now(),
            ];
        } catch (\Throwable $e) {
            Log::error('PartOutPricingService: Failed to get component market value', [
                'name' => $name,
                'part_number' => $partNumber,
                'error' => $e->getMessage(),
            ]);

            return $default;
        }
    }

    /**
     * Calculate the median value from an array of prices.
     */
    private function calculateMedian(array $prices): float
    {
        if (empty($prices)) {
            return 0.0;
        }

        sort($prices);
        $count = count($prices);
        $middle = (int) floor($count / 2);

        if ($count % 2 === 0) {
            return round(($prices[$middle - 1] + $prices[$middle]) / 2, 2);
        }

        return round($prices[$middle], 2);
    }
}
