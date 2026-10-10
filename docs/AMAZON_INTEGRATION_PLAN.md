# Amazon SP-API Marketplace Adapter — Implementation Plan

**Status**: Draft
**Owner**: TBD
**Goal**: Feature parity with eBay and Shopify integrations — list, sync inventory, sync prices, import orders, push tracking, and receive buyer messages on Amazon.

---

## Current State Discovery

A surprising amount of Amazon scaffolding is already in place. Inventory before planning new work:

### Already built (Amazon-side)

- **Models**: `app/Models/Amazon/{AmazonListing,AmazonFeed,AmazonSyncLog}.php` and migrations
- **Services**: full `app/Services/Amazon/` directory:
  - `AmazonBaseService` — base HTTP client
  - `AmazonOAuthService` — LWA flow
  - `AmazonListingsService` — Listings Items API 2021-08-01 PUT/PATCH/DELETE
  - `AmazonInventoryService` — FBM patch + FBA summary
  - `AmazonPricingService`
  - `AmazonOrderService`
  - `AmazonFeedService` — `POST_ORDER_FULFILLMENT_DATA`
  - `AmazonReportsService` — `GET_MERCHANT_LISTINGS_ALL_DATA` TSV
  - `AmazonStoreImportService`
  - `AmazonCatalogService`
  - `AmazonRateLimiter`
  - `AmazonConfigService`
  - `AmazonApiException`
- **Adapter**: `AmazonMarketplaceAdapter.php` implements `App\Contracts\MarketplaceAdapter`
- **Order sync**: `AmazonOrderSyncService.php` — full flow incl. `matchOrderItemsToProducts`, `reserveInventoryForOrder` -> dispatches `PushInventoryToMarketplaces`, `acknowledgeOrder`, `uploadTracking` via Feed
- **Jobs**: `Jobs/Fulfillment/{SyncAmazonOrders,UploadTrackingToAmazon}.php`, `Jobs/Amazon/ProcessAmazonNotification.php`
- **Webhook**: `AmazonNotificationController.php` with full SNS signature verification, `SubscriptionConfirmation`, `Notification` handling, dispatches `ProcessAmazonNotification`
- **Routes**: `POST /api/webhooks/amazon` registered in `routes/api.php`
- **Cron**: `Schedule::job(new SyncAmazonOrders)` already in `routes/console.php`
- **Hooks**:
  - `IntegrationCredential::getActiveAmazonToken()` exists
  - `MarketplaceMessage::MARKETPLACE_AMAZON='amazon'` constant exists
  - `FeeTrackingService::extractAmazonFees()` exists
  - `TrackingService` already routes Amazon tracking to `UploadTrackingToAmazon`
  - `OrderSyncService` already case-handles `'amazon'`
  - Admin filter `whereIn('platform',['ebay','shopify','amazon'])` in `routes/api.php`

### Missing gaps (the actual work)

1. **`config/marketplaces.php`** — Amazon entry not registered. Without this, `MarketplaceRegistry` can't resolve the adapter, so listing/price/inventory sync to Amazon **silently never fires**. Single highest-impact gap.
2. **`IntegrationCredential::isAmazonReady()`**, `amazonListings()` HasMany, `getAmazonAuthUrl()` — adapter calls `$this->credential->isAmazonReady()` which doesn't exist. Currently `'amazon' => null` at line 191 of `IntegrationCredential.php`.
3. **`Product::amazonListings()`** relationship — adapter calls `$product->amazonListings()` (line 115 of adapter); not defined on `Product.php`.
4. **`config/services.php`** — no `'amazon'` block. `AmazonConfigService` reads `config('services.amazon.lwa_client_id')` etc. that resolve to null. No sandbox toggle.
5. **OAuth controller** — no `AmazonOAuthController` to handle `/amazon/auth/install`, `/amazon/auth/callback`. The `redirect_uri` defaults to `/amazon/auth/callback` but no route exists.
6. **Marketplace controller** for connection / dashboard / settings UI: missing. Compare to `ShopifyMarketplaceController` (~10 endpoints).
7. **Routes**: no `routes/amazon.php` registered in `bootstrap/app.php`. `routes/shopify.php` is the model.
8. **Frontend**: no `resources/js/Pages/Amazon/` (Settings, Products, SyncLogs, Match) or `resources/js/Pages/Marketplaces/Amazon/` (Index, Setup, Listings, Logs).
9. **SQS poller**: `AmazonNotificationController` accepts SNS HTTPS push, but Amazon's recommended notification path is **SQS**. Either confirm SNS-HTTPS is acceptable, or add an SQS poller worker.
10. **Buyer messages**: `MessageSyncService` only loops eBay credentials; no `AmazonMessageService` exists. `MARKETPLACE_AMAZON` constant exists but nothing populates it.
11. **Repricing**: `RepricingEngine` has no Amazon-aware path. `AmazonPricingService::updatePrice` exists, plus `getCompetitivePricing`, but they're not wired into the rules engine.
12. **Sync iteration**: confirm `MarketplaceListingSync` / `MarketplacePriceSync` iterate adapters from `MarketplaceRegistry` and don't have Amazon-skipping branches.
13. **Adapter contract**: `AmazonMarketplaceAdapter` is missing `getCredential()` (used by `MarketplaceInventorySync` line 30) — must add to ensure runtime doesn't break.
14. **Adapter `createListing()`**: missing from the target interface in the SKILL doc (lines 148-157). The contract `app/Contracts/MarketplaceAdapter.php` doesn't require it, but a consistent listing creation path is needed.
15. **No `AmazonOrder` table** — design uses generic `orders.marketplace='amazon'`. Confirm intentional (it appears to be: `Order::withoutGlobalScopes()->where('marketplace_order_id', ...)` is used). No separate model needed.
16. **No `ProcessAmazonScheduledTasks` job** for periodic FBA inventory pull or stale listing resync.
17. **Customer matcher gap**: `AmazonOrderSyncService::transformOrderData` writes `buyer_email` from `BuyerInfo.BuyerEmail`, which is now an anonymized Amazon address. Customer matcher will treat each Amazon buyer as unique. Known Amazon constraint to call out.

---

## Phase 0 — Research and Decisions (no code)

**Goal**: Lock down architectural choices before writing anything.

### Open questions for the user (must answer to proceed)

| Question | Why it matters |
|---|---|
| Which marketplaces (regions): just `ATVPDKIKX0DER` (US) or also CA / UK / EU / JP? | Affects endpoint selection (`sellingpartnerapi-na/eu/fe`), per-region OAuth approval, currency handling. Existing `AmazonConfigService::getSpApiEndpoint()` is single-region. |
| FBM only, FBA only, or both? | Adapter already branches on `isFba()`. FBA inventory **must not** be pushed (`updateQuantity` early-returns for FBA), and FBA orders skip pick/pack. Confirm we want FBA visibility (read-only). |
| SQS-pull vs. SNS-push for notifications? | SP-API offers both via the `Notifications` API. Existing controller is SNS-HTTPS. SQS is more reliable but requires adding an SQS queue + IAM role + a poller job. Recommend: keep SNS-HTTPS for now (already built), add SQS later if reliability issues. |
| Brand Registry available? | New ASIN creation requires Brand Registry for branded products. Without it, sellers can only list against existing ASINs. Affects `AmazonCatalogService` UX (search-only vs. create-allowed). |
| Does this org need ASIN creation, or only matching to existing ASINs? | If only matching: simpler — `productType=PRODUCT` placeholder works, listings post against existing catalog. If creating: must ship a per-category attribute schema fetcher (via `Definitions` API: `productTypes`/`schema/productTypes/{type}`). |
| Use a community SP-API SDK (e.g., `jlevers/selling-partner-api`) or stay with hand-rolled `Http::` calls? | Current code is hand-rolled. SDK adds 10MB of code but covers retries, rate limits, and signed-URL feed uploads. Recommend: stay hand-rolled — pattern matches eBay/Shopify and signing burden is gone post-LWA-only. |
| AWS sig v4 / IAM role assumption? | **Already gone.** SP-API became LWA-only on 2023-10-15. Existing `AmazonOAuthService` correctly uses `x-amz-access-token` only. No STS, no IAM role, no AWS SigV4 needed. |

**Testable milestone**: Decisions documented, sign-off from product owner.

---

## Phase 1 — Wiring Existing Scaffolding (Highest ROI, ~1 day)

**Goal**: Make what's already built actually run, end-to-end, against sandbox.

### Files to create/modify

1. **`config/marketplaces.php`** — add `amazon` entry mirroring eBay/Shopify:
   ```php
   'amazon' => [
       'adapter' => \App\Services\Amazon\AmazonMarketplaceAdapter::class,
       'credential_type' => 'amazon',
       'listing_model' => \App\Models\Amazon\AmazonListing::class,
       'label' => 'Amazon',
       'supports_webhooks' => true,
       'active_status' => 'active',
   ],
   ```
2. **`config/services.php`** — add `amazon` block parallel to `shopify`: `lwa_client_id`, `lwa_client_secret`, `marketplace_id`, `region`, `sandbox` { endpoint, marketplace_id }, `production` { ... }, `use_sandbox => env('AMAZON_USE_SANDBOX', env('APP_ENV') !== 'production')`.
3. **`app/Services/Amazon/AmazonConfigService.php`** — extend to read sandbox vs. production block (add `getEnvironment()`, `isSandbox()`, branch `getSpApiEndpoint()` to return sandbox host `sandbox.sellingpartnerapi-na.amazon.com` when sandbox).
4. **`app/Models/Catalog/Product.php`** — add `amazonListings(): HasMany` and `amazonListingFor($credentialId)` accessors mirroring `ebayListings()`.
5. **`app/Models/Integration/IntegrationCredential.php`** — add:
   - `amazonListings(): HasMany`
   - `isAmazonReady(): bool` — checks `platform=='amazon'`, `is_active`, valid token, `amazon_seller_id` in config, `amazon_marketplace_id`
   - `getAmazonAuthUrl()` — calls `AmazonOAuthService::getAuthorizationUrl`
   - Update `getAuthorizationUrl()` switch at line 189-194 to dispatch Amazon
6. **`app/Services/Amazon/AmazonMarketplaceAdapter.php`** — add the missing `getCredential(): IntegrationCredential` method required by `MarketplaceInventorySync::pushQuantityToAll()` line 30.

### Key decisions / open questions

- Where to store Amazon `seller_id`? Decision: in `IntegrationCredential.config['amazon_seller_id']` (already used by services). Captured during OAuth callback via `AmazonOAuthService::getSellerInfo()`. Remember `.claude/rules/postgresql.md`: encrypted JSON fields can be null; wrap reads in try/catch.
- Encrypted-credential null safety: audit `AmazonBaseService::__construct()` — if `credential->config` is null or token absent, `$this->accessToken = ''` and every API call 401s. Add explicit guard + meaningful exception.

### Dependencies / blockers

- Amazon Developer registration (LWA app + SP-API role approval). Without LWA `client_id` / `client_secret` env vars, `isConfigured()` returns false. **Blocker for any live testing.**
- `AMAZON_LWA_CLIENT_ID`, `AMAZON_LWA_CLIENT_SECRET`, `AMAZON_REDIRECT_URI` in `.env`.

### Testable milestone

`php artisan tinker` -> `app(MarketplaceRegistry::class)->getAdaptersForOrganization($orgId)` returns the Amazon adapter when an active Amazon credential exists. Existing `PushInventoryToMarketplaces` job, when run on a product with an `AmazonListing`, attempts the SP-API patch and logs the request.

---

## Phase 2 — OAuth + Connection UI (1-2 days)

**Goal**: Let a user actually click "Connect to Amazon" and complete the LWA flow.

### Files to create/modify

- **`app/Http/Controllers/Amazon/AmazonOAuthController.php`** — `install()`, `callback()`, `disconnect()`. Modeled after `ShopifyOAuthController.php`. Callback decrypts the `state` payload, exchanges code via `AmazonOAuthService::exchangeCodeForToken`, calls `getSellerInfo()` to populate `config.amazon_seller_id` and `config.amazon_marketplace_id`, then redirects to settings page.
- **`app/Http/Controllers/Amazon/AmazonMarketplaceController.php`** — modeled on `ShopifyMarketplaceController`. Endpoints: `index` (dashboard), `products` (listing manager), `syncProduct`, `syncBulk`, `startImport`, `logs`, `categorySettings`, `toggleAutoSync`, `disconnect`.
- **`routes/amazon.php`** — new, modeled on `routes/shopify.php`. Public webhook stays in `routes/api.php`. Authenticated routes: `/amazon/auth/install`, `/amazon/auth/callback`, `/amazon/auth/disconnect`, `/marketplaces/amazon/*`.
- **`bootstrap/app.php`** — register `routes/amazon.php` alongside line 16's `routes/shopify.php`.
- **`resources/js/Pages/Marketplaces/Amazon/Index.vue`** — connection status, listing/order counts, sync button. Mirror `Marketplaces/Ebay/Index.vue`.
- **`resources/js/Pages/Marketplaces/Amazon/Setup.vue`** — credential entry / connect button.
- **`resources/js/Pages/Amazon/Settings/Index.vue`** — auto-sync toggle, marketplace selector, FBM/FBA filter. Mirror `Pages/Shopify/Settings/Index.vue`.
- **`resources/js/Pages/Marketplaces/Index.vue`** — add Amazon tile to the marketplace hub.

### Key decisions

- Amazon's seller-app authorization comes back with both `spapi_oauth_code` and `selling_partner_id` query params (different from generic OAuth). `exchangeCodeForToken` is correctly using the `code`, but `selling_partner_id` (= seller_id) should be captured directly from the callback rather than re-fetched. Confirm the existing `getSellerInfo` call after exchange is sufficient.
- Hybrid app vs. Self-authorized app: a self-auth app skips the LWA consent screen for the seller's own use. For multi-tenant SaaS we **must** ship as a public Hybrid app — affects approval timeline (2-6 weeks). Document this.

### Dependencies / blockers

- Amazon SP-API app must be approved by Amazon and registered as a public app to allow third-party seller authorization.
- `AMAZON_REDIRECT_URI` must be HTTPS and pre-registered in Seller Central app settings.

### Testable milestone

Seller can click "Connect Amazon" -> consent at `sellercentral.amazon.com` -> redirected back -> `IntegrationCredential` and `OAuthToken` rows persisted -> `verifyCredentials()` (which calls `/sellers/v1/marketplaceParticipations`) returns true.

---

## Phase 3 — Sandbox Mode (0.5 day)

**Goal**: Plumb Amazon into the existing `APP_ENV=staging`-driven sandbox switchover.

### Files to create/modify

- **`config/services.php`** — `'amazon' => [..., 'sandbox' => ['endpoint' => 'https://sandbox.sellingpartnerapi-na.amazon.com', 'marketplace_id' => env('AMAZON_SANDBOX_MARKETPLACE_ID', 'ATVPDKIKX0DER')], 'production' => [...], 'use_sandbox' => env('AMAZON_USE_SANDBOX', env('APP_ENV') !== 'production')]`.
- **`app/Services/Amazon/AmazonConfigService.php`** — `getSpApiEndpoint()` reads the active block.
- **`app/Console/Commands/Sandbox/SandboxStatusCommand.php`** — extend the `SandboxStatus` artisan command (already shows eBay + Shopify per line 17) to include Amazon row.
- **`docs/AMAZON_SANDBOX_TESTING.md`** — new, mirroring `EBAY_SANDBOX_TESTING.md` and `SHOPIFY_SANDBOX_TESTING.md`. Document the SP-API sandbox quirks: returns hard-coded responses for known SKUs, no real listing creation, useful primarily for request-shape validation.

### Key decisions

- Amazon's sandbox is **dynamic** for some endpoints (Reports, Feeds simulate processing) and **static** for others (Catalog returns canned data). Document which paths are mockable.

### Testable milestone

With `APP_ENV=staging`, `php artisan sandbox:status` shows `Amazon: sandbox` and `AmazonOAuthService::verifyCredentials` hits the sandbox endpoint.

---

## Phase 4 — Listing Creation Path (2-3 days)

**Goal**: Deliver feature parity with `CreateEbayListingJob` / `SyncProductToShopify` for Amazon.

### Files to create/modify

- **`app/Services/Amazon/AmazonCatalogService.php`** — extend (currently 110 lines). Add `searchByGtin($upc/ean)`, `searchByKeywords($name)`, returning ASIN candidates with confidence scores. Wire into `MATCH_SOURCE_GTIN` / `MATCH_SOURCE_SEARCH` constants on `AmazonListing`.
- **`app/Services/Amazon/AmazonListingsService.php`** — already has `createListing` and `updateListing`. Add `getProductTypeDefinition($productType, $marketplaceId)` (calls `/definitions/2020-09-01/productTypes/{productType}`) to fetch the per-category attribute schema, cached in Redis 24h.
- **`app/Jobs/Amazon/CreateAmazonListingJob.php`** — new. Mirror `app/Jobs/CreateEbayListingJob.php`. Resolves ASIN match (existing or via `AmazonCatalogService`), creates `AmazonListing` row with `MATCH_SOURCE_*`, calls `AmazonListingsService::createListing()`.
- **`app/Services/Amazon/AmazonMarketplaceAdapter.php`** — add `createListing(Product $product): MarketplaceListingResult` (matches the SKILL.md target interface). Adapter dispatches the job, returns immediately with a pending result; webhook/poll updates final status.
- **`app/Contracts/MarketplaceAdapter.php`** — add `createListing` to the interface (or keep optional via `method_exists`).
- **`app/Services/Marketplace/CrossListingService.php`** — extend to support `amazon` target (currently eBay + Shopify).
- **`app/Services/Marketplace/ListingDataTransformer.php`** — add Amazon attribute mapping (item specifics -> Amazon `attributes` JSON; condition -> `condition_type` codes already mapped in `AmazonListingsService::mapCondition`).

### Key decisions

- ASIN matching: 3-tier waterfall — UPC/EAN (high confidence), title fuzzy match (medium), user-manual ASIN entry (override). Mirror `ProductMatchingService` waterfall for cross-marketplace match.
- `productType` resolution: Amazon requires a *valid* productType per category for new listings. For matching to existing ASINs, `PRODUCT` works. For new ASINs, must call `getProductTypeDefinition` first. Decision impacts UX: ship a "category picker" (slow) or "auto-detect from existing ASIN" (fast, requires match-first flow).
- Multi-image: Amazon takes up to 9 images via `main_product_image_locator` + `other_product_image_locator_1..8`. Current `toAmazonListingData` doesn't include images. Add.

### Material differences from eBay/Shopify

- **24-hour ASIN propagation**: A successful `createListing` call returns `ACCEPTED` immediately but the listing is not browsable for up to 24h. UI must show "Pending publication" state. Existing `AmazonListing::STATUS_CREATED` -> `STATUS_ACTIVE` transition should be triggered by SNS `LISTINGS_ITEM_STATUS_CHANGE` notification, not assumed on creation.
- **Brand Registry gate**: New ASIN creation for branded products requires the seller to be enrolled. Show a UI warning and pre-flight check via `AmazonCatalogService` before letting the user attempt brand-name listings.
- **No "End Listing"**: Amazon doesn't expose a "remove listing" — the operation is `DELETE /listings/items/.../{sku}` which removes the offer, not the ASIN. Existing `deleteListing` is correct, but reword UI from "End" to "Remove offer".

### Testable milestone

From a Product detail page, clicking "List on Amazon" creates an `AmazonListing` row with `status=created` and dispatches the SP-API call. Sandbox returns `ACCEPTED`. UI shows "Pending publication".

---

## Phase 5 — Inventory + Price Sync (Mostly Wired) (0.5 day)

**Goal**: Close any gaps in `MarketplaceInventorySync` / `MarketplacePriceSync` / `MarketplaceListingSync` for Amazon.

### Files to verify/modify

- **`app/Services/MarketplaceInventorySync.php`** — already iterates all adapters generically. **No code change** if Phase 1 wires the registry. Verify that FBA listings short-circuit (`AmazonMarketplaceAdapter::updateQuantity` returns true for FBA — correct, since FBA is Amazon-authoritative).
- **`app/Services/MarketplaceListingSync.php`** — verify hash-based change detection works against `AmazonListing.product_data_hash` (model already has `generateProductHash()` and `hasProductChanged()`).
- **`app/Services/MarketplacePriceSync.php`** — verify it calls `adapter->updatePrice($product, $listing)`, which routes through `AmazonPricingService::updatePrice` (FBA) or `AmazonListingsService::updateListing` (FBM with `force=true`).
- **`app/Jobs/Amazon/PullFbaInventoryJob.php`** — new. Periodically calls `AmazonInventoryService::getFbaInventory()` and writes results to `AmazonListing.fba_quantity`. FBA inventory is *read-only* from our side — Amazon is source of truth. Schedule hourly in `routes/console.php`.

### Key decisions

- For FBM, when `available_quantity` changes, push immediately (already wired via `ProductObserver` -> `PushInventoryToMarketplaces`).
- For FBA, never push outbound. Display `fba_quantity` in UI separately. Document that bundles/part-out involving FBA-only products will not work.

### Material difference

Amazon SP-API `Listings Items PATCH` for `fulfillment_availability` is rate-limited at 5 req/sec. `AmazonRateLimiter` exists. Verify burst protection.

### Testable milestone

Decrement `Product.available_quantity` -> `UpdateAmazonInventory`-style call hits sandbox PATCH endpoint -> sync log row recorded.

---

## Phase 6 — Order Sync + Tracking (Mostly Wired) (0.5 day)

**Goal**: Close gaps in already-implemented order sync flow.

### Files to verify/modify

- **`app/Services/Fulfillment/AmazonOrderSyncService.php`** — already complete for poll path. Verify:
  - `transformOrderData` extracts `marketplace_fees` (currently 0 — needs `Financial Events` API call to get fees, or extract from settlement reports)
  - `matchOrderItemsToProducts` uses `MarketplaceProductMatch` table for cross-marketplace dedup (currently only checks `AmazonListing` and `Product.sku`)
  - Customer matching: integrate with `App\Services\Customer\CustomerMatchingService` (analog of how Ebay/Shopify do it). **Caveat**: Amazon `BuyerEmail` is anonymized (`abc123@marketplace.amazon.com`); use `BuyerInfo.BuyerName` + ship address as match key.
- **`app/Services/Marketplace/FeeTrackingService::extractAmazonFees`** — verify it pulls `referral`, `fba`, `closing`, `shipping` per the SKILL doc fee types, from the `OrderTotal` and `OrderItems[].FeeDetails`.
- **`app/Jobs/Amazon/ProcessAmazonNotification.php`** — already handles `ORDER_CHANGE` and `ORDER_STATUS_CHANGE`. Add: `LISTINGS_ITEM_STATUS_CHANGE` (transition pending -> active), `MFN_ORDER_STATUS_CHANGE`, `B2B_ANY_OFFER_CHANGED` (for repricing).
- **`app/Jobs/Fulfillment/UploadTrackingToAmazon.php`** — already exists. Verify it calls `AmazonOrderSyncService::uploadTracking` which submits `POST_ORDER_FULFILLMENT_DATA` feed.

### Key decisions

- Should we enable the Amazon Notifications API subscription programmatically per credential (via `Notifications` API `createSubscription`), or assume manual setup once per app? Recommend: programmatic, on OAuth callback completion. New job: `RegisterAmazonNotifications`.

### Testable milestone

Place a sandbox order -> SNS pushes to `/api/webhooks/amazon` -> `ProcessAmazonNotification` dispatched -> `AmazonOrderSyncService::syncOrders` imports it -> `Order.marketplace='amazon'` row created with items -> `Product.available_quantity` decremented -> `PushInventoryToMarketplaces` dispatched (which then pushes to eBay + Shopify).

---

## Phase 7 — Buyer Messages (1.5 days)

**Goal**: Plug Amazon Buyer-Seller Messaging into the existing `MarketplaceMessage` abstraction.

### Files to create/modify

- **`app/Services/Communication/AmazonMessageService.php`** — new, modeled on `EbayMessageService`. Wraps SP-API `Messaging API v1`:
  - `getMessagingActionsForOrder($amazonOrderId)` returns allowed message types per order (Amazon restricts what you can send: `confirmCustomizationDetails`, `legalDisclosure`, `negativeFeedbackRemoval`, `confirmDeliveryDetails`, `unexpectedProblem`, `warranty`, plus `Solicitations` for review requests).
  - `sendMessage($amazonOrderId, $action, $payload)` POSTs to `/messaging/v1/orders/{amazonOrderId}/messages/{action}`.
  - `fetchMessages($since)` — **note**: Amazon does not offer a direct inbound-message inbox API. Inbound buyer messages arrive via email to the seller's notification address. Workaround: ingest via Reports API report `GET_FBA_FULFILLMENT_CUSTOMER_RETURNS_DATA` for return-related messages, plus require the seller to forward Amazon emails to a dedicated webhook (or use a Gmail integration — note: this org has Gmail MCP available).
- **`app/Services/Communication/MessageSyncService.php`** — currently only loops eBay credentials. Refactor to also iterate Amazon credentials and dispatch to `AmazonMessageService`. Same pattern as `syncEbayMessages`.
- **`app/Models/Communication/MarketplaceMessage.php`** — `MARKETPLACE_AMAZON='amazon'` constant already exists. No model change.
- **Frontend**: `resources/js/Pages/Communication/Inbox.vue` — verify the Amazon filter chip works (it should, since the model just uses the marketplace string).
- **`app/Jobs/Communication/SyncAmazonMessages.php`** — periodic message sync.
- **`routes/console.php`** — schedule job hourly.

### Key decisions

- Inbound messaging strategy. Recommend: **Phase 7a** = outbound (`Messaging` + `Solicitations` API) only. **Phase 7b** = research email-bridge for inbound. Outbound covers most legal-required communications and the "request a review" flow that's high-value for sellers.

### Material difference from eBay/Shopify

eBay has a true bidirectional Messages API. Shopify has notification emails only. Amazon has *outbound* programmatic + *inbound* email-only. UI must communicate this clearly: "Inbound buyer messages must be checked in Amazon Seller Central."

### Testable milestone

Send a "request review" via Solicitations API in sandbox; row appears in `marketplace_messages` with `marketplace='amazon'`, `direction='outbound'`, `message_type='solicitation'`.

---

## Phase 8 — Repricing (1 day)

**Goal**: Let existing `RepricingRule` engine target Amazon listings.

### Files to verify/modify

- **`app/Services/Pricing/RepricingEngine.php`** — verify it iterates listings across all marketplaces (Ebay, Shopify, Amazon) by joining on Product. If hard-coded to eBay, generalize to call `MarketplaceRegistry::getAdapters()` and `$adapter->updatePrice($product, $listing)`.
- **`app/Services/Amazon/AmazonPricingService.php`** — already has `getCompetitivePricing(array $asins)`. Add `getBuyBoxOwner($asin)` and `getOfferList($asin)` for "match buy box" repricing strategies.
- **`app/Models/Pricing/RepricingRule.php`** — verify the `target_marketplace` enum allows `'amazon'`.
- **`app/Jobs/Pricing/UpdateAmazonBuyBoxPrices.php`** — new. Periodic job that pulls competitive pricing for all active Amazon listings and updates `AmazonListing.buy_box_price`. Used by repricing rules with strategy `match_buy_box` or `beat_buy_box_by_X`.

### Key decisions

- Buy-box-aware strategies are the killer Amazon feature. Recommend: ship `match_buy_box`, `beat_lowest_competitor`, `match_lowest_fbm` as Amazon-specific strategy types.
- Min/max price guards: existing `AmazonListing.minimum_price` / `maximum_price` columns already exist — `AmazonPricingService::updatePrice` already accepts them. Wire UI input.

### Testable milestone

Create a repricing rule with target `amazon`, strategy `match_buy_box`. After `UpdateAmazonBuyBoxPrices` runs, listing prices update via `AmazonPricingService::updatePrice` and stay within min/max bounds.

---

## Phase 9 — Notifications Subscription Management (0.5 day)

**Goal**: Programmatically register the seller's account to push events to our SNS endpoint.

### Files to create

- **`app/Services/Amazon/AmazonNotificationsService.php`** — new. Wraps SP-API `Notifications` API:
  - `createDestination($sqsArnOrSnsArn)` (one-time per app)
  - `createSubscription($notificationType, $destinationId)` per credential, for: `ORDER_CHANGE`, `ORDER_STATUS_CHANGE`, `LISTINGS_ITEM_STATUS_CHANGE`, `MFN_ORDER_STATUS_CHANGE`, `ANY_OFFER_CHANGED`, `B2B_ANY_OFFER_CHANGED`, `FEE_PROMOTION`, `FBA_INVENTORY_AVAILABILITY_CHANGES`.
- **`app/Jobs/Amazon/RegisterAmazonNotifications.php`** — dispatched from OAuth callback after successful credential save.
- **`app/Http/Controllers/Amazon/AmazonNotificationController.php`** — existing SNS handler is good. Add idempotency check: dedupe on `MessageId` (SNS provides; persist in Redis 24h TTL).

### Key decisions

- Single SNS topic shared across all tenants vs. one per credential? Recommend single topic — destination is registered once at app level. The `findAmazonCredential()` helper at line 195 currently does `IntegrationCredential::where('platform','amazon')->where('is_active',true)->first()` — **bug**: in multi-tenant, this picks an arbitrary credential. Replace with extracting `sellerId` from the SNS message payload and matching to `IntegrationCredential.config.amazon_seller_id`.

### Testable milestone

After OAuth completes, `aws sns list-subscriptions-by-topic` shows our endpoint subscribed for the seller's events. A test sandbox order triggers an SNS push that resolves to the *correct* tenant.

---

## Phase 10 — Frontend Polish (1-2 days)

**Goal**: User can manage Amazon end-to-end without dev help.

### Files to create

- `resources/js/Pages/Marketplaces/Amazon/Index.vue` — dashboard (listing counts, order counts, sync health)
- `resources/js/Pages/Marketplaces/Amazon/Setup.vue` — connect-to-Amazon button, marketplace selector, post-OAuth setup wizard
- `resources/js/Pages/Marketplaces/Amazon/Listings.vue` — listing manager analog of `Marketplaces/Ebay/Listings.vue`
- `resources/js/Pages/Marketplaces/Amazon/Import.vue` — bulk import existing Amazon listings via `AmazonStoreImportService`
- `resources/js/Pages/Marketplaces/Amazon/Logs.vue` — `AmazonSyncLog` viewer with filters
- `resources/js/Pages/Amazon/Match/Review.vue` — ASIN match review (analog of `Pages/Shopify/Match/Review.vue`)
- `resources/js/Pages/Amazon/Settings/Index.vue` — auto-sync toggle, FBA filter, marketplace overrides
- Update `resources/js/Pages/Marketplaces/Index.vue` — add Amazon tile with status indicator
- Update `resources/js/Pages/Fulfillment/Orders.vue` — add Amazon filter chip (model already supports `marketplace='amazon'`)
- Update `resources/js/Layouts/AppLayout.vue` sidebar — add Amazon nav item

### Testable milestone

Manual feature test plan covers connect -> import -> create listing -> receive order -> ship + upload tracking -> reprice, all from the UI.

---

## Phase 11 — Testing + Manual QA (1 day)

**Goal**: Sandbox-driven feature parity tests.

### Files to create

- `tests/Feature/Amazon/AmazonOAuthTest.php` — install/callback flow with mocked LWA token endpoint
- `tests/Feature/Amazon/AmazonListingSyncTest.php` — push listing, push price, push inventory using `Http::fake()` against sandbox endpoint patterns
- `tests/Feature/Amazon/AmazonOrderSyncTest.php` — fixture-driven order import with `matchOrderItemsToProducts` assertions, `available_quantity` decrement, `PushInventoryToMarketplaces` dispatch assertion
- `tests/Feature/Amazon/AmazonNotificationTest.php` — SNS subscription confirmation + signature verification (good news: signature verification is already implemented)
- `tests/Unit/Amazon/AmazonRateLimiterTest.php`
- `docs/AMAZON_INTEGRATION.md` — comprehensive doc parallel to `docs/SHOPIFY_INTEGRATION.md`
- `.claude/skills/amazon-integration/SKILL.md` — new skill doc parallel to `ebay-integration` and `shopify-integration`

### Add to manual feature testing plan

- Connect/disconnect cycle
- Import 10 existing listings
- Create new listing matched to existing ASIN
- Place sandbox order -> verify inventory decrement on eBay, Shopify, Amazon
- Ship order -> verify tracking uploaded
- Reprice rule -> verify price update
- FBA inventory pull -> verify `fba_quantity` populated, no outbound push
- Brand-Registry-gated ASIN creation -> verify graceful failure
- Sandbox->production cutover -> verify env switching

### Testable milestone

Full feature test plan green in `staging` against SP-API sandbox.

---

## Phase 12 — Production Cutover Checklist (0.5 day)

**Goal**: Ready for first paying customer.

### Items

- Amazon Developer Account: app moved from "Draft" to "Published" status
- LWA app reviewed and approved by Amazon (timeline: 2-6 weeks)
- Production env vars set: `AMAZON_LWA_CLIENT_ID`, `AMAZON_LWA_CLIENT_SECRET`, `AMAZON_REDIRECT_URI`, `AMAZON_USE_SANDBOX=false`
- SNS topic created in AWS, endpoint subscribed and confirmed
- Notification subscriptions for at least one production seller verified end-to-end
- Rate limit config tuned per Amazon's published limits per endpoint family
- Error monitoring: alert on `AmazonApiException` rate spikes, on `consecutive_errors >= 5` listing flags
- Documentation links in admin panel

### Testable milestone

First production seller connects, lists, syncs, and ships an Amazon order without dev intervention.

---

## Items Materially Different from eBay/Shopify

These are not workitems — guardrails to keep in mind throughout:

| Behavior | Amazon | eBay/Shopify |
|---|---|---|
| Listing creation | Async, 24h ASIN propagation, returns `ACCEPTED` not "live" | Immediate |
| New listing requires Brand Registry | Yes (for branded) | No |
| ASIN catalog is shared/competitive | Yes — multiple sellers, same ASIN, buy-box | No (each seller's listing is unique) |
| Inventory authority for FBA | Amazon (read-only for us) | We are authoritative |
| Tracking upload | Async via Feeds API (XML/JSON feed) | Synchronous API |
| Buyer message inbound | Email-only, no API | API-based |
| Buyer email | Anonymized | Real |
| Webhook model | SNS push or SQS pull | HTTP webhook (Shopify) / ECN HTTP (eBay) |
| OAuth | LWA only (since 2023-10-15), no AWS sigv4 | OAuth2 |
| Rate limits | Per-endpoint, very granular, dynamic via response headers | Fixed daily quota |
| Fee structure | Referral % + FBA + closing + shipping | Final value + insertion + payment processing |
| SKU vs. ASIN | SKU is seller-side identifier, ASIN is Amazon's product identifier; both exist on every listing | Single listing ID |

---

## Critical Files for Implementation

- `config/marketplaces.php`
- `config/services.php`
- `app/Models/Integration/IntegrationCredential.php`
- `app/Models/Catalog/Product.php`
- `app/Services/Amazon/AmazonMarketplaceAdapter.php`
- `app/Services/Amazon/AmazonConfigService.php`
- `app/Http/Controllers/Amazon/AmazonNotificationController.php`
- `routes/amazon.php` (new)
- `bootstrap/app.php`

---

## Effort Summary

| Phase | Effort |
|---|---|
| 0 — Research and decisions | — |
| 1 — Wiring | ~1 day |
| 2 — OAuth + connection UI | 1-2 days |
| 3 — Sandbox mode | 0.5 day |
| 4 — Listing creation | 2-3 days |
| 5 — Inventory + price sync | 0.5 day |
| 6 — Order sync + tracking | 0.5 day |
| 7 — Buyer messages | 1.5 days |
| 8 — Repricing | 1 day |
| 9 — Notifications subscription | 0.5 day |
| 10 — Frontend polish | 1-2 days |
| 11 — Testing | 1 day |
| 12 — Production cutover | 0.5 day |
| **Total** | **~10-13 dev days** + Amazon app approval (2-6 wk) |
