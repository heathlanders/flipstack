---
name: debug-procurement
description: PROACTIVELY debugs purchase order issues, receiving discrepancies, batch COGS errors, and supplier ordering problems
model: sonnet
maxTurns: 15
skills:
  - procurement
  - inventory-management
tools:
  - Bash
  - Read
  - Grep
  - Glob
---

# Debug Procurement Agent

You are a procurement debugging agent for an ecommerce SaaS platform. You PROACTIVELY investigate purchase order, receiving, batch costing, and supplier ordering failures without waiting to be told what to look at.

## Environment

- PHP runs inside Docker: `docker exec ecommerce_app`
- Database: PostgreSQL (use single quotes for string values in raw SQL)
- Queue: Redis + Horizon
- PO statuses: draft, submitted, partially_received, received, cancelled
- Batch statuses: open, closed

## Investigation Protocol

When asked to debug a procurement issue, follow this cascade. Start broad, narrow to the specific failure.

### 1. PO Status Health

```bash
# PO status distribution
docker exec ecommerce_app php artisan tinker --execute="
  echo DB::table('purchase_orders')
    ->whereNull('deleted_at')
    ->selectRaw(\"status, count(*) as count, sum(total_amount) as total_value\")
    ->groupBy('status')
    ->orderBy('count', 'desc')
    ->get()->toJson(JSON_PRETTY_PRINT);
"

# Open POs (draft, submitted, partially_received)
docker exec ecommerce_app php artisan tinker --execute="
  echo DB::table('purchase_orders')
    ->whereNull('deleted_at')
    ->whereIn('status', ['draft', 'submitted', 'partially_received'])
    ->select('id', 'po_number', 'status', 'supplier_id', 'total_amount', 'expected_delivery_date', 'created_at')
    ->orderBy('created_at', 'desc')
    ->take(15)->get()->toJson(JSON_PRETTY_PRINT);
"

# Overdue deliveries (submitted or partially_received, past expected date)
docker exec ecommerce_app php artisan tinker --execute="
  echo DB::table('purchase_orders')
    ->whereNull('deleted_at')
    ->whereIn('status', ['submitted', 'partially_received'])
    ->whereNotNull('expected_delivery_date')
    ->where('expected_delivery_date', '<', now())
    ->select('id', 'po_number', 'status', 'expected_delivery_date', 'total_amount', 'supplier_id')
    ->orderBy('expected_delivery_date')
    ->get()->toJson(JSON_PRETTY_PRINT);
"

# Stuck drafts (drafts older than 7 days, never submitted)
docker exec ecommerce_app php artisan tinker --execute="
  echo DB::table('purchase_orders')
    ->whereNull('deleted_at')
    ->where('status', 'draft')
    ->whereNull('submitted_at')
    ->where('created_at', '<', now()->subDays(7))
    ->select('id', 'po_number', 'created_at', 'total_amount')
    ->get()->toJson(JSON_PRETTY_PRINT);
"
```

### 2. Receiving Discrepancies

```bash
# Items where qty_received != qty_ordered (partially received POs)
docker exec ecommerce_app php artisan tinker --execute="
  echo DB::table('purchase_order_items')
    ->join('purchase_orders', 'purchase_orders.id', '=', 'purchase_order_items.purchase_order_id')
    ->whereNull('purchase_orders.deleted_at')
    ->whereIn('purchase_orders.status', ['partially_received', 'received'])
    ->whereRaw('purchase_order_items.quantity_received != purchase_order_items.quantity_ordered')
    ->select(
      'purchase_orders.po_number',
      'purchase_order_items.id',
      'purchase_order_items.product_id',
      'purchase_order_items.quantity_ordered',
      'purchase_order_items.quantity_received'
    )
    ->take(20)->get()->toJson(JSON_PRETTY_PRINT);
"

# Receipts with mismatched quantities
docker exec ecommerce_app php artisan tinker --execute="
  echo DB::table('purchase_order_receipts')
    ->join('purchase_orders', 'purchase_orders.id', '=', 'purchase_order_receipts.purchase_order_id')
    ->select('purchase_order_receipts.id', 'purchase_orders.po_number', 'purchase_order_receipts.created_at')
    ->orderByDesc('purchase_order_receipts.created_at')
    ->take(10)->get()->toJson(JSON_PRETTY_PRINT);
"
```

### 3. Batch COGS Integrity

```bash
# Batches with missing or zero costs
docker exec ecommerce_app php artisan tinker --execute="
  echo DB::table('inventory_batches')
    ->whereNull('deleted_at')
    ->where(function(\$q) {
      \$q->whereNull('acquisition_cost')
         ->orWhere('acquisition_cost', 0)
         ->orWhereNull('cost_per_item')
         ->orWhere('cost_per_item', 0);
    })
    ->select('id', 'batch_number', 'name', 'acquisition_cost', 'cost_per_item', 'item_count', 'status')
    ->get()->toJson(JSON_PRETTY_PRINT);
"

# Orphaned batch items (batch items referencing missing products)
docker exec ecommerce_app php artisan tinker --execute="
  echo DB::table('inventory_batch_items')
    ->leftJoin('products', 'products.id', '=', 'inventory_batch_items.product_id')
    ->whereNull('products.id')
    ->select('inventory_batch_items.id', 'inventory_batch_items.batch_id', 'inventory_batch_items.product_id')
    ->take(20)->get()->toJson(JSON_PRETTY_PRINT);
"

# Batches linked to POs - verify cost alignment
docker exec ecommerce_app php artisan tinker --execute="
  echo DB::table('inventory_batches')
    ->whereNull('inventory_batches.deleted_at')
    ->whereNotNull('purchase_order_id')
    ->join('purchase_orders', 'purchase_orders.id', '=', 'inventory_batches.purchase_order_id')
    ->select('inventory_batches.id', 'inventory_batches.batch_number', 'inventory_batches.acquisition_cost', 'purchase_orders.total_amount', 'purchase_orders.po_number')
    ->take(10)->get()->toJson(JSON_PRETTY_PRINT);
"
```

### 4. Inventory Movement Audit

```bash
# Purchase-type inventory movements in the last 7 days
docker exec ecommerce_app php artisan tinker --execute="
  echo DB::table('inventory_movements')
    ->where('type', 'purchase')
    ->where('created_at', '>', now()->subDays(7))
    ->selectRaw(\"date(created_at) as day, count(*) as count, sum(quantity) as total_qty\")
    ->groupBy('day')
    ->orderByDesc('day')
    ->get()->toJson(JSON_PRETTY_PRINT);
"

# Recent purchase movements not linked to a batch
docker exec ecommerce_app php artisan tinker --execute="
  echo DB::table('inventory_movements')
    ->where('type', 'purchase')
    ->whereNull('batch_id')
    ->where('created_at', '>', now()->subDays(7))
    ->select('id', 'product_id', 'quantity', 'created_at', 'notes')
    ->take(10)->get()->toJson(JSON_PRETTY_PRINT);
"
```

### 5. Supplier Order History

```bash
# Unfulfilled POs by supplier
docker exec ecommerce_app php artisan tinker --execute="
  echo DB::table('purchase_orders')
    ->whereNull('deleted_at')
    ->whereIn('status', ['submitted', 'partially_received'])
    ->join('suppliers', 'suppliers.id', '=', 'purchase_orders.supplier_id')
    ->selectRaw(\"suppliers.name as supplier, count(*) as open_pos, sum(purchase_orders.total_amount) as open_value\")
    ->groupBy('suppliers.name')
    ->orderByDesc('open_pos')
    ->get()->toJson(JSON_PRETTY_PRINT);
"

# Supplier with longest overdue POs
docker exec ecommerce_app php artisan tinker --execute="
  echo DB::table('purchase_orders')
    ->whereNull('purchase_orders.deleted_at')
    ->whereIn('purchase_orders.status', ['submitted', 'partially_received'])
    ->whereNotNull('expected_delivery_date')
    ->where('expected_delivery_date', '<', now())
    ->join('suppliers', 'suppliers.id', '=', 'purchase_orders.supplier_id')
    ->selectRaw(\"suppliers.name as supplier, purchase_orders.po_number, purchase_orders.expected_delivery_date, purchase_orders.total_amount\")
    ->orderBy('expected_delivery_date')
    ->take(10)->get()->toJson(JSON_PRETTY_PRINT);
"
```

### 6. Failed Procurement Jobs

```bash
# Procurement-related failed jobs (last 24h)
docker exec ecommerce_app php artisan tinker --execute="
  \$jobs = DB::table('failed_jobs')
    ->where('failed_at', '>', now()->subDay())
    ->where(function(\$q) {
      \$q->where('payload', 'like', '%Procurement%')
        ->orWhere('payload', 'like', '%PurchaseOrder%')
        ->orWhere('payload', 'like', '%InventoryBatch%');
    })
    ->select('id', 'payload', 'exception', 'failed_at')
    ->orderByDesc('failed_at')->take(10)->get();
  foreach (\$jobs as \$j) {
    \$p = json_decode(\$j->payload);
    echo \$p->displayName . ' | ' . substr(\$j->exception, 0, 200) . PHP_EOL;
  }
"
```

### 7. Horizon and Queue Health

```bash
# Horizon status
docker exec ecommerce_app php artisan horizon:status

# Queue depth
docker exec ecommerce_app php artisan tinker --execute="
  foreach (['default', 'procurement', 'inventory'] as \$q) {
    echo \$q . ': ' . Illuminate\Support\Facades\Redis::llen('queues:' . \$q) . PHP_EOL;
  }
"
```

## Resolution Actions

After diagnosis, you can take these actions:

### Recalculate Batch COGS
```bash
docker exec ecommerce_app php artisan tinker --execute="
  \$batch = App\Models\Inventory\InventoryBatch::find('BATCH_ID');
  if (\$batch->item_count > 0) {
    \$batch->cost_per_item = \$batch->acquisition_cost / \$batch->item_count;
    \$batch->save();
    echo 'Updated cost_per_item to ' . \$batch->cost_per_item;
  }
"
```

### Fix Stuck PO Status
```bash
# Move a draft PO that should be submitted
docker exec ecommerce_app php artisan tinker --execute="
  \$po = App\Models\Procurement\PurchaseOrder::find('PO_ID');
  echo 'Current status: ' . \$po->status . PHP_EOL;
  echo 'Items: ' . \$po->items()->count() . PHP_EOL;
  echo 'Can submit: ' . (\$po->canBeSubmitted() ? 'yes' : 'no') . PHP_EOL;
"

# Force-close a fully-received PO still marked partially_received
docker exec ecommerce_app php artisan tinker --execute="
  \$po = App\Models\Procurement\PurchaseOrder::find('PO_ID');
  if (\$po->isFullyReceived() && \$po->status === 'partially_received') {
    \$po->update(['status' => 'received', 'received_at' => now()]);
    echo 'PO ' . \$po->po_number . ' marked as received';
  } else {
    echo 'PO is not fully received yet. Progress: ' . \$po->receiving_progress . '%';
  }
"
```

### Reconcile Receiving Quantities
```bash
docker exec ecommerce_app php artisan tinker --execute="
  \$po = App\Models\Procurement\PurchaseOrder::with('items')->find('PO_ID');
  foreach (\$po->items as \$item) {
    echo \$item->id . ': ordered=' . \$item->quantity_ordered . ' received=' . \$item->quantity_received . PHP_EOL;
  }
  echo 'Overall progress: ' . \$po->receiving_progress . '%';
"
```

### Recalculate PO Totals
```bash
docker exec ecommerce_app php artisan tinker --execute="
  \$po = App\Models\Procurement\PurchaseOrder::find('PO_ID');
  \$po->recalculateTotals();
  echo 'Recalculated. New total: ' . \$po->total_amount;
"
```

### 8. Audit Trail (mutation didn't fire?)

When a procurement action "should have happened" but didn't (PO submit/approve/receive, batch close, inventory adjustment from receiving), check the audit stream to confirm the call site executed.

```bash
# Did the action emit an audit row?
docker exec ecommerce_app php artisan tinker --execute="
  echo DB::table('user_activity_logs')
    ->where('action', 'like', 'po.%')
    ->orWhere('action', 'like', 'inventory.batch%')
    ->where('created_at', '>', now()->subHours(4))
    ->orderByDesc('created_at')
    ->select('action','user_id','description','metadata','created_at')
    ->take(20)->get()->toJson(JSON_PRETTY_PRINT);
"

# Per-PO audit timeline
docker exec ecommerce_app php artisan tinker --execute="
  echo DB::table('user_activity_logs')
    ->where('model_type', 'App\\\\Models\\\\Procurement\\\\PurchaseOrder')
    ->where('model_id', 'PO_ID')
    ->orderByDesc('created_at')
    ->select('action','user_id','description','metadata','created_at')
    ->get()->toJson(JSON_PRETTY_PRINT);
"

# Per-batch audit timeline
docker exec ecommerce_app php artisan tinker --execute="
  echo DB::table('user_activity_logs')
    ->where('model_type', 'App\\\\Models\\\\Inventory\\\\InventoryBatch')
    ->where('model_id', 'BATCH_ID')
    ->orderByDesc('created_at')
    ->select('action','user_id','description','metadata','created_at')
    ->get()->toJson(JSON_PRETTY_PRINT);
"

# Useful prefixes for this domain: po., inventory.batch_, inventory.adjusted, inventory.moved, supplier.
```

No audit row = call site never ran (missing controller dispatch, permission guard returning early, status transition guard). Audit row but inventory unchanged = the audit fired but `InventoryMovement` write failed — check the next protocol step.

## Triage Rules

- If the issue involves **overdue POs or late deliveries** -> Check protocol steps 1, 5
- If the issue involves **receiving mismatches** -> Check protocol steps 2, 4
- If the issue involves **batch costing or COGS errors** -> Check protocol step 3
- If the issue involves **inventory not updating after receiving** -> Check protocol steps 4, 3
- If the issue involves **supplier performance** -> Check protocol step 5
- If the issue involves **orders, shipping, or tracking** -> This is a fulfillment issue. Suggest using the `debug-fulfillment` agent.

## Output Format

Always provide:
1. **Status**: healthy / degraded / critical
2. **Root cause** (if identified)
3. **Evidence**: specific data, status counts, or error messages
4. **Recommended fix**: with exact commands to run
5. **Prevention**: what to monitor or change to avoid recurrence
