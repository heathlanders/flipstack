---
name: procurement-debug
description: Quick health checks for purchase orders, receiving, and batch tracking
user-invocable: true
---

Diagnose procurement system health. Run ALL of these checks sequentially and report a summary at the end.

**IMPORTANT:** All PHP commands must run inside Docker: `docker exec ecommerce_app`

## Check 1: Open POs Past Expected Delivery Date

```bash
docker exec ecommerce_app php artisan tinker --execute="
  \$overdue = DB::table('purchase_orders')
    ->whereIn('status', ['submitted', 'partially_received'])
    ->whereNotNull('expected_delivery_date')
    ->where('expected_delivery_date', '<', now())
    ->whereNull('deleted_at')
    ->join('suppliers', 'purchase_orders.supplier_id', '=', 'suppliers.id')
    ->select('purchase_orders.id', 'purchase_orders.po_number', 'purchase_orders.status', 'purchase_orders.expected_delivery_date', 'purchase_orders.total_amount', 'suppliers.name as supplier_name')
    ->orderBy('expected_delivery_date')
    ->get();
  echo 'Overdue POs: ' . \$overdue->count() . PHP_EOL;
  if (\$overdue->count() > 0) echo \$overdue->toJson(JSON_PRETTY_PRINT);
"
```

## Check 2: POs with Receiving Discrepancies

```bash
docker exec ecommerce_app php artisan tinker --execute="
  \$discrepancies = DB::table('purchase_orders')
    ->join('purchase_order_items', 'purchase_orders.id', '=', 'purchase_order_items.purchase_order_id')
    ->where('purchase_orders.status', 'received')
    ->whereNull('purchase_orders.deleted_at')
    ->whereRaw(\"purchase_order_items.quantity_received != purchase_order_items.quantity_ordered\")
    ->select(
      'purchase_orders.id', 'purchase_orders.po_number',
      'purchase_order_items.sku', 'purchase_order_items.description',
      'purchase_order_items.quantity_ordered', 'purchase_order_items.quantity_received'
    )
    ->orderByDesc('purchase_orders.received_at')
    ->limit(15)
    ->get();
  echo 'Items with receiving discrepancies: ' . \$discrepancies->count() . PHP_EOL;
  if (\$discrepancies->count() > 0) echo \$discrepancies->toJson(JSON_PRETTY_PRINT);
"
```

## Check 3: Batches Without Cost Data

```bash
docker exec ecommerce_app php artisan tinker --execute="
  \$noCost = DB::table('inventory_batches')
    ->whereNull('deleted_at')
    ->where(function(\$q) {
      \$q->whereNull('acquisition_cost')
        ->orWhere('acquisition_cost', '=', 0);
    })
    ->select('id', 'batch_number', 'name', 'status', 'item_count', 'acquisition_date')
    ->orderByDesc('created_at')
    ->limit(10)
    ->get();
  echo 'Batches without cost data: ' . \$noCost->count() . PHP_EOL;
  if (\$noCost->count() > 0) echo \$noCost->toJson(JSON_PRETTY_PRINT);
"
```

## Check 4: Recent Receiving Activity (Last 7 Days)

```bash
docker exec ecommerce_app php artisan tinker --execute="
  echo '--- PO Status Distribution ---' . PHP_EOL;
  echo DB::table('purchase_orders')
    ->whereNull('deleted_at')
    ->selectRaw(\"status, count(*) as count, sum(total_amount) as total_value\")
    ->groupBy('status')
    ->orderBy('count', 'desc')
    ->get()->toJson(JSON_PRETTY_PRINT) . PHP_EOL;

  echo PHP_EOL . '--- Receipts in Last 7 Days ---' . PHP_EOL;
  \$receipts = DB::table('purchase_order_receipts')
    ->where('created_at', '>', now()->subDays(7))
    ->join('purchase_orders', 'purchase_order_receipts.purchase_order_id', '=', 'purchase_orders.id')
    ->select('purchase_orders.po_number', 'purchase_order_receipts.received_by', 'purchase_order_receipts.created_at')
    ->orderByDesc('purchase_order_receipts.created_at')
    ->get();
  echo 'Receipts (7d): ' . \$receipts->count() . PHP_EOL;
  if (\$receipts->count() > 0) echo \$receipts->take(10)->toJson(JSON_PRETTY_PRINT);

  echo PHP_EOL . PHP_EOL . '--- New Batches in Last 7 Days ---' . PHP_EOL;
  \$batches = DB::table('inventory_batches')
    ->whereNull('deleted_at')
    ->where('created_at', '>', now()->subDays(7))
    ->select('id', 'batch_number', 'name', 'status', 'item_count', 'acquisition_cost')
    ->orderByDesc('created_at')
    ->get();
  echo 'New batches (7d): ' . \$batches->count() . PHP_EOL;
  if (\$batches->count() > 0) echo \$batches->toJson(JSON_PRETTY_PRINT);
"
```

## Summary

After running all checks, provide a summary:

1. **Overall Status**: healthy / degraded / critical
   - Critical: overdue POs > 5, or batches without cost > 10
   - Degraded: any overdue POs, receiving discrepancies, or batches without cost 1-10
   - Healthy: all checks pass

2. **Issues Found**: list each issue with severity

3. **Recommended Actions**:
   - Overdue POs: suggest contacting suppliers or updating expected delivery dates
   - Receiving discrepancies: suggest reviewing partial receipts and reconciling quantities
   - Missing cost data: suggest updating batch acquisition costs for accurate P&L
   - Low receiving activity: flag if no receipts in 7 days for organizations with open POs
