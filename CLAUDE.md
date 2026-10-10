# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project Overview

This is a multi-tenant ecommerce SaaS platform built with Laravel 11, Inertia.js, and Vue 3. The application manages inventory, products, suppliers, eBay integrations, and team collaboration for reselling businesses.

## Development Commands

**IMPORTANT: PHP is not installed on the host machine. All PHP commands (artisan, composer, pint, etc.) MUST be run inside the Docker container via `docker exec ecommerce_app`.**

### Running PHP Commands
```bash
docker exec ecommerce_app php artisan test              # Run tests
docker exec ecommerce_app ./vendor/bin/pint             # Run Pint
docker exec ecommerce_app composer install              # Install deps
```

### Full Development Environment
```bash
docker exec ecommerce_app composer dev
```
Runs Laravel server (port 8090), queue worker, Pail logs, and Vite dev server concurrently.

### Individual Commands
```bash
docker exec ecommerce_app php artisan serve                    # Laravel development server
npm run dev                                                    # Vite dev server with HMR
docker exec ecommerce_app php artisan queue:listen --tries=1   # Queue worker
docker exec ecommerce_app php artisan pail --timeout=0         # Real-time log viewer
```

### Testing
```bash
docker exec ecommerce_app php artisan test                     # Run all tests
docker exec ecommerce_app php artisan test --filter=TestName   # Run specific test
docker exec ecommerce_app php artisan test tests/Feature/      # Run feature tests only
docker exec ecommerce_app php artisan test tests/Unit/         # Run unit tests only
```

### QA test users on develop.flipstack.app
```bash
docker exec ecommerce_app php artisan flipstack:seed-testing   # Seed FlipStack Testing org + 1 user per role
docker exec ecommerce_app php artisan flipstack:seed-testing --reset   # Tear down + reseed
```
Refuses to run with `APP_ENV=production` unless `--force`. Credentials matrix: `docs/DEVELOP_TESTING_SEEDER.md`.

### Code Quality
```bash
docker exec ecommerce_app ./vendor/bin/pint                    # PHP code formatting (Laravel Pint)
docker exec ecommerce_app ./vendor/bin/pint --test             # Check formatting without fixing
```

### Build
```bash
npm run build                        # Production build (Vite)
```

## Architecture

### Multi-Tenancy Model
- **Organization-scoped**: All data is scoped to an Organization. Users belong to an Organization.
- **Subdomain routing**: Organizations can have subdomains (`EnsureOrganizationExists` middleware resolves organization from subdomain)
- **Team structure**: Organizations contain Teams (via Laravel Jetstream). Users have roles within teams.

### Namespace Organization (app/)
Models and Controllers are organized into domain-specific subfolders:

**Models** (`app/Models/`):
- `Catalog/` - Product, Category, Brand, ProductImage, ProductIdentifier, ProductIntakeQueue, ProductBundle, ProductBundleItem
- `Inventory/` - InventoryLevel, InventoryLocation, InventoryMovement, Supplier, SupplierProduct, InventoryBatch, InventoryBatchItem, WarehouseZone, WarehouseBin, PartOutTemplate, PartOutTemplateItem, PartOutWorkOrder, PartOutWorkOrderItem
- `Organization/` - Organization, Team, Membership, OrganizationSettings
- `User/` - User, Role, UserActivityLog, UserSession
- `Supplier/` - SupplierTier, Settlement, CommissionStructure, SupplierPerformance, SupplierProductSubmission
- `Ebay/` - EbayListing, EbayCategory, EbaySyncLog, EbayPromotedCampaign, EbayPromotedListing
- `Integration/` - IntegrationCredential, OAuthToken
- `Notification/` - NotificationLog, NotificationTemplate
- `System/` - AuditLog, BulkOperationJob, SavedSearch, ExchangeRate
- `Procurement/` - PurchaseOrder, PurchaseOrderItem, PurchaseOrderReceipt, PurchaseOrderReceiptItem
- `Customer/` - Customer, CustomerAddress
- `Pricing/` - RepricingRule, RepricingLog
- `Fulfillment/` - Order, OrderItem, Shipment, ShipmentItem, ReturnRequest, PackagePreset, CarrierAccount, FulfillmentRoutingRule, ShippingRule
- `Marketplace/` - ScheduledListing, MarketplaceProductMatch
- `Analytics/` - DemandForecast, ReorderSuggestion, OrderFinancial, DailyKPISnapshot, WorkforcePerformanceDaily, MarketplaceHealthSnapshot, BusinessHealthScore
- `Communication/` - MarketplaceMessage, MarketplaceMessageReply
- `Marketing/` - EmailCampaign, EmailCampaignSend

**Controllers** (`app/Http/Controllers/`):
- `Api/` - Mobile/API endpoints
- `Dashboard/` - Role-specific dashboards (Owner, Buyer, Lister, Fulfillment)
- `Inventory/` - Product, Category, Location, Batch, Zone management, Part-out templates, Part-out work orders
- `Supplier/` - Supplier portal, product submissions
- `Ebay/` - eBay integration, promoted listings
- `Admin/` - Admin operations, supplier submission review
- `Procurement/` - Purchase order management
- `Customer/` - Customer CRM
- `Analytics/` - Profit & Loss, forecasting
- `Pricing/` - Repricing rules
- `Fulfillment/` - Orders, pick/pack/ship, routing rules, shipping rules
- `Marketplace/` - Scheduled listings, cross-listing
- `Communication/` - Buyer message inbox
- `Marketing/` - Email campaigns
- `Catalog/` - Bundles
- `Settings/` - Currency management

### Services Layer (`app/Services/`)
Business logic is extracted into services:
- `Ebay/` - eBay API integration (OAuth, Browse, Finding, Inventory, Sell, Promoted Listings)
- `Fulfillment/` - Order sync, shipping, returns, fulfillment routing, shipping rules engine
- `Procurement/` - Purchase orders, receiving
- `Customer/` - Customer matching, CRM
- `Analytics/` - Profit & Loss, demand forecasting, reorder suggestions, Analytics Hub (executive, fulfillment, workforce, procurement, marketplace health, customer, communication, marketing, pricing KPIs), business health score, KPI aggregation
- `Pricing/` - Repricing engine
- `Inventory/` - Batch tracking, warehouse layout, part-out service, part-out pricing
- `Catalog/` - Bundle management
- `Marketplace/` - Listing scheduler, cross-listing, data transformer, product matching
- `Communication/` - Message sync, eBay messaging
- `Marketing/` - Email campaigns, template rendering
- `Currency/` - Currency conversion, exchange rate provider
- `Supplier/` - Supplier preferences, upload service
- `ProductIdentificationService` - Stage 1 AI product identification. `identify($item, $marketEvidence)` is grounded on real eBay listing titles (gathered pre-Stage-1) to prevent brand-name hallucination
- `GeminiProductResearch` - Stage 2 AI enrichment; image sourcing applies a relevance floor (`MIN_IMAGE_RELEVANCE`) so unrelated marketplace images never attach
- `NotificationService/NotificationPreferenceService` - Notification handling
- `OrganizationService/OrganizationSkuService` - Org-level operations

### Frontend (resources/js/)
- **Framework**: Vue 3 with Inertia.js (SPA-like experience)
- **Styling**: Tailwind CSS with custom brand colors (see `tailwind.config.js`)
- **Components**: `resources/js/Components/` - Reusable UI components
- **Pages**: `resources/js/Pages/` - Inertia page components organized by feature
- **Layouts**: `resources/js/Layouts/` - App layout wrappers
- **Routing**: Uses Ziggy for Laravel route access in JS (`route('name')`)

### Key Page Directories
- `Pages/Inventory/` - Product, batch, warehouse, bundle management, part-out templates and work orders
- `Pages/Procurement/` - Purchase order management
- `Pages/Customers/` - Customer CRM
- `Pages/Analytics/` - Profit & Loss, forecasting dashboards, Analytics Hub (9 enterprise KPI dashboards with Chart.js)
- `Pages/Pricing/` - Repricing rules
- `Pages/Fulfillment/` - Orders, pick/pack/ship, routing rules, shipping rules
- `Pages/Marketplace/` - Scheduled listings, cross-listing
- `Pages/Ebay/` - eBay listings, promoted listings
- `Pages/Communication/` - Buyer message inbox and threads
- `Pages/Marketing/` - Email campaign management
- `Pages/Supplier/` - Supplier portal views, product submissions
- `Pages/Settings/` - Currency management
- `Pages/Dashboard/` - Role-specific dashboard views
- `Pages/Admin/` - Admin pages, supplier submission review
- `Pages/Printing/` - Label printing features

### Route Files
- `routes/web.php` - Main authenticated web routes (messages, marketing, analytics, settings, admin)
- `routes/api.php` - REST API for mobile app and external access
- `routes/supplier.php` - Supplier portal routes (including product submissions)
- `routes/inventory.php` - Inventory routes (products, batches, zones/bins, bundles, part-out templates/work orders)
- `routes/fulfillment.php` - Fulfillment routes (orders, pick/pack/ship, routing rules, shipping rules)
- `routes/procurement.php` - Purchase order routes
- `routes/customers.php` - Customer CRM routes
- `routes/analytics.php` - Analytics routes (inventory analytics, P&L, Analytics Hub with 9 enterprise KPI dashboards)
- `routes/pricing.php` - Repricing routes
- `routes/channels.php` - Broadcasting channels (Pusher)

### Authentication & Authorization
- **Auth**: Laravel Sanctum (API tokens) + Jetstream (sessions, 2FA, profile management)
- **Teams**: Jetstream team functionality with custom Role model
- **Middleware**: Custom middleware for permissions (`CheckUserPermissions`, `CheckInventoryPermissions`), organization validation, supplier status

### Queue Jobs (`app/Jobs/`)
- `ProcessQueueItem*` - Product intake queue processing
- `CreateEbayListingJob` - eBay listing creation
- `RefreshOAuthTokens` - Token refresh automation
- `SendNotificationDigestJob` - Batched notifications
- Subdirectories: `Ebay/` (sync promoted listing stats), `Inventory/` (push inventory), `Supplier/`, `Analytics/` (demand forecasts, reorder suggestions, daily KPI aggregation), `Communication/` (message sync), `Marketing/` (campaign triggers, email sends), `Marketplace/` (process scheduled listings, product matching), `Currency/` (exchange rate sync)

### External Integrations
- **eBay**: Full integration for listing, inventory sync, category mapping
- **Pusher**: Real-time notifications/broadcasting
- **Redis**: Queue backend and caching (via Predis)
- **Twilio**: SMS notifications

### Mobile App
A Flutter mobile app exists in `mobile/` directory for barcode scanning, inventory operations, and warehouse picking workflows. Mobile API endpoints are in `routes/api.php` under `/api/` prefix. See `.claude/skills/flutter-mobile/` and `.claude/skills/mobile-api/` for patterns.

## Database
- Primary: PostgreSQL (runs in Docker container `ecommerce_db`, port 5432 internal / 5433 external)
- Migrations in `database/migrations/`
- UUID primary keys used on core models (Product uses `HasUuids` trait)

## Branches & Releases
- `develop` — staging branch, deploys to `develop.flipstack.app` on push.
- `main` — production branch, deploys to `flipstack.app` on push.
- Pending release tracker: `docs/DEVELOP_VS_MAIN.md` (refresh via the `develop-vs-main` skill before cutting a release).

## Audit Logging (REQUIRED)

All state-changing operations must write to `user_activity_logs`. No exceptions for services, controllers, jobs, webhooks, or mobile API endpoints.

- **Rule (enforced)**: `.claude/rules/audit-logging.md`
- **Spec**: `docs/AUDIT_SYSTEM.md`

Three ways to emit:
- Models: `use LogsAudit;` trait — auto-emits CRUD with diff
- Service domain events: `UserActivityLog::log('domain.verb', desc, $user, $metadata, $severity, $category)`
- Security events: `UserActivityLog::logSecurityAction(...)` — auto severity=warning, category=security

Non-HTTP contexts (jobs, webhooks, cron): wrap in `withAuditActor($user, fn() => ...)` to set actor context. Feature tests for mutation paths must use `Tests\Support\AssertsAudits` and call `assertAudited(...)`.

Run the `audit-coverage` agent before merging any state-changing feature.

## Code Style Guidelines

### No Emojis
- **Never use emojis** in code, comments, strings, or UI text
- Use proper text labels, icons (SVG/icon components), or descriptive text instead
- This applies to all files: PHP, Vue, JavaScript, TypeScript, Blade templates, etc.
