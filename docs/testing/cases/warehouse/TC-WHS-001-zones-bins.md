# Warehouse Zones and Bins Test Cases

## Document ID: TC-WHS-001

**Domain:** Warehouse
**Related Test Plan:** `docs/testing/plans/warehouse-test-plan.md`
**Last Updated:** 2026-04-15

---

### TC-WHS-001-01: List zones by location

| Field | Value |
|-------|-------|
| **ID** | TC-WHS-001-01 |
| **Priority** | P0 |
| **Type** | Feature |
| **Platform** | Web |
| **Automated** | No |

**Preconditions:**
1. Authenticated user with `admin.access` permission (warehouse routes protected by `can:admin.access` middleware)
2. Organization has at least 2 InventoryLocations (e.g., "Main Warehouse" and "Overflow Storage")
3. "Main Warehouse" has 3 WarehouseZone records with associated bins
4. "Overflow Storage" has 1 WarehouseZone

**Test Steps:**

| Step | Action | Expected Result |
|------|--------|-----------------|
| 1 | Send `GET` to `route('inventory.warehouse.zones.index')` | Response status 200, Inertia renders `Inventory/Warehouse/Zones` |
| 2 | Assert `locations` prop contains all org locations with `id` and `name` | Location dropdown data |
| 3 | Assert `selectedLocationId` defaults to first location's ID | Auto-selects first location |
| 4 | Assert `zones` prop contains zones for the selected location | 3 zones for "Main Warehouse" |
| 5 | Assert each zone has `bins_count` and `active_bins_count` via `withCount` | Bin statistics per zone |
| 6 | Send `GET` with `?location_id={overflowId}` | Zones filtered to Overflow Storage (1 zone) |
| 7 | Assert `stats` prop has `total_zones`, `total_bins`, `active_bins` | Aggregate statistics for selected location |
| 8 | Assert `zoneTypes` prop contains the 5 zone types: receiving, storage, picking, shipping, returns | Zone type options for create form |

**Notes:**
- Route: `GET /inventory/warehouse/zones` named `inventory.warehouse.zones.index`
- Protected by `can:admin.access` middleware in route group
- Uses `WarehouseZone::withoutGlobalScopes()->where('organization_id', ...)` for explicit org filtering
- Zones ordered by `sort_order` for display
- `stats.total_bins` = `$zones->sum('bins_count')`, `stats.active_bins` = `$zones->sum('active_bins_count')`

---

### TC-WHS-001-02: Create zone with type

| Field | Value |
|-------|-------|
| **ID** | TC-WHS-001-02 |
| **Priority** | P0 |
| **Type** | Feature |
| **Platform** | Web |
| **Automated** | No |

**Preconditions:**
1. Authenticated user with `admin.access` permission
2. InventoryLocation exists in the organization
3. BinManagementService injected into ZoneController

**Test Steps:**

| Step | Action | Expected Result |
|------|--------|-----------------|
| 1 | Send `POST` to `route('inventory.warehouse.zones.store')` with `location_id`, `name = 'Zone A'`, `code = 'ZA'`, `type = 'storage'` | Redirect back with flash `'Zone created.'` |
| 2 | Assert WarehouseZone created with correct `organization_id`, `location_id`, `name`, `code`, `type` | Zone persisted in database |
| 3 | Assert `sort_order` set to provided value or null | Sort order for display ordering |
| 4 | Validate: `type` must be one of `receiving`, `storage`, `picking`, `shipping`, `returns` | Validation error on invalid type |
| 5 | Validate: `code` required, max 10 characters | Code length enforced |
| 6 | Validate: `location_id` must be a valid UUID that exists in `inventory_locations` | Foreign key validated |

**Notes:**
- `BinManagementService::createZone()` handles zone creation with the validated data and organization ID
- Zone code is a short identifier (max 10 chars) used in bin barcode generation
- Five zone types represent different warehouse workflow areas
- Validation: `location_id` required UUID exists:inventory_locations, `name` required max:255, `code` required max:10, `type` required enum

---

### TC-WHS-001-03: Generate bins for zone (aisles x racks x shelves creates correct count)

| Field | Value |
|-------|-------|
| **ID** | TC-WHS-001-03 |
| **Priority** | P0 |
| **Type** | Feature |
| **Platform** | Web |
| **Automated** | No |

**Preconditions:**
1. Authenticated user with `admin.access` permission
2. WarehouseZone exists in the organization

**Test Steps:**

| Step | Action | Expected Result |
|------|--------|-----------------|
| 1 | Send `POST` to `route('inventory.warehouse.zones.generate-bins', $zone)` with `aisles = 3`, `racks = 4`, `shelves = 5` | Redirect back with flash `'60 bins generated.'` |
| 2 | Assert 60 WarehouseBin records created (3 * 4 * 5 = 60) | Correct bin count |
| 3 | Assert each bin has `zone_id` linking to the zone | All bins belong to the zone |
| 4 | Assert each bin has `aisle`, `rack`, `shelf` values matching the grid | e.g., aisle=A, rack=01, shelf=01 |
| 5 | Assert each bin has a unique `code` within the zone | Code derived from aisle/rack/shelf combination |
| 6 | Assert each bin has `is_active = true` by default | Bins are active on generation |
| 7 | Validate: `aisles` must be integer min:1 max:50 | Upper bound prevents excessive bin generation |
| 8 | Validate: `shelves` must be integer min:1 max:20 | Shelf limit enforced |

**Notes:**
- Route: `POST /inventory/warehouse/zones/{zone}/generate-bins` named `inventory.warehouse.zones.generate-bins`
- `BinManagementService::generateBinsForZone($zone, $aisles, $racks, $shelves)` handles bulk creation
- Returns the count of bins created as part of the flash message
- Max limits: aisles and racks max 50, shelves max 20 (theoretical max: 50*50*20 = 50,000 bins)
- Bin codes typically follow a format like `ZA-A01-R01-S01` (zone code + aisle + rack + shelf)

---

### TC-WHS-001-04: List bins with zone filter and search

| Field | Value |
|-------|-------|
| **ID** | TC-WHS-001-04 |
| **Priority** | P1 |
| **Type** | Feature |
| **Platform** | Web |
| **Automated** | No |

**Preconditions:**
1. Authenticated user with `admin.access` permission
2. Organization has multiple zones with generated bins
3. Some bins have barcode values

**Test Steps:**

| Step | Action | Expected Result |
|------|--------|-----------------|
| 1 | Send `GET` to `route('inventory.warehouse.bins.index')` | Response status 200, Inertia renders `Inventory/Warehouse/Bins` |
| 2 | Assert `bins` prop is paginated at 50 per page | Higher pagination for bin lists |
| 3 | Assert each bin has `zone` relationship loaded with `id, name, code, type` | Zone context for each bin |
| 4 | Send `GET` with `?zone_id={zoneId}` | Only bins in the specified zone returned |
| 5 | Send `GET` with `?search=A01` | Bins matching code or barcode via ILIKE returned |
| 6 | Assert `zones` prop contains zones for the selected location | Zone filter dropdown data |
| 7 | Assert `locations` prop contains all org locations | Location selector data |
| 8 | Assert `filters` prop echoes back `search`, `zone_id`, `location_id` | Filter state preserved |

**Notes:**
- Route: `GET /inventory/warehouse/bins` named `inventory.warehouse.bins.index`
- Bins paginated at 50 (higher than standard 20) due to typical warehouse bin volume
- Search applies ILIKE to both `code` and `barcode` fields
- Location filter scopes bins through their zone's `location_id`
- Bins ordered by `code` ascending for consistent sort order

---

### TC-WHS-001-05: Create individual bin with barcode

| Field | Value |
|-------|-------|
| **ID** | TC-WHS-001-05 |
| **Priority** | P1 |
| **Type** | Feature |
| **Platform** | Web |
| **Automated** | No |

**Preconditions:**
1. Authenticated user with `admin.access` permission
2. WarehouseZone exists
3. BinManagementService available

**Test Steps:**

| Step | Action | Expected Result |
|------|--------|-----------------|
| 1 | Send `POST` to `route('inventory.warehouse.bins.store')` with `zone_id`, `code = 'CUSTOM-01'`, `label = 'Custom Bin'`, `aisle = 'X'`, `rack = '99'`, `shelf = '1'` | Redirect back with flash `'Bin created.'` |
| 2 | Assert WarehouseBin created with correct `zone_id`, `code`, `label` | Bin persisted |
| 3 | Assert `aisle`, `rack`, `shelf`, `bin_position` values stored | Physical location attributes saved |
| 4 | Assert `barcode` auto-generated by BinManagementService | Barcode value populated |
| 5 | Assert `max_capacity` stored if provided | Capacity limit for bin |
| 6 | Validate: `zone_id` required UUID exists:warehouse_zones | Zone must exist |
| 7 | Validate: `code` required max:50 | Bin code length enforced |

**Notes:**
- Route: `POST /inventory/warehouse/bins` named `inventory.warehouse.bins.store`
- `BinManagementService::createBin()` handles creation and barcode generation
- Individual bin creation is used for special-purpose bins (e.g., oversized items, hazmat)
- `max_capacity` is nullable integer min:1 for optional capacity tracking
- Barcode generation may combine zone code + bin code into a scannable format

---

### TC-WHS-001-06: Delete zone with no bins

| Field | Value |
|-------|-------|
| **ID** | TC-WHS-001-06 |
| **Priority** | P1 |
| **Type** | Feature |
| **Platform** | Web |
| **Automated** | No |

**Preconditions:**
1. Authenticated user with `admin.access` permission
2. WarehouseZone exists with `bins_count = 0` (no bins assigned)

**Test Steps:**

| Step | Action | Expected Result |
|------|--------|-----------------|
| 1 | Verify `$zone->bins()->exists()` returns `false` | Zone has no bins |
| 2 | Send `DELETE` to `route('inventory.warehouse.zones.destroy', $zone)` | Redirect back with flash `'Zone deleted.'` |
| 3 | Assert WarehouseZone record deleted from database | Zone no longer exists |
| 4 | Assert location's zone list no longer includes the deleted zone | Zone removed from index |

**Notes:**
- Route: `DELETE /inventory/warehouse/zones/{zone}` named `inventory.warehouse.zones.destroy`
- The controller checks `$zone->bins()->exists()` before allowing deletion
- This is a hard delete (no soft delete on zones)
- Zone deletion does not affect the parent InventoryLocation

---

### TC-WHS-001-07: Delete zone with bins fails

| Field | Value |
|-------|-------|
| **ID** | TC-WHS-001-07 |
| **Priority** | P0 |
| **Type** | Feature |
| **Platform** | Web |
| **Automated** | No |

**Preconditions:**
1. Authenticated user with `admin.access` permission
2. WarehouseZone exists with at least 1 bin assigned

**Test Steps:**

| Step | Action | Expected Result |
|------|--------|-----------------|
| 1 | Verify `$zone->bins()->exists()` returns `true` | Zone has bins |
| 2 | Send `DELETE` to `route('inventory.warehouse.zones.destroy', $zone)` | Redirect back with error flash: `'Cannot delete a zone that has bins. Remove bins first.'` |
| 3 | Assert WarehouseZone record still exists in database | Zone NOT deleted |
| 4 | Assert all bins in the zone still exist | Bins preserved |
| 5 | Remove all bins from the zone manually | Bins deleted one by one |
| 6 | Retry delete zone | Now succeeds with `'Zone deleted.'` flash |

**Notes:**
- This is a safety guard to prevent orphaning bins or losing bin-level inventory data
- The check uses `$zone->bins()->exists()` which is a single query (efficient)
- Users must remove all bins before the zone can be deleted
- Similarly, `destroyBin()` checks `$bin->inventoryLevels()->exists()` before allowing bin deletion (bins with inventory cannot be deleted)

---

### TC-WHS-001-08: Bin barcode auto-generated

| Field | Value |
|-------|-------|
| **ID** | TC-WHS-001-08 |
| **Priority** | P1 |
| **Type** | Feature |
| **Platform** | Web |
| **Automated** | No |

**Preconditions:**
1. Authenticated user with `admin.access` permission
2. WarehouseZone exists with code `'ST'`
3. BinManagementService handles barcode generation

**Test Steps:**

| Step | Action | Expected Result |
|------|--------|-----------------|
| 1 | Create a bin via `storeBin` with `code = 'A01-R02-S03'` in zone `ST` | Bin created |
| 2 | Assert `barcode` field is populated (non-null) | Barcode auto-generated |
| 3 | Assert barcode incorporates zone code and bin code | e.g., `ST-A01-R02-S03` or similar format |
| 4 | Assert barcode is unique across all bins in the organization | No duplicate barcodes |
| 5 | Generate bins via `generateBins` (3 aisles x 2 racks x 2 shelves = 12 bins) | All 12 bins have unique barcodes |
| 6 | Assert each generated bin's barcode follows a consistent pattern | Predictable format for label printing |

**Notes:**
- `BinManagementService::createBin()` generates the barcode during creation
- Barcodes are used by the mobile app for bin scanning during picking workflows
- The barcode format should be scannable by standard barcode readers
- Both individual bin creation (`storeBin`) and bulk generation (`generateBins`) should produce barcodes
- Barcode uniqueness is critical for accurate warehouse operations
