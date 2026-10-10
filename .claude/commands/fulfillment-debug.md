---
name: fulfillment-debug
description: Diagnose fulfillment system health - orders, shipping, tracking, and returns
user-invocable: true
---

Diagnose fulfillment system health. Run ALL of these checks sequentially and report a summary at the end.

**IMPORTANT:** All PHP commands must run inside Docker: `docker exec ecommerce_app`

## Check 1: Order Status Distribution

```bash
docker exec ecommerce_app php artisan tinker --execute="
  echo DB::table('orders')
    ->selectRaw(\"status, count(*) as count\")
    ->groupBy('status')
    ->orderBy('count', 'desc')
    ->get()->toJson(JSON_PRETTY_PRINT);
"
```

## Check 2: Stuck Orders (assigned/picking/packing > 4 hours)

```bash
docker exec ecommerce_app php artisan tinker --execute="
  \$stuck = DB::table('orders')
    ->whereIn('status', ['assigned', 'picking', 'packing'])
    ->where('updated_at', '<', now()->subHours(4))
    ->select('id', 'status', 'updated_at', 'ebay_order_id')
    ->get();
  echo 'Stuck orders: ' . \$stuck->count() . PHP_EOL;
  if (\$stuck->count() > 0) echo \$stuck->take(5)->toJson(JSON_PRETTY_PRINT);
"
```

## Check 3: Failed Fulfillment Jobs (last 24h)

```bash
docker exec ecommerce_app php artisan tinker --execute="
  \$jobs = DB::table('failed_jobs')
    ->where('failed_at', '>', now()->subDay())
    ->where(function(\$q) {
      \$q->where('payload', 'like', '%Fulfillment%')
        ->orWhere('payload', 'like', '%Tracking%')
        ->orWhere('payload', 'like', '%UploadTracking%')
        ->orWhere('payload', 'like', '%SyncEbayOrders%');
    })
    ->orderByDesc('failed_at')->get();
  echo 'Failed fulfillment jobs (24h): ' . \$jobs->count() . PHP_EOL;
  foreach (\$jobs->take(5) as \$j) {
    \$p = json_decode(\$j->payload);
    echo '  ' . \$p->displayName . ' at ' . \$j->failed_at . PHP_EOL;
    echo '  ' . substr(\$j->exception, 0, 150) . PHP_EOL . PHP_EOL;
  }
"
```

## Check 4: Stale Shipments (shipped > 7 days, no delivery update)

```bash
docker exec ecommerce_app php artisan tinker --execute="
  \$stale = DB::table('shipments')
    ->whereIn('status', ['shipped', 'in_transit'])
    ->where('shipped_at', '<', now()->subDays(7))
    ->whereNull('delivered_at')
    ->select('id', 'order_id', 'tracking_number', 'shipping_provider', 'shipped_at', 'status')
    ->get();
  echo 'Stale shipments (7+ days): ' . \$stale->count() . PHP_EOL;
  if (\$stale->count() > 0) echo \$stale->take(5)->toJson(JSON_PRETTY_PRINT);
"
```

## Check 5: Pending Tracking Uploads

```bash
docker exec ecommerce_app php artisan tinker --execute="
  \$pending = DB::table('orders')
    ->join('shipments', 'orders.id', '=', 'shipments.order_id')
    ->where('orders.status', 'shipped')
    ->whereNotNull('orders.ebay_order_id')
    ->whereNotNull('shipments.tracking_number')
    ->whereNull('shipments.marketplace_tracking_uploaded_at')
    ->select('orders.id', 'orders.ebay_order_id', 'shipments.tracking_number', 'shipments.shipped_at')
    ->get();
  echo 'Pending tracking uploads: ' . \$pending->count() . PHP_EOL;
  if (\$pending->count() > 0) echo \$pending->take(5)->toJson(JSON_PRETTY_PRINT);
"
```

## Check 6: Shipping Provider Credential Health

```bash
docker exec ecommerce_app php artisan tinker --execute="
  \$provider = App\Models\Organization\OrganizationSettings::get(
    App\Models\Organization\Organization::first()?->id,
    'fulfillment_shipping_provider',
    'easypost'
  );
  echo 'Active shipping provider: ' . \$provider . PHP_EOL;

  \$creds = App\Models\Integration\IntegrationCredential::whereIn('platform', ['easypost', 'shipstation', 'shippingeasy'])
    ->select('id', 'platform', 'is_active', 'expires_at', 'updated_at')
    ->get();
  foreach (\$creds as \$c) {
    \$status = \$c->is_active ? 'ACTIVE' : 'INACTIVE';
    \$expiry = \$c->expires_at ? (\$c->expires_at < now() ? 'EXPIRED' : \$c->expires_at) : 'N/A';
    echo \"  [\$status] \$c->platform (id:\$c->id) expires: \$expiry\" . PHP_EOL;
  }
"
```

## Check 7: Horizon Queue Depth for Fulfillment Queues

```bash
docker exec ecommerce_app php artisan tinker --execute="
  foreach (['fulfillment', 'ebay', 'settlements', 'default'] as \$q) {
    \$depth = Illuminate\Support\Facades\Redis::llen('queues:' . \$q);
    \$status = \$depth > 100 ? '[HIGH]' : (\$depth > 20 ? '[MODERATE]' : '[OK]');
    echo \"\$status \$q: \$depth jobs\" . PHP_EOL;
  }
"
```

## Check 8: Routing Rule Evaluation

```bash
docker exec ecommerce_app php artisan tinker --execute="
  echo '--- Active Fulfillment Routing Rules ---' . PHP_EOL;
  \$rules = DB::table('fulfillment_routing_rules')
    ->where('is_active', true)
    ->select('id', 'name', 'rule_type', 'priority')
    ->orderBy('priority')
    ->get();
  echo 'Active routing rules: ' . \$rules->count() . PHP_EOL;
  foreach (\$rules as \$r) {
    echo \"  [P\$r->priority] \$r->name (\$r->rule_type)\" . PHP_EOL;
  }
  if (\$rules->isEmpty()) echo '  No active routing rules configured.' . PHP_EOL;

  echo PHP_EOL . '--- Recent Routing Decisions (Last 7 Days) ---' . PHP_EOL;
  \$routed = DB::table('orders')
    ->where('created_at', '>', now()->subDays(7))
    ->whereNotNull('assigned_location_id')
    ->join('inventory_locations', 'orders.assigned_location_id', '=', 'inventory_locations.id')
    ->selectRaw(\"inventory_locations.name as location_name, count(*) as count\")
    ->groupBy('inventory_locations.name')
    ->orderBy('count', 'desc')
    ->get();
  echo 'Orders routed by location (7d):' . PHP_EOL;
  foreach (\$routed as \$r) {
    echo \"  \$r->location_name: \$r->count orders\" . PHP_EOL;
  }
  if (\$routed->isEmpty()) echo '  No routed orders found.' . PHP_EOL;

  \$unrouted = DB::table('orders')
    ->where('created_at', '>', now()->subDays(7))
    ->whereNull('assigned_location_id')
    ->whereNotIn('status', ['cancelled', 'delivered'])
    ->count();
  echo PHP_EOL . 'Unrouted orders (7d, potential fallback): ' . \$unrouted . PHP_EOL;
"
```

## Check 9: Shipping Rule Matches

```bash
docker exec ecommerce_app php artisan tinker --execute="
  echo '--- Active Shipping Rules ---' . PHP_EOL;
  \$rules = DB::table('shipping_rules')
    ->where('is_active', true)
    ->select('id', 'name', 'priority', 'action_carrier', 'action_service')
    ->orderBy('priority')
    ->get();
  echo 'Active shipping rules: ' . \$rules->count() . PHP_EOL;
  foreach (\$rules as \$r) {
    echo \"  [P\$r->priority] \$r->name -> \$r->action_carrier / \$r->action_service\" . PHP_EOL;
  }
  if (\$rules->isEmpty()) echo '  No active shipping rules configured.' . PHP_EOL;

  echo PHP_EOL . '--- Carrier Usage (Last 7 Days) ---' . PHP_EOL;
  \$carriers = DB::table('shipments')
    ->where('created_at', '>', now()->subDays(7))
    ->selectRaw(\"shipping_provider, count(*) as count\")
    ->groupBy('shipping_provider')
    ->orderBy('count', 'desc')
    ->get();
  foreach (\$carriers as \$c) {
    echo \"  \$c->shipping_provider: \$c->count shipments\" . PHP_EOL;
  }
  if (\$carriers->isEmpty()) echo '  No shipments in last 7 days.' . PHP_EOL;

  echo PHP_EOL . '--- Manual Carrier Override Rate (7d) ---' . PHP_EOL;
  \$totalShipments = DB::table('shipments')
    ->where('created_at', '>', now()->subDays(7))
    ->count();
  \$manualOverrides = DB::table('shipments')
    ->where('created_at', '>', now()->subDays(7))
    ->where('carrier_manually_selected', true)
    ->count();
  if (\$totalShipments > 0) {
    \$overrideRate = round((\$manualOverrides / \$totalShipments) * 100, 1);
    echo \"  \$manualOverrides / \$totalShipments shipments manually overridden (\$overrideRate%)\" . PHP_EOL;
  } else {
    echo '  No shipments to calculate override rate.' . PHP_EOL;
  }
"
```

## Summary

After running all checks, provide a summary:

1. **Health Status**: healthy / degraded / critical
   - Critical: failed jobs > 5, or stuck orders > 10, or shipping credential expired
   - Degraded: any stuck orders, failed jobs 1-5, or stale shipments > 5
   - Healthy: all checks pass

2. **Issues Found**: list each issue with severity

3. **Recommended Actions**:
   - Stuck orders: offer to reset them to `assigned` status
   - Failed tracking uploads: offer to re-dispatch `UploadTrackingToEbay` jobs
   - Stale shipments: suggest running `CheckStaleShipments` job
   - Expired credentials: warn and suggest re-authentication
   - High queue depth: suggest checking Horizon status
   - Unrouted orders: suggest configuring fulfillment routing rules or assigning locations manually
   - High manual carrier override rate: suggest reviewing/adding shipping rules to improve auto-selection
   - No routing rules: suggest setting up at least one routing rule for automated fulfillment
