# Marketplace - Shopify Test Plan

## Document ID: FLIPSTACK-TP-MKT-SHOPIFY-001

**Version:** 1.0
**Last Updated:** 2026-04-15
**Status:** Approved

---

## 1. Overview

### 1.1 Scope

Shopify integration: OAuth, product sync (create/update/archive), inventory push, webhook handling, store import, collection mapping, product matching, and auto-sync.

### 1.2 Related Documentation

| Resource | Path |
|----------|------|
| Feature docs | `docs/SHOPIFY_INTEGRATION.md`, `docs/SHOPIFY_API_REFERENCE.md`, `docs/SHOPIFY_SANDBOX_TESTING.md` |
| Existing tests | `tests/Feature/Shopify/`, `tests/Feature/Jobs/` (7 Shopify files), `tests/Feature/Marketplaces/ShopifyMarketplaceTest.php`, `tests/Unit/` (4 Shopify files) |
| Test cases | `docs/testing/cases/marketplace/TC-MKT-006 through TC-MKT-008` |

---

## 2. Features Under Test

| # | Feature | Controller | Route Prefix | Priority |
|---|---------|------------|-------------|----------|
| 1 | OAuth install/callback/disconnect | ShopifyOAuthController | `shopify.auth` | P0 |
| 2 | Product sync (single + bulk) | ShopifyMarketplaceController@syncProduct,syncBulk | `marketplaces.shopify` | P0 |
| 3 | Inventory push to Shopify | ShopifyInventoryService + ShopifyMarketplaceAdapter | - | P0 |
| 4 | Webhook handling (HMAC + dispatch) | ShopifyWebhookController | `shopify.webhooks` | P0 |
| 5 | Store import | ShopifyMarketplaceController@startImport | `marketplaces.shopify.import` | P1 |
| 6 | Product matching | ShopifyProductMatchController | `marketplaces.shopify.match` | P1 |
| 7 | Collection mapping | ShopifyMarketplaceController@categorySettings | `marketplaces.shopify.category-settings` | P2 |
| 8 | Auto-sync toggle | ShopifyMarketplaceController@toggleAutoSync | `marketplaces.shopify.auto-sync` | P2 |

---

## 3. Test Strategy

### 3.1 Unit Tests

| Unit | Test File | Status |
|------|-----------|--------|
| ShopifyProductService | `tests/Unit/Services/ShopifyProductServiceTest.php` | Exists |
| ShopifyRateLimiter | `tests/Unit/Services/ShopifyRateLimiterTest.php` | Exists |
| ShopifyStoreImportService | `tests/Unit/Services/ShopifyStoreImportServiceTest.php` | Exists |
| ShopifyListing model | `tests/Unit/Models/ShopifyListingTest.php` | Exists |
| MarketplaceSyncRuleEngine | `tests/Unit/Services/MarketplaceSyncRuleEngineTest.php` | Exists |

### 3.2 Feature Tests

| Workflow | Test File | Status |
|----------|-----------|--------|
| OAuth connect | `tests/Feature/Integration/ShopifyConnectTest.php` | Exists |
| Webhook handling | `tests/Feature/Shopify/ShopifyWebhookTest.php` | Exists |
| Marketplace page | `tests/Feature/Marketplaces/ShopifyMarketplaceTest.php` | Exists |
| Sync job | `tests/Feature/Jobs/SyncProductToShopifyTest.php` | Exists |
| Bulk sync job | `tests/Feature/Jobs/BulkSyncToShopifyTest.php` | Exists |
| Import job | `tests/Feature/Jobs/ImportShopifyProductsTest.php` | Exists |
| Webhook job | `tests/Feature/Jobs/ProcessShopifyWebhookTest.php` | Exists |
| Retry job | `tests/Feature/Jobs/RetryFailedShopifySyncTest.php` | Exists |
| Inventory update | `tests/Feature/Jobs/UpdateShopifyInventoryTest.php` | Exists |
| Order sync | `tests/Feature/Fulfillment/ShopifyOrderSyncTest.php` | Exists |
| **Product matching flow** | - | **Needed** |
| **Collection mapping** | - | **Needed** |
| **Multi-location inventory** | - | **Needed** |

---

## 4. Test Cases

| Test Case ID | Title | File |
|--------------|-------|------|
| TC-MKT-006 | Shopify Connect (OAuth) | `cases/marketplace/TC-MKT-006-shopify-connect-oauth.md` |
| TC-MKT-007 | Shopify Product Sync | `cases/marketplace/TC-MKT-007-shopify-product-sync.md` |
| TC-MKT-008 | Shopify Webhooks | `cases/marketplace/TC-MKT-008-shopify-webhooks.md` |
| TC-MKT-012 | E2E Sync Lifecycle (cross-marketplace) | `cases/marketplace/TC-MKT-012-marketplace-sync-e2e-lifecycle.md` |
| TC-MKT-013 | Multi-Tenant Sync Isolation | `cases/marketplace/TC-MKT-013-multi-tenant-sync-isolation.md` |

---

## 5. Coverage Goals

| Metric | Current | Target |
|--------|---------|--------|
| OAuth flow | Connect tested | Add disconnect, token handling |
| Product sync | Job-level tests exist | Add controller-level flow tests |
| Inventory push | Job test exists | Add multi-location zeroing test |
| Webhooks | HMAC + dispatch tested | Add per-topic processing tests |
| Product matching | 0% | Full match/confirm/reject/pair flow |
| Collection mapping | 0% | Settings + mapping CRUD |
