# Product Management UI

Frontend architecture for product management, marketplace integration, and inventory operations. Documents what exists today and what needs to be built.

## Table of Contents

1. [Current Pages Inventory](#current-pages-inventory)
2. [Missing Pages](#missing-pages)
3. [Component Architecture](#component-architecture)
4. [Real-Time Updates](#real-time-updates)

---

## Current Pages Inventory

### Product CRUD

| Page | Path | Status |
|------|------|--------|
| Product Index (grid/list) | `resources/js/Pages/Inventory/Products/Index.vue` | Exists |
| Product Show (detail) | `resources/js/Pages/Inventory/Products/Show.vue` | Exists |
| Product Create | `resources/js/Pages/Inventory/Products/Create.vue` | Exists |
| Product Edit | `resources/js/Pages/Inventory/Products/Edit.vue` | **MISSING** |

### Inventory Management

Located in `resources/js/Pages/Inventory/`:

| Page | Description |
|------|-------------|
| Dashboard | Inventory overview, key metrics |
| Analytics | Inventory analytics and reporting |
| Movements | Inventory movement history |
| Locations | Warehouse/location management |
| Categories | Product category management |
| Bulk Operations | Bulk inventory actions |

### eBay Marketplace

Located in `resources/js/Pages/Marketplaces/Ebay/`:

| Page | File | Description |
|------|------|-------------|
| Hub/Dashboard | `Index.vue` | eBay integration overview |
| Setup | `Setup.vue` | eBay account connection and OAuth |
| Import | `Import.vue` | Import existing eBay store listings |
| Listings | `Listings.vue` | Active eBay listings management |
| Products | `Products.vue` | Products available for eBay listing |
| Logs | `Logs.vue` | eBay sync and error logs |
| Policies | `Policies.vue` | eBay business policies (shipping, return, payment) |
| Policy Rules | `PolicyRules.vue` | Policy rule configuration |

### Shopify

Located in `resources/js/Pages/Shopify/`:

| Page | File | Description |
|------|------|-------------|
| Products | `Products/Index.vue` | Shopify product listings |
| Sync Logs | `SyncLogs/Index.vue` | Shopify sync history |
| Settings | `Settings/Index.vue` | Shopify connection settings |

### Intake Queue

| Page/Component | Path | Description |
|----------------|------|-------------|
| Queue Index | `resources/js/Pages/IntakeQueue/Index.vue` | Queue item list and status overview |
| Queue Create | `resources/js/Pages/IntakeQueue/Create.vue` | Add items to the intake queue |
| Verification Wizard | `resources/js/Components/IntakeQueue/VerificationWizard.vue` | 4-step wizard: Product Info, Pricing/Listing, Photos, Review |
| eBay Policy Selector | `resources/js/Components/IntakeQueue/EbayPolicySelector.vue` | eBay policy selection within wizard |
| Shipping Rate Selector | `resources/js/Components/IntakeQueue/ShippingRateSelector.vue` | Shipping rate configuration |

### Organization/Settings

| Page | Description |
|------|-------------|
| Integrations Tab | `resources/js/Pages/Organization/Partials/IntegrationsTab.vue` - Connect eBay, Shopify accounts |

---

## Missing Pages

Prioritized list of pages that need to be built.

### 1. Product Edit Page (Critical)

**Why**: Backend support exists (`ProductController@edit` returns the edit view), but `resources/js/Pages/Inventory/Products/Edit.vue` does not exist. Users cannot edit products after creation through the UI.

**Scope**:
- Form matching `Create.vue` fields but pre-populated with existing product data
- All fields editable: title, description, price, quantity, condition, brand, category, images
- On save, detect which fields changed for selective marketplace sync
- Show marketplace listing status for this product (listed on eBay? Shopify? with links)

**Backend**: `ProductController@edit` and `ProductController@update` should already handle the Inertia response and form submission. Verify they pass all required data (product + relationships + marketplace listings).

### 2. Marketplace Hub Dashboard (High)

**Why**: No unified view across all marketplaces. Users must visit eBay and Shopify sections separately to understand their cross-platform state.

**Scope**:
- Aggregate stats: total active listings per platform, total revenue, error counts
- Recent sync activity across all platforms
- Listings needing attention (errors, stale, needs review)
- Quick-action buttons: sync all, view errors, import

**Backend**: Needs a `MarketplaceHubController` that queries across eBay and Shopify listing tables and aggregates stats.

**Suggested location**: `resources/js/Pages/Marketplaces/Hub/Index.vue`

### 3. Pricing Management (High)

**Why**: No way to do bulk price adjustments or view pricing across marketplaces.

**Scope**:
- Product table with current price and per-marketplace listed price
- Bulk price adjustment tool: select products, choose adjustment type, preview, apply
- Price comparison view: Product price vs what's live on each marketplace
- Price change history (once tracking is implemented)

**Backend**: Needs `PricingController` and `PricingService`.

**Suggested location**: `resources/js/Pages/Pricing/Index.vue`

### 4. Listing Templates (Medium)

**Why**: Database tables exist (`listing_templates`, `product_listings`) but no models or UI. Templates would let users create reusable listing configurations.

**Scope**:
- Template CRUD: create templates with default values for description format, shipping policies, return policies
- Apply template to products (bulk or individual)
- Per-marketplace template variants (eBay template vs Shopify template)

**Backend**: Needs `ListingTemplate` model, `ListingTemplateController`.

**Suggested location**: `resources/js/Pages/Listings/Templates/`

### 5. Cross-Platform Product View (Medium)

**Why**: The current Product Show page doesn't display marketplace listing status.

**Scope**:
- Extend Product Show page (or create a dedicated tab/section)
- For each connected marketplace, show: listing status, marketplace URL, listed price, listed quantity, last sync time, any errors
- Quick actions: sync now, end listing, view on marketplace

**Backend**: Enrich `ProductResource` to include `ebayListings` and `shopifyListings` relationship data. Load these in `ProductController@show`.

### 6. Shopify Dashboard (Low)

**Why**: Shopify has basic pages (Products, SyncLogs, Settings) but no overview dashboard comparable to the eBay Hub.

**Scope**: Mirror eBay Hub layout for Shopify: connection status, listing counts, sync health, recent activity.

**Suggested location**: `resources/js/Pages/Shopify/Dashboard/Index.vue`

---

## Component Architecture

### Existing Reusable Components

Key components in `resources/js/Components/` relevant to product management:

| Component | Purpose |
|-----------|---------|
| `IntakeQueue/VerificationWizard.vue` | Multi-step wizard with validation |
| `IntakeQueue/EbayPolicySelector.vue` | eBay policy dropdown selector |
| `IntakeQueue/ShippingRateSelector.vue` | Shipping rate configuration |

### Needed Components

Components to build for the missing pages:

#### Marketplace Status Components

| Component | Purpose |
|-----------|---------|
| `MarketplaceStatusBadge.vue` | Shows listing status with color coding per platform (active=green, error=red, syncing=yellow) |
| `MarketplaceSyncIndicator.vue` | Shows last sync time with freshness indicator (green: <1hr, yellow: <24hr, red: >24hr) |
| `MarketplaceListingCard.vue` | Per-marketplace card showing: platform icon, status badge, price, quantity, link to marketplace, sync button |
| `CrossPlatformProductRow.vue` | Table row showing a product with status columns for each connected marketplace |

#### Pricing Components

| Component | Purpose |
|-----------|---------|
| `PriceComparisonDisplay.vue` | Side-by-side display of Product price vs marketplace listing prices, highlighting drift |
| `BulkPriceAdjuster.vue` | Form with adjustment type selector (%, fixed, set, round-to), value input, preview table |
| `PriceChangePreview.vue` | Shows old price -> new price for each affected product before applying |

#### General Components

| Component | Purpose |
|-----------|---------|
| `ProductQuickEdit.vue` | Inline edit for common fields (price, quantity) without navigating to full edit page |
| `SyncActionButton.vue` | Reusable button that triggers sync and shows progress/result |

### Component Location Convention

All new marketplace/product components should follow the existing convention:
- Reusable components: `resources/js/Components/{Feature}/`
- Page-specific partials: Inline in the page file or in a `Partials/` subdirectory alongside the page

---

## Real-Time Updates

### Current Setup

The application uses **Pusher** for real-time broadcasting via Laravel Echo. Broadcasting channels are defined in `routes/channels.php`.

### Existing Real-Time Features

- Notification delivery via Pusher
- Queue job status updates (intake queue processing progress)

### Planned Real-Time Features for Product Management

#### Sync Status Updates

When a marketplace sync job runs, broadcast progress to the user's session:

```
Channel: private-organization.{orgId}

Events:
  - MarketplaceSyncStarted   { product_id, marketplace, listing_id }
  - MarketplaceSyncCompleted { product_id, marketplace, listing_id, success }
  - MarketplaceSyncFailed    { product_id, marketplace, listing_id, error }
  - BulkOperationProgress    { operation_id, completed, total, errors }
```

Frontend components listen for these events and update UI state without polling:
- `MarketplaceSyncIndicator` updates last_synced_at in real-time
- `MarketplaceStatusBadge` updates status on sync completion/failure
- Bulk operation progress bar updates as individual items complete

#### Inventory Alerts

```
Events:
  - LowStockAlert    { product_id, available_quantity, threshold }
  - OutOfStockAlert   { product_id }
  - OversellWarning   { product_id, marketplace, order_id }
```

---

## Related Documentation

- [PRODUCT_MANAGEMENT_SUITE.md](PRODUCT_MANAGEMENT_SUITE.md) - Master architecture and implementation roadmap
- [MARKETPLACE_INTEGRATION_ARCHITECTURE.md](MARKETPLACE_INTEGRATION_ARCHITECTURE.md) - Backend marketplace patterns
- [INTAKE_QUEUE_SYSTEM.md](INTAKE_QUEUE_SYSTEM.md) - Intake queue frontend wizard details
