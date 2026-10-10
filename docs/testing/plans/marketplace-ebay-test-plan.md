# Marketplace - eBay Test Plan

## Document ID: FLIPSTACK-TP-MKT-EBAY-001

**Version:** 1.0
**Last Updated:** 2026-04-15
**Status:** Approved

---

## 1. Overview

### 1.1 Scope

eBay marketplace integration: OAuth connection, listing creation/sync, inventory push, promoted listings, store import, policies, ECN webhooks, and SKU parser.

### 1.2 Related Documentation

| Resource | Path |
|----------|------|
| Feature docs | `docs/EBAY_LISTING_SERVICE.md`, `docs/ebay-oauth-workflow.md`, `docs/EBAY_SYNC_AND_UPDATE_SYSTEM.md` |
| Existing tests | `tests/Feature/Ebay/`, `tests/Feature/Integration/EbayConnectTest.php`, `tests/Feature/EbayStoreImportServiceTest.php`, `tests/Unit/EbaySkuLocationParserTest.php` |
| Test cases | `docs/testing/cases/marketplace/TC-MKT-001 through TC-MKT-005` |

### 1.3 Out of Scope

- eBay Browse/Finding API (used for product research, not listing management)
- eBay Seller Hub UI
- eBay sandbox environment setup

---

## 2. Features Under Test

| # | Feature | Controller | Route Prefix | Priority |
|---|---------|------------|-------------|----------|
| 1 | OAuth connect/disconnect/refresh | EbayOAuthController | `integrations.ebay` | P0 |
| 2 | Setup (marketplace ID, policies, location) | EbayMarketplaceController@setup | `marketplaces.ebay.setup` | P0 |
| 3 | Listing sync (single + bulk) | EbayMarketplaceController@syncProduct,syncBulk | `marketplaces.ebay` | P0 |
| 4 | Inventory quantity push | MarketplaceInventorySync + EbayMarketplaceAdapter | - | P0 |
| 5 | Store import | EbayMarketplaceController@startImport | `marketplaces.ebay.import` | P1 |
| 6 | ECN webhooks (challenge + notification) | EbayNotificationController | `webhooks.ebay` | P0 |
| 7 | Promoted listings campaigns | PromotedListingsController | `marketplaces.ebay.promoted` | P2 |
| 8 | Policies CRUD & sync | EbayPolicyController | `marketplaces.ebay.policies` | P1 |
| 9 | Policy rules engine | EbayPolicyRuleController | `marketplaces.ebay.policy-rules` | P2 |
| 10 | Listing retry & error handling | EbayMarketplaceController@retry* | `marketplaces.ebay` | P1 |
| 11 | Auto-sync toggle | EbayMarketplaceController@toggleAutoSync | `marketplaces.ebay.auto-sync` | P2 |
| 12 | SKU parser | EbayMarketplaceController@testSkuParser,saveSkuParser | `marketplaces.ebay.sku-parser` | P2 |

---

## 3. Test Strategy

### 3.1 Unit Tests

| Unit | Test File | Status |
|------|-----------|--------|
| EbaySkuLocationParser | `tests/Unit/EbaySkuLocationParserTest.php` | Exists |
| EbayCategoryServiceCondition | `tests/Unit/Services/EbayCategoryServiceConditionTest.php` | Exists |
| EbayStoreImportService | `tests/Unit/Services/EbayStoreImportServiceTest.php` | Exists |
| EbayMarketplaceAdapter | `tests/Unit/Services/EbayMarketplaceAdapterTest.php` | Needed |
| EbayPolicyRulesEngine | `tests/Unit/Services/EbayPolicyRulesEngineTest.php` | Needed |

### 3.2 Feature Tests

| Workflow | Test File | Status |
|----------|-----------|--------|
| OAuth connect | `tests/Feature/Integration/EbayConnectTest.php` | Exists |
| Import prompt | `tests/Feature/Integration/EbayImportPromptTest.php` | Exists |
| Store import | `tests/Feature/EbayStoreImportServiceTest.php` | Exists |
| Promoted listings | `tests/Feature/Ebay/PromotedListingTest.php` | Exists |
| Marketplace page | `tests/Feature/Marketplaces/EbayMarketplaceTest.php` | Exists |
| Import job | `tests/Feature/Jobs/ImportEbayInventoryTest.php` | Exists |
| **Listing creation flow** | - | **Needed** |
| **Inventory push to eBay** | - | **Needed** |
| **ECN webhook handling** | - | **Needed** |
| **Policy CRUD & sync** | - | **Needed** |

### 4.3 External Dependencies

| Dependency | Strategy |
|------------|----------|
| eBay OAuth API | `Http::fake()` — mock token exchange/refresh |
| eBay Inventory API | `Http::fake()` — mock createOrReplaceInventoryItem, createOffer, publishOffer |
| eBay Sell API | `Http::fake()` — mock listing/policy endpoints |
| eBay Notification API | Manual HMAC generation for webhook tests |

---

## 5. Test Cases

| Test Case ID | Title | File |
|--------------|-------|------|
| TC-MKT-001 | eBay Connect (OAuth) | `cases/marketplace/TC-MKT-001-ebay-connect-oauth.md` |
| TC-MKT-002 | eBay Listing Sync | `cases/marketplace/TC-MKT-002-ebay-listing-sync.md` |
| TC-MKT-003 | eBay Inventory Sync | `cases/marketplace/TC-MKT-003-ebay-inventory-sync.md` |
| TC-MKT-004 | eBay Promoted Listings | `cases/marketplace/TC-MKT-004-ebay-promoted-listings.md` |
| TC-MKT-005 | eBay Store Import | `cases/marketplace/TC-MKT-005-ebay-store-import.md` |
| TC-MKT-012 | E2E Sync Lifecycle (cross-marketplace) | `cases/marketplace/TC-MKT-012-marketplace-sync-e2e-lifecycle.md` |
| TC-MKT-013 | Multi-Tenant Sync Isolation | `cases/marketplace/TC-MKT-013-multi-tenant-sync-isolation.md` |

---

## 6. Coverage Goals

| Metric | Current | Target |
|--------|---------|--------|
| OAuth flow tested | Yes (connect) | Add disconnect, refresh, token expiry |
| Listing creation flow | No | Full: pending -> inventory_created -> offer_created -> published |
| Inventory push | No | Push qty to eBay via Offer API |
| ECN webhooks | No | Challenge + notification with HMAC |
| Policies | No | CRUD + sync + rules engine |
