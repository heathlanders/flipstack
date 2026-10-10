# Tester Guide: eBay promoted listings

**Test ID:** TC-MKT-004
**About:** Plain-language version of [TC-MKT-004-ebay-promoted-listings.md](TC-MKT-004-ebay-promoted-listings.md). Read [`TESTER_GUIDE.md`](../../TESTER_GUIDE.md) first.

> **Note:** This test is **out of cycle** for Marketplace Sync v1. Run only if the QA Lead asks you to.

---

## What we're checking

That a seller can create paid eBay promotion campaigns ("Promoted Listings"), add their products to those campaigns, manage them (pause / resume / end), and view performance numbers.

eBay offers two campaign types:

- **Standard (CPA)** — you pay only when a buyer clicks the ad and buys within 30 days. Set as a percentage of sale price.
- **Advanced (CPC)** — you pay per click, with a daily budget. Requires an eBay Store subscription.

## What you'll need before starting

- An eBay account is connected (TC-MKT-001 done).
- The seller has at least one product published on eBay (TC-MKT-002 done).
- For test 2 only — the eBay account has a paid eBay Store subscription, which is required for Advanced campaigns.
- You can navigate to **eBay > Promoted Listings** in FlipStack.

---

## Test 1 of 8 — Create a Standard (pay-per-sale) campaign

### What to do

1. Go to **eBay > Promoted Listings > Create Campaign**.
2. Pick **"Standard (CPA)"**.
3. Fill in:
   - Name: "Summer Sale 2026"
   - Start date: today
   - Default ad rate: 5%
4. Submit.

### What you should see

- The campaign appears in the campaign list with status **"Active"**.
- It has a name, an "Active" badge, the 5% rate, and a created-on date.
- If you log in to the eBay seller account on eBay.com, the campaign exists there too with the same name.

### What might go wrong

- Submission fails with a server error → bug.
- Campaign saves but isn't visible in the list → bug.
- Campaign saves in FlipStack but doesn't show on eBay → bug.

### How to record

`TC-MKT-004-01` Pass / Fail.

---

## Test 2 of 8 — Create an Advanced (pay-per-click) campaign

Requires an eBay Store subscription on the test account.

### What to do

1. **eBay > Promoted Listings > Create Campaign**.
2. Pick **"Advanced (CPC)"**.
3. Fill in:
   - Name: "Q2 Push"
   - Daily budget: $50.00
   - Start date: today, no end date.
4. Submit.

### What you should see

- The campaign saves with status "Active" and the daily budget shown.
- Campaign exists on eBay too.

### What might go wrong

- The form refuses you because the test account doesn't have an eBay Store → expected behavior, not a bug. Mark this test **Skipped**.
- Submission fails → bug.

### How to record

`TC-MKT-004-02` Pass / Fail / Skipped.

---

## Test 3 of 8 — Add a listing to a campaign with a custom rate

### What to do

1. Open the "Summer Sale 2026" campaign from test 1.
2. Click **"Add Listings"**.
3. Pick a published product from the list.
4. Set the ad rate for this listing to **7%** (overrides the campaign's 5% default).
5. Confirm.

### What you should see

- The listing now shows in the campaign's list of promoted items, with rate 7%.
- The product's eBay listing isn't otherwise changed (still Published, same price, same quantity).

### What might go wrong

- The listing doesn't appear → bug.
- The rate shows 5% (the default) instead of 7% (the override) → bug.

### How to record

`TC-MKT-004-03` Pass / Fail.

---

## Test 4 of 8 — Remove a listing from a campaign

### What to do

1. Open a campaign that has at least one listing in it.
2. Find the listing row, click **"Remove"**, confirm.

### What you should see

- The listing is gone from the campaign's promoted list.
- The product's eBay listing itself is untouched (still Published).
- It's no longer being promoted on eBay.

### What might go wrong

- The product's actual eBay listing gets ended/deleted by accident → bug. Major.
- The remove button doesn't work → bug.

### How to record

`TC-MKT-004-04` Pass / Fail.

---

## Test 5 of 8 — Pause, resume, and end a campaign

### What to do

1. Open an active campaign with at least one listing.
2. Click **"Pause"**.
3. Verify the campaign status flips to **"Paused"** (in FlipStack and on eBay).
4. Click **"Resume"**.
5. Verify the campaign goes back to **"Active"**.
6. Click **"End Campaign"**, confirm.
7. Verify the campaign status flips to **"Ended"** with an end date.

### What you should see

- Each of pause / resume / end works without errors.
- After ending, the campaign cannot be reopened (the buttons disappear or grey out). To get the listings promoted again, the seller has to make a new campaign.

### What might go wrong

- Status changes in FlipStack but not on eBay (or vice versa) → bug.
- Ended campaign can be resumed → bug. eBay does not allow this.

### How to record

`TC-MKT-004-05` Pass / Fail.

---

## Test 6 of 8 — Performance numbers update from eBay

eBay sends performance data (impressions, clicks, sales) on a delay — usually 24-48 hours after the activity. To test, use a campaign that's been live for a couple of days.

### What to do

1. Pick a campaign that has been active for at least 2 days.
2. Trigger the stats refresh (the QA Lead will tell you if this is automatic or manual).
3. Open the campaign detail page.

### What you should see

- The campaign and its listings show: impressions, clicks, total spend, total sales.
- A "Stats last synced" timestamp updates.

### What might go wrong

- All numbers stay at 0 even though the campaign has been live → bug, or a sign that eBay's data hasn't arrived yet (wait another day). Note the date.
- Numbers update but a sync timestamp does not → bug.

### How to record

`TC-MKT-004-06` Pass / Fail.

---

## Test 7 of 8 — The dashboard adds up the numbers correctly

### What to do

1. Make sure the seller has 3 campaigns, each with synced stats.
2. Note each campaign's spend, sales, clicks, and impressions.
3. Add them up by hand.
4. Open **eBay > Promoted Listings** dashboard.

### What you should see

- The totals at the top of the dashboard match your hand-calculated sums.
- The **ROAS** (Return on Ad Spend = total sales / total spend) is correct.
- The **CTR** (Click-Through Rate = clicks / impressions, as a percentage) is correct.
- Each campaign also shows individually with its own numbers.

### What might go wrong

- Totals don't match your math → bug. Check arithmetic first.
- ROAS or CTR is calculated wrong → bug.

### How to record

`TC-MKT-004-07` Pass / Fail. Show your math in the run log.

---

## Test 8 of 8 — A user can't see another seller's campaigns

This is critical security: two different sellers using FlipStack must never see each other's data.

### What to do (with the QA Lead's help — needs two test users)

1. Have two test sellers, A and B, each on different organizations.
2. Seller A has 2 campaigns. Seller B has 1.
3. Log in as Seller A.
4. Open **eBay > Promoted Listings**.

### What you should see

- Only Seller A's 2 campaigns are visible.
- The dashboard totals only include Seller A's data.

### Then try to sneak in

5. Note one of Seller B's campaign URLs (the QA Lead can give it to you).
6. While logged in as Seller A, paste that URL into the address bar.

### What you should see

- An error page: 403 Forbidden, 404 Not Found, or a friendly "you don't have access" page.
- **Definitely NOT** Seller B's campaign data.

### What might go wrong

- Seller A can see Seller B's campaign list → critical bug. Severity P0.
- Seller A can open Seller B's campaign by guessing the URL → critical bug. Severity P0.

### How to record

`TC-MKT-004-08` Pass / Fail. Any failure here is P0. Stop and notify the QA Lead immediately.

---

## Cross-reference

Engineer version: [`TC-MKT-004-ebay-promoted-listings.md`](TC-MKT-004-ebay-promoted-listings.md).
