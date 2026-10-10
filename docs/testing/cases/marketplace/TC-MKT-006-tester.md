# Tester Guide: Connect a seller's Shopify store

**Test ID:** TC-MKT-006
**About:** Plain-language version of [TC-MKT-006-shopify-connect-oauth.md](TC-MKT-006-shopify-connect-oauth.md). Read [`TESTER_GUIDE.md`](../../TESTER_GUIDE.md) first.

---

## What we're checking

That a seller can connect their Shopify store to FlipStack, that the connection saves correctly, that automatic notifications from Shopify get set up, and that the seller can disconnect cleanly.

## What you'll need before starting

- You're logged into FlipStack.
- You have a Shopify store URL ready (it ends in `.myshopify.com` — for testing, use the development store the QA Lead provides).
- You have the Shopify store's admin login.
- The Shopify store is **not yet** connected.

---

## Test 1 of 5 — Click Connect and reach Shopify's permission page

### What to do

1. Go to **Settings > Integrations** in FlipStack.
2. Find the Shopify card.
3. Type the store domain into the input (e.g., `my-test-store.myshopify.com`).
4. Click **"Connect"**.

### What you should see

- Browser redirects to a Shopify permission page hosted at the store's domain (URL begins with `https://my-test-store.myshopify.com/admin/oauth/authorize`).
- The page lists the permissions FlipStack is asking for: read/write products, read/write inventory, read/write orders, read locations.

### What might go wrong

- Nothing happens on click → bug.
- Browser gets a "Page not found" or similar error → bug, often caused by a typo in the domain. Try again.
- Permissions list is incomplete (missing some that the integration needs) → bug.

### How to record

`TC-MKT-006-01` Pass / Fail. Screenshot the Shopify permission page.

---

## Test 2 of 5 — Approve and come back successfully

### What to do

1. After test 1, on the Shopify page, click **"Install app"** or **"Approve"**.
2. Wait for the redirect back to FlipStack.

### What you should see

- Browser returns to FlipStack's Integrations page.
- A green message: **"Shopify store connected successfully."**
- The Shopify card now shows **"Connected"** with the store domain and a **"Disconnect"** button.

### What might go wrong

- You return to FlipStack with an error → bug. Capture the exact text.
- The Shopify card still says "Connect" → bug, save didn't happen.
- The browser hangs and never returns → bug.

### How to record

`TC-MKT-006-02` Pass / Fail.

---

## Test 3 of 5 — Automatic updates from Shopify get registered

When you connect a Shopify store, FlipStack tells Shopify "please notify me about these events" — orders, product updates, product deletions, and app uninstall. This test verifies those notifications are wired up.

### What to do (with engineering's help)

1. After connecting (test 2 done).
2. Engineering checks the Shopify side: log into the Shopify admin, go to **Settings > Notifications** or use the Shopify API tool to list webhooks.

### What you should see

- Four notifications (webhooks) are registered, with topics:
  - `orders/create` — new orders
  - `products/update` — product edits
  - `products/delete` — product deletions
  - `app/uninstalled` — when the seller removes FlipStack from Shopify

- Each one points to FlipStack's notification address (something ending in `/api/webhooks/shopify`).

### What might go wrong

- Fewer than 4 notifications registered → bug.
- Duplicate notifications (same event registered twice) → bug.
- Wrong notification address → bug.

### How to record

`TC-MKT-006-03` Pass / Fail. Engineering can do this part.

---

## Test 4 of 5 — Disconnect cleanly

### What to do

1. Confirm the store is connected.
2. Go to **Settings > Integrations > Shopify**.
3. Click **"Disconnect"**, confirm.

### What you should see

- The Shopify card flips back to "Connect" with the domain input.
- The previously-registered notifications get removed from the Shopify store (engineering can verify via the Shopify admin).
- Any Shopify listings that were created in FlipStack stay in FlipStack — they aren't deleted, just no longer synced.
- On the actual Shopify store, the Shopify products are not deleted.

### What might go wrong

- Notifications are NOT removed from Shopify after disconnect → bug. Shopify will keep sending events to a disconnected app.
- Existing Shopify listings disappear from FlipStack → bug. Destructive.
- The actual Shopify store products get deleted → bug. Major. Stop and notify the QA Lead.

### How to record

`TC-MKT-006-04` Pass / Fail.

---

## Test 5 of 5 — A faked / tampered return is rejected

This test confirms FlipStack doesn't accept a return from Shopify if the security check doesn't match. You'll need engineering to simulate the tampered return.

### What to do (with engineering's help)

1. Engineering starts a connect flow then modifies the return so the security check fails.
2. The browser ends up back at FlipStack with bad data.

### What you should see

- FlipStack does NOT save the connection.
- FlipStack shows an error: **"Connection failed: invalid session. Please try again."**
- Nothing leaks to the user about the technical reason.
- The attempt is recorded internally for security review (engineering can confirm).

### What might go wrong

- FlipStack accepts the bad return and saves a corrupted connection → bug. Major security issue.
- The error message exposes technical details that confuse or alarm the user → bug.

### How to record

`TC-MKT-006-05` Pass / Fail.

---

## Cross-reference

Engineer version: [`TC-MKT-006-shopify-connect-oauth.md`](TC-MKT-006-shopify-connect-oauth.md).
