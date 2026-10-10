---
name: mobile-api
description: Laravel API endpoint patterns for mobile app - Sanctum auth, route groups, controller structure, response format, and adding new mobile endpoints
user-invocable: false
---

# Mobile API Endpoints

## Key Files

| Area | File | Purpose |
|------|------|---------|
| **Routes** | `routes/api.php` | All API route definitions (public + protected) |
| **Auth** | `app/Http/Controllers/Api/AuthController.php` | Login, register, logout, token refresh, profile, 2FA |
| **Products** | `app/Http/Controllers/Api/ProductController.php` | Product CRUD, search, statistics, deletion impact |
| **Intake** | `app/Http/Controllers/Product/IntakeQueueController.php` | Mobile barcode scan, intake queue, photo upload |
| **eBay API** | `app/Http/Controllers/Ebay/EbayApiController.php` | eBay status, listings, sync operations |
| **Dashboard** | `app/Http/Controllers/Dashboard/DashboardLiveDataController.php` | Role-specific live dashboard data |
| **Notifications** | (inline in routes/api.php) | Recent notifications, mark read, delete |
| **Alerts** | `app/Http/Controllers/Api/InventoryAlertController.php` | Stock alerts, config, dismiss/restore |
| **Monitoring** | `app/Http/Controllers/Api/MonitoringApiController.php` | Rate limits, quality metrics, ROI |
| **Printing** | `app/Http/Controllers/Printing/PrintController.php` | Single/batch label printing |
| **Supplier** | `app/Http/Controllers/Api/SupplierApiController.php` | Supplier profile, earnings, performance |
| **Picking** | `app/Http/Controllers/Api/MobilePickController.php` | Order picking, barcode verification, pick/short items |
| **Orders** | `app/Http/Controllers/Api/MobileOrderController.php` | Mobile orders API: list, detail, assign, hold/release |
| **Packing** | `app/Http/Controllers/Api/MobilePackController.php` | Mobile packing API: queue, start, rates, label purchase, complete |
| **Shipping** | `app/Http/Controllers/Api/MobileShipController.php` | Mobile shipping API: queue, confirm shipped, void label |
| **Returns** | `app/Http/Controllers/Api/MobileReturnController.php` | Mobile returns API: list, detail, approve, receive, inspect, restock |
| **Inventory Ops** | `app/Http/Controllers/Api/Inventory/InventoryController.php` | Inventory operations: adjust, transfer, movements, levels |

## Route Structure

### Public Routes (no auth)
```
POST   /api/auth/login              -- Login, returns Bearer token
POST   /api/auth/register           -- Register + auto-login
POST   /api/auth/forgot-password    -- Request password reset
POST   /api/auth/reset-password     -- Reset with token
POST   /api/auth/two-factor-challenge -- 2FA verification
POST   /api/detect-identifier-type  -- Barcode type detection
GET    /api/health                  -- System health (DB, Redis)
GET    /api/webhooks/ebay           -- ECN challenge response
POST   /api/webhooks/ebay           -- ECN event handler
POST   /api/webhooks/amazon         -- Amazon SNS handler
POST   /shopify/webhooks            -- Shopify event handler
POST   /webhooks/easypost           -- EasyPost tracking
POST   /webhooks/shipstation        -- ShipStation events
POST   /webhooks/shippingeasy       -- ShippingEasy events
```

### Protected Routes (auth:sanctum)

**Auth Management**
```
POST   /api/auth/logout             -- Revoke current token
GET    /api/auth/profile            -- Current user info
POST   /api/auth/refresh            -- New token (old revoked)
```

**Mobile Intake Queue** (`/api/mobile/intake/`)
```
POST   /api/mobile/intake/          -- Submit scanned product (identifiers, photos, notes)
POST   /api/mobile/intake/scan-barcode -- Check barcode type + duplicates
GET    /api/mobile/intake/queue-stats  -- My captures, team pending, processed today
GET    /api/mobile/intake/identifier-types -- Available identifier types
GET    /api/mobile/intake/my-captures  -- User's recent captures
POST   /api/mobile/intake/{item}/photos -- Add photos to queue item
PATCH  /api/mobile/intake/{item}    -- Update notes/quantity/location
```

**Products** (`/api/products/`)
```
GET    /api/products/               -- List (paginated, searchable, filterable)
POST   /api/products/               -- Create product
GET    /api/products/statistics      -- Total, low stock, value, avg price
GET    /api/products/categories      -- Category list
GET    /api/products/locations       -- Location list
GET    /api/products/barcode-lookup   -- Barcode lookup (scan-to-lookup mode)
GET    /api/products/{product}       -- Product details
PUT    /api/products/{product}       -- Update product
DELETE /api/products/{product}       -- Delete (soft)
GET    /api/products/{product}/deletion-impact -- Impact analysis
DELETE /api/products/{product}/force -- Force delete
POST   /api/products/{product}/restore -- Restore deleted
```

**Intake Queue Items** (`/api/intake-queue/`)
```
GET    /api/intake-queue/           -- User's queue items
GET    /api/intake-queue/{item}     -- Item details
POST   /api/intake-queue/{item}/extension-research -- Chrome extension data
POST   /api/intake-queue/{item}/retry -- Retry failed item
```

**Dashboard** (`/api/dashboard/`)
```
GET    /api/dashboard/{type}        -- Live data (owner|buyer|lister|fulfillment)
```

**Fulfillment Picking** (`/api/fulfillment/pick/`)
```
GET    /api/fulfillment/pick/                       -- Pick list (orders ready, stats, locations)
GET    /api/fulfillment/pick/{order}                -- Single order detail for picking
POST   /api/fulfillment/pick/{order}/start          -- Start picking (status: assigned -> picking)
POST   /api/fulfillment/pick/{order}/complete       -- Complete pick (status: picking -> picked)
POST   /api/fulfillment/pick/items/{item}/pick      -- Mark item picked (optional barcode verify)
POST   /api/fulfillment/pick/items/{item}/short     -- Mark item short (requires reason)
POST   /api/fulfillment/pick/items/{item}/scan-verify -- Verify barcode matches item (no pick)
```

**Fulfillment Orders** (`/api/fulfillment/orders/`)
```
GET    /api/fulfillment/orders/                    -- Order list (filterable by status, priority)
GET    /api/fulfillment/orders/{order}             -- Order detail with items
POST   /api/fulfillment/orders/{order}/assign      -- Assign order to location/user
POST   /api/fulfillment/orders/{order}/hold        -- Place order on hold
POST   /api/fulfillment/orders/{order}/release     -- Release order from hold
```

**Fulfillment Pack** (`/api/fulfillment/pack/`)
```
GET    /api/fulfillment/pack/                      -- Pack queue (orders ready to pack)
POST   /api/fulfillment/pack/{order}/start         -- Start packing (status: picked -> packing)
GET    /api/fulfillment/pack/{order}/rates         -- Get shipping rates for order
POST   /api/fulfillment/pack/{order}/purchase-label -- Purchase shipping label
POST   /api/fulfillment/pack/{order}/complete      -- Complete packing (status: packing -> packed)
GET    /api/fulfillment/pack/presets               -- Package presets (box dimensions)
```

**Fulfillment Ship** (`/api/fulfillment/ship/`)
```
GET    /api/fulfillment/ship/                      -- Ship queue (orders ready to ship)
GET    /api/fulfillment/ship/{order}               -- Shipment details for order
POST   /api/fulfillment/ship/{order}/confirm       -- Confirm shipped (status: packed -> shipped)
POST   /api/fulfillment/ship/{shipment}/void       -- Void shipping label
GET    /api/fulfillment/ship/stats                 -- Shipping stats (today, pending, etc.)
```

**Fulfillment Returns** (`/api/fulfillment/returns/`)
```
GET    /api/fulfillment/returns/                   -- Return list (filterable by status)
GET    /api/fulfillment/returns/{return}           -- Return detail
POST   /api/fulfillment/returns/{return}/approve   -- Approve return request
POST   /api/fulfillment/returns/{return}/receive   -- Mark return received
POST   /api/fulfillment/returns/{return}/inspect   -- Record inspection results
POST   /api/fulfillment/returns/{return}/restock   -- Restock returned items
```

**PO Receiving** (`/api/receiving/`)
```
GET    /api/receiving/                                 -- Open POs (submitted/partial) + stats
GET    /api/receiving/{purchaseOrder}                  -- PO detail with per-line ordered/received/remaining
POST   /api/receiving/{purchaseOrder}/complete         -- Receive all remaining lines (good condition)
POST   /api/receiving/{purchaseOrder}/items/{item}/scan-verify -- Verify barcode matches a PO line
POST   /api/receiving/{purchaseOrder}/items/{item}/receive     -- Receive qty against a line (audits po.received)
```

**Labeling Work Orders** (`/api/labeling/`)
```
GET    /api/labeling/                                  -- Actionable work orders + stats
GET    /api/labeling/suggest-bin                       -- Suggest a bin for a product
GET    /api/labeling/{workOrder}                       -- Work order detail + progress
POST   /api/labeling/{workOrder}/start | /complete | /mark-all-labeled
POST   /api/labeling/items/{item}/mark-labeled | /scan-bin | /skip | /verify
```

**Buyer Messaging** (`/api/messages/`)
```
GET    /api/messages/                                  -- Inbox list + unread/marketplace stats
GET    /api/messages/{message}                         -- Thread (conversation + replies); marks read
POST   /api/messages/{message}/reply                   -- Send reply (audits message.replied, source mobile)
POST   /api/messages/{message}/mark-read | /archive
```

**Warehouse Bins** (`/api/bins/`)
```
GET    /api/bins/                                      -- List bins (filter location/zone/search)
GET    /api/bins/scan?barcode=                         -- Resolve scanned bin -> bin + contents
GET    /api/bins/{bin}                                 -- Bin detail with contents
POST   /api/bins/{bin}/assign                          -- Place product (id/barcode) into bin (audits inventory.bin_assigned)
```

**Bundles** (`/api/bundles/`, read-only)
```
GET    /api/bundles/                                   -- Bundles with live computed availability + components
GET    /api/bundles/{bundle}                           -- Bundle detail with components
```

**Inventory Operations** (`/api/inventory/`)
```
POST   /api/inventory/adjust                       -- Stock adjustment (cycle count, damage, etc.)
POST   /api/inventory/transfer                     -- Transfer stock between locations
GET    /api/inventory/movements                    -- Movement history (filterable)
GET    /api/inventory/levels                       -- Inventory levels by location
GET    /api/inventory/levels/{product}             -- Levels for specific product
GET    /api/inventory/locations                    -- Available locations
GET    /api/inventory/adjustment-reasons           -- Adjustment reason codes
```

**Marketplace Status** (`/api/marketplace/`)
```
GET    /api/marketplace/status                     -- Connection health for all marketplaces
```

**Notifications** (`/api/notifications/`)
```
GET    /api/notifications/recent    -- Recent notifications
POST   /api/notifications/{id}/read -- Mark as read
POST   /api/notifications/mark-all-read -- Mark all read
DELETE /api/notifications/{id}      -- Delete notification
```

**Inventory Alerts** (`/api/v1/inventory/alerts/`)
```
GET    /api/v1/inventory/alerts/         -- List active alerts
GET    /api/v1/inventory/alerts/summary  -- Alert counts by type
GET    /api/v1/inventory/alerts/products -- Alerts grouped by product
GET    /api/v1/inventory/alerts/config   -- Alert thresholds
PUT    /api/v1/inventory/alerts/config   -- Update thresholds
POST   /api/v1/inventory/alerts/{id}/dismiss -- Dismiss alert
POST   /api/v1/inventory/alerts/dismiss-all  -- Dismiss all
POST   /api/v1/inventory/alerts/{id}/restore -- Restore dismissed
DELETE /api/v1/inventory/alerts/dismissed    -- Clear dismissed
```

**Printing** (`/api/v1/printing/`)
```
POST   /api/v1/printing/print/single/{product} -- Print single label
POST   /api/v1/printing/print/batch            -- Print batch labels
POST   /api/v1/printing/printers/discover      -- Discover network printers
POST   /api/v1/printing/printers/add-discovered -- Add discovered printer
POST   /api/v1/printing/printers/{printer}/test -- Test printer
POST   /api/v1/printing/templates/create-defaults -- Create default templates
```

**eBay** (`/api/ebay/`)
```
GET    /api/ebay/status             -- Connection status
GET    /api/ebay/stats              -- Listing statistics
GET    /api/ebay/listings           -- All listings (paginated)
GET    /api/ebay/listings/{listing} -- Listing details
POST   /api/ebay/listings/{listing}/update -- Queue inventory/price update (update_type: quantity|price|both)
POST   /api/ebay/listings/{listing}/retry  -- Re-queue a failed listing for sync
POST   /api/ebay/sync/product      -- Sync single product
POST   /api/ebay/sync/bulk         -- Bulk sync
POST   /api/ebay/sync/all          -- Full sync
GET    /api/ebay/logs               -- Sync logs
GET    /api/ebay/policies           -- Business policies
PATCH  /api/ebay/config             -- Update config
```

**Monitoring** (`/api/v1/monitoring/`)
```
GET    /api/v1/monitoring/rate-limit-status    -- eBay rate limits
GET    /api/v1/monitoring/quality-metrics       -- Data quality scores
GET    /api/v1/monitoring/recent-activity       -- Recent system activity
GET    /api/v1/monitoring/recent-errors         -- Recent errors
GET    /api/v1/monitoring/source-performance    -- Performance by source
GET    /api/v1/monitoring/roi-summary           -- Business metrics
GET    /api/v1/monitoring/daily-snapshot        -- Daily metrics
GET    /api/v1/monitoring/weekly-comparison     -- Week-over-week
```

**Supplier** (`/api/v1/supplier/`)
```
GET    /api/v1/supplier/profile                -- Supplier summary
GET    /api/v1/supplier/earnings               -- Earnings data
GET    /api/v1/supplier/performance            -- Performance metrics
GET    /api/v1/supplier/performance/trends     -- Performance trends
GET    /api/v1/supplier/settlements            -- Settlement list
GET    /api/v1/supplier/settlements/pending    -- Pending settlements
GET    /api/v1/supplier/settlements/{id}       -- Settlement details
```

## Response Format

All endpoints return consistent JSON:

**Success (single resource)**
```json
{ "success": true, "message": "...", "data": { ... } }
```

**Success (paginated list)**
```json
{
  "success": true,
  "data": [ ... ],
  "meta": { "total": 100, "per_page": 20, "current_page": 1, "last_page": 5 }
}
```

**Error**
```json
{ "success": false, "message": "Human-readable error", "error": "Technical detail" }
```

**Status codes**: 200 (success), 201 (created), 400 (bad request), 401 (unauthorized), 403 (forbidden), 404 (not found), 422 (validation), 500 (server error)

## Authentication Flow

1. `POST /api/auth/login` with `{email, password}` -> returns `{access_token, token_type: "Bearer"}`
2. All subsequent requests: `Authorization: Bearer {token}` header
3. On 401: call `POST /api/auth/refresh` -> new token, retry request
4. Logout: `POST /api/auth/logout` -> revokes current Sanctum token

Token creation: `$user->createToken('auth_token')->plainTextToken`

## Organization Scoping

All data is tenant-scoped. Controllers resolve org ID:
```php
$organizationId = $request->user()->organization_id
    ?? $request->user()->current_organization_id
    ?? $request->user()->currentTeam?->id ?? 1;
```

Models use `forOrganization($id)` scope for all queries.

## Adding New Mobile API Endpoints

### Step 1: Add routes in `routes/api.php`
```php
Route::middleware(['auth:sanctum'])->group(function () {
    Route::prefix('your-feature')->name('api.your-feature.')->group(function () {
        Route::get('/', [YourController::class, 'index'])->name('index');
        Route::post('/', [YourController::class, 'store'])->name('store');
        Route::get('/{resource}', [YourController::class, 'show'])->name('show');
        Route::put('/{resource}', [YourController::class, 'update'])->name('update');
    });
});
```

### Step 2: Create controller in `app/Http/Controllers/Api/`
```php
namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class YourController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $organizationId = $request->user()->organization_id
            ?? $request->user()->current_organization_id
            ?? $request->user()->currentTeam?->id ?? 1;

        $items = YourModel::forOrganization($organizationId)
            ->paginate($request->input('per_page', 20));

        return response()->json([
            'success' => true,
            'data' => $items->map(fn($item) => $this->format($item)),
            'meta' => [
                'total' => $items->total(),
                'per_page' => $items->perPage(),
                'current_page' => $items->currentPage(),
                'last_page' => $items->lastPage(),
            ],
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([...]);

        try {
            $item = YourModel::create([
                ...$validated,
                'organization_id' => $organizationId,
                'created_by' => $request->user()->id,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Created successfully',
                'data' => $this->format($item),
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Creation failed',
                'error' => $e->getMessage(),
            ], 500);
        }
    }
}
```

### Step 3: Add Flutter service + provider + page (see flutter-mobile skill)

## Rate Limiting

| Route Group | Throttle | Purpose |
|-------------|----------|---------|
| `/api/auth/*` | `throttle:auth` | Prevent brute force |
| `/webhooks/*` | `throttle:webhooks` | External service pushes |
| All protected | Default Laravel rate | General API usage |

## Middleware Stack

Protected API routes use: `auth:sanctum`
Web routes add: `EnsureOrganizationExists`, `CheckUserPermissions`, `CheckInventoryPermissions`
Mobile endpoints only need `auth:sanctum` -- permission checks happen in controller via `$user->hasPermission()` or `$this->authorize()`

## Audit Logging

Mobile API endpoints emit the same action vocabulary as their web counterparts, distinguished by `metadata.source = 'mobile'`. Examples: `order.picked` from `/api/mobile/orders/{id}/pick` carries `metadata.source = 'mobile'`; `inventory.adjusted` from `/api/mobile/inventory/scan-adjust` carries `metadata.source = 'mobile'`. Every mobile mutation endpoint MUST include source in metadata so Admin Audit Hub can filter mobile-originated activity.

Trait: models in this feature use `LogsAudit` (`app/Traits/LogsAudit.php`) for auto CRUD diff. Domain lifecycle events emitted from all mutation endpoints in `app/Http/Controllers/Api/` via `UserActivityLog::log(action, desc, $user, $metadata, $severity, $category)`.

Mobile-originated rows contribute to `UserProductivityDaily` via the same action prefixes. Workforce hub can split mobile vs web throughput by source filter.

Spec: `docs/AUDIT_SYSTEM.md`. Enforced rule: `.claude/rules/audit-logging.md`. Tests: `use Tests\Support\AssertsAudits;` then `$this->assertAudited('action', $user, [...])`.
