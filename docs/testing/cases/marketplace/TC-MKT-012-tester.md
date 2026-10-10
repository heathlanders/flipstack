# Tester Guide: Full lifecycle — list, sell, see counts match (the acceptance gate)

**Test ID:** TC-MKT-012
**About:** Plain-language version of [TC-MKT-012-marketplace-sync-e2e-lifecycle.md](TC-MKT-012-marketplace-sync-e2e-lifecycle.md). Read [`TESTER_GUIDE.md`](../../TESTER_GUIDE.md) first.

> **This is the acceptance gate for the cycle.** If this test fails, the cycle does NOT release. Take your time on this one.

---

## What we're checking

That a product can go through the entire selling lifecycle without the stock count getting out of sync between FlipStack, eBay, and Shopify. Specifically:

1. List the product on both eBay and Shopify with stock 10.
2. A buyer buys 2 on eBay → counts drop to 8 everywhere.
3. A buyer buys 3 on Shopify → counts drop to 5 everywhere.
4. The seller never oversells, and all three systems show 5 at the end.

This test combines what you saw in TC-MKT-002, 003, 007, and 008 into one continuous run. If something fails here, file the bug against the most specific test (e.g., if Shopify push fails, file under TC-MKT-007 with a note pointing back to TC-MKT-012).

## What you'll need before starting

- An eBay account is connected (TC-MKT-001 done).
- A Shopify store is connected (TC-MKT-006 done), with a primary location set up.
- A test product exists with: title "E2E Sync Widget," SKU "E2E-001," price $49.99, quantity 10, condition "new," and at least one image. The product has an eBay category set.
- The product is **not yet** listed on either eBay or Shopify.
- The QA Lead can place test orders on both eBay and Shopify (sandbox/dev accounts).

---

## Test 1 of 3 — List, push, order, reconcile (the happy path)

This is the main test. Expect it to take ~30 minutes including waits.

### Step 1 — List on eBay

1. Open the product page.
2. Click **"Sync to eBay."**
3. Wait up to 60 seconds, refresh.

**Expected:** eBay section shows status **"Published"** with quantity 10.

### Step 2 — List on Shopify

4. On the same product page, click **"Sync to Shopify."**
5. Wait, refresh.

**Expected:** Shopify section shows status **"Active"** with quantity 10 at the primary location, 0 at all other locations.

### Step 3 — Order 2 units on eBay

6. Have the QA Lead place a test eBay order for **2 units** of "E2E Sync Widget."
7. Wait up to 60 seconds for FlipStack to receive the order.
8. Refresh the product page.

**Expected:**
- An Order appears in **Fulfillment > Orders** with source "eBay" and quantity 2.
- The product's "Available" count drops from 10 to 8.

### Step 4 — Watch the new count push to both marketplaces

9. Wait another 60 seconds.

**Expected:**
- eBay listing's quantity drops to 8.
- Shopify listing's quantity drops to 8 at the primary location, 0 at all others.

### Step 5 — Order 3 units on Shopify

10. Have the QA Lead place a test Shopify order for **3 units** of the same product.
11. Wait up to 60 seconds.

**Expected:**
- A second Order appears, source "Shopify," quantity 3.
- The product's "Available" count drops from 8 to 5.

### Step 6 — Watch this count push to both again

12. Wait another 60 seconds.

**Expected:**
- eBay listing's quantity drops to 5.
- Shopify listing's quantity drops to 5 at primary, 0 elsewhere.

### Step 7 — Final state check

13. Compare these numbers:

| Where | What it should show |
|---|---|
| FlipStack product "Available" | 5 |
| FlipStack product "Reserved" | 5 (the 2 + 3 still being processed) |
| FlipStack product "Total" | 10 (physical stock unchanged — units haven't shipped yet) |
| eBay listing | 5 available |
| Shopify listing (primary location) | 5 available |
| Shopify listing (other locations) | 0 |

All three sale-facing numbers (FlipStack Available, eBay, Shopify primary) must equal **5**. If they don't match, this test FAILS and the cycle cannot release.

### What might go wrong

- Any number is off by even 1 → bug. File and stop the cycle until fixed.
- Numbers match but the system shows errors in the sync log → may still be a bug.
- The same number is showing across systems but it's because no orders went through (i.e., still 10 everywhere) → the order placement failed. File against TC-MKT-008-03 or TC-MKT-002.

### How to record

`TC-MKT-012-01` Pass / Fail. **This is the acceptance gate.** Capture every screen — FlipStack product page, eBay listing, Shopify admin product page — at the end.

---

## Test 2 of 3 — Editing the product after both listings exist updates both marketplaces

### What to do

1. Take the same product from test 1 (now listed on eBay and Shopify).
2. Edit the price from $49.99 to $39.99 in FlipStack. Save.
3. Wait up to 60 seconds.

### What you should see

- eBay listing now shows $39.99.
- Shopify listing now shows $39.99.
- "Last synced" updates on both.

### Then change the title and primary image

4. Edit the title (e.g., add "(Sale)") and pick a different primary image. Save.
5. Wait.

### What you should see

- Both marketplaces reflect the new title and image.
- If you re-sync immediately after a change, the system should NOT push redundantly (the no-change-detected check should kick in).

### Then turn off Shopify auto-sync

6. Go to **Settings > Integrations > Shopify** and turn off Auto-sync.
7. Edit the product price again ($34.99). Save.

### What you should see

- eBay updates to $34.99.
- Shopify does NOT update (still shows $39.99). Auto-sync is off.
- The sync log records that Shopify was skipped — not a failure, a deliberate choice.

### What might go wrong

- One marketplace updates, the other doesn't (without a deliberate setting) → bug.
- Toggling auto-sync off doesn't actually pause syncs → bug.

### How to record

`TC-MKT-012-02` Pass / Fail.

---

## Test 3 of 3 — When eBay is briefly broken, Shopify still works

### What to do (with engineering's help)

1. The product is listed on both eBay and Shopify. Engineering simulates eBay returning a server error.
2. Adjust the FlipStack stock by 3 units (down to whatever new number).
3. Watch the inventory push.

### What you should see

- The eBay push fails — the sync log records the error.
- The Shopify push succeeds — Shopify's quantity updates correctly.
- The eBay listing's status stays **"Published"** (not flipped to "Error" — this is a transient failure).
- No one job blows up the whole sync — it's logged per-marketplace.

### Then restore eBay and retry

4. Engineering restores eBay to working.
5. Trigger a retry on the eBay sync (or wait for the next stock change).

### What you should see

- eBay catches up to Shopify. Both listings now match FlipStack again.

### What might go wrong

- Shopify push gets blocked by the eBay failure → bug. They should be independent.
- The eBay listing's status flips to "Error" forever → bug.
- The system retries eBay in a tight loop and floods Shopify too → bug.

### How to record

`TC-MKT-012-03` Pass / Fail.

---

## What to capture for the run log

For TC-MKT-012, evidence is required because this is the acceptance gate. The run log should include:

- Final-state screenshots: FlipStack product page, eBay listing on eBay, Shopify product on Shopify admin (test 1, step 7).
- Both order records visible in **Fulfillment > Orders**.
- A note of the times you placed each test order (so engineering can correlate logs).

## Cross-reference

Engineer version: [`TC-MKT-012-marketplace-sync-e2e-lifecycle.md`](TC-MKT-012-marketplace-sync-e2e-lifecycle.md).
