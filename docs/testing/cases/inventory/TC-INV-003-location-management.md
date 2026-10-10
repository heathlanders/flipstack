# Location Management Test Cases

## Document ID: TC-INV-003

**Domain:** Inventory
**Related Test Plan:** `docs/testing/plans/inventory-test-plan.md`
**Last Updated:** 2026-04-15

---

### TC-INV-003-01: View locations index with type filter and search

| Field | Value |
|-------|-------|
| **ID** | TC-INV-003-01 |
| **Priority** | P1 |
| **Type** | Feature |
| **Platform** | Web |
| **Automated** | No |
| **Test File** | `tests/Feature/Inventory/LocationManagementTest.php` (needed) |
| **Test Method** | `test_index_displays_locations_with_filters()` (needed) |

**Preconditions:**

1. Organization fixtures set up via `CreatesOrganizationFixtures` trait
2. User is authenticated (middleware: `auth`)
3. Multiple InventoryLocations exist in the organization with different types (`warehouse`, `store`, `supplier`, `customer`, `virtual`)
4. At least one location has `is_active = false` for status filter testing

**Test Steps:**

| Step | Action | Expected Result |
|------|--------|-----------------|
| 1 | Send `GET` request to `route('inventory.locations.index')` | Response status 200, Inertia renders `Inventory/Locations/Index` |
| 2 | Assert response contains `locations` prop with paginated collection (12 per page) | Paginator present with `data`, `current_page`, `per_page` keys |
| 3 | Assert response contains `stats` with `total`, `active`, `warehouses`, `stores` counts | Statistics calculated from all locations in the org |
| 4 | Assert response contains `types` array listing distinct location types in the org | Filter dropdown populated with actual types used |
| 5 | Send `GET` with `?type=warehouse` filter | Only locations with `type = 'warehouse'` are returned |
| 6 | Send `GET` with `?type=store` filter | Only locations with `type = 'store'` are returned |
| 7 | Send `GET` with `?status=active` filter | Only locations with `is_active = true` are returned |
| 8 | Send `GET` with `?status=inactive` filter | Only locations with `is_active = false` are returned |
| 9 | Send `GET` with `?search=Main` | Locations matching "Main" in `name`, `code`, or `city` are returned (LIKE search) |
| 10 | Send `GET` with `?sort=name` | Locations sorted alphabetically by name ascending |
| 11 | Send `GET` with `?sort=created_at_desc` | Locations sorted by creation date descending |

**Test Data:**

```php
$warehouse = InventoryLocation::factory()->create([
    'organization_id' => $this->organization->id,
    'name' => 'Main Warehouse',
    'code' => 'WH-001',
    'type' => 'warehouse',
    'city' => 'Portland',
    'state' => 'OR',
    'is_active' => true,
    'is_default' => true,
]);

$store = InventoryLocation::factory()->create([
    'organization_id' => $this->organization->id,
    'name' => 'Retail Store',
    'code' => 'ST-001',
    'type' => 'store',
    'city' => 'Seattle',
    'state' => 'WA',
    'is_active' => true,
]);

$inactiveLocation = InventoryLocation::factory()->create([
    'organization_id' => $this->organization->id,
    'name' => 'Old Storage',
    'code' => 'OLD-001',
    'type' => 'warehouse',
    'is_active' => false,
]);
```

**Postconditions:**

- No database mutations; read-only endpoint
- Pagination uses 12 items per page (hardcoded in controller)
- Sorting restricted to allowed columns: `name`, `code`, `type`, `city`, `created_at`
- Query string parameters preserved via `withQueryString()`

**Notes:**

- InventoryLocation uses the `HasUuid` trait (custom UUID trait, not Laravel's `HasUuids`)
- The `BelongsToOrganization` trait applies a global scope for organization isolation
- Search uses SQL `LIKE` with wildcards on `name`, `code`, and `city` fields
- The `sort` parameter supports a `_desc` suffix to reverse sort direction (e.g., `name_desc`)
- Location types: `warehouse`, `store`, `supplier`, `customer`, `virtual`

---

### TC-INV-003-02: Create warehouse location with address fields

| Field | Value |
|-------|-------|
| **ID** | TC-INV-003-02 |
| **Priority** | P1 |
| **Type** | Feature |
| **Platform** | Web |
| **Automated** | No |
| **Test File** | `tests/Feature/Inventory/LocationManagementTest.php` (needed) |
| **Test Method** | `test_store_warehouse_location_with_address()` (needed) |

**Preconditions:**

1. Organization fixtures set up via `CreatesOrganizationFixtures` trait
2. User is authenticated with `admin.access` permission (location creation is admin-only)
3. No location with code `WH-NEW` exists

**Test Steps:**

| Step | Action | Expected Result |
|------|--------|-----------------|
| 1 | Send `POST` request to `route('inventory.locations.store')` with location data | Response redirects to locations index with success flash |
| 2 | Assert location exists in database with correct `organization_id` | Location created in the correct org |
| 3 | Assert `type` is `'warehouse'` | Type field persisted |
| 4 | Assert address fields are stored: `address_line1`, `address_line2`, `city`, `state`, `postal_code`, `country` | All address components saved |
| 5 | Assert `is_active` defaults to true or matches submitted value | Active status set correctly |
| 6 | Assert `code` is stored as submitted | Location code persisted |
| 7 | Assert `contact_person`, `email`, `phone` are stored | Contact info saved |

**Test Data:**

```php
$locationData = [
    'name' => 'New Warehouse',
    'code' => 'WH-NEW',
    'type' => 'warehouse',
    'address_line1' => '123 Industrial Blvd',
    'address_line2' => 'Unit 5',
    'city' => 'Portland',
    'state' => 'OR',
    'postal_code' => '97201',
    'country' => 'US',
    'contact_person' => 'John Manager',
    'email' => 'warehouse@example.com',
    'phone' => '503-555-0100',
    'is_active' => true,
    'is_default' => false,
];
```

**Postconditions:**

- One new InventoryLocation record exists in the `inventory_locations` table
- `organization_id` set from the authenticated user's organization
- UUID primary key generated via the `HasUuid` trait
- Activity logged via `Log::info('Inventory location created', [...])`
- If `is_default` is true, all other locations in the org have `is_default` set to false

**Notes:**

- Location creation uses `StoreLocationRequest` form request for validation
- Location types are validated against the allowed set: `warehouse`, `store`, `supplier`, `customer`, `virtual`
- The controller derives `organizationId` from `$user->currentTeam?->organization_id ?? $user->current_organization_id ?? $user->organization_id`
- The `BelongsToOrganization` trait on the model provides organization scoping
- Shipping configuration fields (`is_ship_from`, `default_weight_oz`, etc.) are optional and can be set later

---

### TC-INV-003-03: Update location fields

| Field | Value |
|-------|-------|
| **ID** | TC-INV-003-03 |
| **Priority** | P1 |
| **Type** | Feature |
| **Platform** | Web |
| **Automated** | No |
| **Test File** | `tests/Feature/Inventory/LocationManagementTest.php` (needed) |
| **Test Method** | `test_update_location_persists_fields()` (needed) |

**Preconditions:**

1. Organization fixtures set up via `CreatesOrganizationFixtures` trait
2. User is authenticated with `admin.access` permission
3. An existing location in the user's organization

**Test Steps:**

| Step | Action | Expected Result |
|------|--------|-----------------|
| 1 | Send `PUT` request to `route('inventory.locations.update', $location)` with updated fields | Response redirects to locations index with success flash |
| 2 | Refresh location from database | Updated data loaded |
| 3 | Assert `name` equals the new value | Name updated |
| 4 | Assert `city` and `state` are updated | Address fields changed |
| 5 | Assert `is_active` changed from true to false (if submitted) | Active status toggled |
| 6 | Assert `organization_id` is unchanged | Organization cannot be changed on update |
| 7 | Attempt to update a location belonging to a different organization | Response status 403 (aborted) |

**Test Data:**

```php
$location = InventoryLocation::factory()->create([
    'organization_id' => $this->organization->id,
    'name' => 'Old Name',
    'city' => 'Portland',
    'state' => 'OR',
    'is_active' => true,
]);

$updateData = [
    'name' => 'Updated Warehouse Name',
    'code' => $location->code,
    'type' => $location->type,
    'city' => 'Seattle',
    'state' => 'WA',
    'is_active' => false,
];
```

**Postconditions:**

- Location record updated in-place (same UUID primary key)
- `updated_at` timestamp refreshed
- Activity logged via `Log::info('Inventory location updated', [...])`
- If `is_default` changed from false to true, other locations' `is_default` flags are cleared

**Notes:**

- The controller explicitly checks `$location->organization_id !== $organizationId` and calls `abort(403)` for cross-org access attempts
- Location update uses `UpdateLocationRequest` form request for validation
- Changing the location `type` is allowed (e.g., converting a `store` to a `warehouse`)
- Changing `is_active` to false does not cascade to inventory levels or orders referencing this location

---

### TC-INV-003-04: Set location as default (only one default per org)

| Field | Value |
|-------|-------|
| **ID** | TC-INV-003-04 |
| **Priority** | P1 |
| **Type** | Feature |
| **Platform** | Web |
| **Automated** | No |
| **Test File** | `tests/Feature/Inventory/LocationManagementTest.php` (needed) |
| **Test Method** | `test_set_location_as_default_clears_previous_default()` (needed) |

**Preconditions:**

1. Organization fixtures set up via `CreatesOrganizationFixtures` trait
2. User is authenticated with `admin.access` permission
3. Two locations exist: Location A (default) and Location B (not default)

**Test Steps:**

| Step | Action | Expected Result |
|------|--------|-----------------|
| 1 | Assert Location A has `is_default = true` | Initial state confirmed |
| 2 | Assert Location B has `is_default = false` | Initial state confirmed |
| 3 | Send `PUT` to `route('inventory.locations.update', $locationB)` with `is_default = true` | Response redirects with success flash |
| 4 | Refresh Location A from database | Fresh data |
| 5 | Assert Location A now has `is_default = false` | Previous default cleared |
| 6 | Refresh Location B from database | Fresh data |
| 7 | Assert Location B now has `is_default = true` | New default set |
| 8 | Assert only one location in the org has `is_default = true` | `InventoryLocation::where('organization_id', $org->id)->where('is_default', true)->count()` equals 1 |

**Test Data:**

```php
$locationA = InventoryLocation::factory()->create([
    'organization_id' => $this->organization->id,
    'name' => 'Location A',
    'type' => 'warehouse',
    'is_default' => true,
]);

$locationB = InventoryLocation::factory()->create([
    'organization_id' => $this->organization->id,
    'name' => 'Location B',
    'type' => 'warehouse',
    'is_default' => false,
]);
```

**Postconditions:**

- Exactly one location per organization has `is_default = true`
- The previous default location is automatically cleared before the new one is set
- The default clearing query: `InventoryLocation::where('organization_id', $organizationId)->where('is_default', true)->where('id', '!=', $location->id)->update(['is_default' => false])`
- Only locations in the same organization are affected (not cross-org)

**Notes:**

- The `is_default` flag is handled in both `store()` and `update()` methods
- In `store()`: if `is_default` is true, ALL existing defaults are cleared (no exclusion)
- In `update()`: if `is_default` is true AND the location is not already default, other defaults are cleared (with `where('id', '!=', $location->id)`)
- Default location is used as the fallback for inventory operations when no location is specified
- There is no dedicated endpoint for setting default -- it is part of the standard update flow

---

### TC-INV-003-05: Delete location with no active inventory succeeds

| Field | Value |
|-------|-------|
| **ID** | TC-INV-003-05 |
| **Priority** | P1 |
| **Type** | Feature |
| **Platform** | Web |
| **Automated** | No |
| **Test File** | `tests/Feature/Inventory/LocationManagementTest.php` (needed) |
| **Test Method** | `test_destroy_location_without_inventory_succeeds()` (needed) |

**Preconditions:**

1. Organization fixtures set up via `CreatesOrganizationFixtures` trait
2. User is authenticated with `admin.access` permission
3. A location exists with no InventoryLevel records where `quantity_on_hand > 0`

**Test Steps:**

| Step | Action | Expected Result |
|------|--------|-----------------|
| 1 | Assert location has no active inventory: `$location->inventoryLevels()->where('quantity_on_hand', '>', 0)->count()` equals 0 | Precondition verified |
| 2 | Send `DELETE` request to `route('inventory.locations.destroy', $location)` | Response redirects to locations index with success flash |
| 3 | Assert location no longer exists in database | `InventoryLocation::find($location->id)` returns null |
| 4 | Assert activity logged | `Log::info('Inventory location deleted', [...])` called |

**Test Data:**

```php
$location = InventoryLocation::factory()->create([
    'organization_id' => $this->organization->id,
    'name' => 'Empty Location',
    'type' => 'warehouse',
    'is_active' => true,
]);

// No InventoryLevel records created for this location
// OR InventoryLevels exist but all have quantity_on_hand = 0
$emptyLevel = InventoryLevel::create([
    'organization_id' => $this->organization->id,
    'product_id' => $product->id,
    'location_id' => $location->id,
    'quantity_on_hand' => 0,
    'quantity_reserved' => 0,
]);
```

**Postconditions:**

- Location record permanently deleted (InventoryLocation does not use SoftDeletes)
- Associated InventoryLevel records with `quantity_on_hand = 0` may be cascade-deleted or orphaned depending on foreign key constraints
- Historical InventoryMovement records that reference this location may retain the `location_id` as a historical reference

**Notes:**

- The controller checks `$location->inventoryLevels()->where('quantity_on_hand', '>', 0)->exists()` before deletion
- Only active inventory (quantity > 0) blocks deletion; zero-quantity levels are allowed
- The organization ownership check (`abort(403)`) prevents deleting another org's locations
- InventoryLocation does NOT use SoftDeletes -- deletion is permanent

---

### TC-INV-003-06: Delete location with active inventory fails

| Field | Value |
|-------|-------|
| **ID** | TC-INV-003-06 |
| **Priority** | P1 |
| **Type** | Feature |
| **Platform** | Web |
| **Automated** | No |
| **Test File** | `tests/Feature/Inventory/LocationManagementTest.php` (needed) |
| **Test Method** | `test_destroy_location_with_active_inventory_fails()` (needed) |

**Preconditions:**

1. Organization fixtures set up via `CreatesOrganizationFixtures` trait
2. User is authenticated with `admin.access` permission
3. A location exists with at least one InventoryLevel where `quantity_on_hand > 0`

**Test Steps:**

| Step | Action | Expected Result |
|------|--------|-----------------|
| 1 | Assert location has active inventory: `$location->inventoryLevels()->where('quantity_on_hand', '>', 0)->exists()` is true | Precondition verified |
| 2 | Send `DELETE` request to `route('inventory.locations.destroy', $location)` | Response redirects back with error flash |
| 3 | Assert error message contains "Cannot delete location with active inventory" | Specific error message: "Cannot delete location with active inventory. Transfer inventory first." |
| 4 | Assert location still exists in database | `InventoryLocation::find($location->id)` returns the location |
| 5 | Assert InventoryLevel records are unchanged | No inventory data modified |

**Test Data:**

```php
$location = InventoryLocation::factory()->create([
    'organization_id' => $this->organization->id,
    'name' => 'Active Warehouse',
    'type' => 'warehouse',
]);

$product = Product::factory()->create([
    'organization_id' => $this->organization->id,
]);

InventoryLevel::create([
    'organization_id' => $this->organization->id,
    'product_id' => $product->id,
    'location_id' => $location->id,
    'quantity_on_hand' => 25,
    'quantity_reserved' => 5,
    'average_cost' => 10.00,
]);
```

**Postconditions:**

- Location not deleted
- Inventory levels unchanged
- Error message directs user to transfer inventory before deleting
- The error is returned via `back()->withErrors([...])` (session flash)

**Notes:**

- The check uses `->where('quantity_on_hand', '>', 0)->exists()` so even 1 unit of stock blocks deletion
- Reserved quantity alone (without on-hand quantity) should not block deletion -- only `quantity_on_hand > 0` is checked
- The user must first transfer all inventory to another location using the stock transfer feature, then retry deletion
- The error message explicitly suggests "Transfer inventory first" as a remediation step

---

### TC-INV-003-07: Location analytics returns top products and recent movements

| Field | Value |
|-------|-------|
| **ID** | TC-INV-003-07 |
| **Priority** | P2 |
| **Type** | Feature |
| **Platform** | Web |
| **Automated** | No |
| **Test File** | `tests/Feature/Inventory/LocationManagementTest.php` (needed) |
| **Test Method** | `test_analytics_returns_top_products_and_movements()` (needed) |

**Preconditions:**

1. Organization fixtures set up via `CreatesOrganizationFixtures` trait
2. User is authenticated
3. A location exists with multiple InventoryLevels (products with stock) and InventoryMovements

**Test Steps:**

| Step | Action | Expected Result |
|------|--------|-----------------|
| 1 | Send `GET` request to `route('inventory.locations.analytics', $location)` | Response status 200, Inertia renders `Inventory/Locations/Analytics` |
| 2 | Assert response contains `analytics.total_products` count | Number of products with `quantity_on_hand > 0` at this location |
| 3 | Assert response contains `analytics.total_value` | Sum of `quantity_on_hand * average_cost` across all levels at this location |
| 4 | Assert response contains `analytics.top_products` array (max 10 items) | Products sorted by `quantity_on_hand` descending, each with `product`, `quantity`, `value` |
| 5 | Assert each top product entry includes `product.name` and `product.sku` | Product details loaded via `product:id,name,sku` select |
| 6 | Assert response contains `analytics.recent_movements` array (max 20 items) | Latest movements at this location, sorted by creation date descending |
| 7 | Assert each movement includes `product.name` and `product.sku` | Product details loaded via relationship |
| 8 | Attempt to access analytics for a location in a different organization | Response status 403 |

**Test Data:**

```php
$location = InventoryLocation::factory()->create([
    'organization_id' => $this->organization->id,
    'name' => 'Analytics Warehouse',
    'type' => 'warehouse',
]);

$products = Product::factory()->count(3)->create([
    'organization_id' => $this->organization->id,
]);

// Create inventory levels with varying quantities
foreach ($products as $i => $product) {
    InventoryLevel::create([
        'organization_id' => $this->organization->id,
        'product_id' => $product->id,
        'location_id' => $location->id,
        'quantity_on_hand' => ($i + 1) * 10, // 10, 20, 30
        'average_cost' => 5.00,
    ]);
}

// Create movements
InventoryMovement::create([
    'organization_id' => $this->organization->id,
    'product_id' => $products[0]->id,
    'location_id' => $location->id,
    'type' => 'purchase',
    'quantity' => 10,
    'unit_cost' => 5.00,
    'total_cost' => 50.00,
    'balance_before' => 0,
    'balance_after' => 10,
]);
```

**Postconditions:**

- No database mutations; analytics is read-only
- `total_value` is computed via `SUM(quantity_on_hand * average_cost)` using `DB::raw()`
- `top_products` limited to 10 results, ordered by `quantity_on_hand DESC`
- `recent_movements` limited to 20 results, ordered by `created_at DESC`

**Notes:**

- Route: `GET /inventory/locations/{location}/analytics` (defined in routes/web.php under the inventory section)
- The controller uses `$location->getTotalProductCount()` and `$location->getTotalInventoryValue()` helper methods on the model
- Organization access is verified via `abort(403)` check
- `top_products` value is calculated as `quantity * average_cost` per item in the response mapping

---

### TC-INV-003-08: Verify ship-from address via EasyPost integration

| Field | Value |
|-------|-------|
| **ID** | TC-INV-003-08 |
| **Priority** | P2 |
| **Type** | Integration |
| **Platform** | Web |
| **Automated** | No |
| **Test File** | `tests/Feature/Inventory/LocationAddressVerificationTest.php` (needed) |
| **Test Method** | `test_verify_ship_from_address_via_easypost()` (needed) |

**Preconditions:**

1. Organization fixtures set up via `CreatesOrganizationFixtures` trait
2. User is authenticated
3. A location exists with complete address fields (`address_line1`, `city`, `state`, `postal_code`)
4. EasyPost service credentials are configured for the organization (or mocked in test)

**Test Steps:**

| Step | Action | Expected Result |
|------|--------|-----------------|
| 1 | Send `POST` request to `route('fulfillment.locations.verify-address', $location)` | Response redirects back with success flash |
| 2 | Refresh location from database | Updated data loaded |
| 3 | Assert `ship_from_verified` is true | Verification flag set |
| 4 | Assert `is_ship_from` is true | Location enabled as ship-from address |
| 5 | Assert `easypost_address_id` is set (non-null) | EasyPost address ID stored for future shipment creation |
| 6 | Attempt to verify a location missing required address fields (no `address_line1`) | Response redirects back with address validation error |
| 7 | Assert error message mentions required fields | "Address Line 1, City, State, and Postal Code are required" |

**Test Data:**

```php
$location = InventoryLocation::factory()->create([
    'organization_id' => $this->organization->id,
    'name' => 'Shipping Warehouse',
    'type' => 'warehouse',
    'address_line1' => '123 Industrial Blvd',
    'city' => 'Portland',
    'state' => 'OR',
    'postal_code' => '97201',
    'country' => 'US',
    'contact_person' => 'Shipping Manager',
    'phone' => '503-555-0100',
    'is_ship_from' => false,
    'ship_from_verified' => false,
    'easypost_address_id' => null,
]);

// Location missing address (for failure test):
$incompleteLocation = InventoryLocation::factory()->create([
    'organization_id' => $this->organization->id,
    'name' => 'Incomplete Location',
    'type' => 'warehouse',
    'address_line1' => null,  // missing
    'city' => null,           // missing
    'state' => null,          // missing
    'postal_code' => null,    // missing
]);
```

**Postconditions:**

- On success: `easypost_address_id`, `ship_from_verified = true`, `is_ship_from = true` are persisted
- On failure (incomplete address): location fields unchanged, error message returned
- Activity logged via `Log::info('Ship-from address verified via EasyPost', [...])`

**Notes:**

- Route: `POST /fulfillment/locations/{location}/verify-address` (defined in `routes/fulfillment.php`)
- The controller creates an `EasyPostService` instance and calls `createAddress()` then `verifyAddress()`
- In tests, the `EasyPostService` should be mocked to avoid real API calls
- The required address fields check is done before the API call: `address_line1`, `city`, `state`, `postal_code`
- The `easypost_address_id` is used later when creating shipping labels for orders from this location
- Organization ownership is verified via `abort(403)` before proceeding
