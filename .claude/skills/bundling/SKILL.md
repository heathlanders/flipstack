---
name: bundling
description: Product bundles (fixed and pick-n) with component tracking, availability calculation, and order reservation
user-invocable: false
---

# Product Bundling

## Key Files

| Area | File | Purpose |
|------|------|---------|
| **Models** | `app/Models/Catalog/ProductBundle.php` | Bundle definition linked to a parent product, with type and pick_n config |
| | `app/Models/Catalog/ProductBundleItem.php` | Component line item with quantity, sort order, and required flag |
| **Services** | `app/Services/Catalog/BundleService.php` | Bundle CRUD, availability calculation, component reservation |
| **Controllers** | `app/Http/Controllers/Catalog/BundleController.php` | Full CRUD, add/remove components, show with availability |
| **Routes** | `routes/inventory.php` (bundles prefix) | Bundle management routes |
| **Pages** | `resources/js/Pages/Inventory/Bundles/` | Index, Create, Show Vue pages |

## Bundle Types

| Type | Constant | Description |
|------|----------|-------------|
| `fixed` | `TYPE_FIXED` | All components are included in every sale. Quantity is fixed per component. |
| `pick_n` | `TYPE_PICK_N` | Buyer picks N items from the component list. `pick_n_quantity` defines how many. |

## ProductBundle Fields

| Field | Type | Purpose |
|-------|------|---------|
| `organization_id` | string | Org scope |
| `product_id` | uuid | FK to the parent `Product` that represents this bundle in listings |
| `name` | string | Bundle display name (defaults to product name) |
| `description` | string (nullable) | Optional description |
| `bundle_type` | string | `fixed` or `pick_n` |
| `pick_n_quantity` | integer (nullable) | How many items the buyer picks (only for `pick_n` type) |
| `is_active` | boolean | Active bundles are available for sale |

## ProductBundleItem Fields

| Field | Type | Purpose |
|-------|------|---------|
| `bundle_id` | uuid | FK to ProductBundle |
| `component_product_id` | uuid | FK to the component Product |
| `quantity` | integer | How many units of this component per bundle sale |
| `sort_order` | integer | Display ordering |
| `is_required` | boolean | Whether this component must be included (relevant for pick_n) |

## Bundle-Product Relationship

A bundle is backed by a regular `Product` record (the parent product). When a bundle is created:
1. The parent product's `is_bundle` flag is set to `true`
2. The parent product's `available_quantity` is updated based on component availability
3. When deleted, `is_bundle` is reset to `false`

## Availability Calculation

For a **fixed** bundle, available quantity = `MIN(component.available_quantity / component.quantity)` across all components.

The `calculateAvailableQuantity()` method on `ProductBundle` computes this. After any component stock change, `BundleService::updateBundleAvailability()` recalculates and updates the parent product's `available_quantity`.

## Order Reservation

When a bundle product is sold, `BundleService::reserveComponentsForOrder(productId, orderQuantity)`:
1. Loads the bundle and its component items
2. For each component: decrements `componentProduct.available_quantity` by `item.quantity * orderQuantity`
3. Recalculates and updates the bundle's parent product availability

This ensures each component's stock is properly decremented when a bundle is purchased.

## Creation Flow

`BundleService::create()` in a database transaction:
1. Finds the parent product, sets `is_bundle = true`
2. Creates the `ProductBundle` record
3. Creates `ProductBundleItem` records for each component (with sort_order from array index)
4. Calls `updateBundleAvailability()` to set initial available quantity
5. Returns the bundle with items and product loaded

## Routes

| Method | URI | Name | Action |
|--------|-----|------|--------|
| GET | `/inventory/bundles` | `inventory.bundles.index` | List bundles with search, stats |
| GET | `/inventory/bundles/create` | `inventory.bundles.create` | Create form (loads non-bundle products) |
| POST | `/inventory/bundles` | `inventory.bundles.store` | Store bundle with components |
| GET | `/inventory/bundles/{bundle}` | `inventory.bundles.show` | Show bundle with components and availability |
| PUT | `/inventory/bundles/{bundle}` | `inventory.bundles.update` | Update name, description, active status |
| POST | `/inventory/bundles/{bundle}/components` | `inventory.bundles.add-component` | Add a component product |
| DELETE | `/inventory/bundles/{bundle}/components/{item}` | `inventory.bundles.remove-component` | Remove a component |
| DELETE | `/inventory/bundles/{bundle}` | `inventory.bundles.destroy` | Delete bundle and reset product flag |

## Validation Rules

- `product_id`: required, uuid, must exist in products table
- `name`: required, string, max 255
- `description`: nullable, string, max 2000
- `bundle_type`: required, `fixed` or `pick_n`
- `pick_n_quantity`: nullable, integer, min 1
- `components`: required array with at least 1 item
- `components.*.product_id`: required, uuid, must exist
- `components.*.quantity`: required, integer, min 1
- `components.*.is_required`: boolean

## Stock Sync with Marketplace Push

When bundle availability changes (via `updateBundleAvailability()`), the parent product's `available_quantity` is updated. This integrates with the existing `PushInventoryToMarketplaces` job -- any process that dispatches that job after product quantity changes will automatically push the bundle's availability to connected marketplaces.

## Business Rules

1. A product can only back one bundle (one-to-one via `product_id`)
2. Only non-bundle products (`is_bundle = false`) can be selected as the parent product or components
3. Component sort order is assigned from array index during creation
4. Adding/removing components triggers availability recalculation
5. Deleting a bundle also deletes all its items and resets the parent product's `is_bundle` flag
6. The create form limits product selection to 500 products (ordered by name)

## Bundle Stats

`BundleService::getStats()` returns:
- `total`: Total bundles for the organization
- `active`: Count of active bundles

## Debugging Commands

```php
// List all bundles with component counts
ProductBundle::withoutGlobalScopes()
  ->where('organization_id', $orgId)
  ->withCount('items')
  ->with('product:id,name,sku,available_quantity')
  ->get(['id', 'product_id', 'name', 'bundle_type', 'is_active']);

// Bundle components with stock levels
ProductBundleItem::where('bundle_id', $bundleId)
  ->with('componentProduct:id,name,sku,available_quantity,stock_quantity')
  ->orderBy('sort_order')
  ->get(['id', 'component_product_id', 'quantity', 'is_required']);

// Check bundle availability calculation
$bundle = ProductBundle::with('items.componentProduct')->find($bundleId);
$available = $bundle->calculateAvailableQuantity();
dump("Bundle available: {$available}, Product available_quantity: {$bundle->product->available_quantity}");

// Find bundles with availability mismatch
ProductBundle::withoutGlobalScopes()
  ->where('organization_id', $orgId)
  ->where('is_active', true)
  ->with(['product:id,available_quantity', 'items.componentProduct:id,available_quantity'])
  ->get()
  ->filter(function ($bundle) {
      return $bundle->calculateAvailableQuantity() !== (int) $bundle->product->available_quantity;
  })
  ->map(fn ($b) => ['id' => $b->id, 'name' => $b->name, 'calculated' => $b->calculateAvailableQuantity(), 'stored' => $b->product->available_quantity]);

// Products marked as bundles without a bundle record
Product::where('organization_id', $orgId)
  ->where('is_bundle', true)
  ->whereDoesntHave('bundle')
  ->get(['id', 'name', 'sku']);
```

## Audit Logging

| Action | Category | Severity | Metadata keys |
|--------|----------|----------|---------------|
| `bundle.created` | product | info | bundle_type, component_count, source |
| `bundle.updated` | product | info | changes |
| `bundle.deleted` | product | warning | bundle_name |
| `bundle.component_swapped` | product | info | old_product_id, new_product_id |
| `bundle.assembled` | inventory | info | order_id, quantity |

Trait: models in this feature use `LogsAudit` (`app/Traits/LogsAudit.php`) for auto CRUD diff. Domain lifecycle events emitted from `app/Services/Catalog/BundleService`, `ProductBundle` model (`LogsAudit`) via `UserActivityLog::log(action, desc, $user, $metadata, $severity, $category)`.

No direct KPI consumer (bundle changes are infrequent); audit drives the Admin Audit Hub entity timeline.

Spec: `docs/AUDIT_SYSTEM.md`. Enforced rule: `.claude/rules/audit-logging.md`. Tests: `use Tests\Support\AssertsAudits;` then `$this->assertAudited('action', $user, [...])`.
