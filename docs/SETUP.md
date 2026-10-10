# Setup & Installation Guide

Complete guide to building, running, and developing the ecommerce SaaS platform from a fresh clone.

---

## Table of Contents

1. [Prerequisites](#1-prerequisites)
2. [Quick Start](#2-quick-start)
3. [Detailed Setup Steps](#3-detailed-setup-steps)
4. [Container Architecture](#4-container-architecture)
5. [Environment Variables Reference](#5-environment-variables-reference)
6. [Development Workflow](#6-development-workflow)
7. [Common Development Commands](#7-common-development-commands)
8. [Database Details](#8-database-details)
9. [Test Accounts](#9-test-accounts-after-seeding)
10. [Testing](#10-testing)
11. [Architecture Overview](#11-architecture-overview)
12. [External Integrations Setup](#12-external-integrations-setup)
13. [Scheduled Tasks Reference](#13-scheduled-tasks-reference)
14. [Troubleshooting](#14-troubleshooting)
15. [Network & IP Configuration](#15-network--ip-configuration)
16. [Production Deployment Notes](#16-production-deployment-notes)

---

## 1. Prerequisites

| Requirement | Version | Notes |
|-------------|---------|-------|
| Docker & Docker Compose | Latest stable | All PHP/backend runs inside containers |
| Node.js & npm | 18+ | Required on the host for Vite frontend builds |
| Git | 2.x+ | Source control |

**PHP is NOT needed on the host machine.** PHP 8.4 runs inside the Docker container.

---

## 2. Quick Start

Copy-paste this block to get running in under 5 minutes:

```bash
git clone <repo-url> ecommerce-saas && cd ecommerce-saas
cp .env.example .env
docker compose up -d --build
docker exec ecommerce_app composer install
npm install
docker exec ecommerce_app php artisan key:generate
docker exec ecommerce_db psql -U ecommerce_user -d postgres -c "CREATE DATABASE testing;"
docker exec ecommerce_app php artisan migrate
docker exec ecommerce_app php artisan db:seed
docker exec ecommerce_app php artisan storage:link
npm run dev
```

App is now running at **http://localhost:8090**. Login with `admin@example.com` / `password`.

---

## 3. Detailed Setup Steps

### Step 1: Clone and configure environment

```bash
git clone <repo-url> ecommerce-saas
cd ecommerce-saas
cp .env.example .env
```

The `.env.example` ships with working defaults for local development. Key values:

| Variable | Default | Action |
|----------|---------|--------|
| `APP_KEY` | Example key | Will be regenerated in Step 5 |
| `APP_URL` | `http://localhost:8090` | Change to your machine's IP (see [Section 15](#15-network--ip-configuration)) |
| `DB_*` | Docker defaults | Leave as-is for local dev |
| `REDIS_*` | Docker defaults | Leave as-is for local dev |
| `MAIL_MAILER` | `log` | Leave as `log` for local dev (emails written to `storage/logs/`) |

### Step 2: Build and start Docker containers

```bash
docker compose up -d --build
```

This starts the following core containers:

- **ecommerce_app** -- PHP 8.4-FPM application server
- **ecommerce_web** -- Nginx reverse proxy (serves the app on port 8090)
- **ecommerce_db** -- PostgreSQL 16 database
- **ecommerce_redis** -- Redis 7 for cache, sessions, and queues
- **ecommerce_pgadmin** -- pgAdmin database UI
- **ecommerce_redis_commander** -- Redis management UI
- **ecommerce_mailhog** -- Local email testing server

The **background-removal** container builds separately and requires a GPU-capable machine. It is optional for general development.

**Horizon** and **Scheduler** are behind Docker profiles and do not start by default (see [Section 6](#6-development-workflow)).

Wait for containers to become healthy before proceeding:

```bash
docker ps
```

### Step 3: Install PHP dependencies

```bash
docker exec ecommerce_app composer install
```

### Step 4: Install Node dependencies

```bash
npm install
```

This runs on the host machine (not inside Docker).

### Step 5: Generate application key

```bash
docker exec ecommerce_app php artisan key:generate
```

### Step 6: Database setup

PostgreSQL auto-creates the `ecommerce_saas` database from `docker-compose.yaml` environment variables. The `init.sql` script auto-installs required extensions (`uuid-ossp`, `pg_stat_statements`).

Create the test database (used by PHPUnit):

```bash
docker exec ecommerce_db psql -U ecommerce_user -d postgres -c "CREATE DATABASE testing;"
```

Run migrations:

```bash
docker exec ecommerce_app php artisan migrate
```

Seed development data:

```bash
docker exec ecommerce_app php artisan db:seed
```

The seeder creates organizations, users (all with password `password`), categories, products, eBay listings, inventory data, and supplier records.

### Step 7: Storage symlink

```bash
docker exec ecommerce_app php artisan storage:link
```

Creates the `public/storage` symlink for serving uploaded files.

### Step 8: Start development

```bash
npm run dev
```

This starts the Vite dev server with HMR on port 5173.

Access the app at **http://localhost:8090**.

---

## 4. Container Architecture

| Container | Image | Host Port(s) | Purpose |
|-----------|-------|---------------|---------|
| `ecommerce_app` | PHP 8.4-FPM (custom Dockerfile) | 5173 | Laravel application + PHP-FPM |
| `ecommerce_web` | Nginx (custom Dockerfile) | 8090 (HTTP), 8443 (HTTPS) | Nginx reverse proxy |
| `ecommerce_db` | `postgres:16-alpine` | 5433 | PostgreSQL database |
| `ecommerce_redis` | `redis:7-alpine` | 6380 | Cache, sessions, queues |
| `ecommerce_pgadmin` | `dpage/pgadmin4:latest` | 8082 | Database admin UI |
| `ecommerce_redis_commander` | `rediscommander/redis-commander` | 8083 | Redis admin UI |
| `ecommerce_mailhog` | `mailhog/mailhog:latest` | 1025 (SMTP), 8025 (Web UI) | Email testing |
| `ecommerce_bg_removal` | Python Flask (custom Dockerfile) | 5000 | Background removal AI (optional) |
| `ecommerce_horizon` | PHP 8.4-FPM (custom Dockerfile) | -- | Queue worker (profile: `horizon`) |
| `ecommerce_scheduler` | PHP 8.4-FPM (custom Dockerfile) | -- | Task scheduler (profile: `scheduler`) |

All containers communicate over the `ecommerce_network` bridge network. Internal service discovery uses container names (e.g., `db`, `redis`).

---

## 5. Environment Variables Reference

### Application

| Variable | Description | Required | Default |
|----------|-------------|----------|---------|
| `APP_NAME` | Application display name | No | `E-commerce SaaS` |
| `APP_ENV` | Environment (`local`, `production`) | Yes | `local` |
| `APP_KEY` | Encryption key (generate with `artisan key:generate`) | Yes | -- |
| `APP_DEBUG` | Enable debug mode | No | `true` |
| `APP_TIMEZONE` | Server timezone | No | `UTC` |
| `APP_URL` | Base URL for the application | Yes | `http://localhost:8090` |
| `APP_LOCALE` | Default locale | No | `en` |
| `BCRYPT_ROUNDS` | Password hashing cost | No | `12` |

### Logging

| Variable | Description | Required | Default |
|----------|-------------|----------|---------|
| `LOG_CHANNEL` | Log channel (`stack`, `single`, `daily`) | No | `stack` |
| `LOG_STACK` | Stack channel driver | No | `single` |
| `LOG_LEVEL` | Minimum log level | No | `debug` |

### Database (PostgreSQL)

| Variable | Description | Required | Default |
|----------|-------------|----------|---------|
| `DB_CONNECTION` | Database driver | No | `pgsql` |
| `DB_HOST` | Database host (Docker internal) | No | `db` |
| `DB_PORT` | Database port (internal) | No | `5432` |
| `DB_DATABASE` | Database name | No | `ecommerce_saas` |
| `DB_USERNAME` | Database username | No | `ecommerce_user` |
| `DB_PASSWORD` | Database password | No | `password` |

### Cache / Session / Queue (Redis)

| Variable | Description | Required | Default |
|----------|-------------|----------|---------|
| `REDIS_CLIENT` | Redis client library | No | `phpredis` |
| `REDIS_HOST` | Redis host (Docker internal) | No | `redis` |
| `REDIS_PASSWORD` | Redis password | No | `null` |
| `REDIS_PORT` | Redis port (internal) | No | `6379` |
| `REDIS_DB` | Redis database index | No | `0` |
| `CACHE_STORE` | Cache backend | No | `redis` |
| `SESSION_DRIVER` | Session backend | No | `redis` |
| `SESSION_LIFETIME` | Session timeout (minutes) | No | `120` |
| `QUEUE_CONNECTION` | Queue backend | No | `redis` |
| `BROADCAST_CONNECTION` | Broadcast driver | No | `log` |
| `FILESYSTEM_DISK` | Default filesystem | No | `local` |

### Mail

| Variable | Description | Required | Default |
|----------|-------------|----------|---------|
| `MAIL_MAILER` | Mail driver (`log`, `smtp`, `resend`) | No | `log` |
| `MAIL_HOST` | SMTP host | No | `127.0.0.1` |
| `MAIL_PORT` | SMTP port | No | `2525` |
| `MAIL_USERNAME` | SMTP username | No | `null` |
| `MAIL_PASSWORD` | SMTP password | No | `null` |
| `MAIL_ENCRYPTION` | SMTP encryption | No | `null` |
| `MAIL_FROM_ADDRESS` | Sender email | No | `noreply@flipstack.app` |
| `MAIL_FROM_NAME` | Sender name | No | `${APP_NAME}` |
| `RESEND_KEY` | Resend API key (production email) | Optional | -- |

### eBay Integration

| Variable | Description | Required | Default |
|----------|-------------|----------|---------|
| `EBAY_SANDBOX_CLIENT_ID` | eBay sandbox app ID | Optional | -- |
| `EBAY_SANDBOX_CLIENT_SECRET` | eBay sandbox cert ID | Optional | -- |
| `EBAY_SANDBOX_RU_NAME` | eBay sandbox redirect URL name | Optional | -- |
| `EBAY_PRODUCTION_CLIENT_ID` | eBay production app ID | Optional | -- |
| `EBAY_PRODUCTION_CLIENT_SECRET` | eBay production cert ID | Optional | -- |
| `EBAY_PRODUCTION_RU_NAME` | eBay production redirect URL name | Optional | -- |
| `EBAY_USE_SANDBOX` | Force sandbox mode (`true`/`false`) | Optional | Auto-detected from `APP_ENV` |

### Background Removal

| Variable | Description | Required | Default |
|----------|-------------|----------|---------|
| `BACKGROUND_REMOVAL_URL` | Background removal service URL | Optional | `http://background-removal:5000` |
| `BACKGROUND_REMOVAL_API_KEY` | API key for background removal | Optional | -- |

### AWS / S3

| Variable | Description | Required | Default |
|----------|-------------|----------|---------|
| `AWS_ACCESS_KEY_ID` | AWS access key | Optional | -- |
| `AWS_SECRET_ACCESS_KEY` | AWS secret key | Optional | -- |
| `AWS_DEFAULT_REGION` | AWS region | Optional | `us-east-1` |
| `AWS_BUCKET` | S3 bucket name | Optional | -- |

### Frontend

| Variable | Description | Required | Default |
|----------|-------------|----------|---------|
| `VITE_APP_NAME` | App name exposed to frontend | No | `${APP_NAME}` |

---

## 6. Development Workflow

### Option A: All-in-one dev command (inside container)

```bash
docker exec ecommerce_app composer dev
```

This uses `concurrently` to run four processes simultaneously:
- Laravel development server (`php artisan serve`)
- Queue listener (`php artisan queue:listen --tries=1`)
- Real-time log viewer (`php artisan pail --timeout=0`)
- Vite dev server (`npm run dev`)

### Option B: Run services individually

Run Vite on the host for the best HMR experience:

```bash
npm run dev
```

In separate terminals, run backend services inside the container:

```bash
# Queue worker
docker exec ecommerce_app php artisan queue:listen --tries=1

# Real-time log viewer
docker exec ecommerce_app php artisan pail --timeout=0
```

### Optional: Horizon (queue management dashboard)

```bash
docker compose --profile horizon up -d
```

Access the Horizon dashboard at `http://localhost:8090/horizon`.

### Optional: Scheduler (cron jobs)

```bash
docker compose --profile scheduler up -d
```

Runs `php artisan schedule:work` for local cron task execution.

### Vite HMR Configuration

The `vite.config.js` HMR host is hardcoded to `localhost`. If your local IP differs, you must update it:

```js
// vite.config.js
server: {
    host: '0.0.0.0',
    port: 5173,
    hmr: {
        host: 'YOUR_LOCAL_IP',  // Change this
        port: 5173,
    },
    cors: {
        origin: ['http://YOUR_LOCAL_IP:8090', 'http://localhost:8090'],  // And this
        credentials: true,
    },
},
```

See [Section 15](#15-network--ip-configuration) for all IP-related changes.

---

## 7. Common Development Commands

All PHP commands must run inside the Docker container via `docker exec ecommerce_app`.

### Testing

```bash
# Run all tests
docker exec ecommerce_app php artisan test

# Run a specific test
docker exec ecommerce_app php artisan test --filter=TestName

# Run feature tests only
docker exec ecommerce_app php artisan test tests/Feature/

# Run unit tests only
docker exec ecommerce_app php artisan test tests/Unit/
```

### Code Formatting

```bash
# Fix formatting
docker exec ecommerce_app ./vendor/bin/pint

# Check formatting without fixing
docker exec ecommerce_app ./vendor/bin/pint --test
```

### Artisan

```bash
# Interactive REPL
docker exec -it ecommerce_app php artisan tinker

# List all routes
docker exec ecommerce_app php artisan route:list

# List all registered commands
docker exec ecommerce_app php artisan list
```

### Cache Management

```bash
# Clear all caches
docker exec ecommerce_app php artisan cache:clear
docker exec ecommerce_app php artisan config:clear
docker exec ecommerce_app php artisan view:clear
docker exec ecommerce_app php artisan route:clear

# Rebuild caches (useful for debugging config issues)
docker exec ecommerce_app php artisan config:cache
docker exec ecommerce_app php artisan route:cache
docker exec ecommerce_app php artisan view:cache
```

### Queue Management

```bash
# Listen for jobs (auto-restarts on code change)
docker exec ecommerce_app php artisan queue:listen --tries=1

# Process a single job
docker exec ecommerce_app php artisan queue:work --once

# Retry failed jobs
docker exec ecommerce_app php artisan queue:retry all

# Clear failed jobs
docker exec ecommerce_app php artisan queue:flush
```

### Migrations

```bash
# Run pending migrations
docker exec ecommerce_app php artisan migrate

# Rollback last batch
docker exec ecommerce_app php artisan migrate:rollback

# Fresh migration (drops all tables, re-runs all migrations)
docker exec ecommerce_app php artisan migrate:fresh

# Fresh migration + seed
docker exec ecommerce_app php artisan migrate:fresh --seed

# Create a new migration
docker exec ecommerce_app php artisan make:migration create_example_table
```

### Seeding

```bash
# Run all seeders
docker exec ecommerce_app php artisan db:seed

# Run a specific seeder
docker exec ecommerce_app php artisan db:seed --class=UserSeeder
```

### Frontend Build

```bash
# Development server with HMR
npm run dev

# Production build
npm run build
```

---

## 8. Database Details

| Property | Value |
|----------|-------|
| Engine | PostgreSQL 16 |
| Host (from host) | `localhost` |
| Port (from host) | `5433` |
| Host (from containers) | `db` |
| Port (from containers) | `5432` |
| Database | `ecommerce_saas` |
| Test database | `testing` |
| Username | `ecommerce_user` |
| Password | `password` |
| Required extensions | `uuid-ossp`, `pg_stat_statements` |

### pgAdmin

| Property | Value |
|----------|-------|
| URL | http://localhost:8082 |
| Login email | `admin@example.com` |
| Login password | `admin` |

When adding a server connection in pgAdmin, use:
- **Host**: `db` (Docker internal hostname, not `localhost`)
- **Port**: `5432` (internal port, not `5433`)
- **Username**: `ecommerce_user`
- **Password**: `password`

### Redis Commander

| Property | Value |
|----------|-------|
| URL | http://localhost:8083 |

### Mailhog (email testing)

| Property | Value |
|----------|-------|
| Web UI | http://localhost:8025 |
| SMTP Port | 1025 |

---

## 9. Test Accounts (after seeding)

All seeded users share the password `password`.

### Primary Accounts

| Email | Name | Role | Organization |
|-------|------|------|--------------|
| `admin@example.com` | Super Admin | Super Admin | Demo Reseller Pro |
| `mike@demoreseller.test` | Mike Johnson | Organization Owner | Demo Reseller Pro |
| `sarah@demoreseller.test` | Sarah Chen | Manager | Demo Reseller Pro |
| `david@demoreseller.test` | David Rodriguez | Employee | Demo Reseller Pro |
| `jennifer@techflow.com` | Jennifer Park | Organization Owner | TechFlow Solutions |
| `robert@techflow.com` | Robert Kim | Manager | TechFlow Solutions |
| `james@retailtech.com` | James Thompson | Organization Owner | RetailTech Depot |
| `alex@globalparts.com` | Alex Turner | Organization Owner | Global Parts Exchange |
| `admin@test.com` | Test Admin | Organization Owner | Demo Test Org |

Additional staff users (buyers, listers, fulfillment managers, support agents, employees) are generated per organization during seeding.

### Develop / staging QA accounts

For QA on `develop.flipstack.app` (or any non-prod environment), run `php artisan flipstack:seed-testing` to create the **FlipStack Testing** org with one user per role (`{role_slug}tester@flipstack.app`, shared password `FlipStackQA2026!`). Full credentials matrix in [DEVELOP_TESTING_SEEDER.md](DEVELOP_TESTING_SEEDER.md).

### Edge Case Test Users (Demo Test Org)

| Email | Purpose |
|-------|---------|
| `inactive@test.local` | Deactivated user |
| `unverified@test.local` | Unverified email |
| `mfa@test.local` | MFA-enabled user |

---

## 10. Testing

### Test Database Setup

The test suite uses a separate `testing` database. Create it before running tests:

```bash
docker exec ecommerce_db psql -U ecommerce_user -d postgres -c "CREATE DATABASE testing;"
```

### PHPUnit Configuration

The `phpunit.xml` configures the test environment:

- **Database**: `testing` (separate from development)
- **Cache/Session**: `array` driver (in-memory, no Redis needed)
- **Mail**: `array` driver (no emails sent)
- **Queue**: `sync` driver (jobs execute immediately)
- **Logging**: `null` channel (suppressed)

### Running Tests

```bash
# All tests
docker exec ecommerce_app php artisan test

# With verbose output
docker exec ecommerce_app php artisan test -v

# Specific test class
docker exec ecommerce_app php artisan test --filter=ProductIntakeQueueTest

# Specific test method
docker exec ecommerce_app php artisan test --filter="ProductIntakeQueueTest::test_can_create_queue_item"

# Test suites
docker exec ecommerce_app php artisan test tests/Unit/
docker exec ecommerce_app php artisan test tests/Feature/

# Intake queue tests specifically
docker exec ecommerce_app php artisan test tests/Feature/IntakeQueue/
```

---

## 11. Architecture Overview

### Tech Stack

| Layer | Technology |
|-------|-----------|
| Backend | Laravel 11 (PHP 8.4) |
| Frontend | Vue 3 + Inertia.js |
| Styling | Tailwind CSS |
| Database | PostgreSQL 16 |
| Cache/Queue | Redis 7 |
| Auth | Laravel Sanctum + Jetstream |
| Real-time | Pusher / Laravel Echo |

### Multi-Tenancy Model

```
Organization (tenant)
  -> Teams
    -> Users (with Roles)
  -> Products, Inventory, eBay Listings, Suppliers, etc.
```

All data is scoped to an Organization. Users belong to an Organization and have roles within Teams.

### Key Directory Structure

```
app/
  Http/Controllers/
    Api/                  # Mobile/API endpoints
    Dashboard/            # Role-specific dashboards
    Inventory/            # Product, category, location management
    Supplier/             # Supplier portal and management
    Ebay/                 # eBay integration
    Admin/                # Admin-only operations
    Product/              # Product intake queue
  Models/
    Catalog/              # Product, Category, Brand, ProductIntakeQueue
    Inventory/            # InventoryLevel, Location, Movement, Supplier
    Organization/         # Organization, Team, Membership
    User/                 # User, Role
    Ebay/                 # EbayListing, EbayCategory, EbaySyncLog
    Integration/          # IntegrationCredential, OAuthToken
  Services/               # Business logic (eBay*, ProductIdentification, Gemini, etc.)
  Jobs/                   # Queue jobs organized by domain (Ebay/, Inventory/, Supplier/)

resources/js/
  Components/             # Reusable Vue components
  Pages/                  # Inertia page components by feature
  Layouts/                # App layout wrappers

routes/
  web.php                 # Main authenticated web routes
  api.php                 # REST API for mobile/external
  supplier.php            # Supplier portal routes
  inventory.php           # Inventory-specific routes
  channels.php            # Broadcasting channels
  console.php             # Scheduled tasks / artisan commands

database/
  migrations/             # All database migrations
  seeders/                # Development seed data
  factories/              # Model factories for testing
```

### Service Layer Pattern

Business logic is extracted into services under `app/Services/`:
- **eBay Services** -- OAuth, Browse, Finding, Inventory, Sell API integration
- **ProductIdentificationService** -- Barcode/UPC lookup
- **GeminiProductResearch** -- AI product data enrichment
- **NotificationService** -- Multi-channel notification handling
- **OrganizationService** -- Org-level operations and SKU generation

### Route Files

| File | Middleware | Purpose |
|------|-----------|---------|
| `routes/web.php` | `auth`, `verified` | Main authenticated web routes |
| `routes/api.php` | `auth:sanctum` | REST API for mobile app and external access |
| `routes/supplier.php` | `auth`, supplier checks | Supplier portal routes |
| `routes/inventory.php` | `auth`, inventory permissions | Inventory management routes |
| `routes/channels.php` | -- | Pusher broadcasting channel authorization |

---

## 12. External Integrations Setup

### eBay

1. Create a developer account at https://developer.ebay.com
2. Create an application to get Client ID, Client Secret, and RuName
3. Set sandbox credentials in `.env`:
   ```
   EBAY_SANDBOX_CLIENT_ID=your_sandbox_client_id
   EBAY_SANDBOX_CLIENT_SECRET=your_sandbox_secret
   EBAY_SANDBOX_RU_NAME=your_sandbox_runame
   ```
4. For production, set the `EBAY_PRODUCTION_*` equivalents
5. The app auto-detects sandbox vs production based on `APP_ENV` (override with `EBAY_USE_SANDBOX`)
6. Users connect via the OAuth flow -- they click "Connect to eBay" in the app

See [docs/ebay-oauth-workflow.md](ebay-oauth-workflow.md) for the full OAuth flow documentation.

### Resend (production email)

1. Sign up at https://resend.com
2. Get your API key
3. Set in `.env`:
   ```
   MAIL_MAILER=resend
   RESEND_KEY=re_your_api_key
   ```

### Pusher (real-time broadcasting)

1. Create an app at https://pusher.com
2. Set credentials in `.env`:
   ```
   BROADCAST_CONNECTION=pusher
   PUSHER_APP_ID=your_app_id
   PUSHER_APP_KEY=your_key
   PUSHER_APP_SECRET=your_secret
   PUSHER_APP_CLUSTER=your_cluster
   ```

### Background Removal Service

The background removal server requires a GPU. For local development:

1. The `ecommerce_bg_removal` container builds from `background-removal-server/Dockerfile`
2. It starts automatically with `docker compose up` if you have the source in `background-removal-server/`
3. Default URL: `http://background-removal:5000` (Docker internal)
4. In production, it runs on a separate GPU machine behind a Cloudflare Tunnel at `https://bg.flipstack.app`

### Other Integrations

These are optional and only needed for specific features:

| Integration | Env Variables | Sign Up |
|-------------|---------------|---------|
| AWS S3 | `AWS_ACCESS_KEY_ID`, `AWS_SECRET_ACCESS_KEY`, `AWS_BUCKET` | https://aws.amazon.com |
| Twilio (SMS) | Twilio SDK config | https://www.twilio.com |
| EasyPost (shipping) | EasyPost API key env vars | https://www.easypost.com |
| UPC Database | Barcode API key env vars | https://www.upcdatabase.org |
| Barcode Spider | Barcode API key env vars | https://www.barcodespider.com |

---

## 13. Scheduled Tasks Reference

These tasks run when the scheduler container is active (`docker compose --profile scheduler up -d`).

| Task | Frequency | Purpose |
|------|-----------|---------|
| `ebay-update-stale` | Every 6 hours | Update stale eBay listings |
| `ebay-retry-failed` | Every 30 minutes | Retry failed eBay operations |
| `ebay-sync-quantities` | Hourly | Sync eBay inventory quantities |
| `ebay-cleanup-logs` | Daily at 03:00 | Clean up old eBay sync logs |
| `oauth-token-refresh` | Every 15 minutes | Refresh expiring OAuth tokens |
| `supplier-daily-metrics` | Daily at 02:00 | Process daily supplier performance metrics |
| `supplier-evaluate-performance` | Daily at 03:30 | Evaluate supplier performance per organization |
| `supplier-process-settlements` | Daily at 04:00 | Process supplier payment settlements |
| `supplier-tier-notifications` | Every 15 minutes | Send supplier tier change notifications |
| `shopify-retry-failed` | Every 30 minutes | Retry failed Shopify sync operations |
| `fulfillment-sync-ebay-orders` | Every 5 minutes | Sync orders from eBay |
| `fulfillment-auto-assign-orders` | Every 10 minutes | Auto-assign orders to fulfillment staff |
| `fulfillment-check-dropship-sla` | Hourly | Check dropship SLA compliance |
| `fulfillment-check-stale-shipments` | Every 6 hours | Flag shipments that haven't progressed |
| `fulfillment-create-settlement-items` | Daily at 05:00 | Create settlement line items from fulfilled orders |

---

## 14. Troubleshooting

### Container won't start / port conflicts

```bash
# Check what's using the ports
lsof -i :8090
lsof -i :5433

# Force remove stale containers and rebuild
docker compose down
docker compose up -d --build
```

### "Class not found" errors

```bash
docker exec ecommerce_app composer dump-autoload
docker exec ecommerce_app composer install
```

### Vite HMR not working

The HMR host in `vite.config.js` must match your machine's local IP. See [Section 15](#15-network--ip-configuration).

If HMR still doesn't connect:
1. Verify the Vite dev server is running (`npm run dev`)
2. Check browser console for WebSocket connection errors
3. Ensure port 5173 is not blocked by a firewall

### Database connection refused

```bash
# Check if the db container is running
docker ps | grep ecommerce_db

# Check PostgreSQL logs
docker logs ecommerce_db --tail 50

# Verify credentials
docker exec ecommerce_db psql -U ecommerce_user -d ecommerce_saas -c "SELECT 1;"
```

The database takes a few seconds to initialize on first start. If you get connection errors immediately after `docker compose up`, wait a few seconds and retry.

### Redis connection refused

```bash
# Check if Redis is running
docker ps | grep ecommerce_redis

# Test Redis connectivity
docker exec ecommerce_redis redis-cli ping
# Should return: PONG
```

### Permission errors on storage/

```bash
docker exec ecommerce_app chmod -R 775 storage bootstrap/cache
docker exec ecommerce_app chown -R laravel:laravel storage bootstrap/cache
```

### Tests failing with "database testing does not exist"

```bash
docker exec ecommerce_db psql -U ecommerce_user -d postgres -c "CREATE DATABASE testing;"
```

### Queue jobs not processing

For development, use the queue listener:

```bash
docker exec ecommerce_app php artisan queue:listen --tries=1
```

Or start the Horizon container:

```bash
docker compose --profile horizon up -d
```

Check failed jobs:

```bash
docker exec ecommerce_app php artisan queue:failed
```

### eBay OAuth errors

1. Verify your eBay credentials are set in `.env`
2. Check the RuName matches your eBay developer application's redirect URL
3. For sandbox, ensure `APP_ENV=local` or `EBAY_USE_SANDBOX=true`
4. Check the Laravel log for detailed error messages:
   ```bash
   docker exec ecommerce_app tail -50 storage/logs/laravel.log
   ```

### General debugging

```bash
# View Laravel logs in real-time
docker exec ecommerce_app php artisan pail --timeout=0

# Or read the log file directly
docker exec ecommerce_app tail -100 storage/logs/laravel.log

# Interactive REPL for testing
docker exec -it ecommerce_app php artisan tinker
```

---

## 15. Network & IP Configuration

The development environment is configured for IP `localhost`. If your local IP is different, update these locations:

### 1. `.env` -- APP_URL

```
APP_URL=http://YOUR_IP:8090
```

### 2. `vite.config.js` -- HMR host and CORS origins

```js
hmr: {
    host: 'YOUR_IP',
    port: 5173,
},
cors: {
    origin: ['http://YOUR_IP:8090', 'http://localhost:8090'],
    credentials: true,
},
```

### Finding your local IP

```bash
# Linux
hostname -I | awk '{print $1}'

# macOS
ipconfig getifaddr en0
```

If you only need `localhost` access (not testing from other devices on the network), you can use `localhost` instead of your IP:

```
APP_URL=http://localhost:8090
```

```js
hmr: {
    host: 'localhost',
    port: 5173,
},
```

---

## 16. Production Deployment Notes

Production runs on **Coolify** at `flipstack.app`. See `CLAUDE.md` for full production details.

### Key differences from local development

| Aspect | Local | Production |
|--------|-------|------------|
| Containers | Single `ecommerce_app` | Blue-green `flipstack_app_1` / `flipstack_app_2` |
| Web server | `ecommerce_web` (nginx) | `flipstack_web` (nginx behind Traefik) |
| Queue | Manual `queue:listen` or Horizon profile | `flipstack_horizon` (always running) |
| Scheduler | Optional profile | `flipstack_scheduler` (always running) |
| Email | `log` driver or Mailhog | Resend (`resend` driver) |
| Background removal | Local Docker container | Separate GPU machine via Cloudflare Tunnel |

### Post-deploy checklist

```bash
# Run new migrations
docker exec flipstack_app_1 php artisan migrate --force

# Restart Horizon (CRITICAL -- it caches code in memory)
docker exec flipstack_horizon php artisan horizon:terminate

# Clear caches if needed
docker exec flipstack_app_1 php artisan config:clear
docker exec flipstack_app_1 php artisan cache:clear
```

Horizon runs a long-lived PHP process. After deploying new code, you **must** run `horizon:terminate` or Horizon will continue executing stale code.
