# Order Fulfillment Workflow

How orders flow through FlipStack from marketplace purchase to delivery (and returns).

## Overview

```
Marketplace Order
       |
       v
  ORDER IMPORT -----> Orders automatically sync every 5 minutes
       |                Inventory reserved immediately
       v
  ORDER MANAGEMENT --> View, filter, assign to warehouse location
       |                Set priority (Standard / High / Urgent)
       v
     PICK -----------> Walk the warehouse, pick items from shelves
       |                Mark items picked or short (unavailable)
       v
     PACK -----------> Enter package dimensions, shop shipping rates
       |                Purchase label, print packing slip
       v
     SHIP -----------> Confirm shipment, tracking auto-uploaded
       |                to eBay / Shopify / Amazon
       v
  TRACKING ----------> Carrier sends updates automatically
       |                In Transit -> Out for Delivery -> Delivered
       v
  DELIVERED            Order complete
       |
       v (if needed)
   RETURNS ----------> Create RMA, receive, inspect, refund, restock
```

---

## 1. Order Import

Orders sync automatically from connected marketplaces every 5 minutes. No manual action is needed.

**Supported marketplaces:** eBay, Shopify, Amazon

**What gets imported:**
- Buyer name, email, phone
- Full shipping address
- All line items (SKU, title, quantity, price, condition)
- Order totals (subtotal, shipping, tax, fees, total)
- Ship-by date (from marketplace handling time)

**What happens automatically:**
- Each order is matched to products in your catalog (by listing ID or SKU)
- Inventory is **immediately reserved** -- `available_quantity` is decremented on each matched product
- The updated quantity is pushed to all connected marketplace listings so you don't oversell

**Order sources:**
| Marketplace | Sync Method | Frequency |
|-------------|------------|-----------|
| eBay | Scheduled API poll | Every 5 minutes |
| Shopify | Scheduled poll + real-time webhooks | Every 5 min + instant on new order |
| Amazon | Scheduled API poll | Every 5 minutes |

New orders arrive with status **Pending**.

---

## 2. Order Management

**Navigate to:** Fulfillment > Orders

### Viewing Orders

The orders page shows all orders with filters for:
- **Status** -- Pending, Assigned, Picking, Packed, Shipped, etc.
- **Marketplace** -- eBay, Shopify, Amazon
- **Priority** -- Standard, High, Urgent
- **Location** -- Which warehouse/location
- **Date range** -- When the order was placed
- **Search** -- By order number, buyer name, or SKU

### Priority System

| Priority | When to Use |
|----------|-------------|
| **Standard** | Normal orders, no rush |
| **High** | Orders with tight ship-by dates |
| **Urgent** | Overdue or escalated orders |

Priority can be changed on any order. Higher priority orders appear first in pick/pack/ship queues.

### Assigning Orders

Before an order can be picked, it must be assigned to a warehouse location.

**Manual assignment:**
1. Select one or more orders
2. Choose a ship-from location from the dropdown
3. Click Assign

**Auto-assignment** (if enabled in Settings):
- Pending orders are automatically assigned to locations every 10 minutes
- Assignment is based on location rules and order priority
- Enable this in Fulfillment > Settings > Workflow

Once assigned, the order status changes to **Assigned** and appears in the Pick queue.

### Bulk Actions
- **Bulk Assign** -- Assign multiple orders to a location at once
- **Bulk Priority** -- Change priority on multiple orders
- **Hold** -- Place an order on hold (removes from pick/pack/ship queues)
- **Release** -- Remove hold and return to active queue
- **Cancel** -- Cancel an order (releases reserved inventory)

### Order Detail

Click any order to see full details:
- Buyer and shipping information
- All line items with product links
- Shipment history and tracking
- Return requests
- Complete audit trail (every action taken on the order, by whom, and when)

---

## 3. Pick

**Navigate to:** Fulfillment > Pick

The pick queue shows all orders that are **Assigned** or currently being **Picked**, sorted by priority and ship-by date.

### Picking an Order

1. **Start Picking** -- Click "Start Pick" on an order. Status changes to **Picking** and you're assigned as the picker.

2. **Pick Each Item** -- For each item in the order:
   - **Pick** -- Item found and pulled from the shelf. Mark it as picked.
   - **Short** -- Item not available (out of stock, damaged, can't locate). Enter a reason and mark as short.

3. **Complete Pick** -- Once all items are either picked or shorted, click "Complete Pick". Status changes to **Picked** and the order moves to the Pack queue.

### Pick Lists

Print a pick list for efficient warehouse walking:
1. Select a location
2. Click "Print Pick List"
3. A PDF is generated with all items to pick, organized for efficient routing

Pick lists show: SKU, product title, quantity needed, and location/bin information.

### Where the bin information comes from

The pick UI and PDF read the bin from `inventory_levels.bin_id` for the order's chosen location
(`Order.location_id` set by `FulfillmentRouter` at order import). If no bin is recorded, the pick
label falls back to the location code, then to "Unassigned". See
[`PRODUCT_LIFECYCLE_OVERVIEW.md`](PRODUCT_LIFECYCLE_OVERVIEW.md) for the end-to-end map.

The bin is captured at intake (`ProductIntakeQueue.quick_bin_id`) and seeded onto the product's
`InventoryLevel` when the queue item is published. Adjustments after publish (transfers, manual
edits) are tracked through `InventoryService::transferStock` and direct `InventoryLevel` updates.

Do **not** look up the bin via `Product.warehouse_location_id` — it points at a deprecated
`warehouse_locations` table and is never populated by the live intake flow.

### Mobile Picking

The mobile app provides a full picking workflow for warehouse workers using phones or tablets. Access via the app drawer: **Fulfillment > Picking**.

**Mobile-specific features:**
- **Barcode scanner** -- Scan a product barcode to auto-match and pick the correct item, or scan to verify before picking
- **Location-guided picking** -- Each item shows its warehouse location code for efficient navigation
- **Priority indicators** -- Urgent/High badges and overdue warnings displayed on each order
- **My picks filter** -- Toggle between all orders and orders assigned to you
- **Pick progress** -- Visual progress bar per order (picked / shorted / pending counts)

**Mobile picking flow:**
1. Open pick list -- see all orders ready for picking, filtered by location
2. Tap "Start Picking" on an order
3. For each item, either:
   - **Scan** -- Open scanner, scan barcode, item auto-verified and picked
   - **Pick** -- Manually mark as picked (no scan required)
   - **Short** -- Mark unavailable with a reason (out of stock, damaged, cannot locate)
4. When all items are processed, tap "Complete Pick"

**API endpoints** (all under `/api/fulfillment/pick/`, auth:sanctum):
- `GET /` -- Pick list with orders, locations, stats
- `GET /{order}` -- Single order detail
- `POST /{order}/start` -- Start picking
- `POST /{order}/complete` -- Complete picking
- `POST /items/{item}/pick` -- Pick item (optional `barcode` param for verification)
- `POST /items/{item}/short` -- Short item (requires `reason`)
- `POST /items/{item}/scan-verify` -- Verify barcode matches without picking

---

## 4. Pack

**Navigate to:** Fulfillment > Pack

The pack queue shows orders that are **Picked** and ready to be packed.

### Packing an Order

1. **Start Packing** -- Click "Start Pack" on an order. A shipment record is automatically created with the picked items. Status changes to **Packing**.

2. **Enter Package Dimensions** -- Enter or select:
   - **Weight** (oz)
   - **Length, Width, Height** (inches)
   - Or select a **Package Preset** (saved box/envelope sizes from Settings)

3. **Shop Rates** -- Click "Get Rates" to fetch live shipping quotes from your configured provider (EasyPost, ShipStation, or ShippingEasy).
   - See rates from all available carriers (USPS, UPS, FedEx, DHL)
   - Compare price, estimated delivery days, and service level
   - Rates are valid for 1 hour

4. **Purchase Label** -- Select a rate and click "Purchase Label".
   - A shipping label is generated instantly
   - Tracking number is assigned
   - Label PDF is available for download/printing
   - Label cost is recorded on the shipment

5. **Complete Packing** -- Click "Complete Pack". Status changes to **Packed**. A packing slip is generated (if enabled in Settings).

### Package Presets

Save commonly used box sizes in Settings to speed up packing:
- Give each preset a name (e.g., "Small Flat Rate Box", "Large Envelope")
- Set dimensions and optional default weight
- Select a preset instead of typing dimensions each time

---

## 5. Ship

**Navigate to:** Fulfillment > Ship

The ship queue shows all shipments with labels purchased, ready to be handed to the carrier.

### Confirming Shipment

1. **Mark Shipped** -- Click "Confirm Shipped" when the package is handed to the carrier.
   - Shipment status changes to **Shipped**
   - Order status changes to **Shipped**
   - Tracking number is **automatically uploaded** to the marketplace (eBay, Shopify, or Amazon)
   - The buyer receives tracking information from the marketplace

2. **Bulk Ship** -- Select multiple shipments and confirm them all at once.

3. **Batch Print Labels** -- Print labels for multiple shipments in one batch.

### Voiding a Label

If you need to cancel a label before shipping:
1. Click "Void Label" on the shipment
2. The label is cancelled with the shipping provider
3. A refund is typically issued for the label cost
4. The tracking number and label are cleared

### After Shipping

Once shipped, the system handles everything automatically:
- Tracking is uploaded to the marketplace
- The buyer sees tracking in their marketplace account
- Carrier tracking updates are received via webhooks (see next section)

### Manual Tracking Entry (Shipped Outside the System)

If you shipped via a method that doesn't go through your shipping provider (walked to USPS, used another label tool, dropship handoff, etc.), you can enter tracking manually instead of buying a label.

**From the Order detail page:**
1. Click **Enter Tracking** on the order header
2. Choose a **Carrier** (USPS, UPS, FedEx, DHL, OnTrac, LaserShip, Canada Post, Royal Mail, Other)
3. Enter the **Tracking Number** (required)
4. Optional: Service (e.g., Priority Mail, Ground), Tracking URL, Shipped At, Location (only if order is unassigned)
5. Click **Save Tracking & Ship**

**From the Ship Queue:**
- Any shipment without a tracking number shows an **Enter Tracking** link in the row.

**What happens on submit:**
- A shipment is created (or the existing pending shipment is reused) with `shipping_provider = manual`
- Order moves to **Shipped**, inventory is decremented
- Tracking is uploaded to the marketplace immediately and queued for retry on failure
- Buyer receives tracking notification through the marketplace, same as automatic flow

Manual shipments do not show a Void button (no carrier label was purchased to refund).

---

## 6. Tracking & Delivery

After a shipment is confirmed, tracking updates flow in automatically from your shipping provider.

### How It Works

1. Your shipping provider (EasyPost/ShipStation/ShippingEasy) monitors the carrier for tracking events
2. When a tracking event occurs, the provider sends a webhook to FlipStack
3. FlipStack updates the shipment status automatically

### Status Progression

```
Shipped --> In Transit --> Out for Delivery --> Delivered
```

| Status | Meaning |
|--------|---------|
| **Shipped** | Package picked up by carrier |
| **In Transit** | Package moving through carrier network |
| **Out for Delivery** | On the delivery truck, arriving today |
| **Delivered** | Successfully delivered to buyer |
| **Failed** | Delivery attempt failed |
| **Returned to Sender** | Package sent back to you |

### Delivery Confirmation

When all shipments for an order are delivered:
- Order status changes to **Delivered**
- Delivery date is recorded
- The order is complete

No manual action is needed for tracking -- it's fully automatic.

---

## 7. Returns

**Navigate to:** Fulfillment > Returns

### Creating a Return

1. Go to the order detail page
2. Click "Create Return"
3. Fill in:
   - **Reason** -- Select from configured reasons (Defective, Wrong Item, Not as Described, Changed Mind, etc.)
   - **Details** -- Optional notes
   - **Item** -- Which item is being returned (for partial returns)
   - **Quantity** -- How many units
   - **Refund Amount** -- Suggested refund amount

An RMA (Return Merchandise Authorization) number is generated automatically (format: `RMA-YYYYMMDD-XXXX`).

### Return Lifecycle

```
Requested --> Approved --> Shipped Back --> Received --> Inspected --> Refunded
                                                                  |
                                                                  --> Denied
```

| Step | Action | What Happens |
|------|--------|-------------|
| **Requested** | Return created | RMA number generated, order flagged |
| **Approved** | Click "Approve" | Send RMA to buyer with return instructions |
| **Shipped Back** | Buyer ships item | Waiting for package to arrive |
| **Received** | Click "Receive" | Item arrived at warehouse, logged by receiver |
| **Inspected** | Click "Inspect" | Examine condition, note damage, mark restockable or not |
| **Refunded** | Click "Process Refund" | Refund issued and synced to marketplace |
| **Denied** | Click "Deny" (with reason) | Return rejected, buyer notified |

### Restocking

After inspection, if the item is in sellable condition:
1. Mark as "Restockable" during inspection
2. Click "Restock" after refund
3. Select the location to return the item to
4. Inventory is incremented back, and marketplace listings are updated

### Auto-Approve (Optional)

In Settings, you can enable auto-approval for returns under a certain dollar amount. This skips the manual approval step for low-value returns.

---

## 8. Dropship Orders

Some orders may be fulfilled by a supplier instead of your warehouse.

### How It Works

1. When an order contains items from a dropship supplier, a **Supplier Fulfillment Request** is created
2. The request is sent to the supplier with an SLA deadline (configurable in Settings, default 48 hours)
3. The supplier fulfills the order directly to the buyer
4. The supplier provides tracking information
5. Tracking is uploaded to the marketplace

### SLA Monitoring

- The system checks hourly for overdue supplier fulfillment requests
- If a supplier misses their SLA deadline, the request is **escalated** and your team is notified
- You can then decide to fulfill in-house or follow up with the supplier

---

## 9. Settings

**Navigate to:** Fulfillment > Settings

### Shipping Provider

Choose your shipping provider for rate shopping and label purchasing:

| Provider | Description |
|----------|-------------|
| **EasyPost** | Multi-carrier API with discounted USPS rates |
| **ShipStation** | Shipping platform with carrier integrations |
| **ShippingEasy** | Shipping solution with discounted rates and order management |

Enter your API credentials and click "Test Connection & Save". For ShippingEasy, you also need the **Store API Key** (found in ShippingEasy > Settings > Stores).

After connecting, copy the **Webhook URL** displayed and add it to your provider's webhook settings. This enables automatic tracking updates.

### Shipping Preferences

| Setting | Description |
|---------|-------------|
| Default Carrier | Preferred carrier (USPS, UPS, FedEx, DHL) |
| Auto Insurance | Automatically insure shipments above a dollar threshold |
| Signature Confirmation | Require signature above a dollar threshold, or always |
| Preferred Carriers | Ordered list of carriers to show first in rate results |
| Always Use Cheapest | Auto-select the lowest rate |

### Label Settings

| Setting | Description |
|---------|-------------|
| Label Format | PDF, PNG, or ZPL (for thermal printers) |
| Label Size | 4x6 (thermal) or 8.5x11 (standard printer) |
| Include Packing Slip | Auto-generate packing slip with each label |
| Include Return Label | Include a prepaid return label in the package |

### Workflow Automation

| Setting | Description |
|---------|-------------|
| Auto-Assign Orders | Automatically assign pending orders to locations |
| Auto-Upload Tracking | Upload tracking to marketplace after shipping (recommended) |
| Order Sync Interval | How often to check for new orders (1-60 minutes) |
| Auto-Mark Shipped on Label | Mark order as shipped as soon as a label is purchased |
| Require Weight on Pack | Require weight entry before purchasing a label |
| Allow Partial Shipments | Ship some items now, rest later |

### Return Policy

| Setting | Description |
|---------|-------------|
| Return Window | Number of days buyers can request a return (0-365) |
| Restocking Fee | Percentage fee deducted from refund (0-100%) |
| Auto-Approve | Auto-approve returns under a dollar amount |
| Require Photos | Require buyer to submit photos of the item |
| Return Reasons | Customize the list of return reasons |
| Auto-Restock | Automatically restock items when received |

### Package Presets

Create saved package dimensions for quick selection during packing:
- Name, type (box/envelope/soft pack/custom)
- Length, width, height (inches)
- Default weight (oz)

---

## 10. Status Reference

### Order Statuses

| Status | Description | Next Steps |
|--------|-------------|-----------|
| **Pending** | Just imported, not yet assigned | Assign to location |
| **Assigned** | Assigned to warehouse location | Start picking |
| **Picking** | Picker is collecting items | Pick or short items |
| **Picked** | All items picked/shorted | Start packing |
| **Packing** | Being packed and labeled | Enter dimensions, buy label |
| **Packed** | Label purchased, ready to ship | Confirm shipment |
| **Shipped** | Handed to carrier | Wait for delivery |
| **In Transit** | Moving through carrier network | Automatic updates |
| **Delivered** | Successfully delivered | Order complete |
| **On Hold** | Temporarily paused | Release to resume |
| **Cancelled** | Order cancelled | Inventory released |
| **Return Initiated** | Return request created | Process return |
| **Returned** | Return completed and refunded | Done |

### Shipment Statuses

| Status | Description |
|--------|-------------|
| **Pending** | Shipment created, no label yet |
| **Label Purchased** | Label bought, ready to pack/ship |
| **Packed** | Items packed in box |
| **Shipped** | Handed to carrier |
| **In Transit** | Moving through carrier network |
| **Out for Delivery** | On delivery truck |
| **Delivered** | Successfully delivered |
| **Failed** | Delivery attempt failed |
| **Returned to Sender** | Package returned to you |
| **Cancelled** | Shipment/label voided |

### Return Statuses

| Status | Description |
|--------|-------------|
| **Requested** | Buyer requested a return |
| **Approved** | Return authorized, RMA sent to buyer |
| **Shipped Back** | Buyer shipped the item back |
| **Received** | Item arrived at warehouse |
| **Inspected** | Item examined, condition noted |
| **Refunded** | Refund processed and synced to marketplace |
| **Denied** | Return request rejected |
| **Cancelled** | Return request withdrawn |
