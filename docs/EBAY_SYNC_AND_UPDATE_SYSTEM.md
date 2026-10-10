# eBay Sync and Update System

This document covers the ongoing listing management system: scheduled sync, manual sync operations, inventory updates, store import, error handling, and the full API surface.

For listing **creation**, see [EBAY_LISTING_SERVICE.md](EBAY_LISTING_SERVICE.md).
For **OAuth authorization**, see [ebay-oauth-workflow.md](ebay-oauth-workflow.md).

---

## 1. Overview

### Sync Model: Polling + ECN Webhooks

This application uses a combination of **outbound polling** and **inbound eBay ECN (Event Change Notification) webhooks**. Polling handles scheduled sync tasks (stale updates, quantity sync, failed retries), while ECN webhooks provide near-real-time notifications for inventory and order events.

Other integrations in this codebase (Shopify, EasyPost, ShipStation) also have incoming webhooks. For eBay ECN details, see [Section 6](#6-ebay-ecn-webhooks) and [INVENTORY_SYNC_SYSTEM.md](INVENTORY_SYNC_SYSTEM.md).

### Three Types of Updates

| Type | Trigger | Description |
|------|---------|-------------|
| **Scheduled sync** | Cron / Laravel scheduler | Runs automatically on a fixed schedule via `ProcessEbayScheduledTasks` |
| **Manual sync** | User action | Triggered from the web UI or mobile API |
| **Import** | User action | Pulls existing eBay store listings into the application via `ImportEbayInventory` |

---

## 2. Scheduled Sync System

### Job: `ProcessEbayScheduledTasks`

**File:** `app/Jobs/Ebay/ProcessEbayScheduledTasks.php`

This job accepts a `$taskType` string and executes the corresponding maintenance task. It is dispatched by the Laravel scheduler (see `routes/console.php`).

### Task Types

| Task | Schedule Command | Description |
|------|-----------------|-------------|
| `update_stale` | `ebay-update-stale` | Finds listings not synced in 24+ hours, dispatches `UpdateEbayInventory` jobs |
| `retry_failed` | `ebay-retry-failed` | Finds listings in `STATUS_ERROR` with `consecutive_errors < 5`, dispatches `RetryFailedEbaySync` jobs |
| `sync_quantities` | `ebay-sync-quantities` | Finds published listings where local quantity differs from `listed_quantity`, dispatches `UpdateEbayInventory` with `updateType='quantity'` |
| `cleanup_logs` | `ebay-cleanup-logs` | Purges old `ebay_sync_logs` records to keep the table manageable |

### Auto-Sync Gate

The `update_stale` task checks the `auto_sync_enabled` flag on the `IntegrationCredential` record before processing any credential's listings. Organizations can disable auto-sync (via `POST /marketplaces/ebay/auto-sync`) without affecting manual syncs.

### Manual Dispatch (Tinker)

```bash
# Trigger stale sync manually
docker exec ecommerce_app php artisan tinker --execute="App\Jobs\Ebay\ProcessEbayScheduledTasks::dispatch('update_stale');"

# Trigger quantity sync
docker exec ecommerce_app php artisan tinker --execute="App\Jobs\Ebay\ProcessEbayScheduledTasks::dispatch('sync_quantities');"
```

---

## 3. Manual Sync Operations

### Web Routes

All routes are prefixed with `/marketplaces/ebay` and handled by `EbayMarketplaceController`.

| Method | Path | Action | Description |
|--------|------|--------|-------------|
| `POST` | `/marketplaces/ebay/products/{product}/sync` | `syncProduct()` | Sync a single product to eBay |
| `POST` | `/marketplaces/ebay/sync/bulk` | `syncBulk()` | Bulk sync an array of product IDs |
| `POST` | `/marketplaces/ebay/listings/{listing}/update` | `updateListing()` | Update quantity/price on a published listing |
| `POST` | `/marketplaces/ebay/listings/{listing}/retry` | `retryListing()` | Retry a failed listing |
| `POST` | `/marketplaces/ebay/retry/all` | `retryAll()` | Retry all failed listings for this team |
| `POST` | `/marketplaces/ebay/auto-sync` | `toggleAutoSync()` | Toggle the `auto_sync_enabled` flag |

### API Routes

All routes are prefixed with `/api/ebay` and handled by `EbayApiController`. Require Sanctum token authentication.

| Method | Path | Action | Description |
|--------|------|--------|-------------|
| `POST` | `/api/ebay/sync/product` | `syncProduct()` | Sync a single product by ID |
| `POST` | `/api/ebay/sync/bulk` | `syncBulk()` | Sync up to 100 products (array of IDs in request body) |
| `POST` | `/api/ebay/sync/all` | `syncAll()` | Sync all pending listings for this team |
| `POST` | `/api/ebay/listings/{id}/update` | `updateListing()` | Update quantity/price on a specific listing |
| `POST` | `/api/ebay/listings/{id}/retry` | `retryListing()` | Retry a specific failed listing |
| `POST` | `/api/ebay/retry/all` | `retryAll()` | Retry all failed listings for this team |

### Additional API Endpoints

| Method | Path | Description |
|--------|------|-------------|
| `GET` | `/api/ebay/status` | OAuth and connection status |
| `GET` | `/api/ebay/stats` | 24-hour sync statistics and listing counts by status |
| `GET` | `/api/ebay/listings` | Paginated listing list with filtering |
| `GET` | `/api/ebay/listings/{listing}` | Single listing detail |
| `GET` | `/api/ebay/logs` | Paginated sync log entries |
| `GET` | `/api/ebay/policies` | Available eBay business policies |
| `GET` | `/api/ebay/categories/suggest` | Category suggestions for a product title |
| `PATCH` | `/api/ebay/config` | Update eBay integration configuration |

---

## 4. Inventory Update Flow

### Job: `UpdateEbayInventory`

**File:** `app/Jobs/Ebay/UpdateEbayInventory.php`
**Queue:** `ebay-sync`
**Retries:** 3 attempts with 30-second backoff on `EbayApiException`

### Update Types

The job accepts an `$updateType` parameter:

| Value | Published listing (has `offer_id`) | Imported listing (no `offer_id`) |
|-------|-----------------------------------|---------------------------------|
| `'quantity'` | `PUT /sell/inventory/v1/offer/{offerId}` (Offer API) | Trading API `ReviseFixedPriceItem` |
| `'price'` | `PUT /sell/inventory/v1/offer/{offerId}` (Offer API) | Trading API `ReviseFixedPriceItem` |
| `'both'` | Single Offer API call with both qty + price | Trading API `ReviseFixedPriceItem` |

**IMPORTANT**: Published listings MUST use the **Offer API** for quantity updates. The Inventory Item API (`PUT /sell/inventory/v1/inventory_item/{sku}`) is silently ignored by eBay for published listings. The `updateOffer()` method also updates `available_quantity`, `last_inventory_update_at`, and `last_synced_at` on the local listing record.

### Change Detection

Before making any API call, the job compares local values against the stored `EbayListing` fields:
- `available_quantity` -- quantity last pushed to eBay
- `listed_price` -- price last pushed to eBay

If the local value matches what was last synced, the update is skipped.

### API Routing Logic

```
if (no offer_id AND has listing_id) -> Trading API (ReviseFixedPriceItem)
else if (has offer_id)              -> Offer API (PUT /sell/inventory/v1/offer/{offerId})
else                                -> Inventory Item API (PUT /sell/inventory/v1/inventory_item/{sku})
```

The Trading API fallback handles legacy imported listings that were not created through the Inventory API pipeline.

### Static Helpers

```php
UpdateEbayInventory::updateAllStale($credential);   // queue updates for all stale listings
UpdateEbayInventory::syncAllQuantities($credential); // queue quantity syncs for mismatched listings
```

### Bulk Sync Job: `BulkSyncToEbay`

**File:** `app/Jobs/Ebay/BulkSyncToEbay.php`
**Queue:** `ebay-sync`
**Retries:** 3 attempts with 60-second backoff

Handles batches of products. For each product, creates a new listing if none exists or updates an existing one. Uses Laravel batch support for cancellation. Per-product errors are logged but do not abort the batch.

---

## 5. eBay Store Import

### Overview

The import pipeline pulls **active listings** from an existing eBay store into the application, creating `Product` and `EbayListing` records for each imported item. This is used to onboard new clients who have an existing eBay store — their entire inventory is seeded into the app in one operation.

### Routes

| Method | Path | Description |
|--------|------|-------------|
| `GET` | `/marketplaces/ebay/import` | Import page UI |
| `POST` | `/marketplaces/ebay/import/start` | Start an import job |
| `GET` | `/marketplaces/ebay/import/status` | Poll import progress (JSON) |
| `POST` | `/marketplaces/ebay/import/cancel` | Cancel a running import |
| `GET` | `/marketplaces/ebay/import/debug` | Debug info: connectivity, credential status, queue health |
| `POST` | `/marketplaces/ebay/sku-parser/test` | Test SKU parser config with a sample SKU |
| `PUT` | `/marketplaces/ebay/sku-parser` | Save SKU parser config to credential |

### Import Options

The `startImport()` controller method accepts these options:

| Option | Type | Default | Description |
|--------|------|---------|-------------|
| `skip_existing_skus` | bool | `true` | Skip items whose SKU already exists as a Product |
| `update_existing_products` | bool | `false` | When a duplicate is found, update the existing Product from eBay data |
| `default_status` | string | `'draft'` | Status for newly created products: `draft`, `active`, or `inactive` |
| `preserve_price` | bool | `false` | When updating, keep the local `selling_price` |
| `preserve_description` | bool | `false` | When updating, keep the local `description` and `short_description` |

### Job: `ImportEbayInventory`

**File:** `app/Jobs/Ebay/ImportEbayInventory.php`
**Tries:** 1 (no retries) | **Timeout:** 600 seconds per page

This job processes eBay store listings page by page (200 items/page). Each page dispatches the next page's job with a 500ms delay for rate limiting.

**Per-item processing logic:**

1. Extract `item_id` and `sku` (falls back to `EBAY-{item_id}` if no SKU)
2. **Level 1 — Check by eBay item ID:** `EbayListing` where `ebay_listing_id = item_id`. If found: refresh listing metadata (price, quantity, category, status). If `update_existing_products` is true, also update the linked Product. Otherwise count as skipped.
3. **Level 2 — Check by Product SKU:** `Product` where `sku = sku` and `organization_id = org`. If found: backfill the EbayListing's `ebay_listing_id` (recovers orphaned listings). Optionally update product. Otherwise count as skipped.
4. **No match — Create new:** Call `createProductFromEbayItem()` then `createEbayListingRecord()`. Log to `EbaySyncLog` with operation `import_inventory`, direction `inbound`.

**Other behavior:**
- Checks for `BulkOperationJob` cancellation between pages
- Notifies user on completion or failure
- Tracks progress counters (imported, skipped, updated, failed) in `BulkOperationJob.parameters`
- Per-item exceptions are caught, logged to `BulkOperationJob.errors` (max 100), and counted as failed

### Two-Level Duplicate Detection

| Level | Lookup | Match Key | Action on Match |
|-------|--------|-----------|-----------------|
| 1 | `EbayListing` table | `ebay_listing_id` + `integration_credential_id` | Refresh listing metadata; optionally update linked Product |
| 2 | `Product` table | `sku` + `organization_id` | Backfill orphaned EbayListing with `ebay_listing_id`; optionally update Product |
| — | No match | — | Create new Product + EbayListing |

Level 2 handles orphaned listings — cases where `CreateEbayListingJob` partially failed, leaving a Product record with no linked EbayListing.

### Update Flow

When `update_existing_products=true` and a duplicate is detected, `updateProductFromEbayItem()` updates:
- **Always updated:** name, condition, weight/dimensions, identifiers (UPC/EAN/MPN), brand, images (old `ebay_import` source images deleted and recreated), category link, quantities, search_keywords
- **Preserved if flagged:** `selling_price` (when `preserve_price=true`), `description` + `short_description` (when `preserve_description=true`)
- **Attributes:** shallow merge — local-only keys are kept, eBay keys are updated/added

### Service: `EbayStoreImportService`

**File:** `app/Services/EbayStoreImportService.php`

**API used:** eBay Trading API at `https://api.ebay.com/ws/api.dll`

**Operations:**
- `GetSellerList` — fetches active listings with pagination (EndTime filter: now to now+120 days)
- `ReviseFixedPriceItem` — updates listings during import (e.g., to assign internal SKU)

### Data Mapping

`mapToProductData()` transforms each eBay item into Product fields:

**Core fields:**
- `name` from title; `description` as-is; `short_description` truncated to 200 chars at word boundary
- `sku` — raw eBay SKU, or parsed SKU if parser is enabled, or `EBAY-{item_id}` fallback
- `raw_sku` — always stores the original eBay SKU (before parsing)
- `selling_price` from `start_price`; `currency` from eBay currency code

**Quantities:**
- `stock_quantity` / `available_quantity` = `quantity - quantity_sold`
- `quantity` = total eBay quantity; `reserved_quantity` = 0; `total_sales` = `quantity_sold`

**Condition mapping** (eBay condition_id to app constant):

| eBay ID | App Condition |
|---------|---------------|
| 1000 | NEW |
| 1500 | NEW_OTHER |
| 1750 | NEW_WITH_DEFECTS |
| 2000 | CERTIFIED_REFURBISHED |
| 2500 | MANUFACTURER_REFURBISHED |
| 2750 | USED_EXCELLENT |
| 3000 | USED_VERY_GOOD |
| 4000 | USED_GOOD |
| 5000 | USED_ACCEPTABLE |
| 6000 | USED_ACCEPTABLE |
| 7000 | FOR_PARTS_OR_NOT_WORKING |
| default | USED_GOOD |

**Identifiers** (extracted from `ItemSpecifics`, skipping "Does not apply" / "N/A"):
- `upc` from keys: UPC, GTIN
- `ean` from key: EAN
- `mpn` from key: MPN
- `brand_name` from key: Brand (excludes "Unbranded")

**Weight & dimensions** (extracted from `ItemSpecifics`, parsed numerically from strings like "2.5 lbs"):
- Weight keys: `Item Weight`, `Weight`
- Length keys: `Item Length`, `Length`
- Width keys: `Item Width`, `Width`
- Height keys: `Item Height`, `Height`

**Images:** All `PictureURL` entries stored; first becomes `featured_image`.

**Attributes JSON:** Stores `ebay_category`, `ebay_category_id`, `ebay_item_id`, `ebay_condition_id`, `listing_type`, plus all remaining ItemSpecifics not already extracted.

**Search keywords:** Generated from title + brand + category, lowercased, deduplicated, min 2 chars each.

**Defaults:** `status` from import options (default `draft`), `type` = `simple`, `track_inventory` = true, `manage_stock` = true.

### Product Creation Side Effects

When `createProductFromEbayItem()` creates a new Product, it also:
1. Creates or links a **Brand** via `Brand::firstOrCreate()` (by slug)
2. Creates **ProductIdentifier** records for UPC/EAN/MPN (source: `ebay_import`, verified: false)
3. Creates **ProductImage** records from eBay picture URLs (first is primary)
4. Finds or creates a **Category** from eBay category name/ID, links to product
5. If SKU parser extracted `parsed_owner`: creates **Supplier** (by code) and **SupplierProduct** (supplier_sku = raw_sku, cost_price = 0). House-stock prefixes from `IntegrationCredential.config.house_prefixes` skip Supplier creation and set `Product.attributes.is_house_stock=true` instead.
6. If the structured location parser extracted a room/shelf/special_area/container: `WarehouseAutoBuilder` creates **WarehouseZone** + **WarehouseBin**, plus an **InventoryLocation** (default `WAREHOUSE`) and an **InventoryLevel** binding the product to that location and bin (quantity_on_hand = stock_quantity, quantity_reserved = 0). See [Structured Location Parser](#structured-location-parser) for the full field-by-field wiring map and known gaps.

### SKU Parser

**Service:** `app/Services/SkuParserService.php`
**Config location:** `IntegrationCredential.config.sku_parsers` (array) and `sku_parser` (legacy single)
**Frontend:** `resources/js/Components/Marketplaces/SkuParserConfig.vue`

The SKU parser extracts structured data from the eBay custom SKU field. All modes return `{owner: ?string, sku: ?string, location: ?string}`.

**Multi-parser support:** Multiple parser configs can be defined, each with an optional `prefix` for quick matching. During import, `parseMulti()` tests each parser in order and returns the first match. Config is stored in `IntegrationCredential.config.sku_parsers` (array). The legacy `sku_parser` (singular) format is also supported for backward compatibility via `SkuParserService::normalizeParsers()`.

**Three parsing modes:**

| Mode | Config | Example |
|------|--------|---------|
| Delimiter | `mode: 'template'`, `template.type: 'delimiter'` | SKU `ARC-12345-BIN3` split by `-` into owner=ARC, sku=12345, location=BIN3 |
| Fixed-width | `mode: 'template'`, `template.type: 'fixed'` | SKU `AMP12345BIN3` extracted by character position/length ranges |
| Regex | `mode: 'regex'` | Pattern with named groups: `(?P<owner>\w+)-(?P<sku>\d+)-(?P<location>\w+)` |

**Common SKU formats in practice:**

| Prefix | Format | Example | Parser |
|--------|--------|---------|--------|
| ARC | `ARC [initials] <product-sku> [(xN)] <location>` | `ARC ABC PO-1033-1022-312 FOH C5` | Regex with `(?P<owner>ARC)`, `(?P<sku>...)`, `(?P<location>...)` |
| HAWK, PINE, OAKS, FERN, DUNE, COVE, etc. | `OWNER <location>` | `HAWK RM 132 SHELF M GROUND LVL` | Owner + Location preset: `(?P<owner>...)\s+(?P<location>...)` |
| REEF6, BIRCH2 | `OWNER <location>` (prefix includes digit) | `REEF6 ABC 90508 MANCHURIANBROCCOLI EB` | Same owner + location pattern |
| KELP | `KELP [price] <location>` | `KELP 1 SHELF 30 TUB EL CAP` | Owner + Location |
| (C), (T), (COOP) | `(tag) OWNER <location>` | `(C) PINE - Tub Morrigan - Shelf B3` | Custom regex or unmatched |

**Preset patterns (in `SkuParserConfig.vue`):**
- **ARC SKU**: Complex regex handling initials, product-sku with dashes, optional quantity multiplier, location
- **Owner + Location**: Generic `(?P<owner>[A-Z][A-Z0-9]{1,5})\s+(?P<location>.+)` -- first word is owner/supplier, rest is location (no internal SKU)

**Config structure example (multi-parser with prefix matching):**
```json
{
  "sku_parsers": [
    {
      "enabled": true,
      "prefix": "ARC",
      "mode": "regex",
      "regex": {
        "pattern": "/^(?P<owner>ARC)\\s+(?:[A-Z]{2,3}\\s+)?(?P<sku>[A-Z][A-Z0-9]*-[A-Z0-9][\\w-]*(?:-[A-Z])?)(?:\\s*\\(x\\d+\\))?(?:\\s+(?P<location>.+))?$/"
      }
    },
    {
      "enabled": true,
      "prefix": "",
      "mode": "regex",
      "regex": {
        "pattern": "/^(?P<owner>[A-Z][A-Z0-9]{1,5})\\s+(?P<location>.+)$/"
      }
    }
  ]
}
```

**Downstream effects of parsing:**
- `parsed_sku` overrides the raw eBay SKU as the Product's `sku` field
- `parsed_owner` triggers Supplier + SupplierProduct creation (see Product Creation Side Effects above)
- `parsed_location` triggers InventoryLocation + InventoryLevel creation

**Testing:** `POST /marketplaces/ebay/sku-parser/test` accepts `{sample_sku: "ARC-12345-BIN3", config: {...}}` and returns parsed result without saving.

### Structured Location Parser

**Service:** `app/Services/EbaySkuLocationParser.php`

In addition to the config-driven `SkuParserService`, a keyword-based `EbaySkuLocationParser` always runs during import. Combined with `EbayStoreImportService` and `WarehouseAutoBuilder`, **this is the onboarding pipeline** — a single import pass hydrates Catalog (Product), Suppliers, and Warehouse (Zone/Bin/InventoryLevel). Validated against a 66k-row production export.

**Canonical token order:**

```
[bracket-prefix?] OWNER [(LISTER_INITIALS | CONSIGNOR [item_id]) ...] [price] LOCATION_TOKENS
```

| Slot | Examples | Notes |
|------|----------|-------|
| bracket-prefix (optional) | `(C)`, `(T)`, `(GHI)`, `(COOP)` — ~862 rows | Captured into `Product.attributes.bracket_prefix` (raw letter, not interpreted). |
| OWNER (required) | `ARC`, `HAWK`, `OAKS`, `PINE`, `FERN`, `DUNE`, `REEF`, `REEF6`, `MESA`, `COVE`, `KELP`, `SAGE`, `MOSS`, `BIRCH2`, `CEDR`, `ELMS`, `COOP` | First letter-led token. Matched case-insensitively (lowercase `arc` parses); stored uppercase. Becomes Supplier(code, org_id) unless in `house_prefixes` config. |
| LISTER_INITIALS (optional) | `ABC`, `DEF`, `GHI` (configured via `IntegrationCredential.config.known_listers`) | Routes to `Product.attributes.lister_initials`; **no Supplier row created**. |
| CONSIGNOR (optional) | `COOP`, `MADM`, `KURT` (anything 2-5 letters not in `known_owners`/`known_listers`) | Becomes Supplier(code, org_id, `meta.is_consignor=true`); writes `Product.attributes.consignor_code`. |
| item_id (optional) | `58585`, `78546`, `87633` | 4-6 digit number after a consignor token; written to `Product.attributes.consignor_item_id`. |
| price (KELP pattern, optional) | `$3.44`, `29$`, `$127.52` | Currently discarded. |
| LOCATION_TOKENS | `RM 134`, `SHELF B3`, `TUB GOLDENGOOSE`, `BOX KURT`, `LVL 3`, `GROUND`, `FOH`, `MEZZ`, `CART`, `RACK` | Keyword-prefixed; can appear in any order. |

**Parsed fields returned by `EbaySkuLocationParser::parse()`:**

| Field | Example | Description |
|-------|---------|-------------|
| `owner` | HAWK, PINE, ARC | First letter-led token (warehouse / primary supplier). Matched case-insensitively; stored uppercase. |
| `consignor` | COOP, MADM, KURT | 2-5 letter code following owner that is NOT in `known_owners` or `known_listers`. Becomes a Supplier row. |
| `lister_initials` | ABC, DEF, GHI | 2-5 letter code in the `known_listers` config list. Routed away from `consignor`; no Supplier row. |
| `bracket_prefix` | C, T, COOP, GHI | Captured content of a leading `(...)` bracket. |
| `item_id` | 58585 | 4-6 digit consignor tracking number |
| `product_sku` | IND-18979-9 | ARC pattern only: dash-delimited product ID |
| `intake_status` | `pending` | Set when SKU contains `ID PENDING` or `PO PENDING` |
| `room` | 132, 134, 119 | Extracted from RM/Rm/rm + digits |
| `shelf` | 44, B3, EA, CM | Extracted from SHELF/SHLF/SEHFL + ID |
| `container_type` | tub, box | Container keyword found |
| `container_name` | LABRADOR, TRAPMAXXING | Name following TUB or BOX |
| `level` | 1-6, GROUND | Vertical position on pallet shelf |
| `special_area` | FOH, CART, MEZZ | Named warehouse area |

**Default `known_owners`** (seeded in `EbayStoreImportService::__construct()` ~line 87; override via `IntegrationCredential.config.known_owners`):

```
HAWK, PINE, OAKS, FERN, DUNE, REEF, REEF6, MESA, COVE, KELP,
SAGE, MOSS, BIRCH, BIRCH2, CEDR, ELMS, FOX, TIDE, SILT, DELT, CLAY, LOAM
```

Tokens in this list are never tagged as consignors (prevents the owner being mis-tagged as a consignor of itself).

### Onboarding pipeline: parsed field → backend write

All writes use `firstOrCreate` and are org-scoped, so re-running the import is **idempotent** (no duplicate Supplier / Zone / Bin / InventoryLevel rows).

| Parsed field | Backend write | Code path |
|--------------|---------------|-----------|
| `owner` | `Supplier(code=OWNER, organization_id)`. House-stock prefixes from `IntegrationCredential.config.house_prefixes` skip Supplier creation and set `Product.attributes.is_house_stock=true`. | `EbayStoreImportService.php:855–886` |
| `consignor` | `Supplier(code=CONSIGNOR, organization_id, meta_data.is_consignor=true)` + `Product.attributes.consignor_code` | `EbayStoreImportService.php` (inside the `$hasLocationData` block, which now includes consignor/lister/bracket in the predicate) |
| `lister_initials` | `Product.attributes.lister_initials`. No Supplier row created. | Same block; routed by `EbaySkuLocationParser::isListerCode()` |
| `bracket_prefix` | `Product.attributes.bracket_prefix` | Same block |
| `item_id` | `Product.attributes.consignor_item_id` | Same block |
| `product_sku` (ARC) | `Product.sku` | `EbayStoreImportService.php:459` |
| `room` | `WarehouseZone(code=RM-<room>, name=Room <room>, organization_id)` | `WarehouseAutoBuilder::resolveZone()` line 93 |
| `special_area` (no room) | `WarehouseZone(code=FOH\|MEZZ\|CART\|…, name=Front of House\|…)` | `WarehouseAutoBuilder::resolveZone()` lines 74–81 |
| `shelf` + `level` + `container_type` + `container_name` | `WarehouseBin(zone_id, code=<compound>, label, rack, shelf, bin_position, barcode)` — e.g. `SHLF-B3-LVL-3-TUB-GOLDENGOOSE` | `WarehouseAutoBuilder::resolveBin()` line 127, `generateBinCode()` 147–174 |
| (always) | `InventoryLocation(code=WAREHOUSE, organization_id, is_default=true)` | `WarehouseAutoBuilder::resolveMainWarehouse()` line 218 |
| (always when product imported) | `InventoryLevel(product_id, location_id, organization_id, bin_id?, quantity_on_hand)` — pick reads from here, **not** `Product.warehouse_location_id` (legacy bigint, ignored) | `EbayStoreImportService.php:966–975` |

**Worked examples from the CSV** (assumes `known_listers=['ABC','DEF','GHI']` configured for the org):

| Raw SKU | Backend writes |
|---------|----------------|
| `HAWK RM 134 SHELF B3 TUB GOLDENGOOSE` | Supplier(HAWK), Zone(`RM-134`), Bin(`SHLF-B3-TUB-GOLDENGOOSE`), InventoryLevel |
| `HAWK COOP 58585 SHELF 29 TUB ELTONJOHN` | Supplier(HAWK), Supplier(COOP, is_consignor), `Product.attributes.consignor_item_id=58585`, Bin(`SHLF-29-TUB-ELTONJOHN`) |
| `HAWK ABC SHELF 28 LVL 3` | Supplier(HAWK), `Product.attributes.lister_initials=ABC` (no Supplier row for ABC), Bin(`SHLF-28-LVL-3`) |
| `ARC ABC PO-1043-1020-101 AE-3/BOOST` | Supplier(ARC), `Product.attributes.lister_initials=ABC`, `Product.sku=PO-1043-1020-101` |
| `(C) PINE Tub Morrigan Shelf B3 21655` | `Product.attributes.bracket_prefix=C`, Supplier(PINE), Bin(`SHLF-B3-TUB-MORRIGAN`) |
| `arc abc PO-1043-1020-101 FOH` (lowercase) | Supplier(ARC), `Product.attributes.lister_initials=ABC`, Zone(`FOH`), `Product.sku=PO-1043-1020-101` |

### Limitations & follow-up code work

Gaps #1, #2, #3, #6 were resolved in the 2026-05-13 code pass; gaps #4, #5, #7, #8 remain open.

1. ~~**Gap #1 — Consignor-only SKU drops Supplier link.**~~ Fixed: `$hasLocationData` predicate now includes `consignor`, `lister_initials`, and `bracket_prefix`.
2. ~~**Gap #2 — Lister initials misclassified as consignors.**~~ Fixed: `IntegrationCredential.config.known_listers` routes those tokens to `Product.attributes.lister_initials`; no Supplier row created.
3. ~~**Gap #3 — Bracket prefix info dropped.**~~ Fixed: regex captures the inner letter; persisted to `Product.attributes.bracket_prefix`.
4. **Gap #4 — COOP is both an owner and a frequent 2nd-token consignor.** 316 rows lead with `COOP`; 3,500+ rows have `COOP` as the second token. Some lines invert order entirely (`COOP HAWK ...`). First-token-wins keeps this consistent but mis-assigns the warehouse owner on inverted-order rows.
5. **Gap #5 — GHI is not in `known_owners`** despite leading 242 rows; falls through the owner regex when first but gets tagged as consignor when second.
6. ~~**Gap #6 — Lowercase `arc` not matched.**~~ Fixed: owner regex is case-insensitive; result is uppercased. Owner regex also skips tokens that are location keywords (`SHELF`, `RM`, `TUB`, …) to keep location-first SKUs intact.
7. **Gap #7 — 682 rows have no recognisable owner prefix** (location-first like `SHELF B3 MVP PINE 55025`, or model-first). Parser falls through to keyword-only extraction; products land in `WAREHOUSE` with no owner Supplier link.
8. **Gap #8 — Bin code granularity is shelf+level+container** — misspelled container names create distinct bins, inflating warehouse layouts.

### CSV Import variant (`ImportEbayInventoryFromCsv`)

For orgs that can't authorise the eBay Inventory API yet (or just want to onboard fast), an upload of eBay's **"Active Listings"** report CSV runs through `ImportEbayInventoryFromCsv` + `EbayCsvParser` and feeds the same `EbayStoreImportService` pipeline as the API import.

| Item | Value |
|------|-------|
| Job file | `app/Jobs/Ebay/ImportEbayInventoryFromCsv.php` |
| Parser file | `app/Services/Ebay/EbayCsvParser.php` |
| Expected CSV headers | `Item number`, `Title`, `Variation details`, `Custom label (SKU)` (UTF-8 BOM tolerated; title may contain commas in quoted fields) |
| Hardcoded defaults | `available_quantity=1`, `quantity=1`, `condition_id=4000` (Used), `start_price=0`, empty `pictures`/`item_specifics` |
| Chunking | Self-redispatching job, `CHUNK_SIZE=50` rows per chunk |
| Retry budget | `$tries=2`, `$timeout=180` |
| Debug log cap | `DEBUG_LOG_MAX=200` (rolling window of the most-recent events in `BulkOperationJob.parameters.debug_log`) |

**Why qty defaults to 1**: the active-listings report has no quantity column. Every row is by definition a live listing with ≥1 unit available on eBay, so `available_quantity=0` would hide every imported row from the inventory location detail page (which filters `quantity_on_hand > 0` for "Total Products"). Per-product qty can be updated post-import or backfilled from the eBay API.

**Operational tuning rationale**:
- `CHUNK_SIZE=50` is sized to keep wall-clock per chunk well under the redis queue `retry_after` window (default 90s; `config/queue.php:70`). Larger chunks risk redis re-issuing the job to a second worker before the first acks, producing `MaxAttemptsExceededException`.
- `$tries=2` is safe because per-row imports are idempotent via `ebayListingExistsByItemId()` + `productExistsBySku()` — a re-issued chunk just skips already-imported items.
- `$timeout=180` stays under the Horizon supervisor timeout so the job's own failure path fires before the worker is killed.
- `DEBUG_LOG_MAX=200` prevents the `parameters` JSON from growing to megabytes on multi-thousand-row imports. Unbounded growth caused the worker to hit the container memory ceiling (512MB on staging) and get SIGKILL'd, leaving the in-flight job unacked → MaxAttempts.

**Recovering from a partial / failed CSV import**: the import is fully idempotent at the row level. Either wipe org data and re-upload (`docs` for the wipe script is on the team wiki), or simply re-run the import job — it will skip rows already in `ebay_listings` by item id.

### Debug & Monitoring

**Debug logging in job:** Each `ImportEbayInventory` job maintains a debug log with timestamped events: `job_started`, `credential_check`, `page_api_response` (with API timing in ms), `page_completed`, `next_page_dispatched`, `exception`. Saved to `BulkOperationJob.parameters.debug_log`.

**Debug endpoint:** `GET /marketplaces/ebay/import/debug` returns:
- Environment and credential status
- Live eBay connectivity test with response time
- Queue health (worker status, stuck imports)
- Recent sync logs and bulk job history
- Failed jobs from `failed_jobs` table

**Status polling:** `GET /marketplaces/ebay/import/status` returns JSON with job progress. Append `?debug=true` to include the full debug_log array.

**Frontend debug panel:** Toggle with `Ctrl+Shift+.` on the import page. Shows integration status, connectivity test, queue health, job timeline with events and memory usage, and poll log with response times.

### Frontend Polling

`Import.vue` polls `GET /import/status` every 3 seconds while an import is active. The UI shows:
- Progress bar (processed_items / total_items)
- Elapsed time counter (updates every second)
- Last error message
- Results grid on completion: imported (green), updated (blue), skipped (gray), failed (red)

Polling starts automatically when an active import is detected and stops when status reaches completed/failed/cancelled.

---

## 6. eBay ECN Webhooks

eBay Event Change Notifications (ECN) provide near-real-time push updates for inventory and order events. Implemented 2026-03-05.

### Endpoints

| Method | Path | Purpose |
|--------|------|---------|
| `GET` | `/api/webhooks/ebay` | Challenge verification (eBay subscription setup) |
| `POST` | `/api/webhooks/ebay` | Receive notifications |

These routes are outside auth middleware (eBay calls them directly).

### Controller: `EbayNotificationController`

**File:** `app/Http/Controllers/Ebay/EbayNotificationController.php`

**Challenge flow (GET):**
1. eBay sends `challenge_code` query parameter during subscription setup
2. App responds with `sha256(challenge_code + verificationToken + endpointUrl)`
3. Verification token stored in `IntegrationCredential.config.ebay_notification_token`

**Notification flow (POST):**
1. Validate `X-EBAY-SIGNATURE` header exists
2. Find active eBay credential and verification token
3. Verify HMAC signature: `base64_encode(hash_hmac('sha256', rawBody, verificationToken, binary: true))`
4. Extract topic from `payload.metadata.topic` or `X-EBAY-TOPIC` header
5. Dispatch `ProcessEbayNotification` job for async processing
6. Always return 200 OK (even on validation failure, per eBay requirements)

**Job:** `ProcessEbayNotification` (`app/Jobs/Ebay/ProcessEbayNotification.php`) handles topic-specific processing asynchronously.

For full details on how ECN integrates with inventory sync, see [INVENTORY_SYNC_SYSTEM.md](INVENTORY_SYNC_SYSTEM.md).

**Polling still supplements webhooks:** The scheduled `update_stale` and `sync_quantities` tasks continue to run as a safety net, catching any notifications that may have been missed.

---

## 7. Error Handling and Retry Logic

### EbayApiException

**File:** `app/Services/EbayApiException.php`

All eBay API errors are wrapped in this exception. Key classification methods:

| Method | Returns true when |
|--------|------------------|
| `isTokenError()` | OAuth token expired, invalid, or revoked |
| `isRateLimitError()` | HTTP 429 or eBay error code 25010 |
| `isRetryable()` | Transient server error that may succeed on retry |
| `isValidationError()` | Request data is invalid (non-retryable) |
| `isNotFound()` | Resource does not exist on eBay |

**Retryable eBay error codes:** 25001, 25002, 25008, 25019, plus any HTTP 5xx response.

**Non-retryable conditions (examples):** invalid category ID, duplicate listing, seller not eligible, policy violation.

### Consecutive Error Counter

`EbayListing.consecutive_errors` tracks how many times in a row a listing has failed:
- Incremented by `recordError()` on each failure
- Reset to 0 by `markSynced()` on success
- Listings with `consecutive_errors >= 5` are excluded from auto-retry by `ProcessEbayScheduledTasks` and `RetryFailedEbaySync`
- These listings must be manually retried or fixed

### RetryFailedEbaySync Job

**File:** `app/Jobs/Ebay/RetryFailedEbaySync.php`

Before retrying, this job checks:
1. `consecutive_errors < 5`
2. The `IntegrationCredential` is active (token not revoked)
3. The associated `Product` is active (not archived or deleted)
4. The last error message does not indicate a permanently non-retryable condition

If any check fails, the listing is marked as permanently failed via `markAsPermanentlyFailed()`.

**Static helpers:**
```php
RetryFailedEbaySync::retryForCredential($credential); // retry all failed for one credential
RetryFailedEbaySync::retryForTeam($team);             // retry all failed for a team
RetryFailedEbaySync::markAsPermanentlyFailed($listing, $reason); // give up on a listing
```

### Rate Limiter

**File:** `app/Services/EbayRateLimiter.php`

Centralized rate limiting applied to all outbound eBay API calls:

| Limit | Mechanism | Value |
|-------|-----------|-------|
| Per-second | Redis token bucket | 5 calls/second |
| Per-day | Redis daily counter (24h expiry) | 5,000 calls/day |

When either limit is hit, the rate limiter applies **exponential backoff**: 500ms → 1s → 2s → ... up to 30s maximum wait.

**Status check:**
```bash
docker exec ecommerce_app php artisan tinker --execute="print_r(app(App\Services\EbayRateLimiter::class)->getStatus());"
```

---

## 8. EbayListing Status Lifecycle

### Status Values

| Status | Meaning |
|--------|---------|
| `pending` | Listing queued for creation, not yet sent to eBay |
| `inventory_created` | Inventory item created on eBay (`PUT /sell/inventory/v1/inventory_item`) |
| `offer_created` | Offer created on eBay (`POST /sell/inventory/v1/offer`), not yet published |
| `published` | Listing is live on eBay (`POST /sell/inventory/v1/offer/{id}/publish`) |
| `ended` | Listing was ended on eBay (sold out, manually ended, or policy violation) |
| `out_of_stock` | Listing exists but quantity is zero |
| `error` | Last sync attempt failed; see `last_error_message` |
| `deleted` | Listing record soft-deleted locally |

### Transition Diagram

```
pending
  |
  v
inventory_created  (after successful inventory item upsert)
  |
  v
offer_created      (after successful offer creation)
  |
  v
published          (after successful publish)
  |         \
  |          v
  |        ended / out_of_stock
  |
  v
error  <--- any failure at any stage
  |
  v (retry)
pending  (reset by RetryFailedEbaySync or manual retry)
```

A listing can transition from `published` back to `error` if a subsequent sync or update fails.

### Key Model Methods

```php
$listing->markSynced();          // sets last_synced_at, resets consecutive_errors
$listing->recordError($message); // increments consecutive_errors, sets last_error_message
$listing->hasProductChanged();   // compares product_hash to detect if re-sync is needed
$listing->generateProductHash(); // builds a hash of price, quantity, title, etc.
```

### Key Scopes

```php
EbayListing::stale()       // not synced in 24+ hours
EbayListing::retryable()   // status=error, consecutive_errors < 5
EbayListing::published()   // status=published
EbayListing::withErrors()  // status=error
```

---

## 9. EbaySyncLog Audit Trail

**File:** `app/Models/Ebay/EbaySyncLog.php`

Every eBay API interaction is logged to `ebay_sync_logs`.

### Operation Types

| Operation | Description |
|-----------|-------------|
| `create_inventory` | Created inventory item via Inventory API |
| `update_inventory` | Updated inventory item |
| `create_offer` | Created offer via Inventory API |
| `update_offer` | Updated offer (price change) |
| `publish_offer` | Published offer (made listing live) |
| `end_listing` | Ended a live listing |
| `sync_quantity` | Pushed a quantity update |
| `sync_price` | Pushed a price update |
| `import` | Imported a listing from eBay store |
| `retry` | Retry attempt on a failed listing |

### Status Values

| Status | Meaning |
|--------|---------|
| `success` | API call completed without error |
| `error` | API call failed; `error_message` and `error_code` set |
| `warning` | API call succeeded but returned warnings |
| `skipped` | Update skipped because no change was detected |
| `pending` | Operation queued but not yet executed |

### Direction

| Direction | Meaning |
|-----------|---------|
| `outbound` | App pushed data to eBay |
| `inbound` | App pulled data from eBay (import) |

### Log Sanitization

Request and response payloads are automatically sanitized before storage to remove sensitive fields (OAuth tokens, passwords, API keys).

### Query Examples

```bash
# Recent sync logs for a listing
docker exec ecommerce_app php artisan tinker --execute="App\Models\Ebay\EbaySyncLog::where('ebay_listing_id', ID)->latest()->take(10)->get(['operation','direction','status','error_message','created_at']);"

# All errors in the last 24 hours
docker exec ecommerce_app php artisan tinker --execute="App\Models\Ebay\EbaySyncLog::where('status','error')->where('created_at','>=',now()->subDay())->count();"
```

---

## 10. Debugging Commands

### Check listing status

```bash
docker exec ecommerce_app php artisan tinker --execute="App\Models\Ebay\EbayListing::where('product_id', 'UUID')->first()?->only(['status', 'consecutive_errors', 'last_error_message', 'last_synced_at']);"
```

### Check recent sync logs for a listing

```bash
docker exec ecommerce_app php artisan tinker --execute="App\Models\Ebay\EbaySyncLog::where('ebay_listing_id', ID)->latest()->take(10)->get(['operation','status','error_message','created_at']);"
```

### Reset listings stuck in error state (consecutive_errors < 5)

```bash
docker exec ecommerce_app php artisan tinker --execute="App\Models\Ebay\EbayListing::withErrors()->where('consecutive_errors', '<', 5)->update(['status' => 'pending', 'consecutive_errors' => 0]);"
```

### Manually trigger a scheduled task

```bash
docker exec ecommerce_app php artisan tinker --execute="App\Jobs\Ebay\ProcessEbayScheduledTasks::dispatch('update_stale');"
docker exec ecommerce_app php artisan tinker --execute="App\Jobs\Ebay\ProcessEbayScheduledTasks::dispatch('retry_failed');"
docker exec ecommerce_app php artisan tinker --execute="App\Jobs\Ebay\ProcessEbayScheduledTasks::dispatch('sync_quantities');"
```

### Check rate limiter status

```bash
docker exec ecommerce_app php artisan tinker --execute="print_r(app(App\Services\EbayRateLimiter::class)->getStatus());"
```

### Count listings by status

```bash
docker exec ecommerce_app php artisan tinker --execute="App\Models\Ebay\EbayListing::selectRaw('status, count(*) as total')->groupBy('status')->get()->toArray();"
```

### Find listings with too many errors (excluded from auto-retry)

```bash
docker exec ecommerce_app php artisan tinker --execute="App\Models\Ebay\EbayListing::where('consecutive_errors', '>=', 5)->get(['id','product_id','status','last_error_message'])->toArray();"
```

---

## 11. Critical Files Reference

| Component | Path |
|-----------|------|
| Scheduled tasks job | `app/Jobs/Ebay/ProcessEbayScheduledTasks.php` |
| Inventory update job | `app/Jobs/Ebay/UpdateEbayInventory.php` |
| Bulk sync job | `app/Jobs/Ebay/BulkSyncToEbay.php` |
| Retry job | `app/Jobs/Ebay/RetryFailedEbaySync.php` |
| Import job | `app/Jobs/Ebay/ImportEbayInventory.php` |
| ECN notification job | `app/Jobs/Ebay/ProcessEbayNotification.php` |
| Store import service | `app/Services/EbayStoreImportService.php` |
| SKU parser service | `app/Services/SkuParserService.php` |
| Inventory service | `app/Services/EbayInventoryService.php` |
| Rate limiter | `app/Services/EbayRateLimiter.php` |
| API exception | `app/Services/EbayApiException.php` |
| API controller | `app/Http/Controllers/Api/Ebay/EbayApiController.php` |
| Web controller | `app/Http/Controllers/Marketplaces/EbayMarketplaceController.php` |
| ECN webhook controller | `app/Http/Controllers/Ebay/EbayNotificationController.php` |
| EbayListing model | `app/Models/Ebay/EbayListing.php` |
| EbaySyncLog model | `app/Models/Ebay/EbaySyncLog.php` |
| Import page (frontend) | `resources/js/Pages/Marketplaces/Ebay/Import.vue` |
| SKU parser config (frontend) | `resources/js/Components/Marketplaces/SkuParserConfig.vue` |
