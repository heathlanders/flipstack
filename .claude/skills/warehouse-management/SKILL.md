---
name: warehouse-management
description: Warehouse zone and bin layout management, bulk bin generation, barcode lookup, pick path organization, and mobile integration
user-invocable: false
---

# Warehouse Management System

## Key Files

| Area | File | Purpose |
|------|------|---------|
| **Models** | `app/Models/Inventory/WarehouseZone.php` | Zone with type, sort order, location relationship |
| | `app/Models/Inventory/WarehouseBin.php` | Bin with aisle/rack/shelf coordinates, capacity tracking, barcode |
| | `app/Models/Inventory/InventoryLevel.php` | Links products to bins via `bin_id` foreign key |
| | `app/Models/Catalog/ProductIntakeQueue.php` | Captures `quick_location_id` + `quick_bin_id` at intake; flows to InventoryLevel on publish |
| **Services** | `app/Services/Inventory/BinManagementService.php` | Zone/bin CRUD, bulk bin generation, barcode generation, lookup |
| **Controllers** | `app/Http/Controllers/Inventory/ZoneController.php` | Zone CRUD, bin CRUD, bulk generation, bin browser |
| **Routes** | `routes/inventory.php` | Zone and bin routes under `inventory.warehouse.` prefix |

## Zone Types

| Constant | Value | Purpose |
|----------|-------|---------|
| `TYPE_RECEIVING` | `receiving` | Inbound goods staging area |
| `TYPE_STORAGE` | `storage` | Long-term storage |
| `TYPE_PICKING` | `picking` | Active pick locations |
| `TYPE_SHIPPING` | `shipping` | Packed orders awaiting shipment |
| `TYPE_RETURNS` | `returns` | RMA processing area |

## Zone Model

```
WarehouseZone {
  organization_id: uuid
  location_id: uuid      // FK to InventoryLocation
  name: string
  code: string            // short code (max 10 chars), stored uppercase
  type: string            // one of 5 zone types
  sort_order: integer     // controls display/pick path order
  is_active: boolean
}
```

Scopes: `active()`, `forLocation($locationId)`.

## Bin Model

```
WarehouseBin {
  zone_id: uuid           // FK to WarehouseZone
  code: string            // e.g., "01-03-02"
  label: string?          // human-friendly label
  aisle: string?          // e.g., "01"
  rack: string?           // e.g., "03"
  shelf: string?          // e.g., "02"
  bin_position: string?   // additional granularity
  max_capacity: integer?  // null = unlimited
  current_count: integer  // items currently stored
  is_active: boolean
  barcode: string         // scannable barcode, auto-generated
}
```

Computed: `fullCode` = `zone.code + "-" + bin.code` (e.g., `STG-01-03-02`).

Scopes: `active()`, `byBarcode($barcode)`.

Method: `isAtCapacity()` returns true when `max_capacity` is set and `current_count >= max_capacity`.

## Bin Code Format

Generated bins use format `{aisle:02d}-{rack:02d}-{shelf:02d}`, e.g., `01-03-02`.

Barcodes are auto-generated as `{ZONE_CODE}-{BIN_CODE}`, e.g., `STG-01-03-02`.

## Bulk Bin Generation

`BinManagementService::generateBinsForZone($zone, $aisles, $racks, $shelves)`:

- Creates a bin for every combination of aisle x rack x shelf
- Total bins = aisles * racks * shelves
- Limits: up to 50 aisles, 50 racks, 20 shelves (max 50,000 bins per call)
- Each bin gets auto-generated code and barcode

## Pick Path Optimization

Zones have `sort_order` controlling pick path sequence. Typical flow:
1. Storage zones (sort_order: 1-10)
2. Picking zones (sort_order: 11-20)
3. Shipping zones (sort_order: 21-30)

Within zones, bins are ordered by code (aisle -> rack -> shelf), creating an efficient serpentine pick path.

## Mobile Integration

The mobile app uses barcode scanning to:
- Look up bins: `BinManagementService::findByBarcode($barcode)` returns WarehouseBin
- Verify pick locations: scan bin barcode during pick phase to confirm correct location
- Receive to bin: scan bin barcode during PO receiving to assign inventory to specific bins

Mobile API endpoints for warehouse operations are in `routes/api.php` under the mobile API controllers.

## Routes

All routes require authentication under prefix `inventory/warehouse`:

| Method | URI | Route Name | Action |
|--------|-----|------------|--------|
| GET | `/inventory/warehouse/zones` | `inventory.warehouse.zones.index` | Zone list with location filter |
| POST | `/inventory/warehouse/zones` | `inventory.warehouse.zones.store` | Create zone |
| PUT | `/inventory/warehouse/zones/{zone}` | `inventory.warehouse.zones.update` | Update zone |
| DELETE | `/inventory/warehouse/zones/{zone}` | `inventory.warehouse.zones.destroy` | Delete zone (must have no bins) |
| POST | `/inventory/warehouse/zones/{zone}/generate-bins` | `inventory.warehouse.zones.generate-bins` | Bulk generate bins |
| GET | `/inventory/warehouse/bins` | `inventory.warehouse.bins.index` | Bin browser with search, zone filter |
| POST | `/inventory/warehouse/bins` | `inventory.warehouse.bins.store` | Create single bin |
| PUT | `/inventory/warehouse/bins/{bin}` | `inventory.warehouse.bins.update` | Update bin |
| DELETE | `/inventory/warehouse/bins/{bin}` | `inventory.warehouse.bins.destroy` | Delete bin (must have no inventory) |

## Zone Index Page

Renders zones for the selected location with stats:
- `total_zones` -- zone count
- `total_bins` -- total bin count across all zones
- `active_bins` -- active bin count
- Per zone: `bins_count`, `active_bins_count`

Zone types are provided as a dropdown map for the UI.

## Debugging Commands

```php
// Zone/bin counts per location
WarehouseZone::where('organization_id', $orgId)
  ->withCount('bins')
  ->get(['location_id', 'name', 'code', 'type', 'bins_count']);

// Bins at capacity
WarehouseBin::whereNotNull('max_capacity')
  ->whereRaw('current_count >= max_capacity')
  ->with('zone:id,name,code')
  ->get(['id', 'code', 'max_capacity', 'current_count']);

// Find bin by barcode
BinManagementService::findByBarcode('STG-01-03-02');

// Inventory in a specific bin
InventoryLevel::where('bin_id', $binId)
  ->with('product:id,name,sku')
  ->get(['product_id', 'quantity_on_hand', 'quantity_reserved']);

// Empty bins (no inventory assigned)
WarehouseBin::where('is_active', true)
  ->whereDoesntHave('inventoryLevels')
  ->count();
```

## Audit Logging

| Action | Category | Severity | Metadata keys |
|--------|----------|----------|---------------|
| `warehouse_zone.created` | inventory | info | zone_code |
| `warehouse_zone.updated` | inventory | info | changes |
| `warehouse_zone.deleted` | inventory | warning | |
| `warehouse_bin.created` | inventory | info | bin_code, zone_id |
| `warehouse_bin.updated` | inventory | info | changes |
| `warehouse_bin.deleted` | inventory | warning | |
| `warehouse_bin.bulk_generated` | inventory | info | count, pattern, zone_id |
| `warehouse_bin.barcode_scanned` | inventory | debug | source: mobile, bin_id |

Trait: models in this feature use `LogsAudit` (`app/Traits/LogsAudit.php`) for auto CRUD diff. Domain lifecycle events emitted from `app/Services/Inventory/WarehouseLayoutService`; `WarehouseZone` + `WarehouseBin` models (`LogsAudit`) via `UserActivityLog::log(action, desc, $user, $metadata, $severity, $category)`.

Barcode scans use `severity: 'debug'` -- high volume from mobile.

Spec: `docs/AUDIT_SYSTEM.md`. Enforced rule: `.claude/rules/audit-logging.md`. Tests: `use Tests\Support\AssertsAudits;` then `$this->assertAudited('action', $user, [...])`.
