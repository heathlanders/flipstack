# Analytics Hub Test Cases

## Document ID: TC-ANL-003

**Domain:** Analytics
**Related Test Plan:** `docs/testing/plans/analytics-test-plan.md`
**Last Updated:** 2026-04-15

---

### TC-ANL-003-01: Executive dashboard KPIs

| Field | Value |
|-------|-------|
| **ID** | TC-ANL-003-01 |
| **Priority** | P1 |
| **Type** | Feature |
| **Platform** | Web |
| **Automated** | No |

**Preconditions:**
1. Authenticated user with `analytics.view` permission
2. Orders, products, and financial data exist

**Test Steps:**

| Step | Action | Expected Result |
|------|--------|-----------------|
| 1 | GET `analytics.hub.executive` with date range | 200 response with Inertia page |
| 2 | Verify overview KPIs | total_revenue, total_orders, avg_order_value, new_customers returned |
| 3 | Verify revenueByMarketplace | Array with marketplace name + revenue per marketplace |
| 4 | Verify revenueByCategory | Array with category name + revenue |
| 5 | Verify revenueTrend | Daily data points for Chart.js |
| 6 | Verify topProducts | Top 10 by revenue |
| 7 | Verify healthScore | 0-100 score from BusinessHealthScoreService |
| 8 | Compare period (optional) | If compare dates provided, delta percentages calculated |

---

### TC-ANL-003-02: Fulfillment analytics dashboard

| Field | Value |
|-------|-------|
| **ID** | TC-ANL-003-02 |
| **Priority** | P1 |
| **Type** | Feature |
| **Platform** | Web |
| **Automated** | No |

**Preconditions:**
1. Orders with shipments at various stages

**Test Steps:**

| Step | Action | Expected Result |
|------|--------|-----------------|
| 1 | GET `analytics.hub.fulfillment` | 200 with fulfillment metrics |
| 2 | Verify overview | avg_order_to_ship_hours, on_time_ship_rate, orders_processed |
| 3 | Verify pickAccuracy | Picked vs shorted ratio |
| 4 | Verify backlogAge | Distribution of pending orders by age |
| 5 | Verify returnMetrics | Return rate, avg processing time |
| 6 | Verify carrierPerformance | Delivery times by carrier (USPS/UPS/FedEx) |

---

### TC-ANL-003-03: Workforce analytics dashboard

| Field | Value |
|-------|-------|
| **ID** | TC-ANL-003-03 |
| **Priority** | P2 |
| **Type** | Feature |
| **Platform** | Web |
| **Automated** | No |

**Preconditions:**
1. Multiple users with fulfillment activity (picks, packs, ships)

**Test Steps:**

| Step | Action | Expected Result |
|------|--------|-----------------|
| 1 | GET `analytics.hub.workforce` | 200 with workforce metrics |
| 2 | Verify per-user metrics | items_picked, items_packed, orders_shipped per user |
| 3 | Verify productivity trends | Daily/weekly output per worker |

---

### TC-ANL-003-04: Procurement analytics dashboard

| Field | Value |
|-------|-------|
| **ID** | TC-ANL-003-04 |
| **Priority** | P2 |
| **Type** | Feature |
| **Platform** | Web |
| **Automated** | No |

**Preconditions:**
1. Purchase orders in various statuses with supplier data

**Test Steps:**

| Step | Action | Expected Result |
|------|--------|-----------------|
| 1 | GET `analytics.hub.procurement` | 200 with procurement metrics |
| 2 | Verify supplier performance | Delivery reliability, cost trends per supplier |
| 3 | Verify PO metrics | Total spend, avg lead time, outstanding POs |

---

### TC-ANL-003-05: Marketplace health dashboard

| Field | Value |
|-------|-------|
| **ID** | TC-ANL-003-05 |
| **Priority** | P1 |
| **Type** | Feature |
| **Platform** | Web |
| **Automated** | No |

**Preconditions:**
1. Active eBay and Shopify listings with sync logs

**Test Steps:**

| Step | Action | Expected Result |
|------|--------|-----------------|
| 1 | GET `analytics.hub.marketplace` | 200 with marketplace metrics |
| 2 | Verify listing health | Active, errored, out-of-stock counts per marketplace |
| 3 | Verify sync success rate | Successful vs failed sync operations |
| 4 | Verify revenue by marketplace | Marketplace comparison |

---

### TC-ANL-003-06: Customer analytics dashboard

| Field | Value |
|-------|-------|
| **ID** | TC-ANL-003-06 |
| **Priority** | P2 |
| **Type** | Feature |
| **Platform** | Web |
| **Automated** | No |

**Preconditions:**
1. Customers with multiple orders

**Test Steps:**

| Step | Action | Expected Result |
|------|--------|-----------------|
| 1 | GET `analytics.hub.customers` | 200 with customer metrics |
| 2 | Verify repeat rate | Percentage of customers with 2+ orders |
| 3 | Verify lifetime value | Average total_spent across customers |
| 4 | Verify acquisition by marketplace | New customers per marketplace |

---

### TC-ANL-003-07: Communication analytics dashboard

| Field | Value |
|-------|-------|
| **ID** | TC-ANL-003-07 |
| **Priority** | P2 |
| **Type** | Feature |
| **Platform** | Web |
| **Automated** | No |

**Preconditions:**
1. MarketplaceMessage records with replies

**Test Steps:**

| Step | Action | Expected Result |
|------|--------|-----------------|
| 1 | GET `analytics.hub.communication` | 200 with communication metrics |
| 2 | Verify response SLA | Avg response time, SLA compliance rate |
| 3 | Verify volume | Messages received/sent per day |

---

### TC-ANL-003-08: Marketing analytics dashboard

| Field | Value |
|-------|-------|
| **ID** | TC-ANL-003-08 |
| **Priority** | P2 |
| **Type** | Feature |
| **Platform** | Web |
| **Automated** | No |

**Preconditions:**
1. EmailCampaign records with sends

**Test Steps:**

| Step | Action | Expected Result |
|------|--------|-----------------|
| 1 | GET `analytics.hub.marketing` | 200 with marketing metrics |
| 2 | Verify campaign metrics | Open rate, click rate, bounce rate, unsubscribe rate |
| 3 | Verify per-campaign breakdown | Metrics per campaign |

---

### TC-ANL-003-09: Pricing analytics dashboard

| Field | Value |
|-------|-------|
| **ID** | TC-ANL-003-09 |
| **Priority** | P2 |
| **Type** | Feature |
| **Platform** | Web |
| **Automated** | No |

**Preconditions:**
1. RepricingRule and RepricingLog records

**Test Steps:**

| Step | Action | Expected Result |
|------|--------|-----------------|
| 1 | GET `analytics.hub.pricing` | 200 with pricing metrics |
| 2 | Verify repricing activity | Total repriced, avg price change, rules active |
| 3 | Verify price distribution | Price range histogram data |

**Notes:**
- All hub dashboards require `analytics.view` permission
- All dashboards accept optional start_date/end_date query params
- All data organization-scoped
- Chart.js formatted data (labels array + datasets array) for frontend rendering
