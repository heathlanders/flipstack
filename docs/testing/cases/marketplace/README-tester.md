# Tester Test Index — Marketplace Sync v1

This is the running order for testers in the **Marketplace Sync v1** cycle. Each row points at a plain-language tester guide that walks you through one test.

**New here?** Read [`docs/testing/TESTER_GUIDE.md`](../../TESTER_GUIDE.md) first. It explains how to run a test, how to record results, and how to file a bug.

---

## In-cycle tests (run these first, in this order)

These eight tests are the heart of the cycle. Number 9 — **TC-MKT-012** — is the acceptance gate. If 12 doesn't pass, we do not release.

| # | Test | What it checks | Time |
|---|---|---|---|
| 1 | [TC-MKT-001](TC-MKT-001-tester.md) | Connect a seller's eBay account to FlipStack | ~10 min |
| 2 | [TC-MKT-006](TC-MKT-006-tester.md) | Connect a seller's Shopify store to FlipStack | ~10 min |
| 3 | [TC-MKT-002](TC-MKT-002-tester.md) | List a product on eBay from FlipStack | ~20 min |
| 4 | [TC-MKT-007](TC-MKT-007-tester.md) | List a product on Shopify from FlipStack | ~20 min |
| 5 | [TC-MKT-003](TC-MKT-003-tester.md) | Change the stock count and watch eBay update | ~15 min |
| 6 | [TC-MKT-008](TC-MKT-008-tester.md) | Buy something on Shopify and watch the stock drop everywhere | ~15 min |
| 7 | [TC-MKT-009](TC-MKT-009-tester.md) | Cross-list (one click: copy an eBay listing to Shopify) | ~15 min |
| 8 | [TC-MKT-011](TC-MKT-011-tester.md) | Sync rules — control which products auto-sync where | ~15 min |
| 9 | [TC-MKT-012](TC-MKT-012-tester.md) | **Acceptance gate** — full lifecycle: list, sell, see counts match | ~30 min |
| 10 | [TC-MKT-013](TC-MKT-013-tester.md) | Two different sellers can't see each other's data | ~20 min |

If any of 1, 2, 3, 4, 6, 7, 9, or 10 fails, the cycle cannot pass. Tests 5 and 8 are high-priority but not blockers.

## Out-of-cycle tests (run only if the QA Lead asks)

These tests cover features that are NOT part of this cycle's release. They are translated for completeness — the QA Lead will tell you if you need to run them.

| # | Test | What it checks |
|---|---|---|
| 11 | [TC-MKT-004](TC-MKT-004-tester.md) | eBay promoted listings (paying eBay to boost a listing) |
| 12 | [TC-MKT-005](TC-MKT-005-tester.md) | Import an existing eBay store's products into FlipStack |
| 13 | [TC-MKT-010](TC-MKT-010-tester.md) | Schedule a listing to publish at a future date/time |

---

## Tip: don't run them out of order

Many tests assume the test before it has already passed. For example, you can't list a product on eBay (test 3) if you haven't connected an eBay account first (test 1). If a "What you'll need before starting" item is missing, it's almost always because an earlier test was skipped.

If a test fails, file the bug, mark it Fail in the run log, and **stop running tests that depend on it.** The QA Lead will tell you when the fix is ready and which test to re-run.
