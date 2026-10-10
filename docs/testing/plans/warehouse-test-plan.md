# Warehouse Test Plan
## Document ID: FLIPSTACK-TP-WHS-001
**Version:** 1.0 | **Last Updated:** 2026-04-15 | **Status:** Approved

## Scope
Verify warehouse zone CRUD, bin management within zones, bulk bin generation, and zone type validation (receiving, storage, picking, shipping, returns).

## Related Docs
| Resource | Path |
|----------|------|
| Feature docs | `docs/WAREHOUSE_LAYOUT.md` |
| Existing tests | `tests/Feature/WarehouseLayoutTest.php` |

## Features Under Test
| # | Feature | Controller@method | Route Name | Priority |
|---|---------|-------------------|------------|----------|
| 1 | Zone listing | ZoneController@index | inventory.warehouse.zones.index | High |
| 2 | Zone store | ZoneController@store | inventory.warehouse.zones.store | High |
| 3 | Zone show | ZoneController@show | inventory.warehouse.zones.show | Medium |
| 4 | Zone update | ZoneController@update | inventory.warehouse.zones.update | Medium |
| 5 | Zone delete | ZoneController@destroy | inventory.warehouse.zones.destroy | Low |
| 6 | Bin store | ZoneController@storeBin | inventory.warehouse.bins.store | High |
| 7 | Bin update | ZoneController@updateBin | inventory.warehouse.bins.update | Medium |
| 8 | Bin delete | ZoneController@destroyBin | inventory.warehouse.bins.destroy | Low |
| 9 | Bulk bin generation | ZoneController@bulkGenerateBins | inventory.warehouse.bins.bulk | Medium |
| 10 | Barcode lookup | ZoneController@barcodeLookup | inventory.warehouse.barcode | Medium |

## Test Cases
| ID | Title | File |
|----|-------|------|
| TC-WHS-001 | Zone CRUD, bin management, bulk generation, and zone type validation | `tests/Feature/Warehouse/WarehouseLayoutFlowTest.php` |

## Coverage Goals
| Metric | Current | Target |
|--------|---------|--------|
| Feature test coverage | Partial (WarehouseLayoutTest.php) | 90% route coverage |
| WarehouseZone model coverage | Medium | 85% |
| WarehouseBin model coverage | Medium | 85% |
| Zone type validation coverage | Low | 100% |
