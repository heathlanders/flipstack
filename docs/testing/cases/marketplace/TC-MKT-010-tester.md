# Tester Guide: Schedule a listing for a future date

**Test ID:** TC-MKT-010
**About:** Plain-language version of [TC-MKT-010-scheduled-listings.md](TC-MKT-010-scheduled-listings.md). Read [`TESTER_GUIDE.md`](../../TESTER_GUIDE.md) first.

> **Note:** This test is **out of cycle** for Marketplace Sync v1. Run only if the QA Lead asks.

---

## What we're checking

That a seller can schedule a product listing to be published at a future date and time, that the system honors the schedule, and that they can cancel or remove a scheduled listing.

## What you'll need before starting

- An eBay account or a Shopify store is connected.
- At least one product exists that is not yet listed on the target marketplace.

---

## Test 1 of 6 — View the list of scheduled listings

### What to do

1. Set up (with the QA Lead's help) at least 3 scheduled listings in different states:
   - 1 pending (set for a future date)
   - 1 already-published (was scheduled and ran successfully)
   - 1 cancelled
2. Go to **Marketplaces > Scheduled Listings**.

### What you should see

- All 3 listings are visible in the list.
- Each row shows: product name, target marketplace, scheduled time, current status.
- Status badges are visually distinct (different colors or labels for Pending / Published / Cancelled / Failed).
- Listings are sorted by scheduled time (soonest first).
- Only the seller's own listings are shown — not another seller's.

### What might go wrong

- Listings missing from the list → bug.
- Status badges all look the same → bug, hard to tell what's what.
- Another seller's listings appear → critical bug, organization isolation broken. P0.

### How to record

`TC-MKT-010-01` Pass / Fail.

---

## Test 2 of 6 — Schedule a listing for tomorrow

### What to do

1. Pick a product not yet on the target marketplace.
2. Click **"Schedule listing"** (or similar).
3. Pick the marketplace (e.g., eBay).
4. Set the date/time to tomorrow at 10:00 AM.
5. Fill in or confirm the listing data (title, price, quantity, condition).
6. Save.

### What you should see

- A new row appears in **Scheduled Listings** with status **"Pending"** and the scheduled time.
- A success message confirms the schedule was saved.

### Then try to schedule for the past

- Repeat steps 1-6, but set the date to yesterday.
- Save.

### What you should see

- The form rejects the past date with a validation error: **"Scheduled time must be in the future."**

### What might go wrong

- Past dates are accepted → bug. The listing would either run immediately or never run.
- Scheduling errors out without a clear message → bug.

### How to record

`TC-MKT-010-02` Pass / Fail.

---

## Test 3 of 6 — Cancel a scheduled listing before it runs

### What to do

1. Pick a Pending scheduled listing whose time hasn't arrived yet.
2. Click **"Cancel"** on its row, confirm.

### What you should see

- The listing's status flips to **"Cancelled"**.
- It stays in the list (for record-keeping) but won't run.
- When the original scheduled time passes, no listing is created on the marketplace.

### Then try to cancel something already-published

- Find an already-published scheduled listing.
- Try to cancel it.

### What you should see

- The system blocks it with a message: **"This listing has already been published and cannot be cancelled."**

### What might go wrong

- Cancelling a Pending listing doesn't stop it (the listing publishes anyway at the scheduled time) → bug.
- Already-published listings can be "cancelled," which does nothing → minor bug.

### How to record

`TC-MKT-010-03` Pass / Fail.

---

## Test 4 of 6 — Due listings actually publish on schedule

### What to do (with the QA Lead's help — uses time manipulation)

1. Set up 4 scheduled listings:
   - **A:** Pending, scheduled 1 hour ago (due, should run).
   - **B:** Pending, scheduled 2 hours ago (due, should run).
   - **C:** Pending, scheduled tomorrow (not due).
   - **D:** Cancelled, scheduled 1 hour ago (cancelled, should not run).
2. Have the QA Lead trigger the scheduler (this normally runs every few minutes automatically).

### What you should see

- A flips to **"Published"** with a real eBay or Shopify listing now existing.
- B flips to **"Published"** too.
- C stays in **"Pending"** (not yet due).
- D stays in **"Cancelled"** (skipped).

### What might go wrong

- C runs early → bug.
- D runs anyway despite being cancelled → bug. Major.
- One of A/B fails and stops B/A from running → bug. Each listing should run independently.

### How to record

`TC-MKT-010-04` Pass / Fail.

---

## Test 5 of 6 — A listing that fails to publish gets a clear error

### What to do (with engineering's help)

1. Set up a scheduled listing that will fail to publish (e.g., missing eBay-required fields, or expired connection).
2. Wait for the scheduler to try it.

### What you should see

- The listing's status flips to **"Failed"** (not Published).
- The row shows a clear error message: **"eBay rejected the listing: [specific reason]."**
- Other Pending listings around it still run normally — one failure doesn't bring everything down.

### What might go wrong

- Failed listing has no error message → bug, the seller won't know what to fix.
- Other due listings get blocked → bug.

### How to record

`TC-MKT-010-05` Pass / Fail.

---

## Test 6 of 6 — Delete a scheduled listing entirely

### What to do

1. Pick any scheduled listing (any status).
2. Click **"Delete"** (the trash icon, or similar), confirm.

### What you should see

- The listing is removed from the list.
- Returning to **Scheduled Listings** confirms it's gone.

### What might go wrong

- The deletion fails silently → bug.
- A Published listing gets deleted, removing the audit history → minor bug. Some teams prefer to keep published records as history. Confirm with the QA Lead what the expected behavior is.

### How to record

`TC-MKT-010-06` Pass / Fail.

---

## Cross-reference

Engineer version: [`TC-MKT-010-scheduled-listings.md`](TC-MKT-010-scheduled-listings.md).
