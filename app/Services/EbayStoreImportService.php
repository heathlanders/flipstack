<?php

namespace App\Services;

use App\Enums\Supplier\SupplierStatus;
use App\Models\Catalog\Brand;
use App\Models\Catalog\Category;
use App\Models\Catalog\Product;
use App\Models\Catalog\ProductIdentifier;
use App\Models\Catalog\ProductImage;
use App\Models\Ebay\EbayListing;
use App\Models\Ebay\EbaySyncLog;
use App\Models\Integration\IntegrationCredential;
use App\Models\Integration\OAuthToken;
use App\Models\Inventory\InventoryLevel;
use App\Models\Inventory\InventoryLocation;
use App\Models\Inventory\Supplier;
use App\Models\Inventory\SupplierProduct;
use App\Services\Inventory\WarehouseAutoBuilder;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class EbayStoreImportService
{
    protected IntegrationCredential $credential;

    protected ?OAuthToken $token = null;

    protected string $tradingApiUrl = 'https://api.ebay.com/ws/api.dll';

    protected int $compatibilityLevel = 967;

    protected int $siteId = 0;

    protected bool $debugMode = false;

    protected array $debugContext = [];

    protected array $skuParsers = [];

    protected ?SkuParserService $skuParser = null;

    protected EbaySkuLocationParser $locationParser;

    protected WarehouseAutoBuilder $warehouseBuilder;

    /**
     * Owner codes that recur in this org's data — excluded from being tagged as
     * consignors by the structured parser. Pulled from credential config.
     *
     * @var array<int,string>
     */
    protected array $knownOwners = [];

    /**
     * Owner codes representing the org's own house stock (e.g. "HAWK").
     * Products with these owners do NOT get a Supplier row or
     * SupplierProduct link — they are flagged with attributes.is_house_stock.
     *
     * @var array<int,string>
     */
    protected array $housePrefixes = [];

    /**
     * Staff initials (e.g. "ABC", "DEF", "GHI") that route to
     * `lister_initials` rather than `consignor`. Listers never become
     * Suppliers. Pulled from credential config.
     *
     * @var array<int,string>
     */
    protected array $knownListers = [];

    public function __construct(IntegrationCredential $credential)
    {
        $this->credential = $credential;

        if ($credential->environment === 'sandbox') {
            $this->tradingApiUrl = 'https://api.sandbox.ebay.com/ws/api.dll';
        }

        $config = $credential->config ?? [];
        $this->skuParsers = SkuParserService::normalizeParsers($config);
        if (! empty($this->skuParsers)) {
            $this->skuParser = new SkuParserService;
        }

        $this->knownOwners = array_values(array_filter(
            array_map('strtoupper', (array) ($config['known_owners'] ?? [])),
            'is_string'
        ));
        if (empty($this->knownOwners)) {
            // Seed a default list of recurring owner codes so non-configured
            // orgs still get the "owners are never consignors" benefit.
            // Orgs override via IntegrationCredential.config.known_owners.
            $this->knownOwners = [
                'HAWK', 'PINE', 'OAKS', 'FERN', 'DUNE', 'REEF', 'REEF6',
                'MESA', 'COVE', 'KELP', 'SAGE', 'MOSS', 'BIRCH', 'BIRCH2',
                'CEDR', 'ELMS', 'FOX', 'TIDE', 'SILT', 'DELT', 'CLAY', 'LOAM',
            ];
        }
        $this->housePrefixes = array_values(array_filter(
            array_map('strtoupper', (array) ($config['house_prefixes'] ?? [])),
            'is_string'
        ));

        $this->knownListers = array_values(array_filter(
            array_map('strtoupper', (array) ($config['known_listers'] ?? [])),
            'is_string'
        ));

        $this->locationParser = new EbaySkuLocationParser($this->knownOwners, $this->knownListers);
        $this->warehouseBuilder = new WarehouseAutoBuilder;
    }

    public function enableDebug(): void
    {
        $this->debugMode = true;
    }

    public function getDebugContext(): array
    {
        return $this->debugContext;
    }

    public function clearDebugContext(): void
    {
        $this->debugContext = [];
    }

    protected function addDebugContext(string $event, array $data = []): void
    {
        if (! $this->debugMode) {
            return;
        }

        $this->debugContext[] = array_merge([
            'event' => 'service:'.$event,
            'timestamp' => now()->toISOString(),
        ], $data);
    }

    protected function getToken(): OAuthToken
    {
        if ($this->token) {
            return $this->token;
        }

        $this->token = $this->credential->getActiveEbayToken();

        if (! $this->token) {
            throw new \Exception('No active eBay OAuth token available.');
        }

        if ($this->token->needsRefresh()) {
            $oauthService = new EbayOAuthService($this->credential);
            $this->token = $oauthService->refreshToken($this->token);
        }

        return $this->token;
    }

    public function fetchActiveListings(int $page = 1, int $perPage = 200): array
    {
        $xml = $this->buildGetSellerListXml($page, $perPage);

        $this->addDebugContext('api_request', [
            'url' => $this->tradingApiUrl,
            'call' => 'GetSellerList',
            'page' => $page,
            'per_page' => $perPage,
            'request_size' => strlen($xml),
        ]);

        $response = Http::withHeaders([
            'X-EBAY-API-IAF-TOKEN' => $this->getToken()->decrypted_access_token,
            'X-EBAY-API-SITEID' => (string) $this->siteId,
            'X-EBAY-API-COMPATIBILITY-LEVEL' => (string) $this->compatibilityLevel,
            'X-EBAY-API-CALL-NAME' => 'GetSellerList',
            'Content-Type' => 'text/xml',
        ])->withBody($xml, 'text/xml')
            ->timeout(60)
            ->post($this->tradingApiUrl);

        $this->addDebugContext('api_response', [
            'status' => $response->status(),
            'response_size' => strlen($response->body()),
            'response_preview' => substr($response->body(), 0, 300),
        ]);

        if (! $response->successful()) {
            throw new \Exception('eBay Trading API request failed with status: '.$response->status());
        }

        return $this->parseXmlResponse($response->body());
    }

    public function getTotalListingCount(): int
    {
        $result = $this->fetchActiveListings(1, 1);

        return $result['total_items'] ?? 0;
    }

    public function buildGetSellerListXml(int $page, int $perPage): string
    {
        $now = now();
        // Use EndTime filters instead of StartTime - more reliable for finding active listings
        // especially those created via Inventory API which may have different start times
        $endTimeFrom = $now->toIso8601String();
        $endTimeTo = $now->copy()->addDays(120)->toIso8601String();

        return '<?xml version="1.0" encoding="utf-8"?>
<GetSellerListRequest xmlns="urn:ebay:apis:eBLBaseComponents">
    <RequesterCredentials>
        <eBayAuthToken>'.htmlspecialchars($this->getToken()->decrypted_access_token).'</eBayAuthToken>
    </RequesterCredentials>
    <EndTimeFrom>'.$endTimeFrom.'</EndTimeFrom>
    <EndTimeTo>'.$endTimeTo.'</EndTimeTo>
    <GranularityLevel>Fine</GranularityLevel>
    <Pagination>
        <EntriesPerPage>'.$perPage.'</EntriesPerPage>
        <PageNumber>'.$page.'</PageNumber>
    </Pagination>
    <OutputSelector>ItemID</OutputSelector>
    <OutputSelector>Title</OutputSelector>
    <OutputSelector>SKU</OutputSelector>
    <OutputSelector>Description</OutputSelector>
    <OutputSelector>PrimaryCategory</OutputSelector>
    <OutputSelector>SellingStatus</OutputSelector>
    <OutputSelector>PictureDetails</OutputSelector>
    <OutputSelector>Quantity</OutputSelector>
    <OutputSelector>StartPrice</OutputSelector>
    <OutputSelector>ConditionID</OutputSelector>
    <OutputSelector>ConditionDisplayName</OutputSelector>
    <OutputSelector>ItemSpecifics</OutputSelector>
    <OutputSelector>ListingType</OutputSelector>
    <OutputSelector>ViewItemURL</OutputSelector>
    <OutputSelector>StartTime</OutputSelector>
    <OutputSelector>EndTime</OutputSelector>
    <OutputSelector>HitCount</OutputSelector>
    <OutputSelector>WatchCount</OutputSelector>
    <OutputSelector>ShippingDetails</OutputSelector>
    <OutputSelector>ReturnPolicy</OutputSelector>
    <OutputSelector>BestOfferDetails</OutputSelector>
    <OutputSelector>SellerProfiles</OutputSelector>
    <OutputSelector>StoreCategoryID</OutputSelector>
    <OutputSelector>StoreCategoryName</OutputSelector>
    <OutputSelector>SecondaryCategory</OutputSelector>
    <OutputSelector>ListingDuration</OutputSelector>
    <OutputSelector>ProductListingDetails</OutputSelector>
</GetSellerListRequest>';
    }

    public function parseXmlResponse(string $xml): array
    {
        libxml_use_internal_errors(true);
        $xmlDoc = simplexml_load_string($xml);

        if ($xmlDoc === false) {
            $errors = libxml_get_errors();
            $parseErrors = array_map(fn ($e) => trim($e->message), $errors);
            libxml_clear_errors();
            $this->addDebugContext('xml_parse_error', [
                'errors' => $parseErrors,
                'xml_preview' => substr($xml, 0, 500),
            ]);
            throw new \Exception('Failed to parse eBay API XML response');
        }

        $xmlDoc->registerXPathNamespace('e', 'urn:ebay:apis:eBLBaseComponents');

        $ack = (string) $xmlDoc->Ack;
        if ($ack === 'Failure') {
            $errorCode = (string) ($xmlDoc->Errors->ErrorCode ?? 'unknown');
            $errorMsg = (string) ($xmlDoc->Errors->LongMessage ?? $xmlDoc->Errors->ShortMessage ?? 'Unknown error');
            $this->addDebugContext('ebay_api_failure', [
                'error_code' => $errorCode,
                'error_message' => $errorMsg,
                'ack' => $ack,
            ]);
            throw new \Exception('eBay API error: '.$errorMsg);
        }

        $totalItems = (int) ($xmlDoc->PaginationResult->TotalNumberOfEntries ?? 0);
        $totalPages = (int) ($xmlDoc->PaginationResult->TotalNumberOfPages ?? 0);
        $currentPage = (int) ($xmlDoc->PageNumber ?? 1);

        $items = [];
        if (isset($xmlDoc->ItemArray->Item)) {
            foreach ($xmlDoc->ItemArray->Item as $item) {
                $items[] = $this->parseItem($item);
            }
        }

        // Use count of items as fallback when API returns 0 but items exist
        // (eBay Inventory API listings may not populate TotalNumberOfEntries correctly)
        if ($totalItems === 0 && count($items) > 0) {
            $totalItems = count($items);
        }

        return [
            'items' => $items,
            'total_items' => $totalItems,
            'total_pages' => $totalPages,
            'current_page' => $currentPage,
        ];
    }

    protected function parseItem(\SimpleXMLElement $item): array
    {
        $pictures = [];
        if (isset($item->PictureDetails->PictureURL)) {
            foreach ($item->PictureDetails->PictureURL as $url) {
                $pictures[] = (string) $url;
            }
        }

        $itemSpecifics = [];
        if (isset($item->ItemSpecifics->NameValueList)) {
            foreach ($item->ItemSpecifics->NameValueList as $spec) {
                $name = (string) $spec->Name;
                $value = (string) $spec->Value;
                $itemSpecifics[$name] = $value;
            }
        }

        $quantity = (int) ($item->Quantity ?? 0);
        $quantitySold = (int) ($item->SellingStatus->QuantitySold ?? 0);

        // Shipping details
        $shippingDetails = null;
        if (isset($item->ShippingDetails)) {
            $sd = $item->ShippingDetails;
            $shippingDetails = [
                'shipping_type' => (string) ($sd->ShippingType ?? ''),
                'handling_time' => (int) ($item->DispatchTimeMax ?? $sd->DispatchTimeMax ?? 0),
                'services' => [],
            ];
            if (isset($sd->ShippingServiceOptions)) {
                foreach ($sd->ShippingServiceOptions as $svc) {
                    $shippingDetails['services'][] = [
                        'service' => (string) ($svc->ShippingService ?? ''),
                        'cost' => (float) ($svc->ShippingServiceCost ?? 0),
                        'additional_cost' => (float) ($svc->ShippingServiceAdditionalCost ?? 0),
                        'free_shipping' => ((string) ($svc->FreeShipping ?? 'false')) === 'true',
                    ];
                }
            }
        }

        // Return policy
        $returnPolicy = null;
        if (isset($item->ReturnPolicy)) {
            $rp = $item->ReturnPolicy;
            $returnPolicy = [
                'returns_accepted' => (string) ($rp->ReturnsAcceptedOption ?? ''),
                'returns_within' => (string) ($rp->ReturnsWithinOption ?? ''),
                'refund_option' => (string) ($rp->RefundOption ?? ''),
                'shipping_cost_paid_by' => (string) ($rp->ShippingCostPaidByOption ?? ''),
                'description' => (string) ($rp->Description ?? ''),
            ];
        }

        // Best offer details
        $bestOffer = null;
        if (isset($item->BestOfferDetails)) {
            $bo = $item->BestOfferDetails;
            $bestOffer = [
                'enabled' => ((string) ($bo->BestOfferEnabled ?? 'false')) === 'true',
                'auto_accept_price' => isset($item->ListingDetails->BestOfferAutoAcceptPrice) ? (float) $item->ListingDetails->BestOfferAutoAcceptPrice : null,
                'minimum_accept_price' => isset($item->ListingDetails->MinimumBestOfferPrice) ? (float) $item->ListingDetails->MinimumBestOfferPrice : null,
            ];
        }

        // Seller profiles
        $sellerProfiles = null;
        if (isset($item->SellerProfiles)) {
            $sp = $item->SellerProfiles;
            $sellerProfiles = [
                'shipping_profile_id' => (string) ($sp->SellerShippingProfile->ShippingProfileID ?? ''),
                'shipping_profile_name' => (string) ($sp->SellerShippingProfile->ShippingProfileName ?? ''),
                'return_profile_id' => (string) ($sp->SellerReturnProfile->ReturnProfileID ?? ''),
                'return_profile_name' => (string) ($sp->SellerReturnProfile->ReturnProfileName ?? ''),
                'payment_profile_id' => (string) ($sp->SellerPaymentProfile->PaymentProfileID ?? ''),
                'payment_profile_name' => (string) ($sp->SellerPaymentProfile->PaymentProfileName ?? ''),
            ];
        }

        // Secondary category
        $secondaryCategoryId = null;
        $secondaryCategoryName = null;
        if (isset($item->SecondaryCategory)) {
            $secondaryCategoryId = (string) ($item->SecondaryCategory->CategoryID ?? '');
            $secondaryCategoryName = (string) ($item->SecondaryCategory->CategoryName ?? '');
        }

        // EPID from ProductListingDetails
        $epid = null;
        if (isset($item->ProductListingDetails->ProductID)) {
            $epid = (string) $item->ProductListingDetails->ProductID;
        }

        return [
            'item_id' => (string) $item->ItemID,
            'title' => (string) $item->Title,
            'sku' => (string) ($item->SKU ?? ''),
            'description' => (string) ($item->Description ?? ''),
            'start_price' => (float) ($item->StartPrice ?? 0),
            'currency' => (string) ($item->StartPrice['currencyID'] ?? 'USD'),
            'quantity' => $quantity,
            'quantity_sold' => $quantitySold,
            'available_quantity' => $quantity - $quantitySold,
            'condition_id' => (string) ($item->ConditionID ?? ''),
            'condition_display_name' => (string) ($item->ConditionDisplayName ?? ''),
            'category_id' => (string) ($item->PrimaryCategory->CategoryID ?? ''),
            'category_name' => (string) ($item->PrimaryCategory->CategoryName ?? ''),
            'listing_type' => (string) ($item->ListingType ?? ''),
            'view_item_url' => (string) ($item->ViewItemURL ?? ''),
            'pictures' => $pictures,
            'item_specifics' => $itemSpecifics,
            'start_time' => (string) ($item->StartTime ?? ''),
            'end_time' => (string) ($item->EndTime ?? ''),
            'hit_count' => (int) ($item->HitCount ?? 0),
            'watch_count' => (int) ($item->WatchCount ?? 0),
            'shipping_details' => $shippingDetails,
            'return_policy' => $returnPolicy,
            'best_offer' => $bestOffer,
            'seller_profiles' => $sellerProfiles,
            'store_category_id' => (string) ($item->StoreCategoryID ?? ''),
            'store_category_name' => (string) ($item->StoreCategoryName ?? ''),
            'secondary_category_id' => $secondaryCategoryId,
            'secondary_category_name' => $secondaryCategoryName,
            'listing_duration' => (string) ($item->ListingDuration ?? ''),
            'epid' => $epid,
        ];
    }

    public function mapToProductData(array $item): array
    {
        $rawSku = ! empty($item['sku']) ? $item['sku'] : 'EBAY-'.$item['item_id'];
        $identifiers = $this->extractIdentifiers($item['item_specifics'] ?? []);
        $pictures = $item['pictures'] ?? [];
        $itemSpecifics = $item['item_specifics'] ?? [];
        $weightAndDimensions = $this->extractWeightAndDimensions($itemSpecifics);
        $itemSpecificAttributes = $this->extractItemSpecificAttributes($itemSpecifics);

        $sku = $rawSku;
        $parsedOwner = null;
        $parsedLocation = null;

        if ($this->skuParser && ! empty($this->skuParsers)) {
            $parsed = $this->skuParser->parseMulti($rawSku, $this->skuParsers);
            if ($parsed !== null) {
                if (! empty($parsed['sku'])) {
                    $sku = $parsed['sku'];
                }
                $parsedOwner = $parsed['owner'] ?? null;
                $parsedLocation = $parsed['location'] ?? null;
            }
        }

        // Structured location parsing (keyword-based, works on any SKU format)
        $parsedLocationData = $this->locationParser->parse($rawSku);

        // Use structured parser's owner if the config-based parser didn't find one
        if ($parsedOwner === null && ! empty($parsedLocationData['owner'])) {
            $parsedOwner = $parsedLocationData['owner'];
        }

        // Use structured parser's product_sku for ARC items if not already extracted
        if (! empty($parsedLocationData['product_sku']) && $sku === $rawSku) {
            $sku = $parsedLocationData['product_sku'];
        }

        $attributes = array_merge([
            'ebay_category' => $item['category_name'] ?? null,
            'ebay_category_id' => $item['category_id'] ?? null,
            'ebay_item_id' => $item['item_id'],
            'ebay_condition_id' => $item['condition_id'] ?? null,
            'listing_type' => $item['listing_type'] ?? null,
            'shipping_details' => $item['shipping_details'] ?? null,
            'return_policy' => $item['return_policy'] ?? null,
            'best_offer' => $item['best_offer'] ?? null,
            'store_category_id' => ! empty($item['store_category_id']) ? $item['store_category_id'] : null,
            'store_category_name' => ! empty($item['store_category_name']) ? $item['store_category_name'] : null,
            'ebay_secondary_category_id' => $item['secondary_category_id'] ?? null,
            'ebay_secondary_category_name' => $item['secondary_category_name'] ?? null,
            'listing_duration' => ! empty($item['listing_duration']) ? $item['listing_duration'] : null,
            'ebay_epid' => $item['epid'] ?? null,
            'watch_count' => $item['watch_count'] ?? 0,
        ], $itemSpecificAttributes);

        // Remove null values to keep attributes clean
        $attributes = array_filter($attributes, fn ($v) => $v !== null);

        return [
            'name' => $item['title'],
            'description' => $item['description'] ?: null,
            'short_description' => $this->generateShortDescription($item['description'] ?? ''),
            'sku' => $sku,
            'raw_sku' => $rawSku,
            'parsed_owner' => $parsedOwner,
            'parsed_location' => $parsedLocation,
            'parsed_location_data' => $parsedLocationData,
            'selling_price' => $item['start_price'],
            'currency' => $item['currency'] ?? 'USD',
            'stock_quantity' => $item['available_quantity'] ?? 0,
            'available_quantity' => $item['available_quantity'] ?? 0,
            'quantity' => $item['quantity'] ?? 0,
            'reserved_quantity' => 0,
            'condition' => $this->mapCondition($item['condition_id'] ?? ''),
            'condition_description' => $item['condition_display_name'] ?: null,
            'upc' => $identifiers['upc'] ?? null,
            'ean' => $identifiers['ean'] ?? null,
            'mpn' => $identifiers['mpn'] ?? null,
            'weight' => $weightAndDimensions['weight'],
            'length' => $weightAndDimensions['length'],
            'width' => $weightAndDimensions['width'],
            'height' => $weightAndDimensions['height'],
            'total_sales' => $item['quantity_sold'] ?? 0,
            'view_count' => $item['hit_count'] ?? 0,
            'search_keywords' => $this->generateSearchKeywords($item, $identifiers),
            'featured_image' => $pictures[0] ?? null,
            'gallery_images' => $pictures ?: null,
            'attributes' => $attributes,
            'brand_name' => $identifiers['brand'] ?? null,
            'identifiers' => $identifiers,
            'category_name' => $item['category_name'] ?? null,
            'category_id_ebay' => $item['category_id'] ?? null,
            'start_time' => $item['start_time'] ?? null,
            'end_time' => $item['end_time'] ?? null,
            'seller_profiles' => $item['seller_profiles'] ?? null,
            'status' => 'draft',
            'type' => 'simple',
            'track_inventory' => true,
            'manage_stock' => true,
        ];
    }

    public function mapCondition(string $conditionId): string
    {
        $mapping = [
            '1000' => 'NEW',
            '1500' => 'NEW_OTHER',
            '1750' => 'NEW_WITH_DEFECTS',
            '2000' => 'CERTIFIED_REFURBISHED',
            '2500' => 'MANUFACTURER_REFURBISHED',
            '2750' => 'USED_EXCELLENT',
            '3000' => 'USED_VERY_GOOD',
            '4000' => 'USED_GOOD',
            '5000' => 'USED_ACCEPTABLE',
            '6000' => 'USED_ACCEPTABLE',
            '7000' => 'FOR_PARTS_OR_NOT_WORKING',
        ];

        return $mapping[$conditionId] ?? 'USED_GOOD';
    }

    public function extractIdentifiers(array $itemSpecifics): array
    {
        $identifiers = [];
        $skipValues = ['Does not apply', 'N/A', 'NA', 'does not apply', 'n/a'];

        // UPC: check UPC and GTIN keys
        foreach (['UPC', 'GTIN'] as $key) {
            if (! empty($itemSpecifics[$key]) && ! in_array($itemSpecifics[$key], $skipValues)) {
                $identifiers['upc'] = $itemSpecifics[$key];
                break;
            }
        }

        // EAN: check EAN key separately
        if (! empty($itemSpecifics['EAN']) && ! in_array($itemSpecifics['EAN'], $skipValues)) {
            $identifiers['ean'] = $itemSpecifics['EAN'];
            // If no UPC was found, don't fall back EAN into UPC slot
        }

        if (! empty($itemSpecifics['MPN']) && ! in_array($itemSpecifics['MPN'], $skipValues)) {
            $identifiers['mpn'] = $itemSpecifics['MPN'];
        }

        if (! empty($itemSpecifics['Brand']) && $itemSpecifics['Brand'] !== 'Unbranded' && ! in_array($itemSpecifics['Brand'], $skipValues)) {
            $identifiers['brand'] = $itemSpecifics['Brand'];
        }

        return $identifiers;
    }

    public function extractItemSpecificAttributes(array $itemSpecifics): array
    {
        $excludeKeys = ['Brand', 'MPN', 'UPC', 'GTIN', 'EAN'];
        $skipValues = ['Does not apply', 'N/A', 'NA', 'does not apply', 'n/a'];
        $attributes = [];

        foreach ($itemSpecifics as $name => $value) {
            if (in_array($name, $excludeKeys)) {
                continue;
            }
            if (in_array($value, $skipValues)) {
                continue;
            }
            $attributes[$name] = $value;
        }

        return $attributes;
    }

    public function extractWeightAndDimensions(array $itemSpecifics): array
    {
        $result = ['weight' => null, 'length' => null, 'width' => null, 'height' => null];

        $weightKeys = ['Item Weight', 'Weight'];
        $lengthKeys = ['Item Length', 'Length'];
        $widthKeys = ['Item Width', 'Width'];
        $heightKeys = ['Item Height', 'Height'];

        $mapping = [
            'weight' => $weightKeys,
            'length' => $lengthKeys,
            'width' => $widthKeys,
            'height' => $heightKeys,
        ];

        foreach ($mapping as $field => $keys) {
            foreach ($keys as $key) {
                if (! empty($itemSpecifics[$key]) && $itemSpecifics[$key] !== 'Does not apply') {
                    $parsed = $this->parseNumericValue($itemSpecifics[$key]);
                    if ($parsed !== null) {
                        $result[$field] = $parsed;
                    } else {
                        Log::warning('eBay import: could not parse measurement', [
                            'field' => $field,
                            'raw_value' => $itemSpecifics[$key],
                        ]);
                    }
                    break;
                }
            }
        }

        return $result;
    }

    protected function parseNumericValue(string $value): ?float
    {
        // Extract first numeric value from strings like "2.5 lbs", "10 inches", "3.2"
        if (preg_match('/(\d+\.?\d*)/', $value, $matches)) {
            return (float) $matches[1];
        }

        return null;
    }

    public function generateSearchKeywords(array $item, array $identifiers): string
    {
        $parts = [];

        // Title words
        if (! empty($item['title'])) {
            $parts[] = $item['title'];
        }

        // Brand
        if (! empty($identifiers['brand'])) {
            $parts[] = $identifiers['brand'];
        }

        // Category
        if (! empty($item['category_name'])) {
            $parts[] = $item['category_name'];
        }

        $combined = mb_strtolower(implode(' ', $parts));
        // Split into words, deduplicate
        $words = array_unique(preg_split('/\s+/', $combined));
        // Remove very short words
        $words = array_filter($words, fn ($w) => mb_strlen($w) > 1);

        return implode(' ', $words);
    }

    public function generateShortDescription(string $description): ?string
    {
        if (empty($description)) {
            return null;
        }

        $text = html_entity_decode(strip_tags($description), ENT_QUOTES, 'UTF-8');
        $text = preg_replace('/\s+/', ' ', trim($text));

        if (empty($text)) {
            return null;
        }

        if (mb_strlen($text) <= 200) {
            return $text;
        }

        // Cut at word boundary
        $truncated = mb_substr($text, 0, 200);
        $lastSpace = mb_strrpos($truncated, ' ');
        if ($lastSpace !== false && $lastSpace > 150) {
            $truncated = mb_substr($truncated, 0, $lastSpace);
        }

        return $truncated.'...';
    }

    public function productExistsBySku(int $orgId, string $sku): bool
    {
        return Product::where('organization_id', $orgId)
            ->where('sku', $sku)
            ->exists();
    }

    public function ebayListingExistsByItemId(int $credentialId, string $itemId): bool
    {
        return EbayListing::where('integration_credential_id', $credentialId)
            ->where('ebay_listing_id', $itemId)
            ->exists();
    }

    public function updateExistingListing(int $credentialId, string $itemId, array $item): void
    {
        EbayListing::where('integration_credential_id', $credentialId)
            ->where('ebay_listing_id', $itemId)
            ->update([
                'status' => EbayListing::STATUS_PUBLISHED,
                'listed_price' => $item['start_price'],
                'currency' => $item['currency'] ?? 'USD',
                'available_quantity' => $item['available_quantity'] ?? 0,
                'sold_quantity' => $item['quantity_sold'] ?? 0,
                'listing_format' => $item['listing_type'] ?? 'FixedPriceItem',
                'condition' => $item['condition_display_name'] ?? null,
                'ebay_category_id' => $item['category_id'] ?? null,
                'ebay_category_name' => $item['category_name'] ?? null,
                'ebay_listing_url' => $item['view_item_url'] ?: null,
                'listing_end_date' => ! empty($item['end_time']) ? \Carbon\Carbon::parse($item['end_time']) : null,
                'last_synced_at' => now(),
            ]);
    }

    /**
     * Update an existing EbayListing by matching product SKU.
     *
     * This handles orphaned listings where the product exists but the EbayListing
     * has a NULL ebay_listing_id (e.g., CreateEbayListingJob failed partway through).
     * When eBay store import finds the item is live on eBay with a matching SKU,
     * we update the listing record with the actual eBay item data.
     */
    public function updateExistingListingByProductSku(int $orgId, int $credentialId, string $sku, array $item): void
    {
        $product = Product::where('organization_id', $orgId)
            ->where('sku', $sku)
            ->first();

        if (! $product) {
            return;
        }

        EbayListing::where('product_id', $product->id)
            ->where('integration_credential_id', $credentialId)
            ->update([
                'ebay_listing_id' => $item['item_id'],
                'status' => EbayListing::STATUS_PUBLISHED,
                'listed_price' => $item['start_price'],
                'currency' => $item['currency'] ?? 'USD',
                'available_quantity' => $item['available_quantity'] ?? 0,
                'sold_quantity' => $item['quantity_sold'] ?? 0,
                'listing_format' => $item['listing_type'] ?? 'FixedPriceItem',
                'condition' => $item['condition_display_name'] ?? null,
                'ebay_category_id' => $item['category_id'] ?? null,
                'ebay_category_name' => $item['category_name'] ?? null,
                'ebay_listing_url' => $item['view_item_url'] ?: null,
                'listing_end_date' => ! empty($item['end_time']) ? \Carbon\Carbon::parse($item['end_time']) : null,
                'last_synced_at' => now(),
            ]);
    }

    public function createProductFromEbayItem(array $item, int $organizationId, int $userId, string $defaultStatus = 'draft'): ?Product
    {
        $productData = $this->mapToProductData($item);

        try {
            $product = $this->createProductRecord($productData, $item, $organizationId, $userId, $defaultStatus);
        } catch (\Exception $e) {
            $this->addDebugContext('product_creation_failed', [
                'sku' => $productData['sku'],
                'item_id' => $item['item_id'] ?? null,
                'error' => $e->getMessage(),
                'sql_state' => method_exists($e, 'getCode') ? $e->getCode() : null,
            ]);
            throw $e;
        }

        // Create brand if specified
        if (! empty($productData['brand_name'])) {
            $brand = Brand::firstOrCreate(
                [
                    'organization_id' => $organizationId,
                    'slug' => Str::slug($productData['brand_name']),
                ],
                [
                    'name' => $productData['brand_name'],
                    'is_active' => true,
                ]
            );
            $product->update(['brand_id' => $brand->id]);
        }

        // Create product identifiers
        $identifiers = $productData['identifiers'] ?? [];
        if (! empty($identifiers['upc'])) {
            ProductIdentifier::create([
                'product_id' => $product->id,
                'type' => 'upc',
                'value' => $identifiers['upc'],
                'is_primary' => true,
                'source' => 'ebay_import',
                'verified' => false,
                'created_by' => $userId,
            ]);
        }

        if (! empty($identifiers['ean'])) {
            ProductIdentifier::create([
                'product_id' => $product->id,
                'type' => 'ean',
                'value' => $identifiers['ean'],
                'is_primary' => empty($identifiers['upc']),
                'source' => 'ebay_import',
                'verified' => false,
                'created_by' => $userId,
            ]);
        }

        if (! empty($identifiers['mpn'])) {
            ProductIdentifier::create([
                'product_id' => $product->id,
                'type' => 'mpn',
                'value' => $identifiers['mpn'],
                'is_primary' => false,
                'source' => 'ebay_import',
                'verified' => false,
                'created_by' => $userId,
            ]);
        }

        // Create product images
        $this->createProductImages($product, $productData['gallery_images'] ?? []);

        // Find or create category and link
        if (! empty($productData['category_name'])) {
            $category = $this->findOrCreateCategory(
                $organizationId,
                $productData['category_name'],
                $productData['category_id_ebay'] ?? null
            );
            if ($category) {
                $product->update(['category_id' => $category->id]);
            }
        }

        // Link supplier from parsed owner — except when the owner is one of
        // the org's house prefixes (e.g. "HAWK"). House stock
        // is owned outright, not sourced from a supplier; flag the product
        // and skip the Supplier + SupplierProduct creation entirely.
        $parsedOwner = $productData['parsed_owner'] ?? null;
        $ownerUpper = $parsedOwner ? strtoupper((string) $parsedOwner) : null;
        $isHouseStock = $ownerUpper !== null && in_array($ownerUpper, $this->housePrefixes, true);

        if ($isHouseStock) {
            $attrs = $product->attributes ?? [];
            if (empty($attrs['is_house_stock'])) {
                $attrs['is_house_stock'] = true;
                $product->update(['attributes' => $attrs]);
            }
        } elseif (! empty($parsedOwner)) {
            try {
                $supplier = Supplier::firstOrCreate(
                    ['code' => $parsedOwner, 'organization_id' => $organizationId],
                    [
                        'name' => $parsedOwner,
                        'supplier_status' => SupplierStatus::ONBOARDING,
                        'meta_data' => ['auto_generated' => true],
                    ]
                );
                SupplierProduct::firstOrCreate(
                    ['supplier_id' => $supplier->id, 'product_id' => $product->id],
                    ['supplier_sku' => $productData['raw_sku'] ?? $productData['sku'], 'cost_price' => 0, 'is_active' => true]
                );
            } catch (\Exception $e) {
                Log::warning('SKU parser: failed to link supplier', [
                    'owner' => $parsedOwner,
                    'product_id' => $product->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        // Build structured warehouse location from parsed SKU data
        $parsedLoc = $productData['parsed_location_data'] ?? null;
        $hasLocationData = $parsedLoc !== null && (
            ! empty($parsedLoc['owner']) ||
            ! empty($parsedLoc['consignor']) ||
            ! empty($parsedLoc['lister_initials']) ||
            ! empty($parsedLoc['bracket_prefix']) ||
            ! empty($parsedLoc['room']) ||
            ! empty($parsedLoc['shelf']) ||
            ! empty($parsedLoc['special_area']) ||
            ! empty($parsedLoc['container_type']) ||
            ! empty($parsedLoc['product_sku'])
        );
        if ($hasLocationData) {
            try {
                $warehouse = $this->warehouseBuilder->resolveMainWarehouse($organizationId);

                // Store consignor + intake_status data in product attributes
                $attrs = $product->attributes ?? [];
                if (! empty($parsedLoc['consignor'])) {
                    $attrs['consignor_code'] = $parsedLoc['consignor'];
                }
                if (! empty($parsedLoc['item_id'])) {
                    $attrs['consignor_item_id'] = $parsedLoc['item_id'];
                }
                if (! empty($parsedLoc['lister_initials'])) {
                    $attrs['lister_initials'] = $parsedLoc['lister_initials'];
                }
                if (! empty($parsedLoc['bracket_prefix'])) {
                    $attrs['bracket_prefix'] = $parsedLoc['bracket_prefix'];
                }
                if (! empty($parsedLoc['intake_status'])) {
                    $attrs['intake_status'] = $parsedLoc['intake_status'];
                }
                // Store parsed location fields for display/filtering
                foreach (['room', 'shelf', 'container_type', 'container_name', 'level', 'special_area'] as $field) {
                    if (! empty($parsedLoc[$field])) {
                        $attrs['warehouse_'.$field] = $parsedLoc[$field];
                    }
                }
                if ($attrs !== ($product->attributes ?? [])) {
                    $product->update(['attributes' => $attrs]);
                }

                // Create consignor as supplier if present and link to product.
                // Skip when the item is marked intake_status=pending — there
                // is no real consignor yet (the SKU said "PO PENDING").
                $consignorPending = ($parsedLoc['intake_status'] ?? null) === 'pending';
                if (! empty($parsedLoc['consignor']) && ! $consignorPending) {
                    try {
                        $consignor = Supplier::firstOrCreate(
                            ['code' => $parsedLoc['consignor'], 'organization_id' => $organizationId],
                            [
                                'name' => $parsedLoc['consignor'],
                                'supplier_status' => SupplierStatus::ONBOARDING,
                                'meta_data' => ['is_consignor' => true, 'auto_generated' => true],
                            ]
                        );
                        SupplierProduct::firstOrCreate(
                            ['supplier_id' => $consignor->id, 'product_id' => $product->id],
                            [
                                'supplier_sku' => $productData['raw_sku'] ?? $productData['sku'],
                                'cost_price' => 0,
                                'is_active' => true,
                            ]
                        );
                    } catch (\Exception $e) {
                        // Non-critical, continue
                    }
                }

                // Build warehouse hierarchy whenever the parser found any positional
                // data (room, special area, shelf, container, or level). Items with
                // shelf/container but no room land under the default "Main Floor" zone.
                $hasPositional = ! empty($parsedLoc['room'])
                    || ! empty($parsedLoc['special_area'])
                    || ! empty($parsedLoc['shelf'])
                    || ! empty($parsedLoc['container_type'])
                    || ! empty($parsedLoc['level']);

                if ($hasPositional) {
                    $bin = $this->warehouseBuilder->buildFromParsedLocation(
                        $parsedLoc,
                        $organizationId,
                        $warehouse->id
                    );

                    InventoryLevel::firstOrCreate(
                        ['product_id' => $product->id, 'location_id' => $warehouse->id, 'organization_id' => $organizationId],
                        ['quantity_on_hand' => $productData['stock_quantity'] ?? 0, 'quantity_reserved' => 0, 'bin_id' => $bin?->id]
                    );
                } else {
                    // No positional data — create basic inventory level without bin
                    InventoryLevel::firstOrCreate(
                        ['product_id' => $product->id, 'location_id' => $warehouse->id, 'organization_id' => $organizationId],
                        ['quantity_on_hand' => $productData['stock_quantity'] ?? 0, 'quantity_reserved' => 0]
                    );
                }
            } catch (\Exception $e) {
                Log::warning('Warehouse auto-build failed, falling back to flat location', [
                    'product_id' => $product->id,
                    'error' => $e->getMessage(),
                ]);
                // Fallback to flat location if structured parsing fails
                $this->createFlatInventoryLocation($product, $productData, $organizationId);
            }
        } elseif (! empty($productData['parsed_location'])) {
            // Legacy fallback: flat location from old SkuParserService
            $this->createFlatInventoryLocation($product, $productData, $organizationId);
        }

        // Calculate and store completeness score
        $this->updateCompletenessScore($product);

        return $product;
    }

    public function updateCompletenessScore(Product $product): void
    {
        $score = 0;

        if (! empty($product->gallery_images) || ! empty($product->featured_image)) {
            $score++;
        }
        if (! empty($product->description)) {
            $score++;
        }
        if ($product->weight > 0) {
            $score++;
        }
        if ($product->length > 0 && $product->width > 0 && $product->height > 0) {
            $score++;
        }
        if (! empty($product->upc) || ! empty($product->ean)) {
            $score++;
        }
        if ($product->brand_id) {
            $score++;
        }
        if (! empty($product->condition)) {
            $score++;
        }
        if ($product->category_id) {
            $score++;
        }
        if ($product->supplierProducts()->exists()) {
            $score++;
        }
        if (\App\Models\Inventory\InventoryLevel::where('product_id', $product->id)->exists()) {
            $score++;
        }

        $attributes = $product->attributes ?? [];
        $attributes['completeness_score'] = $score;
        $product->update(['attributes' => $attributes]);
    }

    /**
     * Legacy fallback: create a flat InventoryLocation from parsed_location string.
     */
    protected function createFlatInventoryLocation(Product $product, array $productData, int $organizationId): void
    {
        $locationCode = $productData['parsed_location'] ?? null;
        if (empty($locationCode)) {
            return;
        }

        try {
            $location = InventoryLocation::firstOrCreate(
                ['code' => $locationCode, 'organization_id' => $organizationId],
                ['name' => $locationCode, 'is_active' => true]
            );
            InventoryLevel::firstOrCreate(
                ['product_id' => $product->id, 'location_id' => $location->id, 'organization_id' => $organizationId],
                ['quantity_on_hand' => $productData['stock_quantity'] ?? 0, 'quantity_reserved' => 0]
            );
        } catch (\Exception $e) {
            Log::warning('SKU parser: failed to link flat location', [
                'location' => $locationCode,
                'product_id' => $product->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    protected function createProductRecord(array $productData, array $item, int $organizationId, int $userId, string $defaultStatus = 'draft'): Product
    {
        // Custom-SKU parsers often strip the raw SKU to a
        // canonical product id like "IND-56185-109" that ARC repeats across
        // many eBay listings (same product, different listings). The
        // (organization_id, sku) UNIQUE constraint means we must reuse an
        // existing Product if one already has this canonical SKU rather than
        // failing the row.
        $existing = Product::where('organization_id', $organizationId)
            ->where('sku', $productData['sku'])
            ->first();

        if ($existing) {
            return $existing;
        }

        return Product::create([
            'organization_id' => $organizationId,
            'name' => $productData['name'],
            'slug' => Str::slug($productData['name']).'-'.Str::random(5),
            'description' => $productData['description'],
            'short_description' => $productData['short_description'] ?? null,
            'sku' => $productData['sku'],
            'selling_price' => $productData['selling_price'],
            'currency' => $productData['currency'],
            'stock_quantity' => $productData['stock_quantity'],
            'available_quantity' => $productData['available_quantity'] ?? 0,
            'quantity' => $productData['quantity'] ?? 0,
            'reserved_quantity' => $productData['reserved_quantity'] ?? 0,
            'condition' => $productData['condition'],
            'condition_description' => $productData['condition_description'] ?? null,
            'upc' => $productData['upc'] ?? null,
            'ean' => $productData['ean'] ?? null,
            'mpn' => $productData['mpn'] ?? null,
            'weight' => $productData['weight'] ?? null,
            'length' => $productData['length'] ?? null,
            'width' => $productData['width'] ?? null,
            'height' => $productData['height'] ?? null,
            'total_sales' => $productData['total_sales'] ?? 0,
            'view_count' => $productData['view_count'] ?? 0,
            'search_keywords' => $productData['search_keywords'] ?? null,
            'featured_image' => $productData['featured_image'],
            'gallery_images' => $productData['gallery_images'] ?? [],
            'attributes' => $productData['attributes'] ?? [],
            'status' => $defaultStatus,
            'type' => 'simple',
            'track_inventory' => true,
            'manage_stock' => true,
            'created_by' => $userId,
        ]);
    }

    public function createEbayListingRecord(Product $product, array $item, int $credentialId): EbayListing
    {
        return EbayListing::create([
            'product_id' => $product->id,
            'integration_credential_id' => $credentialId,
            'team_id' => $this->credential->team_id,
            'ebay_sku' => $item['sku'] ?: $product->sku,
            'ebay_listing_id' => $item['item_id'],
            'ebay_listing_url' => $item['view_item_url'] ?: null,
            'status' => EbayListing::STATUS_PUBLISHED,
            'listed_price' => $item['start_price'],
            'currency' => $item['currency'] ?? 'USD',
            'available_quantity' => $item['available_quantity'] ?? 0,
            'sold_quantity' => $item['quantity_sold'] ?? 0,
            'listing_format' => $item['listing_type'] ?? 'FixedPriceItem',
            'condition' => $item['condition_display_name'] ?? null,
            'ebay_category_id' => $item['category_id'] ?? null,
            'ebay_category_name' => $item['category_name'] ?? null,
            'last_synced_at' => now(),
            'listing_start_date' => ! empty($item['start_time']) ? \Carbon\Carbon::parse($item['start_time']) : now(),
            'listing_end_date' => ! empty($item['end_time']) ? \Carbon\Carbon::parse($item['end_time']) : null,
            'last_sync_payload' => array_filter([
                'seller_profiles' => $item['seller_profiles'] ?? null,
                'listing_duration' => $item['listing_duration'] ?? null,
            ]),
        ]);
    }

    public function createProductImages(Product $product, array $pictures): void
    {
        if (empty($pictures)) {
            return;
        }

        foreach ($pictures as $index => $url) {
            ProductImage::create([
                'product_id' => $product->id,
                'url' => $url,
                'sort_order' => $index,
                'is_primary' => $index === 0,
                'metadata' => ['source' => 'ebay_import'],
            ]);
        }
    }

    public function findOrCreateCategory(int $orgId, string $categoryName, ?string $categoryId = null): ?Category
    {
        if (empty($categoryName)) {
            return null;
        }

        $slug = Str::slug($categoryName);

        $category = Category::where('organization_id', $orgId)
            ->where('slug', $slug)
            ->first();

        if ($category) {
            return $category;
        }

        $metadata = [];
        if ($categoryId) {
            $metadata['ebay_category_id'] = $categoryId;
        }

        return Category::create([
            'organization_id' => $orgId,
            'name' => $categoryName,
            'slug' => $slug,
            'is_active' => true,
            'metadata' => $metadata ?: null,
        ]);
    }

    public function updateProductFromEbayItem(Product $product, array $item, int $userId, array $updateOptions = []): Product
    {
        $mappedData = $this->mapToProductData($item);

        $updateArray = [
            'name' => $mappedData['name'],
            'description' => $mappedData['description'],
            'short_description' => $mappedData['short_description'],
            'selling_price' => $mappedData['selling_price'],
            'currency' => $mappedData['currency'],
            'stock_quantity' => $mappedData['stock_quantity'],
            'available_quantity' => $mappedData['available_quantity'],
            'quantity' => $mappedData['quantity'],
            'condition' => $mappedData['condition'],
            'condition_description' => $mappedData['condition_description'],
            'upc' => $mappedData['upc'],
            'ean' => $mappedData['ean'],
            'mpn' => $mappedData['mpn'],
            'weight' => $mappedData['weight'],
            'length' => $mappedData['length'],
            'width' => $mappedData['width'],
            'height' => $mappedData['height'],
            'featured_image' => $mappedData['featured_image'],
            'gallery_images' => $mappedData['gallery_images'] ?? [],
            'search_keywords' => $mappedData['search_keywords'],
            'total_sales' => $mappedData['total_sales'],
            'view_count' => $mappedData['view_count'] ?? 0,
            'attributes' => $mappedData['attributes'],
        ];

        // Preserve local price if requested
        if (! empty($updateOptions['preserve_price'])) {
            unset($updateArray['selling_price']);
        }

        // Preserve local description if requested
        if (! empty($updateOptions['preserve_description'])) {
            unset($updateArray['description'], $updateArray['short_description']);
        }

        // Merge attributes: keep local keys, update eBay keys
        $updateArray['attributes'] = array_merge(
            $product->attributes ?? [],
            $mappedData['attributes'] ?? []
        );

        $product->update($updateArray);

        // Update brand if changed
        if (! empty($mappedData['brand_name'])) {
            $brand = Brand::firstOrCreate(
                [
                    'organization_id' => $product->organization_id,
                    'slug' => Str::slug($mappedData['brand_name']),
                ],
                [
                    'name' => $mappedData['brand_name'],
                    'is_active' => true,
                ]
            );
            if ($product->brand_id !== $brand->id) {
                $product->update(['brand_id' => $brand->id]);
            }
        }

        // Upsert product identifiers
        $identifiers = $mappedData['identifiers'] ?? [];
        foreach (['upc', 'ean', 'mpn'] as $type) {
            if (! empty($identifiers[$type])) {
                ProductIdentifier::updateOrCreate(
                    ['product_id' => $product->id, 'type' => $type],
                    [
                        'value' => $identifiers[$type],
                        'source' => 'ebay_import',
                        'verified' => false,
                        'created_by' => $userId,
                    ]
                );
            }
        }

        // Sync product images: remove old ebay_import images, recreate
        ProductImage::where('product_id', $product->id)
            ->whereJsonContains('metadata->source', 'ebay_import')
            ->delete();
        $this->createProductImages($product, $mappedData['gallery_images'] ?? []);

        // Update category link
        if (! empty($mappedData['category_name'])) {
            $category = $this->findOrCreateCategory(
                $product->organization_id,
                $mappedData['category_name'],
                $mappedData['category_id_ebay'] ?? null
            );
            if ($category && $product->category_id !== $category->id) {
                $product->update(['category_id' => $category->id]);
            }
        }

        $product->refresh();
        $this->updateCompletenessScore($product);

        return $product;
    }

    public function reviseFixedPriceItem(EbayListing $listing, array $updates): bool
    {
        if (! $listing->ebay_listing_id) {
            throw new \Exception('Listing has no eBay ItemID for Trading API update.');
        }

        $operation = match (true) {
            isset($updates['title'], $updates['description']), isset($updates['title']), isset($updates['description']), isset($updates['picture_urls']) => EbaySyncLog::OP_SYNC_LISTING,
            isset($updates['start_price']) => EbaySyncLog::OP_SYNC_PRICE,
            default => EbaySyncLog::OP_SYNC_QUANTITY,
        };

        $syncLog = EbaySyncLog::startOperation(
            $this->credential->id,
            $listing->team_id,
            $operation,
            [
                'product_id' => $listing->product_id,
                'ebay_item_id' => $listing->ebay_listing_id,
                'ebay_sku' => $listing->ebay_sku,
            ]
        );

        $xml = $this->buildReviseFixedPriceItemXml($listing->ebay_listing_id, $updates);

        $syncLog->setRequest($this->tradingApiUrl, 'POST', [
            'call' => 'ReviseFixedPriceItem',
            'item_id' => $listing->ebay_listing_id,
            'updates' => $updates,
        ]);

        try {
            $response = Http::withHeaders([
                'X-EBAY-API-IAF-TOKEN' => $this->getToken()->decrypted_access_token,
                'X-EBAY-API-SITEID' => (string) $this->siteId,
                'X-EBAY-API-COMPATIBILITY-LEVEL' => (string) $this->compatibilityLevel,
                'X-EBAY-API-CALL-NAME' => 'ReviseFixedPriceItem',
                'Content-Type' => 'text/xml',
            ])->withBody($xml, 'text/xml')
                ->timeout(30)
                ->post($this->tradingApiUrl);

            if (! $response->successful()) {
                $syncLog->completeError(
                    'HTTP request failed with status: '.$response->status(),
                    'HTTP_'.$response->status(),
                    [],
                    $response->status(),
                    true
                );

                return false;
            }

            $body = $response->body();
            $xmlDoc = simplexml_load_string($body);

            if ($xmlDoc === false) {
                $syncLog->completeError('Failed to parse eBay response XML', 'PARSE_ERROR');

                return false;
            }

            $ack = (string) $xmlDoc->Ack;

            if ($ack === 'Failure') {
                $errorMsg = (string) ($xmlDoc->Errors->LongMessage ?? $xmlDoc->Errors->ShortMessage ?? 'Unknown error');
                $errorCode = (string) ($xmlDoc->Errors->ErrorCode ?? 'UNKNOWN');
                $syncLog->completeError($errorMsg, $errorCode, [], $response->status(), true);

                return false;
            }

            // Update listing record with new values
            $listingUpdates = ['last_synced_at' => now()];
            if (isset($updates['quantity'])) {
                $listingUpdates['available_quantity'] = $updates['quantity'];
                $listingUpdates['last_inventory_update_at'] = now();
            }
            if (isset($updates['start_price'])) {
                $listingUpdates['listed_price'] = $updates['start_price'];
                $listingUpdates['last_price_update_at'] = now();
            }
            $listing->update($listingUpdates);

            $syncLog->completeSuccess(['ack' => $ack], $response->status());

            Log::info('ReviseFixedPriceItem success', [
                'listing_id' => $listing->id,
                'item_id' => $listing->ebay_listing_id,
                'updates' => $updates,
            ]);

            return true;

        } catch (\Exception $e) {
            $syncLog->completeError($e->getMessage(), 'EXCEPTION', [], null, true);

            Log::error('ReviseFixedPriceItem failed', [
                'listing_id' => $listing->id,
                'item_id' => $listing->ebay_listing_id,
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    protected function buildReviseFixedPriceItemXml(string $itemId, array $updates): string
    {
        $fields = '';

        if (isset($updates['quantity'])) {
            $fields .= '    <Quantity>'.intval($updates['quantity']).'</Quantity>'."\n";
        }

        if (isset($updates['start_price'])) {
            $currency = $updates['currency'] ?? 'USD';
            $fields .= '    <StartPrice currencyID="'.htmlspecialchars($currency).'">'.number_format((float) $updates['start_price'], 2, '.', '').'</StartPrice>'."\n";
        }

        if (isset($updates['title'])) {
            $fields .= '    <Title>'.htmlspecialchars(mb_substr($updates['title'], 0, 80)).'</Title>'."\n";
        }

        if (isset($updates['description'])) {
            $fields .= '    <Description>'.htmlspecialchars($updates['description']).'</Description>'."\n";
        }

        if (isset($updates['sku'])) {
            $fields .= '    <SKU>'.htmlspecialchars($updates['sku']).'</SKU>'."\n";
        }

        if (isset($updates['picture_urls']) && is_array($updates['picture_urls'])) {
            $fields .= "    <PictureDetails>\n";
            foreach ($updates['picture_urls'] as $url) {
                $fields .= '        <PictureURL>'.htmlspecialchars($url).'</PictureURL>'."\n";
            }
            $fields .= "    </PictureDetails>\n";
        }

        return '<?xml version="1.0" encoding="utf-8"?>
<ReviseFixedPriceItemRequest xmlns="urn:ebay:apis:eBLBaseComponents">
    <RequesterCredentials>
        <eBayAuthToken>'.htmlspecialchars($this->getToken()->decrypted_access_token).'</eBayAuthToken>
    </RequesterCredentials>
    <Item>
        <ItemID>'.htmlspecialchars($itemId).'</ItemID>
'.$fields.'    </Item>
</ReviseFixedPriceItemRequest>';
    }
}
