# Demand Forecasting and Reorder Suggestions

## Overview

The demand forecasting system analyzes order history to predict future product demand and generates reorder suggestions when products are at risk of stockout. It calculates sales velocity from paid orders, estimates stockout dates, and recommends reorder quantities based on lead time and safety stock buffers. Suggestions can be accepted (creating a draft purchase order) or dismissed.

---

## Architecture

### Key Files

| File | Purpose |
|------|---------|
| `app/Models/Analytics/DemandForecast.php` | Forecast record per product with algorithm metadata |
| `app/Models/Analytics/ReorderSuggestion.php` | Actionable reorder recommendation with priority and status |
| `app/Services/Analytics/DemandForecastingService.php` | Velocity calculation, forecast generation, stockout prediction, dashboard data |
| `app/Services/Analytics/ReorderSuggestionService.php` | Suggestion generation, acceptance, dismissal, reorder quantity formula |
| `app/Jobs/Analytics/` | Background jobs for batch forecast and suggestion generation |
| `app/Http/Controllers/Analytics/` | Forecasting dashboard controllers |

---

## Data Model

### `demand_forecasts`

| Column | Type | Description |
|--------|------|-------------|
| `id` | uuid | Primary key |
| `organization_id` | uuid | FK to organizations |
| `product_id` | uuid | FK to products |
| `forecast_date` | date | The date being forecasted for |
| `forecasted_quantity` | integer | Predicted units to be sold by forecast_date |
| `actual_quantity` | integer | Actual units sold (backfilled for accuracy tracking, nullable) |
| `confidence_level` | string | `low`, `medium`, `high` |
| `algorithm` | string | `moving_average`, `weighted_average`, `exponential_smoothing` |
| `parameters` | json | Algorithm inputs: lookback_days, forecast_days, daily_velocity, order_count |
| `generated_at` | datetime | When this forecast was computed |

### `reorder_suggestions`

| Column | Type | Description |
|--------|------|-------------|
| `id` | uuid | Primary key |
| `organization_id` | uuid | FK to organizations |
| `product_id` | uuid | FK to products |
| `supplier_id` | uuid | FK to suppliers (nullable) |
| `suggested_quantity` | integer | Units to reorder |
| `reason` | string | `low_stock_forecast`, `seasonal_trend`, `velocity_increase` |
| `estimated_stockout_date` | date | When the product is predicted to run out |
| `priority` | string | `critical`, `high`, `medium`, `low` |
| `status` | string | `pending`, `accepted`, `dismissed` |
| `accepted_at` | datetime | When the suggestion was accepted |
| `purchase_order_id` | uuid | FK to purchase_orders (set when accepted and PO created) |

---

## Key Flows

### Sales Velocity Calculation

`DemandForecastingService::calculateSalesVelocity(productId, days)`

Queries `OrderItem` records where the parent `Order.paid_at` is not null (paid orders only) within the lookback window. Returns average units sold per day:

```
velocity = SUM(order_items.quantity) / lookback_days
```

Default lookback is 30 days. The algorithm constants support 7, 30, and 90 day windows for different granularity.

### Forecast Generation

`DemandForecastingService::generateForecast(productId, orgId, daysAhead)`

1. Calculate 30-day sales velocity
2. Forecast quantity = ceil(velocity * daysAhead)
3. Determine confidence from recent order count:
   - 10+ orders in 30 days = `high`
   - 3-9 orders = `medium`
   - 0-2 orders = `low`
4. Store forecast with `moving_average` algorithm and parameter snapshot

`generateForOrganization(orgId)` runs this for all active products in an organization.

### Stockout Prediction

`DemandForecastingService::getStockoutDate(productId)`

```
days_until_stockout = floor(current_stock / daily_velocity)
stockout_date = today + days_until_stockout
```

Where `current_stock` is `Product.available_quantity` and `daily_velocity` is the 30-day moving average. Returns null if velocity is zero (no sales, no stockout predicted). Returns now() if stock is already zero.

### Reorder Suggestion Generation

`ReorderSuggestionService::generateSuggestions(orgId)`

For each active product:

1. Calculate 30-day velocity. Skip if zero.
2. Calculate days of stock remaining: `floor(current_stock / velocity)`
3. Only suggest if `days_left <= lead_time + safety_stock_days` (default: 14 + 7 = 21 days)
4. Skip if a pending suggestion already exists for this product
5. Calculate reorder quantity using the formula below
6. Assign priority:
   - 0-3 days remaining = `critical`
   - 4-7 days = `high`
   - 8-14 days = `medium`
   - 15+ days = `low`

### Reorder Quantity Formula

```
reorder_qty = ceil((velocity * lead_time_days) + (velocity * safety_stock_days) - current_stock)
reorder_qty = max(reorder_qty, 0)
```

Defaults: `lead_time_days = 14`, `safety_stock_days = 7`.

Example:
```
velocity = 3 units/day
lead_time = 14 days
safety_stock = 7 days
current_stock = 15

reorder_qty = ceil((3 * 14) + (3 * 7) - 15)
            = ceil(42 + 21 - 15)
            = ceil(48)
            = 48 units
```

### Dashboard Data

`DemandForecastingService::getDashboardData(orgId)` returns:

- `products_at_risk` -- count of products with <= 14 days of stock
- `at_risk_products` -- list sorted by days_left ascending (max 50), each with name, SKU, stock, velocity, stockout date, and priority
- `avg_daily_velocity` -- average velocity across all selling products
- `forecasts_generated` -- number of forecasts generated in the last 7 days
- `critical_suggestions` -- count of pending critical-priority suggestions

### Suggestion Lifecycle

```
pending -> accepted  (ReorderSuggestionService::accept, sets accepted_at)
pending -> dismissed (ReorderSuggestionService::dismiss)
```

When accepted, the suggestion can be linked to a `PurchaseOrder` via `purchase_order_id` for tracking.

---

## Integration Points

- **Order Data**: Velocity is calculated from `OrderItem` records with paid orders. Accuracy depends on `Order.paid_at` being set during order import.
- **Product Inventory**: Stockout prediction reads `Product.available_quantity`, which is the single source of truth maintained by the inventory sync system (see `docs/INVENTORY_SYNC_SYSTEM.md`).
- **Purchase Orders**: Accepted suggestions can link to `PurchaseOrder` records in the procurement module. The `purchase_order_id` FK connects the suggestion to the resulting order.
- **Supplier Linking**: `ReorderSuggestion.supplier_id` optionally links to the preferred supplier for the product.
- **Background Jobs**: `app/Jobs/Analytics/` contains jobs for batch forecast generation and suggestion generation, typically run on a daily schedule.
- **Routes**: Analytics routes are in `routes/analytics.php`, handled by controllers in `app/Http/Controllers/Analytics/`.
- **Frontend**: `resources/js/Pages/Analytics/` contains the forecasting dashboard with at-risk product lists and suggestion management.
