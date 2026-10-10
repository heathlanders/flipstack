# Shopify API Reference and Enterprise Roadmap

API capabilities currently implemented, gaps for enterprise-level coverage, and the roadmap for additions needed by the ecommerce SaaS platform.

## Current API Coverage

### REST Admin API (2026-01)

| Resource | Read | Write | Delete | Notes |
|----------|------|-------|--------|-------|
| Products | Full | Full | Full | Single variant, create/update/archive/delete |
| Inventory Items | Full | - | - | Get item metadata |
| Inventory Levels | Full | Full | - | Set absolute, adjust delta |
| Locations | Full | - | - | Fetch all locations |
| Orders | Full | - | - | List, get, count, close, cancel |
| Fulfillments | Partial | Full | - | Create, update tracking |
| Fulfillment Orders | Full | - | - | Get for order |
| Custom Collections | Full | Full | - | Create, add products |
| Smart Collections | Full | - | - | List only |
| Metafields | Full | Full | Full | Any resource type |
| Webhooks | Full | Full | Full | 7 topics registered |
| Shop | Full | - | - | Shop info for auth verification |
| OAuth | - | Full | - | Token exchange |

### Current OAuth Scopes

```
read_products, write_products, read_inventory, write_inventory,
read_orders, read_locations, write_fulfillments
```

---

## Enterprise Gaps and Roadmap

### Priority 1: Critical for Multi-Channel Reselling

#### 1.1 Customer Management

**Why**: Needed for order fulfillment, CRM sync, and customer-aware pricing.

**Scope Needed**: `read_customers, write_customers`

**Endpoints to Implement**:
| Endpoint | Method | Purpose |
|----------|--------|---------|
| `/customers` | GET | List/search customers |
| `/customers/{id}` | GET | Get customer details |
| `/customers` | POST | Create customer |
| `/customers/{id}` | PUT | Update customer |
| `/customers/search` | GET | Search by query string |
| `/customers/{id}/orders` | GET | Get customer's order history |

**Service**: `ShopifyCustomerService extends ShopifyBaseService`

#### 1.2 Draft Orders

**Why**: Needed for B2B/wholesale workflows, custom orders, manual order creation.

**Scope Needed**: `read_draft_orders, write_draft_orders`

**Endpoints to Implement**:
| Endpoint | Method | Purpose |
|----------|--------|---------|
| `/draft_orders` | GET | List draft orders |
| `/draft_orders/{id}` | GET | Get draft order |
| `/draft_orders` | POST | Create draft order |
| `/draft_orders/{id}` | PUT | Update draft order |
| `/draft_orders/{id}` | DELETE | Delete draft order |
| `/draft_orders/{id}/complete` | PUT | Convert to real order |
| `/draft_orders/{id}/send_invoice` | POST | Email invoice to customer |

**Service**: `ShopifyDraftOrderService extends ShopifyBaseService`

#### 1.3 Multi-Location Inventory

**Why**: Current implementation uses single location. Enterprise needs multi-warehouse.

**Current Gap**: `ShopifyMarketplaceAdapter::resolveLocationId()` always uses first/cached location.

**Changes Needed**:
- Map internal `InventoryLocation` to Shopify locations
- Support setting inventory per-location (not just primary)
- Handle location-specific fulfillment routing
- Add `shopify_location_id` to `InventoryLocation` model or mapping table

**Endpoints Already Available** (need multi-location logic):
| Endpoint | Method | Purpose |
|----------|--------|---------|
| `/locations` | GET | List all locations |
| `/locations/{id}` | GET | Get location details |
| `/inventory_levels/set` | POST | Set at specific location |
| `/inventory_levels/adjust` | POST | Adjust at specific location |
| `/inventory_levels/connect` | POST | Connect item to location |

#### 1.4 Order Write Operations

**Why**: Need to create orders from other channels, update fulfillment status bidirectionally.

**Scope Needed**: `write_orders`

**Endpoints to Implement**:
| Endpoint | Method | Purpose |
|----------|--------|---------|
| `/orders` | POST | Create order (from eBay/other channel) |
| `/orders/{id}` | PUT | Update order |
| `/orders/{id}/transactions` | POST | Create payment transaction |
| `/orders/{id}/risks` | GET | Get fraud analysis |

**Service**: Extend `ShopifyOrderService`

---

### Priority 2: Advanced Product Management

#### 2.1 Product Variants (Multi-Variant)

**Why**: Current implementation creates single-variant products. Enterprise needs size/color/material variants.

**Current Gap**: `toShopifyProductData()` creates one variant from Product model.

**Changes Needed**:
- Support `ProductVariant` model (or Product children)
- Map variant options (size, color, material) to Shopify options
- Create multiple variants per product
- Handle variant-specific inventory, pricing, images

**Endpoints to Implement**:
| Endpoint | Method | Purpose |
|----------|--------|---------|
| `/products/{id}/variants` | GET | List variants |
| `/products/{id}/variants` | POST | Create variant |
| `/variants/{id}` | PUT | Update variant |
| `/variants/{id}` | DELETE | Delete variant |

#### 2.2 Product Images (Enhanced)

**Why**: Current implementation sends image URLs. Need image management.

**Endpoints to Implement**:
| Endpoint | Method | Purpose |
|----------|--------|---------|
| `/products/{id}/images` | GET | List product images |
| `/products/{id}/images` | POST | Upload/add image |
| `/products/{id}/images/{id}` | PUT | Update image (position, alt) |
| `/products/{id}/images/{id}` | DELETE | Delete image |

**Service**: `ShopifyImageService extends ShopifyBaseService` or extend `ShopifyProductService`

#### 2.3 Product Tags and Organization

**Why**: Needed for store navigation, marketing, and filtering.

**Current Gap**: Tags not mapped in `toShopifyProductData()`.

**Changes Needed**:
- Map Product tags/labels to Shopify `tags` field (comma-separated)
- Support tag-based smart collections
- Add tag sync to product update flow

---

### Priority 3: Pricing and Discounts

#### 3.1 Price Rules and Discounts

**Why**: Enterprise pricing requires automated discounts, sales, and promotions.

**Scope Needed**: `read_price_rules, write_price_rules`

**Endpoints to Implement**:
| Endpoint | Method | Purpose |
|----------|--------|---------|
| `/price_rules` | GET/POST | Manage price rules |
| `/price_rules/{id}` | GET/PUT/DELETE | CRUD on rules |
| `/price_rules/{id}/discount_codes` | GET/POST | Manage discount codes |
| `/price_rules/{id}/discount_codes/{id}` | GET/PUT/DELETE | CRUD on codes |

**Service**: `ShopifyDiscountService extends ShopifyBaseService`

#### 3.2 Markets (International)

**Why**: Multi-currency and international selling.

**Scope Needed**: `read_markets`

**Endpoints to Implement**:
| Endpoint | Method | Purpose |
|----------|--------|---------|
| `/markets` | GET | List markets |
| `/markets/{id}` | GET | Get market details |

---

### Priority 4: Shipping and Fulfillment

#### 4.1 Shipping Zones and Rates

**Why**: Needed for accurate shipping cost calculation.

**Scope Needed**: `read_shipping`

**Endpoints to Implement**:
| Endpoint | Method | Purpose |
|----------|--------|---------|
| `/shipping_zones` | GET | List shipping zones and rates |

#### 4.2 Carrier Services

**Why**: Custom shipping rate calculation for multi-channel.

**Scope Needed**: `write_shipping`

**Endpoints to Implement**:
| Endpoint | Method | Purpose |
|----------|--------|---------|
| `/carrier_services` | GET/POST | Register carrier service |
| `/carrier_services/{id}` | GET/PUT/DELETE | Manage carrier |
| Callback URL | POST | Calculate rates at checkout |

**Service**: `ShopifyShippingService extends ShopifyBaseService`

#### 4.3 Fulfillment Services

**Why**: Third-party fulfillment (3PL) integration.

**Endpoints to Implement**:
| Endpoint | Method | Purpose |
|----------|--------|---------|
| `/fulfillment_services` | GET/POST | Manage fulfillment services |
| `/fulfillment_services/{id}` | GET/PUT/DELETE | CRUD |

---

### Priority 5: Analytics and Reporting

#### 5.1 Reports and Analytics

**Scope Needed**: `read_reports, read_analytics`

**Endpoints to Implement**:
| Endpoint | Method | Purpose |
|----------|--------|---------|
| `/reports` | GET | List available reports |
| `/reports/{id}` | GET | Get report details |

#### 5.2 Events and Activity

**Endpoints to Implement**:
| Endpoint | Method | Purpose |
|----------|--------|---------|
| `/events` | GET | List shop events |
| `/products/{id}/events` | GET | Product-specific events |
| `/orders/{id}/events` | GET | Order-specific events |

---

### Priority 6: GraphQL Bulk Operations

**Why**: Current implementation uses REST pagination for imports. GraphQL bulk operations are more efficient for large stores (10k+ products).

**Approach**:
- Use Shopify GraphQL Admin API for bulk queries
- `bulkOperationRunQuery` mutation starts async bulk operation
- Poll `currentBulkOperation` query for completion
- Download JSONL result file from `url` field

**Service**: `ShopifyBulkOperationService`

**Key Mutations**:
```graphql
mutation {
  bulkOperationRunQuery(query: """
    { products { edges { node { id title variants { edges { node { id sku inventoryQuantity } } } } } }
  """) {
    bulkOperation { id status }
    userErrors { field message }
  }
}
```

---

## Implementation Priority Matrix

| Feature | Business Impact | Effort | Priority |
|---------|----------------|--------|----------|
| Multi-Location Inventory | High | Medium | P1 |
| Order Write Operations | High | Medium | P1 |
| Customer Management | High | Low | P1 |
| Draft Orders | Medium | Medium | P1 |
| Product Variants | High | High | P2 |
| Enhanced Images | Medium | Low | P2 |
| Product Tags | Medium | Low | P2 |
| Discounts/Price Rules | Medium | Medium | P3 |
| International Markets | Medium | Low | P3 |
| Shipping Zones | Low | Low | P4 |
| Carrier Services | Low | Medium | P4 |
| Fulfillment Services | Low | Medium | P4 |
| Reports/Analytics | Low | Low | P5 |
| GraphQL Bulk Operations | High | High | P6 |

---

## Webhook Topics - Current vs Enterprise

### Currently Registered (7)

```
products/create, products/update, products/delete,
orders/create, orders/updated,
inventory_levels/update,
app/uninstalled
```

### Enterprise Additions Needed

| Topic | Priority | Purpose |
|-------|----------|---------|
| `orders/cancelled` | P1 | Release reserved inventory |
| `orders/fulfilled` | P1 | Update fulfillment status |
| `orders/paid` | P2 | Payment confirmation trigger |
| `refunds/create` | P1 | Handle returns, restore inventory |
| `customers/create` | P2 | CRM sync |
| `customers/update` | P2 | CRM sync |
| `inventory_items/create` | P2 | New inventory items |
| `inventory_items/update` | P2 | Item metadata changes |
| `fulfillments/create` | P2 | External fulfillment tracking |
| `fulfillments/update` | P2 | Tracking updates |
| `collections/create` | P3 | Collection sync |
| `collections/update` | P3 | Collection sync |
| `draft_orders/create` | P3 | Draft order workflow |
| `draft_orders/update` | P3 | Draft order workflow |

### Scope Additions Needed for Enterprise

Current scopes:
```
read_products, write_products, read_inventory, write_inventory,
read_orders, read_locations, write_fulfillments
```

Add for enterprise:
```
read_customers, write_customers,          # Customer management
write_orders,                              # Order creation
read_draft_orders, write_draft_orders,     # Draft orders
read_price_rules, write_price_rules,       # Discounts
read_shipping, write_shipping,             # Shipping
read_reports, read_analytics,              # Reporting
read_fulfillments,                         # Read fulfillments
read_assigned_fulfillment_orders,          # Multi-location fulfillment
write_assigned_fulfillment_orders,         # Multi-location fulfillment
```

---

## API Version Strategy

- Current: `2026-01`
- Shopify releases new API versions quarterly (January, April, July, October)
- Each version is supported for ~12 months after release
- Version is centralized in `ShopifyConfigService::getApiVersion()`
- Update via `SHOPIFY_API_VERSION` env variable
- Test against new versions before upgrading

---

## GraphQL vs REST

### When to Use GraphQL

| Use Case | Reason |
|----------|--------|
| Bulk operations (10k+ items) | Single query, async processing |
| Complex nested queries | Reduce API calls |
| Selective field fetching | Reduce response size |
| Mutations with error handling | Structured userErrors |

### When to Keep REST

| Use Case | Reason |
|----------|--------|
| Simple CRUD operations | Simpler implementation |
| Webhook registration | REST-only |
| OAuth flow | REST-only |
| Rate limit is not an issue | REST is fine for low-volume |

### Migration Path

1. Keep REST for existing services (stable, tested)
2. Add GraphQL for new bulk/complex operations
3. Gradually migrate high-volume operations to GraphQL
4. Use `ShopifyGraphQLService extends ShopifyBaseService` as new base
