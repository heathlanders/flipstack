# Order Import Test Cases

## Document ID: TC-FUL-001

**Domain:** Fulfillment
**Related Test Plan:** `docs/testing/plans/fulfillment-test-plan.md`
**Last Updated:** 2026-04-15

---

### TC-FUL-001-01: eBay order import creates order with correct fields

| Field | Value |
|-------|-------|
| **ID** | TC-FUL-001-01 |
| **Priority** | P0 |
| **Type** | Feature |
| **Platform** | API |
| **Automated** | No |
| **Test File** | `tests/Feature/Fulfillment/EbayOrderSyncTest.php` (needed) |
| **Test Method** | `test_import_order_creates_order_with_correct_fields()` (needed) |

**Preconditions:**

1. Organization exists with eBay IntegrationCredential configured (platform = `ebay`, environment = `production`)
2. OAuthToken exists for the credential with a valid (non-expired) access token
3. Team exists under the organization
4. `RefreshDatabase` trait resets state between runs
5. Queue is faked (`Queue::fake()`) to prevent real job dispatch

**Test Steps:**

| Step | Action | Expected Result |
|------|--------|-----------------|
| 1 | Construct `OrderSyncService` with the eBay IntegrationCredential | Service instantiates without error |
| 2 | Call `importOrder()` with a valid eBay order payload containing `orderId`, `buyer.username`, `fulfillmentStartInstructions[0].shippingStep.shipTo`, line items, and pricing summary | Returns array with `created` = true and `order` as an Order model instance |
| 3 | Assert `order.marketplace` equals `'ebay'` | Marketplace field is set to `ebay` |
| 4 | Assert `order.marketplace_order_id` equals the payload `orderId` value | Marketplace order ID stored correctly |
| 5 | Assert `order.buyer_name` equals the payload buyer username | Buyer name extracted from payload |
| 6 | Assert `order.shipping_address_line1`, `shipping_city`, `shipping_state`, `shipping_postal_code` match the `shipTo` address fields | Shipping address parsed and stored |
| 7 | Assert `order.subtotal`, `order.shipping_cost`, `order.tax_amount`, `order.total_amount` match payload `pricingSummary` values | Monetary amounts stored as decimal:2 |
| 8 | Assert `order.items_count` equals the number of `lineItems` in the payload | items_count computed after item sync |
| 9 | Assert `order.status` equals `Order::STATUS_PENDING` | New orders start in pending status |
| 10 | Assert `order.organization_id` matches the credential's organization_id | Order scoped to correct organization |

**Test Data:**

```php
$ebayOrderPayload = [
    'orderId' => '04-12345-67890',
    'buyer' => [
        'username' => 'testbuyer123',
    ],
    'fulfillmentStartInstructions' => [[
        'shippingStep' => [
            'shipTo' => [
                'fullName' => 'Jane Smith',
                'contactAddress' => [
                    'addressLine1' => '456 Oak Avenue',
                    'addressLine2' => 'Suite 200',
                    'city' => 'Portland',
                    'stateOrProvince' => 'OR',
                    'postalCode' => '97201',
                    'countryCode' => 'US',
                ],
            ],
        ],
    ]],
    'pricingSummary' => [
        'priceSubtotal' => ['value' => '49.99', 'currency' => 'USD'],
        'deliveryCost' => ['value' => '5.99', 'currency' => 'USD'],
        'tax' => ['value' => '4.50', 'currency' => 'USD'],
        'total' => ['value' => '60.48', 'currency' => 'USD'],
    ],
    'lineItems' => [[
        'lineItemId' => 'LI-001',
        'title' => 'Vintage Widget',
        'quantity' => 1,
        'lineItemCost' => ['value' => '49.99', 'currency' => 'USD'],
        'sku' => 'VW-001',
    ]],
    'orderPaymentStatus' => 'PAID',
    'creationDate' => '2026-04-14T10:00:00.000Z',
];
```

**Postconditions:**

- One Order record exists in the `orders` table with UUID primary key
- One OrderItem record exists linked to the order
- `order.last_synced_at` is set to approximately now
- `order.credential_id` references the IntegrationCredential used for import

**Notes:**

- The `OrderSyncService` constructor requires an `IntegrationCredential` -- not a user or team
- eBay order IDs follow the format `XX-XXXXX-XXXXX`
- The `transformOrderData()` private method handles the eBay-specific payload structure
- This test does NOT exist yet -- it must be created to match parity with `ShopifyOrderSyncTest`
- The service wraps the import in a `DB::transaction()`

---

### TC-FUL-001-02: Shopify order import creates order with items matched to products by variant_id

| Field | Value |
|-------|-------|
| **ID** | TC-FUL-001-02 |
| **Priority** | P0 |
| **Type** | Feature |
| **Platform** | API |
| **Automated** | Yes |
| **Test File** | `tests/Feature/Fulfillment/ShopifyOrderSyncTest.php` |
| **Test Method** | `test_import_order_matches_product_by_variant_id()` |

**Preconditions:**

1. Organization exists with Shopify IntegrationCredential (platform = `shopify`)
2. `CreatesShopifyFixtures` trait sets up org, team, user, and credential via `setUpShopifyFixtures()`
3. A Product exists in the organization's catalog
4. A ShopifyListing exists linking the product with `shopify_variant_id = 9876543210`
5. Queue is faked

**Test Steps:**

| Step | Action | Expected Result |
|------|--------|-----------------|
| 1 | Create a test product via `createTestProduct()` helper | Product record exists in database |
| 2 | Create a ShopifyListing linking the product with `shopify_variant_id = 9876543210` | Listing record exists with variant mapping |
| 3 | Call `ShopifyOrderSyncService::importOrder()` with a Shopify order payload where `line_items[0].variant_id = 9876543210` | Returns array with `created` = true |
| 4 | Load the order's items | Order has exactly 1 item |
| 5 | Assert `item.product_id` equals the created product's ID | Item linked to correct product via variant_id match |

**Test Data:**

```php
// Shopify order payload generated by CreatesShopifyFixtures::shopifyOrderPayload()
// Key fields:
$payload = [
    'id' => 5551234567890,
    'name' => '#1001',
    'line_items' => [[
        'id' => 11112222333,
        'variant_id' => 9876543210,
        'title' => 'Test Product',
        'quantity' => 1,
        'price' => '29.99',
        'sku' => 'TEST-001',
    ]],
    // ... other Shopify order fields
];
```

**Postconditions:**

- OrderItem.product_id is set to the matching product's UUID
- The match was performed by `shopify_variant_id` lookup on the ShopifyListing table

**Notes:**

- Matching priority: variant_id > SKU (Shopify uses variant_id as primary match)
- If no ShopifyListing matches the variant_id, the service falls back to SKU matching
- Product uses UUID primary keys (`HasUuids` trait)

---

### TC-FUL-001-03: Order import reserves inventory (decrements Product.available_quantity)

| Field | Value |
|-------|-------|
| **ID** | TC-FUL-001-03 |
| **Priority** | P0 |
| **Type** | Feature |
| **Platform** | API |
| **Automated** | Yes |
| **Test File** | `tests/Feature/Fulfillment/ShopifyOrderSyncTest.php` |
| **Test Method** | `test_import_order_reserves_inventory()` |

**Preconditions:**

1. Shopify fixtures set up via `CreatesShopifyFixtures` trait
2. Product exists with `available_quantity = 10`
3. ShopifyListing exists linking the product to a variant_id
4. Queue is faked to capture dispatched jobs

**Test Steps:**

| Step | Action | Expected Result |
|------|--------|-----------------|
| 1 | Create product with `available_quantity = 10` via `createTestProduct(['available_quantity' => 10])` | Product exists with qty 10 |
| 2 | Create ShopifyListing linking product to variant_id | Listing exists |
| 3 | Call `ShopifyOrderSyncService::importOrder()` with order payload (line item qty = 1) | Order created successfully |
| 4 | Refresh the product from the database | Product reloaded with current values |
| 5 | Assert `product.available_quantity` equals 9 | Quantity decremented by order item quantity |
| 6 | Assert `PushInventoryToMarketplaces` job was dispatched via `Queue::assertPushed()` | Inventory sync job queued for affected product |

**Test Data:**

```php
$product = $this->createTestProduct(['available_quantity' => 10]);
// Order line item: quantity = 1
// Expected: 10 - 1 = 9
```

**Postconditions:**

- `Product.available_quantity` is decremented by the total quantity across matched order items
- `PushInventoryToMarketplaces` job dispatched with the affected product's ID (string UUID)
- If multiple items reference different products, each product is decremented independently
- Each affected product ID dispatches a separate `PushInventoryToMarketplaces` job

**Notes:**

- `available_quantity` is the single source of truth for cross-marketplace sell quantity
- The `reserveInventoryForOrder()` method is called only when `created` is true (new orders), not on upsert
- Reservation errors are caught and logged but do NOT fail the order import
- `PushInventoryToMarketplaces` accepts `string $productId` (not the Model) to avoid serialization issues

---

### TC-FUL-001-04: Order import upserts existing order (same marketplace_order_id updates, does not duplicate)

| Field | Value |
|-------|-------|
| **ID** | TC-FUL-001-04 |
| **Priority** | P1 |
| **Type** | Feature |
| **Platform** | API |
| **Automated** | Yes |
| **Test File** | `tests/Feature/Fulfillment/ShopifyOrderSyncTest.php` |
| **Test Method** | `test_import_order_upserts_existing_order()` |

**Preconditions:**

1. Shopify fixtures set up
2. Queue is faked
3. No pre-existing orders in the database

**Test Steps:**

| Step | Action | Expected Result |
|------|--------|-----------------|
| 1 | Call `importOrder()` with a Shopify order payload | Returns `created = true`, order record created |
| 2 | Call `importOrder()` again with the same payload (same Shopify order ID) | Returns `created = false` |
| 3 | Assert the order ID from step 2 matches the order ID from step 1 | Same database record, no duplicate |
| 4 | Assert total Order count in database is 1 | Only one order exists |

**Test Data:**

```php
$payload = static::shopifyOrderPayload();
// payload['id'] is the Shopify order ID (e.g., 5551234567890)
// This becomes marketplace_order_id in the orders table
```

**Postconditions:**

- Exactly 1 Order record exists with the given `marketplace_order_id`
- `last_synced_at` is updated on the second import
- Inventory is NOT re-reserved on the second import (only reserved when `created = true`)

**Notes:**

- The upsert uses `Order::where('marketplace_order_id', $id)->first()` with `withoutGlobalScopes()`
- `withoutGlobalScopes()` is used to bypass the organization scope during lookup -- this is intentional
- On update, `items_count` and `last_synced_at` are refreshed
- Order items are synced (updated/created) but not duplicated

---

### TC-FUL-001-05: Order import dispatches PushInventoryToMarketplaces job

| Field | Value |
|-------|-------|
| **ID** | TC-FUL-001-05 |
| **Priority** | P0 |
| **Type** | Feature |
| **Platform** | API |
| **Automated** | No |
| **Test File** | `tests/Feature/Fulfillment/EbayOrderSyncTest.php` (needed) |
| **Test Method** | `test_import_order_dispatches_push_inventory_job()` (needed) |

**Preconditions:**

1. Organization with eBay IntegrationCredential
2. Product exists with `available_quantity > 0` and an eBay listing matching the order's line item
3. Queue is faked

**Test Steps:**

| Step | Action | Expected Result |
|------|--------|-----------------|
| 1 | Create product with `available_quantity = 5` and a matching EbayListing | Product and listing exist |
| 2 | Call `OrderSyncService::importOrder()` with an eBay order payload referencing the listing's `ebay_listing_id` | Order created, items matched |
| 3 | Assert `PushInventoryToMarketplaces::class` was dispatched via `Queue::assertPushed()` | Job is in the queue |
| 4 | Assert the job was dispatched with the correct product ID (string UUID) | Correct product targeted for sync |

**Test Data:**

```php
$product = Product::factory()->create([
    'organization_id' => $org->id,
    'available_quantity' => 5,
]);

$listing = EbayListing::factory()->create([
    'product_id' => $product->id,
    'organization_id' => $org->id,
    'ebay_listing_id' => '123456789012',
]);

// eBay order payload with lineItems[0].lineItemId referencing this listing
```

**Postconditions:**

- `PushInventoryToMarketplaces` job dispatched once per unique affected product ID
- The job receives a `string $productId`, not a Product model instance
- `Product.available_quantity` decremented before job dispatch

**Notes:**

- If the order has multiple items referencing different products, one job per product is dispatched
- If the order has multiple items referencing the SAME product, only one job is dispatched (deduped via `array_unique`)
- The Shopify variant of this test already exists in `ShopifyOrderSyncTest::test_import_order_reserves_inventory`
- The eBay variant does not yet exist and should be created

---

### TC-FUL-001-06: Amazon order import creates order with Amazon-specific fields

| Field | Value |
|-------|-------|
| **ID** | TC-FUL-001-06 |
| **Priority** | P1 |
| **Type** | Feature |
| **Platform** | API |
| **Automated** | Yes |
| **Test File** | `tests/Feature/Fulfillment/AmazonOrderSyncTest.php` |
| **Test Method** | `test_import_order_creates_order_record()` |

**Preconditions:**

1. Organization exists with Amazon IntegrationCredential (platform = `amazon`)
2. OAuthToken exists for the credential
3. `AmazonOrderSyncTest::setUpAmazonFixtures()` creates org, team, user, credential
4. AmazonListing exists linking a product to an Amazon ASIN/SKU
5. Queue is faked

**Test Steps:**

| Step | Action | Expected Result |
|------|--------|-----------------|
| 1 | Construct `AmazonOrderSyncService` with the Amazon IntegrationCredential | Service instantiated |
| 2 | Call `importOrder()` with an Amazon SP-API order payload | Returns array with `created` = true |
| 3 | Assert `order.marketplace` equals `'amazon'` | Marketplace set correctly |
| 4 | Assert `order.marketplace_order_id` equals the Amazon order ID (e.g., `113-1234567-1234567`) | Amazon order ID format preserved |
| 5 | Assert order items are created with Amazon-specific fields (ASIN, fulfillment channel) | Amazon metadata stored in marketplace_data |
| 6 | Assert `order.status` is `Order::STATUS_PENDING` | Default status for new orders |

**Test Data:**

```php
$amazonOrderPayload = [
    'AmazonOrderId' => '113-1234567-1234567',
    'PurchaseDate' => '2026-04-14T10:00:00Z',
    'OrderStatus' => 'Unshipped',
    'FulfillmentChannel' => 'MFN',
    'ShippingAddress' => [
        'Name' => 'John Buyer',
        'AddressLine1' => '789 Pine St',
        'City' => 'Seattle',
        'StateOrRegion' => 'WA',
        'PostalCode' => '98101',
        'CountryCode' => 'US',
    ],
    'OrderTotal' => ['Amount' => '35.99', 'CurrencyCode' => 'USD'],
    'OrderItems' => [[
        'OrderItemId' => 'AMZ-ITEM-001',
        'ASIN' => 'B0EXAMPLE01',
        'SellerSKU' => 'AMZ-SKU-001',
        'Title' => 'Amazon Test Product',
        'QuantityOrdered' => 1,
        'ItemPrice' => ['Amount' => '29.99', 'CurrencyCode' => 'USD'],
        'ShippingPrice' => ['Amount' => '5.99', 'CurrencyCode' => 'USD'],
    ]],
];
```

**Postconditions:**

- Order record exists with `marketplace = 'amazon'` and UUID primary key
- OrderItems created with Amazon-specific data in `marketplace_data` JSON column
- `items_count` reflects the number of Amazon order items

**Notes:**

- Amazon order IDs follow the format `XXX-XXXXXXX-XXXXXXX`
- Amazon `FulfillmentChannel` values: `MFN` (merchant fulfilled) or `AFN` (Amazon fulfilled / FBA)
- Only `MFN` orders should be imported for fulfillment workflow -- `AFN` orders are fulfilled by Amazon

---

### TC-FUL-001-07: Order import with unknown product SKU still creates order (items unlinked)

| Field | Value |
|-------|-------|
| **ID** | TC-FUL-001-07 |
| **Priority** | P1 |
| **Type** | Feature |
| **Platform** | API |
| **Automated** | No |
| **Test File** | `tests/Feature/Fulfillment/EbayOrderSyncTest.php` (needed) |
| **Test Method** | `test_import_order_with_unknown_sku_creates_unlinked_items()` (needed) |

**Preconditions:**

1. Organization with eBay IntegrationCredential
2. No products exist in the organization's catalog matching the order's SKU or listing ID
3. Queue is faked

**Test Steps:**

| Step | Action | Expected Result |
|------|--------|-----------------|
| 1 | Call `importOrder()` with eBay order payload where the line item SKU does not match any Product and the listing ID does not match any EbayListing | Order created successfully (no exception thrown) |
| 2 | Load the order's items | Items exist with correct title, quantity, and pricing |
| 3 | Assert `item.product_id` is null for all items | Items are unlinked -- no product match |
| 4 | Assert `item.ebay_listing_id` is null | No listing match |
| 5 | Assert no `PushInventoryToMarketplaces` job was dispatched | No inventory to sync when product is unlinked |

**Test Data:**

```php
$payload = [
    'orderId' => '04-99999-11111',
    'lineItems' => [[
        'lineItemId' => 'LI-UNKNOWN',
        'title' => 'Mystery Item',
        'quantity' => 1,
        'lineItemCost' => ['value' => '19.99', 'currency' => 'USD'],
        'sku' => 'NONEXISTENT-SKU-999',
    ]],
    // ... standard eBay order fields
];
```

**Postconditions:**

- Order record exists with `items_count = 1`
- OrderItem exists with `product_id = null` and `ebay_listing_id = null`
- `available_quantity` is unchanged on all products (no reservation performed)
- No `PushInventoryToMarketplaces` job dispatched

**Notes:**

- This is a critical resilience test: order import must NEVER fail due to product matching failure
- Unlinked items can be manually matched later via the order detail UI
- The `matchOrderItemsToProducts()` method logs warnings but does not throw exceptions
- `reserveInventoryForOrder()` skips items where `product_id` is null

---

### TC-FUL-001-08: Organization isolation -- order import only affects correct organization's data

| Field | Value |
|-------|-------|
| **ID** | TC-FUL-001-08 |
| **Priority** | P0 |
| **Type** | Feature |
| **Platform** | API |
| **Automated** | No |
| **Test File** | `tests/Feature/Fulfillment/OrderImportIsolationTest.php` (needed) |
| **Test Method** | `test_order_import_isolated_to_credential_organization()` (needed) |

**Preconditions:**

1. Two separate Organizations exist (Org A and Org B)
2. Each organization has its own IntegrationCredential for eBay
3. Org A has a Product with SKU `WIDGET-001`
4. Org B has a different Product with the same SKU `WIDGET-001`
5. Queue is faked

**Test Steps:**

| Step | Action | Expected Result |
|------|--------|-----------------|
| 1 | Import an eBay order using Org A's credential with line item SKU `WIDGET-001` | Order created under Org A |
| 2 | Assert `order.organization_id` equals Org A's ID | Order scoped to Org A |
| 3 | Assert the order item's `product_id` matches Org A's product (not Org B's) | Product matching respects organization scope |
| 4 | Assert Org A's product `available_quantity` is decremented | Inventory reserved from correct product |
| 5 | Assert Org B's product `available_quantity` is unchanged | No cross-org contamination |
| 6 | Assert `Order::where('organization_id', $orgB->id)->count()` is 0 | No orders created in wrong org |

**Test Data:**

```php
// Org A
$orgA = Organization::factory()->create(['name' => 'Store Alpha']);
$productA = Product::factory()->create([
    'organization_id' => $orgA->id,
    'sku' => 'WIDGET-001',
    'available_quantity' => 10,
]);
$credentialA = IntegrationCredential::factory()->create([
    'organization_id' => $orgA->id,
    'platform' => 'ebay',
]);

// Org B
$orgB = Organization::factory()->create(['name' => 'Store Beta']);
$productB = Product::factory()->create([
    'organization_id' => $orgB->id,
    'sku' => 'WIDGET-001',
    'available_quantity' => 10,
]);
```

**Postconditions:**

- Org A: 1 order, product `available_quantity` = 9
- Org B: 0 orders, product `available_quantity` = 10 (unchanged)
- SKU matching in `matchOrderItemsToProducts()` filters by `organization_id`
- eBay listing matching also filters by `organization_id`

**Notes:**

- The `BelongsToOrganization` trait on Order applies a global scope, but `importOrder()` uses `withoutGlobalScopes()` for the upsert lookup
- Product matching in `matchOrderItemsToProducts()` explicitly filters by `order.organization_id`
- This test is critical for multi-tenant SaaS correctness and should be P0
- The `credential.organization_id` is used to set the order's `organization_id` on creation
