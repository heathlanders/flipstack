# Tester Guide — FlipStack

Welcome. This guide is for testers who do not write code. It explains what we are testing, how to run a test, what to write down, and how to report a problem when something goes wrong.

If you have any question that this guide does not answer, ask the QA Lead.

---

## 1. What you are doing and why

FlipStack connects a seller's products to online marketplaces like eBay and Shopify. It lists products, keeps the stock count in sync, imports orders, and tells the seller what is happening.

Before we release new features to real sellers, we run them through the "Marketplace Sync v1" test cycle to make sure they behave correctly when a real person uses them. Your job as a tester is to follow each test step in order, see what actually happens on the screen, and write down whether it matched what was expected.

You are not expected to know how the system works inside. You are expected to be observant and honest about what you see.

---

## 2. Where to find each test

There is one tester guide file per test. They live in:

```
docs/testing/cases/marketplace/TC-MKT-NNN-tester.md
```

Start with `docs/testing/cases/marketplace/README-tester.md` — that is the index. It lists every test in the suggested order. The index also marks which tests are part of the current cycle and which are extra.

Each test file has the same shape:

| Section | What it tells you |
|---|---|
| **What we're checking** | One plain-English sentence describing the goal. |
| **What you'll need before starting** | Things that must already be set up (an account, a product, etc.). If anything is missing, stop and ask the QA Lead. |
| **What to do** | Numbered steps. Each step is one click or one observation. |
| **What you should see** | What is supposed to happen after each step. |
| **What might go wrong** | Common problems and what to write down if you see them. |
| **How to record this** | Where to mark the result. |

---

## 3. How to record results

For every test, you write one of four results in the cycle's run log. The QA Lead will tell you the exact run-log file (typically a shared spreadsheet or a Markdown file in `docs/testing/runs/`).

| Result | When to use it |
|---|---|
| **Pass** | You finished every step and what you saw matched "What you should see" exactly. |
| **Fail** | You finished the steps but something did not match — the screen showed the wrong number, an error appeared, a button did nothing, the page broke. File a bug. See section 5. |
| **Blocked** | You couldn't even start the test. Something needed in "What you'll need before starting" was missing or broken. Tell the QA Lead. |
| **Skipped** | The QA Lead told you to skip this test for this cycle. |

A test is **Pass only if every single step passed.** If steps 1 through 4 worked but step 5 failed, the whole test is Fail — not "mostly passed."

---

## 4. What evidence to collect

For every test, no matter the result, capture evidence. You will paste it into the run log and (if the test fails) into the bug report.

Always capture:

- **A screenshot of the screen at the end of the test.** Even on a Pass.
- **The full URL in the address bar** at the moment something went wrong.
- **The exact text of any error message** — copy/paste it, do not retype it. Take a screenshot too.
- **The date and time** you ran the test (your computer clock is fine).
- **Which browser** you used (Chrome, Safari, Firefox, etc.) and whether you were in a private/incognito window.

For a Fail, also capture:

- **A screenshot of the screen right BEFORE the failure** — what was the last thing that looked correct?
- **A screenshot of the screen AT the failure** — what does broken look like?
- **Steps you took, in order**, even if it's the same as the test file. Sometimes you click something the file didn't tell you to click.

For tests that involve eBay or Shopify (most of them), capture screenshots of those external sites too — for example, the eBay listing page or the Shopify admin product page.

Store screenshots in a folder named after the test, e.g. `evidence/TC-MKT-012/01-before-order.png`.

---

## 5. How to file a bug

When a test fails, file a bug right away. Do not move on to the next test hoping someone else will catch it.

Open a new GitHub issue using the **Bug Report** template (the QA Lead will give you the link). Fill in:

| Field | What to write |
|---|---|
| **Title** | One short sentence: *what* broke and *where*. Example: "Cross-list to Shopify button does nothing on product detail page." |
| **Which test** | The test ID, e.g. `TC-MKT-012-01`. |
| **What I did** | The numbered steps from the test, plus anything extra you clicked. |
| **What I expected** | Copy the matching line from "What you should see" in the test file. |
| **What actually happened** | Plain description of what you saw. |
| **Evidence** | Attach screenshots and paste error text. |
| **Severity** | Pick one — see the table below. |
| **Browser & date** | E.g. "Chrome 120, 2026-05-01 14:30". |

### Severity (how bad is it?)

You don't need to know the technical impact — just describe how it felt to use.

| Level | Meaning | Examples |
|---|---|---|
| **P0 – Blocker** | A core feature is completely broken. No one could use the app this way. | Site won't load. Connecting eBay always fails. Listing a product crashes the page. |
| **P1 – Major** | An important feature is broken or misbehaves badly. | Stock count shows the wrong number after an order. eBay listing has the wrong price. |
| **P2 – Moderate** | The feature works but has a noticeable problem. | A button is in the wrong place. An error message is confusing. Page is slow. |
| **P3 – Minor** | Cosmetic or polish issue. | A typo. A missing icon. A column is too narrow. |

When in doubt, pick the higher severity. Engineering can lower it later if needed. Better to flag too loud than too quiet.

### Good vs. bad bug descriptions

**Bad:** "It's broken."

**Good:** "On the product detail page for 'Test Widget' (URL: flipstack.app/inventory/products/abc-123), I clicked the 'Sync to eBay' button. The button greyed out for 1 second then went back to normal. No success message appeared. The eBay section of the page did not change. After waiting 5 minutes and refreshing, no eBay listing was created."

The good one tells engineering exactly where you were, what you did, and what you saw. The bad one forces them to ask three follow-up questions.

---

## 6. Stuck? Ask.

| If you can't... | Ask |
|---|---|
| Log into the test site | The QA Lead |
| Find a screen the test mentions | The QA Lead |
| Tell whether a result is Pass or Fail | The QA Lead |
| Understand a word in the test | This Glossary (section 7), then the QA Lead |
| Get a connected eBay or Shopify account to work | Engineering Owner |
| Tell whether a bug is severe or minor | File it with your best guess; the Engineering Owner will adjust |

The QA Lead's name and contact are at the top of the cycle's run log file.

---

## 7. Glossary

Plain-English definitions for words that show up in the tests.

| Word | What it means here |
|---|---|
| **Marketplace** | A selling site — eBay, Shopify, Amazon, etc. |
| **Listing** | A single product as it appears on a marketplace. The same product can have a listing on eBay AND a listing on Shopify. |
| **SKU** | The seller's internal product code. Example: `WH-100`. Used to match the same product across systems. |
| **Inventory / quantity / stock** | How many of a thing the seller has on hand. |
| **"Available" count** | How many are still up for sale (i.e. not already on hold for someone else's order). This is the number that gets sent to eBay and Shopify. |
| **Reserved** | Already on hold for an order in progress. Counts against "Available." |
| **Connect / "connect your account"** | Linking the seller's eBay or Shopify account to FlipStack so the app can read and write on their behalf. The connection is the saved permission. (Engineers call this "OAuth.") |
| **Disconnect** | Removing that saved permission. |
| **Reconnect / re-authentication needed** | The saved permission expired or was revoked. The seller has to log in again. |
| **Cross-list** | Take a product that is already listed on one marketplace and list it on another in one click. |
| **Sync** | Keeping two sides matching. Inventory sync = the count is the same in FlipStack, eBay, and Shopify. Price sync = the price matches. |
| **Background task** | Work the system does behind the scenes after you click a button. Most listing and inventory updates run as background tasks — you click "Sync," the app says "OK," and the actual eBay/Shopify update finishes a few seconds later. |
| **Automatic update from eBay/Shopify** | When eBay or Shopify tells FlipStack about something that just happened (an order arrived, a product was edited on Shopify directly, etc.). Engineers call this a "webhook." |
| **Sandbox / dev store** | A pretend version of eBay or Shopify used for testing. Real money does not change hands. Test orders do not get shipped. |
| **Production / live** | The real eBay or Shopify. Real listings, real buyers, real money. |
| **Organization / tenant** | A seller's account in FlipStack. Two different sellers using FlipStack each have their own organization. They must never see each other's data. |
| **Acceptance gate** | The single most important test in the cycle. If it fails, we cannot release. (`TC-MKT-012` is the acceptance gate for this cycle.) |
| **Hash check / "no change detected"** | A shortcut the app uses to skip pointless updates. If the product's data hasn't changed since the last sync, the app does not bother sending it to eBay/Shopify again. You will see this in a few tests where no API calls happen on a re-sync — that is correct. |
| **Rate limit** | eBay and Shopify only allow so many requests per second. The app waits between requests to stay polite. If you see "rate limit exceeded," the app went too fast — usually a bug. |

---

## 8. The cycle in one picture

This is what the QA Lead is doing while you test:

```
Backlog -> Ready to Test -> In Progress -> [Pass: Verified] -> Done
                                       \-> [Fail: Blocked / Bug Found] -> (fix) -> retest
```

You will mostly see "Ready to Test" tests on your plate. You move them to "In Progress" when you start, and to "Verified" (Pass) or "Blocked / Bug Found" (Fail) when you finish. The QA Lead handles the rest.

---

## 9. Before you start your first test

Check this list:

- [ ] **If this is your first time on FlipStack, read [SANDBOX_GUIDE.md](SANDBOX_GUIDE.md) first.** It explains what the app does, what each test user role is for, and walks through an end-to-end flow you can run solo.
- [ ] You have login access to the test site (the QA Lead will give you the URL — usually `develop.flipstack.app` for staging or a similar address).
- [ ] You know which user account to log in as. The staging site has one account per role under the **FlipStack Testing** org; the credentials matrix is in [docs/DEVELOP_TESTING_SEEDER.md](../DEVELOP_TESTING_SEEDER.md).
- [ ] You know whether the test cycle uses **sandbox** or **live** eBay/Shopify accounts. Tests on a live account can affect real listings — be careful.
- [ ] You have the run-log file open so you can record results as you go.
- [ ] You have a screenshot tool ready (your operating system's built-in is fine).
- [ ] You read `README-tester.md` so you know which tests to run and in what order.

You're ready. Open `README-tester.md` and start with the first test.
