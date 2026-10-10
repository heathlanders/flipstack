---
name: fulfillment-debug
description: Quick fulfillment health checks and debugging commands for order sync, tracking, and shipment issues across all marketplaces
user-invocable: false
---

# Fulfillment Debug Skill

Quick reference for diagnosing fulfillment issues across eBay, Shopify, and Amazon.

## Health Check Commands

### Order Distribution by Marketplace
```bash
docker exec ecommerce_app php artisan tinker --execute="
  echo DB::table('orders')
    ->selectRaw(\"marketplace, status, count(*) as count\")
    ->groupBy('marketplace', 'status')
    ->orderBy('marketplace')->orderBy('count', 'desc')
    ->get()->toJson(JSON_PRETTY_PRINT);
"
```

### Stuck Orders (> 4 hours in active status)
```bash
docker exec ecommerce_app php artisan tinker --execute="
  echo DB::table('orders')
    ->whereIn('status', ['assigned', 'picking', 'packing'])
    ->where('updated_at', '<', now()->subHours(4))
    ->select('id', 'marketplace', 'status', 'updated_at', 'marketplace_order_id')
    ->get()->toJson(JSON_PRETTY_PRINT);
"
```

### Recent Sync Errors (All Marketplaces)
```bash
docker exec ecommerce_app php artisan tinker --execute="
  echo DB::table('failed_jobs')
    ->where('failed_at', '>', now()->subDay())
    ->where(function(\$q) {
      \$q->where('payload', 'like', '%SyncEbayOrders%')
        ->orWhere('payload', 'like', '%SyncShopifyOrders%')
        ->orWhere('payload', 'like', '%SyncAmazonOrders%')
        ->orWhere('payload', 'like', '%UploadTracking%')
        ->orWhere('payload', 'like', '%SyncRefund%');
    })
    ->selectRaw(\"id, substring(payload from '\"displayName\":\"([^\"]+)\"') as job, substring(exception, 1, 200) as error, failed_at\")
    ->orderByDesc('failed_at')->take(10)->get()->toJson(JSON_PRETTY_PRINT);
"
```

### Pending Tracking Uploads by Marketplace
```bash
docker exec ecommerce_app php artisan tinker --execute="
  echo DB::table('orders')
    ->join('shipments', 'orders.id', '=', 'shipments.order_id')
    ->where('orders.status', 'shipped')
    ->where('shipments.tracking_uploaded_to_marketplace', false)
    ->whereNotNull('shipments.tracking_number')
    ->selectRaw(\"orders.marketplace, count(*) as pending_uploads\")
    ->groupBy('orders.marketplace')
    ->get()->toJson(JSON_PRETTY_PRINT);
"
```

### Queue Depth
```bash
docker exec ecommerce_app php artisan tinker --execute="
  foreach (['fulfillment', 'ebay', 'shopify', 'amazon', 'default'] as \$q) {
    echo \$q . ': ' . Illuminate\Support\Facades\Redis::llen('queues:' . \$q) . PHP_EOL;
  }
"
```

### Last Successful Sync Per Marketplace
```bash
docker exec ecommerce_app php artisan tinker --execute="
  echo DB::table('orders')
    ->selectRaw(\"marketplace, max(created_at) as last_import, count(*) as total\")
    ->groupBy('marketplace')
    ->get()->toJson(JSON_PRETTY_PRINT);
"
```

## Resolution Commands

### Retry Failed Tracking Uploads
```bash
docker exec ecommerce_app php artisan tinker --execute="
  \$service = new App\Services\Fulfillment\TrackingService(new App\Services\Fulfillment\FulfillmentAuditService);
  \$count = \$service->retryFailedTrackingUploads();
  echo \"Dispatched \$count tracking upload retries\";
"
```

### Re-sync Orders for Specific Marketplace
```bash
# Shopify
docker exec ecommerce_app php artisan tinker --execute="
  App\Jobs\Fulfillment\SyncShopifyOrders::dispatch();
  echo 'Dispatched SyncShopifyOrders';
"

# Amazon
docker exec ecommerce_app php artisan tinker --execute="
  App\Jobs\Fulfillment\SyncAmazonOrders::dispatch();
  echo 'Dispatched SyncAmazonOrders';
"

# eBay
docker exec ecommerce_app php artisan tinker --execute="
  App\Jobs\Fulfillment\SyncEbayOrders::dispatch();
  echo 'Dispatched SyncEbayOrders';
"
```

### Reset Stuck Orders
```bash
docker exec ecommerce_app php artisan tinker --execute="
  \$count = App\Models\Fulfillment\Order::whereIn('status', ['picking', 'packing'])
    ->where('updated_at', '<', now()->subHours(4))
    ->update(['status' => 'assigned', 'updated_at' => now()]);
  echo \"Reset \$count stuck orders to assigned\";
"
```

## Tracking / Delivery Status Diagnostics

### Inspect a single shipment (provider, token, raw eBay XML, parsed result)
```bash
# By Shipment UUID
docker exec ecommerce_app php artisan tracking:debug --shipment=SHIPMENT_UUID

# By Order UUID (uses the latest shipment on that order)
docker exec ecommerce_app php artisan tracking:debug --order=ORDER_UUID

# Bypass the 6h Trading API cache so the next fetch re-hits eBay
docker exec ecommerce_app php artisan tracking:debug --order=ORDER_UUID --no-cache
```

Prints: shipment + order rows; which `TrackingProvider` matched; for eBay, the resolved IntegrationCredential + OAuth token state + cache key + cache occupancy; the parsed `TrackingResult` (latestStatus, deliveredAt, events). Read-only — no DB writes.

Use when `/fulfillment/orders/{id}` doesn't show as delivered even though eBay does. Three outcomes drive next steps:

1. **`latestStatus=delivered` + `deliveredAt=...`** — adapter works. The event description tells you the source:
   - `Delivered (per eBay)` — Trading API `ActualDeliveryTime` (carrier-confirmed via eBay).
   - `Delivered (presumed — eBay max estimated delivery date elapsed, FULFILLED)` — REST fallback path: order is `FULFILLED` and `maxEstimatedDeliveryDate` has passed, so we presume delivery from the data eBay already gave us at order import. Honest signal, not a carrier scan.
   Re-run sync to write `actual_delivery_date`: `App\Jobs\Fulfillment\SyncShipmentTracking::dispatchSync('SHIPMENT_UUID');`.
2. **`isEmpty()=true`** — neither eBay signal is present yet (Trading API has no `ActualDeliveryTime`, AND order is not yet `FULFILLED` or max delivery date is still in the future). Check `Order.marketplace_status` and `Order.marketplace_data->fulfillmentStartInstructions->0->maxEstimatedDeliveryDate`. Either wait, wire a carrier API (USPS Web Tools is free), or use the manual override.
3. **token error / no credential** — fix the org's eBay OAuth, not a code bug.

### Re-trigger the cron sweeper for all eligible shipments
```bash
docker exec ecommerce_app php artisan tinker --execute="App\Jobs\Fulfillment\SyncAllActiveShipmentTracking::dispatchSync();"
```

### Sync one shipment immediately (synchronous, useful for tests / one-offs)
```bash
docker exec ecommerce_app php artisan tinker --execute="App\Jobs\Fulfillment\SyncShipmentTracking::dispatchSync('SHIPMENT_UUID');"
```

## ShippingEasy Diagnostics

### ShippingEasy Sync Log Errors (Last 24h)
```bash
docker exec ecommerce_app php artisan tinker --execute="
  echo DB::table('shipping_easy_sync_logs')
    ->where('status', 'error')
    ->where('created_at', '>', now()->subDay())
    ->select('id', 'operation', 'api_endpoint', 'http_status_code', 'error_message', 'created_at')
    ->orderByDesc('created_at')->take(20)->get()->toJson(JSON_PRETTY_PRINT);
"
```

### ShippingEasy Operation Summary (Last 24h)
```bash
docker exec ecommerce_app php artisan tinker --execute="
  echo DB::table('shipping_easy_sync_logs')
    ->where('created_at', '>', now()->subDay())
    ->selectRaw(\"operation, status, count(*) as count, round(avg(response_time_ms)) as avg_ms\")
    ->groupBy('operation', 'status')
    ->orderBy('operation')
    ->get()->toJson(JSON_PRETTY_PRINT);
"
```

### ShippingEasy Connection Status
```bash
docker exec ecommerce_app php artisan tinker --execute="
  \$creds = App\Models\Integration\IntegrationCredential::where('platform', 'shippingeasy')
    ->where('is_active', true)->get();
  foreach (\$creds as \$c) {
    echo 'Org '.\$c->organization_id.': key='.substr(\$c->decrypted_client_id, 0, 8).'... store_key='.(\$c->config['store_api_key'] ?? 'NOT SET').PHP_EOL;
  }
"
```

### ShippingEasy Rate Limit Hits (Last 24h)
```bash
docker exec ecommerce_app php artisan tinker --execute="
  echo DB::table('shipping_easy_sync_logs')
    ->where('http_status_code', 429)
    ->where('created_at', '>', now()->subDay())
    ->select('id', 'operation', 'api_endpoint', 'error_message', 'created_at')
    ->orderByDesc('created_at')->take(20)->get()->toJson(JSON_PRETTY_PRINT);
"
```

### ShippingEasy Failed Webhook Jobs
```bash
docker exec ecommerce_app php artisan tinker --execute="
  echo DB::table('failed_jobs')
    ->where('failed_at', '>', now()->subDay())
    ->where('payload', 'like', '%ShippingEasy%')
    ->selectRaw(\"id, substring(exception, 1, 200) as error, failed_at\")
    ->orderByDesc('failed_at')->take(10)->get()->toJson(JSON_PRETTY_PRINT);
"
```

## Audit Logging

Debug skill -- does not write audit; reads `user_activity_logs` to confirm whether a mutation call site executed. See the Audit Trail section in `.claude/agents/debug-fulfillment.md`.

Trait: models in this feature use `LogsAudit` (`app/Traits/LogsAudit.php`) for auto CRUD diff. Domain lifecycle events emitted from none -- read-only via `UserActivityLog::log(action, desc, $user, $metadata, $severity, $category)`.

n/a

Spec: `docs/AUDIT_SYSTEM.md`. Enforced rule: `.claude/rules/audit-logging.md`. Tests: `use Tests\Support\AssertsAudits;` then `$this->assertAudited('action', $user, [...])`.
