# Demand Forecasting Test Cases

## Document ID: TC-ANL-002

**Domain:** Analytics
**Related Test Plan:** `docs/testing/plans/analytics-test-plan.md`
**Last Updated:** 2026-04-15

---

### TC-ANL-002-01: Generate forecasts with moving average

| Field | Value |
|-------|-------|
| **ID** | TC-ANL-002-01 |
| **Priority** | P1 |
| **Type** | Feature |
| **Platform** | Web |
| **Automated** | Yes |
| **Test File** | `tests/Feature/Analytics/ForecastingTest.php` |

**Preconditions:**
1. Products with 30+ days of sales history
2. OrderItems linked to products with order dates

**Test Steps:**

| Step | Action | Expected Result |
|------|--------|-----------------|
| 1 | Run DemandForecastingService for organization | DemandForecast records created |
| 2 | Verify forecast data | daily_velocity, weekly_velocity calculated from sales history |
| 3 | Verify forecast period | Predictions cover next 30/60/90 days |

---

### TC-ANL-002-02: Stockout prediction

| Field | Value |
|-------|-------|
| **ID** | TC-ANL-002-02 |
| **Priority** | P1 |
| **Type** | Feature |
| **Platform** | Web |
| **Automated** | Partial |

**Preconditions:**
1. Product with stock_quantity=20 and daily_velocity=5

**Test Steps:**

| Step | Action | Expected Result |
|------|--------|-----------------|
| 1 | Calculate stockout date | days_until_stockout = stock_quantity / daily_velocity = 4 days |
| 2 | Product flagged as at-risk | stockout_date populated |
| 3 | Product with zero velocity | No stockout prediction (infinite days) |

---

### TC-ANL-002-03: Reorder suggestions with priority

| Field | Value |
|-------|-------|
| **ID** | TC-ANL-002-03 |
| **Priority** | P1 |
| **Type** | Feature |
| **Platform** | Web |
| **Automated** | Partial |

**Preconditions:**
1. Products with varying stock levels and velocities
2. DemandForecast records exist

**Test Steps:**

| Step | Action | Expected Result |
|------|--------|-----------------|
| 1 | Generate reorder suggestions | ReorderSuggestion records created |
| 2 | Verify priority levels | critical (stockout < 3 days), high (< 7), medium (< 14), low (< 30) |
| 3 | Verify suggested quantity | Based on lead time and target stock days |
| 4 | Products not at risk | No suggestion generated |

---

### TC-ANL-002-04: Forecast accuracy calculation

| Field | Value |
|-------|-------|
| **ID** | TC-ANL-002-04 |
| **Priority** | P2 |
| **Type** | Feature |
| **Platform** | Web |
| **Automated** | No |

**Preconditions:**
1. Historical forecasts with actual sales data available for comparison

**Test Steps:**

| Step | Action | Expected Result |
|------|--------|-----------------|
| 1 | Compare predicted vs actual sales | accuracy_percentage calculated |
| 2 | MAPE (Mean Absolute Percentage Error) | Error metric stored per product |

---

### TC-ANL-002-05: Organization scoping

| Field | Value |
|-------|-------|
| **ID** | TC-ANL-002-05 |
| **Priority** | P1 |
| **Type** | Feature |
| **Platform** | Web |
| **Automated** | No |

**Preconditions:**
1. Two organizations with different product sets

**Test Steps:**

| Step | Action | Expected Result |
|------|--------|-----------------|
| 1 | Generate forecasts for Org A | Only Org A products included |
| 2 | Query forecasts as Org B user | Org A forecasts not visible |

---

### TC-ANL-002-06: Insufficient sales history

| Field | Value |
|-------|-------|
| **ID** | TC-ANL-002-06 |
| **Priority** | P2 |
| **Type** | Feature |
| **Platform** | Web |
| **Automated** | No |

**Preconditions:**
1. Product with < 7 days of sales history

**Test Steps:**

| Step | Action | Expected Result |
|------|--------|-----------------|
| 1 | Attempt forecast for new product | Handled gracefully, no forecast or low-confidence flag |
| 2 | No division by zero | Service doesn't crash on zero history |
