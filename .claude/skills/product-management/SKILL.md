---
name: product-management
description: Product CRUD, pricing strategies, bulk operations, import/export, categories, listing templates, images, and marketplace sync rules
user-invocable: false
---

# Product Management

## Key Files

| Area | File | Purpose |
|------|------|---------|
| **Models** | `app/Models/Catalog/Product.php` | Core product model (UUID PKs, HasUuids trait) |
| | `app/Models/Catalog/Category.php` | Nested set categories (lft/rgt/depth/path) |
| | `app/Models/Catalog/Brand.php` | Brand with logo and metadata |
| | `app/Models/Catalog/ProductImage.php` | Product images (sort order, primary). `metadata` tracks bg-removal original/processed for revert. Per-image edit (reprocess/revert/replace/sync-to-marketplaces) via `ProductImageController` — see `docs/IMAGE_PROCESSING.md` |
| | `app/Models/Listing/ListingTemplate.php` | Reusable listing templates per platform |
| | `app/Models/MarketplaceSyncRule.php` | Rule-based marketplace include/exclude |
| **Services** | `app/Services/Inventory/ProductService.php` | Product CRUD, duplication, bulk actions |
| | `app/Services/Inventory/PricingService.php` | Price calculations with strategies and rounding |
| | `app/Services/Inventory/CategoryService.php` | Category tree ops with nested set maintenance |
| | `app/Services/MarketplaceSyncRuleEngine.php` | Per-product override + rule chain + default sync decision |
| **Controllers** | `app/Http/Controllers/Inventory/ProductController.php` | Full CRUD, search, stats, bulk actions, filter presets |
| | `app/Http/Controllers/Inventory/PricingController.php` | Pricing preview and apply |
| | `app/Http/Controllers/Inventory/BulkOperationsController.php` | Bulk ops: status, category, stock, transfer, delete |
| | `app/Http/Controllers/Inventory/ImportExportController.php` | CSV/XLSX import with mapping, export with field selection |
| | `app/Http/Controllers/Inventory/CategoryController.php` | Category CRUD with nested set, tree move, stats |
| | `app/Http/Controllers/Inventory/ListingTemplateController.php` | Listing template CRUD per platform |
| **Jobs** | `app/Jobs/Inventory/BulkProductUpdate.php` | Bulk status/category/brand/price/delete (3600s timeout) |
| | `app/Jobs/Inventory/PushPriceToMarketplaces.php` | Push price changes to all marketplaces |
| | `app/Jobs/Inventory/PushListingToMarketplaces.php` | Push listing data to all marketplaces |
| **Routes** | `routes/inventory.php` | Product, template, pricing routes |

## Product Status Values

`draft`, `active`, `inactive`, `archived`

## Product Condition Options

`NEW`, `NEW_OTHER`, `NEW_WITH_DEFECTS`, `MANUFACTURER_REFURBISHED`, `CERTIFIED_REFURBISHED`, `USED_EXCELLENT`, `USED_VERY_GOOD`, `USED_GOOD`, `USED_ACCEPTABLE`, `FOR_PARTS_OR_NOT_WORKING`

## Key Product Fields

| Field Group | Fields |
|-------------|--------|
| **Pricing** | `cost_price`, `selling_price`, `msrp`, `currency` |
| **Inventory** | `available_quantity` (sellable, source of truth, **bound by product hub UI**), `stock_quantity` (legacy physical on-hand, mirrored by manual adjustments), `quantity` (legacy total), `reserved_quantity` (informational), `low_stock_threshold`, `manage_stock`, `allow_backorders` |
| **Identifiers** | `sku`, `barcode`, `upc`, `ean`, `mpn` |
| **Marketplace** | `marketplace_overrides` (JSON: `{platform: 'include'/'exclude'/'auto'}`) |
| **Condition** | `condition`, `condition_description` |

> Note: the product hub Stock Status card binds `available_quantity` (decremented on order import). `stock_quantity` is the legacy physical-on-hand column kept for warehouse semantics. See `inventory-management` skill for the full Writers reference table.

## Product Scopes

`active`, `inStock`, `priceRange`, `forOrganization`, `search` (PostgreSQL full-text), `byType`, `byCategory`, `byBrand`, `featured`, `byAnyIdentifier`, `readyForEbay`, `notOnEbay`, `onEbay`, `readyForShopify`, `onShopify`, `notOnShopify`

## Stock Status Filters (ProductController)

| Filter | Condition |
|--------|-----------|
| `in_stock` | qty > threshold |
| `low_stock` | 0 < qty <= threshold |
| `out_of_stock` | qty = 0 |
| `overstocked` | qty > threshold x 3 |

## Pricing System

### Strategies
| Strategy | Calculation |
|----------|-------------|
| `fixed` | Set to exact value |
| `percentage` | Increase/decrease by percentage |
| `cost_plus_fixed` | `cost_price + fixed_amount` |
| `cost_plus_percentage` | `cost_price * (1 + percentage)` |

### Rounding Options
`nearest_99`, `nearest_95`, `nearest_dollar`, `up_dollar`

### Flow
1. `PricingController::preview()` -- returns preview with current/new price, margin, change per product
2. `PricingController::apply()` -- calls `PricingService::apply()`, returns updated/skipped/total counts
3. Price floor enforced: `max(0, ...)` plus optional `min_price`/`max_price` bounds

## Bulk Operations

### Supported Operations
| Operation | Job Class | What It Does |
|-----------|-----------|--------------|
| `update_status` | `BulkProductUpdate` | Change status to active/inactive/archived |
| `update_category` | `BulkCategoryUpdate` | Reassign products to a category |
| `update_brand` | `BulkProductUpdate` | Change brand |
| `update_prices` | `BulkProductUpdate` | Fixed/increase_percent/decrease_percent/increase_amount/decrease_amount |
| `transfer_location` | `BulkLocationTransfer` | Move stock between locations |
| `adjust_stock` | `BulkStockAdjustment` | Add/subtract/set stock quantities |
| `delete` | `BulkProductUpdate` | Bulk delete products |

### Tracking
- `BulkOperationJob` model tracks status: `pending` -> `processing` -> `completed`/`failed`/`cancelled`
- Cancellation via `Cache::put("bulk_job_cancelled_{$jobId}", true, 3600)` -- checked each chunk
- Events: `BulkOperationProgress` (per chunk), `BulkOperationCompleted` (final) via Pusher

## Import/Export

### Import Flow
1. `uploadFile` -- CSV/XLSX (max 10MB), returns preview + suggested column mapping
2. `validateImport` -- validates with user's mapping, returns error/warning rows
3. `processImport` -- dispatches `ProcessProductImport` job, returns job_id

### Import Template Columns
Required: `sku`, `name`, `selling_price`. Optional: `description`, `category`, `brand`, `stock_quantity`, `cost_price`, `status`

### Export Flow
1. `generateExport` -- dispatches `ProcessProductExport` with field list + filters
2. `downloadExport` -- streams completed file from `BulkOperationJob.result_file_path`

## Category System

### Nested Set Implementation
Categories use `lft`/`rgt`/`depth`/`path` columns for efficient tree queries. All inserts, moves, and deletes maintain the nested set invariants.

### Delete Strategies
| Strategy | Behavior |
|----------|----------|
| `move_to_parent` | Orphaned products reassigned to parent category |
| `delete_cascade` | Delete category and all children |
| `prohibit` | Refuse if category has products or children |

### Key Methods
- `CategoryService::createCategory()` -- nested set insert
- `CategoryService::moveCategory()` -- triggers full `rebuildNestedSet()`
- `CategoryService::getCategoryTree()` -- flat collection ordered by lft
- `Category::full_path` accessor -- "Parent > Child > Grandchild"

## Marketplace Sync Rule Engine

The sync rule engine is checked by all three sync orchestrators. Product changes auto-trigger sync via `ProductObserver` -> `PushListingToMarketplaces` (listing fields), `PushPriceToMarketplaces` (price), or `EndMarketplaceListings` (deactivation). See the `marketplace-sync` skill for the full pipeline.

### Decision Chain (`MarketplaceSyncRuleEngine::shouldSync()`)
1. Check `Product.marketplace_overrides[$platform]`: `include` = sync, `exclude` = skip, `auto` = continue
2. Check active `MarketplaceSyncRule` records for org+platform, ordered by `priority ASC` -- first match wins
3. Default: sync to all platforms

### Rule Conditions
Operators: `in`, `not_in`, `between`, `greater_than`, `less_than`
Fields: `category_id`, `condition`, `selling_price`, `brand_id`/`brand`, `created_by`

### Sync Status (Product Edit Page)
`MarketplaceSyncRuleEngine::getDetailedSyncStatus($product, ['ebay', 'shopify'])` returns per-platform:
- `syncs` (bool), `reason` (`manual_include`/`manual_exclude`/`rule_include`/`rule_exclude`/`default`), `label`, matched `rule`

## Listing Templates

**Platforms:** `ebay`, `amazon`, `all`

**Template fields:** `name`, `description`, `platform`, `description_template`, `default_item_specifics` (array), `default_categories` (array), `shipping_template` (array), `return_policy_template` (array), `payment_template` (array), `pricing_strategy` (`fixed_markup`/`percentage`/`competitive`), `markup_percentage`, `fixed_markup_amount`

## Cross-Service Interactions

1. **Product edit** -> `MarketplaceSyncRuleEngine::getDetailedSyncStatus()` for sync status display
2. **Price apply** -> `PricingService::apply()` -> can trigger `PushPriceToMarketplaces` job
3. **Listing push** -> `PushListingToMarketplaces` -> `MarketplaceListingSync::pushListingToAll()`
4. **Bulk ops** -> `BulkOperationJob` tracking -> Pusher progress events -> UI updates
5. **Product create** -> `ProductService::create()` -> DB transaction with image sync

## Permissions

| Permission | Used By |
|-----------|---------|
| `inventory.view` | Product listing, show |
| `inventory.create` | Product create/store |
| `inventory.update` | Product update, pricing apply |
| `inventory.delete` | Product delete, bulk delete |
| `inventory.export` | Product export |
| `inventory.import` | Product import |
| `inventory.manage_categories` | Category CRUD |

## Bundles/Kits

### Key Files
| File | Purpose |
|------|---------|
| `app/Models/Catalog/ProductBundle.php` | Bundle definition linking a parent product to its components |
| `app/Models/Catalog/ProductBundleItem.php` | Individual component with quantity required per bundle unit |
| `app/Services/Catalog/BundleService.php` | Bundle CRUD, component management, availability calculation |

### Bundle Creation and Component Management
A bundle is a virtual product composed of multiple component products. `BundleService::createBundle()` creates the parent product and attaches components with their per-bundle quantities. Components can be added, removed, or updated after creation.

### Availability Calculation
Bundle availability is computed as: `min(floor(component.available_quantity / component.qty_per_bundle))` across all components. When any component's stock changes, the bundle's available quantity is recalculated and pushed to all marketplaces via `PushInventoryToMarketplaces`.

---

## Demand Forecasting

### Key Files
| File | Purpose |
|------|---------|
| `app/Models/Analytics/DemandForecast.php` | Forecast record with predicted demand, confidence interval |
| `app/Models/Analytics/ReorderSuggestion.php` | Suggested reorder linked to a product and forecast |
| `app/Services/Analytics/DemandForecastingService.php` | Sales velocity analysis, forecast generation, reorder suggestions |

### Sales Velocity Analysis
`DemandForecastingService` analyzes historical sales data to compute sales velocity (units/day), trend direction, and seasonality factors per product. These feed into demand forecasts with configurable lookahead periods.

### Reorder Suggestions
Based on demand forecasts, current stock levels, and supplier lead times, the service generates `ReorderSuggestion` records. Each suggestion includes the recommended order quantity, suggested order date, and the forecast that triggered it. Suggestions are linked to products and can be converted directly into Purchase Order line items.

---

## Debugging Commands

```php
// Product status distribution
Product::where('organization_id', $orgId)
  ->selectRaw("status, count(*) as count")->groupBy('status')->get();

// Products with marketplace overrides
Product::where('organization_id', $orgId)
  ->whereNotNull('marketplace_overrides')
  ->where('marketplace_overrides', '!=', '{}')->count();

// Active sync rules
MarketplaceSyncRule::where('organization_id', $orgId)
  ->where('is_active', true)->orderBy('priority')
  ->get(['name', 'marketplace', 'action', 'priority', 'conditions']);

// Products not on any marketplace
Product::where('organization_id', $orgId)->where('status', 'active')
  ->doesntHave('ebayListings')->doesntHave('shopifyListings')->count();

// Recent bulk operations
BulkOperationJob::where('organization_id', $orgId)
  ->orderByDesc('created_at')->take(5)
  ->get(['id', 'operation', 'status', 'total_items', 'processed_items', 'failed_items']);
```

## Audit Logging

| Action | Category | Severity | Metadata keys |
|--------|----------|----------|---------------|
| `product.created` | product | info | sku, source (manual|intake|csv|api), category_id |
| `product.updated` | product | info | changes |
| `product.deleted` | product | warning | sku |
| `product.bulk_imported` | product | info | count, source, filename |
| `product.merged` | product | warning | merged_into_id, conflict_fields |
| `product.price_changed` | pricing | info | old_price, new_price, source (manual|repricing) |
| `category.created` | product | info | parent_id |
| `category.updated` | product | info | changes |
| `category.deleted` | product | warning | |
| `brand.created` | product | info | |
| `brand.updated` | product | info | changes |
| `brand.deleted` | product | warning | |

Trait: models in this feature use `LogsAudit` (`app/Traits/LogsAudit.php`) for auto CRUD diff. Domain lifecycle events emitted from `app/Services/Inventory/ProductService` (replace the file-log `logActivity()` call at line ~238 with `UserActivityLog::log()`); `Product`, `Category`, `Brand` models (`LogsAudit`). Bulk imports emit ONE summary row + per-row `withoutAudit = true`. via `UserActivityLog::log(action, desc, $user, $metadata, $severity, $category)`.

KPI consumer: `UserProductivityDaily.products_listed_count` (from `product.created` where `metadata.source != 'csv'|'api'` -- listed by a human).

Spec: `docs/AUDIT_SYSTEM.md`. Enforced rule: `.claude/rules/audit-logging.md`. Tests: `use Tests\Support\AssertsAudits;` then `$this->assertAudited('action', $user, [...])`.
