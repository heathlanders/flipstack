# Profit & Loss Test Cases

## Document ID: TC-ANL-001

**Domain:** Analytics
**Related Test Plan:** `docs/testing/plans/analytics-test-plan.md`
**Last Updated:** 2026-04-15

---

### TC-ANL-001-01: Dashboard loads with summary KPIs

| Field | Value |
|-------|-------|
| **ID** | TC-ANL-001-01 |
| **Priority** | P0 |
| **Type** | Feature |
| **Platform** | Web |
| **Automated** | Yes |
| **Test File** | `tests/Feature/Analytics/ProfitLossTest.php` |

**Preconditions:**
1. Authenticated user with `analytics.view` permission
2. Orders with OrderFinancial records exist in date range

**Test Steps:**

| Step | Action | Expected Result |
|------|--------|-----------------|
| 1 | GET `analytics.profit-loss.dashboard` with start_date and end_date | 200 response |
| 2 | Verify summary object | Contains: total_revenue, total_cogs, gross_profit, gross_margin, total_fees, net_profit, net_margin |
| 3 | Verify calculations | gross_profit = revenue - cogs, net_profit = gross_profit - fees - shipping |

---

### TC-ANL-001-02: Revenue breakdown by marketplace

| Field | Value |
|-------|-------|
| **ID** | TC-ANL-001-02 |
| **Priority** | P1 |
| **Type** | Feature |
| **Platform** | Web |
| **Automated** | Partial |

**Preconditions:**
1. Orders from multiple marketplaces (eBay, Shopify) with financials

**Test Steps:**

| Step | Action | Expected Result |
|------|--------|-----------------|
| 1 | GET dashboard | byMarketplace array returned |
| 2 | Verify eBay entry | revenue, cogs, fees, profit aggregated for eBay orders only |
| 3 | Verify Shopify entry | revenue, cogs, fees, profit aggregated for Shopify orders only |
| 4 | Verify totals | Sum of marketplace breakdowns = summary totals |

---

### TC-ANL-001-03: Daily trend data

| Field | Value |
|-------|-------|
| **ID** | TC-ANL-001-03 |
| **Priority** | P1 |
| **Type** | Feature |
| **Platform** | Web |
| **Automated** | Partial |

**Preconditions:**
1. Orders spread across multiple dates in range

**Test Steps:**

| Step | Action | Expected Result |
|------|--------|-----------------|
| 1 | GET dashboard with 7-day range | dailyTrend array with entry per day |
| 2 | Verify each entry | Contains: date, revenue, cogs, profit, order_count |
| 3 | Days with no orders | Entry exists with zero values |

---

### TC-ANL-001-04: Top products by profit

| Field | Value |
|-------|-------|
| **ID** | TC-ANL-001-04 |
| **Priority** | P1 |
| **Type** | Feature |
| **Platform** | Web |
| **Automated** | No |

**Preconditions:**
1. Multiple products with varying profit margins

**Test Steps:**

| Step | Action | Expected Result |
|------|--------|-----------------|
| 1 | GET dashboard | topProducts array returned (limit 10) |
| 2 | Verify ordering | Sorted by profit descending |
| 3 | Verify fields | Each entry: product_name, sku, revenue, cogs, profit, margin, units_sold |

---

### TC-ANL-001-05: Fee summary breakdown

| Field | Value |
|-------|-------|
| **ID** | TC-ANL-001-05 |
| **Priority** | P1 |
| **Type** | Feature |
| **Platform** | Web |
| **Automated** | No |

**Preconditions:**
1. Orders with various fee types (marketplace_fees, shipping_cost, payment_processing)

**Test Steps:**

| Step | Action | Expected Result |
|------|--------|-----------------|
| 1 | GET dashboard | feeSummary object returned |
| 2 | Verify breakdown | marketplace_fees, shipping_costs, other_fees separated |
| 3 | Verify total | Sum of fee categories = total_fees in summary |

---

### TC-ANL-001-06: Date range filtering

| Field | Value |
|-------|-------|
| **ID** | TC-ANL-001-06 |
| **Priority** | P1 |
| **Type** | Feature |
| **Platform** | Web |
| **Automated** | No |

**Preconditions:**
1. Orders spanning multiple months

**Test Steps:**

| Step | Action | Expected Result |
|------|--------|-----------------|
| 1 | GET dashboard with start_date=2026-03-01, end_date=2026-03-31 | Only March data returned |
| 2 | GET dashboard with start_date=2026-04-01, end_date=2026-04-15 | Only April data returned |
| 3 | Verify exclusion | Orders outside range not included in calculations |
| 4 | No dates provided | Default range applied (last 30 days) |

**Notes:**
- Date range uses Order.paid_at, not created_at
- Organization-scoped: only orders for authenticated user's org
