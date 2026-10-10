<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class EbayProductScraper
{
    public function getProductDetails(string $epid): ?array
    {
        try {
            $url = "https://www.ebay.com/p/{$epid}";

            $response = Http::timeout(10)
                ->withHeaders([
                    'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36',
                ])
                ->get($url);

            if (! $response->successful()) {
                return null;
            }

            $html = $response->body();

            $itemSpecifics = [];

            // Extract the item specifics section
            if (preg_match('/<div[^>]*id="viTabs_0_is"[^>]*>(.*?)<\/div>.*?<div[^>]*class="vim x-breadcrumb/s', $html, $sectionMatch)) {
                $section = $sectionMatch[1];

                // Match each dl.ux-labels-values block
                preg_match_all('/<dl[^>]*class="[^"]*ux-labels-values[^"]*"[^>]*>(.*?)<\/dl>/s', $section, $dlMatches);

                foreach ($dlMatches[1] as $dlContent) {
                    // Extract label from dt
                    if (preg_match('/<dt[^>]*>.*?<span[^>]*class="[^"]*ux-textspans[^"]*"[^>]*>([^<]+)<\/span>/s', $dlContent, $labelMatch)) {
                        $label = trim($labelMatch[1]);

                        // Extract value from dd
                        if (preg_match('/<dd[^>]*>.*?<span[^>]*class="[^"]*ux-textspans[^"]*"[^>]*>([^<]+)<\/span>/s', $dlContent, $valueMatch)) {
                            $value = trim($valueMatch[1]);

                            // Skip unwanted fields
                            if (! in_array($label, ['Condition', 'eBay Product ID (ePID)', 'GTIN', 'UPC']) &&
                                strlen($value) > 0 && strlen($value) < 500) {
                                $itemSpecifics[$label] = $value;
                            }
                        }
                    }
                }
            }

            // Extract from About this product section too
            if (preg_match('/<div[^>]*class="vim x-product-details"[^>]*>(.*?)<\/div>.*?<div[^>]*class="vim d-item-description/s', $html, $productMatch)) {
                $section = $productMatch[1];

                preg_match_all('/<div[^>]*class="[^"]*ux-labels-values[^"]*"[^>]*>.*?<span[^>]*class="[^"]*ux-textspans[^"]*"[^>]*>([^<]+)<\/span>.*?<span[^>]*class="[^"]*ux-textspans[^"]*"[^>]*>([^<]+)<\/span>/s', $section, $productMatches, PREG_SET_ORDER);

                foreach ($productMatches as $match) {
                    $label = trim($match[1]);
                    $value = trim($match[2]);

                    if (! isset($itemSpecifics[$label]) && ! in_array($label, ['eBay Product ID (ePID)', 'GTIN', 'UPC'])) {
                        $itemSpecifics[$label] = $value;
                    }
                }
            }

            // Get brand, model, mpn
            $brand = $itemSpecifics['Brand'] ?? null;
            $model = $itemSpecifics['Model'] ?? null;
            $mpn = $itemSpecifics['MPN'] ?? null;

            return [
                'brand' => $brand,
                'model' => $model,
                'mpn' => $mpn,
                'item_specifics' => $itemSpecifics,
                'description' => null,
            ];

        } catch (\Exception $e) {
            Log::warning('Failed to scrape eBay product page', [
                'epid' => $epid,
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }
}
