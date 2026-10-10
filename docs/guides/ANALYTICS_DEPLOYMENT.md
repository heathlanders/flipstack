# Phase 3: Advanced Analytics & Visualization - Deployment Guide

## Overview

This package contains a complete inventory analytics system for the E-commerce SaaS platform, including:

- **Backend Services**: Analytics calculations, ABC analysis, turnover analysis
- **API Controllers**: REST endpoints for dashboard data
- **Vue.js Components**: Charts, KPI cards, tables, and visualizations
- **Vue.js Pages**: Dashboard, ABC Analysis, Turnover Report, Valuation Report

## Prerequisites

- Laravel 11 with Jetstream/Inertia.js setup
- Vue.js 3 with Tailwind CSS
- Redis running on port 6380
- PostgreSQL database with existing schema (Products, InventoryMovements, InventoryLevels, etc.)

## File Structure

```
ecommerce-analytics/
├── app/
│   ├── Http/
│   │   ├── Controllers/
│   │   │   └── Inventory/
│   │   │       └── InventoryAnalyticsController.php
│   │   └── Resources/
│   │       └── AnalyticsDashboardResource.php
│   └── Services/
│       └── Analytics/
│           ├── InventoryAnalyticsService.php
│           ├── ABCAnalysisService.php
│           └── TurnoverAnalysisService.php
├── resources/
│   └── js/
│       ├── Components/
│       │   └── Analytics/
│       │       ├── KPICard.vue
│       │       ├── StockHealthChart.vue
│       │       ├── MovementTrendsChart.vue
│       │       ├── TopPerformersTable.vue
│       │       ├── ABCAnalysisChart.vue
│       │       ├── LocationPerformanceChart.vue
│       │       ├── DateRangePicker.vue
│       │       ├── CategoryDistributionChart.vue
│       │       └── AgingAnalysisChart.vue
│       └── Pages/
│           └── Inventory/
│               └── Analytics/
│                   ├── Dashboard.vue
│                   ├── ABCAnalysis.vue
│                   ├── TurnoverReport.vue
│                   └── ValuationReport.vue
└── routes/
    └── analytics.php
```

## Installation Steps

### Step 1: Copy Backend Files

```bash
# From your project root

# Create directories
mkdir -p app/Services/Analytics
mkdir -p app/Http/Controllers/Inventory

# Copy services
cp /path/to/ecommerce-analytics/app/Services/Analytics/*.php app/Services/Analytics/

# Copy controller
cp /path/to/ecommerce-analytics/app/Http/Controllers/Inventory/InventoryAnalyticsController.php app/Http/Controllers/Inventory/

# Copy API resource
cp /path/to/ecommerce-analytics/app/Http/Resources/AnalyticsDashboardResource.php app/Http/Resources/
```

### Step 2: Copy Frontend Files

```bash
# Create directories
mkdir -p resources/js/Components/Analytics
mkdir -p resources/js/Pages/Inventory/Analytics

# Copy components
cp /path/to/ecommerce-analytics/resources/js/Components/Analytics/*.vue resources/js/Components/Analytics/

# Copy pages
cp /path/to/ecommerce-analytics/resources/js/Pages/Inventory/Analytics/*.vue resources/js/Pages/Inventory/Analytics/
```

### Step 3: Add Routes

Add the analytics routes to your `routes/web.php` file, inside the authenticated middleware group:

```php
// In routes/web.php

use App\Http\Controllers\Inventory\InventoryAnalyticsController;

Route::middleware(['auth:sanctum', 'verified'])->group(function () {
    
    // ... existing routes ...
    
    // Inventory Analytics Routes
    Route::prefix('inventory/analytics')->name('inventory.analytics.')->group(function () {
        Route::get('/', [InventoryAnalyticsController::class, 'dashboard'])->name('dashboard');
        Route::get('/abc', [InventoryAnalyticsController::class, 'abcAnalysis'])->name('abc');
        Route::get('/turnover', [InventoryAnalyticsController::class, 'turnoverReport'])->name('turnover');
        Route::get('/valuation', [InventoryAnalyticsController::class, 'valuationReport'])->name('valuation');
        Route::get('/export', [InventoryAnalyticsController::class, 'export'])->name('export');
        Route::post('/clear-cache', [InventoryAnalyticsController::class, 'clearCache'])->name('clear-cache');
    });

    // Analytics API Routes
    Route::prefix('api/analytics')->name('api.analytics.')->group(function () {
        Route::get('/kpis', [InventoryAnalyticsController::class, 'getKPIs'])->name('kpis');
        Route::get('/inventory-value', [InventoryAnalyticsController::class, 'getInventoryValue'])->name('inventory-value');
        Route::get('/stock-health', [InventoryAnalyticsController::class, 'getStockHealth'])->name('stock-health');
        Route::get('/movement-trends', [InventoryAnalyticsController::class, 'getMovementTrends'])->name('movement-trends');
        Route::get('/top-performers', [InventoryAnalyticsController::class, 'getTopPerformers'])->name('top-performers');
        Route::get('/location-performance', [InventoryAnalyticsController::class, 'getLocationPerformance'])->name('location-performance');
        Route::get('/abc', [InventoryAnalyticsController::class, 'getABCAnalysis'])->name('abc');
        Route::get('/turnover', [InventoryAnalyticsController::class, 'getTurnoverAnalysis'])->name('turnover');
        Route::get('/turnover-trend', [InventoryAnalyticsController::class, 'getTurnoverTrend'])->name('turnover-trend');
    });
});
```

### Step 4: Verify AppLayout Component

Ensure you have an `AppLayout.vue` component at `resources/js/Layouts/AppLayout.vue`. If using Jetstream, this should already exist.

### Step 5: Build Frontend Assets

```bash
npm install
npm run build
```

### Step 6: Clear Caches

```bash
php artisan cache:clear
php artisan config:clear
php artisan route:clear
php artisan view:clear
```

### Step 7: Verify Permissions

Ensure the following permissions exist in your roles:

- `analytics.view` - View analytics dashboards
- `analytics.export` - Export analytics data
- `reports.view` - View valuation reports

## Configuration

### Redis Cache Settings

The analytics system uses Redis for caching. Ensure your `.env` has:

```env
CACHE_STORE=redis
REDIS_HOST=redis
REDIS_PORT=6379
```

### Cache TTLs

- Dashboard KPIs: 15 minutes
- ABC Analysis: 4 hours
- Turnover Analysis: 4 hours

### Clearing Cache

To manually clear the analytics cache:

```bash
# Via API (authenticated)
POST /inventory/analytics/clear-cache

# Via artisan
php artisan cache:clear
```

## API Endpoints

| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `/inventory/analytics` | Dashboard page |
| GET | `/inventory/analytics/abc` | ABC Analysis page |
| GET | `/inventory/analytics/turnover` | Turnover Report page |
| GET | `/inventory/analytics/valuation` | Valuation Report page |
| GET | `/inventory/analytics/export` | Export data (CSV/JSON) |
| POST | `/inventory/analytics/clear-cache` | Clear analytics cache |
| GET | `/api/analytics/kpis` | Dashboard KPIs JSON |
| GET | `/api/analytics/stock-health` | Stock health metrics |
| GET | `/api/analytics/movement-trends` | Movement trends data |
| GET | `/api/analytics/top-performers` | Top performing products |
| GET | `/api/analytics/location-performance` | Location metrics |
| GET | `/api/analytics/abc` | ABC analysis data |
| GET | `/api/analytics/turnover` | Turnover analysis data |
| GET | `/api/analytics/turnover-trend` | Historical turnover trend |

## Query Parameters

### Date Range Filtering

Most endpoints support date filtering:

```
?start_date=2024-01-01&end_date=2024-01-31
```

### ABC Analysis

```
?metric=revenue|sales_volume|inventory_value
?a_threshold=80
?b_threshold=95
```

### Turnover Analysis

```
?period=30|90|180|365|730
```

### Movement Trends

```
?interval=hour|day|week|month
?periods=30
```

### Top Performers

```
?limit=10
?metric=total_sales|view_count|stock_quantity
```

## Database Requirements

The analytics system expects these tables/columns:

### Products Table
- `id` (UUID) - PRIMARY KEY
- `organization_id` (bigint) - FK to organizations
- `sku`, `name` (string)
- `cost_price`, `selling_price` (decimal)
- `stock_quantity`, `low_stock_threshold` (integer)
- `total_sales`, `view_count` (integer)
- `status`, `condition` (string)
- `category_id`, `brand_id` (UUID foreign keys)
- `created_at`, `updated_at`

### InventoryMovements Table (UUID primary key)
- `id` (UUID) - PRIMARY KEY
- `organization_id` (bigint)
- `product_id` (UUID)
- `location_id` (UUID) - single location (NOT from/to pattern)
- `type` (string) - VALUES: 'purchase', 'sale', 'adjustment', 'transfer_in', 'transfer_out', 'return', 'damaged', 'lost', 'found'
- `quantity` (integer) - always positive, type determines direction
- `reason` (text, nullable)
- `notes` (text, nullable)
- `reference_type`, `reference_number` (string, nullable)
- `created_by` (bigint) - FK to users
- `created_at` (timestamp) - NO updated_at column

### InventoryLevels Table (UUID primary key)
- `id` (UUID) - PRIMARY KEY
- `organization_id` (bigint)
- `product_id` (UUID)
- `location_id` (UUID)
- `quantity_on_hand`, `quantity_reserved` (integer)
- `quantity_available` (COMPUTED column - do not write)
- `average_cost` (decimal)
- `total_cost` (COMPUTED column - do not write)

### InventoryLocations Table (UUID primary key)
- `id` (UUID) - PRIMARY KEY
- `organization_id` (bigint)
- `name`, `type` (string)
- `is_active` (boolean)

### Important Schema Notes

1. **UUID Primary Keys**: All inventory tables use UUID, not auto-incrementing integers. Models must use `HasUuids` trait.

2. **Computed Columns**: `quantity_available` and `total_cost` in `inventory_levels` are PostgreSQL generated columns - never INSERT or UPDATE them.

3. **No updated_at on movements**: The `inventory_movements` table only has `created_at`.

4. **Single Location per Movement**: Unlike some systems with `from_location_id` and `to_location_id`, this schema uses a single `location_id`. For transfers, create two records: `transfer_out` at source, `transfer_in` at destination.

5. **Movement Type Column**: The column is named `type` (not `movement_type`).

6. **Foreign Key Types**:
   - `organization_id` → bigint
   - `product_id` → UUID
   - `location_id` → UUID
   - `created_by` → bigint

## Troubleshooting

### "Permission denied" errors
Ensure the user has `analytics.view` permission assigned via their role.

### Charts not loading
- Check browser console for JavaScript errors
- Ensure all Vue components are properly imported
- Run `npm run build` to rebuild assets

### Data not showing
- Verify Redis is running and accessible
- Check that organization_id is correctly scoped
- Clear cache and reload: `php artisan cache:clear`

### Export not working
- Verify user has `analytics.export` permission
- Check file permissions in storage directory

## Navigation Integration

Add navigation links to your sidebar/menu:

```vue
<template>
  <nav>
    <!-- In your navigation menu -->
    <a :href="route('inventory.analytics.dashboard')" 
       class="nav-link">
      📊 Analytics
    </a>
    <a :href="route('inventory.analytics.abc')" 
       class="nav-link pl-8">
      ABC Analysis
    </a>
    <a :href="route('inventory.analytics.turnover')" 
       class="nav-link pl-8">
      Turnover Report
    </a>
    <a :href="route('inventory.analytics.valuation')" 
       class="nav-link pl-8">
      Valuation Report
    </a>
  </nav>
</template>
```

## Support

For issues or questions, check:
1. Laravel logs: `storage/logs/laravel.log`
2. Browser console for frontend errors
3. Redis monitor: `redis-cli monitor`
4. Network tab in browser dev tools for API responses
