# FlipStack Sandbox Guide for QA Testers

Welcome. This is the doc you read **first** when you start testing FlipStack on the develop staging site. It explains what FlipStack does, what "sandbox" means here, who each test user is, what they are supposed to do, and how to run a guided end-to-end walkthrough on your own.

This guide is for **non-technical testers**. You should be able to read it cold and start clicking inside thirty minutes.

For test execution mechanics (how to record Pass/Fail, how to file a bug, glossary of jargon), see [TESTER_GUIDE.md](TESTER_GUIDE.md). This guide covers everything that comes **before** you open a specific test case.

---

## Table of contents

1. [What FlipStack is](#1-what-flipstack-is)
2. [What "sandbox" means here](#2-what-sandbox-means-here)
3. [First-time login](#3-first-time-login)
4. [Pick your role](#4-pick-your-role)
5. [Per-role walkthrough](#5-per-role-walkthrough)
6. [End-to-end sandbox journey](#6-end-to-end-sandbox-journey)
7. [Connecting eBay and Shopify sandbox accounts](#7-connecting-ebay-and-shopify-sandbox-accounts)
8. [What is NOT pre-loaded](#8-what-is-not-pre-loaded)
9. [Bug or sandbox limitation?](#9-bug-or-sandbox-limitation)
10. [Who to ask](#10-who-to-ask)

---

## 1. What FlipStack is

FlipStack is a tool for resellers — businesses that buy used or surplus goods, list them online, and ship them out.

In one workflow:

1. A **Buyer** sources a product (estate sale, liquidation, supplier shipment) and adds it to FlipStack's **Intake Queue**.
2. AI research suggests title, category, and price. A **Lister** reviews it and pushes the listing to **eBay**, **Shopify**, and **Amazon** in one click.
3. When a buyer on eBay or Shopify places an order, FlipStack **imports the order automatically**, decreases the available stock everywhere, and tells the **Fulfillment** team to pick, pack, and ship.
4. **Reports** show the seller what made money, what is selling fast, and what is sitting on the shelf.

A single seller's data lives inside one **Organization**. Two different sellers using FlipStack each have their own organization and never see each other's data. The sandbox uses one shared org called **FlipStack Testing**.

---

## 2. What "sandbox" means here

The staging site `develop.flipstack.app` is wired to **fake** versions of eBay, Shopify, and Amazon. Nothing you do here costs money, ships a real package, or shows up to a real buyer.

| Real production (`flipstack.app`) | Develop sandbox (`develop.flipstack.app`) |
|---|---|
| Real eBay seller account | eBay developer sandbox seller |
| Real Shopify store | Shopify Partner dev store |
| Real Amazon Seller Central | Amazon SP-API sandbox |
| Real money / real buyers | No money, fake test buyers |
| Real ship labels | Sandbox label PDFs (do not ship) |

### Sandbox limitations you will see (these are NOT bugs)

- **eBay search returns nothing.** eBay's sandbox does not support the Finding API. Any search-based feature will look empty.
- **eBay Browse / UPC lookup is stubbed.** When you scan a barcode or look up a product by UPC, the sandbox returns minimal or stub data. Real lookups happen in production only.
- **eBay Promoted Listings stats are stubbed.** The Promoted Listings dashboard will show zeros or placeholder data. The Marketing API is not exposed in eBay's sandbox.
- **Sandbox seller starts empty.** Sandbox eBay accounts have no fulfillment policies, no payment policies, and no merchant location until someone seeds them. If listing publish fails with "policy missing," that is a setup gap, not a code bug — flag the QA Lead.
- **Test orders never physically ship.** Even after you "ship" in FlipStack, no carrier picks anything up.

If you see one of these and aren't sure whether it is real-bug or sandbox-quirk, see [section 9](#9-bug-or-sandbox-limitation).

---

## 3. First-time login

### URL

```
https://develop.flipstack.app/login
```

### Shared password

All test users share one password:

```
FlipStackQA2026!
```

### Email pattern

Every test user's email is `{role_slug}tester@flipstack.app`. So the buyer is `buyertester@flipstack.app`, the lister is `listertester@flipstack.app`, and so on.

Full credentials matrix and what the seeder creates is in [DEVELOP_TESTING_SEEDER.md](../DEVELOP_TESTING_SEEDER.md).

### The first few seconds

After you log in:

- **Most users** land on a role-specific dashboard (Owner, Buyer, Lister, Fulfillment, or Support).
- **Supplier users** are redirected to the separate Supplier Portal at `/supplier`.
- **Super admin** lands on the Owner dashboard but has extra system menus visible.

If you log in and see a dashboard that is empty, that is expected — the sandbox is seeded with users and a warehouse layout but **no products, no orders, no customers, no listings**. You are supposed to create that data yourself while testing. See [section 8](#8-what-is-not-pre-loaded).

---

## 4. Pick your role

The FlipStack Testing org has **14 users**, one for each role. Pick the role that matches the test you are running.

| Family | Role | Login | Lands on |
|---|---|---|---|
| Leadership | super_admin | `super_admintester@flipstack.app` | Owner dashboard + system menus |
| Leadership | organization_owner | `organization_ownertester@flipstack.app` | Owner dashboard |
| Leadership | admin | `admintester@flipstack.app` | Owner dashboard |
| Leadership | manager | `managertester@flipstack.app` | Owner dashboard |
| Buying | buyer | `buyertester@flipstack.app` | Buyer dashboard |
| Buying | sourcing_specialist | `sourcing_specialisttester@flipstack.app` | Buyer dashboard |
| Listing | lister | `listertester@flipstack.app` | Lister dashboard |
| Listing | listing_specialist | `listing_specialisttester@flipstack.app` | Lister dashboard |
| Fulfillment | fulfillment_manager | `fulfillment_managertester@flipstack.app` | Fulfillment dashboard |
| Fulfillment | shipper | `shippertester@flipstack.app` | Fulfillment dashboard |
| Support | support_agent | `support_agenttester@flipstack.app` | Support dashboard |
| Support | employee | `employeetester@flipstack.app` | Basic dashboard |
| Supplier | supplier_admin | `supplier_admintester@flipstack.app` | Supplier portal |
| Supplier | supplier_staff | `supplier_stafftester@flipstack.app` | Supplier portal (limited) |

### Switching roles

To switch user, **log out** (top-right user menu → Sign out) and log back in as the new email. Do not try to test multiple roles in two browser tabs at once — Laravel sessions overwrite each other and you will get confused.

A clean trick: keep one browser **profile** for each role you test often. For example, Chrome's "Profile" feature lets you keep four logged-in tabs side by side without conflict.

---

## 5. Per-role walkthrough

For each role: who they are, what dashboard they see, what they can do, what is hidden from them, and a five-minute smoke test you can run to confirm the role still works.

---

### 5.1 Super Admin

**Login:** `super_admintester@flipstack.app`
**Dashboard:** Owner (with extra system menus)

**Who they are:** A FlipStack staff member, not a customer. Super Admin can see across **every** organization, impersonate users, manage system-wide AI prompts, and inspect audit logs.

**Top things they can do:**
- View all organizations on the platform
- Impersonate any user (without knowing their password)
- Manage global eBay category mappings
- Manage AI prompts used by the research pipeline
- View global audit logs

**What is hidden:** Nothing. Super Admin has every permission.

**Five-minute smoke test:**
1. Log in. Confirm dashboard loads without 500.
2. Open the user menu and look for an "Admin" or "System" section in the sidebar.
3. Open the user list and confirm you can see users from organizations other than FlipStack Testing.
4. Log out.

---

### 5.2 Organization Owner

**Login:** `organization_ownertester@flipstack.app`
**Dashboard:** Owner

**Who they are:** The seller themselves — the person whose business this is. They signed up, they pay the bill, they own everything.

**Top things they can do:**
- Manage organization settings, billing, branding
- Invite and manage all users
- Connect / disconnect eBay, Shopify, Amazon, ShippingEasy
- Set SKU prefix, default currency, timezone
- View all reports and dashboards

**What is hidden:** Cross-organization views (only Super Admin sees those).

**Five-minute smoke test:**
1. Log in. Owner dashboard loads with revenue / order / inventory tiles (mostly zeroes since sandbox is empty).
2. Sidebar → **Settings** → confirm General, Team, Integrations tabs all load.
3. Sidebar → **Integrations** → confirm eBay, Shopify, Amazon connect buttons all visible.
4. Click "Manage Teams" quick action → team list page loads.
5. Log out.

---

### 5.3 Admin

**Login:** `admintester@flipstack.app`
**Dashboard:** Owner

**Who they are:** A trusted senior employee. Same dashboard as the Owner, slightly fewer powers — mainly cannot delete the organization, manage billing, or change ownership.

**Top things they can do:**
- Same as Owner for day-to-day operations
- User management, role assignment, team management
- Inventory + product CRUD
- Orders management
- Reports + analytics

**What is hidden:**
- Billing pages
- Organization deletion
- Owner transfer

**Five-minute smoke test:** Same as Organization Owner. Then try to access **Settings → Billing** — should be hidden or show a permission denied.

---

### 5.4 Manager

**Login:** `managertester@flipstack.app`
**Dashboard:** Owner

**Who they are:** A team lead — runs day-to-day operations, has admin-like access without organization-level controls.

**Top things they can do:**
- Inventory + product CRUD
- Orders management
- Intake queue processing
- Reports
- User management (limited — invite, edit, but not delete)

**What is hidden:**
- Integrations management (cannot connect/disconnect marketplaces)
- Organization settings

**Five-minute smoke test:**
1. Log in. Owner dashboard loads.
2. Sidebar → **Inventory** → product list opens.
3. Sidebar → **Orders** → orders list opens.
4. Try **Settings → Integrations** — should be hidden or read-only.
5. Log out.

---

### 5.5 Buyer

**Login:** `buyertester@flipstack.app`
**Dashboard:** Buyer

**Who they are:** A sourcer. They go to estate sales, liquidations, or work with suppliers, and dump everything they bought into FlipStack so the listing team can take it from there.

**Top things they can do:**
- Add items to the **Intake Queue** (manual entry, barcode scan, photo upload)
- View ROI analysis on past purchases
- Manage suppliers + supplier products
- Create purchase orders (procurement)
- View product catalog

**What is hidden:**
- Order management
- Marketplace integrations management
- Team / role management
- Fulfillment and shipping screens

**Five-minute smoke test:**
1. Log in. Buyer dashboard loads with three quick action tiles: ROI Analysis, Add Products, Suppliers.
2. Click **Add Products** → product create form opens.
3. Sidebar → **Intake Queue** (or equivalent) → empty list with "Add Item" button visible.
4. Sidebar → **Suppliers** → empty list, "Add Supplier" button visible.
5. Try sidebar **Orders** — should be hidden.
6. Log out.

---

### 5.6 Sourcing Specialist

**Login:** `sourcing_specialisttester@flipstack.app`
**Dashboard:** Buyer

**Who they are:** Same job family as Buyer. This role exists for orgs that want to differentiate "buyer" (someone with purchasing authority) from "sourcing specialist" (a researcher / scout).

**Top things they can do:** Same surface as Buyer.

**What is hidden:** Same as Buyer.

**Five-minute smoke test:** Same as Buyer.

> **Note for testers:** When a test case says "log in as a buyer," either user works. If the test specifically says "as a sourcing specialist," use this login.

---

### 5.7 Lister

**Login:** `listertester@flipstack.app`
**Dashboard:** Lister

**Who they are:** The cataloger. They take raw items from the intake queue, finish the listing details (title, category, photos, price), and publish to marketplaces.

**Top things they can do:**
- Process items from the **Intake Queue**
- Edit products (title, description, photos, identifiers)
- Push listings to eBay, Shopify, Amazon
- Use listing templates
- View intake queue analytics

**What is hidden:**
- Order management
- Fulfillment / shipping
- Procurement / purchase orders
- Team management
- Marketplace OAuth (cannot connect/disconnect — only use already-connected)

**Five-minute smoke test:**
1. Log in. Lister dashboard loads with Intake Queue, Create Listing, Templates quick actions.
2. Click **Intake Queue** → list opens.
3. Click **Create Listing** → product create form opens.
4. Sidebar → **Templates** → listing templates list opens.
5. Try sidebar **Orders** — should be hidden.
6. Log out.

---

### 5.8 Listing Specialist

**Login:** `listing_specialisttester@flipstack.app`
**Dashboard:** Lister

**Who they are:** Same job family as Lister. Variant role for orgs that want a senior cataloger title.

**Top things they can do:** Same as Lister.

**What is hidden:** Same as Lister.

**Five-minute smoke test:** Same as Lister.

---

### 5.9 Fulfillment Manager

**Login:** `fulfillment_managertester@flipstack.app`
**Dashboard:** Fulfillment

**Who they are:** Runs the warehouse — supervises pickers, decides which orders get shipped first, configures shipping rules and routing.

**Top things they can do:**
- View and process all orders
- Pick / pack / ship workflow
- Buy and print shipping labels
- Configure fulfillment routing rules (which warehouse ships what)
- Configure shipping rules (which carrier for which order)
- Process returns
- View inventory + manage stock counts

**What is hidden:**
- Marketplace OAuth management
- Team / role management
- Procurement
- Intake queue (read-only at most)

**Five-minute smoke test:**
1. Log in. Fulfillment dashboard loads with Orders, Shipping, Returns tiles.
2. Click **Orders** → orders list opens (likely empty in fresh sandbox).
3. Sidebar → **Shipping** → shipping/labels page opens.
4. Sidebar → **Returns** → returns list opens.
5. Sidebar → **Fulfillment Routing Rules** → rules list opens.
6. Try sidebar **Settings → Integrations** — should be hidden.
7. Log out.

---

### 5.10 Shipper

**Login:** `shippertester@flipstack.app`
**Dashboard:** Fulfillment

**Who they are:** A warehouse picker / packer. Same dashboard as Fulfillment Manager, fewer admin powers — they execute, they don't configure.

**Top things they can do:**
- View orders
- Pick / pack / ship
- Update stock counts (manual adjustments)
- View inventory

**What is hidden:**
- Routing rules / shipping rules configuration
- Order editing (only status updates allowed)
- Returns processing (depending on team setup)

**Five-minute smoke test:**
1. Log in. Fulfillment dashboard loads.
2. Open Orders list — should be visible.
3. Try opening **Fulfillment Routing Rules** — should be hidden or read-only.
4. Log out.

---

### 5.11 Support Agent

**Login:** `support_agenttester@flipstack.app`
**Dashboard:** Support

**Who they are:** Customer service. Reads buyer messages on eBay / Shopify, answers them through FlipStack, handles return requests, looks up orders for buyers.

**Top things they can do:**
- View and reply to **Marketplace Messages** (eBay buyer messages, etc.)
- View orders (read-only)
- View returns
- View customer CRM
- View product catalog (read-only)

**What is hidden:**
- Inventory editing
- Listing creation
- Marketplace OAuth
- Team management

**Five-minute smoke test:**
1. Log in. Support dashboard loads with Messages, Orders, Returns tiles.
2. Click **Messages** → buyer message inbox opens (likely empty in sandbox).
3. Sidebar → **Customers** → customer list opens.
4. Sidebar → **Orders** → orders list opens (read-only).
5. Try sidebar **Inventory → Add Product** — should be hidden or disabled.
6. Log out.

---

### 5.12 Employee

**Login:** `employeetester@flipstack.app`
**Dashboard:** Basic

**Who they are:** A general employee with minimal access — view-only on most things. Useful for testing that gated features really are gated.

**Top things they can do:**
- View inventory (read-only)
- View product catalog (read-only)
- View their own profile
- View basic analytics

**What is hidden:** Almost everything else. No order management, no listing creation, no team management, no integrations.

**Five-minute smoke test:**
1. Log in. **Basic** dashboard loads (different layout — fewer tiles, mostly view-only quick links).
2. Sidebar should be visibly shorter than other roles.
3. Try clicking around — most action buttons should be missing or disabled.
4. Log out.

---

### 5.13 Supplier Admin

**Login:** `supplier_admintester@flipstack.app`
**Dashboard:** Supplier Portal (`/supplier`)

**Who they are:** A representative from an outside supplier company that ships product to the FlipStack seller. They use a separate **Supplier Portal**, not the main app, so they only ever see their own data — never the seller's full inventory.

**Top things they can do:**
- View settlements (what the seller owes them and when)
- Dispute a settlement
- View their own performance scorecard
- Submit new product proposals to the seller
- Manage their own portal settings + notification preferences
- Complete onboarding (upload documents, banking info, etc.)

**What is hidden:** Everything outside their own supplier scope. They cannot see other suppliers, the seller's full inventory, the seller's other suppliers' settlements, or anything in the main app.

**Five-minute smoke test:**
1. Log in. Should redirect to `/supplier` portal, **not** `/dashboard`.
2. Layout looks different from main app — separate sidebar, supplier branding.
3. Sidebar shows: Dashboard, Settlements, Performance, Submissions, Settings.
4. Open **Submissions** → "Submit New Product" button visible.
5. Open **Settings** → can edit own profile, notification rules.
6. Try going to `/dashboard` directly in URL — should redirect back to `/supplier`.
7. Log out.

---

### 5.14 Supplier Staff

**Login:** `supplier_stafftester@flipstack.app`
**Dashboard:** Supplier Portal (limited)

**Who they are:** A junior employee at the same supplier company. Same portal as Supplier Admin, even narrower scope.

**Top things they can do:**
- View dashboard
- View performance
- View notifications

**What is hidden:**
- Settlements (cannot see money figures)
- Settings editing
- Product submission (depending on supplier config)

**Five-minute smoke test:**
1. Log in. Lands on `/supplier`.
2. Sidebar is visibly shorter than Supplier Admin's — no Settlements link, no Settings link.
3. Try `/supplier/settlements` directly in URL — should be hidden or denied.
4. Log out.

---

## 6. End-to-end sandbox journey

This is a one-person guided tour that touches **every major flow** in FlipStack by switching between roles. It takes about an hour. Run it whenever you want to confirm the whole stack still works after a deploy.

You will need:
- The 14 logins from [section 4](#4-pick-your-role) (or just the 5 below).
- An eBay sandbox seller and Shopify dev store already connected to the FlipStack Testing org. If they aren't, see [section 7](#7-connecting-ebay-and-shopify-sandbox-accounts) or ask the QA Lead.

### Step 1 — Owner connects marketplaces

1. Log in as `organization_ownertester@flipstack.app`.
2. **Settings → Integrations**. Confirm eBay and Shopify show as **Connected** (green badge).
3. If not connected, follow [section 7](#7-connecting-ebay-and-shopify-sandbox-accounts).
4. Log out.

### Step 2 — Buyer adds an item to intake

1. Log in as `buyertester@flipstack.app`.
2. **Add Products** quick action.
3. Fill in: Title = "Sandbox Test Widget", SKU auto-generated, Quantity = 5, Cost = $10.
4. Save. Confirm it appears in the intake queue (or in product list, depending on flow).
5. Log out.

### Step 3 — Lister publishes to marketplaces

1. Log in as `listertester@flipstack.app`.
2. **Intake Queue** → find "Sandbox Test Widget" → process it.
3. Confirm AI suggested category, title polish, price (or fill in manually if AI is empty).
4. Click **Publish to eBay** and **Publish to Shopify**.
5. Wait ~30 seconds (background jobs run).
6. Refresh. The product page should show eBay and Shopify listing IDs.
7. Open the eBay sandbox seller account in another tab — listing should be visible there.
8. Log out.

### Step 4 — Order arrives (simulate)

1. Open the eBay sandbox **buyer** test account in another tab.
2. Buy 1 of "Sandbox Test Widget" using the sandbox checkout.
3. Wait ~1 minute for FlipStack to import the order (background job + webhook).
4. Log in to FlipStack as `fulfillment_managertester@flipstack.app`.
5. **Orders** → confirm the new order appears.
6. Confirm the product's stock dropped from 5 to 4 in FlipStack.
7. Open the Shopify dev store admin → confirm the same product's stock there also shows 4 (cross-marketplace sync).

### Step 5 — Fulfillment ships

1. Still logged in as Fulfillment Manager.
2. Open the order → click **Pick** → confirm pick → click **Pack** → click **Ship**.
3. Generate a sandbox label. Sandbox labels are PDFs with a "TEST" watermark — they will not actually ship.
4. Mark order as shipped.
5. Confirm the eBay sandbox order shows tracking uploaded.
6. Log out.

### Step 6 — Support handles a question

1. Log in as `support_agenttester@flipstack.app`.
2. **Messages** → if a buyer message synced from eBay sandbox, reply to it.
3. **Customers** → confirm the buyer that just placed the order shows up as a Customer.
4. Log out.

### Step 7 — Owner reviews

1. Log in as `organization_ownertester@flipstack.app`.
2. **Analytics → Profit & Loss** → confirm the sandbox order shows up with cost, revenue, fees.
3. **Analytics → Hub** → confirm the executive dashboard shows non-zero numbers.
4. Log out.

If every step worked, the core sandbox is healthy. If any step failed, file a bug per [TESTER_GUIDE.md section 5](TESTER_GUIDE.md#5-how-to-file-a-bug) and tag it `[E2E]`.

---

## 7. Connecting eBay and Shopify sandbox accounts

If the test cycle lands on a fresh sandbox where eBay or Shopify is not yet connected, here is how to wire it up. This part is for the QA Lead or a senior tester — engineers should have done it once already.

### eBay sandbox

1. Log in as `organization_ownertester@flipstack.app`.
2. **Settings → Integrations → eBay → Connect**.
3. You are sent to `https://signin.sandbox.ebay.com` (note the `sandbox` in the URL).
4. Log in with the **eBay sandbox seller** credentials. The QA Lead has these — they live in 1Password / shared vault, NOT in this guide.
5. Approve the permissions.
6. You are returned to FlipStack. Confirm a green "Connected" badge.
7. Confirm the integration row shows `environment: sandbox` (engineer may need to verify in the database).

### Shopify dev store

1. Same path: **Settings → Integrations → Shopify → Connect**.
2. Enter the dev store URL when asked, e.g. `flipstack-dev.myshopify.com`.
3. Approve the install on Shopify's side.
4. Returned to FlipStack with a green "Connected" badge.
5. Confirm the integration row shows `environment: sandbox`.

### Amazon SP-API sandbox

Amazon production is gated behind an app approval process that takes weeks. The sandbox is wired up but limited. Connect via **Settings → Integrations → Amazon → Connect** and follow on-screen prompts. If anything looks off, this is the most likely surface to trip — flag the QA Lead.

### What you should NOT do

- **Do not** connect a real eBay seller or real Shopify store on `develop.flipstack.app`. The dev environment is configured for sandbox only and connecting a real account here will fail or produce confusing data.
- **Do not** test with a sandbox account that other testers may also be using simultaneously. Coordinate with the QA Lead.

---

## 8. What is NOT pre-loaded

The sandbox seeder creates **users + warehouse layout + integration stubs** and nothing else. By design.

| Pre-loaded | Not pre-loaded |
|---|---|
| 14 test users | Products |
| 1 organization (FlipStack Testing) | Orders |
| 1 supplier (Test Supplier Co) | Customers |
| 1 warehouse + 1 zone + 3 bins | eBay listings |
| eBay / Shopify / Amazon credential rows (inactive) | Shopify listings |
| | Intake queue items |
| | Marketplace messages |
| | Email campaigns |

This is intentional — every test starts from a known empty state, so you can build exactly the data the test needs and not worry about leftover noise.

If you want to seed product / order test data on top, ask the QA Lead about the planned `flipstack:seed-testing-data` command (still on the deferred roadmap).

### Resetting the sandbox

If sandbox state gets too messy, the QA Lead or an engineer can run:

```bash
docker exec flipstack_staging_app_1 php artisan flipstack:seed-testing --reset
```

That wipes the FlipStack Testing org and re-seeds it from scratch. Warn other testers first — they will need to log in again.

---

## 9. Bug or sandbox limitation?

When something doesn't work, ask yourself **was this caused by the sandbox or by the code?** before filing a bug.

| Symptom | Bug or sandbox? | What to do |
|---|---|---|
| eBay search returns no results | **Sandbox.** Finding API is not in eBay sandbox. | Skip / note in test, do not file. |
| UPC barcode lookup returns stub data | **Sandbox.** Browse + UpcLookup are stubbed. | Skip / note. |
| Promoted Listings dashboard shows zeros | **Sandbox.** Marketing API is stubbed. | Skip / note. |
| eBay listing publish fails: "missing fulfillment policy" | **Setup gap.** Sandbox seller wasn't seeded with policies. | Tell QA Lead. |
| Cross-marketplace stock sync didn't happen | **Bug.** This is core feature, must work in sandbox. | File bug. |
| Order didn't import after sandbox checkout | **Bug.** This is core feature, must work in sandbox. | File bug. |
| Test order didn't physically ship | **Sandbox.** No carrier picks up sandbox labels. | Expected. |
| Page returns 500 error | **Bug.** Always file. | File bug, screenshot the error. |
| Page returns 403 / "permission denied" | **Probably correct.** Check the role's "What is hidden" in section 5. | Verify against role definition before filing. |
| Shopify dev store didn't update | **Could be either.** Check the integration row is `environment: sandbox` and active. | Ask QA Lead first. |

When in doubt: file the bug, mark it P2, let engineering decide. Better to over-flag than miss a real issue.

---

## 10. Who to ask

| If you can't... | Ask |
|---|---|
| Log in to develop.flipstack.app | QA Lead |
| Find a screen the test mentions | QA Lead |
| Decide if a result is Pass or Fail | QA Lead |
| Get an eBay sandbox or Shopify dev store credential | QA Lead (they hold the shared credentials) |
| Tell whether a bug is severe or minor | File it with your best guess; engineering will adjust |
| Tell whether something is a bug or a sandbox limit | Cross-check section 9, then ask QA Lead |
| Reset the sandbox | QA Lead (will run the reset command) |

The QA Lead's name and contact are at the top of the cycle's run log file.

---

## Related docs

- [TESTER_GUIDE.md](TESTER_GUIDE.md) — how to run a single test case, record results, file a bug
- [DEVELOP_TESTING_SEEDER.md](../DEVELOP_TESTING_SEEDER.md) — credentials matrix + what the seeder creates
- [DEVELOP_SANDBOX_CUTOVER_FOLLOWUP.md](../DEVELOP_SANDBOX_CUTOVER_FOLLOWUP.md) — engineer-side sandbox env config (for reference only)
- [admin-guide.md](../admin-guide.md) — full feature walkthrough (technical, owner perspective)
- [testing/MASTER_TEST_PLAN.md](MASTER_TEST_PLAN.md) — overall test strategy
- [testing/COVERAGE_MATRIX.md](COVERAGE_MATRIX.md) — what features are covered by which tests
