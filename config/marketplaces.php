<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Marketplace Adapters
    |--------------------------------------------------------------------------
    |
    | Each marketplace is registered with its adapter class, credential type,
    | and listing model. The MarketplaceRegistry uses this config to resolve
    | adapters for an organization's connected marketplaces.
    |
    */

    'ebay' => [
        'adapter' => \App\Services\Ebay\EbayMarketplaceAdapter::class,
        'credential_type' => 'ebay',
        'listing_model' => \App\Models\Ebay\EbayListing::class,
        'label' => 'eBay',
        'supports_webhooks' => true,
        'active_status' => 'published',
    ],

    'shopify' => [
        'adapter' => \App\Services\Shopify\ShopifyMarketplaceAdapter::class,
        'credential_type' => 'shopify',
        'listing_model' => \App\Models\Shopify\ShopifyListing::class,
        'label' => 'Shopify',
        'supports_webhooks' => true,
        'active_status' => 'active',
    ],

    'amazon' => [
        'adapter' => \App\Services\Amazon\AmazonMarketplaceAdapter::class,
        'credential_type' => 'amazon',
        'listing_model' => \App\Models\Amazon\AmazonListing::class,
        'label' => 'Amazon',
        'supports_webhooks' => true,
        'active_status' => 'active',
    ],

];
