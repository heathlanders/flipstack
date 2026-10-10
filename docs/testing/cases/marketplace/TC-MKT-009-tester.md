# Tester Guide: Cross-list — copy a product from one marketplace to another

**Test ID:** TC-MKT-009
**About:** Plain-language version of [TC-MKT-009-cross-listing.md](TC-MKT-009-cross-listing.md). Read [`TESTER_GUIDE.md`](../../TESTER_GUIDE.md) first.

---

## What we're checking

That a seller can take a product they've already listed on one marketplace and one-click list it on another. For example: an eBay listing copied to Shopify, with all the data converted to fit Shopify's rules.

## What you'll need before starting

- An eBay account is connected (TC-MKT-001 done) and a Shopify store is connected (TC-MKT-006 done).
- At least one product has an active eBay listing but is **not yet** on Shopify (for tests 1, 2, 5).
- At least one product has an active Shopify listing but is **not yet** on eBay (for test 3).

---

## Test 1 of 6 — Preview the cross-listing before pulling the trigger

### What to do

1. Pick a product with an active eBay listing and no Shopify listing.
2. On the product detail page, find the cross-list section. Click **"Preview cross-list to Shopify"** (or similar).

### What you should see

- A preview screen opens showing what the Shopify listing would look like.
- The original eBay info (title, description, price, images) is on one side.
- The Shopify-formatted version is on the other.
- The fields are converted as appropriate (e.g., the eBay condition might become a Shopify product tag).
- **Nothing is actually published yet.** No Shopify listing is created during a preview.

### What might go wrong

- The preview screen errors out → bug.
- The preview shows an empty or garbled Shopify version → bug.
- A real Shopify listing actually gets created during preview → bug. Major.

### How to record

`TC-MKT-009-01` Pass / Fail.

---

## Test 2 of 6 — Cross-list eBay → Shopify

### What to do

1. After test 1 (preview shown).
2. Click **"Confirm cross-list"** or **"Create Shopify listing"**.
3. Wait up to 60 seconds.

### What you should see

- The Shopify section on the product page shows status **"Active"** with a Shopify product ID.
- A Shopify listing exists in the Shopify admin matching what the preview showed.
- The product is now on both eBay AND Shopify.
- The eBay listing wasn't disturbed — same status, same data.

### What might go wrong

- Cross-list fails → bug.
- Shopify listing gets created with bad data (different from the preview) → bug.
- The eBay listing changes or breaks during cross-list → bug. Cross-list shouldn't touch the source.

### How to record

`TC-MKT-009-02` Pass / Fail.

---

## Test 3 of 6 — Cross-list Shopify → eBay

The reverse direction — same flow, but starting from Shopify.

### What to do

1. Pick a product with an active Shopify listing and no eBay listing.
2. On the product page, click **"Preview cross-list to eBay"**.
3. Confirm the preview, then click **"Confirm cross-list"**.
4. Wait up to a few minutes (eBay listing creation takes longer than Shopify).

### What you should see

- The eBay section shows the listing progressing through "Pending" → "Published."
- An eBay listing exists with data based on the Shopify product.
- The Shopify listing wasn't disturbed.

### What might go wrong

- Same as test 2.

### How to record

`TC-MKT-009-03` Pass / Fail.

---

## Test 4 of 6 — eBay condition translates correctly to Shopify

eBay has specific condition values like "New," "Used - Good," "For parts or not working." Shopify doesn't have a built-in condition field — these usually become product tags.

### What to do

1. Cross-list 4 products from eBay to Shopify, each with a different condition:
   - Product A: condition "New"
   - Product B: condition "Used - Good"
   - Product C: condition "Used - Acceptable"
   - Product D: condition "For parts or not working"
2. After cross-listing each, look at the Shopify version.

### What you should see

- Each Shopify product has a tag matching its eBay condition:
  - A: tag includes "Condition:New"
  - B: tag includes "Condition:Used - Good"
  - C: tag includes "Condition:Used - Acceptable"
  - D: tag includes "Condition:For Parts" (or similar mapping)
- Tags are predictable and consistent across products.

### What might go wrong

- Conditions are lost (no tag at all) → bug.
- Conditions are mapped wrong (e.g., "Used - Good" becomes "New") → bug.

### How to record

`TC-MKT-009-04` Pass / Fail.

---

## Test 5 of 6 — Cross-listing finder shows only eligible products

The cross-listing screen should help sellers find products that are good candidates — listed on one marketplace but not the other.

### What to do

1. Set up three products in your test account:
   - Product A: on eBay only
   - Product B: on Shopify only
   - Product C: on both eBay and Shopify
2. Open **Marketplaces > Cross-listing** (or similar). Filter by "target = Shopify."

### What you should see

- Product A appears in the list (it's on eBay but not Shopify — eligible).
- Product C does NOT appear (already on both).
- Product B does NOT appear when filtering by "target = Shopify" (it's already on Shopify).

### Then flip the filter

- Filter by "target = eBay."
- Product B appears (Shopify-only, eligible to add to eBay).
- Product C still doesn't appear.

### What might go wrong

- Already-cross-listed products show up as "candidates" → bug.
- Search/filter doesn't work → minor bug.

### How to record

`TC-MKT-009-05` Pass / Fail.

---

## Test 6 of 6 — Cross-listing fails clearly when the target isn't connected

### What to do

1. Pick a product with an eBay listing.
2. Make sure Shopify is **not connected** (or is in an "Reconnect needed" state).
3. Try to cross-list to Shopify.

### What you should see

- The system rejects the action with a clear message: **"No active Shopify connection. Connect Shopify in Settings > Integrations."**
- No Shopify listing is created.
- The user is pointed at how to fix it.

### What might go wrong

- The system tries to push to Shopify anyway and fails with a confusing error → bug.
- The system silently does nothing → bug. The user won't know why.

### How to record

`TC-MKT-009-06` Pass / Fail.

---

## Cross-reference

Engineer version: [`TC-MKT-009-cross-listing.md`](TC-MKT-009-cross-listing.md).
