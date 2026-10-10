# FlipStack Mobile App

Flutter mobile app for the FlipStack ecommerce SaaS platform. Provides warehouse picking, inventory management, fulfillment workflows, and real-time business dashboards for reselling teams.

## Setup

```bash
cd mobile
flutter pub get
flutter run
```

### Environment Configuration

The API URL defaults to `http://localhost:8090` for local development. Override at build time:

```bash
# Development (default)
flutter run

# Staging
flutter run --dart-define=API_BASE_URL=https://staging.flipstack.app

# Production
flutter run --dart-define=API_BASE_URL=https://flipstack.app

# Production APK
flutter build apk --dart-define=API_BASE_URL=https://flipstack.app
```

## Architecture

- **State Management:** Riverpod (StateNotifier + immutable state with copyWith)
- **Navigation:** GoRouter with auth redirect and ShellRoute for bottom nav
- **HTTP:** Dio with auth interceptor and token refresh
- **Offline:** SQLite (sqflite) for offline barcode scan queue
- **Connectivity:** connectivity_plus for online/offline detection

### Directory Structure

```
lib/
  core/
    config/           -- Environment-based API URL (AppConfig)
    constants/        -- API endpoints, app strings, enums
    exceptions/       -- Custom ApiException
    network/          -- ApiClient (Dio), ConnectivityService, OfflineSyncService
    notifications/    -- Push notification service (FCM scaffolding)
    providers/        -- Shared Riverpod providers (apiClient, connectivity)
    storage/          -- SecureStorage (tokens), OfflineScanDb (SQLite)
    theme/            -- Material theme
  data/
    models/           -- JSON-serializable data classes
    services/         -- API service classes (one per feature domain)
  features/
    alerts/           -- Inventory alerts (low stock, out of stock, overstock)
    analytics/        -- Inventory analytics (KPIs, ABC analysis, turnover)
    authentication/   -- Login, register, 2FA, password reset
    dashboard/        -- Role-aware dashboard (owner, buyer, lister, fulfillment)
    home/             -- Home page
    inventory/        -- Product CRUD, categories, locations, barcode lookup, movements
    marketplace/      -- Marketplace connection status (eBay, Shopify)
    notifications/    -- Notification list, preferences
    orders/           -- Fulfillment order list and detail
    packing/          -- Pack queue, shipping rates, label purchase
    picking/          -- Pick list with barcode scanner verification
    profile/          -- User profile, sessions, password, account deletion
    returns/          -- Return list, inspection, restock
    shipping/         -- Ship queue, confirm shipped, void labels
    stock_ops/        -- Stock adjustments and transfers
  shared/
    routes/           -- GoRouter config (app_router.dart)
    widgets/          -- MainLayout (bottom nav + drawer)
```

## Features

### Warehouse Operations
- Picking workflow with barcode scan verification
- Stock adjustments (cycle counts, receiving, damage recording)
- Stock transfers between locations
- Inventory movement history with filters
- Barcode scan-to-lookup (instant product details)
- Offline scan queue (syncs when connection returns)

### Fulfillment
- Order management (list, detail, assign, hold/release)
- Pack workflow (rates, label purchase, complete)
- Ship confirmation with tracking upload
- Returns (approve, receive, inspect with camera, restock)
- Label printing from product detail, packing, and shipping pages

### Inventory
- Product CRUD with image upload
- Category CRUD with hierarchy
- Location CRUD
- Inventory alerts (low stock, out of stock, overstock)
- Analytics (KPIs, ABC analysis, turnover reports, stock health)

### Business Intelligence
- Role-aware dashboards (owner, buyer, lister, fulfillment)
- Marketplace connection health (eBay stats, sync logs)
- Inventory valuation by location

### Account
- Login with token refresh
- Profile management with session control
- Notification preferences
- Offline connectivity indicator

## Backend API

The app communicates with the Laravel backend via REST API at `/api/*`. All endpoints require `auth:sanctum` Bearer token authentication except login/register.

Key endpoint groups:
- `/api/auth/*` -- Authentication
- `/api/products/*` -- Product CRUD + barcode lookup
- `/api/fulfillment/pick/*` -- Picking workflow
- `/api/fulfillment/orders/*` -- Order management
- `/api/fulfillment/pack/*` -- Packing workflow
- `/api/fulfillment/ship/*` -- Shipping
- `/api/fulfillment/returns/*` -- Returns
- `/api/inventory/*` -- Stock levels, adjust, transfer, movements
- `/api/analytics/*` -- KPIs, ABC, turnover
- `/api/v1/inventory/alerts/*` -- Stock alerts
- `/api/marketplace/status` -- Marketplace health
- `/api/dashboard/{type}` -- Role dashboards

See `.claude/skills/mobile-api/SKILL.md` for complete endpoint documentation.

## Dependencies

| Package | Purpose |
|---------|---------|
| flutter_riverpod | State management |
| dio | HTTP client |
| go_router | Navigation |
| mobile_scanner | Barcode scanning |
| camera / image_picker | Photos |
| flutter_secure_storage | Token storage |
| sqflite | Offline scan queue |
| connectivity_plus | Online/offline detection |
| shared_preferences | Local preferences |
| firebase_messaging / firebase_core | Push notifications (scaffolded) |
