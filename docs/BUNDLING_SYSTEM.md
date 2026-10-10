# Product Bundling System

## Overview

The bundling system allows organizations to sell multiple products as a single listing. A bundle is a `Product` record (with `is_bundle = true`) that contains component products with specified quantities. The system calculates bundle availability as the minimum number of complete bundles that can be assembled from component stock, and reserves component inventory when bundle orders are placed.

---

## Architecture

### Key Files

| File | Purpose |
|------|---------|
| `app/Models/Catalog/ProductBundle.php` | Bundle definition model, linked to a parent Product |
| `app/Models/Catalog/ProductBundleItem.php` | Component product with quantity and sort order |
| `app/Services/Catalog/BundleService.php` | Bundle CRUD, availability calculation, component reservation |
| `app/Http/Controllers/Catalog/` | Bundle management controllers |

---

## Data Model

### `product_bundles`

| Column | Type | Description |
|--------|------|-------------|
| `id` | uuid | Primary key |
| `organization_id` | uuid | FK to organizations |
| `product_id` | uuid | FK to products (the bundle's parent listing) |
| `name` | string | Bundle display name |
| `description` | text | Bundle description (nullable) |
| `bundle_type` | string | `fixed` or `pick_n` |
| `pick_n_quantity` | integer | For pick_n type: how many items the buyer chooses |
| `is_active` | boolean | Whether the bundle is active |

### `product_bundle_items`

| Column | Type | Description |
|--------|------|-------------|
| `id` | uuid | Primary key |
| `bundle_id` | uuid | FK to product_bundles |
| `component_product_id` | uuid | FK to products (the component) |
| `quantity` | integer | Units of this component per bundle |
| `sort_order` | integer | Display ordering |
| `is_required` | boolean | Whether the component is mandatory (for pick_n bundles) |

### Relationship to Product

A bundle is backed by a regular `Product` record. The `Product.is_bundle` flag marks it as a bundle. The `ProductBundle` record holds the bundle configuration, and `ProductBundleItem` records define the components. This means bundles appear in product listings, can have eBay/Shopify listings, and participate in the normal inventory sync flow.

---

## Key Flows

### Bundle Types

**Fixed Bundle** (`bundle_type = 'fixed'`): Every component is included in every sale. Availability is determined by the most constrained component.

**Pick N Bundle** (`bundle_type = 'pick_n'`): The buyer selects `pick_n_quantity` items from the available components. Components marked `is_required = true` are always included; the buyer picks from the remaining optional components.

### Availability Calculation

Bundle availability = MIN(floor(component.available_quantity / component.quantity)) across all components.

```
Example:
  Component A: available_quantity = 10, quantity_per_bundle = 2  -> 5 bundles
  Component B: available_quantity = 7,  quantity_per_bundle = 1  -> 7 bundles
  Bundle availability = MIN(5, 7) = 5
```

This is computed by `ProductBundle::calculateAvailableQuantity()` and written to the parent `Product.available_quantity` by `BundleService::updateBundleAvailability()`.

### Bundle Creation

```
BundleService::create(data, organizationId)
    |
    DB transaction:
        1. Set Product.is_bundle = true on the parent product
        2. Create ProductBundle record
        3. Create ProductBundleItem records for each component
        4. Calculate and set bundle availability on parent Product
    |
    Return bundle with items and product loaded
```

### Component Reservation on Order

When an order containing a bundle product is placed:

```
BundleService::reserveComponentsForOrder(productId, orderQuantity)
    |
    Load ProductBundle with items and component products
    |
    For each component item:
        componentQty = item.quantity * orderQuantity
        componentProduct.decrementAvailableQuantity(componentQty)
    |
    Recalculate and update bundle availability
```

This integrates with the inventory reservation flow described in `docs/INVENTORY_SYNC_SYSTEM.md`. After component quantities are decremented, the `PushInventoryToMarketplaces` job pushes updated quantities to all marketplace listings for each affected component product.

### Stock Sync Integration

Because the bundle's parent `Product.available_quantity` is kept in sync by `updateBundleAvailability()`, the normal marketplace inventory push mechanism works for bundles without special handling. When any component's stock changes (from any order, not just bundle orders), the bundle availability should be recalculated.

---

## Integration Points

- **Inventory Sync**: Bundle availability is stored on `Product.available_quantity`, so `PushInventoryToMarketplaces` pushes the correct bundle quantity to eBay/Shopify listings automatically.
- **Order Processing**: `BundleService::reserveComponentsForOrder()` should be called during order import when the ordered product is a bundle. This decrements component stock rather than the bundle product's stock directly.
- **Marketplace Listings**: Bundles are regular products with marketplace listings. The bundle's `available_quantity` reflects assemblable units.
- **Routes**: Bundle management routes are in `routes/inventory.php`.
- **Frontend**: `resources/js/Pages/Inventory/` contains bundle management views.
- **Organization Scoping**: `ProductBundle` uses `BelongsToOrganization` trait. All queries are scoped to the current organization.
