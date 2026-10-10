---
name: demand-forecasting
description: Sales velocity analysis, demand forecasting (moving average), stockout prediction, and reorder suggestions with priority levels
user-invocable: false
---

# Demand Forecasting and Reorder Suggestions

## Key Files

| Area | File | Purpose |
|------|------|---------|
| **Models** | `app/Models/Analytics/DemandForecast.php` | Per-product forecast with algorithm, confidence, and parameters |
| | `app/Models/Analytics/ReorderSuggestion.php` | Reorder recommendation with priority, reason, and PO link |
| **Services** | `app/Services/Analytics/DemandForecastingService.php` | Velocity calculation, forecast generation, stockout prediction, dashboard data |
| | `app/Services/Analytics/ReorderSuggestionService.php` | Suggestion generation, accept/dismiss, reorder quantity formula |
| **Controllers** | `app/Http/Controllers/Analytics/ForecastController.php` | Dashboard, suggestions list, accept/dismiss, generate trigger |
| **Jobs** | `app/Jobs/Analytics/GenerateDemandForecasts.php` | Queued forecast generation for all org products |
| | `app/Jobs/Analytics/GenerateReorderSuggestions.php` | Queued reorder suggestion generation |
| **Routes** | `routes/web.php` (analytics/forecasting prefix) | Forecasting dashboard and suggestion routes |
| **Pages** | `resources/js/Pages/Analytics/Forecasting/Dashboard.vue` | Forecasting dashboard with at-risk products |
| | `resources/js/Pages/Analytics/Forecasting/ReorderSuggestions.vue` | Suggestion list with accept/dismiss actions |

## Algorithms

| Algorithm | Constant | Description |
|-----------|----------|-------------|
| Moving Average | `ALGORITHM_MOVING_AVERAGE` | Simple average of daily sales over lookback period (currently the only implemented algorithm) |
| Weighted Average | `ALGORITHM_WEIGHTED_AVERAGE` | Defined as constant but not yet implemented |
| Exponential Smoothing | `ALGORITHM_EXPONENTIAL_SMOOTHING` | Defined as constant but not yet implemented |

## Sales Velocity Calculation

`DemandForecastingService::calculateSalesVelocity(productId, days)`:
1. Queries `OrderItem` records where the parent order was created within the lookback period and has `paid_at` set (confirmed paid)
2. Sums the `quantity` field across matching order items
3. Returns `total_sold / days` rounded to 2 decimal places
4. Default lookback: 30 days

## Forecast Generation

`DemandForecastingService::generateForecast(productId, orgId, daysAhead)`:
1. Calculates 30-day sales velocity
2. Forecasts quantity: `ceil(velocity * daysAhead)`
3. Determines confidence based on recent order count:
   - `high`: 10+ orders in last 30 days
   - `medium`: 3-9 orders
   - `low`: 0-2 orders
4. Creates a `DemandForecast` record with the moving average algorithm
5. Stores parameters: `lookback_days`, `forecast_days`, `daily_velocity`, `order_count`

`generateForOrganization(orgId)` runs this for all active products.

## Stockout Prediction

`DemandForecastingService::getStockoutDate(productId)`:
- Formula: `days_until_stockout = current_stock / daily_velocity`
- Returns `now + days_until_stockout` as a Carbon date
- Returns `now()` if stock is already zero
- Returns `null` if velocity is zero (no sales, no predicted stockout)

## DemandForecast Fields

| Field | Type | Purpose |
|-------|------|---------|
| `organization_id` | string | Org scope |
| `product_id` | uuid | FK to Product |
| `forecast_date` | date | The date this forecast is for |
| `forecasted_quantity` | integer | Predicted units to be sold by forecast_date |
| `actual_quantity` | integer (nullable) | Actual units sold (for accuracy tracking) |
| `confidence_level` | string | `low`, `medium`, `high` |
| `algorithm` | string | Algorithm used |
| `parameters` | JSON | Algorithm inputs (lookback_days, forecast_days, daily_velocity, order_count) |
| `generated_at` | datetime | When the forecast was created |

## Reorder Suggestions

### Generation Logic

`ReorderSuggestionService::generateSuggestions(orgId)`:
1. Iterates all active products
2. Calculates 30-day sales velocity for each
3. Computes days of stock remaining: `current_stock / velocity`
4. Only suggests reorder if `days_left <= lead_time (14) + safety_stock_days (7)`
5. Skips products with existing pending suggestions (dedup)
6. Calculates suggested quantity via the reorder formula
7. Assigns priority based on days remaining

### Reorder Quantity Formula

```
reorder_qty = ceil((velocity * lead_time_days) + (velocity * safety_stock_days) - current_stock)
```

Where:
- `velocity` = average daily sales (30-day lookback)
- `lead_time_days` = 14 (default)
- `safety_stock_days` = 7 (default)
- `current_stock` = `product.available_quantity` or `stock_quantity`
- Minimum returned value is 0

### Priority Levels

| Priority | Constant | Days of Stock Left |
|----------|----------|--------------------|
| `critical` | `PRIORITY_CRITICAL` | 0-3 days |
| `high` | `PRIORITY_HIGH` | 4-7 days |
| `medium` | `PRIORITY_MEDIUM` | 8-14 days |
| `low` | `PRIORITY_LOW` | 15-21 days |

### Suggestion Reasons

| Reason | Constant | When Used |
|--------|----------|-----------|
| `low_stock_forecast` | `REASON_LOW_STOCK_FORECAST` | Stock projected to run out within lead time + safety buffer |
| `seasonal_trend` | `REASON_SEASONAL_TREND` | Defined but not yet implemented |
| `velocity_increase` | `REASON_VELOCITY_INCREASE` | Defined but not yet implemented |

## ReorderSuggestion Fields

| Field | Type | Purpose |
|-------|------|---------|
| `organization_id` | string | Org scope |
| `product_id` | uuid | FK to Product |
| `supplier_id` | uuid (nullable) | FK to preferred Supplier |
| `suggested_quantity` | integer | Recommended units to reorder |
| `reason` | string | Why reorder is suggested |
| `estimated_stockout_date` | date | When stock is projected to hit zero |
| `priority` | string | `critical`, `high`, `medium`, `low` |
| `status` | string | `pending`, `accepted`, `dismissed` |
| `accepted_at` | datetime (nullable) | When accepted |
| `purchase_order_id` | uuid (nullable) | FK to PurchaseOrder if one was created |

## Suggestion Workflow

1. **Pending**: Generated automatically, awaiting user review
2. **Accepted**: User accepts the suggestion (`accepted_at` set). Can optionally create a draft PurchaseOrder.
3. **Dismissed**: User dismisses the suggestion (will not regenerate while dismissed record exists for that product)

## Dashboard Data

`DemandForecastingService::getDashboardData(orgId)` returns:
- `products_at_risk`: Count of products with fewer than 14 days of stock
- `at_risk_products`: Array (max 50) of at-risk products with: id, name, sku, current_stock, daily_velocity, estimated_stockout_date, days_left, priority
- `avg_daily_velocity`: Average daily velocity across all selling products
- `forecasts_generated`: Count of forecasts generated in last 7 days
- `critical_suggestions`: Count of pending critical-priority suggestions

## Jobs

| Job | Queue | Timeout | Tries | Tags |
|-----|-------|---------|-------|------|
| `GenerateDemandForecasts` | default | 300s | 1 | `demand-forecasts`, `org:{id}` |
| `GenerateReorderSuggestions` | default | 300s | 1 | `reorder-suggestions`, `org:{id}` |

Both jobs are dispatched together from `ForecastController::generate()`.

## Routes

| Method | URI | Name | Action |
|--------|-----|------|--------|
| GET | `/analytics/forecasting` | `analytics.forecasting.dashboard` | Dashboard with at-risk products |
| GET | `/analytics/forecasting/suggestions` | `analytics.forecasting.suggestions` | Paginated suggestions list (filterable by status, priority) |
| POST | `/analytics/forecasting/generate` | `analytics.forecasting.generate` | Queue forecast + suggestion generation |
| POST | `/analytics/forecasting/suggestions/{suggestion}/accept` | `analytics.forecasting.accept` | Accept a suggestion |
| POST | `/analytics/forecasting/suggestions/{suggestion}/dismiss` | `analytics.forecasting.dismiss` | Dismiss a suggestion |

## Integration with Purchase Orders

`ReorderSuggestion` has a `purchase_order_id` FK linking to `PurchaseOrder`. When a suggestion is accepted, a draft PO can be created (this linkage exists in the model but the auto-creation workflow is not yet implemented).

## Scopes

- `ReorderSuggestion::pending()` -- filters to `status = pending`
- `ReorderSuggestion::critical()` -- filters to `priority = critical`
- `ReorderSuggestion::byPriority()` -- orders by priority: critical > high > medium > low

## Debugging Commands

```php
// Sales velocity for a product (units/day over 30 days)
$service = app(\App\Services\Analytics\DemandForecastingService::class);
$service->calculateSalesVelocity($productId, 30);

// Estimated stockout date
$service->getStockoutDate($productId);

// Dashboard data for an org
$service->getDashboardData($orgId);

// Recent forecasts for a product
DemandForecast::where('product_id', $productId)
  ->orderByDesc('generated_at')
  ->take(5)
  ->get(['forecast_date', 'forecasted_quantity', 'actual_quantity', 'confidence_level', 'algorithm', 'parameters']);

// Pending reorder suggestions by priority
ReorderSuggestion::where('organization_id', $orgId)
  ->where('status', 'pending')
  ->byPriority()
  ->with(['product:id,name,sku,available_quantity', 'supplier:id,name'])
  ->get(['id', 'product_id', 'supplier_id', 'suggested_quantity', 'reason', 'estimated_stockout_date', 'priority']);

// Critical items needing immediate reorder
ReorderSuggestion::where('organization_id', $orgId)
  ->where('status', 'pending')
  ->where('priority', 'critical')
  ->with('product:id,name,sku')
  ->get();

// Products with no forecast data (never analyzed)
$forecastedIds = DemandForecast::where('organization_id', $orgId)->distinct()->pluck('product_id');
Product::where('organization_id', $orgId)
  ->where('status', 'active')
  ->whereNotIn('id', $forecastedIds)
  ->count();

// Reorder quantity calculation for a product
$reorderService = app(\App\Services\Analytics\ReorderSuggestionService::class);
$reorderService->calculateReorderQuantity($productId, 14, 7);
```

## Audit Logging

| Action | Category | Severity | Metadata keys |
|--------|----------|----------|---------------|
| `forecast.generated` | inventory | debug | algorithm, confidence, source: cron |
| `reorder.suggested` | inventory | info | priority, suggested_quantity, days_to_stockout |
| `reorder.accepted` | inventory | info | po_id (if PO drafted) |
| `reorder.dismissed` | inventory | info | dismiss_reason |

Trait: models in this feature use `LogsAudit` (`app/Traits/LogsAudit.php`) for auto CRUD diff. Domain lifecycle events emitted from `app/Jobs/Analytics/Generate{DemandForecasts,ReorderSuggestions}` (wrap in `withAuditActor(null, ..., ['source' => 'cron', 'job_class' => self::class])`) via `UserActivityLog::log(action, desc, $user, $metadata, $severity, $category)`.

Audit drives reorder action timelines in Admin Audit Hub.

Spec: `docs/AUDIT_SYSTEM.md`. Enforced rule: `.claude/rules/audit-logging.md`. Tests: `use Tests\Support\AssertsAudits;` then `$this->assertAudited('action', $user, [...])`.
