<?php

namespace App\Services\Amazon;

use App\Models\Amazon\AmazonSyncLog;

class AmazonPricingService extends AmazonBaseService
{
    public function updatePrice(string $sellerSku, float $price, ?float $minPrice = null, ?float $maxPrice = null): array
    {
        $syncLog = $this->createSyncLog(AmazonSyncLog::OP_UPDATE_PRICE, [
            'amazon_sku' => $sellerSku,
        ]);

        $sellerId = $this->credential->config['amazon_seller_id'] ?? '';

        $priceValue = [[
            'currency' => 'USD',
            'our_price' => [['schedule' => [['value_with_tax' => $price]]]],
            'marketplace_id' => $this->marketplaceId,
        ]];

        if ($minPrice !== null) {
            $priceValue[0]['minimum_seller_allowed_price'] = [['schedule' => [['value_with_tax' => $minPrice]]]];
        }

        if ($maxPrice !== null) {
            $priceValue[0]['maximum_seller_allowed_price'] = [['schedule' => [['value_with_tax' => $maxPrice]]]];
        }

        return $this->patch(
            "/listings/2021-08-01/items/{$sellerId}/{$sellerSku}",
            [
                'productType' => 'PRODUCT',
                'patches' => [
                    [
                        'op' => 'replace',
                        'path' => '/attributes/purchasable_offer',
                        'value' => $priceValue,
                    ],
                ],
                'marketplaceIds' => [$this->marketplaceId],
            ],
            $syncLog
        );
    }

    public function getCompetitivePricing(array $asins): array
    {
        $response = $this->get('/products/pricing/v0/competitivePrice', [
            'MarketplaceId' => $this->marketplaceId,
            'Asins' => implode(',', $asins),
            'ItemType' => 'Asin',
        ]);

        return $response['payload'] ?? [];
    }
}
