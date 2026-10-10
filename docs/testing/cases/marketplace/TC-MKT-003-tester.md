# Tester Guide: Change stock and watch eBay update

**Test ID:** TC-MKT-003
**About:** Plain-language version of [TC-MKT-003-ebay-inventory-sync.md](TC-MKT-003-ebay-inventory-sync.md). Read [`TESTER_GUIDE.md`](../../TESTER_GUIDE.md) first.

---

## What we're checking

That when the stock count changes in FlipStack, eBay's listing reflects the new number quickly and accurately. The "Available" count (what's still up for sale) is the number that should reach eBay — not the total physical count, since some units may already be reserved for orders in progress.

## What you'll need before starting

- An eBay account is connected (TC-MKT-001 done).
- At least one product is already published on eBay (TC-MKT-002 done).
- You can edit the product's stock numbers. Ask the QA Lead if you don't know how.
- It helps to have a second tab open showing the eBay seller account, so you can check the live listing.

---

## Test 1 of 6 — A normal stock push to eBay

### What to do

1. Pick a product whose listing on eBay shows quantity 10. Confirm the FlipStack "Available" count is also 10.
2. Trigger an inventory push (this happens automatically when stock changes — for testing, ask the QA Lead how to manually trigger one, or just edit the stock by 1 unit and back).
3. Wait up to 60 seconds.

### What you should see

- The eBay listing still shows 10 available (or 8 if you stepped down by 2 in step 2).
- "Last synced" on the FlipStack listing updates.
- No errors anywhere.

### What might go wrong

- eBay shows a different number than FlipStack → bug.
- "Last synced" doesn't update → bug.

### How to record

`TC-MKT-003-01` Pass / Fail.

---

## Test 2 of 6 — Confirm the right number is being sent

### What to do

1. Set the FlipStack stock to a specific, memorable number (e.g., 12).
2. Trigger a push.
3. Open the eBay listing.

### What you should see

- The eBay listing's "available" count is exactly 12.

### What might go wrong

- eBay shows 11 or 13 (off-by-one) → bug.
- eBay shows 0 → bug (listing might have a different problem).

### How to record

`TC-MKT-003-02` Pass / Fail.

---

## Test 3 of 6 — "Available" vs. "total" — eBay should get "Available"

The product can have a total stock of 10 but only 5 truly available because 5 are reserved for orders that are in progress. eBay should see **5**, not 10. If it sees 10, the seller risks selling more than they have — a costly mistake.

### What to do

1. Pick a product with total stock 10 and "Available" 10 (no orders in progress).
2. Have engineering simulate 3 units being reserved (an in-progress order). Now: total = 10, Available = 7.
3. Trigger a push.

### What you should see

- The eBay listing now shows **7** available.
- The total stock in FlipStack still shows 10. Available is what changed.

### Then reserve 3 more

- Now: total = 10, Available = 4. Push again. eBay should show 4.

### What might go wrong

- eBay shows 10 (the total) → bug. Major. This is the main thing this test exists to catch.
- eBay shows 0 → bug.

### How to record

`TC-MKT-003-03` Pass / Fail. This is a critical test for preventing oversell.

---

## Test 4 of 6 — When auto-sync is OFF for eBay, no push happens

Sometimes a seller pauses sync to one marketplace while leaving others on. The setting is per-marketplace.

### What to do

1. Go to **Settings > Integrations > eBay** and turn OFF the **"Auto-sync inventory"** toggle.
2. Change the stock on a product (FlipStack only).
3. Wait.
4. Check the eBay listing.

### What you should see

- The eBay listing's quantity does NOT change. Auto-sync is off, so the system stays silent.
- The Shopify listing for the same product (if there is one) DOES update — only eBay was paused.
- No errors are logged for the skip — pausing is a normal choice, not a failure.

### Then turn it back on

- Re-enable Auto-sync. Change stock again. Now eBay should update.

### What might go wrong

- eBay still updates even though auto-sync is off → bug. The toggle isn't working.
- Shopify also stops updating → bug. Toggling eBay off shouldn't affect other marketplaces.

### How to record

`TC-MKT-003-04` Pass / Fail.

---

## Test 5 of 6 — Sync rules — only some products auto-sync

Sellers can set rules like "only sync Electronics products to eBay." The rule engine is shared with TC-MKT-011, but for this test we just check it filters correctly here too.

### What to do

1. Set up (or ask the QA Lead to set up) a rule: **only products in the "Electronics" category sync to eBay**.
2. Find Product A (in Electronics) and Product B (in another category like "Clothing"). Both have eBay listings.
3. Change the stock on each.

### What you should see

- Product A's eBay listing updates.
- Product B's eBay listing does NOT update — the rule blocked it.

### What might go wrong

- Both update → bug, the rule isn't being applied.
- Neither updates → bug, the rule is too restrictive.

### How to record

`TC-MKT-003-05` Pass / Fail. (Larger test of rules in TC-MKT-011.)

---

## Test 6 of 6 — When eBay is briefly broken, Shopify still works

If eBay's servers are down for a few minutes, that shouldn't break the Shopify push for the same product.

### What to do (with engineering's help)

1. Engineering simulates eBay returning a server error.
2. Have a product with both an eBay listing AND a Shopify listing.
3. Trigger a stock push.

### What you should see

- The eBay push fails. A note about the failure appears in the FlipStack sync log.
- The Shopify push for the same product **succeeds** — it's not blocked by eBay's failure.
- The eBay listing's status in FlipStack stays "Published" (not flipped to Error). The failure is treated as a temporary blip, not a hard failure.
- The next time stock changes (or after a manual retry), eBay catches up.

### What might go wrong

- Shopify push is also skipped → bug. One marketplace shouldn't block another.
- The eBay listing flips to "Error" status forever → bug. Transient failures shouldn't kill the listing's status.
- The system retries eBay 1000 times in a loop → bug.

### How to record

`TC-MKT-003-06` Pass / Fail.

---

## Cross-reference

Engineer version: [`TC-MKT-003-ebay-inventory-sync.md`](TC-MKT-003-ebay-inventory-sync.md).
