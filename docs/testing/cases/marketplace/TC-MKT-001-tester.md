# Tester Guide: Connect a seller's eBay account

**Test ID:** TC-MKT-001
**About:** This is the plain-language version of [TC-MKT-001-ebay-connect-oauth.md](TC-MKT-001-ebay-connect-oauth.md). New to testing? Read [`TESTER_GUIDE.md`](../../TESTER_GUIDE.md) first.

---

## What we're checking

That a seller can connect their eBay account to FlipStack, that the connection actually saves, and that the seller can disconnect it later.

## What you'll need before starting

- You're logged into the test site (`develop.flipstack.app` or whichever URL the QA Lead gave you).
- You have an eBay test account ready to use. The QA Lead will tell you whether to use a sandbox or a live test account.
- The eBay account is **not yet** connected in FlipStack. (If it is, ask the QA Lead which user to test with, or disconnect it first.)
- You have the eBay test account's username and password handy.

---

## Test 1 of 6 — Click "Connect" and reach eBay's login page

### What to do

1. Go to **Settings > Integrations** in FlipStack.
2. Find the eBay card. It should show a **"Connect"** button.
3. Click **"Connect"**.

### What you should see

- After step 3, your browser leaves FlipStack and lands on an eBay page that asks you to log in or grant permission.
- The web address (URL) after step 3 starts with `auth.ebay.com` (or `signin.sandbox.ebay.com` if testing with sandbox).

### What might go wrong

- Nothing happens when you click Connect → bug.
- You get an error page on FlipStack instead of going to eBay → bug.
- You land on eBay but the page shows an error like "Invalid app credentials" → bug.

### How to record this

Mark `TC-MKT-001-01` Pass or Fail in the run log. Screenshot the eBay page you ended up on.

---

## Test 2 of 6 — Log in to eBay and come back successfully

### What to do

1. After test 1, on the eBay page, sign in with the eBay test account.
2. Approve the permissions FlipStack asks for.
3. Wait for the browser to redirect back to FlipStack.

### What you should see

- The browser returns to FlipStack on the Integrations page.
- A green "success" message appears, similar to: **"eBay account connected successfully."**
- The eBay card now shows **"Connected"** with a **"Disconnect"** button (instead of the "Connect" button).

### What might go wrong

- You return to FlipStack and see an error message → bug. Capture the exact error text.
- You return to FlipStack but the eBay card still says "Connect" → bug.
- You wait more than 30 seconds with no redirect → likely a bug. Try refreshing the FlipStack tab.

### How to record this

Mark `TC-MKT-001-02` Pass or Fail. Screenshot the eBay card showing "Connected."

---

## Test 3 of 6 — A bad return from eBay shows a friendly error

This test simulates what happens when the connection breaks halfway through. You will need help from the QA Lead or engineering to set this up. Mark it **Skipped** in the run log if you can't trigger the bad return on your own.

### What to do (with engineering's help)

1. Start a fresh "Connect" flow as in test 1.
2. Engineering will modify the return URL so eBay sends back a bad code instead of a good one.
3. Let the browser return to FlipStack.

### What you should see

- FlipStack does **not** show the green success message.
- FlipStack shows a red error message similar to: **"Failed to connect eBay account. Please try again."**
- The eBay card still says **"Connect"** — no connection was saved.
- The error message must NOT show technical details from eBay (no eBay error codes or jargon).

### What might go wrong

- FlipStack shows the technical eBay error text to the user → bug. Privacy/clarity issue.
- FlipStack shows the green success message even though the connection failed → bug.
- The page crashes or shows a 500 error → bug.

### How to record this

Mark `TC-MKT-001-03` Pass, Fail, or Skipped.

---

## Test 4 of 6 — Disconnect the eBay account

### What to do

1. Make sure the eBay account is connected (run tests 1 and 2 first if needed).
2. Go to **Settings > Integrations**.
3. On the eBay card, click **"Disconnect"**.
4. Confirm in the popup.

### What you should see

- The eBay card flips back to showing a **"Connect"** button.
- Any eBay listings that were already created (from earlier tests) are **still listed** under the products in FlipStack — they don't get deleted.
- If you go look at the seller's actual eBay account on eBay.com, the listings there are also still there. (Disconnecting doesn't remove anything from eBay — it just breaks the link.)

### What might go wrong

- Click Disconnect, nothing happens → bug.
- Existing eBay listings disappear from FlipStack → bug. (Important.)
- You see a server error after clicking Disconnect → bug.

### How to record this

Mark `TC-MKT-001-04` Pass or Fail. Screenshot before and after.

---

## Test 5 of 6 — Connection automatically renews itself

eBay connections expire after a couple of hours. The app is supposed to renew them in the background so the seller never has to reconnect manually. This test checks that the renewal works.

### What to do

This test runs in the background — there's not much for you to click. Ask the QA Lead and Engineering Owner to confirm that:

1. The seller's eBay account stays connected (the eBay card keeps saying "Connected") for 24+ hours after the initial connection without anyone pressing Reconnect.
2. The seller can list products on eBay throughout that time without seeing "you need to reconnect" errors.

You don't need to babysit this for 24 hours. Use the test cycle's existing eBay connection — if it has been alive for over a day and nothing prompted you to reconnect, that's a Pass.

### What you should see

- The eBay card stays in "Connected" state.
- Listing or syncing actions all work without authentication errors.

### What might go wrong

- The eBay card flips to "Reconnect required" within hours → bug. Engineering needs to investigate token refresh.
- A listing action fails with a "session expired" or "please log in to eBay" message → bug.

### How to record this

Mark `TC-MKT-001-05` Pass, Fail, or Blocked (if the cycle hasn't been running long enough to confirm).

---

## Test 6 of 6 — When the connection truly cannot be renewed, the seller is told

If a seller revokes FlipStack's permission from inside their eBay account (eBay > Settings > Account > Authorized Applications), or if the connection's long-term permission expires (after 18 months), the app cannot renew on its own. It needs to tell the seller and stop trying.

### What to do (with engineering's help)

1. Engineering will simulate this by either revoking the test account's permission or shortening the renewal token.
2. Wait for FlipStack's next sync attempt (or trigger one — list a product, etc.).

### What you should see

- The eBay card shows **"Re-authentication required"** with a **"Reconnect"** button.
- A notification appears for the seller (in the bell icon, by email, or both — depending on settings).
- Listing and sync actions stop and show a "please reconnect eBay" message instead of trying.

### What might go wrong

- The card still says "Connected" even though it isn't → bug. Sellers will be confused.
- Sync keeps trying anyway and floods the logs with errors → bug.
- No notification is sent → bug. Sellers won't know.

### How to record this

Mark `TC-MKT-001-06` Pass, Fail, or Blocked.

---

## Cross-reference

The engineer version of this test is [`TC-MKT-001-ebay-connect-oauth.md`](TC-MKT-001-ebay-connect-oauth.md). When in doubt, the engineer version is the source of truth — but you should not need it.
