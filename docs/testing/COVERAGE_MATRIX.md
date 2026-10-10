# FlipStack Test Coverage Matrix

**Last Updated:** 2026-04-15
**Master Test Plan:** `docs/testing/MASTER_TEST_PLAN.md`

---

## Summary

| Metric | Value |
|--------|-------|
| Total automated test files | 66 |
| Feature test files | 52 |
| Unit test files | 14 |
| Domains with test coverage | 18 / 22 |
| Domains with zero coverage | 4 (Part-Out, Printing, Dashboard, API) |
| Mobile test files | 0 (1 placeholder) |
| Test documentation files | 90 (All phases complete) |

---

## Coverage by Domain

| # | Domain | Risk | Test Plan | Cases Documented | Automated Tests | Test Files | Coverage |
|---|--------|------|-----------|-----------------|-----------------|------------|----------|
| 1 | Fulfillment | Critical | [x] | 54 (8 files) | 7 feature | `Fulfillment/` (7 files) | Moderate |
| 2 | Inventory | Critical | [x] | 72 (9 files) | 9 feature, 1 unit | `Inventory/` (9), `Unit/Services/Inventory/` (1) | Good |
| 3 | Marketplace - eBay | Critical | [x] | 34 (5 files) | 3 feature, 3 unit | `Ebay/` (1), `Marketplaces/` (1), `Integration/` (2), `Jobs/` (1), `Unit/` (3) | Moderate |
| 4 | Marketplace - Shopify | Critical | [x] | 38 (6 files) | 8 feature, 4 unit | `Shopify/` (1), `Marketplaces/` (1), `Integration/` (1), `Jobs/` (5), `Unit/` (4) | Good |
| 5 | Intake Queue | High | [x] | 26 (3 files) | 3 feature, 1 unit | `IntakeQueue/` (3), `Unit/Models/` (1) | Moderate |
| 6 | Procurement | High | [x] | 18 (2 files) | 1 feature | `Procurement/` (1) | Low |
| 7 | Analytics | Medium | [x] | 21 (3 files) | 2 feature | `Analytics/` (2) | Low |
| 8 | Pricing | High | [x] | 12 (1 file) | 1 feature | `Pricing/` (1) | Low |
| 9 | Customer | Medium | [x] | 10 (1 file) | 1 feature | `Customers/` (1) | Low |
| 10 | Communication | Medium | [x] | 8 (1 file) | 1 feature | `Communication/` (1) | Low |
| 11 | Marketing | Medium | [x] | 10 (1 file) | 1 feature | `Marketing/` (1) | Low |
| 12 | Auth & Organization | Critical | [x] | 28 (4 files) | 2 feature, 1 mail | `AuthenticationTest` (1), `TeamManagement` (1), `Mail/` (1) | Low |
| 13 | Supplier | Medium | [x] | 14 (2 files) | 1 feature | `Supplier/` (1) | Low |
| 14 | Catalog & Bundles | Medium | [x] | 10 (1 file) | 1 feature | `Inventory/BundleTest` (1) | Low |
| 15 | Part-Out | Medium | [x] | 10 (1 file) | 0 | None | None |
| 16 | Warehouse | Medium | [x] | 8 (1 file) | 1 feature, 1 unit | `Inventory/WarehouseLayout` (1), `Unit/Services/Inventory/` (1) | Low |
| 17 | Printing | Low | [x] | 12 (2 files) | 0 | None | None |
| 18 | Dashboard | Low | [x] | 8 (1 file) | 0 | None | None |
| 19 | Notifications | Low | [x] | 12 (2 files) | 1 feature | `Notifications/` (1) | Minimal |
| 20 | Settings | Low | [x] | 12 (2 files) | 1 feature | `Settings/` (1) | Minimal |
| 21 | Mobile App | High | [x] | 54 (8 files) | 0 | `mobile/test/widget_test.dart` (placeholder) | None |
| 22 | API Endpoints | High | [x] | 58 (8 files) | 0 | None (tested indirectly via feature tests) | None |

---

## Coverage Legend

| Level | Criteria |
|-------|----------|
| **Good** | Core workflows automated, edge cases partially covered |
| **Moderate** | Happy paths automated, significant gaps in edge cases |
| **Low** | 1-2 test files, minimal scenario coverage |
| **Minimal** | Single test file with basic smoke test only |
| **None** | No automated tests exist |

---

## Detailed File Mapping

### Fulfillment

| Feature | Test File | Test Count | Gaps |
|---------|-----------|------------|------|
| Shopify order sync | `Feature/Fulfillment/ShopifyOrderSyncTest.php` | | Order creation, inventory reservation |
| Amazon order sync | `Feature/Fulfillment/AmazonOrderSyncTest.php` | | Amazon-specific order fields |
| Return sync | `Feature/Fulfillment/MarketplaceReturnSyncTest.php` | | Return processing workflow |
| Multi-marketplace tracking | `Feature/Fulfillment/MultiMarketplaceTrackingTest.php` | | Tracking upload across marketplaces |
| Routing rules | `Feature/Fulfillment/RoutingRuleTest.php` | | Rule evaluation, priority ordering |
| Shipping rules | `Feature/Fulfillment/ShippingRuleTest.php` | | Carrier/service auto-selection |
| ShippingEasy service | `Feature/Fulfillment/ShippingEasyServiceTest.php` | | API integration |
| ShippingEasy webhooks | `Feature/Fulfillment/ShippingEasyWebhookTest.php` | | Webhook verification |
| **MISSING: Pick workflow** | N/A | 0 | Entire pick flow untested |
| **MISSING: Pack workflow** | N/A | 0 | Entire pack flow untested |
| **MISSING: Ship workflow** | N/A | 0 | Ship confirmation untested |
| **MISSING: eBay order sync** | N/A | 0 | eBay-specific order import |

### Inventory

| Feature | Test File | Gaps |
|---------|-----------|------|
| Product CRUD | `Feature/Inventory/ProductManagementTest.php` | |
| Categories | `Feature/Inventory/CategoryManagementTest.php` | |
| Stock operations | `Feature/Inventory/InventoryOperationsTest.php` | |
| Suppliers | `Feature/Inventory/SupplierManagementTest.php` | |
| Product deletion | `Feature/Inventory/ProductDeletionTest.php` | |
| Batch tracking | `Feature/Inventory/BatchTrackingTest.php` | |
| Warehouse layout | `Feature/Inventory/WarehouseLayoutTest.php` | |
| Bundles | `Feature/Inventory/BundleTest.php` | |
| Marketplace sync | `Feature/Inventory/MarketplaceInventorySyncTest.php` | |
| **MISSING: Import/Export** | N/A | Bulk import, CSV export |
| **MISSING: Bulk operations** | N/A | Mass update, mass delete |
| **MISSING: Product images** | N/A | Upload, reorder, delete |
| **MISSING: Location management** | N/A | CRUD for inventory locations |

### Auth & Organization

| Feature | Test File | Gaps |
|---------|-----------|------|
| Authentication | `Feature/AuthenticationTest.php` | 2FA, password reset, session management |
| Team management | `Feature/TeamManagementTest.php` | Team CRUD |
| Invitation mail | `Feature/Mail/OrganizationOwnerInvitationMailTest.php` | Mail content |
| **MISSING: Role/permission enforcement** | N/A | RBAC across all routes |
| **MISSING: Organization setup** | N/A | Org creation, settings |
| **MISSING: User invitation flow** | N/A | Accept invite, onboarding |
| **MISSING: 2FA flow** | N/A | Enable, verify, recovery |

---

## Gap Analysis (Ranked by Business Risk)

### Critical Gaps (revenue/security impact)

1. **eBay order import** — No test for eBay-specific order sync (only Shopify/Amazon tested)
2. **Pick/Pack/Ship workflow** — Core fulfillment flow has no dedicated tests
3. **Role/permission enforcement** — No tests verify RBAC across routes
4. **Multi-tenant isolation** — No dedicated tests verify org data separation
5. **Mobile app** — Zero test coverage for 38 Flutter screens

### High Gaps (operational impact)

6. **API endpoint contracts** — Mobile depends on API stability but no contract tests exist
7. **Procurement receiving** — Only PO creation tested, not receiving workflow
8. **Repricing engine** — Single test file for complex rule evaluation
9. **Part-Out system** — Recently built (2026-04-02), zero tests

### Medium Gaps (feature completeness)

10. **Import/Export** — Bulk operations untested
11. **Dashboard data endpoints** — Live data feeds untested
12. **Notification preferences** — User preference management untested
13. **Printing system** — Label generation untested
14. **Warehouse bin operations** — Scan-to-bin untested

---

## Update Log

| Date | Change | Author |
|------|--------|--------|
| 2026-04-15 | Initial coverage matrix created | -- |
