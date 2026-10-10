# Tester Guide: Buy something on Shopify and watch the stock drop everywhere

**Test ID:** TC-MKT-008
**About:** Plain-language version of [TC-MKT-008-shopify-webhooks.md](TC-MKT-008-shopify-webhooks.md). Read [`TESTER_GUIDE.md`](../../TESTER_GUIDE.md) first.

---

## What we're checking

That when something happens on Shopify (an order, a product edit, a deletion, or the seller uninstalling the app), Shopify tells FlipStack about it via an automatic update, and FlipStack reacts correctly. In particular: a Shopify order should immediately reduce the FlipStack stock and push the new count to other marketplaces (like eBay).

This test relies on FlipStack receiving messages from Shopify. Most steps require placing a real test order on a Shopify dev store, which the QA Lead will set up.

## What you'll need before starting

- A Shopify store is connected (TC-MKT-006 done).
- At least one product is listed on both Shopify AND eBay (TC-MKT-002 + TC-MKT-007 done).
- The QA Lead can place test orders on the connected Shopify store (using the dev store's order tool).

---

## Test 1 of 7 — Real Shopify message accepted

This is the security check: a genuine message from Shopify is accepted; a faked one is rejected.

### What to do (with engineering's help)

1. Engineering sends a real, properly-signed Shopify message to FlipStack's notification address.

### What you should see

- FlipStack accepts the message and queues it for processing.

### What might go wrong

- Genuine message rejected → bug. Real Shopify events would be dropped on the floor.

### How to record

`TC-MKT-008-01` Pass / Fail.

---

## Test 2 of 7 — Faked Shopify message rejected

### What to do (with engineering's help)

1. Engineering sends a fake message that pretends to be from Shopify but uses a wrong signature.

### What you should see

- FlipStack rejects the message with an "unauthorized" response.
- Nothing gets processed.
- The attempt is logged for security review.

### What might go wrong

- Fake message accepted → bug. Major security issue. P0.

### How to record

`TC-MKT-008-02` Pass / Fail.

---

## Test 3 of 7 — A Shopify order reserves stock immediately

### What to do

1. Pick a product listed on Shopify with FlipStack stock = 10. Note the eBay listing for the same product also shows quantity 10.
2. Have the QA Lead place a test order for **2 units** of this product on the Shopify store.
3. Wait up to 60 seconds.
4. Refresh the FlipStack product page.

### What you should see

- FlipStack's "Available" count drops from 10 to 8.
- An Order record appears in **Fulfillment > Orders** with the source marked "Shopify" and the buyer's info.
- Within another minute, the eBay listing for the same product also drops to 8 (FlipStack pushed the new count to other marketplaces).

### What might go wrong

- "Available" count doesn't change → bug. Major. Ships an oversell risk.
- Available drops by the wrong amount (e.g., goes from 10 to 9 when 2 were ordered) → bug.
- Order doesn't appear in **Fulfillment > Orders** → bug.
- eBay listing doesn't update to 8 → bug. (Cross-marketplace inventory broken.)

### How to record

`TC-MKT-008-03` Pass / Fail. This is one of the most important tests in the cycle.

---

## Test 4 of 7 — When a seller edits the product directly on Shopify

If a seller goes to Shopify admin and changes the title there (instead of in FlipStack), Shopify sends FlipStack an update notification. FlipStack should record what changed but NOT overwrite the FlipStack version — FlipStack is the source of truth.

### What to do

1. Pick a product with title "Wireless Headphones" in both FlipStack and Shopify.
2. In the Shopify admin, change the title to "Wireless Headphones - SALE."
3. Save.
4. Wait up to 60 seconds.
5. Open the FlipStack product page.

### What you should see

- The FlipStack product title is **still** "Wireless Headphones" (not auto-overwritten).
- The Shopify section's "Last synced" time updates (FlipStack saw the change).
- A flag or note may appear: "Title differs between FlipStack and Shopify — review."

### What might go wrong

- FlipStack's title gets overwritten with "Wireless Headphones - SALE" → bug. FlipStack must remain the source of truth.
- FlipStack ignores the change entirely (no flag, no acknowledgement) → minor bug.

### How to record

`TC-MKT-008-04` Pass / Fail.

---

## Test 5 of 7 — When a seller deletes the product on Shopify

### What to do

1. Pick a product with an active Shopify listing.
2. In the Shopify admin, delete the product.
3. Wait up to 60 seconds.
4. Open the FlipStack product page.

### What you should see

- The Shopify section status flips to **"Deleted on Shopify"** (or similar).
- A "Re-list on Shopify" button appears so the seller can recreate it later if they want.
- The FlipStack product itself is **not** deleted. Stock count is unchanged.
- Other marketplace listings (e.g., eBay) for this product are not affected.

### What might go wrong

- The FlipStack product gets deleted → bug. Major. Destructive.
- The eBay listing for the same product gets deleted → bug. Even worse.
- Stock drops or changes → bug.

### How to record

`TC-MKT-008-05` Pass / Fail.

---

## Test 6 of 7 — When the seller uninstalls FlipStack from Shopify

### What to do

1. With a connected Shopify store.
2. In the Shopify admin: Settings > Apps. Uninstall FlipStack.
3. Wait up to 60 seconds.
4. Open FlipStack > Settings > Integrations.

### What you should see

- The Shopify card flips to **"Disconnected"** with a "Reconnect" option.
- All Shopify listings in FlipStack are marked as deleted (since FlipStack no longer has access).
- The seller gets a notification (bell icon, email, or both): "Your Shopify store has been disconnected."
- The FlipStack products themselves stay (only the Shopify listings are marked deleted).

### What might go wrong

- FlipStack keeps the connection in "Connected" state → bug. Will fail every time it tries to call Shopify.
- FlipStack tries to call Shopify and floods the logs with errors → bug.
- The seller is not notified → bug. They'd be confused why Shopify orders stop syncing.

### How to record

`TC-MKT-008-06` Pass / Fail.

---

## Test 7 of 7 — Shopify gets a fast response (no waiting)

Shopify expects a quick response when it sends a notification — within 5 seconds, or it considers the notification failed and retries. FlipStack should respond immediately and process the notification in the background.

### What to do (engineering can verify)

1. Engineering measures the response time from FlipStack to a Shopify notification.

### What you should see

- FlipStack responds in well under 1 second, regardless of how complex the actual processing is.
- The actual order processing happens in the background a few seconds later.

### What might go wrong

- Response takes more than 5 seconds → bug. Shopify will retry, leading to duplicate orders.
- Response is fast but the background processing never runs → bug.

### How to record

`TC-MKT-008-07` Pass / Fail. Engineering can confirm.

---

## Cross-reference

Engineer version: [`TC-MKT-008-shopify-webhooks.md`](TC-MKT-008-shopify-webhooks.md).
