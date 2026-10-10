# Product Bundles Test Cases

## Document ID: TC-CAT-001

**Domain:** Catalog/Bundles
**Related Test Plan:** `docs/testing/plans/catalog-test-plan.md`
**Last Updated:** 2026-04-15

---

### TC-CAT-001-01: List bundles

| Field | Value |
|-------|-------|
| **ID** | TC-CAT-001-01 |
| **Priority** | P0 |
| **Type** | Feature |
| **Platform** | Web |
| **Automated** | No |

**Preconditions:**
1. Authenticated user (controller applies `auth` middleware)
2. Organization has multiple ProductBundle records with associated products and items
3. BundleService injected into BundleController

**Test Steps:**

| Step | Action | Expected Result |
|------|--------|-----------------|
| 1 | Send `GET` request to `route('inventory.bundles.index')` | Response status 200, Inertia renders `Inventory/Bundles/Index` |
| 2 | Assert `bundles` prop is paginated at 20 per page | Paginator with `data`, `current_page`, `per_page` keys |
| 3 | Assert each bundle has `product` relationship loaded with `id, name, sku, selling_price, available_quantity` | Bundle product data present |
| 4 | Assert each bundle has `items_count` attribute | `withCount('items')` applied |
| 5 | Assert `stats` prop returned from `BundleService::getStats($organizationId)` | Bundle statistics (total, active, etc.) |
| 6 | Send `GET` with `?search=Widget` | Only bundles matching name or product name/SKU via ILIKE search returned |
| 7 | Assert bundles ordered by `created_at desc` | Most recent bundles first |

**Notes:**
- Route: `GET /inventory/bundles` named `inventory.bundles.index`
- Uses `ProductBundle::withoutGlobalScopes()` to bypass any global scope, then explicitly filters by `organization_id`
- Search uses `ILIKE` for case-insensitive PostgreSQL pattern matching on bundle name and related product name/SKU
- Organization ID resolved via three-level fallback: `currentTeam->organization_id`, `current_organization_id`, `organization_id`

---

### TC-CAT-001-02: Create fixed bundle with components

| Field | Value |
|-------|-------|
| **ID** | TC-CAT-001-02 |
| **Priority** | P0 |
| **Type** | Feature |
| **Platform** | Web |
| **Automated** | No |

**Preconditions:**
1. Authenticated user
2. A parent Product exists (will be the bundle product)
3. At least 2 component Products exist with `is_bundle = false`
4. BundleService injected

**Test Steps:**

| Step | Action | Expected Result |
|------|--------|-----------------|
| 1 | Send `POST` to `route('inventory.bundles.store')` with `product_id`, `name`, `bundle_type = 'fixed'`, and `components` array (2 items with `product_id`, `quantity`, `is_required`) | Redirect to bundle show page with flash `'Bundle created.'` |
| 2 | Assert ProductBundle record created with `organization_id`, `name`, `bundle_type = 'fixed'` | Bundle persisted in database |
| 3 | Assert 2 ProductBundleItem records created linked to the bundle | Component items created |
| 4 | Assert each item has `component_product_id`, `quantity`, `is_required` values matching input | Item fields match submitted data |
| 5 | Assert parent product's `is_bundle` flag set to `true` | Product marked as bundle via BundleService |
| 6 | Validate: POST with empty `components` array | Validation error: `components` required, min 1 |
| 7 | Validate: POST with `bundle_type = 'invalid'` | Validation error: bundle_type must be `fixed` or `pick_n` |

**Notes:**
- Validation rules enforce: `product_id` required UUID exists in products, `name` required max:255, `bundle_type` in `fixed,pick_n`, `components` required array min:1
- `BundleService::create()` handles bundle creation, item creation, and product flag update
- `product_id` is the parent/bundle product, `components.*.product_id` are the child component products
- Component product IDs validated with `exists:products,id`

---

### TC-CAT-001-03: Create pick-n bundle with pick_n_quantity

| Field | Value |
|-------|-------|
| **ID** | TC-CAT-001-03 |
| **Priority** | P1 |
| **Type** | Feature |
| **Platform** | Web |
| **Automated** | No |

**Preconditions:**
1. Authenticated user
2. A parent Product and at least 4 component Products exist

**Test Steps:**

| Step | Action | Expected Result |
|------|--------|-----------------|
| 1 | Send `POST` to `route('inventory.bundles.store')` with `bundle_type = 'pick_n'`, `pick_n_quantity = 3`, and 4 components | Bundle created with `bundle_type = 'pick_n'` and `pick_n_quantity = 3` |
| 2 | Assert `pick_n_quantity` stored on the ProductBundle record | Customer picks 3 of the 4 available components |
| 3 | Assert 4 ProductBundleItem records created | All components available for selection |
| 4 | Assert some components can be marked `is_required = false` (optional in pick-n) | Optional flag persisted per component |
| 5 | Validate: `pick_n_quantity` must be integer min:1 | Validation enforced |

**Notes:**
- Pick-n bundles allow customers to choose N components from a larger set
- `pick_n_quantity` defines how many items the customer selects
- Validation: `pick_n_quantity` is nullable integer min:1 (nullable because fixed bundles do not use it)
- Availability calculation for pick-n bundles may differ from fixed bundles

---

### TC-CAT-001-04: View bundle detail with component tree and availability

| Field | Value |
|-------|-------|
| **ID** | TC-CAT-001-04 |
| **Priority** | P0 |
| **Type** | Feature |
| **Platform** | Web |
| **Automated** | No |

**Preconditions:**
1. Authenticated user
2. ProductBundle exists with parent product and at least 3 component items
3. Component products have known `available_quantity` values

**Test Steps:**

| Step | Action | Expected Result |
|------|--------|-----------------|
| 1 | Send `GET` to `route('inventory.bundles.show', $bundle)` | Response status 200, Inertia renders `Inventory/Bundles/Show` |
| 2 | Assert `bundle` prop has `product` relationship loaded with `id, name, sku, selling_price, available_quantity, cost_price` | Parent product details |
| 3 | Assert `bundle.items` array loaded, each with `componentProduct` including `id, name, sku, available_quantity, cost_price` | Component tree visible |
| 4 | Assert `availableQuantity` prop is calculated via `$bundle->calculateAvailableQuantity()` | Bundle availability computed |
| 5 | Assert available quantity is the minimum of `floor(component.available_quantity / component.quantity)` across all required components | Fixed bundle: limited by scarcest component |
| 6 | Verify component with lowest stock ratio limits overall bundle availability | Bottleneck component identified |

**Notes:**
- The `show` method explicitly loads relationships: `product:id,name,sku,...` and `items.componentProduct:id,name,sku,...`
- `calculateAvailableQuantity()` is a method on the ProductBundle model
- For fixed bundles: availability = min(floor(component_available_qty / required_qty)) across all required components
- For pick-n bundles: availability calculation may differ (depends on which components the customer picks)

---

### TC-CAT-001-05: Update bundle name/status

| Field | Value |
|-------|-------|
| **ID** | TC-CAT-001-05 |
| **Priority** | P1 |
| **Type** | Feature |
| **Platform** | Web |
| **Automated** | No |

**Preconditions:**
1. Authenticated user
2. Existing ProductBundle with known name and `is_active = true`

**Test Steps:**

| Step | Action | Expected Result |
|------|--------|-----------------|
| 1 | Send `PUT` to `route('inventory.bundles.update', $bundle)` with `name = 'Updated Bundle Name'`, `is_active = false` | Redirect back with flash `'Bundle updated.'` |
| 2 | Assert bundle name changed in database | `$bundle->refresh()->name` equals `'Updated Bundle Name'` |
| 3 | Assert `is_active` changed to `false` | Bundle deactivated |
| 4 | Assert description can be updated or set to null | `description` is nullable, max:2000 |
| 5 | Assert component items are NOT modified by bundle update | Items unchanged; update only affects bundle-level fields |

**Notes:**
- Route: `PUT /inventory/bundles/{bundle}` named `inventory.bundles.update`
- Update validation is simpler than create: only `name`, `description`, `is_active`
- Components are managed separately via addComponent/removeComponent endpoints
- No re-validation of `product_id` or `bundle_type` on update (these are immutable after creation)

---

### TC-CAT-001-06: Add component product

| Field | Value |
|-------|-------|
| **ID** | TC-CAT-001-06 |
| **Priority** | P1 |
| **Type** | Feature |
| **Platform** | Web |
| **Automated** | No |

**Preconditions:**
1. Authenticated user
2. Existing ProductBundle with 2 component items
3. A new product available to add as component

**Test Steps:**

| Step | Action | Expected Result |
|------|--------|-----------------|
| 1 | Send `POST` to `route('inventory.bundles.add-component', $bundle)` with `product_id`, `quantity = 2`, `is_required = true` | Redirect back with flash `'Component added.'` |
| 2 | Assert new ProductBundleItem created with `bundle_id`, `component_product_id`, `quantity = 2` | Item persisted |
| 3 | Assert `sort_order` set to `max(existing sort_order) + 1` | Auto-incremented sort order |
| 4 | Assert `is_required` defaults to `true` if not provided | Default value applied |
| 5 | Assert `BundleService::updateBundleAvailability($bundle)` called after adding | Bundle availability recalculated |
| 6 | Assert bundle now has 3 component items | Item count increased |

**Notes:**
- Route: `POST /inventory/bundles/{bundle}/components` named `inventory.bundles.add-component`
- `sort_order` calculated as `$bundle->items()->max('sort_order') + 1` (or 1 if no items exist)
- `is_required` defaults to `true` via `$validated['is_required'] ?? true`
- After adding a component, `updateBundleAvailability()` recalculates the bundle's available quantity

---

### TC-CAT-001-07: Remove component

| Field | Value |
|-------|-------|
| **ID** | TC-CAT-001-07 |
| **Priority** | P1 |
| **Type** | Feature |
| **Platform** | Web |
| **Automated** | No |

**Preconditions:**
1. Authenticated user
2. Existing ProductBundle with at least 2 component items
3. Known ProductBundleItem ID to remove

**Test Steps:**

| Step | Action | Expected Result |
|------|--------|-----------------|
| 1 | Send `DELETE` to `route('inventory.bundles.remove-component', [$bundle, $item])` | Redirect back with flash `'Component removed.'` |
| 2 | Assert ProductBundleItem record deleted from database | `ProductBundleItem::find($item->id)` returns null |
| 3 | Assert bundle's item count decreased by 1 | `$bundle->items()->count()` reduced |
| 4 | Assert `BundleService::updateBundleAvailability($bundle)` called after removal | Bundle availability recalculated |
| 5 | Assert remaining component items and bundle itself are unaffected | Other items and bundle record intact |

**Notes:**
- Route: `DELETE /inventory/bundles/{bundle}/components/{item}` named `inventory.bundles.remove-component`
- The `$item` parameter is a ProductBundleItem resolved via route model binding
- After removal, `updateBundleAvailability()` updates the available quantity based on remaining components
- Removing all components from a bundle does not auto-delete the bundle itself

---

### TC-CAT-001-08: Delete bundle resets product.is_bundle

| Field | Value |
|-------|-------|
| **ID** | TC-CAT-001-08 |
| **Priority** | P1 |
| **Type** | Feature |
| **Platform** | Web |
| **Automated** | No |

**Preconditions:**
1. Authenticated user
2. Existing ProductBundle with parent product (`is_bundle = true`) and component items

**Test Steps:**

| Step | Action | Expected Result |
|------|--------|-----------------|
| 1 | Verify parent product has `is_bundle = true` before deletion | Precondition confirmed |
| 2 | Send `DELETE` to `route('inventory.bundles.destroy', $bundle)` | Redirect to `inventory.bundles.index` with flash `'Bundle deleted.'` |
| 3 | Assert all ProductBundleItem records for this bundle deleted | `$bundle->items()->delete()` called first |
| 4 | Assert ProductBundle record deleted | Bundle no longer in database |
| 5 | Assert parent product's `is_bundle` reset to `false` | `$product->update(['is_bundle' => false])` |
| 6 | Assert parent product itself is NOT deleted | Product record still exists |

**Notes:**
- The `destroy` method explicitly: 1) deletes all items, 2) deletes the bundle, 3) resets `product.is_bundle = false`
- The parent product relationship is accessed via `$bundle->product` before bundle deletion
- Component products are not affected by bundle deletion (they remain as standalone products)
- This is a hard delete, not a soft delete

---

### TC-CAT-001-09: Bundle availability calculated from minimum component stock

| Field | Value |
|-------|-------|
| **ID** | TC-CAT-001-09 |
| **Priority** | P0 |
| **Type** | Feature |
| **Platform** | Web |
| **Automated** | No |

**Preconditions:**
1. Fixed bundle with 3 required components:
   - Component A: quantity=2, available_quantity=10 (can make 5 bundles)
   - Component B: quantity=1, available_quantity=3 (can make 3 bundles)
   - Component C: quantity=3, available_quantity=12 (can make 4 bundles)

**Test Steps:**

| Step | Action | Expected Result |
|------|--------|-----------------|
| 1 | Call `$bundle->calculateAvailableQuantity()` | Returns 3 (limited by Component B: floor(3/1) = 3) |
| 2 | Update Component B available_quantity to 10 | New bottleneck is Component C: floor(12/3) = 4 |
| 3 | Call `$bundle->calculateAvailableQuantity()` again | Returns 4 (limited by Component C) |
| 4 | Set Component A available_quantity to 0 | Zero stock on any required component |
| 5 | Call `$bundle->calculateAvailableQuantity()` | Returns 0 (cannot make any bundles) |
| 6 | Verify on bundle show page: `availableQuantity` prop reflects the calculated value | Frontend displays correct availability |

**Notes:**
- Formula for fixed bundles: `min(floor(component.available_quantity / item.quantity))` across all required items
- Components with `is_required = false` may be excluded from the minimum calculation
- `BundleService::updateBundleAvailability()` may cache or store the result on the bundle or parent product
- Zero availability on any required component means zero bundle availability

---

### TC-CAT-001-10: Organization isolation on bundles

| Field | Value |
|-------|-------|
| **ID** | TC-CAT-001-10 |
| **Priority** | P0 |
| **Type** | Security |
| **Platform** | Web |
| **Automated** | No |

**Preconditions:**
1. Two organizations (Org A and Org B) each with bundles
2. Org A has 3 bundles; Org B has 2 bundles
3. Authenticate as Org A user

**Test Steps:**

| Step | Action | Expected Result |
|------|--------|-----------------|
| 1 | Send `GET` to `route('inventory.bundles.index')` as Org A user | Only Org A's 3 bundles returned |
| 2 | Assert Org B's bundle IDs do not appear in the response | No cross-org data leakage |
| 3 | Attempt to view Org B's bundle detail via `route('inventory.bundles.show', $orgBBundle)` | 403 Forbidden or 404 Not Found |
| 4 | Attempt to add a component to Org B's bundle | 403 Forbidden or 404 Not Found |
| 5 | Attempt to delete Org B's bundle | 403 Forbidden or 404 Not Found |
| 6 | On create page: `products` dropdown only shows Org A products (where `is_bundle = false`) | Product list filtered by organization |

**Notes:**
- Index query uses `ProductBundle::withoutGlobalScopes()->where('organization_id', $organizationId)` for explicit org filtering
- The `create` page filters products by `Product::where('organization_id', $organizationId)`
- Route model binding on `show`, `update`, `destroy` must also enforce org scoping (either via global scope or controller check)
- The `store` method derives `organizationId` from the user and passes it to `BundleService::create()`
