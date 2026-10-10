# Listing Management

How products are listed across marketplaces through scheduled listings, cross-listing with data transformation, and eBay Promoted Listings advertising.

## Overview

```
PRODUCT CATALOG
       |
       +-- SCHEDULED LISTING -------> Queue a listing for future publish
       |       |
       |       v
       |   SCHEDULER SERVICE -------> Process due listings, dispatch jobs
       |       |
       |       v
       |   MARKETPLACE JOB ---------> CreateEbayListingJob (or Shopify equivalent)
       |
       +-- CROSS-LISTING -----------> Copy listing from one marketplace to another
       |       |
       |       v
       |   DATA TRANSFORMER --------> Map fields between eBay <-> Shopify formats
       |       |
       |       v
       |   TARGET LISTING ----------> Create listing on the target marketplace
       |
       +-- PROMOTED LISTINGS -------> eBay advertising campaigns
               |
               v
           CAMPAIGN + AD RATE ------> Track impressions, clicks, sales, ROAS
```

---

## Architecture

### Key Files

| Layer | File |
|-------|------|
| Model | `app/Models/Marketplace/ScheduledListing.php` |
| Model | `app/Models/Ebay/EbayPromotedCampaign.php` |
| Model | `app/Models/Ebay/EbayPromotedListing.php` |
| Service | `app/Services/Marketplace/ListingSchedulerService.php` |
| Service | `app/Services/Marketplace/CrossListingService.php` |
| Service | `app/Services/Marketplace/ListingDataTransformer.php` |
| Job | `app/Jobs/CreateEbayListingJob.php` |
| Job | `app/Jobs/Marketplace/ProcessScheduledListings.php` |
| Controller | `app/Http/Controllers/Marketplace/` |
| Controller | `app/Http/Controllers/Ebay/` |
| Routes | `routes/web.php` (marketplace routes) |
| Pages | `resources/js/Pages/Marketplace/` |
| Pages | `resources/js/Pages/Ebay/` |

---

## Data Model

### ScheduledListing

| Field | Type | Description |
|-------|------|-------------|
| `id` | UUID | Primary key |
| `organization_id` | UUID | Owning organization |
| `product_id` | UUID | Product to list |
| `marketplace` | string | Target marketplace (ebay, shopify) |
| `integration_credential_id` | UUID | Which marketplace account to use |
| `scheduled_at` | datetime | When to publish the listing |
| `status` | string | pending, processing, published, failed, cancelled |
| `listing_data` | JSON | Snapshot of listing configuration at schedule time |
| `error_message` | string | Error details if failed |
| `published_at` | datetime | When actually published |
| `created_by` | int | User who scheduled it |

### EbayPromotedCampaign

| Field | Type | Description |
|-------|------|-------------|
| `id` | UUID | Primary key |
| `organization_id` | UUID | Owning organization |
| `integration_credential_id` | UUID | eBay account |
| `ebay_campaign_id` | string | eBay's campaign identifier |
| `name` | string | Campaign display name |
| `status` | string | active, paused, ended |
| `campaign_type` | string | standard or advanced |
| `budget_daily` | decimal | Daily spend limit |
| `budget_total` | decimal | Total campaign budget |
| `start_date` | date | Campaign start |
| `end_date` | date | Campaign end |

### EbayPromotedListing

| Field | Type | Description |
|-------|------|-------------|
| `id` | UUID | Primary key |
| `campaign_id` | UUID | Parent campaign |
| `ebay_listing_id` | UUID | The eBay listing being promoted |
| `product_id` | UUID | Linked product |
| `ad_rate_percentage` | decimal | Ad rate (percentage of sale price paid as ad fee) |
| `status` | string | active, paused, etc. |
| `impressions` | int | Total ad impressions |
| `clicks` | int | Total ad clicks |
| `sales_count` | int | Attributed sales count |
| `sales_amount` | decimal | Attributed sales revenue |
| `ad_fees` | decimal | Total ad fees paid |
| `roas` | decimal | Return on ad spend (sales_amount / ad_fees) |
| `last_synced_at` | datetime | Last stats sync from eBay |

---

## Key Flows

### 1. Scheduled Listing Lifecycle

**Create Schedule:**
1. User selects a product, marketplace, account, and future date/time
2. `ListingSchedulerService::schedule()` creates a `ScheduledListing` with status `pending`
3. Listing configuration is captured in `listing_data` as a JSON snapshot

**Process Due Listings:**
1. `ListingSchedulerService::processDueListings()` queries for listings where `scheduled_at <= now()` and `status = pending`
2. Each listing transitions to `processing`
3. For eBay: `CreateEbayListingJob` is dispatched with the product, credential, and user
4. On success: status becomes `published`, `published_at` is set
5. On failure: status becomes `failed`, `error_message` captures the error
6. Non-eBay marketplaces currently return a `failed` status with a "not yet supported" message

**Cancel:**
- `ListingSchedulerService::cancel()` sets status to `cancelled`

**Upcoming View:**
- `ListingSchedulerService::getUpcoming()` returns pending listings within the next N days (default 7)

### 2. Cross-Listing Data Transformation

`CrossListingService` handles copying a listing from one marketplace to another.

**Preview Flow:**
1. `getSourceListingData()` loads the product and its existing marketplace listing (eBay or Shopify)
2. `transformForTarget()` converts the data to the target marketplace format via `ListingDataTransformer`
3. `preview()` returns both source and target data for user review before execution

**Execution Flow:**
1. `execute()` receives the finalized listing data and target marketplace
2. For eBay targets: creates a pending `EbayListing` record, dispatches `CreateEbayListingJob`
3. For Shopify targets: creates a pending `ShopifyListing` record (Shopify create job pending implementation)

**Data Transformation (eBay to Shopify):**

| eBay Field | Shopify Field | Notes |
|------------|--------------|-------|
| `title` / `name` | `title` | Direct mapping |
| `description` | `body_html` | eBay description becomes Shopify HTML body |
| `listed_price` | `price` | Selling price |
| `msrp` | `compare_at_price` | MSRP becomes compare-at for sale display |
| `sku` | `sku` | Direct mapping |
| `upc` / `ean` / `barcode` | `barcode` | First available identifier |
| `available_quantity` | `inventory_quantity` | Stock level |
| `brand` | `vendor` | Brand name |
| `ebay_category_name` + `condition` | `product_type` | Category with condition suffix for non-new items |
| `condition` + `brand` | `tags` | Auto-generated tags: condition, brand, "cross-listed", "source:ebay" |
| images | `images` | Normalized to URL array |

**Data Transformation (Shopify to eBay):**

| Shopify Field | eBay Field | Notes |
|--------------|------------|-------|
| `title` / `name` | `title` | Direct mapping |
| `body_html` | `description` | HTML body becomes eBay description |
| `price` | `listed_price` | Selling price |
| `sku` | `sku` | Direct mapping |
| `barcode` | `upc` | Barcode mapped to UPC |
| `inventory_quantity` | `listed_quantity` | Stock level |
| `condition` / `product_type` / `tags` | `condition` | Inferred from available data; defaults to NEW |
| -- | `listing_format` | Always `FIXED_PRICE` |
| images | `images` | Normalized to URL array |

**Condition Inference (Shopify to eBay):**
- If `product_type` or `tags` contain "used", "pre-owned": maps to `USED_EXCELLENT`
- If they contain "refurbished", "renewed": maps to `SELLER_REFURBISHED`
- Default: `NEW`

**Image Normalization:**
`ListingDataTransformer::normalizeImages()` handles multiple image formats:
- Plain URL strings pass through
- Objects with `src`, `url`, or `image_url` keys are extracted to URL strings

### 3. eBay Promoted Listings

**Campaign Structure:**
- Campaigns are either `standard` (cost-per-sale) or `advanced` (cost-per-click)
- Each campaign has a daily and/or total budget, start/end dates
- Individual listings are added to campaigns with a per-listing `ad_rate_percentage`

**Performance Tracking:**
- Stats are synced periodically from eBay via `app/Jobs/Ebay/` sync jobs
- Per-listing metrics: impressions, clicks, sales count, sales amount, ad fees
- ROAS (Return on Ad Spend) = `sales_amount / ad_fees`
- `last_synced_at` tracks when stats were last refreshed

---

## Integration Points

| System | Integration |
|--------|-------------|
| **Products** | Scheduled listings and cross-listings originate from the product catalog |
| **eBay Integration** | `CreateEbayListingJob` handles actual eBay API listing creation |
| **Shopify Integration** | Shopify listing creation uses `ShopifyListing` records (create job pending) |
| **Inventory Sync** | After listing creation, inventory quantities are synced via `MarketplaceInventorySync` |
| **Queue System** | Listing creation is dispatched as queued jobs for reliability and retry handling |
| **Scheduler** | `ProcessScheduledListings` job runs on a schedule to process due listings |
| **Promoted Listings** | Campaign stats sync jobs run periodically to update ROAS and performance metrics |
| **Fee Tracking** | Promoted listing ad fees are tracked separately from marketplace transaction fees |
