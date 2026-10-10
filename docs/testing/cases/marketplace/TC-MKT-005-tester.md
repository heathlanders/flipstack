# Tester Guide: Import an existing eBay store into FlipStack

**Test ID:** TC-MKT-005
**About:** Plain-language version of [TC-MKT-005-ebay-store-import.md](TC-MKT-005-ebay-store-import.md). Read [`TESTER_GUIDE.md`](../../TESTER_GUIDE.md) first.

> **Note:** This test is **out of cycle** for Marketplace Sync v1. Run only if the QA Lead asks.

---

## What we're checking

That a seller who already has products listed on eBay can pull all those listings into FlipStack in one go. Each eBay listing should become a Product in FlipStack with an attached eBay listing record.

## What you'll need before starting

- An eBay account is connected (TC-MKT-001 done).
- The connected eBay account has at least 50 active listings — the more variety, the better the test.
- No import is currently running for this seller (the system only allows one at a time).

---

## Test 1 of 6 — Start an import

### What to do

1. Go to **eBay > Import Store**.
2. Leave the default settings (import all).
3. Click **"Start Import"**.

### What you should see

- The page redirects to a progress screen showing **"Import started"** with a progress bar at 0%.
- The progress bar starts ticking up as eBay listings are pulled in.

### What might go wrong

- Click does nothing → bug.
- Error: "An import is already in progress." → expected if one is running. Wait or cancel it. Not a bug.
- Progress bar stays at 0% for over 5 minutes → likely bug.

### How to record

`TC-MKT-005-01` Pass / Fail.

---

## Test 2 of 6 — Watch the progress

### What to do

1. While the import is running, leave the progress page open.
2. Refresh occasionally, or watch the live updates.

### What you should see

- The progress bar moves up as more listings are processed.
- It shows: total items, items processed, items failed, percentage done.
- If any items failed, a list of failures appears with reasons in plain English.
- When all are done, status flips to **"Completed"** at 100%.

### What might go wrong

- Progress bar stays stuck while the count goes up (or vice versa) → bug.
- Failed items have no reason shown → bug, sellers won't know what went wrong.

### How to record

`TC-MKT-005-02` Pass / Fail.

---

## Test 3 of 6 — Cancel an import partway

### What to do

1. Start a new import.
2. Wait until 10-20 items are processed (15 of 50 done is fine).
3. Click **"Cancel Import"**, confirm.

### What you should see

- Status flips to **"Cancelled"**.
- The progress bar stops moving.
- No more eBay listings are pulled in.
- The 15 (or however many) items that were already imported **stay** in FlipStack as products. Cancelling doesn't undo what's already done.

### What might go wrong

- Cancel button does nothing → bug.
- Already-imported products are deleted on cancel → bug, that's destructive.
- Import keeps running for several more minutes after cancel → bug, but possibly minor (system might be finishing the current batch).

### How to record

`TC-MKT-005-03` Pass / Fail.

---

## Test 4 of 6 — Resume a cancelled import

### What to do

1. After test 3 (a cancelled import with 15 of 50 done).
2. Click **"Resume Import"**.

### What you should see

- Status flips back to **"Processing"**.
- The progress bar shows roughly 30% (15/50) and continues from there.
- The system does NOT re-import the 15 already-imported products. They stay as one Product each, not as duplicates.
- When done, total products = 50.

### What might go wrong

- Resume restarts from 0 and creates duplicates → bug. Major.
- Resume button does nothing → bug.

### How to record

`TC-MKT-005-04` Pass / Fail.

---

## Test 5 of 6 — Imported items become real products

Look at one of the imported products in detail.

### What to do

1. After an import completes, go to **Inventory > Products**.
2. Find an imported product (sort by created date, the newest are probably yours).
3. Open it.

### What you should see

The Product page shows:

- Title (matches the eBay listing's title).
- Description (the eBay description, with HTML cleaned up).
- Price.
- SKU (matches eBay's SKU).
- Condition (e.g., "Used - Excellent").
- One or more product images (matching the eBay images).
- An eBay section showing **"Published"** with the original eBay listing ID.
- Available quantity matching what was on eBay.

### What might go wrong

- A field is missing or wrong (e.g., title says "undefined") → bug.
- Images are broken or missing → bug.
- The product is missing the eBay listing link → bug.
- Wrong condition (e.g., "Used" when eBay said "New") → bug, mapping issue.

### How to record

`TC-MKT-005-05` Pass / Fail. Screenshot the Product page next to the eBay listing page.

---

## Test 6 of 6 — Re-importing doesn't create duplicates

### What to do

1. After a successful full import (test 1 + 2 finished).
2. Start a fresh import on the same eBay store.

### What you should see

- The import processes all 50 items but creates **zero** new Products.
- The existing 50 Products are detected and either skipped or updated with the latest eBay data.
- Final product count remains 50, not 100.

### What might go wrong

- Re-importing creates 50 duplicate products → bug. Major.
- Re-importing creates 0 duplicates but throws errors for every item → bug. Errors should be expected/handled.

### How to record

`TC-MKT-005-06` Pass / Fail.

---

## Cross-reference

Engineer version: [`TC-MKT-005-ebay-store-import.md`](TC-MKT-005-ebay-store-import.md).
