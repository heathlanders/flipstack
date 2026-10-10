---
name: analytics-debug
description: Quick health checks for profit/loss calculations, marketplace fees, and forecasting
user-invocable: true
---

Diagnose analytics system health. Run ALL of these checks sequentially and report a summary at the end.

**IMPORTANT:** All PHP commands must run inside Docker: `docker exec ecommerce_app`

## Check 1: Orders Shipped Without Financial Calculations

```bash
docker exec ecommerce_app php artisan tinker --execute="
  \$missing = DB::table('orders')
    ->leftJoin('order_financials', 'orders.id', '=', 'order_financials.order_id')
    ->whereIn('orders.status', ['shipped', 'delivered'])
    ->whereNull('order_financials.id')
    ->select('orders.id', 'orders.status', 'orders.ebay_order_id', 'orders.shopify_order_id', 'orders.created_at')
    ->orderByDesc('orders.created_at')
    ->limit(15)
    ->get();
  echo 'Orders without financials: ' . \$missing->count() . PHP_EOL;
  if (\$missing->count() > 0) echo \$missing->take(10)->toJson(JSON_PRETTY_PRINT);

  \$totalMissing = DB::table('orders')
    ->leftJoin('order_financials', 'orders.id', '=', 'order_financials.order_id')
    ->whereIn('orders.status', ['shipped', 'delivered'])
    ->whereNull('order_financials.id')
    ->count();
  echo PHP_EOL . 'Total orders missing financials: ' . \$totalMissing . PHP_EOL;
"
```

## Check 2: Marketplace Fee Sync Gaps

```bash
docker exec ecommerce_app php artisan tinker --execute="
  echo '--- Orders with Zero/Null Marketplace Fees ---' . PHP_EOL;
  \$noFees = DB::table('order_financials')
    ->where(function(\$q) {
      \$q->whereNull('marketplace_fees')
        ->orWhere('marketplace_fees', '=', 0);
    })
    ->join('orders', 'order_financials.order_id', '=', 'orders.id')
    ->whereIn('orders.status', ['shipped', 'delivered'])
    ->count();
  echo 'Orders with missing marketplace fees: ' . \$noFees . PHP_EOL;

  echo PHP_EOL . '--- Last Financial Calculation Timestamps ---' . PHP_EOL;
  \$latest = DB::table('order_financials')
    ->selectRaw(\"max(calculated_at) as last_calc, count(*) as total_records\")
    ->first();
  echo 'Last calculation: ' . (\$latest->last_calc ?? 'NEVER') . PHP_EOL;
  echo 'Total financial records: ' . \$latest->total_records . PHP_EOL;
"
```

## Check 3: P&L Summary for Current Month

```bash
docker exec ecommerce_app php artisan tinker --execute="
  \$summary = DB::table('order_financials')
    ->where('calculated_at', '>=', now()->startOfMonth())
    ->selectRaw(\"
      count(*) as order_count,
      sum(revenue) as total_revenue,
      sum(cost_of_goods) as total_cogs,
      sum(marketplace_fees) as total_fees,
      sum(shipping_label_cost) as total_shipping_cost,
      sum(shipping_charged) as total_shipping_charged,
      sum(net_profit) as total_profit,
      avg(profit_margin_pct) as avg_margin
    \")
    ->first();
  echo 'P&L Summary (Current Month):' . PHP_EOL;
  echo '  Orders calculated: ' . \$summary->order_count . PHP_EOL;
  echo '  Revenue: \$' . number_format(\$summary->total_revenue ?? 0, 2) . PHP_EOL;
  echo '  COGS: \$' . number_format(\$summary->total_cogs ?? 0, 2) . PHP_EOL;
  echo '  Marketplace fees: \$' . number_format(\$summary->total_fees ?? 0, 2) . PHP_EOL;
  echo '  Shipping cost: \$' . number_format(\$summary->total_shipping_cost ?? 0, 2) . PHP_EOL;
  echo '  Shipping charged: \$' . number_format(\$summary->total_shipping_charged ?? 0, 2) . PHP_EOL;
  echo '  Net profit: \$' . number_format(\$summary->total_profit ?? 0, 2) . PHP_EOL;
  echo '  Avg margin: ' . number_format(\$summary->avg_margin ?? 0, 1) . '%' . PHP_EOL;
"
```

## Check 4: Forecast Accuracy (Last 30 Days)

```bash
docker exec ecommerce_app php artisan tinker --execute="
  \$forecasts = DB::table('demand_forecasts')
    ->where('forecast_date', '>=', now()->subDays(30))
    ->where('forecast_date', '<=', now())
    ->whereNotNull('actual_quantity')
    ->selectRaw(\"
      count(*) as total_forecasts,
      avg(abs(forecasted_quantity - actual_quantity)) as avg_absolute_error,
      avg(CASE WHEN actual_quantity > 0 THEN abs(forecasted_quantity - actual_quantity)::float / actual_quantity * 100 ELSE 0 END) as avg_pct_error,
      confidence_level,
      count(CASE WHEN abs(forecasted_quantity - actual_quantity) <= 2 THEN 1 END) as accurate_count
    \")
    ->groupBy('confidence_level')
    ->get();
  echo 'Forecast Accuracy (Last 30 Days):' . PHP_EOL;
  if (\$forecasts->isEmpty()) {
    echo '  No forecast data with actuals available.' . PHP_EOL;
  } else {
    foreach (\$forecasts as \$f) {
      \$accuracyPct = \$f->total_forecasts > 0 ? round((\$f->accurate_count / \$f->total_forecasts) * 100, 1) : 0;
      echo \"  [\$f->confidence_level] \$f->total_forecasts forecasts, accuracy (within 2 units): \$accuracyPct%, avg error: \" . round(\$f->avg_pct_error, 1) . '%' . PHP_EOL;
    }
  }

  \$pendingForecasts = DB::table('demand_forecasts')
    ->where('forecast_date', '>=', now()->subDays(30))
    ->where('forecast_date', '<=', now())
    ->whereNull('actual_quantity')
    ->count();
  echo PHP_EOL . 'Forecasts awaiting actuals: ' . \$pendingForecasts . PHP_EOL;
"
```

## Check 5: KPI Aggregation Health

```bash
docker exec ecommerce_app php artisan tinker --execute="
  echo '--- Workforce Aggregation (Last 7 Days) ---' . PHP_EOL;
  \$workforce = DB::table('workforce_performance_daily')
    ->where('performance_date', '>=', now()->subDays(7))
    ->selectRaw(\"performance_date, count(distinct user_id) as users, sum(items_picked) as picked, sum(orders_packed) as packed\")
    ->groupBy('performance_date')
    ->orderByDesc('performance_date')
    ->get();
  if (\$workforce->isEmpty()) {
    echo '  No workforce data. Run: php artisan analytics:backfill --days=7' . PHP_EOL;
  } else {
    foreach (\$workforce as \$w) echo \"  \$w->performance_date: \$w->users users, \$w->picked items picked, \$w->packed orders packed\" . PHP_EOL;
  }

  echo PHP_EOL . '--- Business Health Score ---' . PHP_EOL;
  \$health = DB::table('business_health_scores')
    ->orderByDesc('score_date')
    ->first(['score_date', 'overall_score']);
  if (\$health) {
    echo \"  Latest: \$health->score_date, Score: \$health->overall_score/100\" . PHP_EOL;
  } else {
    echo '  No health scores calculated yet.' . PHP_EOL;
  }

  echo PHP_EOL . '--- Marketplace Health Snapshots ---' . PHP_EOL;
  \$mh = DB::table('marketplace_health_snapshots')
    ->where('snapshot_date', '>=', now()->subDays(7))
    ->selectRaw(\"marketplace, max(snapshot_date) as latest, sum(sync_successes) as successes, sum(sync_failures) as failures\")
    ->groupBy('marketplace')
    ->get();
  if (\$mh->isEmpty()) {
    echo '  No marketplace health data.' . PHP_EOL;
  } else {
    foreach (\$mh as \$m) echo \"  \$m->marketplace: latest \$m->latest, \$m->successes successes, \$m->failures failures\" . PHP_EOL;
  }
"
```

## Summary

After running all checks, provide a summary:

1. **Health Status**: healthy / degraded / critical
   - Critical: missing financials > 20, no financial calculations ever run, or business health score < 40
   - Degraded: missing financials 1-20, missing marketplace fees > 10, low forecast accuracy (< 50%), missing KPI aggregation days, or health score 40-60
   - Healthy: all checks pass, KPI aggregation current, health score > 60

2. **Issues Found**: list each issue with severity

3. **Recommended Actions**:
   - Missing financials: suggest running the P&L calculation job for affected orders
   - Missing fees: suggest re-syncing marketplace transaction data from eBay/Shopify
   - Low forecast accuracy: suggest reviewing algorithm parameters or increasing data window
   - No forecast data: suggest running the demand forecast generation job
