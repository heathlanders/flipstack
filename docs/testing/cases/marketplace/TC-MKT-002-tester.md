# Tester Guide: List a product on eBay

**Test ID:** TC-MKT-002
**About:** Plain-language version of [TC-MKT-002-ebay-listing-sync.md](TC-MKT-002-ebay-listing-sync.md). Read [`TESTER_GUIDE.md`](../../TESTER_GUIDE.md) first if this is your first test.

---

## What we're checking

That a product in FlipStack can be listed on eBay correctly: the right title, price, quantity, and images appear on eBay; that updates flow through; and that broken cases (missing category, etc.) fail gracefully instead of crashing.

## What you'll need before starting

- An eBay account is **already connected** in FlipStack. (Run TC-MKT-001 first if not.)
- At least one product exists in FlipStack with: title, description, price, a quantity number, an SKU, a condition (new/used/etc.), and at least one image.
- That product has an eBay category picked. (If unsure, look at the product detail page — there should be an "eBay Category" field with a value.)
- The product is **not yet** listed on eBay.

---

## Test 1 of 8 — List a single product on eBay

### What to do

1. Open the product in FlipStack: **Inventory > Products > [your product]**.
2. Find the **eBay** section on the product detail page.
3. Click **"Sync to eBay"** (or a similarly named button).
4. Wait. The system does this in the background — give it up to 60 seconds.
5. Refresh the page.

### What you should see

- The eBay section on the product page now shows a status of **"Published"** (or "Live" / "Active").
- An eBay listing ID is shown (a long number like `110000000001`).
- A "Last synced" time is filled in.
- If you click through to eBay (or open the seller's eBay account in another tab), the listing exists with the right title, price, quantity, and images.

### What might go wrong

- Status stays **"Pending"** for more than 5 minutes → bug.
- Status flips to **"Error"** with a message → bug. Copy the error text.
- Listing appears on eBay but with wrong price/quantity/images → bug. Note the difference exactly (FlipStack shows X, eBay shows Y).

### How to record

`TC-MKT-002-01` Pass / Fail. Screenshot the FlipStack eBay section and the eBay listing page.

---

## Test 2 of 8 — List a batch of 10 products at once

### What to do

1. Go to **Inventory > Products**.
2. Pick 10 products that aren't yet on eBay (use the checkboxes).
3. From the bulk actions menu, choose **"Sync to eBay"**.
4. Wait. The system processes them one by one to avoid overloading eBay.

### What you should see

- A progress indicator showing how many of the 10 are done.
- Within a few minutes, all 10 products should reach **"Published"** status on their detail pages.
- All 10 listings exist on eBay.

### What might go wrong

- Some products get stuck on "Pending" while others publish → bug.
- All 10 fail at once → bug, likely a connection issue. Check eBay is still connected.
- The system tries to do all 10 at once and eBay rejects them with rate-limit errors → bug.

### How to record

`TC-MKT-002-02` Pass / Fail. Screenshot the bulk progress at the end.

---

## Test 3 of 8 — Try to list a product with no eBay category (should fail nicely)

### What to do

1. Find a product that has every field filled in **except** eBay category. (Or, with engineering's help, clear the eBay category on a test product.)
2. On the product page, click **"Sync to eBay"**.

### What you should see

- The eBay section shows status **"Error"**.
- The error message is plain English, similar to: **"Missing eBay category. Set a category before syncing."**
- The system did NOT try to send the bad data to eBay.
- No partial listing was created.

### What might go wrong

- The system crashes the page or shows a stack trace → bug.
- It happily creates a broken listing on eBay → bug. Bad.
- The error message is gibberish or a number with no explanation → bug. Confusing for sellers.

### How to record

`TC-MKT-002-03` Pass / Fail. Screenshot the error.

---

## Test 4 of 8 — Retry a failed listing

### What to do

1. After test 3 (or any test that left an Error), set the product's eBay category correctly.
2. Go to the product detail page, eBay section.
3. Click **"Retry"** next to the error.

### What you should see

- The status goes from "Error" back to "Pending."
- The error message clears.
- Within a minute or so, the status reaches "Published."
- The eBay listing now exists.

### What might go wrong

- "Retry" button does nothing → bug.
- Status goes to "Pending" but never updates → bug.
- The listing publishes but with the OLD broken data instead of the fixed data → bug.

### How to record

`TC-MKT-002-04` Pass / Fail.

---

## Test 5 of 8 — Change a price and watch it update on eBay

### What to do

1. Pick a product that's already published on eBay (use one from test 1).
2. Note the current price on the product detail page (let's say $29.99).
3. Edit the product and change the price to $34.99. Save.
4. Wait up to 60 seconds.
5. Refresh.

### What you should see

- The eBay section's "Last synced" time updates to a recent time.
- If you check the actual eBay listing, the price there is now $34.99.
- The listing status stays **"Published"** (no flip to Pending or Error).

### What might go wrong

- Last synced time doesn't update → bug, the price probably did NOT push.
- eBay listing still shows the old price → bug.
- Status flips to Error → bug.

### How to record

`TC-MKT-002-05` Pass / Fail. Screenshot eBay before and after.

---

## Test 6 of 8 — Change quantity to a non-zero number

### What to do

1. Pick a product that's published on eBay with quantity 10.
2. Reduce the quantity to 3 in FlipStack (you may need to use the stock adjustment tool — ask the QA Lead).
3. Wait up to 60 seconds.

### What you should see

- The eBay listing now shows quantity 3 (or "3 available").
- "Last synced" time updates.

### Bonus: drop the quantity to 0

Most sellers have eBay's "out-of-stock control" turned on, which keeps zero-quantity listings hidden but not deleted.

- If out-of-stock control is on: the listing should change to status **"Out of stock"** in FlipStack and become hidden on eBay (but still exist).
- If out-of-stock control is off: the listing may end on eBay.

Ask the QA Lead which mode the test account uses before judging this.

### What might go wrong

- eBay still shows 10 available → bug, the inventory push failed silently.
- Setting to 0 ends the listing when the seller didn't want that → bug.

### How to record

`TC-MKT-002-06` Pass / Fail. Note which out-of-stock mode is in use.

---

## Test 7 of 8 — Change price AND quantity at the same time

### What to do

1. Pick a published product.
2. Change BOTH price and quantity in one save.
3. Wait.

### What you should see

- Both changes appear on eBay.
- "Last synced" updates once.
- No partial update (e.g., new price but old quantity).

### What might go wrong

- Only one of the two changes makes it to eBay → bug.
- The system makes redundant updates (you see "Last synced" tick twice in a row) → bug, but minor (P2/P3).

### How to record

`TC-MKT-002-07` Pass / Fail.

---

## Test 8 of 8 — Re-sync with no changes (system should skip the work)

### What to do

1. Pick a product that's already published and hasn't changed since.
2. On the product page, click **"Sync to eBay"** again (or the equivalent — "Force sync" should NOT be used here).
3. Watch what happens.

### What you should see

- The system detects no changes and does nothing.
- "Last synced" timestamp does **not** update (or updates only minimally).
- Status stays "Published."
- No new entry appears in any sync log.

This is a "skip when nothing changed" optimization. The test passes if it correctly does nothing.

### Then change the title and resync

- Change the product title slightly. Save. Click Sync.
- Now the system should send the update — Last synced updates, the eBay listing reflects the new title.

### What might go wrong

- Re-syncing always pushes data even when nothing changed → minor bug (wastes eBay's API budget).
- Re-syncing with a real change does NOT push → bug.

### How to record

`TC-MKT-002-08` Pass / Fail.

---

## Cross-reference

Engineer version: [`TC-MKT-002-ebay-listing-sync.md`](TC-MKT-002-ebay-listing-sync.md).
