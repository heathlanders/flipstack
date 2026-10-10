# Tester Guide: Two different sellers can't see each other's data

**Test ID:** TC-MKT-013
**About:** Plain-language version of [TC-MKT-013-multi-tenant-sync-isolation.md](TC-MKT-013-multi-tenant-sync-isolation.md). Read [`TESTER_GUIDE.md`](../../TESTER_GUIDE.md) first.

---

## What we're checking

That two sellers using the same FlipStack site never see each other's data, never accidentally use each other's eBay or Shopify connections, and never receive each other's order notifications. This is a critical security and privacy test.

You will use two test seller accounts: **Seller A** and **Seller B**, each on their own FlipStack organization, each with their own connected eBay and Shopify accounts.

## What you'll need before starting

- Two test sellers, A and B, each in their own FlipStack organization.
- Both sellers have eBay connected (different eBay accounts) — TC-MKT-001 done for both.
- Both sellers have Shopify connected (different stores) — TC-MKT-006 done for both.
- Each seller has at least one product, listed on both marketplaces.
- The QA Lead can give you the URLs / IDs of Seller B's products and rules so you can try to peek.

---

## Test 1 of 5 — When Seller A pushes inventory, only Seller A's connections are used

### What to do (with engineering's help)

1. Logged in as Seller A.
2. Trigger an inventory push for one of Seller A's products.
3. Engineering watches the outgoing requests to eBay and Shopify.

### What you should see

- The eBay request uses Seller A's connection (not Seller B's).
- The Shopify request goes to Seller A's store domain (not Seller B's).
- No call uses Seller B's credentials, ever.
- All sync log entries are tagged with Seller A's organization.

### Repeat for Seller B

4. Log in as Seller B. Push inventory for one of B's products. Confirm A's connections are never used.

### What might go wrong

- Seller A's push uses Seller B's eBay connection → critical bug. Cross-org credential leak. P0.
- Sync log entries appear in the wrong organization → critical bug.

### How to record

`TC-MKT-013-01` Pass / Fail. Any failure here is P0.

---

## Test 2 of 5 — eBay notifications are routed to the right seller

When eBay sends an automatic update (e.g., an order or an account-deletion notice), FlipStack must figure out which seller it belongs to and only touch that seller's data.

### What to do (with engineering's help)

1. Engineering sends FlipStack an eBay notification carrying Seller A's eBay user ID.

### What you should see

- The notification is processed and tagged with Seller A's organization.
- Only Seller A's records get touched.
- Seller B's data is untouched.

### Then send a notification with no matching seller

2. Engineering sends a notification for an eBay user ID that doesn't match anyone.

### What you should see

- FlipStack returns a successful response (per eBay's requirements) but logs a warning: "no matching seller for this eBay user ID."
- No data anywhere gets changed.

### Then send a notification with a bad signature

3. Engineering sends a notification with a wrong signature.

### What you should see

- FlipStack rejects with an "unauthorized" response.
- Nothing gets processed.
- The rejection is logged for security.

### Then send the verification challenge

4. eBay sometimes sends a verification "are you there?" check.

### What you should see

- FlipStack responds with the correct verification value.

### What might go wrong

- A notification for Seller A's eBay user ID touches Seller B's data → critical bug. P0.
- A bad-signature notification gets accepted → critical bug. P0.

### How to record

`TC-MKT-013-02` Pass / Fail. P0 if it fails.

---

## Test 3 of 5 — Shopify notifications are signature-checked per seller

Each seller has their own Shopify secret. A notification claiming to be from Seller A's store must be signed with Seller A's secret — not Seller B's.

### What to do (with engineering's help)

1. Engineering sends a notification claiming to be from Seller A's Shopify store, signed with Seller A's secret.

### What you should see

- Accepted. Tagged with Seller A's organization. Processed.

2. Engineering sends a notification claiming to be from Seller A's Shopify store, but signed with **Seller B's secret**.

### What you should see

- Rejected. Logged as a security warning. Nothing processed.

3. Engineering sends a notification with a Shopify domain that doesn't match any seller.

### What you should see

- Rejected.

4. Engineering sends a valid Seller B notification.

### What you should see

- Accepted. Tagged with Seller B. Seller A's data untouched.

### What might go wrong

- A notification with the wrong secret gets accepted → critical bug. P0.
- A notification gets routed to the wrong seller → critical bug. P0.

### How to record

`TC-MKT-013-03` Pass / Fail.

---

## Test 4 of 5 — Seller A can't see or touch Seller B's products through the UI

### What to do

1. Log in as Seller A.
2. Go to **Marketplaces > Cross-listing**.

### What you should see

- Only Seller A's products are visible in the candidate list. Seller B's are nowhere to be seen.

### Then try to peek at Seller B's product directly

3. Get a Seller B product URL from the QA Lead (e.g., `develop.flipstack.app/marketplaces/cross-list/preview?product_id=<B-product-id>`).
4. While logged in as Seller A, paste that URL into the address bar.

### What you should see

- An error page: 403 Forbidden, 404 Not Found, or a friendly "you don't have access" page.
- **Definitely NOT** Seller B's product preview.

### Then try to act on B's product

5. Have engineering simulate a POST request to cross-list one of B's products into A's account.

### What you should see

- Rejected with 403/404. No listing is created in either seller's account.

### Then try to load B's sync rules

6. Get one of Seller B's sync rule IDs. While logged in as A, try to open the rule's edit page directly.

### What you should see

- Rejected. Rule not visible to Seller A.

### What might go wrong

- Seller A can see B's product preview → critical bug. P0.
- Seller A can act on B's product → critical bug. P0.
- Seller A can load B's sync rules → critical bug. P0.

### How to record

`TC-MKT-013-04` Pass / Fail.

---

## Test 5 of 5 — Sync rules are evaluated per seller

A rule belongs to one seller. When evaluating whether to sync Seller A's product, only Seller A's rules should be considered.

### What to do

1. Set up:
   - **Seller A's rule:** marketplace=eBay, scope=Category Electronics, action=Do not sync.
   - **Seller B's rule:** marketplace=eBay, scope=All, action=Sync.
2. Both sellers have an Electronics product.

### What you should see

- Seller A's Electronics product: blocked from syncing (A's rule applies, says "Do not sync"). Seller B's rule is irrelevant.
- Seller B's Electronics product: syncs (B's rule applies, says "Sync"). Seller A's rule is irrelevant.

### What might go wrong

- Seller B's rule blocks Seller A's product (or vice versa) → critical bug. P0. Rules are leaking across sellers.

### How to record

`TC-MKT-013-05` Pass / Fail.

---

## Why this matters

If any test in this file fails, two real customers using FlipStack could end up exposed to each other's data — products, prices, orders, eBay tokens, Shopify secrets. **Every failure here is P0.** Stop the cycle and notify the QA Lead and Engineering Owner immediately. Do not move on to other tests.

## Cross-reference

Engineer version: [`TC-MKT-013-multi-tenant-sync-isolation.md`](TC-MKT-013-multi-tenant-sync-isolation.md).
