# FlipStack Feature Matrix

> Single source of truth for feature coverage across web, mobile API, and mobile app.
> Supersedes `MOBILE_GAP_ANALYSIS.md` (deprecated).

**Last updated:** 2026-06-06

## Coverage Summary

| Metric | Value |
|--------|-------|
| Web app pages (Vue) | 190 |
| Mobile app screens (Flutter) | 50 |
| Active mobile API route groups | 20 |
| Commented-out API route groups (scaffolded) | 6 |
| Mobile coverage (mobile-relevant features) | ~97% |
| Mobile coverage (all web features) | ~52% |

> **2026-06-06 refresh:** Part-Out and Label Printing were already shipped on mobile
> (`features/part_out/`, `features/printing/`) and had been mis-marked as not-on-mobile.
> Server-side push (FCM) dispatch is also built — see Notifications.
>
> **2026-06-06 build (this session):** Shipped 5 backlog gaps end-to-end — **PO receiving**,
> **labeling work orders**, **buyer messaging**, **warehouse bins scan/lookup**, and
> **bundles + eBay listing mgmt**. New mobile controllers: `MobileReceivingController`,
> `MobileMessageController`, `MobileBinController`, `MobileBundleController` (+ existing
> `MobileLabelingController` wired to a new Flutter screen). New Flutter slices:
> `features/{receiving,labeling,messaging,bins,bundles}` + an eBay listings screen.
> 18 mobile-API backend tests added, all green. Also fixed a latent Sanctum bug:
> `InventoryMovement.created_by` was null on mobile receiving (now threaded through
> `ReceivingService`). Remaining mobile gaps are now back-office only (CRM, marketing,
> analytics dashboards, supplier portal, repricing, P&L).

## Executive Summary

| Domain | Web | Mobile API | Mobile App | Gap |
|--------|-----|-----------|------------|-----|
| Authentication & Account | Full | Full | Full | -- |
| Fulfillment (Pick/Pack/Ship/Returns/Orders) | Full | Full | Full | -- |
| Inventory (Products, Stock Ops, Categories, Locations) | Full | Full | Full | -- |
| Inventory Alerts | Full | Full | Full | -- |
| Inventory Analytics | Full | Full | Full | -- |
| Product Intake (Barcode Scan + Submit) | Full | Full | Full | -- |
| Part-Out (Disassembly Work Orders) | Full | Full | Full | -- |
| Label Printing | Full | Full | Full | Single/batch print + printer discovery on mobile |
| Dashboard | Full | Full | Full | -- |
| Notifications (incl. server-side FCM dispatch) | Full | Full | Full | FCM client (device handling) still TODO |
| Marketplace Status | Full | Full | Full | -- |
| eBay Integration (Full) | Full | Full | Partial | Mobile now lists + updates (qty/price) + retries listings; OAuth/policies/promoted still desktop |
| Shopify Integration | Full | None | None | Desktop-only |
| Product Intake Queue (Processing) | Full | None | None | Desktop-only |
| Procurement Receiving (PO receive) | Full | Full | Full | Mobile receive + scan-verify + complete (`MobileReceivingController`) |
| Procurement (PO CRUD / suppliers) | Full | None | None | Desktop-only |
| Supplier Portal | Full | None | None | Desktop-only |
| Customer CRM | Full | None | None | Desktop-only |
| Email Marketing | Full | None | None | Desktop-only |
| Buyer Communication | Full | Full | Full | Mobile inbox + thread + reply (`MobileMessageController`, `features/messaging/`) |
| Repricing | Full | None | None | Desktop-only |
| Demand Forecasting | Full | None | None | Desktop-only |
| Profit & Loss | Full | None | None | Desktop-only |
| Fulfillment Routing & Shipping Rules | Full | None | None | Desktop-only |
| Warehouse Bins (scan/lookup/assign) | Full | Full | Full | Mobile scan bin -> contents + place product (`MobileBinController`, `features/bins/`) |
| Warehouse Zones (layout admin) | Full | None | None | Desktop-only (zone/bin CRUD + bulk generate) |
| Labeling Work Orders | Full | Full | Full | Mobile label/scan-bin/skip/complete (`features/labeling/`) |
| Bundles | Full | Full | Full | Mobile availability view (`MobileBundleController`, `features/bundles/`); create/edit still desktop |
| Organization, Teams & Roles | Full | Scaffolded | None | API commented out in api.php |
| Settings & Currency | Full | None | None | Desktop-only |
| Onboarding | Full | Scaffolded | None | API commented out in api.php |
| Scheduled Listings & Cross-listing | Full | None | None | Desktop-only |
| Bulk Operations & Import/Export | Full | None | None | Desktop-only |
| Admin (AI Prompts, Tiers, Reviews) | Full | None | None | Desktop-only |

---

## Detailed Feature Breakdown

### 1. Authentication & Account

| Feature | Web | Mobile API | Mobile App | Notes |
|---------|-----|-----------|------------|-------|
| Email/password login | Full | Full | Full | `POST /auth/login` |
| Registration | Full | Full | Full | `POST /auth/register` |
| Two-factor authentication | Full | Full | Full | `POST /auth/two-factor-challenge` |
| Password reset | Full | Full | Full | `POST /auth/forgot-password` |
| Token refresh | -- | Full | Full | `POST /auth/refresh` |
| Profile view/edit | Full | Full | Full | `GET/PUT /profile` |
| Change password | Full | Full | Full | `PUT /profile/password` |
| Active sessions | Full | Full | Full | `GET /profile/sessions` |
| FCM token registration | -- | Full | Full | `POST /auth/fcm-token` |

**Web pages:** `Pages/Auth/`, `Pages/Profile/` (8 pages)
**Mobile screens:** `features/authentication/` (5 screens), `features/profile/` (4 screens)

---

### 2. Fulfillment

#### 2a. Orders

| Feature | Web | Mobile API | Mobile App | Notes |
|---------|-----|-----------|------------|-------|
| Order list with filters | Full | Full | Full | `GET /fulfillment/orders` |
| Order detail | Full | Full | Full | `GET /fulfillment/orders/{id}` |
| Assign order | Full | Full | Full | `POST /fulfillment/orders/{id}/assign` |
| Hold/release order | Full | Full | Full | `POST /fulfillment/orders/{id}/hold` |
| Cancel order | Full | None | Partial | Mobile service has it, no API route |
| Add tracking | Full | None | Partial | Mobile service has it, no API route |
| Order notes | Full | None | Partial | Mobile service has it, no API route |

**Web pages:** `Pages/Fulfillment/Orders/` (4 pages)
**Mobile screens:** `features/orders/` (2 screens)
**Docs:** [FULFILLMENT_WORKFLOW.md](./FULFILLMENT_WORKFLOW.md)

#### 2b. Picking

| Feature | Web | Mobile API | Mobile App | Notes |
|---------|-----|-----------|------------|-------|
| Pick queue list | Full | Full | Full | `GET /fulfillment/pick` |
| Start picking | Full | Full | Full | `POST /fulfillment/pick/{id}/start` |
| Barcode scan verify | Full | Full | Full | `POST /fulfillment/pick/items/{id}/scan-verify` |
| Mark item picked | Full | Full | Full | `POST /fulfillment/pick/items/{id}/pick` |
| Mark item short | Full | Full | Full | `POST /fulfillment/pick/items/{id}/short` |
| Complete picking | Full | Full | Full | `POST /fulfillment/pick/{id}/complete` |

**Web pages:** `Pages/Fulfillment/Picking/` (2 pages)
**Mobile screens:** `features/picking/` (2 screens)

#### 2c. Packing

| Feature | Web | Mobile API | Mobile App | Notes |
|---------|-----|-----------|------------|-------|
| Pack queue | Full | Full | Full | `GET /fulfillment/pack` |
| Start packing | Full | Full | Full | `POST /fulfillment/pack/{id}/start` |
| Get shipping rates | Full | Full | Full | `POST /fulfillment/pack/{id}/rates` |
| Purchase label | Full | Full | Full | `POST /fulfillment/pack/{id}/purchase-label` |
| Complete packing | Full | Full | Full | `POST /fulfillment/pack/{id}/complete` |

**Web pages:** `Pages/Fulfillment/Packing/` (2 pages)
**Mobile screens:** `features/packing/` (2 screens)

#### 2d. Shipping

| Feature | Web | Mobile API | Mobile App | Notes |
|---------|-----|-----------|------------|-------|
| Ship queue | Full | Full | Full | `GET /fulfillment/ship` |
| Shipment detail | Full | Full | Full | `GET /fulfillment/ship/{id}` |
| Confirm dispatch | Full | Full | Full | `POST /fulfillment/ship/{id}/confirm` |
| Void label | Full | Full | Full | `POST /fulfillment/ship/{id}/void` |
| Get label URL | Full | Full | Full | `GET /fulfillment/ship/{id}/label` |

**Web pages:** `Pages/Fulfillment/Shipping/` (3 pages)
**Mobile screens:** `features/shipping/` (2 screens)

#### 2e. Returns

| Feature | Web | Mobile API | Mobile App | Notes |
|---------|-----|-----------|------------|-------|
| Returns list | Full | Full | Full | `GET /fulfillment/returns` |
| Return detail | Full | Full | Full | `GET /fulfillment/returns/{id}` |
| Approve return | Full | Full | Full | `POST /fulfillment/returns/{id}/approve` |
| Receive return | Full | Full | Full | `POST /fulfillment/returns/{id}/receive` |
| Inspect return | Full | Full | Full | `POST /fulfillment/returns/{id}/inspect` |
| Restock items | Full | Full | Full | `POST /fulfillment/returns/{id}/restock` |

**Web pages:** `Pages/Fulfillment/Returns/` (2 pages)
**Mobile screens:** `features/returns/` (2 screens)
**Docs:** [FULFILLMENT_WORKFLOW.md](./FULFILLMENT_WORKFLOW.md)

---

### 3. Inventory Management

#### 3a. Products

| Feature | Web | Mobile API | Mobile App | Notes |
|---------|-----|-----------|------------|-------|
| Product list + search | Full | Full | Full | `GET /products` |
| Product detail | Full | Full | Full | `GET /products/{id}` |
| Create product | Full | Full | Full | `POST /products` |
| Edit product | Full | Full | Full | `PUT /products/{id}` |
| Delete product | Full | Full | Full | `DELETE /products/{id}` |
| Force delete + restore | Full | Full | Full | `DELETE /products/{id}/force`, `POST /products/{id}/restore` |
| Image upload | Full | Full | Full | Via product create/update |
| Barcode lookup | Full | Full | Full | `GET /products/barcode-lookup` |
| Product statistics | Full | Full | Full | `GET /products/statistics` |
| Deletion impact check | Full | Full | Full | `GET /products/{id}/deletion-impact` |
| Marketplace listings section | Full | None | None | Web-only (product hub overhaul) |
| Sync actions (eBay/Shopify) | Full | None | None | Web-only |
| Bulk price/qty update | Full | None | None | Desktop-only |
| Import/export CSV | Full | None | None | Desktop-only |

**Web pages:** `Pages/Inventory/Products/` (6 pages)
**Mobile screens:** `features/inventory/` (9 screens)
**Docs:** [PRODUCT_MANAGEMENT_SUITE.md](./PRODUCT_MANAGEMENT_SUITE.md), [PRODUCT_MANAGEMENT_UI.md](./PRODUCT_MANAGEMENT_UI.md)

#### 3b. Stock Operations

| Feature | Web | Mobile API | Mobile App | Notes |
|---------|-----|-----------|------------|-------|
| Adjust stock | Full | Full | Full | `POST /inventory/adjust` |
| Transfer stock | Full | Full | Full | `POST /inventory/transfer` |
| View levels | Full | Full | Full | `GET /inventory/levels` |
| Movement history | Full | Full | Full | `GET /inventory/movements` |
| Low stock alerts | Full | Full | Full | `GET /inventory/low-stock` |
| Inventory valuation | Full | Full | Full | `GET /inventory/valuation` |

**Mobile screens:** `features/stock_ops/` (2 screens)

#### 3c. Categories

| Feature | Web | Mobile API | Mobile App | Notes |
|---------|-----|-----------|------------|-------|
| List categories | Full | Full | Full | `GET /categories` |
| Create category | Full | Full | Full | `POST /categories` |
| View category | Full | Full | Full | `GET /categories/{id}` |
| Edit category | Full | Full | Full | `PUT /categories/{id}` |
| Delete category | Full | Full | Full | `DELETE /categories/{id}` |

**Web pages:** `Pages/Inventory/Categories/` (3 pages)
**Mobile screens:** `features/inventory/categories_page.dart`

#### 3d. Locations

| Feature | Web | Mobile API | Mobile App | Notes |
|---------|-----|-----------|------------|-------|
| List locations | Full | Full | Full | `GET /locations` |
| Create location | Full | Full | Full | `POST /locations` |
| View location | Full | Full | Full | `GET /locations/{id}` |
| Edit location | Full | Full | Full | `PUT /locations/{id}` |
| Delete location | Full | Full | Full | `DELETE /locations/{id}` |

**Web pages:** `Pages/Inventory/Locations/` (3 pages)
**Mobile screens:** `features/inventory/locations_page.dart`

---

### 4. Inventory Alerts

| Feature | Web | Mobile API | Mobile App | Notes |
|---------|-----|-----------|------------|-------|
| Alert list by type | Full | Full | Full | `GET /api/v1/inventory/alerts` |
| Alert summary | Full | Full | Full | `GET /api/v1/inventory/alerts/summary` |
| Alert config | Full | Full | Full | `GET/PUT /api/v1/inventory/alerts/config` |
| Dismiss alert | Full | Full | Full | `POST /api/v1/inventory/alerts/{id}/dismiss` |
| Dismiss all | Full | Full | Full | `POST /api/v1/inventory/alerts/dismiss-all` |
| Restore alert | Full | Full | Full | `POST /api/v1/inventory/alerts/{id}/restore` |

**Mobile screens:** `features/alerts/alerts_page.dart`
**API routes:** `routes/api-inventory-alerts.php`

---

### 5. Inventory Analytics

| Feature | Web | Mobile API | Mobile App | Notes |
|---------|-----|-----------|------------|-------|
| KPIs | Full | Full | Full | `GET /analytics/kpis` |
| Stock health | Full | Full | Full | `GET /analytics/stock-health` |
| ABC analysis | Full | Full | Full | `GET /analytics/abc` |
| Turnover analysis | Full | Full | Full | `GET /analytics/turnover` |
| Turnover trend | Full | Full | Full | `GET /analytics/turnover-trend` |
| Top performers | Full | Full | Full | `GET /analytics/top-performers` |
| Movement trends | Full | Full | Full | `GET /analytics/movement-trends` |
| Location performance | Full | Full | Full | `GET /analytics/location-performance` |
| Inventory value | Full | Full | Full | `GET /analytics/inventory-value` |

**Web pages:** `Pages/Analytics/` (4 pages)
**Mobile screens:** `features/analytics/analytics_page.dart`

---

### 6. Product Intake

| Feature | Web | Mobile API | Mobile App | Notes |
|---------|-----|-----------|------------|-------|
| Barcode scan + submit | Full | Full | Full | `POST /mobile/intake` (commented-out block) + `features/inventory/add_product_page.dart` |
| Scan barcode / duplicate check | Full | Full | Full | `POST /mobile/intake/scan-barcode` (commented-out block) |
| Queue stats | Full | Full | Full | `GET /mobile/intake/queue-stats` (commented-out block) |
| My captures | Full | Full | Full | `GET /mobile/intake/my-captures` (commented-out block) |
| Photo upload | Full | Full | Full | `POST /mobile/intake/{id}/photos` (commented-out block) |
| Queue item detail | Full | Full | Full | `GET /intake-queue/{id}` (active route) |
| 4-step processing pipeline | Full | None | None | Desktop-only (AI research, enrichment) |
| Chrome extension research | Full | None | None | Desktop-only |
| Publish to eBay | Full | None | None | Desktop-only |
| Bulk publication | Full | None | None | Desktop-only |

**Web pages:** `Pages/Inventory/IntakeQueue/` (3 pages)
**Mobile screens:** `features/inventory/add_product_page.dart`, `barcode_lookup_page.dart`
**Docs:** [INTAKE_QUEUE_SYSTEM.md](./INTAKE_QUEUE_SYSTEM.md), [PRODUCT_RESEARCH_ORCHESTRATOR.md](./PRODUCT_RESEARCH_ORCHESTRATOR.md)

---

### 6b. Part-Out (Disassembly Work Orders)

| Feature | Web | Mobile API | Mobile App | Notes |
|---------|-----|-----------|------------|-------|
| Work order list | Full | Full | Full | `GET /part-out` |
| Work order detail | Full | Full | Full | `GET /part-out/{id}` |
| Start part-out | Full | Full | Full | `POST /part-out/{id}/start` |
| Extract item | Full | Full | Full | `POST /part-out/items/{id}/extract` |
| Skip item | Full | Full | Full | `POST /part-out/items/{id}/skip` |
| Item notes | Full | Full | Full | `POST /part-out/items/{id}/notes` |
| Barcode scan verify | Full | Full | Full | `POST /part-out/items/{id}/scan-verify` |
| Complete / cancel | Full | Full | Full | `POST /part-out/{id}/complete`, `/cancel` |

**Web pages:** `Pages/Inventory/PartOut/`
**Mobile screens:** `features/part_out/` (2 screens: list, active work order)
**Controller:** `Api\MobilePartOutController`

---

### 6c. Label Printing

| Feature | Web | Mobile API | Mobile App | Notes |
|---------|-----|-----------|------------|-------|
| Print single label | Full | Full | Full | `POST /printing/print/single/{product}` |
| Print batch labels | Full | Full | Full | `POST /printing/print/batch` |
| Discover printers | Full | Full | Full | `POST /printing/printers/discover` |
| Add discovered printer | Full | Full | Full | `POST /printing/printers/add-discovered` |
| Test printer | Full | Full | Full | `POST /printing/printers/{id}/test` |
| Create default templates | Full | Full | Full | `POST /printing/templates/create-defaults` |

**Web pages:** `Pages/Printing/` (3 pages)
**Mobile screens:** `features/printing/` (1 screen)

---

### 7. Dashboard

| Feature | Web | Mobile API | Mobile App | Notes |
|---------|-----|-----------|------------|-------|
| Owner dashboard | Full | Full | Full | `GET /dashboard/owner` |
| Buyer dashboard | Full | Full | Full | `GET /dashboard/buyer` |
| Lister dashboard | Full | Full | Full | `GET /dashboard/lister` |
| Fulfillment dashboard | Full | Full | Full | `GET /dashboard/fulfillment` |

**Web pages:** `Pages/Dashboard/` (4 pages)
**Mobile screens:** `features/dashboard/owner_dashboard_page.dart`

---

### 8. Notifications

| Feature | Web | Mobile API | Mobile App | Notes |
|---------|-----|-----------|------------|-------|
| Notification list | Full | Full | Full | `GET /notifications/recent` |
| Mark as read | Full | Full | Full | `POST /notifications/{id}/read` |
| Mark all read | Full | Full | Full | `POST /notifications/mark-all-read` |
| Delete notification | Full | Full | Full | `DELETE /notifications/{id}` |
| Push notifications (FCM) | Full | Full | Partial | Server dispatch built (`Services/Notification/Fcm/FcmDispatcher`, `Notifications/Channels/FcmChannel`, `Models/Notification/DeviceToken`); device token registered via `POST /auth/fcm-token`. Mobile client foreground/background message handling still TODO |
| Notification preferences | Full | None | Full | Mobile has UI, web has preferences page |

**Web pages:** `Pages/Notifications/` (1 page)
**Mobile screens:** `features/notifications/` (2 screens)
**Docs:** [NOTIFICATION_SYSTEM.md](./NOTIFICATION_SYSTEM.md)

---

### 9. Marketplace Integrations

#### 9a. Marketplace Status (Mobile)

| Feature | Web | Mobile API | Mobile App | Notes |
|---------|-----|-----------|------------|-------|
| Connected marketplaces | Full | Full | Full | `GET /marketplace/status` |
| eBay connection status | Full | Full | Full | `GET /ebay/status` |
| eBay listing stats | Full | Full | Full | `GET /ebay/stats` |
| eBay sync logs | Full | Full | Full | `GET /ebay/logs` |
| eBay listing list | Full | Full | Full | `GET /ebay/listings` |
| eBay listing detail | Full | Full | Full | `GET /ebay/listings/{id}` |

**Mobile screens:** `features/marketplace/marketplace_status_page.dart`

#### 9b. eBay Full Integration (Web-only)

| Feature | Web | Mobile API | Mobile App | Priority | Notes |
|---------|-----|-----------|------------|----------|-------|
| OAuth connection | Full | None | None | Desktop | Web-only flow |
| Listing creation/edit | Full | None | None | Desktop | Complex forms |
| Category mapping | Full | None | None | Desktop | |
| Policies management | Full | Partial | None | Desktop | `GET /ebay/policies` exists |
| Promoted listings | Full | None | None | Desktop | |
| Bulk sync operations | Full | Partial | None | Desktop | `POST /ebay/sync/*` exists |
| SKU resolution | Full | None | None | Desktop | |
| Config update | Full | Partial | None | Desktop | `PATCH /ebay/config` exists |

**Web pages:** `Pages/Ebay/` (6 pages)
**Docs:** [EBAY_LISTING_SERVICE.md](./EBAY_LISTING_SERVICE.md), [EBAY_SYNC_AND_UPDATE_SYSTEM.md](./EBAY_SYNC_AND_UPDATE_SYSTEM.md)

#### 9c. Shopify Integration (Web-only)

| Feature | Web | Mobile API | Mobile App | Priority | Notes |
|---------|-----|-----------|------------|----------|-------|
| OAuth connection | Full | None | None | Desktop | |
| Product sync | Full | None | None | Desktop | |
| Collection management | Full | None | None | Desktop | |
| Product import | Full | None | None | Desktop | |
| Cross-listing | Full | None | None | Desktop | |

**Web pages:** `Pages/Marketplace/Shopify/` (2 pages)
**Docs:** [SHOPIFY_INTEGRATION.md](./SHOPIFY_INTEGRATION.md)

---

### 10. Analytics & Reporting (Web-only)

| Feature | Web | Mobile API | Mobile App | Priority | Notes |
|---------|-----|-----------|------------|----------|-------|
| Profit & Loss | Full | None | None | Desktop | Per-order P&L, marketplace fees |
| Demand Forecasting | Full | None | None | Desktop | Moving average, stockout prediction |
| Reorder Suggestions | Full | None | None | Desktop | Priority-based reorder |

**Web pages:** `Pages/Analytics/` (4 pages)
**Docs:** [PROFIT_LOSS_SYSTEM.md](./PROFIT_LOSS_SYSTEM.md), [DEMAND_FORECASTING.md](./DEMAND_FORECASTING.md)

---

### 11. Communication (Web-only)

| Feature | Web | Mobile API | Mobile App | Priority | Notes |
|---------|-----|-----------|------------|----------|-------|
| Buyer message inbox | Full | None | None | Tier 2 | On-the-go replies valuable |
| Message threads | Full | None | None | Tier 2 | |
| Reply to messages | Full | None | None | Tier 2 | |
| eBay message sync | Full | None | None | Tier 2 | |
| Email campaigns | Full | None | None | Desktop | |
| Campaign templates | Full | None | None | Desktop | |
| Send tracking | Full | None | None | Desktop | |

**Web pages:** `Pages/Communication/` (2 pages), `Pages/Marketing/` (3 pages)
**Docs:** [BUYER_COMMUNICATION.md](./BUYER_COMMUNICATION.md), [EMAIL_MARKETING.md](./EMAIL_MARKETING.md)

---

### 12. Pricing & Listings (Web-only)

| Feature | Web | Mobile API | Mobile App | Priority | Notes |
|---------|-----|-----------|------------|----------|-------|
| Repricing rules | Full | None | None | Desktop | |
| Price guards & logging | Full | None | None | Desktop | |
| Scheduled listings | Full | None | None | Desktop | |
| Cross-marketplace listing | Full | None | None | Desktop | |
| Promoted listings | Full | None | None | Desktop | |

**Web pages:** `Pages/Pricing/` (3 pages), `Pages/Marketplace/` (4 pages)
**Docs:** [REPRICING_ENGINE.md](./REPRICING_ENGINE.md), [LISTING_MANAGEMENT.md](./LISTING_MANAGEMENT.md)

---

### 13. Procurement & Supply Chain (Web-only)

| Feature | Web | Mobile API | Mobile App | Priority | Notes |
|---------|-----|-----------|------------|----------|-------|
| Purchase order CRUD | Full | None | None | Desktop | |
| PO receiving | Full | None | None | Tier 2 | Warehouse floor activity |
| Batch/lot tracking | Full | None | None | Desktop | |
| Supplier portal | Full | None | None | Desktop | |
| Supplier performance | Full | None | None | Desktop | |
| Settlements & commissions | Full | None | None | Desktop | |

**Web pages:** `Pages/Procurement/` (4 pages), `Pages/Supplier/` (8 pages)
**Docs:** [PURCHASE_ORDER_SYSTEM.md](./PURCHASE_ORDER_SYSTEM.md)

---

### 14. Warehouse Operations

| Feature | Web | Mobile API | Mobile App | Priority | Notes |
|---------|-----|-----------|------------|----------|-------|
| Warehouse zones | Full | None | None | Tier 2 | Scan-to-bin on warehouse floor |
| Bin management | Full | None | None | Tier 2 | |
| Bulk bin generation | Full | None | None | Desktop | |
| Labeling work orders | Full | Full | None | Tier 2 | `Api\MobileLabelingController` ready (`/labeling/*`), no Flutter screen |
| Fulfillment routing rules | Full | None | None | Desktop | |
| Shipping rules engine | Full | None | None | Desktop | |
| Label printing (single) | Full | Full | Full | Done | `features/printing/`; `POST /printing/print/single/{product}` |
| Label printing (batch) | Full | Full | Full | Done | `POST /printing/print/batch` |
| Printer management | Full | Full | Full | Done | Discover, add, test on mobile |

**Web pages:** `Pages/Inventory/Warehouse/` (2 pages), `Pages/Fulfillment/` (routing/shipping rules), `Pages/Printing/` (3 pages)
**Mobile screens:** `features/printing/` (1 screen)
**Docs:** [WAREHOUSE_BIN_MANAGEMENT.md](./WAREHOUSE_BIN_MANAGEMENT.md), [FULFILLMENT_ROUTING.md](./FULFILLMENT_ROUTING.md)

---

### 15. Organization & Administration

| Feature | Web | Mobile API | Mobile App | Priority | Notes |
|---------|-----|-----------|------------|----------|-------|
| Team CRUD | Full | Scaffolded | None | Tier 3 | API commented out (api.php:249-277) |
| User CRUD | Full | Scaffolded | None | Tier 3 | API commented out (api.php:219-244) |
| Role & permissions | Full | Scaffolded | None | Tier 3 | API commented out (api.php:282-304) |
| Organization settings | Full | Scaffolded | None | Tier 3 | API commented out (api.php:309-321) |
| Onboarding wizard | Full | Scaffolded | None | Tier 3 | API commented out (api.php:326-341) |
| Admin tools (AI prompts) | Full | None | None | Desktop | |
| Supplier tier management | Full | None | None | Desktop | |
| Submission review | Full | None | None | Desktop | |

**Web pages:** `Pages/Teams/`, `Pages/Admin/`, `Pages/Settings/` (~12 pages)

---

### 16. Bundles & Advanced

| Feature | Web | Mobile API | Mobile App | Priority | Notes |
|---------|-----|-----------|------------|----------|-------|
| Fixed bundles | Full | None | None | Desktop | |
| Pick-n bundles | Full | None | None | Desktop | |
| Bulk operations (price/qty) | Full | None | None | Desktop | |
| Import/export CSV | Full | None | None | Desktop | |
| Customer CRM | Full | None | None | Desktop | |

**Web pages:** `Pages/Inventory/Bundles/` (3 pages), `Pages/Customers/` (3 pages)
**Docs:** [BUNDLING_SYSTEM.md](./BUNDLING_SYSTEM.md), [CUSTOMER_CRM.md](./CUSTOMER_CRM.md)

---

## Scaffolded API Routes (Commented Out)

These route groups exist in `routes/api.php` (lines 215-428) but are commented out. The referenced controllers may or may not exist. Uncommenting + building Flutter screens would enable these features on mobile.

| Route Group | Controller | Endpoints | Status |
|-------------|-----------|-----------|--------|
| Users API (`/users`) | `Api\UserController` | CRUD + activity, avatar, preferences | Commented out |
| Teams API (`/teams`) | `Api\TeamController` | CRUD + join/leave/members/activity | Commented out |
| Roles API (`/roles`) | `Api\RoleController` | CRUD + permissions management | Commented out |
| Organization API (`/organization`) | `Api\OrganizationController` | Info, settings, stats | Commented out |
| Onboarding API (`/onboarding`) | `Api\OnboardingController` | Progress, steps, checklist | Commented out |
| Analytics Tracking API (`/analytics`) | `Api\AnalyticsController` | Activity tracking, team/user performance | Commented out |
| Monitoring/BI API (`/monitoring`) | `Api\MonitoringController` | Rate limits, quality metrics, errors, BI | Commented out |
| Mobile Intake API (`/mobile/intake`) | `IntakeQueueController` | Submit, scan, stats, captures, photos | Commented out (duplicate of active intake routes) |

---

## Priority Tiers

### Tier 1: Mobile-First (Warehouse Floor) -- DONE
All Tier 1 features are implemented: picking, packing, stock adjustments, barcode
scan-to-lookup, inventory alerts, part-out, label printing.

### Tier 2: High Value -- SHIPPED 2026-06-06
- **PO receiving** -- DONE (`MobileReceivingController` + `features/receiving/`)
- **Buyer communication** -- DONE (`MobileMessageController` + `features/messaging/`)
- **Warehouse bins** -- DONE (`MobileBinController` + `features/bins/`)
- **Labeling work orders** -- DONE (`features/labeling/` against existing `MobileLabelingController`)
- **Bundles + eBay listing mgmt** -- DONE (`features/bundles/`, `features/ebay/` listings screen)

### Tier 2 remaining
- **Push notifications (client)** -- Server dispatch is built; mobile needs foreground/background message handling

### Tier 3: Convenience
- **Organization/team management** -- API is scaffolded, needs uncommenting + Flutter screens
- **Onboarding** -- API is scaffolded
- **Monitoring dashboard** -- API is scaffolded

### Tier 4: Desktop-Only
Supplier portal, admin tools, bulk operations, CSV import/export, marketplace OAuth flows, repricing rules, email marketing, demand forecasting, P&L reports, fulfillment routing rules, scheduled listings.

---

## Technical Debt

| Issue | Impact | Priority |
|-------|--------|----------|
| No offline support | Unusable without internet | High |
| FCM client message handling not wired (server dispatch IS built) | Users miss real-time alerts even though backend can send | High |
| No real-time updates (WebSocket) | Dashboard/orders don't auto-refresh | Medium |
| No retry logic for API failures | Poor UX on flaky connections | Medium |
| Labeling API (`MobileLabelingController`) active but no mobile UI | Cannot run labeling work orders from the floor | Medium |
| 6 scaffolded API route groups commented out | Backend ready but inactive | Low |
| Mobile intake routes duplicated (active + commented) | Confusing, needs cleanup | Low |

---

## Recent Changes (since 2026-03-23)

- **2026-06-06 mobile gap build** -- Shipped all 5 ranked backlog gaps: PO receiving,
  labeling, buyer messaging, warehouse bins, bundles + eBay listing mgmt. 18 new
  backend tests (green). Screen count 41 -> 50; API route groups 16 -> 20.
- **2026-06-06 matrix refresh** -- Corrected stale rows: Part-Out and Label Printing
  are shipped on mobile (`features/part_out/`, `features/printing/`); server-side FCM
  dispatch (`FcmDispatcher`/`FcmChannel`/`DeviceToken`) is built; screen count 38 -> 41.
  Added the Mobile Gap Backlog section.
- **Product hub overhaul** -- Marketplace listings section, sync actions, cross-navigation on product detail page
- **Notification system overhaul** -- Fixed broken UI, added marketplace notifications
- **ShippingEasy integration** -- Rate limiting, cancel endpoint, webhook format fixes
- **Shopify improvements** -- Import products card, collection support, inventory sync fixes
- **eBay fixes** -- Offer API for quantity updates, HMAC signature fix
- **Inventory sync** -- Zero out non-primary Shopify locations, removed stock_quantity from listing hash

---

## Mobile Gap Backlog (recommended next work, 2026-06-06)

Ranked build candidates for closing the remaining mobile-relevant gaps. Each lists the
existing backend to reuse so an implementation plan is a short hop. Pattern every new
mobile feature after an existing `Api\Mobile*Controller` (Sanctum auth, org scoping,
`metadata.source = 'mobile'` audit) + a Flutter `features/{x}/` slice (service +
Riverpod provider + page), mirroring picking/part-out.

| # | Gap | Backend to reuse | Mobile API? | Notes |
|---|-----|------------------|-------------|-------|
| 1 | **PO receiving** -- SHIPPED 2026-06-06 | `Procurement/PurchaseOrderReceipt*`, `ReceivingService` | Full (`MobileReceivingController`) | Done: `features/receiving/` + scan-verify + complete. Also fixed `InventoryMovement.created_by` null under Sanctum. |
| 2 | **Buyer messaging** -- SHIPPED 2026-06-06 | `Communication/MarketplaceMessage(+Reply)`, `EbayMessageService::sendReply` (now takes `$source`) | Full (`MobileMessageController`) | Done: `features/messaging/` inbox/thread/reply/archive. |
| 3 | **Warehouse bins scan/lookup** -- SHIPPED 2026-06-06 | `Inventory/WarehouseBin`, `InventoryLevel.bin_id` | Full (`MobileBinController`) | Done: `features/bins/` -- scan bin -> contents, place product into bin (capacity-guarded). |
| 4 | **Bundles / eBay listing mgmt** -- SHIPPED 2026-06-06 | Bundles: `MobileBundleController` + `Catalog/ProductBundle`. eBay: existing `EbayApiController` `updateListing`/`retryListing` | Full | Done: `features/bundles/` (availability) + `features/ebay/` listings screen (sync qty/price, retry). |
| 5 | **Labeling work orders** -- SHIPPED 2026-06-06 | `Api\MobileLabelingController` (`/labeling/*`) | Full | Done: `features/labeling/` -- label/scan-bin/skip/complete. |

**Quick-win polish -- finish existing "coming soon" stubs in the app:**

| Stub | Location | Fix |
|------|----------|-----|
| Home -> Inventory nav | `features/home/.../home_page.dart` | Wire button to `/inventory` route |
| Profile photo upload | `features/profile/.../edit_profile_page.dart` | Use `POST /users/{id}/avatar` (active) |
| eBay listings page | `features/ebay/.../ebay_status_page.dart` | `GET /ebay/listings` already returns data |
| eBay logs page | `features/ebay/.../ebay_status_page.dart` | `GET /ebay/logs` already returns data |
| Help center / Support | profile + main layout | Static/webview screens |

---

## Cross-References

| Doc | Topic |
|-----|-------|
| [FULFILLMENT_WORKFLOW.md](./FULFILLMENT_WORKFLOW.md) | Pick/pack/ship process |
| [INVENTORY_SYNC_SYSTEM.md](./INVENTORY_SYNC_SYSTEM.md) | Cross-marketplace inventory sync |
| [PURCHASE_ORDER_SYSTEM.md](./PURCHASE_ORDER_SYSTEM.md) | Procurement system |
| [WAREHOUSE_BIN_MANAGEMENT.md](./WAREHOUSE_BIN_MANAGEMENT.md) | Warehouse zones/bins |
| [CUSTOMER_CRM.md](./CUSTOMER_CRM.md) | Customer management |
| [NOTIFICATION_SYSTEM.md](./NOTIFICATION_SYSTEM.md) | Notification infrastructure |
| [EBAY_LISTING_SERVICE.md](./EBAY_LISTING_SERVICE.md) | eBay listing creation |
| [EBAY_SYNC_AND_UPDATE_SYSTEM.md](./EBAY_SYNC_AND_UPDATE_SYSTEM.md) | eBay sync pipeline |
| [SHOPIFY_INTEGRATION.md](./SHOPIFY_INTEGRATION.md) | Shopify integration |
| [MARKETPLACE_INTEGRATION_ARCHITECTURE.md](./MARKETPLACE_INTEGRATION_ARCHITECTURE.md) | Multi-marketplace patterns |
| [PRODUCT_MANAGEMENT_SUITE.md](./PRODUCT_MANAGEMENT_SUITE.md) | Product CRUD |
| [INTAKE_QUEUE_SYSTEM.md](./INTAKE_QUEUE_SYSTEM.md) | Product intake queue |
| [PRODUCT_RESEARCH_ORCHESTRATOR.md](./PRODUCT_RESEARCH_ORCHESTRATOR.md) | AI research system |
| [BUNDLING_SYSTEM.md](./BUNDLING_SYSTEM.md) | Product bundles |
| [REPRICING_ENGINE.md](./REPRICING_ENGINE.md) | Automated repricing |
| [DEMAND_FORECASTING.md](./DEMAND_FORECASTING.md) | Sales forecasting |
| [PROFIT_LOSS_SYSTEM.md](./PROFIT_LOSS_SYSTEM.md) | P&L calculations |
| [EMAIL_MARKETING.md](./EMAIL_MARKETING.md) | Email campaigns |
| [BUYER_COMMUNICATION.md](./BUYER_COMMUNICATION.md) | Marketplace messages |
| [FULFILLMENT_ROUTING.md](./FULFILLMENT_ROUTING.md) | Routing rules |
| [SHIPPINGEASY_INTEGRATION.md](./SHIPPINGEASY_INTEGRATION.md) | ShippingEasy provider |
| [MULTI_CURRENCY.md](./MULTI_CURRENCY.md) | Currency handling |
| [LISTING_MANAGEMENT.md](./LISTING_MANAGEMENT.md) | Cross-marketplace listing |
| [CATEGORY_PIPELINE.md](./CATEGORY_PIPELINE.md) | Category import/sync |
| [PRICING_AND_INVENTORY_SYNC.md](./PRICING_AND_INVENTORY_SYNC.md) | Sync protocols |
