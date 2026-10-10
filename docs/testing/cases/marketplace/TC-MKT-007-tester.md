# Tester Guide: List a product on Shopify

**Test ID:** TC-MKT-007
**About:** Plain-language version of [TC-MKT-007-shopify-product-sync.md](TC-MKT-007-shopify-product-sync.md). Read [`TESTER_GUIDE.md`](../../TESTER_GUIDE.md) first.

---

## What we're checking

That a product in FlipStack can be listed on Shopify with the right data, that updates flow through, and that Shopify's multi-location inventory is handled correctly (only the seller's primary location holds stock; others are zeroed).

## What you'll need before starting

- A Shopify store is connected (TC-MKT-006 done).
- The connected Shopify store has a known **primary location** set up.
- For test 8 only — the Shopify store has multiple locations defined (the QA Lead can confirm).
- At least one product in FlipStack with title, description, price, quantity, SKU, condition, and at least one image.
- The product is **not yet** listed on Shopify.

---

## Test 1 of 8 — List a single product on Shopify

### What to do

1. Open the product: **Inventory > Products > [your product]**.
2. Find the **Shopify** section.
3. Click **"Sync to Shopify"**.
4. Wait up to 60 seconds, then refresh.

### What you should see

- Shopify section shows status **"Active"** (or "Live").
- A Shopify product ID appears.
- "Last synced" time fills in.
- Open the Shopify admin in another tab — the product exists with the right title, description, price, image(s), and quantity.

### What might go wrong

- Status stays "Pending" for over 5 minutes → bug.
- Status flips to "Error" → bug. Copy the error text.
- Product appears in Shopify but with wrong data → bug.

### How to record

`TC-MKT-007-01` Pass / Fail.

---

## Test 2 of 8 — Bulk list 8 products at once

### What to do

1. Go to **Inventory > Products**.
2. Pick 8 products that aren't on Shopify yet.
3. From the bulk actions menu, choose **"Sync to Shopify"**.

### What you should see

- A progress indicator shows 0 of 8, then ticks up.
- The system goes one at a time (or with controlled pacing) so Shopify doesn't hit a rate limit.
- All 8 reach **Active** status within a few minutes.

### What might go wrong

- Shopify rejects some with rate-limit errors → bug, the system isn't pacing properly.
- Some get stuck on "Pending" forever → bug.

### How to record

`TC-MKT-007-02` Pass / Fail.

---

## Test 3 of 8 — Re-sync a product with no changes (no waste)

### What to do

1. Pick a product already published on Shopify with no recent changes.
2. Click **"Sync to Shopify"** again.

### What you should see

- The system detects nothing changed and skips the work.
- "Last synced" timestamp does not update.
- No error.

### Then change the description and re-sync

- Edit the description, save, click Sync.
- This time the system pushes the update — Last synced updates, the Shopify product reflects the new description.

### What might go wrong

- The system always pushes even when nothing changed → minor bug.
- Real changes aren't pushed → bug.

### How to record

`TC-MKT-007-03` Pass / Fail.

---

## Test 4 of 8 — Force-resync overrides the "no change" check

If a seller suspects something on Shopify is out of sync, they can use **"Force Sync"** to push everything regardless.

### What to do

1. Pick a product already published on Shopify, no changes since last sync.
2. Click **"Force Sync"** (instead of regular Sync).

### What you should see

- The push happens regardless of the no-change check.
- The Shopify product fields are confirmed/refreshed (compare with FlipStack — they should match).
- "Last synced" time updates.

### What might go wrong

- Force Sync acts the same as regular Sync (no override) → bug.
- Force Sync corrupts data on Shopify → bug. Major.

### How to record

`TC-MKT-007-04` Pass / Fail.

---

## Test 5 of 8 — Product gets added to a Shopify collection

If the seller has set up category-to-collection mappings, the product should be automatically added to the matching Shopify collection during sync.

### What to do (needs configuration)

1. With the QA Lead's help, set up a mapping: local category "Electronics" → Shopify collection "Electronics Store."
2. Pick a product in the "Electronics" category and sync it to Shopify.
3. Open the Shopify admin and look at the "Electronics Store" collection.

### What you should see

- The product shows up inside the "Electronics Store" Shopify collection.
- The product's Shopify "product type" field is set to "Electronics."

### Then change the category

- Change the FlipStack product's category to "Accessories" (assuming a mapping exists for that too).
- Re-sync.
- The product moves: removed from "Electronics Store" collection, added to "Accessories" collection.

### What might go wrong

- Product isn't in any collection → bug.
- Product is in the wrong collection → bug.
- Product stays in the OLD collection after the category changes → bug.

### How to record

`TC-MKT-007-05` Pass / Fail.

---

## Test 6 of 8 — Multiple images sync in the right order

### What to do

1. Pick (or set up) a product with 4 images, each with a clear order (1st, 2nd, 3rd, 4th).
2. Sync to Shopify.
3. Open the Shopify admin product page.

### What you should see

- All 4 images are visible.
- They're in the same order as in FlipStack.
- The 1st image is set as the main / primary product image.

### Then change images

- Remove 1 image, add 2 new ones (5 total). Save and re-sync.
- Shopify now shows 5 images in the new order.

### What might go wrong

- Some images don't sync → bug.
- Image order is scrambled → bug.
- Wrong primary image → bug.

### How to record

`TC-MKT-007-06` Pass / Fail.

---

## Test 7 of 8 — Retry a failed sync

### What to do

1. Find (or trigger) a product whose Shopify section shows status **"Error"** with a message like "Shopify rate limit exceeded" or any temporary failure.
2. Click **"Retry"**.

### What you should see

- Status flips back to "Pending."
- Error message clears.
- Within a minute, status reaches "Active."

### What might go wrong

- Retry button does nothing → bug.
- Same error reappears → either a real persistent issue (not a retry bug) or a bug. Investigate the error text.

### How to record

`TC-MKT-007-07` Pass / Fail.

---

## Test 8 of 8 — Multi-location: only the primary location holds stock

This test is important. Shopify supports multiple locations (e.g., a main warehouse plus a secondary office). FlipStack must put all the stock at the seller's chosen primary location and zero out all others, so the total Shopify inventory matches FlipStack's count.

### What to do

1. Make sure the connected Shopify store has at least 2 locations (a primary, plus 1+ others). Ask the QA Lead to confirm.
2. Pick a product with FlipStack stock = 20.
3. Sync (or trigger a stock push) to Shopify.
4. Open the Shopify admin > Products > your product. Check the inventory for each location.

### What you should see

- **Primary location:** 20 available.
- **All other locations:** 0 available.
- The total across all locations = 20 (which matches FlipStack).

### What might go wrong

- Stock split across multiple locations (e.g., 10 at primary, 10 at secondary) → bug. Will cause inventory drift over time.
- All locations show 20 (duplicated) → bug. Major. Will cause oversell.
- Primary location shows 0 instead of 20 → bug.

### How to record

`TC-MKT-007-08` Pass / Fail.

---

## Cross-reference

Engineer version: [`TC-MKT-007-shopify-product-sync.md`](TC-MKT-007-shopify-product-sync.md).
