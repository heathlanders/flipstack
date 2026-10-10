---
name: inventory-management
description: Warehouse inventory levels, locations, stock movements, adjustments, transfers, low stock alerts, and inventory analytics (ABC/turnover/valuation)
user-invocable: false
---

# Inventory Management

## Key Files

| Area | File | Purpose |
|------|------|---------|
| **Models** | `app/Models/Inventory/InventoryLevel.php` | Per-product per-location stock (on_hand, reserved, available) |
| | `app/Models/Inventory/InventoryMovement.php` | Signed-quantity stock change audit trail (9 types) |
| | `app/Models/Inventory/InventoryLocation.php` | Warehouse/store/supplier locations with shipping config |
| **Services** | `app/Services/Inventory/InventoryService.php` | Stock adjust, transfer, reserve (optimistic locking), valuation |
| | `app/Services/Inventory/InventoryMovementService.php` | Movement recording, history, summary aggregation |
| | `app/Services/Inventory/InventoryAnalyticsService.php` | KPIs, value trends, stock health, movement trends |
| | `app/Services/Inventory/ABCAnalysisService.php` | ABC classification by revenue/value |
| | `app/Services/Inventory/TurnoverAnalysisService.php` | Turnover ratios, days of supply, trend analysis |
| **Controllers** | `app/Http/Controllers/Inventory/InventoryController.php` | Dashboard, levels, movements, adjust, transfer, reserve/release |
| | `app/Http/Controllers/Inventory/InventoryMovementController.php` | Movement CRUD, product stock levels view |
| | `app/Http/Controllers/Inventory/LocationController.php` | Location CRUD, address verification, stats |
| | `app/Http/Controllers/Inventory/InventoryAnalyticsController.php` | Analytics dashboard, ABC, turnover, valuation, export |
| **Jobs** | `app/Jobs/Inventory/BulkStockAdjustment.php` | Bulk add/subtract/set stock (optimistic locking) |
| | `app/Jobs/Inventory/BulkLocationTransfer.php` | Bulk transfer between locations (paired movements) |
| | `app/Jobs/Inventory/GenerateLowStockAlerts.php` | Stub -- not yet implemented |
| | `app/Jobs/Inventory/PushInventoryToMarketplaces.php` | Push `available_quantity` to all marketplaces |

## Inventory Level Fields

| Field | Purpose |
|-------|---------|
| `quantity_on_hand` | Physical stock at this location |
| `quantity_reserved` | Reserved for orders at this location |
| `quantity_available` | Computed: `on_hand - reserved` |
| `reorder_point` | Threshold for low stock alerts |
| `reorder_quantity` | Suggested reorder amount |
| `max_stock_level` | Threshold for overstock alerts |
| `average_cost` | Weighted average cost per unit |
| `last_movement_at` | Timestamp of last stock change |
| `last_count_at` | Timestamp of last physical count |

## Relationship: Warehouse Level vs Cross-Marketplace

**`InventoryLevel`** tracks stock at individual warehouse locations (per-product, per-location).

**`Product.available_quantity`** is the **single source of truth** for cross-marketplace selling quantity. When an order comes in from any marketplace:
1. `OrderSyncService::reserveInventoryForOrder()` decrements `Product.available_quantity`
2. `PushInventoryToMarketplaces` job pushes the new quantity to connected marketplaces where `auto_sync_enabled=true`

When the order is shipped:
- `FulfillmentService::shipOrder()` decrements `InventoryLevel.quantity_on_hand` and `quantity_reserved` at the fulfillment location

This means `Product.available_quantity` changes **immediately** on order import, while `InventoryLevel` changes happen later during fulfillment.

### Two product-level quantity columns: `available_quantity` vs `stock_quantity`

Both columns exist on `products` and they represent different points in the lifecycle:

- `available_quantity` — sellable / pushed to marketplaces. Decremented on order import. **The product hub UI binds this column.**
- `stock_quantity` — legacy physical-on-hand. Decremented on physical ship/pick.

They diverge by design between order placement and physical pick. After May 2026 every manual writer mirrors the delta to **both** columns and dispatches `PushInventoryToMarketplaces`.

### Writers reference

| Writer | File | Touches `stock_quantity` | Touches `available_quantity` | Dispatches push |
|---|---|---|---|---|
| eBay/Shopify/Amazon order import | `app/Services/Fulfillment/*OrderSyncService.php` | no | yes (decrement) | yes |
| Receiving | `app/Services/Procurement/ReceivingService.php` | yes (increment) | yes (increment) | yes |
| Adjust Stock modal | `ProductController::adjustStock` | yes | yes (mirror delta) | yes |
| Movements form | `InventoryMovementController::store` | yes | yes (mirror delta) | yes |
| Bulk Stock Adjustment | `BulkStockAdjustment` job | yes | yes (mirror delta) | yes |
| Ship / pick | `FulfillmentService::shipOrder` | yes (decrement) | no (already decremented at order placement) | n/a |

If you add a new writer of either column, mirror the delta and dispatch `PushInventoryToMarketplaces::dispatch($productId)` — otherwise marketplace listings will silently drift.

## Inventory Movement Types

| Type | Value | Direction | Use Case |
|------|-------|-----------|----------|
| `purchase` | `purchase` | + | Receiving new stock |
| `sale` | `sale` | - | Sold/committed stock |
| `adjustment` | `adjustment` | +/- | Count corrections |
| `transfer_in` | `transfer_in` | + | Received from another location |
| `transfer_out` | `transfer_out` | - | Sent to another location |
| `return` | `return` | + | Customer/supplier return |
| `damaged` | `damaged` | - | Damaged stock write-off |
| `lost` | `lost` | - | Missing stock write-off |
| `found` | `found` | + | Previously lost stock recovered |

Movements use signed `quantity`: positive = stock increase, negative = stock decrease. Each movement records `balance_before` and `balance_after` for audit trail.

## Movement Reason Codes

`recount`, `damaged`, `theft`, `found`, `received`, `returned`, `correction`, `expired`, `quality`, `other`

## Location Types

`warehouse`, `store`, `supplier`, `customer`, `virtual`

## Location Shipping Fields

| Field | Purpose |
|-------|---------|
| `easypost_address_id` | Verified EasyPost address ID |
| `is_ship_from` | Can be used as ship-from address |
| `ship_from_verified` | Address verified with EasyPost |
| `default_weight_oz` | Default package weight |
| `default_length/width/height` | Default package dimensions |
| `shipping_label_printer_id` | Auto-print shipping labels |
| `packing_slip_printer_id` | Auto-print packing slips |
| `pick_list_printer_id` | Auto-print pick lists |
| `auto_print_shipping_label/packing_slip/pick_list` | Auto-print toggles |

## Stock Operations

### Adjust Stock
`InventoryService::adjustStock(productId, locationId, qty, reason, ...)` -- creates an `adjustment` movement, updates `InventoryLevel` with weighted average cost if `unitCost` provided.

### Transfer Stock
`InventoryService::transferStock(productId, fromId, toId, qty, ...)` -- creates paired `transfer_out` + `transfer_in` movements in a transaction. Throws `InsufficientStockException` if source doesn't have enough.

### Reserve Stock (Optimistic Locking)
`InventoryService::reserveStock(...)` -- uses `version` column for optimistic concurrency. If another process modified the row, throws `InventoryException`. Increments `quantity_reserved`, decrements available.

### Release Reserved Stock
`InventoryService::releaseReservedStock(...)` -- also uses `version` optimistic lock. Decrements `quantity_reserved`.

### Commit Reserved Stock
`InventoryService::commitReservedStock(...)` -- converts reservation into a `sale` movement. Decrements both `quantity_on_hand` and `quantity_reserved`.

## Bulk Operations

### BulkStockAdjustment
- Queue: default, timeout: 3600s, tries: 3
- Types: `add`, `subtract`, `set`
- Uses `lockForUpdate()` on each InventoryLevel row
- Falls back to default location if `location_id` not specified
- Updates `InventoryLevel`, `Product.stock_quantity`, AND mirrors the same delta to `Product.available_quantity`
- Dispatches `PushInventoryToMarketplaces` once per affected product after the bulk loop, so manual bulk corrections propagate to eBay/Shopify

### BulkLocationTransfer
- Queue: default, timeout: 3600s, tries: 3
- Validates: both locations exist, active, same org, not the same
- Reference: `TRF-{8 random chars}-{jobId}`
- Uses `lockForUpdate()` on both InventoryLevel rows
- Updates weighted average cost at destination
- Creates paired transfer_out + transfer_in movements
- Skips products with no stock or all reserved (warning, not failure)

## Inventory Analytics

### Permissions
- `analytics.view` -- most analytics endpoints
- `reports.view` -- valuation report
- `analytics.export` -- export data

### Available Analytics
| Endpoint | Service | What It Provides |
|----------|---------|-----------------|
| `getKPIs` | `InventoryAnalyticsService` | Key performance indicators |
| `getInventoryValue` | `InventoryAnalyticsService` | Total valuation over time |
| `getStockHealth` | `InventoryAnalyticsService` | In-stock/low/out/overstock distribution |
| `getMovementTrends` | `InventoryAnalyticsService` | Movement volume by type over time |
| `getTopPerformers` | `InventoryAnalyticsService` | Best-selling/highest-value products |
| `getLocationPerformance` | `InventoryAnalyticsService` | Per-location inventory metrics |
| `getABCAnalysis` | `ABCAnalysisService` | ABC classification (configurable A/B thresholds) |
| `getTurnoverAnalysis` | `TurnoverAnalysisService` | Turnover ratio, days of supply per product |
| `getTurnoverTrend` | `TurnoverAnalysisService` | Turnover over time (month/day intervals) |

### ABC Analysis
- Default thresholds: A = 80%, B = 95% (of cumulative value)
- Metric options: `revenue` (default)
- Returns: SKU, Name, Category, Brand, Value, % of Total, Cumulative %, ABC Class

### Turnover Analysis
- Configurable `period` in days (default 365)
- Returns: SKU, Name, Category, Stock Qty, Units Sold, Turnover Ratio, Days of Supply (DSI), Revenue, Profit

### Export
Formats: `csv`, `json`. Types: ABC analysis, turnover analysis.

## Low Stock Alerts

`InventoryLevel` scopes: `lowStock` (qty_on_hand <= reorder_point), `outOfStock` (qty_on_hand = 0), `overStock` (qty_on_hand > max_stock_level)

`InventoryService::getLowStockAlerts($organizationId)` returns active products where `quantity_available <= reorder_point`.

`Product::isLowStock()` returns true when `stock_quantity <= low_stock_threshold`.

**Note:** `GenerateLowStockAlerts` job exists but is a stub (empty `handle()` method).

## Business Rules

1. **Cannot delete location** with `quantity_on_hand > 0` -- enforced in `LocationController::destroy`
2. **Single default location** -- setting a new default unsets all others
3. **Optimistic locking** on reservations prevents race conditions
4. **Weighted average cost** updated on purchases and transfers
5. **Movement audit trail** records `balance_before`/`balance_after` for every stock change

## Batch/Lot Tracking

### Key Files
| File | Purpose |
|------|---------|
| `app/Models/Inventory/InventoryBatch.php` | Batch record with COGS, expiry, supplier reference |
| `app/Models/Inventory/InventoryBatchItem.php` | Links batch to individual product quantities |
| `app/Services/Inventory/BatchTrackingService.php` | Batch creation, COGS calculation, expiry management |

### Batch Creation on PO Receiving
When a Purchase Order is received, `BatchTrackingService` creates an `InventoryBatch` record per PO receipt. Each line item becomes an `InventoryBatchItem` with the unit cost from the PO, enabling FIFO/weighted-average COGS tracking.

### COGS Tracking per Batch
Each `InventoryBatchItem` stores the cost at time of receipt. When stock is consumed (sale, adjustment), the service resolves which batch(es) to draw from based on the configured costing method. This feeds into the P&L analytics.

### Batch Expiry Management
Batches with an `expires_at` date are monitored. Expiring batches can trigger low-stock-style alerts and are prioritized in pick path ordering (FEFO -- First Expired, First Out).

---

## Bin Locations

### Key Files
| File | Purpose |
|------|---------|
| `app/Models/Inventory/WarehouseZone.php` | Zone within a warehouse location (receiving, storage, shipping, etc.) |
| `app/Models/Inventory/WarehouseBin.php` | Individual bin/slot within a zone |

### Zone Types
Zones categorize areas within a warehouse: `receiving`, `storage`, `picking`, `packing`, `shipping`, `returns`, `quarantine`.

### Bin Code Format
Bins use a hierarchical code format: `{Zone}-{Aisle}-{Rack}-{Shelf}-{Position}` (e.g., `STR-A-03-02-01`). Codes are unique within a location.

### Product-to-Bin Assignment
Products are assigned to bins via `WarehouseBin` relationships. A product can exist in multiple bins (e.g., primary pick bin + overstock bin). The bin assignment includes quantity tracking.

### Pick Path Optimization
During the pick phase, items are sorted by bin location to minimize travel distance. The sort order follows zone priority, then aisle, rack, shelf, and position sequentially.

---

## Bundles

### Key Files
| File | Purpose |
|------|---------|
| `app/Models/Catalog/ProductBundle.php` | Bundle definition linking a parent product to components |
| `app/Models/Catalog/ProductBundleItem.php` | Individual component with quantity per bundle |
| `app/Services/Catalog/BundleService.php` | Bundle CRUD, availability calculation, inventory reservation |

### Component Inventory Reservation Flow
When a bundle is sold, `BundleService` reserves inventory for each component product individually. Each component's `available_quantity` is decremented by `(bundle_qty_sold * component_qty_per_bundle)`. The `PushInventoryToMarketplaces` job fires for every affected component product.

### Auto-Recalculate Bundle Availability
Bundle availability is the minimum of `floor(component.available_quantity / component.qty_per_bundle)` across all components. When any component's stock changes (sale, adjustment, transfer), the bundle's availability is recalculated and pushed to marketplaces.

---

## Debugging Commands

```php
// Inventory levels per location
InventoryLevel::where('organization_id', $orgId)
  ->with('location:id,name', 'product:id,name,sku')
  ->where('quantity_on_hand', '>', 0)
  ->get(['product_id', 'location_id', 'quantity_on_hand', 'quantity_reserved']);

// Low stock alerts
InventoryLevel::where('organization_id', $orgId)
  ->whereColumn('quantity_on_hand', '<=', 'reorder_point')
  ->where('reorder_point', '>', 0)
  ->with('product:id,name,sku', 'location:id,name')->get();

// Recent movements
InventoryMovement::where('organization_id', $orgId)
  ->orderByDesc('created_at')->take(20)
  ->get(['product_id', 'location_id', 'type', 'quantity', 'balance_before', 'balance_after', 'created_at']);

// Total inventory valuation
InventoryLevel::where('organization_id', $orgId)
  ->selectRaw("SUM(quantity_on_hand * average_cost) as total_value, SUM(quantity_on_hand) as total_qty")
  ->first();

// Movement summary by type (last 30 days)
InventoryMovement::where('organization_id', $orgId)
  ->where('created_at', '>', now()->subDays(30))
  ->selectRaw("type, count(*) as count, sum(quantity) as total_qty")
  ->groupBy('type')->get();

// Products with quantity mismatch (available_quantity vs sum of levels)
DB::select("
  SELECT p.id, p.name, p.available_quantity,
    COALESCE(SUM(il.quantity_on_hand - il.quantity_reserved), 0) as level_available
  FROM products p
  LEFT JOIN inventory_levels il ON il.product_id = p.id
  WHERE p.organization_id = ? AND p.status = 'active'
  GROUP BY p.id
  HAVING p.available_quantity != COALESCE(SUM(il.quantity_on_hand - il.quantity_reserved), 0)
", [$orgId]);
```

## Notifications

Inventory events that trigger user notifications:

| Event | Notification Class | Wired In |
|-------|--------------------|----------|
| Low stock / out of stock | `Inventory\LowStockNotification` | `LowStockAlert` event -> `NotifyStockChange` listener |
| Bulk operation completed | `Inventory\BulkOperationCompletedNotification` | `BulkOperationCompleted` event -> `SendBulkOperationNotification` listener |
| Marketplace inventory sync failed | `Inventory\MarketplaceInventorySyncFailedNotification` | `PushInventoryToMarketplaces::failed()` |

The `BulkOperationCompletedNotification` covers: stock adjustments, category updates, product updates, location transfers, product imports, and product exports. See `docs/NOTIFICATION_SYSTEM.md` for the full notification architecture.

## Audit Logging

| Action | Category | Severity | Metadata keys |
|--------|----------|----------|---------------|
| `inventory.adjusted` | inventory | info | product_id, delta, reason, location_id |
| `inventory.moved` | inventory | info | from_location_id, to_location_id, quantity |
| `inventory.transferred` | inventory | info | from_bin_id, to_bin_id, quantity |
| `inventory.batch_received` | inventory | info | batch_id, item_count, cost_per_item |
| `inventory.bin_assigned` | inventory | info | product_id, bin_id |
| `inventory.cycle_count_logged` | inventory | info | location_id, variance |

Trait: models in this feature use `LogsAudit` (`app/Traits/LogsAudit.php`) for auto CRUD diff. Domain lifecycle events emitted from `app/Services/Inventory/{BatchService,WarehouseLayoutService,InventoryAdjustmentService}`; `InventoryLevel`, `InventoryMovement`, `InventoryBatch` models (`LogsAudit`) via `UserActivityLog::log(action, desc, $user, $metadata, $severity, $category)`.

KPI consumer: `UserProductivityDaily.inventory_moves_count` (from `inventory.adjusted|moved|transferred`). `InventoryMovement` rows remain the source of truth for stock math; audit captures the actor + intent.

Spec: `docs/AUDIT_SYSTEM.md`. Enforced rule: `.claude/rules/audit-logging.md`. Tests: `use Tests\Support\AssertsAudits;` then `$this->assertAudited('action', $user, [...])`.
