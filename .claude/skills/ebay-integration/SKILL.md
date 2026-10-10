---
name: ebay-integration
description: eBay listing creation, sync system, category matching, item specifics, OAuth, and error handling - invoke when working on eBay features
user-invocable: true
---

# eBay Integration Reference

## Key Files

| Component | Path |
|-----------|------|
| Listing Job | `app/Jobs/CreateEbayListingJob.php` |
| Listing Orchestrator | `app/Services/Ebay/EbayListingService.php` (create-or-update entry for sync/retry/bulk) |
| Inventory Service | `app/Services/EbayInventoryService.php` |
| Sell Service | `app/Services/EbaySellService.php` |
| Category Service | `app/Services/EbayCategoryService.php` |
| Config Service | `app/Services/EbayConfigService.php` |
| OAuth Service | `app/Services/EbayOAuthService.php` |
| Policy Rules | `app/Services/Ebay/EbayPolicyRulesEngine.php` |
| Rate Limiter | `app/Services/EbayRateLimiter.php` |
| API Exception | `app/Services/EbayApiException.php` |
| Browse Service | `app/Services/EbayBrowseService.php` |
| Sold Listings | `app/Services/EbaySoldListingsService.php` |
| Store Import | `app/Services/EbayStoreImportService.php` |
| Scheduled Tasks | `app/Jobs/Ebay/ProcessEbayScheduledTasks.php` |
| Update Inventory | `app/Jobs/Ebay/UpdateEbayInventory.php` |
| Sync Product Job | `app/Jobs/Ebay/SyncProductToEbay.php` (create-or-update one product) |
| Update Listing Job | `app/Jobs/Ebay/UpdateEbayListing.php` (full listing update incl. images + specifics) |
| Bulk Sync | `app/Jobs/Ebay/BulkSyncToEbay.php` (static `syncProducts()` / `syncAllPending()` -> batch of SyncProductToEbay) |
| Retry Job | `app/Jobs/Ebay/RetryFailedEbaySync.php` |
| Import Job | `app/Jobs/Ebay/ImportEbayInventory.php` |
| ECN Handler | `app/Http/Controllers/Ebay/EbayNotificationController.php` |
| Web Controller | `app/Http/Controllers/Marketplaces/EbayMarketplaceController.php` |
| API Controller | `app/Http/Controllers/Api/Ebay/EbayApiController.php` |
| OAuth Controller | `app/Http/Controllers/Ebay/EbayOAuthController.php` |
| EbayListing Model | `app/Models/Ebay/EbayListing.php` |
| EbaySyncLog Model | `app/Models/Ebay/EbaySyncLog.php` |
| EbayCategory Model | `app/Models/Ebay/EbayCategory.php` |

## Listing Creation Flow (6 Steps)

```
Product Intake Queue -> AI Analysis -> Verification Wizard -> CreateEbayListingJob -> eBay API
```

CreateEbayListingJob steps:
1. Create/find Product record
2. Find best eBay category (authoritative, 6-strategy chain)
3. Validate condition against category-specific eBay policies
4. Create inventory item (product data + aspects with fuzzy matching)
5. Create offer (pricing + business policies)
6. Publish to eBay

Status progression: `in_progress -> listed_on_ebay | retry_scheduled | needs_review | listing_failed | already_exists`

## Category Matching (6-Strategy Chain)

| # | Strategy | Confidence | Method |
|---|----------|-----------|--------|
| 1 | eBay API Suggestions | 95% | `findByApiSuggestion()` - ML-based, cached 24h |
| 2 | Exact brand+model | 95% | `findByExactMatch()` - local DB |
| 3 | Keyword search | 85% | `searchByKeywords()` - full-text local DB |
| 4 | Brand-specific | 75% | `findByBrand()` - hardcoded map |
| 5 | Product type fallback | 60% | `findByProductType()` - type-to-category map |
| 6 | Default | 30% | `getDefaultCategory()` - "Everything Else > Other" (10290) |

**Two-pass approach**: Preliminary (ProcessQueueItem Step 3.5, for AI prompt aspects) and Final (CreateEbayListingJob, authoritative).

## Item Specifics Pipeline (7 Steps)

1. **Preliminary category lookup** -> `getAspectsForPrompt()` returns required/recommended aspects with allowed values
2. **AI spec generation** -> Gemini prompt includes eBay aspects; AI returns item_specifics. Prompt enforces <=65-char, concise values with no parentheticals, and instructs the model to OMIT unknown aspects rather than write "Not specified"/"Unknown"
3. **User review** -> Wizard merges AI specs; user can add/edit/remove
4. **Aspect assembly** (`buildAspects()`) -> merges from all sources:
   - Standard identifiers (Brand, MPN, Model, UPC, EAN)
   - Specifications via `$specMapping` (67-key safety net)
   - Custom aspects from wizard (HIGHEST PRIORITY -- overwrites)
   - `fillRequiredAspects()` for missing REQUIRED aspects
5. **Fuzzy value matching** (`matchAllowedValue()`) -> exact (case-insensitive) -> normalized (strip spaces/dashes) -> substring containment -> send as-is
6. **Value sanitization** (`sanitizeAspectValue()`, const `EBAY_ASPECT_VALUE_MAX_LENGTH = 65`) -> final pass over ALL assembled aspect values: strips a trailing parenthetical ("Not specified (long aside)" -> "Not specified"), then caps at 65 chars on a word boundary, drops empties (and unsets the aspect if no value survives). This is the single chokepoint that guarantees nothing exceeds eBay's 65-char limit, regardless of source.
7. **Final fallback** -> Unfilled REQUIRED aspects get "Does Not Apply"; logged to `aspect_audit`

**Durable persistence (2026-06-06):** intake now writes structured specifics onto
`Product.attributes` (top-level `brand/model/mpn/upc/ean` + nested `specifications`
map) via `IntakeQueueController::buildIntakeAttributes()`, merging AI specs < AI eBay
specifics < user-edited specifics. So both create AND update read specifics from the
Product (single source of truth), not the transient queue `enriched_data`.
`Product::toEbayInventoryData()` includes `images` + `specifications` so create/sync
pushes them. Read the `attributes` JSON column inside the model via
`getAttribute('attributes')` to avoid the Eloquent `$attributes` property collision.

**Serialization rule:** `product.aspects` must be a JSON OBJECT. An empty PHP array
serializes as `[]` and eBay rejects it (`errorId 2004`, "Could not serialize field
[product.aspects]"). `buildInventoryItemPayload()` OMITS the `aspects` key when empty;
never send `aspects: []`.

## EbayListing Status Lifecycle

```
pending -> inventory_created -> offer_created -> published -> ended | out_of_stock
                                                          -> error (any failure)
error -> pending (retry)
```

Key scopes: `stale()` (>24h), `retryable()` (error + consecutive_errors<5), `published()`, `withErrors()`

Key methods: `markSynced()`, `recordError($msg)`, `hasProductChanged()`, `generateProductHash()`

## Sync System

### Create-or-update sync (SyncProductToEbay / EbayListingService)

The Retry / Sync-to-eBay / Bulk-sync buttons and the mobile eBay endpoints all
dispatch `SyncProductToEbay` (or batch them via `BulkSyncToEbay::syncProducts()`).
Constructor: `(Product, ?IntegrationCredential, ?categoryId, bool forceUpdate, ?userId)` —
match this when adding new dispatch sites.

`SyncProductToEbay::handle()` delegates to `EbayListingService::syncProduct()`, which routes:

| Case | Path | Why |
|------|------|-----|
| Existing live listing (`ebay_listing_id` set) | `UpdateEbayListing` job | Rich update: images + item specifics; eBay's inventory PUT is a full replace, so a thin payload would STRIP specifics |
| Listing left non-published by a prior failure | unstick (status -> `published`, errors -> 0) then `UpdateEbayListing` | Retry self-heals; `UpdateEbayListing` skips anything not published |
| No listing yet | `EbayInventoryService::syncProduct()` | Create inventory item + offer + publish |

> **History:** `EbayListingService` was missing for a long time (namespace-refactor
> casualty) and the jobs had stale constructors, so every Retry/Sync/Bulk/mobile
> button threw on dispatch and left listings stuck `pending`. Fixed 2026-06-06.

`UpdateEbayListing` (full listing) vs `UpdateEbayInventory` (qty/price only): the
former re-pushes title/description/**images/aspects** via the Inventory Item API
and refetches the category's `required_aspects`; the latter is the lightweight
qty/price path below.

Manual re-push / unstick: `php artisan ebay:resync <product UUID|SKU>` mirrors
current `product_images` into `gallery_images` and re-pushes via `UpdateEbayListing`.

### Scheduled Tasks (ProcessEbayScheduledTasks)

| Task | Description |
|------|-------------|
| `update_stale` | Listings not synced in 24+ hours -> UpdateEbayInventory |
| `retry_failed` | Error listings with consecutive_errors<5 -> RetryFailedEbaySync |
| `sync_quantities` | Mismatched local vs listed_quantity -> UpdateEbayInventory(quantity) |
| `cleanup_logs` | Purge old EbaySyncLog records |

Auto-sync gate: checks `auto_sync_enabled` flag on IntegrationCredential.

### UpdateEbayInventory

| updateType | Published listing (has offer_id) | Imported listing (no offer_id) |
|-----------|--------------------------------|-------------------------------|
| `quantity` | Offer API: `PUT /sell/inventory/v1/offer/{offerId}` | Trading API: `ReviseFixedPriceItem` |
| `price` | Offer API: `PUT /sell/inventory/v1/offer/{offerId}` | Trading API: `ReviseFixedPriceItem` |
| `both` | Single Offer API call | Trading API: `ReviseFixedPriceItem` |

**CRITICAL**: Published listings MUST use the Offer API. The Inventory Item API (`PUT /inventory_item/{sku}`) is silently ignored by eBay for published listings.

Change detection: compares against `available_quantity`/`listed_price`, skips if unchanged.
`updateOffer()` also updates `available_quantity`, `last_inventory_update_at`, and `last_synced_at` on the local listing record.

## Error Handling

### EbayApiException Classification

| Method | Condition |
|--------|----------|
| `isTokenError()` | OAuth expired/invalid/revoked |
| `isRateLimitError()` | HTTP 429 or error code 25010 |
| `isRetryable()` | Transient server error (codes 25001,25002,25008,25019, HTTP 5xx) |
| `isValidationError()` | Bad request data (non-retryable) |
| `isNotFound()` | Resource doesn't exist on eBay |

### Aspect value too long (prevented, not surfaced)

eBay returns `"...value of 'X' is too long. Enter a value of no more than 65 characters"` when any item-specific exceeds 65 chars (common with verbose AI non-answers). As of the `sanitizeAspectValue()` cap (step 6 above) this is prevented at build time. If it recurs: suspect stale Horizon code (run `horizon:terminate`) or an aspect value path that bypasses `buildAspects()`.

### Consecutive Error Counter

- `EbayListing.consecutive_errors` incremented on failure, reset on success
- >=5 errors: excluded from auto-retry, must be manually fixed
- `RetryFailedEbaySync` checks: consecutive_errors<5, credential active, product active, error not permanent

## Rate Limiter (EbayRateLimiter)

| Parameter | Value |
|-----------|-------|
| Per-second | 5 requests/second (token bucket) |
| Daily | 5,000 requests/day (counter) |
| Backoff | 500ms -> 1s -> 2s (max 30s) |
| Max retries | 3 per API call |
| Redis keys | `ebay_api_rate_limit`, `ebay_api_daily_limit:YYYY-MM-DD` |

## OAuth Flow (7 Steps)

1. User clicks "Connect to eBay" -> IntegrationController checks org + config
2. Generate auth URL with encrypted state (org_id, credential_id, timestamp)
3. Redirect to eBay auth.ebay.com/oauth2/authorize
4. User authorizes -> eBay redirects with code + state
5. `EbayOAuthController::callback()` decrypts state, validates (10-min expiry)
6. `exchangeCodeForToken()` -> POST /identity/v1/oauth2/token (Basic Auth)
7. Store OAuthToken (encrypted at rest), fetch user info

Token refresh: scheduled `RefreshOAuthTokens` job, threshold 30 min before expiry.

Scopes: sell.inventory, sell.fulfillment, sell.account, sell.marketing, sell.analytics.readonly, sell.finances, sell.payment.dispute, commerce.identity.readonly

## eBay Condition Enum Values

| Enum | ID | Description |
|------|-----|-------------|
| `NEW` | 1000 | Brand new |
| `NEW_OTHER` | 1500 | New, not original packaging |
| `NEW_WITH_DEFECTS` | 1750 | New with defects |
| `CERTIFIED_REFURBISHED` | 2000 | Manufacturer-certified |
| `SELLER_REFURBISHED` | 2500 | Seller refurbished |
| `USED_EXCELLENT` | 3000 | Used, excellent |
| `USED_VERY_GOOD` | 4000 | Used, very good |
| `USED_GOOD` | 5000 | Used, good |
| `USED_ACCEPTABLE` | 6000 | Used, acceptable |
| `FOR_PARTS_OR_NOT_WORKING` | 7000 | For parts |

Conditions validated per-category via Sell Metadata API. Cached 7 days.

## Store Import

`ImportEbayInventory` job: fetches pages of 200 from Trading API `GetSellerList`, creates Product + EbayListing records. `EbayStoreImportService` handles data mapping (title, description, condition, price, quantity, identifiers, images, category).

**CSV variant**: `ImportEbayInventoryFromCsv` + `EbayCsvParser` consume eBay's "Active Listings" report CSV (4 columns: `Item number`, `Title`, `Variation details`, `Custom label (SKU)`) and feed the same import service. Hardcoded defaults fill the missing fields: `available_quantity=1` (every row is a live listing), `condition_id=4000` (Used). Self-redispatching with `CHUNK_SIZE=50`, `$tries=2`, `$timeout=180`, `DEBUG_LOG_MAX=200`. Sizing is constrained by the redis queue `retry_after` (default 90s) and the container memory ceiling — see `docs/EBAY_SYNC_AND_UPDATE_SYSTEM.md` § "CSV Import variant" for the full rationale.

### SKU Parser (Multi-Parser, config-based)

`SkuParserService` is the org-configurable parser. Supports multiple parsers tested in order via `parseMulti()`, each with optional prefix matching. Config stored in `IntegrationCredential.config.sku_parsers` (array). Returns `{owner, sku, location}`.

Key files: `app/Services/SkuParserService.php`, `resources/js/Components/Marketplaces/SkuParserConfig.vue`, `tests/Unit/SkuParserServiceTest.php`.

### Structured Location Parser (`EbaySkuLocationParser`)

Runs on **every** import (no config required). Combined with `EbayStoreImportService` it hydrates three backend domains from a single freeform custom label: Catalog (Product), Suppliers, and Warehouse (Zone/Bin/InventoryLevel). Validated against a 66k-row production export.

**Canonical token order:**

```
[bracket-prefix?] OWNER [(LISTER_INITIALS | CONSIGNOR [item_id]) ...] [price] LOCATION_TOKENS
```

| Slot | Examples | Backend target |
|------|----------|----------------|
| bracket-prefix (optional) | `(C)`, `(T)`, `(GHI)`, `(COOP)` | Captured into `Product.attributes.bracket_prefix` (raw letter, not interpreted). |
| OWNER (required) | `ARC`, `HAWK`, `OAKS`, `PINE`, `FERN`, `DUNE`, `REEF`, `REEF6`, `MESA`, `COVE`, `KELP`, `SAGE`, `MOSS`, `BIRCH2`, `CEDR`, `ELMS`, `COOP` | `Supplier(code=OWNER, organization_id)` via `firstOrCreate`. Matched case-insensitively (`arc abc ...` parses), result stored uppercase. House-stock prefixes from `IntegrationCredential.config.house_prefixes` skip Supplier creation and set `Product.attributes.is_house_stock=true`. |
| LISTER_INITIALS (optional) | `ABC`, `DEF`, `GHI` — configured via `IntegrationCredential.config.known_listers` | `Product.attributes.lister_initials`. **Does NOT create a Supplier row.** |
| CONSIGNOR (optional) | `COOP`, `MADM`, `KURT` (anything 2-5 letters not in `known_owners` or `known_listers`) | `Supplier(code=CONSIGNOR, organization_id, meta.is_consignor=true)` + `Product.attributes.consignor_code`. |
| item_id (optional) | `58585`, `78546`, `87633` | `Product.attributes.consignor_item_id` (only set with a consignor). |
| price (KELP pattern, optional) | `$3.44`, `29$`, `$127.52` | Currently discarded. |
| LOCATION_TOKENS | `RM 134`, `SHELF B3`, `TUB GOLDENGOOSE`, `BOX KURT`, `LVL 3`, `GROUND`, `FOH`, `MEZZ`, `CART`, `RACK` | Mapped via `WarehouseAutoBuilder` (next section). |

**Default `known_owners`** (seeded in `EbayStoreImportService::__construct()`; override via `IntegrationCredential.config.known_owners`):

```
HAWK, PINE, OAKS, FERN, DUNE, REEF, REEF6, MESA, COVE, KELP,
SAGE, MOSS, BIRCH, BIRCH2, CEDR, ELMS, FOX, TIDE, SILT, DELT, CLAY, LOAM
```

Tokens in this list are never tagged as consignors (prevents HAWK appearing as both owner and consignor of itself).

**Worked examples from the CSV** (with `known_listers=['ABC','DEF','GHI']` configured):

| Raw SKU | Backend writes |
|---------|----------------|
| `HAWK RM 134 SHELF B3 TUB GOLDENGOOSE` | Supplier(HAWK), Zone(`RM-134`), Bin(`SHLF-B3-TUB-GOLDENGOOSE`), InventoryLevel |
| `HAWK COOP 58585 SHELF 29 TUB ELTONJOHN` | Supplier(HAWK), Supplier(COOP, `meta.is_consignor=true`), `Product.attributes.consignor_item_id=58585`, Bin(`SHLF-29-TUB-ELTONJOHN`) |
| `HAWK ABC SHELF 28 LVL 3` | Supplier(HAWK), `Product.attributes.lister_initials=ABC` (no Supplier(ABC)), Bin(`SHLF-28-LVL-3`) |
| `ARC ABC PO-1043-1020-101 AE-3/BOOST` | Supplier(ARC), `Product.attributes.lister_initials=ABC` (no Supplier(ABC)), `Product.sku=PO-1043-1020-101` |
| `(C) PINE Tub Morrigan Shelf B3 21655` | `Product.attributes.bracket_prefix=C`, Supplier(PINE), Bin(`SHLF-B3-TUB-MORRIGAN`) |
| `arc abc PO-1043-1020-101 FOH` (lowercase) | Supplier(ARC), `Product.attributes.lister_initials=ABC`, Zone(`FOH`) |

### Warehouse Auto-Builder

`WarehouseAutoBuilder` writes Zones + Bins + the default `WAREHOUSE` `InventoryLocation`. Called inline from `EbayStoreImportService::importAndProcessItem()`. All writes use `firstOrCreate` and are org-scoped, so re-running the import is idempotent.

| Parsed field | Backend write |
|--------------|---------------|
| `room` | `WarehouseZone(code=RM-<room>, name=Room <room>, organization_id)` |
| `special_area` (when no room) | `WarehouseZone(code=FOH\|MEZZ\|CART\|…, name=Front of House\|…)` |
| `shelf` + `level` + `container_type` + `container_name` | `WarehouseBin(zone_id, code=<compound>, label, rack, shelf, bin_position, barcode)` where compound code is e.g. `SHLF-B3-LVL-3-TUB-GOLDENGOOSE` |
| (always) | `InventoryLocation(code=WAREHOUSE, organization_id, is_default=true)` |
| (always when product imported) | `InventoryLevel(product_id, location_id, organization_id, bin_id?, quantity_on_hand)` — pick reads from here, **not** `Product.warehouse_location_id` (legacy bigint, ignored) |

Bin code uniqueness is shelf + level + container, so misspelled container names inflate the bin count (Gap #8).

### Known issues / follow-up code work

Gaps #1, #2, #3, #6 below were addressed in the 2026-05-13 code pass; the remaining gaps are still open.

1. ~~Consignor-only SKU drops Supplier link.~~ Fixed: `$hasLocationData` predicate now includes `consignor`, `lister_initials`, and `bracket_prefix`.
2. ~~Lister initials misclassified as consignors.~~ Fixed: `IntegrationCredential.config.known_listers` routes those tokens to `Product.attributes.lister_initials`; no Supplier row is created. Seed via the config (e.g. `['ABC','DEF','GHI']`).
3. ~~Bracket prefix info dropped.~~ Fixed: captured into `Product.attributes.bracket_prefix` (raw letter, not interpreted).
4. **COOP is both an owner and a frequent 2nd-token consignor.** Some lines invert order (`COOP HAWK ...`). First-token-wins keeps this consistent but means inverted-order rows mis-assign the warehouse owner. Still open.
5. **GHI is not in `known_owners`** despite leading 242 rows; falls through to the owner regex when first, but gets tagged as consignor when second (unless added to `known_listers` or `known_owners` per org). Still open.
6. ~~Lowercase `arc` is not matched.~~ Fixed: owner regex is now case-insensitive; result is uppercased. Owner regex also skips tokens that are location keywords (`SHELF`, `TUB`, `RM`, …) to avoid eating location-first SKUs.
7. **682 rows have no recognisable owner prefix** (location-first or model-first). Parser falls through to keyword-only extraction; products land in `WAREHOUSE` with no owner Supplier link. Still open.
8. **Bin code granularity is shelf+level+container** — typos in container names create distinct bins. Still open.

Key files: `app/Services/EbaySkuLocationParser.php`, `app/Services/EbayStoreImportService.php`, `app/Services/Inventory/WarehouseAutoBuilder.php`, `tests/Unit/EbaySkuLocationParserTest.php`, `tests/Feature/EbayStoreImportServiceTest.php`.

### Import Prompt on Connection

When a user connects their eBay account via OAuth and their organization has zero products, a dismissible banner is shown on the integrations settings page prompting them to import their eBay inventory.

| Component | Path |
|-----------|------|
| OAuth callback (flash) | `app/Http/Controllers/Ebay/EbayOAuthController.php` |
| Settings data (productCount) | `app/Http/Controllers/Organization/OrganizationController.php` |
| Banner UI | `resources/js/Pages/Organization/Partials/IntegrationsTab.vue` |
| Tests | `tests/Feature/Integration/EbayImportPromptTest.php` |

Flow: OAuth callback checks `Product::where('organization_id', ...)->exists()`, flashes `show_ebay_import_prompt`. Settings page also passes `productCount` so the banner shows reactively when eBay is connected + products == 0. Banner is dismissible and links to `marketplaces.ebay.import`.

## Promoted Listings

### Key Files
| File | Purpose |
|------|---------|
| `app/Models/Ebay/EbayPromotedCampaign.php` | Campaign record with budget, status, and ad strategy |
| `app/Models/Ebay/EbayPromotedListing.php` | Individual listing enrolled in a campaign with ad rate |
| `app/Services/Ebay/EbayPromotedListingsService.php` | Campaign CRUD, listing enrollment, stats sync |

### Campaign Management
Campaigns are created via the eBay Marketing API. Each campaign has a funding strategy (cost-per-sale percentage) and can contain multiple promoted listings. `EbayPromotedListingsService` handles creation, updating, and deletion of campaigns.

### Ad Rate Settings
Each `EbayPromotedListing` has an `ad_rate` (percentage of sale price). The suggested ad rate is fetched from eBay's API per listing. Users can set custom rates or accept the suggested rate.

### Stats Sync
The `SyncPromotedListingStats` job runs daily, pulling performance metrics from the eBay Promoted Listings Reporting API: impressions, clicks, sales, spend, and revenue. ROAS (Return on Ad Spend) is calculated as `promoted_revenue / promoted_spend`.

---

## Buyer Messages

### Key Files
| File | Purpose |
|------|---------|
| `app/Services/Communication/EbayMessageService.php` | Syncs messages via eBay Trading API (GetMyMessages) |
| `app/Models/Communication/MarketplaceMessage.php` | Stored message with thread tracking |
| `app/Models/Communication/MarketplaceMessageReply.php` | Reply within a message thread |

### Message Retrieval and Thread Management
`EbayMessageService` polls eBay's Trading API (`GetMyMessages`) to retrieve buyer messages. Messages are stored as `MarketplaceMessage` records, grouped into threads by eBay's `itemId` and `senderId`. Replies sent through the app are dispatched via the eBay Trading API (`AddMemberMessageAAQToPartner`).

---

## Fee Extraction

### Parsing Fees from eBay Order Data
During order import, `OrderSyncService` extracts fee data from eBay's Fulfillment API order response. Fees are parsed from the `totalMarketplaceFee` and individual line item fee breakdowns.

### Fee Types
| Fee Type | Source Field | Description |
|----------|-------------|-------------|
| `final_value` | `finalValueFee` | Percentage of sale price |
| `insertion` | `insertionFee` | Listing fee (if applicable) |
| `promoted` | `promotedListingFee` | Ad cost for promoted listings |
| `payment_processing` | `paymentProcessingFee` | Managed payments processing fee |

These fees are stored per order and used by the P&L analytics and `FeeTrackingService` for profitability calculations.

---

## Debugging Commands

```bash
# Listing status and error
docker exec ecommerce_app php artisan tinker --execute="App\Models\Ebay\EbayListing::where('product_id','UUID')->first()?->only(['status','consecutive_errors','last_error_message','last_synced_at']);"

# Recent sync logs
docker exec ecommerce_app php artisan tinker --execute="App\Models\Ebay\EbaySyncLog::where('ebay_listing_id',ID)->latest()->take(10)->get(['operation','status','error_message','created_at']);"

# Listing count by status
docker exec ecommerce_app php artisan tinker --execute="App\Models\Ebay\EbayListing::selectRaw(\"status, count(*) as total\")->groupBy('status')->get()->toArray();"

# Rate limiter status
docker exec ecommerce_app php artisan tinker --execute="echo json_encode(app(App\Services\EbayRateLimiter::class)->getStatus(), JSON_PRETTY_PRINT);"

# Reset retryable error listings
docker exec ecommerce_app php artisan tinker --execute="App\Models\Ebay\EbayListing::withErrors()->where('consecutive_errors','<',5)->update(['status'=>'pending','consecutive_errors'=>0]);"

# Re-push a listing's current images + specifics (mirrors product_images -> gallery_images,
# unsticks non-published status, runs UpdateEbayListing). Prints per-listing result.
docker exec ecommerce_app php artisan ebay:resync <product UUID|SKU>

# Trigger scheduled task manually
docker exec ecommerce_app php artisan tinker --execute="App\Jobs\Ebay\ProcessEbayScheduledTasks::dispatch('update_stale');"

# Check aspect audit for a listing
docker exec ecommerce_app php artisan tinker --execute="\$l=App\Models\Ebay\EbayListing::where('product_id','UUID')->first(); echo json_encode(\$l->aspect_audit ?? [], JSON_PRETTY_PRINT);"

# Check OAuth token health
docker exec ecommerce_app php artisan tinker --execute="App\Models\Integration\OAuthToken::where('platform','ebay')->where('is_active',true)->get(['id','expires_at','last_used_at'])->each(fn(\$t)=>print('Token '.\$t->id.': expires '.\$t->expires_at.PHP_EOL));"

# Test category resolution
docker exec ecommerce_app php artisan tinker --execute="\$svc=app(App\Services\EbayCategoryService::class); print_r(\$svc->findBestCategory(['title'=>'Dell Latitude 5520','brand'=>'Dell']));"
```

## Sandbox vs Production

`EbayConfigService::isSandbox()` is true whenever `APP_ENV !== 'production'` (override `EBAY_USE_SANDBOX`). All eBay API calls MUST go through `EbayConfigService::getApiBaseUrl()` / `getOAuthTokenUrl()` — no hardcoded `api.ebay.com`. `IntegrationCredential.environment` is matched per-env when looking up creds.

**Sandbox limits (eBay-side):**
- **Finding API**: no sandbox endpoint. `EbayFindingService::search()` short-circuits + warns.
- **Browse API**: returns stub data. UPC lookup unreliable on develop. Logs warning.
- **Marketing API / Promoted Listings**: not in sandbox.
- Sandbox seller policies + merchant location must be re-created from scratch.

**ECN webhooks:** legacy `/api/webhooks/ebay` endpoint reads env-specific token via `EbayNotificationController::appNotificationToken()` (`EBAY_SANDBOX_NOTIFICATION_TOKEN` / `EBAY_PRODUCTION_NOTIFICATION_TOKEN`). Per-credential `/api/webhooks/ebay/{credentialId}` reads from `IntegrationCredential.config.ebay_notification_token`.

See `docs/EBAY_SANDBOX_TESTING.md` for sandbox seller setup, ECN registration, cutover SQL.

## Notifications

eBay integration events that trigger user notifications:

| Event | Notification Class | Priority |
|-------|--------------------|----------|
| eBay listing created | `Ebay\EbayListingCompleted` | medium |
| eBay listing failed | `Ebay\EbayListingCompleted` | high |
| eBay import completed | `EbayImportCompleted` | medium/high |
| eBay order sync failed | `Fulfillment\OrderSyncFailedNotification` | high |
| eBay tracking upload failed | `Fulfillment\TrackingUploadFailedNotification` | high |
| eBay OAuth token failed | `System\OAuthTokenFailedNotification` | critical |

See `docs/NOTIFICATION_SYSTEM.md` for the full notification architecture.

## Audit Logging

| Action | Category | Severity | Metadata keys |
|--------|----------|----------|---------------|
| `listing.ebay.published` | marketplace | info | ebay_item_id, sku, category_id, listing_format |
| `listing.ebay.updated` | marketplace | info | changes, ebay_item_id |
| `listing.ebay.ended` | marketplace | warning | reason, ebay_item_id |
| `listing.ebay.relisted` | marketplace | info | original_item_id, new_item_id |
| `listing.ebay.promoted` | marketplace | info | campaign_id, rate_pct |
| `ebay.sync.ran` | marketplace | debug | listings_synced, source: cron |
| `ebay.sync.failed` | marketplace | error | error_code, http_status, ebay_item_id |

Trait: models in this feature use `LogsAudit` (`app/Traits/LogsAudit.php`) for auto CRUD diff. Domain lifecycle events emitted from `app/Services/Ebay/{Sell,Inventory,PromotedListing}Service`, `CreateEbayListingJob`, `EbayNotificationController` (webhook -- wrap in `withAuditActor(null, ..., ['source' => 'webhook'])`); `EbayListing` + `EbayPromotedCampaign` models (`LogsAudit`) via `UserActivityLog::log(action, desc, $user, $metadata, $severity, $category)`.

KPI consumer: `UserProductivityDaily.listings_published_ebay` (from `listing.ebay.published`).

Spec: `docs/AUDIT_SYSTEM.md`. Enforced rule: `.claude/rules/audit-logging.md`. Tests: `use Tests\Support\AssertsAudits;` then `$this->assertAudited('action', $user, [...])`.
