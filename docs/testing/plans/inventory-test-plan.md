# Inventory Test Plan

## Document ID: FLIPSTACK-TP-INV-001

**Version:** 1.0
**Last Updated:** 2026-04-15
**Status:** Approved

---

## 1. Overview

### 1.1 Scope

Product lifecycle, category hierarchy, inventory locations, stock operations (adjust/transfer/reserve), batch/lot tracking, and marketplace inventory sync. Excludes warehouse zones/bins (separate plan), part-out (separate plan), and bundles (separate plan).

### 1.2 Related Documentation

| Resource | Path |
|----------|------|
| Feature docs | `docs/PRODUCT_MANAGEMENT_SUITE.md`, `docs/PRODUCT_MANAGEMENT_UI.md`, `docs/INVENTORY_SYNC_SYSTEM.md` |
| Existing tests | `tests/Feature/Inventory/` (9 files) |
| Test cases | `docs/testing/cases/inventory/TC-INV-001 through TC-INV-005` |

---

## 2. Features Under Test

| # | Feature | Controller | Route Prefix | Priority |
|---|---------|------------|-------------|----------|
| 1 | Product CRUD (create/show/edit/delete) | ProductController | `inventory.products` | P0 |
| 2 | Product search & filtering | ProductController@index,searchSuggestions | `inventory.products` | P0 |
| 3 | Product soft delete / restore / force delete | ProductController@destroy,restore,forceDestroy | `inventory.products` | P1 |
| 4 | Product duplication | ProductController@duplicate | `inventory.products` | P2 |
| 5 | Bulk operations | ProductController@bulkAction | `inventory.products` | P1 |
| 6 | Product export | ProductController@export | `inventory.products` | P2 |
| 7 | Category CRUD & hierarchy | CategoryController | `inventory.categories` | P1 |
| 8 | Category tree (nested set) | CategoryController@move | `inventory.categories` | P1 |
| 9 | Location CRUD | LocationController | `inventory.locations` | P1 |
| 10 | Stock adjustments | InventoryMovementController, ProductController@adjustStock | `inventory.movements` | P0 |
| 11 | Stock transfers | InventoryController@transferStock | `inventory.operations` | P0 |
| 12 | Stock reservations | InventoryController@reserveStock | `inventory.operations` | P0 |
| 13 | Batch/lot tracking | BatchController | `inventory.batches` | P1 |
| 14 | Inventory levels & low stock alerts | InventoryController | `api.inventory` | P1 |
| 15 | Cross-marketplace inventory sync | MarketplaceInventorySync | - (service/job) | P0 |

---

## 3. Test Strategy

### 3.1 Unit Tests

| Unit | Test File | Status |
|------|-----------|--------|
| WarehouseAutoBuilder | `tests/Unit/Services/Inventory/WarehouseAutoBuilderTest.php` | Exists |
| ProductService | `tests/Unit/Services/ProductServiceTest.php` | Needed |
| InventoryService | `tests/Unit/Services/InventoryServiceTest.php` | Needed |
| BatchTrackingService | `tests/Unit/Services/BatchTrackingServiceTest.php` | Needed |
| CategoryService | `tests/Unit/Services/CategoryServiceTest.php` | Needed |
| ProductDeletionService | `tests/Unit/Services/ProductDeletionServiceTest.php` | Needed |

### 3.2 Feature Tests

| Workflow | Test File | Status |
|----------|-----------|--------|
| Product CRUD | `tests/Feature/Inventory/ProductManagementTest.php` | Exists (partial — index/create form only) |
| Categories | `tests/Feature/Inventory/CategoryManagementTest.php` | Exists (model relationships only) |
| Stock operations | `tests/Feature/Inventory/InventoryOperationsTest.php` | Exists (model scopes only) |
| Suppliers | `tests/Feature/Inventory/SupplierManagementTest.php` | Exists |
| Product deletion | `tests/Feature/Inventory/ProductDeletionTest.php` | Exists |
| Batch tracking | `tests/Feature/Inventory/BatchTrackingTest.php` | Exists (partial) |
| Warehouse layout | `tests/Feature/Inventory/WarehouseLayoutTest.php` | Exists |
| Bundles | `tests/Feature/Inventory/BundleTest.php` | Exists |
| Marketplace sync | `tests/Feature/Inventory/MarketplaceInventorySyncTest.php` | Exists |
| **Product store/update endpoints** | - | **Needed** |
| **Category CRUD endpoints** | - | **Needed** |
| **Location CRUD endpoints** | - | **Needed** |
| **Stock adjustment endpoints** | - | **Needed** |
| **Stock transfer flow** | - | **Needed** |
| **Batch item tracking** | - | **Needed** |
| **Bulk operations** | - | **Needed** |
| **Export** | - | **Needed** |

---

## 4. Test Data Setup

| Data | Source | Notes |
|------|--------|-------|
| Organization + User | `CreatesOrganizationFixtures` trait | Admin + viewer roles |
| Products | Product factory | With SKU, barcode, stock levels |
| Categories | Category factory | Parent-child hierarchy, nested set values |
| Locations | InventoryLocation factory | Warehouse and store types |
| Inventory Levels | InventoryLevel factory | Per product-location combo |
| Batches | InventoryBatch factory | Open and closed statuses |
| Movements | InventoryMovement factory | Various types |

---

## 5. Test Cases

| Test Case ID | Title | File |
|--------------|-------|------|
| TC-INV-001 | Product CRUD | `cases/inventory/TC-INV-001-product-crud.md` |
| TC-INV-002 | Category Management | `cases/inventory/TC-INV-002-category-management.md` |
| TC-INV-003 | Location Management | `cases/inventory/TC-INV-003-location-management.md` |
| TC-INV-004 | Stock Operations | `cases/inventory/TC-INV-004-stock-operations.md` |
| TC-INV-005 | Batch Tracking | `cases/inventory/TC-INV-005-batch-tracking.md` |

---

## 6. Coverage Goals

| Metric | Current | Target |
|--------|---------|--------|
| Product CRUD endpoints | Index + create form only | Full CRUD + duplicate + bulk |
| Category endpoints | Model tests only | Full CRUD + move + tree |
| Location endpoints | Model tests only | Full CRUD + analytics |
| Stock operations | Model scopes only | Adjust + transfer + reserve endpoints |
| Batch tracking | Index + create | Add items + close + search |
| Organization isolation | Not tested | Every endpoint verified |

---

## 7. Known Issues and Risks

| Risk | Impact | Mitigation |
|------|--------|------------|
| Product uses UUID PKs (HasUuids trait) | String IDs in routes, not integers | Use `Product::factory()->create()->id` for route params |
| Category nested set (lft/rgt/depth) | Tree corruption on concurrent writes | Test tree integrity after insert/update/delete/move |
| Product soft delete with marketplace listings | Archived product still listed on eBay/Shopify | Test that deletion triggers listing end/archive |
| available_quantity as single source of truth | Over-selling if not decremented atomically | Test concurrent reservation scenarios |
