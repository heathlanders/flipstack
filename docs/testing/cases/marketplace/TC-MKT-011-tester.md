# Tester Guide: Sync rules — control which products auto-sync

**Test ID:** TC-MKT-011
**About:** Plain-language version of [TC-MKT-011-marketplace-sync-rules.md](TC-MKT-011-marketplace-sync-rules.md). Read [`TESTER_GUIDE.md`](../../TESTER_GUIDE.md) first.

---

## What we're checking

That a seller can write rules to control which products sync to which marketplaces. For example: "only sync Electronics to eBay" or "skip products under $10." Rules combine and stack with priorities, and "first match wins."

This test is a bit more abstract than the others — sync rules don't have an obvious moment of truth on screen. The QA Lead will likely walk you through these together using a checklist.

## What you'll need before starting

- An eBay account is connected.
- You can navigate to the sync rules page (the QA Lead will tell you where — typically **Marketplaces > Sync Rules** or similar).
- You can create products in different categories and at different prices.

---

## Test 1 of 6 — Basic rule that allows a product

### What to do

1. Go to **Marketplaces > Sync Rules**.
2. Create a rule:
   - Marketplace: eBay
   - Scope: All products
   - Action: Sync
   - Active: yes
3. Save.
4. Pick any product. Trigger an inventory push or check whether the rule says "Sync" for it.

### What you should see

- The rule applies and the product is allowed to sync to eBay.
- The rules page reflects the new rule clearly.

### What might go wrong

- The rule saves but isn't applied → bug.
- The rule blocks instead of allows → bug.

### How to record

`TC-MKT-011-01` Pass / Fail.

---

## Test 2 of 6 — Rule for one marketplace doesn't affect another

### What to do

1. Create Rule A: marketplace=eBay, scope=all, action=Sync.
2. With only Rule A in place, attempt syncs to both eBay and Shopify (if Shopify is connected).
3. Check what each marketplace push does.

### What you should see

- Products sync to eBay (Rule A applies).
- For Shopify, no rule matches — the system uses its default behavior (which is "allow" by default — see test 6).

### Then add a Shopify rule

4. Create Rule B: marketplace=Shopify, scope=all, action=Sync.

### What you should see

- Now both eBay and Shopify syncs work and each is governed by its own rule.

### What might go wrong

- Rule A blocks Shopify syncs → bug. Rules are marketplace-specific.

### How to record

`TC-MKT-011-02` Pass / Fail.

---

## Test 3 of 6 — Rule scoped to a category

### What to do

1. Create Rule: marketplace=eBay, scope=Category "Electronics," action=Sync.
2. Have one product in "Electronics" and another in "Clothing."
3. Trigger or simulate an inventory push for each.

### What you should see

- Electronics product: syncs (rule matched).
- Clothing product: no rule matched → falls through to the default (allow). Mark this and confirm with the QA Lead what the expected end behavior is for non-matching products.

### What might go wrong

- Electronics product is blocked → bug, the rule was misread as "exclude."
- Clothing product is blocked → bug, only if the rule should be exclusive (it shouldn't, in this setup).

### How to record

`TC-MKT-011-03` Pass / Fail.

---

## Test 4 of 6 — Rule based on price range

### What to do

1. Create Rule: marketplace=eBay, condition=Price between $10 and $100, action=Sync.
2. Test products at: $5, $10, $50, $100, $150.
3. Trigger or check rule evaluation for each.

### What you should see

| Price | Should match? |
|---|---|
| $5 | No (below minimum) |
| $10 | Yes (boundary inclusive) |
| $50 | Yes |
| $100 | Yes (boundary inclusive) |
| $150 | No (above maximum) |

### What might go wrong

- Boundaries are exclusive instead of inclusive (e.g., $10 doesn't match) → minor bug, document the actual behavior.

### How to record

`TC-MKT-011-04` Pass / Fail.

---

## Test 5 of 6 — Multiple rules with priority — first match wins

The seller can have many rules; the system goes through them in priority order and stops at the first one that matches.

### What to do

1. Create Rule A (priority 1): marketplace=eBay, scope=Category "Electronics," action=Sync.
2. Create Rule B (priority 2): marketplace=eBay, scope=All, action=Do not sync.
3. Test with Product P (Electronics) and Product Q (Clothing).

### What you should see

| Product | Rule A matches? | Rule B matches? | Result |
|---|---|---|---|
| P (Electronics) | Yes | (skipped — A already won) | Sync allowed |
| Q (Clothing) | No | Yes | Sync blocked |

### Then swap priorities

4. Make Rule A priority 2 and Rule B priority 1. Test the same products.

### What you should see

- Now Rule B (Block all) evaluates first. It matches both products. Both are blocked.
- Rule A is never reached.

### What might go wrong

- Priority order ignored → bug.
- Multiple rules merging instead of "first match wins" → bug, makes rule logic unpredictable.

### How to record

`TC-MKT-011-05` Pass / Fail.

---

## Test 6 of 6 — When no rule matches, the default is "Sync"

The safest default is to allow syncing. If a seller doesn't set up any rules, every product should still sync. If they have rules but none match a particular product, that product should also still sync.

### What to do

1. Remove all sync rules for the test seller.
2. Pick any product. Try to sync.

### What you should see

- The product syncs without issue. The "no rules = allow everything" default kicks in.

### Then add a rule that doesn't match

3. Create a rule: marketplace=eBay, scope=Category "Electronics," action=Sync.
4. Pick a Clothing product. Try to sync.

### What you should see

- The Clothing product still syncs. The Electronics rule doesn't apply, so the default (allow) wins.

### What might go wrong

- No-rule = block by default → bug. Sellers would be confused why nothing syncs.
- Adding any rule causes everything that doesn't match it to be blocked → bug, that would force sellers to write a "catch all" rule for everything.

### How to record

`TC-MKT-011-06` Pass / Fail.

---

## Cross-reference

Engineer version: [`TC-MKT-011-marketplace-sync-rules.md`](TC-MKT-011-marketplace-sync-rules.md).
