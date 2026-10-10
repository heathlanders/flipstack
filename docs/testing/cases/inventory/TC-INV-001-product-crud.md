# Product CRUD Test Cases

## Document ID: TC-INV-001

**Domain:** Inventory
**Related Test Plan:** `docs/testing/plans/inventory-test-plan.md`
**Last Updated:** 2026-04-15

---

### TC-INV-001-01: View products index with pagination, filters, and search

| Field | Value |
|-------|-------|
| **ID** | TC-INV-001-01 |
| **Priority** | P0 |
| **Type** | Feature |
| **Platform** | Web |
| **Automated** | Partial |
| **Test File** | `tests/Feature/Inventory/ProductManagementTest.php` |
| **Test Method** | `test_index_page_displays_products()` (exists, covers basic index) |

**Preconditions:**

1. Organization exists via `CreatesOrganizationFixtures` trait (`setUpOrganizationFixtures()` creates org with `sku_prefix = 'TEST'`, team, user with admin role, onboarding progress)
2. User is authenticated and has `inventory.view` permission
3. Multiple products exist in the organization across various statuses (`active`, `draft`, `archived`), categories, brands, and stock levels
4. At least one Category and one Brand exist in the organization
5. `RefreshDatabase` trait resets state between runs

**Test Steps:**

| Step | Action | Expected Result |
|------|--------|-----------------|
| 1 | Send `GET` request to `route('inventory.products.index')` as authenticated user | Response status 200, Inertia renders `Inventory/Products/Index` |
| 2 | Assert response contains `products` prop with paginated collection | Products paginator present with `data`, `current_page`, `per_page` keys |
| 3 | Create 25 products, request page 1 with `per_page=20` | First page returns 20 products, `last_page` is 2 |
| 4 | Send `GET` request with `?status=active` filter | Only products with `status = 'active'` are returned |
| 5 | Send `GET` request with `?status=draft` filter | Only products with `status = 'draft'` are returned |
| 6 | Send `GET` request with `?status=archived` filter | Only soft-deleted (archived) products are returned via `onlyTrashed()` |
| 7 | Send `GET` request with `?category_id={category_id}` filter | Only products in the specified category are returned |
| 8 | Send `GET` request with `?brand_id={brand_id}` filter | Only products with the specified brand are returned |
| 9 | Send `GET` request with `?stock_level=low_stock` filter | Only products where `stock_quantity <= low_stock_threshold` are returned |
| 10 | Send `GET` request with `?search=Widget` query parameter | Products matching the search term via PostgreSQL full-text search (`to_tsvector`) are returned |
| 11 | Assert response contains `filterOptions` with available categories, brands, and statuses | Filter dropdown data is populated from the organization's catalog |
| 12 | Assert response contains `stats` with product counts by status | Stats include `total`, `active`, `draft`, `archived` counts |

**Test Data:**

```php
// Via CreatesOrganizationFixtures
$this->setUpOrganizationFixtures();

$category = Category::factory()->create(['organization_id' => $this->organization->id]);
$brand = Brand::factory()->create(['organization_id' => $this->organization->id]);

$activeProduct = Product::factory()->create([
    'organization_id' => $this->organization->id,
    'category_id' => $category->id,
    'brand_id' => $brand->id,
    'name' => 'Vintage Widget',
    'sku' => 'TEST-VW-001',
    'status' => 'active',
    'stock_quantity' => 50,
    'low_stock_threshold' => 10,
]);

$draftProduct = Product::factory()->create([
    'organization_id' => $this->organization->id,
    'name' => 'Draft Product',
    'sku' => 'TEST-DP-002',
    'status' => 'draft',
]);

$archivedProduct = Product::factory()->create([
    'organization_id' => $this->organization->id,
    'name' => 'Archived Product',
    'sku' => 'TEST-AP-003',
    'status' => 'archived',
    'deleted_at' => now(),
]);
```

**Postconditions:**

- No database mutations; this is a read-only endpoint
- Response includes `savedSearches` for the authenticated user
- Pagination query string parameters are preserved via `withQueryString()`

**Notes:**

- The existing `ProductManagementTest::test_index_page_displays_products()` only verifies the page loads, not filtering or pagination behavior
- Archived products use `onlyTrashed()` scope, so the `status=archived` filter path differs from other status filters
- Search uses PostgreSQL `to_tsvector` full-text search, which requires the `pg_trgm` extension in test database
- The controller uses `ProductFilterRequest` form request for input validation
- Products are always scoped to the user's organization via `Product::forOrganization($organizationId)`

---

### TC-INV-001-02: Create product with required fields

| Field | Value |
|-------|-------|
| **ID** | TC-INV-001-02 |
| **Priority** | P0 |
| **Type** | Feature |
| **Platform** | Web |
| **Automated** | No |
| **Test File** | `tests/Feature/Inventory/ProductCrudTest.php` (needed) |
| **Test Method** | `test_store_product_with_required_fields()` (needed) |

**Preconditions:**

1. Organization fixtures set up via `CreatesOrganizationFixtures` trait
2. User is authenticated with `inventory.create` permission
3. At least one Category exists in the organization
4. No product with SKU `TEST-NEW-001` exists

**Test Steps:**

| Step | Action | Expected Result |
|------|--------|-----------------|
| 1 | Send `POST` request to `route('inventory.products.store')` with required fields: `name`, `sku`, `status`, `cost_price`, `selling_price`, `category_id` | Response redirects to product show or index page with success flash message |
| 2 | Assert product exists in database with correct `organization_id` | `Product::where('sku', 'TEST-NEW-001')->exists()` returns true |
| 3 | Assert product has a UUID primary key (string format) | `$product->id` matches UUID v4 pattern `/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/` |
| 4 | Assert `cost_price` and `selling_price` are stored as decimal:2 | Values match input to 2 decimal places |
| 5 | Assert `status` is set to the submitted value (`active` or `draft`) | Status field persisted correctly |
| 6 | Assert `category_id` links to the correct Category record | Foreign key relationship is valid |
| 7 | Assert `created_by` is set to the authenticated user's ID | Creator tracked for audit |

**Test Data:**

```php
$category = Category::factory()->create(['organization_id' => $this->organization->id]);

$productData = [
    'name' => 'Brand New Widget',
    'sku' => 'TEST-NEW-001',
    'status' => 'active',
    'cost_price' => 15.50,
    'selling_price' => 29.99,
    'category_id' => $category->id,
    'condition' => 'NEW',
    'description' => 'A brand new widget for testing.',
    'weight' => 1.500,
    'track_inventory' => true,
    'manage_stock' => true,
    'stock_quantity' => 25,
    'low_stock_threshold' => 5,
];
```

**Postconditions:**

- One new Product record exists in the `products` table with a UUID primary key
- `organization_id` matches the authenticated user's organization
- `slug` is auto-generated from the product name
- If `manage_stock` is true, `stock_quantity` and `low_stock_threshold` are persisted
- `created_by` references the authenticated user

**Notes:**

- Product uses the `HasUuids` trait, so the ID is a UUID string, not an auto-incrementing integer
- The `ProductService` handles creation logic; the controller delegates to it
- The SKU should be unique within the organization (enforced by validation or database constraint)
- The controller uses `ProductFilterRequest` or a dedicated `StoreProductRequest` for validation

---

### TC-INV-001-03: Create product fails validation on missing name and duplicate SKU

| Field | Value |
|-------|-------|
| **ID** | TC-INV-001-03 |
| **Priority** | P1 |
| **Type** | Feature |
| **Platform** | Web |
| **Automated** | No |
| **Test File** | `tests/Feature/Inventory/ProductCrudTest.php` (needed) |
| **Test Method** | `test_store_product_fails_validation_on_missing_name()`, `test_store_product_fails_validation_on_duplicate_sku()` (needed) |

**Preconditions:**

1. Organization fixtures set up via `CreatesOrganizationFixtures` trait
2. User is authenticated with `inventory.create` permission
3. For duplicate SKU test: an existing product with SKU `TEST-DUP-001` in the same organization

**Test Steps:**

| Step | Action | Expected Result |
|------|--------|-----------------|
| 1 | Send `POST` to `route('inventory.products.store')` with `name` field omitted | Response status 422 or redirect back with validation errors |
| 2 | Assert validation error on `name` field | Error message indicates name is required |
| 3 | Assert no new product was created in the database | `Product::count()` unchanged |
| 4 | Send `POST` with all required fields but `sku` set to an existing product's SKU within the same organization | Response status 422 or redirect back with validation errors |
| 5 | Assert validation error on `sku` field | Error message indicates SKU is already taken |
| 6 | Assert no new product was created | Database count unchanged |

**Test Data:**

```php
// Missing name
$invalidData = [
    // 'name' => null, // intentionally omitted
    'sku' => 'TEST-VALID-SKU',
    'status' => 'active',
    'cost_price' => 10.00,
    'selling_price' => 20.00,
];

// Duplicate SKU
$existingProduct = Product::factory()->create([
    'organization_id' => $this->organization->id,
    'sku' => 'TEST-DUP-001',
]);

$duplicateSkuData = [
    'name' => 'Another Widget',
    'sku' => 'TEST-DUP-001', // already exists in this org
    'status' => 'active',
    'cost_price' => 10.00,
    'selling_price' => 20.00,
];
```

**Postconditions:**

- No new Product records created on validation failure
- Session flash errors contain specific field error messages
- Original form input is flashed back for user correction (`withInput()`)

**Notes:**

- SKU uniqueness is scoped to the organization -- the same SKU in a different organization should be allowed
- The validation may be handled in a form request class or inline in the controller
- Numeric fields (`cost_price`, `selling_price`) should also reject non-numeric values

---

### TC-INV-001-04: View product detail page loads all tabs

| Field | Value |
|-------|-------|
| **ID** | TC-INV-001-04 |
| **Priority** | P0 |
| **Type** | Feature |
| **Platform** | Web |
| **Automated** | No |
| **Test File** | `tests/Feature/Inventory/ProductCrudTest.php` (needed) |
| **Test Method** | `test_show_product_loads_all_tabs()` (needed) |

**Preconditions:**

1. Organization fixtures set up via `CreatesOrganizationFixtures` trait
2. User is authenticated with `inventory.view` permission
3. A product exists in the organization with images, marketplace listings (eBay and/or Shopify), and inventory movements

**Test Steps:**

| Step | Action | Expected Result |
|------|--------|-----------------|
| 1 | Send `GET` request to `route('inventory.products.show', $product)` | Response status 200, Inertia renders `Inventory/Products/Show` |
| 2 | Assert response contains `product` prop with all loaded relationships | Product data includes `category`, `brand`, `images`, `creator` |
| 3 | Assert product overview data includes pricing fields | `cost_price`, `selling_price`, `msrp` present in response |
| 4 | Assert product images are loaded and ordered by `sort_order` | Images relationship eagerly loaded |
| 5 | Assert marketplace listing data is present (eBay listings, Shopify listings) | `ebayListings` and `shopifyListings` relationships loaded |
| 6 | Assert inventory movement history is available | Recent movements or a movements count is provided |
| 7 | Assert the product UUID is used in the route parameter | Route resolves the product by UUID, not integer ID |

**Test Data:**

```php
$product = Product::factory()->create([
    'organization_id' => $this->organization->id,
    'name' => 'Test Detail Product',
    'sku' => 'TEST-DTL-001',
    'status' => 'active',
    'cost_price' => 25.00,
    'selling_price' => 49.99,
    'msrp' => 59.99,
]);

// Product ID is a UUID, e.g., '550e8400-e29b-41d4-a716-446655440000'
// Route: /inventory/products/{product} where {product} is the UUID
```

**Postconditions:**

- No database mutations; read-only endpoint
- All tab data (overview, images, marketplace, activity) is available in the Inertia response props

**Notes:**

- Product uses `HasUuids` so the route model binding resolves by UUID string
- The show page is the "Product Detail Hub" serving as the central product information page
- Marketplace sync rules and cross-listing status may also be returned depending on implementation
- The `MarketplaceSyncRuleEngine` is injected into the `ProductController` constructor

---

### TC-INV-001-05: Update product fields persist correctly

| Field | Value |
|-------|-------|
| **ID** | TC-INV-001-05 |
| **Priority** | P0 |
| **Type** | Feature |
| **Platform** | Web |
| **Automated** | No |
| **Test File** | `tests/Feature/Inventory/ProductCrudTest.php` (needed) |
| **Test Method** | `test_update_product_persists_fields()` (needed) |

**Preconditions:**

1. Organization fixtures set up via `CreatesOrganizationFixtures` trait
2. User is authenticated with `inventory.update` permission
3. An existing product in the organization with known initial values

**Test Steps:**

| Step | Action | Expected Result |
|------|--------|-----------------|
| 1 | Send `PUT` request to `route('inventory.products.update', $product)` with updated `name`, `selling_price`, `description`, `status` | Response redirects with success flash message |
| 2 | Refresh product from database | `$product->refresh()` succeeds |
| 3 | Assert `name` equals the new value | Name field updated |
| 4 | Assert `selling_price` equals the new decimal value | Price updated as decimal:2 |
| 5 | Assert `description` equals the new text | Description updated |
| 6 | Assert `status` changed from `draft` to `active` | Status transition persisted |
| 7 | Assert `updated_at` timestamp is recent | Timestamp updated on save |
| 8 | Assert fields NOT included in the update request remain unchanged | `cost_price`, `sku`, `barcode` unchanged |

**Test Data:**

```php
$product = Product::factory()->create([
    'organization_id' => $this->organization->id,
    'name' => 'Original Name',
    'sku' => 'TEST-UPD-001',
    'status' => 'draft',
    'cost_price' => 10.00,
    'selling_price' => 19.99,
    'description' => 'Original description.',
]);

$updateData = [
    'name' => 'Updated Widget Name',
    'selling_price' => 34.99,
    'description' => 'Updated description with more details.',
    'status' => 'active',
    // cost_price intentionally omitted to verify it is unchanged
];
```

**Postconditions:**

- Product record updated in-place (same UUID primary key)
- `updated_at` timestamp refreshed
- No new Product record created (update, not insert)
- If `selling_price` changed and marketplace listings exist, a price sync job may be dispatched (depends on sync rules)

**Notes:**

- The `PUT` method is used for full update; ensure CSRF token is included in test
- The route uses UUID model binding: `PUT /inventory/products/{uuid}`
- Changing status from `draft` to `active` may trigger marketplace sync rules via `MarketplaceSyncRuleEngine`
- SKU changes may require re-evaluation of marketplace listing SKUs

---

### TC-INV-001-06: Soft delete (archive) product sets deleted_at and status to archived

| Field | Value |
|-------|-------|
| **ID** | TC-INV-001-06 |
| **Priority** | P1 |
| **Type** | Feature |
| **Platform** | Web |
| **Automated** | No |
| **Test File** | `tests/Feature/Inventory/ProductDeletionTest.php` (may partially exist) |
| **Test Method** | `test_destroy_product_soft_deletes_and_archives()` (needed) |

**Preconditions:**

1. Organization fixtures set up via `CreatesOrganizationFixtures` trait
2. User is authenticated with `inventory.delete` permission
3. An active product exists in the organization

**Test Steps:**

| Step | Action | Expected Result |
|------|--------|-----------------|
| 1 | Send `DELETE` request to `route('inventory.products.destroy', $product)` | Response redirects with success flash message |
| 2 | Assert product is soft-deleted: `Product::find($product->id)` returns null | Standard query excludes soft-deleted records |
| 3 | Assert product exists with trashed: `Product::withTrashed()->find($product->id)` returns the product | Record still in database with `deleted_at` set |
| 4 | Assert `deleted_at` is set to approximately now | Soft delete timestamp recorded |
| 5 | Assert `status` is changed to `archived` | Status updated on soft delete |
| 6 | Assert product no longer appears in the default index listing | Index query excludes trashed products (unless `status=archived` filter is used) |

**Test Data:**

```php
$product = Product::factory()->create([
    'organization_id' => $this->organization->id,
    'name' => 'Product To Archive',
    'sku' => 'TEST-ARC-001',
    'status' => 'active',
    'stock_quantity' => 10,
]);
```

**Postconditions:**

- `deleted_at` column is set (non-null)
- `status` column is `archived`
- Product is excluded from default queries (SoftDeletes trait)
- Related InventoryLevels, marketplace listings, etc. are NOT cascade-deleted (soft delete preserves data)
- The `ProductDeletionService` may handle additional cleanup (end marketplace listings, etc.)

**Notes:**

- The `ProductDeletionService` is injected into the `ProductController` constructor and handles deletion logic
- The `deletionImpact()` endpoint (`GET /inventory/products/{product}/deletion-impact`) can be called first to preview what will be affected
- Active marketplace listings (eBay, Shopify) should be ended or archived when the product is soft-deleted
- Existing `ProductDeletionTest` may cover some of this behavior

---

### TC-INV-001-07: Restore archived product clears deleted_at and sets status to draft

| Field | Value |
|-------|-------|
| **ID** | TC-INV-001-07 |
| **Priority** | P1 |
| **Type** | Feature |
| **Platform** | Web |
| **Automated** | No |
| **Test File** | `tests/Feature/Inventory/ProductCrudTest.php` (needed) |
| **Test Method** | `test_restore_product_clears_deleted_at_and_sets_draft()` (needed) |

**Preconditions:**

1. Organization fixtures set up via `CreatesOrganizationFixtures` trait
2. User is authenticated with `inventory.update` or `inventory.delete` permission
3. A soft-deleted product exists (status = `archived`, `deleted_at` is non-null)

**Test Steps:**

| Step | Action | Expected Result |
|------|--------|-----------------|
| 1 | Send `POST` request to `route('inventory.products.restore', $product)` | Response redirects with success flash message |
| 2 | Refresh product from database (without trashed scope) | `Product::find($product->id)` returns the product |
| 3 | Assert `deleted_at` is null | Soft delete cleared |
| 4 | Assert `status` is `draft` | Restored products default to draft status (not re-activated immediately) |
| 5 | Assert product appears in the default index listing | Product visible without the `archived` filter |

**Test Data:**

```php
$archivedProduct = Product::factory()->create([
    'organization_id' => $this->organization->id,
    'name' => 'Archived Product',
    'sku' => 'TEST-RST-001',
    'status' => 'archived',
    'deleted_at' => now()->subDays(5),
]);
```

**Postconditions:**

- `deleted_at` is null
- `status` is `draft` (not `active` -- requires manual re-activation)
- Product is included in default (non-trashed) queries
- Marketplace listings are NOT automatically re-created on restore

**Notes:**

- Route: `POST /inventory/products/{product}/restore`
- The product is resolved via route model binding with `withTrashed()` scope on the route definition
- Restoring to `draft` rather than `active` is a safety measure to prevent accidental re-listing
- The user must manually change status to `active` and re-create marketplace listings if desired

---

### TC-INV-001-08: Force delete permanently removes product and related records

| Field | Value |
|-------|-------|
| **ID** | TC-INV-001-08 |
| **Priority** | P1 |
| **Type** | Feature |
| **Platform** | Web |
| **Automated** | No |
| **Test File** | `tests/Feature/Inventory/ProductDeletionTest.php` (may partially exist) |
| **Test Method** | `test_force_destroy_permanently_removes_product()` (needed) |

**Preconditions:**

1. Organization fixtures set up via `CreatesOrganizationFixtures` trait
2. User is authenticated with `inventory.delete` permission
3. A soft-deleted product exists (already archived)
4. Related records exist: ProductImages, InventoryMovements, ProductIdentifiers

**Test Steps:**

| Step | Action | Expected Result |
|------|--------|-----------------|
| 1 | Send `DELETE` request to `route('inventory.products.force-destroy', $product)` | Response redirects with success flash message |
| 2 | Assert product does not exist even with trashed scope | `Product::withTrashed()->find($product->id)` returns null |
| 3 | Assert related ProductImage records are deleted | `ProductImage::where('product_id', $product->id)->count()` is 0 |
| 4 | Assert related InventoryMovement records are handled (deleted or nullified) | Movements referencing the product are cleaned up |
| 5 | Assert related ProductIdentifier records are deleted | Identifiers removed |
| 6 | Assert the UUID is no longer occupied | No record with that UUID exists in the products table |

**Test Data:**

```php
$product = Product::factory()->create([
    'organization_id' => $this->organization->id,
    'sku' => 'TEST-FD-001',
    'status' => 'archived',
    'deleted_at' => now()->subDays(30),
]);

ProductImage::factory()->create(['product_id' => $product->id]);
ProductIdentifier::factory()->create(['product_id' => $product->id, 'type' => 'upc', 'value' => '012345678901']);
```

**Postconditions:**

- Product row permanently removed from the `products` table
- All related child records cleaned up by the `ProductDeletionService`
- No orphaned foreign key references remain
- This action is irreversible

**Notes:**

- Route: `DELETE /inventory/products/{product}/force`
- Force delete should only be available for products that are already soft-deleted (archived)
- The `ProductDeletionService` handles cascade cleanup of related records
- Consider confirming the operation via a UI confirmation dialog before sending the request
- eBay and Shopify listings referencing this product should already be ended (from soft delete step)

---

### TC-INV-001-09: Duplicate product creates copy with new SKU

| Field | Value |
|-------|-------|
| **ID** | TC-INV-001-09 |
| **Priority** | P2 |
| **Type** | Feature |
| **Platform** | Web |
| **Automated** | No |
| **Test File** | `tests/Feature/Inventory/ProductCrudTest.php` (needed) |
| **Test Method** | `test_duplicate_product_creates_copy_with_new_sku()` (needed) |

**Preconditions:**

1. Organization fixtures set up via `CreatesOrganizationFixtures` trait
2. User is authenticated with `inventory.create` permission
3. An existing product with SKU `TEST-ORIG-001`, images, and category assignment

**Test Steps:**

| Step | Action | Expected Result |
|------|--------|-----------------|
| 1 | Send `POST` request to `route('inventory.products.duplicate', $originalProduct)` | Response redirects to the new product's show page or the index |
| 2 | Assert a new Product record exists in the database | Product count increased by 1 |
| 3 | Assert the new product has a different UUID than the original | `$newProduct->id !== $originalProduct->id` |
| 4 | Assert the new product's SKU is modified (e.g., `TEST-ORIG-001-COPY` or `TEST-ORIG-001-2`) | SKU is unique and derived from the original |
| 5 | Assert the new product's name matches or is prefixed (e.g., "Copy of Original Product") | Name indicates it is a duplicate |
| 6 | Assert the new product's `status` is `draft` | Duplicates start as drafts, not active |
| 7 | Assert `cost_price`, `selling_price`, `category_id`, `brand_id` are copied | Core fields duplicated |
| 8 | Assert `stock_quantity` and `available_quantity` are reset to 0 | Inventory quantities are not duplicated |
| 9 | Assert marketplace listings are NOT duplicated | No eBay or Shopify listings reference the new product |

**Test Data:**

```php
$originalProduct = Product::factory()->create([
    'organization_id' => $this->organization->id,
    'name' => 'Original Product',
    'sku' => 'TEST-ORIG-001',
    'status' => 'active',
    'cost_price' => 15.00,
    'selling_price' => 29.99,
    'stock_quantity' => 50,
    'available_quantity' => 45,
    'category_id' => $category->id,
    'brand_id' => $brand->id,
]);
```

**Postconditions:**

- Two Product records exist: original (unchanged) and duplicate (new UUID)
- Duplicate has unique SKU within the organization
- Duplicate has `status = 'draft'` and `stock_quantity = 0`
- No marketplace listings are created for the duplicate
- `created_by` on the duplicate is set to the authenticated user

**Notes:**

- Route: `POST /inventory/products/{product}/duplicate`
- The duplicate SKU format depends on the implementation (common patterns: append `-COPY`, append `-2`, or auto-increment a suffix)
- Images may or may not be duplicated depending on the implementation
- The original product remains completely unchanged

---

### TC-INV-001-10: Bulk action - archive multiple products

| Field | Value |
|-------|-------|
| **ID** | TC-INV-001-10 |
| **Priority** | P1 |
| **Type** | Feature |
| **Platform** | Web |
| **Automated** | No |
| **Test File** | `tests/Feature/Inventory/ProductCrudTest.php` (needed) |
| **Test Method** | `test_bulk_action_archive_multiple_products()` (needed) |

**Preconditions:**

1. Organization fixtures set up via `CreatesOrganizationFixtures` trait
2. User is authenticated with `inventory.delete` permission
3. Multiple active products exist in the organization (at least 3)

**Test Steps:**

| Step | Action | Expected Result |
|------|--------|-----------------|
| 1 | Send `POST` request to `route('inventory.products.bulk-action')` with `action = 'archive'` and `product_ids = [$id1, $id2, $id3]` | Response redirects with success flash message indicating count of archived products |
| 2 | Assert all three products are soft-deleted | `Product::whereIn('id', $ids)->count()` returns 0 |
| 3 | Assert all three products exist with trashed | `Product::withTrashed()->whereIn('id', $ids)->count()` returns 3 |
| 4 | Assert `deleted_at` is set on all three | All records have non-null `deleted_at` |
| 5 | Assert `status` is `archived` on all three | Status updated in bulk |
| 6 | Assert products from other organizations are NOT affected | Only specified product IDs in the correct org are archived |

**Test Data:**

```php
$products = Product::factory()->count(3)->create([
    'organization_id' => $this->organization->id,
    'status' => 'active',
]);

$otherOrgProduct = Product::factory()->create([
    'organization_id' => Organization::factory()->create()->id,
    'status' => 'active',
]);

$bulkData = [
    'action' => 'archive',
    'product_ids' => $products->pluck('id')->toArray(), // array of UUID strings
];
```

**Postconditions:**

- All specified products are soft-deleted with `status = 'archived'`
- Other organization's products remain unaffected
- The `BulkProductRequest` validates the input (action type, product IDs format)

**Notes:**

- Route: `POST /inventory/products/bulk-action`
- The controller uses `BulkProductRequest` form request for validation
- Product IDs are UUID strings, not integers
- Other bulk actions may include: `activate`, `deactivate`, `export`, `delete` (force)
- Organization scoping must be enforced to prevent cross-org bulk operations

---

### TC-INV-001-11: Product export generates CSV with selected columns

| Field | Value |
|-------|-------|
| **ID** | TC-INV-001-11 |
| **Priority** | P2 |
| **Type** | Feature |
| **Platform** | Web |
| **Automated** | No |
| **Test File** | `tests/Feature/Inventory/ProductExportTest.php` (needed) |
| **Test Method** | `test_export_products_generates_csv()` (needed) |

**Preconditions:**

1. Organization fixtures set up via `CreatesOrganizationFixtures` trait
2. User is authenticated with `products.export` permission
3. Multiple products exist in the organization with various statuses

**Test Steps:**

| Step | Action | Expected Result |
|------|--------|-----------------|
| 1 | Send `GET` request to `route('inventory.products.export')` with optional filter parameters | Response status 200 with `Content-Type: text/csv` or a download response |
| 2 | Assert response has correct `Content-Disposition` header for file download | Header includes `attachment; filename=` with a descriptive filename |
| 3 | Parse CSV content and assert header row contains expected columns | Headers include: `name`, `sku`, `status`, `cost_price`, `selling_price`, `stock_quantity`, `category` |
| 4 | Assert data rows match the products in the organization | Row count equals the number of products (respecting any applied filters) |
| 5 | Assert exported data only includes the authenticated user's organization | No products from other organizations appear in the export |
| 6 | Send `GET` with `?status=active` filter | Only active products are exported |

**Test Data:**

```php
Product::factory()->count(5)->create([
    'organization_id' => $this->organization->id,
    'status' => 'active',
]);

Product::factory()->count(2)->create([
    'organization_id' => $this->organization->id,
    'status' => 'draft',
]);
```

**Postconditions:**

- No database mutations; export is read-only
- CSV file is streamed as a download response
- Filters applied to the export match the same filters available on the index page

**Notes:**

- Route: `GET /inventory/products/export`
- The `ProductExportService` is injected into the controller and handles CSV generation
- Large exports should be handled via a queued job with a download link (not blocking the request)
- Exported prices should be formatted as plain numbers (no currency symbols) for spreadsheet compatibility
- UUID product IDs should be included as a column for re-import/matching purposes

---

### TC-INV-001-12: Organization isolation - user only sees own organization's products

| Field | Value |
|-------|-------|
| **ID** | TC-INV-001-12 |
| **Priority** | P0 |
| **Type** | Feature |
| **Platform** | Web |
| **Automated** | No |
| **Test File** | `tests/Feature/Inventory/ProductIsolationTest.php` (needed) |
| **Test Method** | `test_user_only_sees_own_organization_products()` (needed) |

**Preconditions:**

1. Two separate Organizations exist (Org A and Org B)
2. Each organization has its own user, team, and admin role
3. Org A has 3 products; Org B has 2 products
4. The authenticated user belongs to Org A

**Test Steps:**

| Step | Action | Expected Result |
|------|--------|-----------------|
| 1 | Authenticate as Org A's user | User session established for Org A |
| 2 | Send `GET` to `route('inventory.products.index')` | Response contains only Org A's 3 products |
| 3 | Assert Org B's products are NOT in the response | Product IDs from Org B are absent from the paginator data |
| 4 | Attempt to access Org B's product detail page: `GET route('inventory.products.show', $orgBProduct)` | Response is 403 Forbidden or 404 Not Found |
| 5 | Attempt to update Org B's product: `PUT route('inventory.products.update', $orgBProduct)` | Response is 403 Forbidden or 404 Not Found |
| 6 | Attempt to delete Org B's product: `DELETE route('inventory.products.destroy', $orgBProduct)` | Response is 403 Forbidden or 404 Not Found |

**Test Data:**

```php
// Org A (authenticated user's org)
$orgA = Organization::factory()->create(['name' => 'Store Alpha']);
$userA = User::factory()->create(['organization_id' => $orgA->id]);
Product::factory()->count(3)->create(['organization_id' => $orgA->id]);

// Org B (different org)
$orgB = Organization::factory()->create(['name' => 'Store Beta']);
$userB = User::factory()->create(['organization_id' => $orgB->id]);
$orgBProducts = Product::factory()->count(2)->create(['organization_id' => $orgB->id]);
```

**Postconditions:**

- Org A user can only CRUD their own organization's products
- All cross-org access attempts are rejected (403 or 404)
- The `forOrganization($organizationId)` scope on the Product model enforces data isolation
- No data leakage between organizations in any response

**Notes:**

- This is a critical multi-tenant security test and should be P0
- The controller derives `organizationId` from the authenticated user's properties (`organization_id`, `current_organization_id`, or `currentTeam.organization_id`)
- Route model binding may not enforce organization scoping automatically -- the controller must check `$product->organization_id` against the user's org
- Products with UUID PKs cannot be guessed sequentially, but organization isolation must not rely on obscurity
- Test all CRUD operations for cross-org access denial, not just index
