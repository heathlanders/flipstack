# FlipStack Regression Test Suite

**Purpose:** Pre-release checklist executed before every production deployment.
**Last Updated:** 2026-04-15
**Master Test Plan:** `docs/testing/MASTER_TEST_PLAN.md`

---

## How to Use

1. Copy this checklist into the release PR or a GitHub Issue
2. Execute each item and check it off
3. All Critical Path items must pass before deploy
4. Smoke Test failures should be investigated but may not block deploy
5. Document any failures with issue links

> **For full feature-level cycles** (not just regression smoke), use the GitHub Project board described in [`EXECUTION_LIFECYCLE.md`](EXECUTION_LIFECYCLE.md). This regression suite remains the lightweight pre-deploy gate.

---

## 1. Automated Tests (CI Gate)

```bash
docker exec ecommerce_app php artisan test
docker exec ecommerce_app ./vendor/bin/pint --test
npm run build
```

- [ ] All PHPUnit tests pass (zero failures)
- [ ] Laravel Pint formatting passes
- [ ] Vite production build succeeds without errors

---

## 2. Critical Path Tests (Must Pass)

These verify revenue-critical functionality. Any failure blocks deployment.

### Authentication & Access

- [ ] User can log in with email/password
- [ ] User can log in with 2FA (if enabled)
- [ ] Password reset flow completes successfully
- [ ] Unauthenticated users are redirected to login
- [ ] Users only see data for their organization (multi-tenant isolation)

### Product Management

- [ ] Product can be created with all required fields
- [ ] Product can be edited and changes persist
- [ ] Product can be deleted (soft delete, linked data handled)
- [ ] Product list loads with pagination, search, and filters
- [ ] Product detail page displays all tabs correctly

### eBay Integration

- [ ] eBay OAuth connection succeeds (or verify existing connection active)
- [ ] eBay listing creation dispatches job successfully
- [ ] Inventory quantity sync pushes correct available_quantity to eBay
- [ ] eBay order import creates local order with correct data
- [ ] eBay order import reserves inventory (decrements available_quantity)

### Shopify Integration

- [ ] Shopify OAuth connection succeeds (or verify existing connection active)
- [ ] Product sync to Shopify creates/updates Shopify listing
- [ ] Inventory sync pushes correct quantity to Shopify location
- [ ] Shopify webhook (orders/create) creates local order
- [ ] Shopify webhook reserves inventory correctly

### Fulfillment

- [ ] Orders page loads with correct order data
- [ ] Pick workflow: order can be picked (items scanned/confirmed)
- [ ] Pack workflow: picked order can be packed with package details
- [ ] Ship workflow: packed order generates shipping label
- [ ] Tracking number uploads to marketplace (eBay and/or Shopify)
- [ ] Return can be initiated and processed

### Inventory Sync

- [ ] available_quantity is single source of truth
- [ ] Order import decrements available_quantity
- [ ] PushInventoryToMarketplaces job dispatches after quantity change
- [ ] Quantity pushed to all connected marketplaces

---

## 3. Smoke Tests (Quick Verification)

Quick checks that major surfaces are functional. Failures warrant investigation.

### Page Load Tests

- [ ] Owner dashboard loads without errors
- [ ] Buyer dashboard loads without errors
- [ ] Lister dashboard loads without errors
- [ ] Fulfillment dashboard loads without errors
- [ ] Product list page loads
- [ ] Orders page loads
- [ ] Analytics / P&L page loads
- [ ] Settings page loads
- [ ] Sidebar navigation renders all menu items correctly

### API Health

- [ ] `GET /api/health` returns 200
- [ ] Authenticated API request returns valid JSON
- [ ] Unauthenticated API request returns 401

### Queue & Background Jobs

- [ ] Horizon dashboard accessible
- [ ] Queue worker is processing jobs (not stuck)
- [ ] No failed jobs from last 24 hours (or all investigated)

### Mobile App

- [ ] Mobile app login succeeds with valid credentials
- [ ] Mobile dashboard loads with data
- [ ] Mobile pick workflow starts correctly
- [ ] Barcode scanner opens and scans

---

## 4. Database & Migration Safety

- [ ] All migrations run without error on fresh database
- [ ] Rollback of latest migration(s) succeeds
- [ ] No raw SQL that is PostgreSQL-version-specific without guard

---

## 5. Post-Deploy Verification

After deployment completes:

- [ ] Production health endpoint responds: `curl -k --resolve "flipstack.app:443:127.0.0.1" https://flipstack.app/api/health`
- [ ] Horizon restarted: `docker exec flipstack_horizon php artisan horizon:terminate`
- [ ] Spot-check: log in and navigate key pages
- [ ] Monitor `storage/logs/laravel.log` for errors in first 5 minutes

---

## 6. Release-Specific Regression

<!-- Add items specific to the features in this release -->

### vX.Y.Z - [Release Date]

- [ ] [Feature-specific check 1]
- [ ] [Feature-specific check 2]

---

## Execution Log

| Release | Date | Executor | Result | Notes |
|---------|------|----------|--------|-------|
| | | | Pass / Fail | |
