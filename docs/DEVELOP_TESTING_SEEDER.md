# Develop Testing Seeder

QA playground for `develop.flipstack.app`. Seeds one fully-configured user per role under a single org named **FlipStack Testing**.

## Run it

Inside the dev container:

```bash
docker exec ecommerce_app php artisan flipstack:seed-testing
```

On the staging server:

```bash
docker exec flipstack_staging_app_1 php artisan flipstack:seed-testing
```

### Flags

- `--reset` — tear down existing FlipStack Testing org (users, teams, roles, supplier, location, creds) before seeding. Use this when you want a clean slate.
- `--force` — bypass the production environment guard. The command refuses to run with `APP_ENV=production` unless this is passed.

The seeder is **idempotent** — re-running without `--reset` updates existing rows in place and resets passwords without duplicating anything.

## Login

- URL: `https://develop.flipstack.app/login`
- Shared password: `FlipStackQA2026!`
- **QA tester onboarding:** [docs/testing/SANDBOX_GUIDE.md](testing/SANDBOX_GUIDE.md) — laymans guide to roles, dashboards, and an end-to-end sandbox walkthrough.

## Credentials matrix

All emails follow the pattern `{role_slug}tester@flipstack.app`.

| Role | Email | Dashboard |
|------|-------|-----------|
| super_admin | super_admintester@flipstack.app | System admin |
| organization_owner | organization_ownertester@flipstack.app | Owner |
| admin | admintester@flipstack.app | Owner |
| manager | managertester@flipstack.app | Owner |
| buyer | buyertester@flipstack.app | Buyer |
| sourcing_specialist | sourcing_specialisttester@flipstack.app | Buyer |
| lister | listertester@flipstack.app | Lister |
| listing_specialist | listing_specialisttester@flipstack.app | Lister |
| fulfillment_manager | fulfillment_managertester@flipstack.app | Fulfillment |
| shipper | shippertester@flipstack.app | Fulfillment |
| support_agent | support_agenttester@flipstack.app | Support |
| employee | employeetester@flipstack.app | Employee |
| supplier_admin | supplier_admintester@flipstack.app | Supplier portal |
| supplier_staff | supplier_stafftester@flipstack.app | Supplier portal |

## What gets seeded

- **Org** `FlipStack Testing` (slug: `flipstack-testing`, status: `active`)
- **14 users** — one per role, shared password, email verified, onboarding marked complete
- **Personal teams** for each non-supplier user (with `current_team_id` set)
- **1 Supplier** record (`Test Supplier Co`, code `TEST-SUP`) linked to both supplier users
- **Inventory layout** — 1 location (`WH-MAIN`), 1 zone (`A`), 3 bins (`A-01`, `A-02`, `A-03`)
- **Integration credential stubs** — eBay, Shopify, Amazon all in `sandbox` env, `is_active=false` (QA connects via OAuth UI)

## What is NOT seeded

By design, no products, intake queue items, orders, customers, or listings. QA can build this data through the UI to test specific flows. A separate `flipstack:seed-testing-data` command is on the deferred roadmap (see `memory/project_develop_testing_seeder.md`).

## Safety

- Refuses to run if `APP_ENV=production` (override with `--force` only when you know what you are doing).
- Uses `Notification::fake()` during seed so no welcome emails go to the fake addresses.
- All seeded users carry `metadata.develop_testing_seeder = true` so they can be filtered out of activity dashboards.

## Mobile QA on develop

QA the Flutter app against develop with the "FlipStack Dev" APK. It installs side-by-side with the production app (different package id + label).

- **Download:** `https://develop.flipstack.app/download` (landing page; raw APK at `https://develop.flipstack.app/storage/downloads/flipstack-develop.apk`, also linked from the develop login page). Prod testers use `https://flipstack.app/download`.
- **Login:** any seeded tester user above, password `FlipStackQA2026!`
- **Side-by-side install:** the develop APK uses package id `com.example.ecommerce_mobile_app.develop` and label `FlipStack Dev`, so it does NOT collide with the prod install.
- **CI:** see `docs/MOBILE_BUILDS.md`. Pushing to `develop` (touching `mobile/**`) rebuilds the develop APK automatically.

## Related

- Mobile build flavors + CI: `docs/MOBILE_BUILDS.md`
- Plan + decision history: `~/.claude/projects/-home-user-ecommerce-saas/memory/project_develop_testing_seeder.md`
- Staging env config: `~/.claude/projects/-home-user-ecommerce-saas/memory/project_staging_environment.md`
- Sandbox switchover (eBay/Shopify): `~/.claude/projects/-home-user-ecommerce-saas/memory/project_develop_sandbox_switchover.md`
