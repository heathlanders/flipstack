# Category Management Test Cases

## Document ID: TC-INV-002

**Domain:** Inventory
**Related Test Plan:** `docs/testing/plans/inventory-test-plan.md`
**Last Updated:** 2026-04-15

---

### TC-INV-002-01: View categories index in tree view and flat view

| Field | Value |
|-------|-------|
| **ID** | TC-INV-002-01 |
| **Priority** | P1 |
| **Type** | Feature |
| **Platform** | Web |
| **Automated** | Partial |
| **Test File** | `tests/Feature/Inventory/CategoryManagementTest.php` (exists, covers model relationships only) |
| **Test Method** | `test_index_renders_tree_view()`, `test_index_renders_flat_view()` (needed) |

**Preconditions:**

1. Organization fixtures set up via `CreatesOrganizationFixtures` trait
2. User is authenticated with `inventory.view` permission
3. Categories exist in the organization with a hierarchy: Root -> Child -> Grandchild (at least 3 levels deep)
4. Categories have correct `lft`, `rgt`, `depth` nested set values

**Test Steps:**

| Step | Action | Expected Result |
|------|--------|-----------------|
| 1 | Send `GET` request to `route('inventory.categories.index')` (default view = `tree`) | Response status 200, Inertia renders `Inventory/Categories/Index` |
| 2 | Assert response contains `categories` prop ordered by `lft` column | Categories sorted in nested set order (preorder traversal) |
| 3 | Assert response contains `viewMode` prop set to `tree` | View mode indicator passed to frontend |
| 4 | Assert response contains `stats` with `total`, `active`, `inactive`, `with_products` counts | Statistics calculated for the organization's categories |
| 5 | Assert response contains `parentOptions` for create/edit dropdown | Parent category select data present |
| 6 | Send `GET` request with `?view=flat` query parameter | Response status 200 |
| 7 | Assert categories are returned as a paginated collection (not a flat array) | Paginator with `data`, `current_page`, `per_page` keys |
| 8 | Assert categories in flat view are ordered by `sort_order` then `name` | Flat view uses sort_order + name ordering, not lft |
| 9 | Send `GET` request with `?search=Electronics` | Only categories matching the search term in `name` or `description` are returned |
| 10 | Send `GET` request with `?status=active` | Only categories with `is_active = true` are returned |

**Test Data:**

```php
$root = Category::factory()->create([
    'organization_id' => $this->organization->id,
    'name' => 'Electronics',
    'slug' => 'electronics',
    'parent_id' => null,
    'lft' => 1,
    'rgt' => 6,
    'depth' => 0,
    'is_active' => true,
]);

$child = Category::factory()->create([
    'organization_id' => $this->organization->id,
    'name' => 'Laptops',
    'slug' => 'laptops',
    'parent_id' => $root->id,
    'lft' => 2,
    'rgt' => 5,
    'depth' => 1,
    'is_active' => true,
]);

$grandchild = Category::factory()->create([
    'organization_id' => $this->organization->id,
    'name' => 'Gaming Laptops',
    'slug' => 'gaming-laptops',
    'parent_id' => $child->id,
    'lft' => 3,
    'rgt' => 4,
    'depth' => 2,
    'is_active' => true,
]);
```

**Postconditions:**

- No database mutations; read-only endpoint
- Tree view returns all categories as a flat array ordered by `lft` (frontend builds the visual tree)
- Flat view returns paginated results (20 per page)
- `products_count` and `active_products_count` are appended via `withCount()`

**Notes:**

- Category uses `HasUuids` trait, so IDs are UUID strings
- The existing `CategoryManagementTest` only tests model relationships (parent/children), not the controller endpoints
- The `forOrganization()` scope ensures data isolation between organizations
- Search uses `LIKE` matching on `name` and `description`, not full-text search

---

### TC-INV-002-02: Create root category sets lft/rgt/depth correctly

| Field | Value |
|-------|-------|
| **ID** | TC-INV-002-02 |
| **Priority** | P1 |
| **Type** | Feature |
| **Platform** | Web |
| **Automated** | No |
| **Test File** | `tests/Feature/Inventory/CategoryCrudTest.php` (needed) |
| **Test Method** | `test_store_root_category_sets_nested_set_values()` (needed) |

**Preconditions:**

1. Organization fixtures set up via `CreatesOrganizationFixtures` trait
2. User is authenticated with `admin.access` permission (category creation is admin-only)
3. No categories exist in the organization (clean slate), or existing categories have known `lft`/`rgt` values
4. `RefreshDatabase` ensures clean state

**Test Steps:**

| Step | Action | Expected Result |
|------|--------|-----------------|
| 1 | Send `POST` request to `route('inventory.categories.store')` with `name = 'Electronics'`, `parent_id = null` | Response redirects to categories index with success flash |
| 2 | Assert category exists in database with `organization_id` matching the user's organization | Category created in correct org |
| 3 | Assert `depth` is 0 | Root category has depth 0 |
| 4 | Assert `lft` and `rgt` are consecutive integers (e.g., `lft = 1`, `rgt = 2` for first root) | Leaf node has `rgt = lft + 1` |
| 5 | Assert `parent_id` is null | No parent for root categories |
| 6 | Create a second root category | Second root category created |
| 7 | Assert second root has `lft = 3`, `rgt = 4` (or `maxRgt + 1`, `maxRgt + 2`) | Second root appended after the first root's range |

**Test Data:**

```php
$categoryData = [
    'name' => 'Electronics',
    'description' => 'All electronic products',
    'parent_id' => null,
    'is_active' => true,
    'color' => '#3B82F6',
];

// For the second root:
$secondCategoryData = [
    'name' => 'Clothing',
    'description' => 'Apparel and accessories',
    'parent_id' => null,
    'is_active' => true,
];
```

**Postconditions:**

- Category record exists with correct nested set values
- `slug` auto-generated from `name` (e.g., `electronics`)
- `path` equals the slug for root categories (e.g., `electronics`)
- `sort_order` is auto-calculated (next available order for siblings)
- The `updateNestedSetAfterInsert()` method adjusts existing `lft`/`rgt` values for all categories in the organization

**Notes:**

- Category creation is wrapped in a `DB::transaction()` to ensure nested set integrity
- The `calculateNestedSetValues()` private method determines lft/rgt/depth based on parent_id
- For root categories (no parent): `lft = max(rgt) + 1`, `rgt = max(rgt) + 2`, `depth = 0`
- The `generateUniqueSlug()` method ensures slug uniqueness within the organization
- Category creation requires `admin.access` middleware

---

### TC-INV-002-03: Create child category under parent sets correct nested set values

| Field | Value |
|-------|-------|
| **ID** | TC-INV-002-03 |
| **Priority** | P1 |
| **Type** | Feature |
| **Platform** | Web |
| **Automated** | No |
| **Test File** | `tests/Feature/Inventory/CategoryCrudTest.php` (needed) |
| **Test Method** | `test_store_child_category_sets_nested_set_values()` (needed) |

**Preconditions:**

1. Organization fixtures set up via `CreatesOrganizationFixtures` trait
2. User is authenticated with `admin.access` permission
3. A root category exists with `lft = 1`, `rgt = 2`, `depth = 0`

**Test Steps:**

| Step | Action | Expected Result |
|------|--------|-----------------|
| 1 | Send `POST` to `route('inventory.categories.store')` with `parent_id` set to the root category's ID | Response redirects with success flash |
| 2 | Assert child category has `depth = 1` | One level below the parent |
| 3 | Assert child has `lft = parent.rgt` (original value), `rgt = parent.rgt + 1` | Inserted at the rightmost position within the parent |
| 4 | Refresh parent from database | Parent's `rgt` has been updated |
| 5 | Assert parent's `rgt` increased by 2 (from 2 to 4) | Parent's range expanded to contain the child |
| 6 | Assert `path` is `parent_slug.child_slug` (e.g., `electronics.laptops`) | Path reflects hierarchy |
| 7 | Create a grandchild under the child category | Third-level category |
| 8 | Assert grandchild has `depth = 2`, and parent chain `rgt` values are correctly adjusted | Entire subtree nested set values are consistent |

**Test Data:**

```php
$parent = Category::factory()->create([
    'organization_id' => $this->organization->id,
    'name' => 'Electronics',
    'slug' => 'electronics',
    'parent_id' => null,
    'lft' => 1,
    'rgt' => 2,
    'depth' => 0,
    'path' => 'electronics',
]);

$childData = [
    'name' => 'Laptops',
    'parent_id' => $parent->id,
    'is_active' => true,
];

// After child insertion:
// Parent: lft=1, rgt=4 (was 2, +2)
// Child:  lft=2, rgt=3, depth=1
// Path:   electronics.laptops
```

**Postconditions:**

- Child category inserted with correct `lft`, `rgt`, `depth` values
- Parent's `rgt` increased by 2
- All sibling categories and their subtrees have adjusted `lft`/`rgt` values
- `path` is dot-separated slug hierarchy (ltree-style)
- `sort_order` is set to the next available order among siblings under the same parent

**Notes:**

- The nested set insert algorithm: set child `lft = parent.rgt`, `rgt = parent.rgt + 1`, then increment all existing nodes where `rgt >= insert_point` by 2, and all `lft > insert_point` by 2
- This is wrapped in a DB transaction to prevent partial updates on concurrent inserts
- The `depth` column is redundant with nested set (can be calculated from ancestors) but stored for query convenience
- Verify tree integrity after insertion: for every category, `rgt > lft`, and the set of all `lft`/`rgt` values across the org forms a valid nested set

---

### TC-INV-002-04: Update category name and slug

| Field | Value |
|-------|-------|
| **ID** | TC-INV-002-04 |
| **Priority** | P1 |
| **Type** | Feature |
| **Platform** | Web |
| **Automated** | No |
| **Test File** | `tests/Feature/Inventory/CategoryCrudTest.php` (needed) |
| **Test Method** | `test_update_category_name_regenerates_slug()` (needed) |

**Preconditions:**

1. Organization fixtures set up via `CreatesOrganizationFixtures` trait
2. User is authenticated with `admin.access` permission
3. A category exists with `name = 'Electronics'`, `slug = 'electronics'`

**Test Steps:**

| Step | Action | Expected Result |
|------|--------|-----------------|
| 1 | Send `PUT` request to `route('inventory.categories.update', $category)` with `name = 'Consumer Electronics'` | Response redirects with success flash |
| 2 | Refresh category from database | Category reloaded |
| 3 | Assert `name` equals `'Consumer Electronics'` | Name updated |
| 4 | Assert `slug` equals `'consumer-electronics'` | Slug regenerated from new name |
| 5 | Assert `description`, `is_active`, `color` remain unchanged if not submitted | Non-submitted fields retain original values |
| 6 | Assert `lft`, `rgt`, `depth` are unchanged (name change does not affect tree structure) | Nested set values preserved |

**Test Data:**

```php
$category = Category::factory()->create([
    'organization_id' => $this->organization->id,
    'name' => 'Electronics',
    'slug' => 'electronics',
    'description' => 'Electronic products',
    'color' => '#3B82F6',
    'is_active' => true,
    'lft' => 1,
    'rgt' => 2,
    'depth' => 0,
]);

$updateData = [
    'name' => 'Consumer Electronics',
    'description' => 'Electronic products', // unchanged
    'is_active' => true,
];
```

**Postconditions:**

- Category `name` and `slug` updated
- Slug is regenerated only when the name changes (checked via `$category->name !== $request->name`)
- `path` is also updated if slug changes (for root categories: path = slug)
- Children's paths are recursively updated via `updateChildrenPaths()` if this category has children
- `updated_at` timestamp refreshed

**Notes:**

- The `generateUniqueSlug()` method accepts an `$excludeId` parameter to prevent false positive uniqueness conflicts with the category being updated
- If the name does not change, the slug is not regenerated (preserving stable URLs)
- Category update is wrapped in a `DB::transaction()`
- Validation rules: `name` required, max 255 chars; `parent_id` nullable, must exist in categories table

---

### TC-INV-002-05: Move category to new parent recalculates lft/rgt/depth for subtree

| Field | Value |
|-------|-------|
| **ID** | TC-INV-002-05 |
| **Priority** | P1 |
| **Type** | Feature |
| **Platform** | Web |
| **Automated** | No |
| **Test File** | `tests/Feature/Inventory/CategoryCrudTest.php` (needed) |
| **Test Method** | `test_move_category_to_new_parent_recalculates_nested_set()` (needed) |

**Preconditions:**

1. Organization fixtures set up via `CreatesOrganizationFixtures` trait
2. User is authenticated with `admin.access` permission
3. Three root categories exist: A, B, C. Category A has a child A1.
4. All have correct nested set values

**Test Steps:**

| Step | Action | Expected Result |
|------|--------|-----------------|
| 1 | Send `POST` request to `route('inventory.categories.move', $categoryA1)` with `new_parent_id = $categoryB->id` | Response redirects with success flash |
| 2 | Refresh all categories from database | Fresh data loaded |
| 3 | Assert A1's `parent_id` is now B's ID | Parent changed |
| 4 | Assert A1's `depth` is 1 (child of B, which is depth 0) | Depth recalculated |
| 5 | Assert A1's `path` is `b-slug.a1-slug` | Path updated to reflect new hierarchy |
| 6 | Assert A's `rgt` decreased (lost a child) | A's range shrunk |
| 7 | Assert B's `rgt` increased (gained a child) | B's range expanded |
| 8 | Move A1 to root (parent_id = null) | A1 becomes a root category |
| 9 | Assert A1's `depth` is 0 and `parent_id` is null | Category is now a root |

**Test Data:**

```php
// Initial tree:
// A (lft=1, rgt=4, depth=0)
//   A1 (lft=2, rgt=3, depth=1)
// B (lft=5, rgt=6, depth=0)
// C (lft=7, rgt=8, depth=0)

$categoryA = Category::factory()->create([
    'organization_id' => $this->organization->id,
    'name' => 'Category A', 'slug' => 'category-a',
    'parent_id' => null, 'lft' => 1, 'rgt' => 4, 'depth' => 0,
    'path' => 'category-a',
]);

$categoryA1 = Category::factory()->create([
    'organization_id' => $this->organization->id,
    'name' => 'Category A1', 'slug' => 'category-a1',
    'parent_id' => $categoryA->id, 'lft' => 2, 'rgt' => 3, 'depth' => 1,
    'path' => 'category-a.category-a1',
]);

$categoryB = Category::factory()->create([
    'organization_id' => $this->organization->id,
    'name' => 'Category B', 'slug' => 'category-b',
    'parent_id' => null, 'lft' => 5, 'rgt' => 6, 'depth' => 0,
    'path' => 'category-b',
]);

$moveData = [
    'new_parent_id' => $categoryB->id,
];
```

**Postconditions:**

- A1's `parent_id`, `depth`, and `path` reflect the new position in the tree
- All `lft`/`rgt` values across the organization's category tree remain consistent
- Children of A1 (if any) have recursively updated `path` and `depth` via `updateChildrenPaths()`
- The move operation is wrapped in a `DB::transaction()`

**Notes:**

- Route: `POST /inventory/categories/{category}/move`
- The current implementation uses a simplified move algorithm (`moveCategory()`) that updates `parent_id`, `path`, and `depth` but may not fully recalculate `lft`/`rgt` for complex moves
- A comment in the code notes: "For production, consider using a package like kalnoy/nestedset"
- Moving a category to be its own descendant should be prevented (circular reference)
- Validation: `new_parent_id` must be nullable or an existing category ID

---

### TC-INV-002-06: Delete leaf category with no children and no products succeeds

| Field | Value |
|-------|-------|
| **ID** | TC-INV-002-06 |
| **Priority** | P1 |
| **Type** | Feature |
| **Platform** | Web |
| **Automated** | No |
| **Test File** | `tests/Feature/Inventory/CategoryCrudTest.php` (needed) |
| **Test Method** | `test_destroy_leaf_category_succeeds()` (needed) |

**Preconditions:**

1. Organization fixtures set up via `CreatesOrganizationFixtures` trait
2. User is authenticated with `admin.access` permission
3. A leaf category exists (no children, no products assigned)
4. Other categories exist in the organization to verify nested set update

**Test Steps:**

| Step | Action | Expected Result |
|------|--------|-----------------|
| 1 | Assert category has no children: `$category->children()->count() === 0` | Precondition verified |
| 2 | Assert category has no products: `$category->products()->count() === 0` | Precondition verified |
| 3 | Send `DELETE` request to `route('inventory.categories.destroy', $category)` | Response redirects to categories index with success flash |
| 4 | Assert category no longer exists in database | `Category::find($category->id)` returns null |
| 5 | Refresh sibling and parent categories | Fresh data loaded |
| 6 | Assert remaining categories have adjusted `lft`/`rgt` values | `updateNestedSetAfterDelete()` decremented affected values |
| 7 | Assert nested set integrity: all `lft`/`rgt` values form a valid tree | No gaps or overlaps in the nested set numbering |

**Test Data:**

```php
// Tree: Parent (lft=1, rgt=6, depth=0)
//         Child1 (lft=2, rgt=3, depth=1)  <-- to delete
//         Child2 (lft=4, rgt=5, depth=1)

$parent = Category::factory()->create([
    'organization_id' => $this->organization->id,
    'name' => 'Parent', 'lft' => 1, 'rgt' => 6, 'depth' => 0,
    'parent_id' => null,
]);

$child1 = Category::factory()->create([
    'organization_id' => $this->organization->id,
    'name' => 'Child 1', 'lft' => 2, 'rgt' => 3, 'depth' => 1,
    'parent_id' => $parent->id,
]);

$child2 = Category::factory()->create([
    'organization_id' => $this->organization->id,
    'name' => 'Child 2', 'lft' => 4, 'rgt' => 5, 'depth' => 1,
    'parent_id' => $parent->id,
]);

// After deleting Child1:
// Parent: lft=1, rgt=4 (was 6, -2)
// Child2: lft=2, rgt=3 (was 4/5, shifted left by 2)
```

**Postconditions:**

- Category record permanently deleted (no soft delete on Category model)
- Sibling categories' `lft`/`rgt` values shifted down by `width` (where `width = rgt - lft + 1` = 2 for a leaf)
- Parent's `rgt` decreased by `width`
- All categories with `lft > deleted.lft` have `lft` decremented by `width`
- All categories with `rgt > deleted.rgt` have `rgt` decremented by `width`

**Notes:**

- The `updateNestedSetAfterDelete()` method handles the lft/rgt adjustment
- Category deletion is wrapped in a `DB::transaction()` (transaction + nested set update + delete)
- Category does NOT use SoftDeletes -- deletion is permanent
- Category creation requires `admin.access` middleware, so deletion likely does too

---

### TC-INV-002-07: Delete category with children fails with error message

| Field | Value |
|-------|-------|
| **ID** | TC-INV-002-07 |
| **Priority** | P1 |
| **Type** | Feature |
| **Platform** | Web |
| **Automated** | No |
| **Test File** | `tests/Feature/Inventory/CategoryCrudTest.php` (needed) |
| **Test Method** | `test_destroy_category_with_children_fails()` (needed) |

**Preconditions:**

1. Organization fixtures set up via `CreatesOrganizationFixtures` trait
2. User is authenticated with `admin.access` permission
3. A parent category exists with at least one child category

**Test Steps:**

| Step | Action | Expected Result |
|------|--------|-----------------|
| 1 | Send `DELETE` request to `route('inventory.categories.destroy', $parentCategory)` | Response redirects back with error flash |
| 2 | Assert error message contains "Cannot delete category that has subcategories" | Specific error message returned |
| 3 | Assert parent category still exists in database | `Category::find($parentCategory->id)` returns the category |
| 4 | Assert child category still exists and is unaffected | Child record unchanged |
| 5 | Assert `lft`/`rgt` values for all categories are unchanged | No nested set modifications on failed delete |

**Test Data:**

```php
$parent = Category::factory()->create([
    'organization_id' => $this->organization->id,
    'name' => 'Parent Category',
    'parent_id' => null,
]);

$child = Category::factory()->create([
    'organization_id' => $this->organization->id,
    'name' => 'Child Category',
    'parent_id' => $parent->id,
]);
```

**Postconditions:**

- No categories deleted
- No nested set values modified
- Error message displayed to the user via session flash errors

**Notes:**

- The controller explicitly checks `$category->children()->count() > 0` before attempting deletion
- The check happens before the `updateNestedSetAfterDelete()` call
- To delete a category with children, the user must first move or delete all children
- This is enforced at the application level, not via database foreign key constraints

---

### TC-INV-002-08: Delete category with products fails with error message

| Field | Value |
|-------|-------|
| **ID** | TC-INV-002-08 |
| **Priority** | P1 |
| **Type** | Feature |
| **Platform** | Web |
| **Automated** | No |
| **Test File** | `tests/Feature/Inventory/CategoryCrudTest.php` (needed) |
| **Test Method** | `test_destroy_category_with_products_fails()` (needed) |

**Preconditions:**

1. Organization fixtures set up via `CreatesOrganizationFixtures` trait
2. User is authenticated with `admin.access` permission
3. A leaf category exists (no children) but has at least one product assigned to it

**Test Steps:**

| Step | Action | Expected Result |
|------|--------|-----------------|
| 1 | Send `DELETE` request to `route('inventory.categories.destroy', $category)` | Response redirects back with error flash |
| 2 | Assert error message contains "Cannot delete category that contains products" | Specific error message returned |
| 3 | Assert category still exists in database | Category not deleted |
| 4 | Assert assigned products are unaffected | Products still reference this category_id |
| 5 | Assert `lft`/`rgt` values unchanged | No nested set modifications |

**Test Data:**

```php
$category = Category::factory()->create([
    'organization_id' => $this->organization->id,
    'name' => 'Category With Products',
    'parent_id' => null,
]);

$product = Product::factory()->create([
    'organization_id' => $this->organization->id,
    'category_id' => $category->id,
    'name' => 'Assigned Product',
]);
```

**Postconditions:**

- Category not deleted
- Product `category_id` still references the category
- No orphaned product records

**Notes:**

- The products check (`$category->products()->count() > 0`) is evaluated before the children check in the controller's `destroy()` method
- To delete this category, the user must first reassign or remove all products from the category
- Soft-deleted products (archived) should also be counted -- otherwise deleting a category with only archived products would leave orphaned references when products are restored

---

### TC-INV-002-09: Category path auto-generated from hierarchy

| Field | Value |
|-------|-------|
| **ID** | TC-INV-002-09 |
| **Priority** | P2 |
| **Type** | Feature |
| **Platform** | Web |
| **Automated** | No |
| **Test File** | `tests/Feature/Inventory/CategoryCrudTest.php` (needed) |
| **Test Method** | `test_category_path_generated_from_hierarchy()` (needed) |

**Preconditions:**

1. Organization fixtures set up via `CreatesOrganizationFixtures` trait
2. User is authenticated with `admin.access` permission
3. A hierarchy of categories exists: Root -> Child -> Grandchild

**Test Steps:**

| Step | Action | Expected Result |
|------|--------|-----------------|
| 1 | Create root category with name "Electronics" | Category created with `path = 'electronics'` |
| 2 | Create child under root with name "Laptops" | Child created with `path = 'electronics.laptops'` |
| 3 | Create grandchild under child with name "Gaming Laptops" | Grandchild created with `path = 'electronics.laptops.gaming-laptops'` |
| 4 | Assert `full_path` accessor on grandchild returns `"Electronics > Laptops > Gaming Laptops"` | Human-readable path built from ancestor chain |
| 5 | Update root category name to "Consumer Electronics" | Root slug changes to `consumer-electronics` |
| 6 | Assert root's `path` updated to `'consumer-electronics'` | Path uses new slug |
| 7 | Assert child's `path` updated to `'consumer-electronics.laptops'` | Recursive path update via `updateChildrenPaths()` |
| 8 | Assert grandchild's `path` updated to `'consumer-electronics.laptops.gaming-laptops'` | Deep recursive update |

**Test Data:**

```php
// Hierarchy:
// Electronics (path: electronics)
//   Laptops (path: electronics.laptops)
//     Gaming Laptops (path: electronics.laptops.gaming-laptops)
//
// After renaming root to "Consumer Electronics":
// Consumer Electronics (path: consumer-electronics)
//   Laptops (path: consumer-electronics.laptops)
//     Gaming Laptops (path: consumer-electronics.laptops.gaming-laptops)
```

**Postconditions:**

- All categories in the subtree have updated `path` values reflecting the new hierarchy
- The `full_path` accessor (`getFullPathAttribute()`) traverses the parent chain and returns a `>` separated human-readable string
- Path uses dot-separated slugs (ltree-style format)

**Notes:**

- The `generatePath()` method builds path from parent's path + current slug
- The `updateChildrenPaths()` method recursively updates all children when a parent's path changes
- The `full_path` accessor loads the parent relationship chain on-demand, which may cause N+1 queries for deep hierarchies
- Path is stored in the `path` column and used for efficient ancestor/descendant lookups

---

### TC-INV-002-10: Category slug auto-generated from name, unique within organization

| Field | Value |
|-------|-------|
| **ID** | TC-INV-002-10 |
| **Priority** | P2 |
| **Type** | Feature |
| **Platform** | Web |
| **Automated** | No |
| **Test File** | `tests/Feature/Inventory/CategoryCrudTest.php` (needed) |
| **Test Method** | `test_category_slug_unique_within_organization()` (needed) |

**Preconditions:**

1. Organization fixtures set up (Org A and Org B)
2. User is authenticated with `admin.access` permission
3. A category named "Electronics" already exists in Org A

**Test Steps:**

| Step | Action | Expected Result |
|------|--------|-----------------|
| 1 | Create a category with name "Electronics" in Org A | First category has `slug = 'electronics'` |
| 2 | Create another category with the same name "Electronics" in Org A | Second category has `slug = 'electronics-1'` (auto-incremented) |
| 3 | Create a third with the same name | `slug = 'electronics-2'` |
| 4 | Create a category with name "Electronics" in Org B (different organization) | Category has `slug = 'electronics'` (uniqueness scoped to org) |
| 5 | Assert slug generation uses `Str::slug()` for formatting | Special characters and spaces converted to hyphens, lowercased |
| 6 | Create a category with name "Gaming & PCs!" | Slug is `gaming-pcs` (special chars stripped) |

**Test Data:**

```php
// Org A: first "Electronics" -> slug: 'electronics'
// Org A: second "Electronics" -> slug: 'electronics-1'
// Org A: third "Electronics" -> slug: 'electronics-2'
// Org B: "Electronics" -> slug: 'electronics' (different org, no conflict)

// Special characters:
// "Gaming & PCs!" -> slug: 'gaming-pcs'
// "Category  With   Spaces" -> slug: 'category-with-spaces'
```

**Postconditions:**

- Each category has a unique slug within its organization
- The `generateUniqueSlug()` method appends an incrementing counter (`-1`, `-2`, ...) when conflicts occur
- Slugs from different organizations can be identical (no cross-org conflict)
- Slug is always lowercase, hyphenated, with special characters stripped (via Laravel `Str::slug()`)

**Notes:**

- The `generateUniqueSlug()` method uses a while loop to find the next available slug suffix
- When updating a category, the `$excludeId` parameter prevents the category from conflicting with its own existing slug
- Slugs are used in the `path` column (ltree format), so slug changes cascade through `updateChildrenPaths()`
- Empty or null names should be rejected by validation before slug generation is attempted
