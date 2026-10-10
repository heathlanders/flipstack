# Product Lifecycle Overview

End-to-end map of a product from intake capture to pick/pack/ship. Use this as the entry point;
each stage links to the deeper doc that owns it.

## Stages and field handoffs

```
[1] INTAKE          -> [2] AI RESEARCH  -> [3] PRODUCT CREATE -> [4] LISTING SYNC -> [5] ORDER IMPORT -> [6] PICK / PACK / SHIP
ProductIntakeQueue     enriched_data       Product               Ebay/Shopify       Order/OrderItem    Shipment
  quick_quantity         merged_product_     name, sku            Listing             location_id        InventoryLevel
  quick_location_id  >>> fields           >> available_quantity >>(qty pushed)    >> reserved_qty   >> .bin_id
  quick_bin_id           item_specifics      cost/selling price                       (FulfillmentRouter   (pick UI displays
  quick_location         category_id         component_breakdown                       chooses location)    bin/aisle/rack/shelf)
  (legacy freetext)
```

| Stage | Owns | Reads | Writes |
|---|---|---|---|
| 1. Intake | `ProductIntakeQueue` | user form (Vue picker) | `quick_location_id`, `quick_bin_id`, `quick_quantity`, photos, identifiers |
| 2. AI research | `ProcessQueueItem` job | barcode/photos | `enriched_data.research_data`, `merged_product_fields` |
| 3. Product create | `IntakeQueueController::publish/complete` | queue item + research | `Product`, **InventoryLevel.location_id + bin_id** (via `seedProductInventory`) |
| 4. Listing sync | `CreateEbayListingJob`, Shopify push | Product, available_quantity | EbayListing/ShopifyListing |
| 5. Order import | `OrderSyncService` | marketplace API | `Order`, `OrderItem` (FulfillmentRouter sets `Order.location_id`) |
| 6. Pick / pack / ship | `PickController`, `PackController`, `ShipController`, `FulfillmentPrintService` | `InventoryLevel` at `Order.location_id` (bin first, location code fallback) | `Shipment`, `pick_status` |

## Where physical location lives

`InventoryLevel.location_id` and `InventoryLevel.bin_id` are the single source of truth for "where in
the warehouse is this item." `Product.warehouse_location_id` exists in the schema as a legacy
`bigint` FK to a deprecated `warehouse_locations` table — not used anywhere live. Do not write to it.

Intake captures `quick_location_id` (UUID FK to `inventory_locations`) and `quick_bin_id` (UUID FK
to `warehouse_bins`). When the queue item is published into a `Product`, `seedProductInventory()`
seeds an `inventory_levels` row with both. The pick path reads back from `inventory_levels` filtered
by `Order.location_id` (set by `FulfillmentRouter` at order import).

## Per-stage docs

- Intake: [`INTAKE_QUEUE_SYSTEM.md`](INTAKE_QUEUE_SYSTEM.md)
- Listing: [`LISTING_MANAGEMENT.md`](LISTING_MANAGEMENT.md), [`EBAY_LISTING_SERVICE.md`](EBAY_LISTING_SERVICE.md)
- Inventory sync: [`INVENTORY_SYNC_SYSTEM.md`](INVENTORY_SYNC_SYSTEM.md), [`PRICING_AND_INVENTORY_SYNC.md`](PRICING_AND_INVENTORY_SYNC.md)
- Fulfillment: [`FULFILLMENT_WORKFLOW.md`](FULFILLMENT_WORKFLOW.md), [`FULFILLMENT_ROUTING.md`](FULFILLMENT_ROUTING.md)

## Common gotcha

If a product was published before 2026-05-05, its intake row likely only has the legacy freetext
`quick_location` (no FK). `seedProductInventory()` defaults to the org's first active inventory
location in that case (and logs an info-level warning). The pick UI still shows that fallback
location — pickers will see the right warehouse, but bin granularity is lost.

To bin-assign legacy products: open the product and adjust `inventory_levels.bin_id` directly, or
re-publish via the wizard (Step 1 has the cascading Location → Bin picker).
