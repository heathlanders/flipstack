---
name: listing-management
description: Scheduled listing publication, cross-marketplace listing with data transformation, and eBay promoted listings campaign management with ROAS tracking
user-invocable: false
---

# Listing Management System

## Key Files

| Area | File | Purpose |
|------|------|---------|
| **Models** | `app/Models/Marketplace/ScheduledListing.php` | Scheduled listing with 5 statuses, marketplace, listing data JSON |
| | `app/Models/Marketplace/MarketplaceProductMatch.php` | Product-to-marketplace match staging (confidence, status, source data) |
| | `app/Models/Ebay/EbayPromotedCampaign.php` | Promoted listing campaign (standard/advanced), budget, date range |
| | `app/Models/Ebay/EbayPromotedListing.php` | Individual promoted listing with ad rate, impressions, clicks, ROAS |
| **Services** | `app/Services/Marketplace/ListingSchedulerService.php` | Schedule, cancel, process due listings |
| | `app/Services/Marketplace/CrossListingService.php` | Cross-list products between eBay and Shopify with data transformation |
| | `app/Services/Marketplace/ProductMatchingService.php` | Cross-marketplace product matching waterfall (SKU > UPC > title) |
| | `app/Services/Marketplace/ListingDataTransformer.php` | Field mapping between marketplace formats |
| | `app/Services/Ebay/EbayPromotedListingsService.php` | Campaign CRUD, listing management, dashboard stats |
| **Controllers** | `app/Http/Controllers/Marketplace/ScheduledListingController.php` | Scheduled listing CRUD |
| | `app/Http/Controllers/Marketplace/CrossListingController.php` | Cross-listing preview and execution |
| | `app/Http/Controllers/Ebay/PromotedListingsController.php` | Campaign management, listing add/remove, stats sync |
| **Jobs** | `app/Jobs/Marketplace/ProcessScheduledListings.php` | Processes due scheduled listings |
| | `app/Jobs/Ebay/SyncPromotedListingStats.php` | Syncs promoted listing stats from eBay API |
| **Routes** | `routes/web.php` | Routes under `marketplaces.` prefix |

## Scheduled Listings

### Status State Machine

```
PENDING -----> PROCESSING -----> PUBLISHED
                   |
                   v
                 FAILED

PENDING -----> CANCELLED (user action)
```

| Status | Value | Trigger |
|--------|-------|---------|
| `STATUS_PENDING` | `pending` | Initial state on creation |
| `STATUS_PROCESSING` | `processing` | `processDueListings()` picks it up |
| `STATUS_PUBLISHED` | `published` | Listing job dispatched successfully |
| `STATUS_FAILED` | `failed` | Error during processing |
| `STATUS_CANCELLED` | `cancelled` | `ListingSchedulerService::cancel()` |

### Scheduling Flow

1. User creates scheduled listing with `product_id`, `marketplace`, `integration_credential_id`, `scheduled_at`, and `listing_data` (JSON)
2. `ProcessScheduledListings` job (or `ListingSchedulerService::processDueListings()`) runs periodically
3. Finds all listings where `scheduled_at <= now()` AND `status = pending`
4. For each due listing:
   - Sets status to `processing`
   - For eBay: dispatches `CreateEbayListingJob`
   - For other marketplaces: marks as `failed` (not yet implemented)
   - Sets status to `published` with `published_at` timestamp on success

### Scheduled Listing Routes

| Method | URI | Route Name |
|--------|-----|------------|
| GET | `/marketplaces/scheduled-listings` | `marketplaces.scheduled-listings.index` |
| GET | `/marketplaces/scheduled-listings/create` | `marketplaces.scheduled-listings.create` |
| POST | `/marketplaces/scheduled-listings` | `marketplaces.scheduled-listings.store` |
| POST | `/marketplaces/scheduled-listings/{id}/cancel` | `marketplaces.scheduled-listings.cancel` |
| DELETE | `/marketplaces/scheduled-listings/{id}` | `marketplaces.scheduled-listings.destroy` |

## Cross-Listing

### Data Transformation

`ListingDataTransformer` maps fields between marketplace formats:

**eBay -> Shopify:**
| eBay Field | Shopify Field |
|-----------|---------------|
| `title` / `name` | `title` |
| `description` | `body_html` |
| `listed_price` / `selling_price` | `price` |
| `msrp` | `compare_at_price` |
| `upc` / `ean` / `barcode` | `barcode` |
| `available_quantity` | `inventory_quantity` |
| `brand` | `vendor` |
| eBay category + condition | `product_type` |
| condition + brand + "cross-listed" + "source:ebay" | `tags` |

**Shopify -> eBay:**
| Shopify Field | eBay Field |
|---------------|-----------|
| `title` / `name` | `title` |
| `body_html` / `description` | `description` |
| `price` / `listed_price` | `listed_price` |
| `inventory_quantity` | `listed_quantity` |
| `barcode` | `upc` |
| inferred from product_type/tags | `condition` (defaults to `NEW`) |
| -- | `listing_format` = `FIXED_PRICE` |

### Condition Inference (Shopify -> eBay)
Checks product_type and tags for keywords:
- "used", "pre-owned", "preowned" -> `USED_EXCELLENT`
- "refurbished", "renewed" -> `SELLER_REFURBISHED`
- Default -> `NEW`

### Cross-Listing Flow

1. `CrossListingController::preview()` -- loads source listing data, transforms for target, shows side-by-side preview
2. User reviews and optionally edits transformed data
3. `CrossListingController::execute()` -- creates pending listing record and dispatches appropriate job:
   - eBay target: creates `EbayListing` (pending), dispatches `CreateEbayListingJob`
   - Shopify target: creates `ShopifyListing` (pending), awaits Shopify create job

### Cross-Listing Routes

| Method | URI | Route Name |
|--------|-----|------------|
| GET | `/marketplaces/cross-list/preview` | `marketplaces.cross-list.preview` |
| POST | `/marketplaces/cross-list/execute` | `marketplaces.cross-list.execute` |
| GET | `/marketplaces/cross-list/products` | `marketplaces.cross-list.products` (JSON API) |

## eBay Promoted Listings

### Campaign Model

```
EbayPromotedCampaign {
  ebay_campaign_id: string        // eBay's campaign ID
  name: string
  status: 'active' | 'paused' | 'ended'
  campaign_type: 'standard' | 'advanced'
  budget_daily: decimal?
  budget_total: decimal?
  start_date: date
  end_date: date?
}
```

### Promoted Listing Model

```
EbayPromotedListing {
  campaign_id: FK             // parent campaign
  ebay_listing_id: FK         // linked eBay listing
  product_id: FK              // linked product
  ad_rate_percentage: decimal  // ad rate (e.g., 2.50 = 2.5%)
  status: string              // 'active' | 'removed'
  impressions: integer
  clicks: integer
  sales_count: integer
  sales_amount: decimal
  ad_fees: decimal
  roas: decimal               // return on ad spend
  last_synced_at: datetime
}
```

### Dashboard Stats

`EbayPromotedListingsService::getDashboardStats()` returns:
- `total_spend` -- sum of all ad_fees
- `total_revenue` -- sum of all sales_amount
- `overall_roas` -- total_revenue / total_spend
- `active_campaigns` -- count of active campaigns

### Promoted Listing Routes

| Method | URI | Route Name |
|--------|-----|------------|
| GET | `/marketplaces/ebay/promoted` | `marketplaces.ebay.promoted.dashboard` |
| GET | `/marketplaces/ebay/promoted/campaigns` | `marketplaces.ebay.promoted.campaigns` |
| POST | `/marketplaces/ebay/promoted/campaigns` | `marketplaces.ebay.promoted.store` |
| GET | `/marketplaces/ebay/promoted/campaigns/{campaign}` | `marketplaces.ebay.promoted.show` |
| POST | `/marketplaces/ebay/promoted/campaigns/{campaign}/pause` | `marketplaces.ebay.promoted.pause` |
| POST | `/marketplaces/ebay/promoted/campaigns/{campaign}/resume` | `marketplaces.ebay.promoted.resume` |
| POST | `/marketplaces/ebay/promoted/campaigns/{campaign}/end` | `marketplaces.ebay.promoted.end` |
| POST | `/marketplaces/ebay/promoted/campaigns/{campaign}/listings` | `marketplaces.ebay.promoted.add-listing` |
| POST | `/marketplaces/ebay/promoted/campaigns/{campaign}/sync-stats` | `marketplaces.ebay.promoted.sync-stats` |
| POST | `/marketplaces/ebay/promoted/listings/{listing}/remove` | `marketplaces.ebay.promoted.remove-listing` |

## Debugging Commands

```php
// Pending scheduled listings
ScheduledListing::where('organization_id', $orgId)
  ->where('status', 'pending')
  ->orderBy('scheduled_at')->get(['id', 'marketplace', 'scheduled_at']);

// Failed scheduled listings
ScheduledListing::where('organization_id', $orgId)
  ->where('status', 'failed')
  ->get(['id', 'marketplace', 'error_message', 'scheduled_at']);

// Promoted listings ROAS by campaign
EbayPromotedListing::whereHas('campaign', fn($q) => $q->where('organization_id', $orgId))
  ->selectRaw("campaign_id, SUM(ad_fees) as spend, SUM(sales_amount) as revenue, CASE WHEN SUM(ad_fees) > 0 THEN ROUND(SUM(sales_amount) / SUM(ad_fees), 2) ELSE 0 END as roas")
  ->groupBy('campaign_id')->get();

// Top performing promoted listings by ROAS
EbayPromotedListing::whereHas('campaign', fn($q) => $q->where('organization_id', $orgId))
  ->where('ad_fees', '>', 0)
  ->orderByDesc('roas')
  ->with('product:id,name,sku')
  ->limit(20)->get(['product_id', 'ad_rate_percentage', 'impressions', 'clicks', 'sales_amount', 'ad_fees', 'roas']);

// Cross-listed products (have listings on multiple marketplaces)
Product::where('organization_id', $orgId)
  ->has('ebayListings')
  ->has('shopifyListings')
  ->count();
```

## Audit Logging

| Action | Category | Severity | Metadata keys |
|--------|----------|----------|---------------|
| `scheduled_listing.created` | marketplace | info | marketplace, scheduled_at |
| `scheduled_listing.processed` | marketplace | info | marketplace, listing_id, source: cron |
| `scheduled_listing.failed` | marketplace | error | marketplace, error_message |
| `listing.cross_listed` | marketplace | info | source_marketplace, target_marketplaces |
| `promoted.campaign.created` | marketplace | info | target_rate_pct |
| `promoted.campaign.updated` | marketplace | info | changes |

Trait: models in this feature use `LogsAudit` (`app/Traits/LogsAudit.php`) for auto CRUD diff. Domain lifecycle events emitted from `app/Services/Marketplace/{ListingScheduler,CrossListingService}`, `app/Jobs/Marketplace/ProcessScheduledListing`; `ScheduledListing` model (`LogsAudit`). Cron jobs use `withAuditActor` with `source: 'cron'`. via `UserActivityLog::log(action, desc, $user, $metadata, $severity, $category)`.

Per-marketplace publish actions (`listing.{ebay|shopify|amazon}.published`) are emitted by the respective marketplace skill, not here -- this skill orchestrates them.

Spec: `docs/AUDIT_SYSTEM.md`. Enforced rule: `.claude/rules/audit-logging.md`. Tests: `use Tests\Support\AssertsAudits;` then `$this->assertAudited('action', $user, [...])`.
