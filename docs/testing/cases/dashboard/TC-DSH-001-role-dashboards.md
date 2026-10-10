# Role-Based Dashboards Test Cases

## Document ID: TC-DSH-001

**Domain:** Dashboard
**Related Test Plan:** `docs/testing/plans/dashboard-test-plan.md`
**Last Updated:** 2026-04-15

---

### TC-DSH-001-01: Main dashboard loads for owner role

| Field | Value |
|-------|-------|
| **ID** | TC-DSH-001-01 |
| **Priority** | P0 |
| **Type** | Feature |
| **Platform** | Web |
| **Automated** | No |

**Preconditions:**
1. Organization exists with products, intake queue items, and team members
2. User is authenticated with the `owner` role (resolves via `getDashboardType()`)
3. User has `dashboard.owner` permission

**Test Steps:**

| Step | Action | Expected Result |
|------|--------|-----------------|
| 1 | Send `GET` request to `route('dashboard')` as authenticated owner user | DashboardController dispatches to `MainDashboardController::show()` via `match($dashboardType)` |
| 2 | Assert Inertia renders `Dashboard/Main` | Response status 200, Inertia component = `Dashboard/Main` |
| 3 | Assert `dashboardType` prop equals `'main'` | Identifies dashboard variant for frontend |
| 4 | Assert `metrics` prop contains `inventory.total_products`, `inventory.inventory_value`, `alerts.low_stock_count`, `alerts.pending_intake` | Owner metrics calculated from organization data |
| 5 | Assert `financialSummary` contains `total_invested`, `estimated_value`, `potential_profit`, `profit_margin` | Financial summary computed from `stock_quantity * cost_price` and `stock_quantity * selling_price` |
| 6 | Assert `marketplaceConnections` returns array with ebay, shopify, amazon entries each having `platform`, `connected`, `status` keys | IntegrationCredential lookup for marketplace connection status |
| 7 | Assert `platformHealth` contains status entries for configured marketplaces | Platform integration health data returned |
| 8 | Assert `quickActions` and `recentActivity` props are populated | User-specific quick actions and recent activity feed returned |

**Notes:**
- `MainDashboardController` extends `OwnerDashboardController` and adds `marketplaceConnections` prop
- The `DashboardController::index()` uses `$user->getDashboardType()` to route to the correct sub-controller
- Supplier users are redirected to `supplier.dashboard` before role matching
- Financial summary uses `DB::raw('stock_quantity * cost_price')` for PostgreSQL aggregation

---

### TC-DSH-001-02: Owner dashboard shows revenue/orders/profit KPIs

| Field | Value |
|-------|-------|
| **ID** | TC-DSH-001-02 |
| **Priority** | P0 |
| **Type** | Feature |
| **Platform** | Web |
| **Automated** | No |

**Preconditions:**
1. Organization with products having `cost_price`, `selling_price`, and `stock_quantity` values
2. Products created across current and previous weeks (for trend calculation)
3. User authenticated with `owner` role

**Test Steps:**

| Step | Action | Expected Result |
|------|--------|-----------------|
| 1 | Send `GET` request to `route('dashboard.owner')` | Response status 200, Inertia renders `Dashboard/Owner` |
| 2 | Assert `metrics.inventory.total_products` matches organization product count | `Product::where('organization_id', ...)->count()` |
| 3 | Assert `metrics.inventory.inventory_value` equals sum of `stock_quantity * cost_price` for all org products | Decimal value, aggregated via `DB::raw()` |
| 4 | Assert `metrics.inventory.products_this_week` counts products created since start of current week | `Product::where('created_at', '>=', $startOfWeek)->count()` |
| 5 | Assert `metrics.inventory.trend` is a percentage change between this week and last week | Uses `calculatePercentageChange(current, previous)` from `BaseDashboardController` |
| 6 | Assert `metrics.alerts.low_stock_count` counts products with `manage_stock=true` and `stock_quantity <= 5` | Low stock threshold hardcoded at 5 |
| 7 | Assert `financialSummary.profit_margin` equals `(potential_profit / estimated_value) * 100` | Percentage calculated from price deltas |
| 8 | Assert `teamPerformance` returns up to 5 teams with `name`, `member_count`, `productivity_score` | Teams loaded with users, product count per team calculated |

**Notes:**
- Owner dashboard includes `teamPerformance` prop not present in main dashboard
- `productivity_score` is simplified: `min(100, teamProducts * 10)`
- `getAlerts()` in `BaseDashboardController` checks for low stock and pending orders
- `platformHealth` currently returns partially hardcoded data for ebay/amazon/mercari

---

### TC-DSH-001-03: Buyer dashboard shows purchase metrics

| Field | Value |
|-------|-------|
| **ID** | TC-DSH-001-03 |
| **Priority** | P1 |
| **Type** | Feature |
| **Platform** | Web |
| **Automated** | No |

**Preconditions:**
1. Organization with products created in current and previous months
2. Products with `cost_price` and `selling_price` values (some with `selling_price = null` for research metric)
3. At least one Supplier with linked products
4. User authenticated with `buyer` role and `dashboard.buyer` permission

**Test Steps:**

| Step | Action | Expected Result |
|------|--------|-----------------|
| 1 | Send `GET` request to `route('dashboard.buyer')` | Response status 200, Inertia renders `Dashboard/Buyer` |
| 2 | Assert `metrics.purchasing.this_month` counts products created since start of current month | Monthly purchase count |
| 3 | Assert `metrics.purchasing.avg_cost` equals average `cost_price` of this month's products | `Product::avg('cost_price')` |
| 4 | Assert `metrics.investment.avg_roi` is calculated as `(potentialProfit / monthlyInvestment) * 100` | ROI percentage |
| 5 | Assert `metrics.research.needs_pricing` counts products where `selling_price IS NULL OR selling_price = 0` | Items needing price research |
| 6 | Assert `roiAnalysis` returns up to 5 categories ordered by avg ROI descending | Category-level ROI breakdown using `DB::raw()` aggregation |
| 7 | Assert `supplierPerformance` returns suppliers ordered by total investment, limited to 5 | Supplier metrics via `products` relationship |
| 8 | Assert `budgetTracking.monthly.spent` reflects this month's total `quantity * cost_price` | Budget tracking with hardcoded $5000 monthly budget |

**Notes:**
- `BuyerDashboardController` includes `roiAnalysis`, `supplierPerformance`, `marketInsights`, and `budgetTracking` props
- Monthly budget is currently a placeholder ($5000) -- may come from `OrganizationSettings` in future
- `getMarketInsights()` returns opportunity suggestions based on top-performing categories
- ROI calculation uses `(selling_price - cost_price) / cost_price * 100`

---

### TC-DSH-001-04: Lister dashboard shows listing stats

| Field | Value |
|-------|-------|
| **ID** | TC-DSH-001-04 |
| **Priority** | P1 |
| **Type** | Feature |
| **Platform** | Web |
| **Automated** | No |

**Preconditions:**
1. Organization with ProductIntakeQueue items in `pending` and `completed` statuses
2. Products in `draft` status and products missing images/descriptions
3. User authenticated with `lister` role and `dashboard.lister` permission

**Test Steps:**

| Step | Action | Expected Result |
|------|--------|-----------------|
| 1 | Send `GET` request to `route('dashboard.lister')` | Response status 200, Inertia renders `Dashboard/Lister` |
| 2 | Assert `metrics.pipeline.pending_intake` counts `ProductIntakeQueue` with `processing_status = 'pending'` for org | Pending intake queue items |
| 3 | Assert `metrics.pipeline.completed_this_week` counts completed items updated since start of week | Weekly throughput |
| 4 | Assert `metrics.pipeline.avg_processing_time` is average hours from `created_at` to `updated_at` for completed items | Uses `EXTRACT(EPOCH FROM ...)` PostgreSQL function |
| 5 | Assert `metrics.quality.photo_completion` is percentage of products that have at least one image | `(productsWithPhotos / totalProducts) * 100` |
| 6 | Assert `listingPipeline.intake` returns up to 10 pending intake items | Ordered by `created_at asc` |
| 7 | Assert `listingPipeline.drafts` returns up to 10 draft products with `completion` score | Completion score based on 6 fields (name, description, category, cost, selling price, images) |
| 8 | Assert `optimizationSuggestions` returns quality suggestions when incomplete product count exceeds 5 | Actionable suggestions for lister workflow |

**Notes:**
- `ProductIntakeQueue` uses `processing_status` (not `status`) for queue state
- `calculateProductCompletion()` scores 6 fields equally at ~16.7% each
- `performanceMetrics.velocity.items_per_day` divides monthly completions by days elapsed
- `platformBreakdown` returns listing counts and avg price per marketplace

---

### TC-DSH-001-05: Fulfillment dashboard shows order pipeline stats

| Field | Value |
|-------|-------|
| **ID** | TC-DSH-001-05 |
| **Priority** | P0 |
| **Type** | Feature |
| **Platform** | Web |
| **Automated** | No |

**Preconditions:**
1. Organization with Orders in statuses: `pending`, `assigned`, `picking`, `picked`, `packing`, `packed`, `shipped`
2. Shipments with `shipped_at`, `actual_delivery_date`, and carrier data in the last 30 days
3. At least one active InventoryLocation
4. User authenticated with `fulfillment` role and `dashboard.fulfillment` permission

**Test Steps:**

| Step | Action | Expected Result |
|------|--------|-----------------|
| 1 | Send `GET` request to `route('dashboard.fulfillment')` | Response status 200, Inertia renders `Dashboard/Fulfillment` |
| 2 | Assert `metrics.orders.pending` counts orders with `STATUS_PENDING` | Pending order count |
| 3 | Assert `metrics.orders.processing` counts orders in assigned/picking/picked/packing/packed statuses | Aggregated in-process count |
| 4 | Assert `metrics.orders.shipped_today` counts shipped orders for today's date | `Order::where('shipped_at', today())->count()` |
| 5 | Assert `metrics.performance.avg_processing_time` is average hours from shipment creation to `shipped_at` | Uses `EXTRACT(EPOCH FROM (shipped_at - created_at)) / 3600` |
| 6 | Assert `orderQueue.to_pick` returns up to 10 orders needing picking, ordered by priority desc then ship_by_date | Pick queue with item counts |
| 7 | Assert `shippingMetrics.delivery_performance.on_time_rate` is percentage of delivered shipments meeting estimated date | `actual_delivery_date <= estimated_delivery_date` |
| 8 | Assert `carrierPerformance` returns per-carrier stats with `packages_shipped`, `on_time_rate`, `avg_cost` | Grouped by carrier from last 30 days of shipments |

**Notes:**
- `FulfillmentDashboardController` uses Order status constants (`STATUS_PENDING`, `STATUS_ASSIGNED`, etc.)
- `orderQueue` has three sections: `to_pick`, `to_pack`, `ready_to_ship`
- `inventoryLocations` includes `product_count` (items with qty > 0) and `utilization` percentage
- `same_day_shipping_rate` compares `DATE(shipped_at) = DATE(created_at)` via PostgreSQL `DATE()` function

---

### TC-DSH-001-06: Support dashboard loads

| Field | Value |
|-------|-------|
| **ID** | TC-DSH-001-06 |
| **Priority** | P1 |
| **Type** | Feature |
| **Platform** | Web |
| **Automated** | No |

**Preconditions:**
1. Organization with MarketplaceMessage records (incoming, some with `read_at = null`)
2. ReturnRequest records in `requested`, `approved`, or `in_transit` statuses
3. User authenticated with `support` role and `dashboard.support` permission

**Test Steps:**

| Step | Action | Expected Result |
|------|--------|-----------------|
| 1 | Send `GET` request to `route('dashboard.support')` | Response status 200, Inertia renders `Dashboard/Support` |
| 2 | Assert `metrics.messages.unread` counts incoming messages where `read_at IS NULL` | Unread message count |
| 3 | Assert `metrics.messages.avg_response_hours` is average hours from `received_at` to `replied_at` for last 7 days | Uses `EXTRACT(EPOCH FROM (replied_at - received_at)) / 3600` |
| 4 | Assert `metrics.returns.open` counts return requests in open statuses | `['requested', 'approved', 'in_transit']` |
| 5 | Assert `recentMessages` returns up to 10 incoming messages ordered by `created_at desc` | Each with `sender`, `subject`, `marketplace`, `is_read` |
| 6 | Assert `returnRequests` returns up to 10 open returns with order relationship | Includes `order.marketplace_order_id`, `order.buyer_name` |

**Notes:**
- Support dashboard is simpler than other role dashboards with fewer computed metrics
- `MarketplaceMessage.direction = 'incoming'` filters for buyer messages (not outgoing replies)
- Average response time only considers messages that have a `replied_at` value
- Return requests load `order` relationship for display context

---

### TC-DSH-001-07: Dashboard routes to correct role view

| Field | Value |
|-------|-------|
| **ID** | TC-DSH-001-07 |
| **Priority** | P0 |
| **Type** | Feature |
| **Platform** | Web |
| **Automated** | No |

**Preconditions:**
1. Multiple users exist with different roles: owner, buyer, lister, fulfillment, support
2. Each user has the corresponding `dashboard.*` permission

**Test Steps:**

| Step | Action | Expected Result |
|------|--------|-----------------|
| 1 | Authenticate as user with `owner` role. Send `GET` to `route('dashboard')` | `DashboardController::index()` calls `MainDashboardController::show()`. Inertia renders `Dashboard/Main` with `dashboardType = 'main'` |
| 2 | Authenticate as user with `buyer` role. Send `GET` to `route('dashboard')` | Dispatches to `BuyerDashboardController::show()`. Inertia renders `Dashboard/Buyer` with `dashboardType = 'buyer'` |
| 3 | Authenticate as user with `lister` role. Send `GET` to `route('dashboard')` | Dispatches to `ListerDashboardController::show()`. Inertia renders `Dashboard/Lister` with `dashboardType = 'lister'` |
| 4 | Authenticate as user with `fulfillment` role. Send `GET` to `route('dashboard')` | Dispatches to `FulfillmentDashboardController::show()`. Inertia renders `Dashboard/Fulfillment` with `dashboardType = 'fulfillment'` |
| 5 | Authenticate as user with `support` role. Send `GET` to `route('dashboard')` | Dispatches to `SupportDashboardController::show()`. Inertia renders `Dashboard/Support` with `dashboardType = 'support'` |
| 6 | Authenticate as user with no recognized role. Send `GET` to `route('dashboard')` | Falls through to `basicDashboard()`. Inertia renders `Dashboard/Basic` with `dashboardType = 'basic'` |
| 7 | Authenticate as active supplier user. Send `GET` to `route('dashboard')` | Redirected to `route('supplier.dashboard')` before role matching |

**Notes:**
- `DashboardController::index()` uses PHP `match()` on `$user->getDashboardType()` return value
- The `default` case renders `Dashboard/Basic` with `availableFeatures` based on permissions
- Supplier redirect checks `$user->is_supplier && $user->supplier_status === SupplierStatus::ACTIVE`
- Role-specific routes (`dashboard.owner`, `dashboard.buyer`, etc.) are protected by `can:dashboard.*` middleware

---

### TC-DSH-001-08: Dashboard data scoped to organization

| Field | Value |
|-------|-------|
| **ID** | TC-DSH-001-08 |
| **Priority** | P0 |
| **Type** | Security |
| **Platform** | Web |
| **Automated** | No |

**Preconditions:**
1. Two organizations (Org A and Org B) each with products, orders, intake items, and team members
2. Org A user authenticated with `owner` role

**Test Steps:**

| Step | Action | Expected Result |
|------|--------|-----------------|
| 1 | Authenticate as Org A owner. Send `GET` to `route('dashboard')` | Dashboard loads successfully |
| 2 | Assert `metrics.inventory.total_products` counts only Org A products | `Product::where('organization_id', $orgA->id)->count()` |
| 3 | Assert `financialSummary.total_invested` sums only Org A product costs | No Org B product values included |
| 4 | Assert `teamPerformance` includes only Org A teams | `$organization->teams()` scopes to current org |
| 5 | Assert `recentActivity` shows only Org A intake items and products | `BaseDashboardController::getRecentActivity()` filters by `organization_id` |
| 6 | Assert `getAlerts()` low stock count only includes Org A products | Alert data scoped by `$organization->id` |
| 7 | Assert no Org B product IDs, team IDs, or user IDs appear anywhere in the response props | Full response inspection for data leakage |
| 8 | Repeat test for buyer, lister, fulfillment, and support dashboard types | All dashboard controllers derive `organization` from `$user->organization` |

**Notes:**
- All dashboard controllers access organization via `$request->user()->organization` or `$user->organization`
- `BaseDashboardController::getRecentActivity()` uses `$user->organization_id` for scoping
- `getAlerts()` uses `$organization->id` for product queries
- `FulfillmentDashboardController` uses `$organization->id` for Order, Shipment, and InventoryLocation queries
- `SupportDashboardController` uses `$organization->id` for MarketplaceMessage and ReturnRequest queries
- This is a critical multi-tenant isolation test -- failure would constitute a data breach
