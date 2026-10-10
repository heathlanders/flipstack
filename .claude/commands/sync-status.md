---
name: sync-status
description: Unified cross-marketplace sync health view - inventory mismatches, listing status, sync errors, queue depth
user-invocable: true
---

Check cross-marketplace sync health. Run ALL of these checks sequentially and report a unified summary.

**IMPORTANT:** All PHP commands must run inside Docker: `docker exec ecommerce_app`

## Check 1: Products with Mismatched Quantities vs Marketplace Listings

```bash
docker exec ecommerce_app php artisan tinker --execute="
  \$mismatches = DB::select(\"
    SELECT p.id, p.name, p.sku, p.available_quantity,
      el.quantity_available as ebay_qty, el.ebay_listing_id as ebay_id,
      sl.shopify_inventory_quantity as shopify_qty, sl.shopify_product_id as shopify_id
    FROM products p
    LEFT JOIN ebay_listings el ON el.product_id = p.id AND el.status = 'published' AND el.deleted_at IS NULL
    LEFT JOIN shopify_listings sl ON sl.product_id = p.id AND sl.status = 'active' AND sl.deleted_at IS NULL
    WHERE p.status = 'active'
      AND (el.id IS NOT NULL OR sl.id IS NOT NULL)
      AND (
        (el.id IS NOT NULL AND el.quantity_available != p.available_quantity)
        OR (sl.id IS NOT NULL AND sl.shopify_inventory_quantity != p.available_quantity)
      )
    LIMIT 10
  \");
  echo 'Quantity mismatches: ' . count(\$mismatches) . PHP_EOL;
  if (count(\$mismatches) > 0) echo json_encode(\$mismatches, JSON_PRETTY_PRINT);
"
```

## Check 2: eBay Listing Status Distribution

```bash
docker exec ecommerce_app php artisan tinker --execute="
  echo DB::table('ebay_listings')
    ->whereNull('deleted_at')
    ->selectRaw(\"status, count(*) as count\")
    ->groupBy('status')
    ->orderBy('count', 'desc')
    ->get()->toJson(JSON_PRETTY_PRINT);
"
```

## Check 3: Shopify Listing Status Distribution

```bash
docker exec ecommerce_app php artisan tinker --execute="
  echo DB::table('shopify_listings')
    ->whereNull('deleted_at')
    ->selectRaw(\"status, count(*) as count\")
    ->groupBy('status')
    ->orderBy('count', 'desc')
    ->get()->toJson(JSON_PRETTY_PRINT);
"
```

## Check 4: Recent Sync Errors Across All Platforms (24h)

```bash
docker exec ecommerce_app php artisan tinker --execute="
  echo '--- eBay Sync Errors ---' . PHP_EOL;
  echo DB::table('ebay_sync_logs')
    ->where('created_at', '>', now()->subDay())
    ->where('status', 'error')
    ->selectRaw(\"error_code, count(*) as count\")
    ->groupBy('error_code')
    ->orderBy('count', 'desc')
    ->get()->toJson(JSON_PRETTY_PRINT) . PHP_EOL;

  echo '--- Shopify Sync Errors ---' . PHP_EOL;
  echo DB::table('shopify_sync_logs')
    ->where('created_at', '>', now()->subDay())
    ->where('status', 'error')
    ->selectRaw(\"error_code, count(*) as count\")
    ->groupBy('error_code')
    ->orderBy('count', 'desc')
    ->get()->toJson(JSON_PRETTY_PRINT) . PHP_EOL;
"
```

## Check 5: Queue Depths for Sync-Related Queues

```bash
docker exec ecommerce_app php artisan tinker --execute="
  foreach (['default', 'ebay', 'shopify', 'fulfillment'] as \$q) {
    \$depth = Illuminate\Support\Facades\Redis::llen('queues:' . \$q);
    \$status = \$depth > 100 ? '[HIGH]' : (\$depth > 20 ? '[MODERATE]' : '[OK]');
    echo \"\$status \$q: \$depth jobs\" . PHP_EOL;
  }
"
```

## Check 6: Failed PushInventoryToMarketplaces Jobs (24h)

```bash
docker exec ecommerce_app php artisan tinker --execute="
  \$failed = DB::table('failed_jobs')
    ->where('failed_at', '>', now()->subDay())
    ->where(function(\$q) {
      \$q->where('payload', 'like', '%PushInventoryToMarketplaces%')
        ->orWhere('payload', 'like', '%PushPriceToMarketplaces%')
        ->orWhere('payload', 'like', '%PushListingToMarketplaces%')
        ->orWhere('payload', 'like', '%UpdateEbayInventory%')
        ->orWhere('payload', 'like', '%UpdateShopifyInventory%');
    })
    ->orderByDesc('failed_at')->get();
  echo 'Failed sync jobs (24h): ' . \$failed->count() . PHP_EOL;
  foreach (\$failed->take(5) as \$j) {
    \$p = json_decode(\$j->payload);
    echo '  ' . \$p->displayName . ' at ' . \$j->failed_at . PHP_EOL;
    echo '  ' . substr(\$j->exception, 0, 150) . PHP_EOL . PHP_EOL;
  }
"
```

## Check 7: Active Sync Rules Summary

```bash
docker exec ecommerce_app php artisan tinker --execute="
  \$rules = App\Models\MarketplaceSyncRule::where('is_active', true)
    ->orderBy('priority')
    ->get(['name', 'marketplace', 'action', 'priority', 'conditions']);
  echo 'Active sync rules: ' . \$rules->count() . PHP_EOL;
  foreach (\$rules as \$r) {
    echo \"  [P\$r->priority] \$r->marketplace: \$r->action - \$r->name\" . PHP_EOL;
  }
"
```

## Check 8: Rate Limiter Status

```bash
docker exec ecommerce_app php artisan tinker --execute="
  \$ebayKey = 'ebay_rate_limiter:calls_today';
  \$ebayToday = Illuminate\Support\Facades\Redis::get(\$ebayKey) ?? 0;
  echo \"eBay API calls today: \$ebayToday / 5000\" . PHP_EOL;

  \$ebayBackoff = Illuminate\Support\Facades\Redis::get('ebay_rate_limiter:backoff_until');
  if (\$ebayBackoff) echo 'eBay backoff until: ' . \$ebayBackoff . PHP_EOL;

  \$shopifyThrottle = Illuminate\Support\Facades\Redis::get('shopify_rate_limiter:throttled');
  echo 'Shopify throttled: ' . (\$shopifyThrottle ? 'YES' : 'no') . PHP_EOL;
"
```

## Check 9: Fee Sync Status

```bash
docker exec ecommerce_app php artisan tinker --execute="
  echo '--- Last Fee Calculation per Marketplace ---' . PHP_EOL;
  \$feeSyncs = DB::table('order_financials')
    ->join('orders', 'order_financials.order_id', '=', 'orders.id')
    ->selectRaw(\"
      CASE
        WHEN orders.ebay_order_id IS NOT NULL THEN 'ebay'
        WHEN orders.shopify_order_id IS NOT NULL THEN 'shopify'
        ELSE 'unknown'
      END as marketplace,
      max(order_financials.calculated_at) as last_calculated,
      count(*) as total_records,
      count(CASE WHEN order_financials.marketplace_fees IS NULL OR order_financials.marketplace_fees = 0 THEN 1 END) as missing_fees
    \")
    ->groupByRaw(\"
      CASE
        WHEN orders.ebay_order_id IS NOT NULL THEN 'ebay'
        WHEN orders.shopify_order_id IS NOT NULL THEN 'shopify'
        ELSE 'unknown'
      END
    \")
    ->get();
  foreach (\$feeSyncs as \$f) {
    echo \"  \$f->marketplace: last calc \$f->last_calculated, total: \$f->total_records, missing fees: \$f->missing_fees\" . PHP_EOL;
  }
  if (\$feeSyncs->isEmpty()) echo '  No financial records found.' . PHP_EOL;
"
```

## Check 10: Scheduled Listings Status

```bash
docker exec ecommerce_app php artisan tinker --execute="
  \$scheduled = DB::table('scheduled_listings')
    ->selectRaw(\"status, marketplace, count(*) as count\")
    ->groupBy('status', 'marketplace')
    ->orderBy('status')
    ->get();
  echo 'Scheduled Listings by Status:' . PHP_EOL;
  foreach (\$scheduled as \$s) {
    \$flag = in_array(\$s->status, ['failed', 'pending']) ? '[ATTENTION]' : '[OK]';
    echo \"  \$flag \$s->marketplace / \$s->status: \$s->count\" . PHP_EOL;
  }
  if (\$scheduled->isEmpty()) echo '  No scheduled listings found.' . PHP_EOL;

  echo PHP_EOL . '--- Overdue Pending Listings ---' . PHP_EOL;
  \$overdue = DB::table('scheduled_listings')
    ->where('status', 'pending')
    ->where('scheduled_at', '<=', now())
    ->select('id', 'marketplace', 'scheduled_at', 'product_id')
    ->orderBy('scheduled_at')
    ->limit(5)
    ->get();
  echo 'Overdue pending: ' . \$overdue->count() . PHP_EOL;
  if (\$overdue->isNotEmpty()) echo \$overdue->toJson(JSON_PRETTY_PRINT);
"
```

## Check 11: Repricing Status

```bash
docker exec ecommerce_app php artisan tinker --execute="
  \$active = DB::table('repricing_rules')
    ->where('is_active', true)
    ->selectRaw(\"strategy, count(*) as count, max(last_run_at) as latest_run\")
    ->groupBy('strategy')
    ->get();
  echo 'Active repricing rules:' . PHP_EOL;
  foreach (\$active as \$a) {
    \$hoursAgo = \$a->latest_run ? round(now()->diffInHours(\$a->latest_run), 1) : null;
    \$status = is_null(\$a->latest_run) ? '[NEVER RUN]' : (\$hoursAgo > 24 ? '[STALE]' : '[OK]');
    echo \"  \$status \$a->strategy: \$a->count rules, last run: \" . (\$a->latest_run ?? 'never') . PHP_EOL;
  }
  if (\$active->isEmpty()) echo '  No active repricing rules.' . PHP_EOL;

  \$recentChanges = DB::table('repricing_logs')
    ->where('applied_at', '>', now()->subDay())
    ->count();
  echo PHP_EOL . 'Price changes (24h): ' . \$recentChanges . PHP_EOL;
"
```

## Summary

After running all checks, provide a unified summary:

1. **Overall Sync Health**: healthy / degraded / critical
   - Critical: quantity mismatches > 10, or failed sync jobs > 5, or rate limiter in backoff
   - Degraded: any mismatches, failed jobs 1-5, or elevated queue depth
   - Healthy: all checks pass

2. **Per-Platform Status**:
   - eBay: listing counts, error rate, rate limiter status
   - Shopify: listing counts, error rate, throttle status

3. **Mismatches**: list any quantity discrepancies with product SKU/name

4. **Recommended Actions**:
   - Quantity mismatches: offer to dispatch `PushInventoryToMarketplaces` for affected products
   - Failed sync jobs: offer to retry specific jobs
   - Rate limiter issues: suggest waiting or checking credential health
   - High queue depth: suggest checking Horizon status
   - Missing marketplace fees: suggest re-syncing transaction data from eBay/Shopify
   - Overdue scheduled listings: suggest checking the listing scheduler job and queue health
   - Failed scheduled listings: suggest reviewing error messages and retrying
   - Stale repricing rules: suggest verifying the repricing scheduler is running
   - Never-run repricing rules: suggest triggering a manual repricing run
