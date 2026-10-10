# Fulfillment Test Plan

## Document ID: FLIPSTACK-TP-FUL-001

**Version:** 1.0
**Last Updated:** 2026-04-15
**Status:** Approved

---

## 1. Overview

### 1.1 Scope

Full order lifecycle: import, assign, pick, pack, ship, track, return. Includes routing rules, shipping rules, settings, webhook handling, and multi-marketplace tracking upload.

### 1.2 Related Documentation

| Resource | Path |
|----------|------|
| Feature documentation | `docs/FULFILLMENT_WORKFLOW.md`, `docs/FULFILLMENT_ROUTING.md` |
| Feature matrix | `docs/FEATURE_MATRIX.md#fulfillment` |
| Existing tests | `tests/Feature/Fulfillment/` (7 files) |
| Test cases | `docs/testing/cases/fulfillment/` |

### 1.3 Out of Scope

- Supplier dropship fulfillment (separate test plan)
- ShipStation/EasyPost API internals (mocked)
- Marketplace-specific order format details (covered in marketplace test plans)

---

## 2. Features Under Test

| # | Feature | Controller | Route Prefix | Vue Page | Priority |
|---|---------|------------|-------------|----------|----------|
| 1 | Order listing & filtering | OrderController@index | `fulfillment.orders` | `Fulfillment/Orders/Index` | P0 |
| 2 | Order detail & audit trail | OrderController@show,auditTrail | `fulfillment.orders` | `Fulfillment/Orders/Show` | P0 |
| 3 | Order assignment & bulk ops | OrderController@assign,bulkAssign,bulkPriority | `fulfillment.orders` | - | P0 |
| 4 | Order hold/release/cancel | OrderController@hold,release,cancel | `fulfillment.orders` | - | P1 |
| 5 | Pick workflow | PickController | `fulfillment.pick` | `Fulfillment/Pick/Index` | P0 |
| 6 | Pack workflow | PackController | `fulfillment.pack` | `Fulfillment/Pack/Index` | P0 |
| 7 | Ship workflow | ShipController | `fulfillment.ship` | `Fulfillment/Ship/Index` | P0 |
| 8 | Returns lifecycle | ReturnController | `fulfillment.returns` | `Fulfillment/Returns/Index,Show` | P1 |
| 9 | Fulfillment routing rules | RoutingController | `fulfillment.routing` | `Fulfillment/Routing/*` | P1 |
| 10 | Shipping rules engine | ShippingRuleController | `fulfillment.shipping-rules` | `Fulfillment/ShippingRules/*` | P1 |
| 11 | Tracking upload | TrackingService | - (jobs) | - | P0 |
| 12 | Webhook handling | Tracking/ShipStation/ShippingEasy webhooks | `webhooks.*` | - | P1 |
| 13 | Fulfillment settings | SettingsController | `fulfillment.settings` | `Fulfillment/Settings/Index` | P2 |

---

## 3. Test Strategy

### 3.1 Unit Tests

| Unit | Test File | Status |
|------|-----------|--------|
| FulfillmentService | `tests/Unit/Services/FulfillmentServiceTest.php` | Needed |
| ShippingRuleEngine | `tests/Unit/Services/ShippingRuleEngineTest.php` | Needed |
| FulfillmentRouter | `tests/Unit/Services/FulfillmentRouterTest.php` | Needed |
| ReturnService | `tests/Unit/Services/ReturnServiceTest.php` | Needed |
| TrackingService | `tests/Unit/Services/TrackingServiceTest.php` | Needed |

### 3.2 Feature / Integration Tests

| Workflow | Test File | Status |
|----------|-----------|--------|
| Order sync (Shopify) | `tests/Feature/Fulfillment/ShopifyOrderSyncTest.php` | Exists |
| Order sync (Amazon) | `tests/Feature/Fulfillment/AmazonOrderSyncTest.php` | Exists |
| Return sync | `tests/Feature/Fulfillment/MarketplaceReturnSyncTest.php` | Exists |
| Tracking upload | `tests/Feature/Fulfillment/MultiMarketplaceTrackingTest.php` | Exists |
| Routing rules CRUD | `tests/Feature/Fulfillment/RoutingRuleTest.php` | Exists |
| Shipping rules CRUD | `tests/Feature/Fulfillment/ShippingRuleTest.php` | Exists |
| ShippingEasy service | `tests/Feature/Fulfillment/ShippingEasyServiceTest.php` | Exists |
| ShippingEasy webhook | `tests/Feature/Fulfillment/ShippingEasyWebhookTest.php` | Exists |
| **Pick workflow** | `tests/Feature/Fulfillment/PickWorkflowTest.php` | **Needed** |
| **Pack workflow** | `tests/Feature/Fulfillment/PackWorkflowTest.php` | **Needed** |
| **Ship workflow** | `tests/Feature/Fulfillment/ShipWorkflowTest.php` | **Needed** |
| **Order management** | `tests/Feature/Fulfillment/OrderManagementTest.php` | **Needed** |
| **Returns lifecycle** | `tests/Feature/Fulfillment/ReturnLifecycleTest.php` | **Needed** |
| **eBay order sync** | `tests/Feature/Fulfillment/EbayOrderSyncTest.php` | **Needed** |

### 3.3 UI Tests

| Page | Vue Component | Test Status |
|------|---------------|-------------|
| Orders Index | `Pages/Fulfillment/Orders/Index.vue` | Manual |
| Orders Show | `Pages/Fulfillment/Orders/Show.vue` | Manual |
| Pick Queue | `Pages/Fulfillment/Pick/Index.vue` | Manual |
| Pack Queue | `Pages/Fulfillment/Pack/Index.vue` | Manual |
| Ship Queue | `Pages/Fulfillment/Ship/Index.vue` | Manual |
| Returns Index | `Pages/Fulfillment/Returns/Index.vue` | Manual |
| Returns Show | `Pages/Fulfillment/Returns/Show.vue` | Manual |
| Routing Rules | `Pages/Fulfillment/Routing/Index.vue` | Manual |
| Shipping Rules | `Pages/Fulfillment/ShippingRules/Index.vue` | Manual |
| Settings | `Pages/Fulfillment/Settings/Index.vue` | Manual |

---

## 4. Test Environment

### 4.1 Prerequisites

- Docker container `ecommerce_app` running
- PostgreSQL test database
- Queue driver: `sync`

### 4.2 Test Data Setup

| Data | Source | Notes |
|------|--------|-------|
| Organization + User | `CreatesOrganizationFixtures` trait | Admin role with fulfillment permissions |
| Orders | Order factory | Various statuses and marketplaces |
| Order Items | OrderItem factory | Linked to products |
| Shipments | Shipment factory | Various shipment statuses |
| Inventory Locations | InventoryLocation factory | Ship-from verified |
| Products | Product factory | With SKUs and stock |
| Integration Credentials | IntegrationCredential factory | eBay/Shopify/ShippingEasy |

### 4.3 External Dependencies

| Dependency | Strategy |
|------------|----------|
| EasyPost API | `Http::fake()` — mock rate/label responses |
| ShipStation API | `Http::fake()` — mock rate/label responses |
| ShippingEasy API | `Http::fake()` — mock rate/label/carrier responses |
| eBay Fulfillment API | `Http::fake()` — mock tracking upload |
| Shopify Fulfillment API | `Http::fake()` — mock tracking upload |

---

## 5. Test Cases

| Test Case ID | Title | File |
|--------------|-------|------|
| TC-FUL-001 | Order Import | `cases/fulfillment/TC-FUL-001-order-import.md` |
| TC-FUL-002 | Pick Workflow | `cases/fulfillment/TC-FUL-002-pick-workflow.md` |
| TC-FUL-003 | Pack Workflow | `cases/fulfillment/TC-FUL-003-pack-workflow.md` |
| TC-FUL-004 | Ship Workflow | `cases/fulfillment/TC-FUL-004-ship-workflow.md` |
| TC-FUL-005 | Returns | `cases/fulfillment/TC-FUL-005-returns.md` |
| TC-FUL-006 | Routing Rules | `cases/fulfillment/TC-FUL-006-routing-rules.md` |
| TC-FUL-007 | Shipping Rules | `cases/fulfillment/TC-FUL-007-shipping-rules.md` |
| TC-FUL-008 | Tracking Upload | `cases/fulfillment/TC-FUL-008-tracking-upload.md` |

---

## 6. Coverage Goals

| Metric | Current | Target |
|--------|---------|--------|
| Automated test files | 7 | 14 |
| Order lifecycle coverage | Partial (sync only) | Full (import through delivery) |
| Pick/Pack/Ship coverage | 0% | 100% of happy paths |
| Returns coverage | Partial (sync only) | Full lifecycle |
| Webhook coverage | ShippingEasy only | All 3 providers |

---

## 7. Known Issues and Risks

| Risk | Impact | Mitigation |
|------|--------|------------|
| Order status state machine is enforced by service, not DB constraints | Invalid transitions possible if service bypassed | Test state machine transitions explicitly |
| Shipping provider API rate limits | Tests could hit real APIs | Always use Http::fake() in tests |
| RMA number generation uses date + random | Uniqueness not DB-enforced | Test uniqueness in high-concurrency scenario |
| Tracking upload retries (3 attempts, 60s backoff) | Hard to test timing | Test retry count, not timing |
