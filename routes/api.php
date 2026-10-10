<?php

use App\Http\Controllers\Amazon\AmazonNotificationController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\DashboardLiveDataController;
use App\Http\Controllers\Api\Ebay\EbayApiController;
use App\Http\Controllers\Api\ProductController;
use App\Http\Controllers\Api\SearchController;
use App\Http\Controllers\Ebay\EbayNotificationController;
use App\Http\Controllers\Notification\NotificationController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "api" middleware group. Make something great!
|
*/

// ================================
// PUBLIC ROUTES
// ================================

// Auth routes (strict rate limiting to prevent brute force)
Route::prefix('auth')->middleware('throttle:auth')->group(function () {
    Route::post('register', [AuthController::class, 'register']);
    Route::post('login', [AuthController::class, 'login']);
    Route::post('forgot-password', [AuthController::class, 'forgotPassword']);
    Route::post('reset-password', [AuthController::class, 'resetPassword']);
    Route::post('two-factor-challenge', [AuthController::class, 'twoFactorChallenge']);
});

// eBay ECN webhook endpoints (public -- eBay pushes to these, no auth)
// Per-credential endpoints for multi-account support + legacy fallback
Route::middleware('throttle:webhooks')->group(function () {
    Route::get('/webhooks/ebay/{credential}', [EbayNotificationController::class, 'challenge'])->name('webhooks.ebay.credential.challenge');
    Route::post('/webhooks/ebay/{credential}', [EbayNotificationController::class, 'handle'])->name('webhooks.ebay.credential.handle');
    Route::get('/webhooks/ebay', [EbayNotificationController::class, 'challenge'])->name('webhooks.ebay.challenge');
    Route::post('/webhooks/ebay', [EbayNotificationController::class, 'handle'])->name('webhooks.ebay.handle');
});

// Amazon SNS notification endpoint (public -- Amazon pushes to this, no auth)
Route::post('/webhooks/amazon', [AmazonNotificationController::class, 'handle'])
    ->middleware('throttle:webhooks')
    ->name('webhooks.amazon.handle');

// Barcode detection (no auth required)
Route::post('/detect-identifier-type', function (Request $request) {
    $request->validate(['value' => 'required|string']);

    $type = \App\Models\Catalog\ProductIdentifier::detectIdentifierType($request->value);

    return response()->json([
        'success' => true,
        'detected_type' => $type,
        'formatted_type' => \App\Models\Catalog\ProductIdentifier::getValidIdentifierTypes()[$type] ?? 'Unknown',
    ]);
});

// Health check endpoint (public - for mobile app to check system status)
Route::get('/health', function () {
    try {
        $dbUp = \Illuminate\Support\Facades\DB::connection()->getPdo() !== null;
        $redisUp = \Illuminate\Support\Facades\Redis::connection()->ping();

        $isHealthy = $dbUp && $redisUp;

        $rateLimiterStatus = 'unknown';
        $apiUsage = 'unknown';
        try {
            $rateLimiter = app(\App\Services\EbayRateLimiter::class);
            $status = $rateLimiter->getStatus();
            $rateLimiterStatus = $status['daily']['percentage_used'] < 90 ? 'healthy' : 'warning';
            $apiUsage = $status['daily']['percentage_used'].'%';
        } catch (\Exception $e) {
            $rateLimiterStatus = 'unavailable';
        }

        return response()->json([
            'success' => true,
            'status' => $isHealthy ? 'healthy' : 'degraded',
            'timestamp' => now()->toIso8601String(),
            'services' => [
                'database' => $dbUp ? 'up' : 'down',
                'redis' => $redisUp ? 'up' : 'down',
                'rate_limiter' => $rateLimiterStatus,
                'api_usage' => $apiUsage,
            ],
        ], $isHealthy ? 200 : 503);
    } catch (\Exception $e) {
        return response()->json([
            'success' => false,
            'status' => 'error',
            'message' => 'System unavailable',
            'timestamp' => now()->toIso8601String(),
        ], 503);
    }
});

// ================================
// PROTECTED ROUTES (Requires Authentication)
// ================================

Route::middleware(['auth:sanctum'])->group(function () {

    // ================================
    // AUTH ROUTES
    // ================================
    Route::prefix('auth')->group(function () {
        Route::post('logout', [AuthController::class, 'logout']);
        Route::get('profile', [AuthController::class, 'profile']);
        Route::post('refresh', [AuthController::class, 'refresh']);

        Route::post('/fcm-token', function (Request $request) {
            $data = $request->validate([
                'token' => 'required|string|max:512',
                'platform' => 'required|in:ios,android',
                'device_name' => 'nullable|string|max:191',
                'app_version' => 'nullable|string|max:32',
            ]);

            $user = $request->user();

            $row = \App\Models\Notification\DeviceToken::updateOrCreate(
                ['fcm_token' => $data['token']],
                [
                    'user_id' => $user->id,
                    'organization_id' => $user->organization_id,
                    'platform' => $data['platform'],
                    'device_name' => $data['device_name'] ?? null,
                    'app_version' => $data['app_version'] ?? null,
                    'last_seen_at' => now(),
                    'revoked_at' => null,
                ],
            );

            return response()->json(['success' => true, 'id' => $row->id]);
        })->name('api.auth.fcm-token');

        Route::delete('/fcm-token', function (Request $request) {
            $data = $request->validate(['token' => 'required|string|max:512']);

            \App\Models\Notification\DeviceToken::where('user_id', $request->user()->id)
                ->where('fcm_token', $data['token'])
                ->update(['revoked_at' => now()]);

            return response()->json(['success' => true]);
        })->name('api.auth.fcm-token.revoke');
    });

    // User info
    Route::get('user', function (Request $request) {
        return response()->json([
            'success' => true,
            'data' => new \App\Http\Resources\UserResource($request->user()),
        ]);
    });

    // ================================
    // SEARCH & WORKFLOW API
    // ================================
    Route::get('/omni-search', [SearchController::class, 'omniSearch'])->name('api.omni-search');
    Route::get('/workflow-counts', [SearchController::class, 'workflowCounts'])->name('api.workflow-counts');
    Route::post('/intake-queue/quick-add', [SearchController::class, 'quickIntakeAdd'])->name('api.intake-queue.quick-add');

    // ================================
    // NOTIFICATION API ROUTES (NEW)
    // ================================
    Route::prefix('notifications')->name('api.notifications.')->group(function () {
        Route::get('/recent', [NotificationController::class, 'recent']);
        Route::post('/{id}/read', [NotificationController::class, 'markAsRead']);
        Route::post('/mark-all-read', [NotificationController::class, 'markAllAsRead']);
        Route::delete('/{id}', [NotificationController::class, 'destroy']);
    });

    // eBay API Routes
    Route::prefix('ebay')->name('api.ebay.')->group(function () {

        // Status and Stats
        Route::get('/status', [EbayApiController::class, 'status'])->name('status');
        Route::get('/stats', [EbayApiController::class, 'stats'])->name('stats');

        // Listings
        Route::get('/listings', [EbayApiController::class, 'listings'])->name('listings');
        Route::get('/listings/{listing}', [EbayApiController::class, 'listing'])->name('listings.show');
        Route::post('/listings/{listing}/update', [EbayApiController::class, 'updateListing'])->name('listings.update');
        Route::post('/listings/{listing}/retry', [EbayApiController::class, 'retryListing'])->name('listings.retry');

        // Sync operations
        Route::post('/sync/product', [EbayApiController::class, 'syncProduct'])->name('sync.product');
        Route::post('/sync/bulk', [EbayApiController::class, 'syncBulk'])->name('sync.bulk');
        Route::post('/sync/all', [EbayApiController::class, 'syncAll'])->name('sync.all');
        Route::post('/retry/all', [EbayApiController::class, 'retryAll'])->name('retry.all');

        // Logs
        Route::get('/logs', [EbayApiController::class, 'logs'])->name('logs');

        // Configuration
        Route::get('/policies', [EbayApiController::class, 'policies'])->name('policies');
        Route::get('/categories/suggest', [EbayApiController::class, 'categorySuggestions'])->name('categories.suggest');
        Route::patch('/config', [EbayApiController::class, 'updateConfig'])->name('config.update');
    });

    // ================================
    // LABEL PRINTING API ROUTES (OPERATIONS)
    // ================================
    Route::middleware(['auth:sanctum'])->prefix('api/v1/printing')->name('api.v1.printing.')->group(function () {

        // Print Operations (Keep your existing controllers)
        Route::post('/print/single/{product}', [App\Http\Controllers\Printing\LabelPrintingController::class, 'printSingle'])
            ->name('print.single');
        Route::post('/print/batch', [App\Http\Controllers\Printing\LabelPrintingController::class, 'printBatch'])
            ->name('print.batch');

        // Printer Management Operations (Keep your existing controllers)
        Route::post('/printers/discover', [App\Http\Controllers\Printing\PrinterManagementController::class, 'discover'])
            ->name('printers.discover');
        Route::post('/printers/add-discovered', [App\Http\Controllers\Printing\PrinterManagementController::class, 'addDiscovered'])
            ->name('printers.add-discovered');
        Route::post('/printers/{printer}/test', [App\Http\Controllers\Printing\PrinterManagementController::class, 'test'])
            ->name('printers.test');
        Route::post('/templates/create-defaults', [App\Http\Controllers\Printing\PrinterManagementController::class, 'createDefaultTemplates'])
            ->name('templates.create-defaults');

        /*
            // CRUD Operations for API (Optional - for AJAX calls from Vue components)
            Route::apiResource('printers', App\Http\Controllers\Printing\PrinterController::class, ['as' => 'api']);
            Route::apiResource('templates', App\Http\Controllers\Printing\TemplateController::class, ['as' => 'api']);
            Route::apiResource('jobs', App\Http\Controllers\Printing\JobController::class, ['as' => 'api'])->only(['index', 'show']);
            Route::apiResource('locations', App\Http\Controllers\Printing\LocationController::class, ['as' => 'api']);
            */
    });
    // ================================
    // USER/TEAM MANAGEMENT API ROUTES (Mobile App)
    // ================================

    // ================================
    // USERS API
    // ================================
    Route::prefix('users')->name('api.users.')->group(function () {
        Route::get('/', [App\Http\Controllers\Api\UserController::class, 'index'])
            ->name('index');

        Route::post('/', [App\Http\Controllers\Api\UserController::class, 'store'])
            ->middleware('api.permission:users.create')
            ->name('store');

        Route::get('/{user}', [App\Http\Controllers\Api\UserController::class, 'show'])
            ->name('show');

        Route::put('/{user}', [App\Http\Controllers\Api\UserController::class, 'update'])
            ->middleware('api.permission:users.edit')
            ->name('update');

        Route::delete('/{user}', [App\Http\Controllers\Api\UserController::class, 'destroy'])
            ->middleware('api.permission:users.delete')
            ->name('destroy');

        // User profile and preferences (mobile specific)
        Route::get('/{user}/activity', [App\Http\Controllers\Api\UserController::class, 'activity'])
            ->name('activity');

        Route::post('/{user}/avatar', [App\Http\Controllers\Api\UserController::class, 'updateAvatar'])
            ->middleware('api.permission:users.edit')
            ->name('update-avatar');

        Route::put('/profile/preferences', [App\Http\Controllers\Api\UserController::class, 'updatePreferences'])
            ->name('update-preferences');
    });

    // ================================
    // TEAMS API
    // ================================
    Route::prefix('teams')->name('api.teams.')->group(function () {
        Route::get('/', [App\Http\Controllers\Api\TeamController::class, 'index'])
            ->name('index');

        Route::post('/', [App\Http\Controllers\Api\TeamController::class, 'store'])
            ->middleware('api.permission:teams.create')
            ->name('store');

        Route::get('/{team}', [App\Http\Controllers\Api\TeamController::class, 'show'])
            ->name('show');

        Route::put('/{team}', [App\Http\Controllers\Api\TeamController::class, 'update'])
            ->middleware('api.permission:teams.edit')
            ->name('update');

        Route::delete('/{team}', [App\Http\Controllers\Api\TeamController::class, 'destroy'])
            ->middleware('api.permission:teams.delete')
            ->name('destroy');

        // Team membership (mobile actions)
        Route::post('/{team}/join', [App\Http\Controllers\Api\TeamController::class, 'join'])
            ->name('join');

        Route::delete('/{team}/leave', [App\Http\Controllers\Api\TeamController::class, 'leave'])
            ->name('leave');

        Route::get('/{team}/members', [App\Http\Controllers\Api\TeamController::class, 'members'])
            ->name('members');

        Route::get('/{team}/activity', [App\Http\Controllers\Api\TeamController::class, 'activity'])
            ->name('activity');
    });

    // ================================
    // ROLES API
    // ================================
    Route::prefix('roles')->name('api.roles.')->group(function () {
        Route::get('/', [App\Http\Controllers\Api\RoleController::class, 'index'])
            ->name('index');

        Route::post('/', [App\Http\Controllers\Api\RoleController::class, 'store'])
            ->middleware('api.permission:roles.create')
            ->name('store');

        Route::get('/{role}', [App\Http\Controllers\Api\RoleController::class, 'show'])
            ->name('show');

        Route::put('/{role}', [App\Http\Controllers\Api\RoleController::class, 'update'])
            ->middleware('api.permission:roles.edit')
            ->name('update');

        Route::delete('/{role}', [App\Http\Controllers\Api\RoleController::class, 'destroy'])
            ->middleware('api.permission:roles.delete')
            ->name('destroy');

        // Permission management
        Route::get('/permissions/available', [App\Http\Controllers\Api\RoleController::class, 'availablePermissions'])
            ->name('available-permissions');

        Route::post('/{role}/permissions', [App\Http\Controllers\Api\RoleController::class, 'updatePermissions'])
            ->middleware('api.permission:roles.manage_permissions')
            ->name('update-permissions');
    });

    // ================================
    // ORGANIZATION API
    // ================================
    Route::prefix('organization')->name('api.organization.')->group(function () {
        Route::get('/info', [App\Http\Controllers\Api\OrganizationController::class, 'info'])
            ->name('info');

        Route::get('/settings', [App\Http\Controllers\Api\OrganizationController::class, 'settings'])
            ->name('settings');

        Route::put('/settings', [App\Http\Controllers\Api\OrganizationController::class, 'updateSettings'])
            ->middleware('api.permission:organization.edit')
            ->name('update-settings');

        Route::get('/stats', [App\Http\Controllers\Api\OrganizationController::class, 'stats'])
            ->name('stats');
    });

    // ================================
    // ONBOARDING API (Mobile) - Controller not yet built
    // ================================
    /*
    Route::prefix('onboarding')->name('api.onboarding.')->group(function () {
        Route::get('/progress', [App\Http\Controllers\Api\OnboardingController::class, 'progress'])
            ->name('progress');

        Route::post('/complete-step', [App\Http\Controllers\Api\OnboardingController::class, 'completeStep'])
            ->name('complete-step');

        Route::post('/skip-step', [App\Http\Controllers\Api\OnboardingController::class, 'skipStep'])
            ->name('skip-step');

        Route::post('/finish', [App\Http\Controllers\Api\OnboardingController::class, 'finish'])
            ->name('finish');

        Route::get('/checklist', [App\Http\Controllers\Api\OnboardingController::class, 'checklist'])
            ->name('checklist');
    });
    */

    // ================================
    // USER ACTIVITY & ANALYTICS API
    // ================================
    Route::prefix('analytics')->name('api.analytics.')->group(function () {
        // User activity tracking (mobile app usage)
        Route::post('/track-activity', [App\Http\Controllers\Api\AnalyticsController::class, 'trackActivity'])
            ->name('track-activity');

        // Team performance metrics
        Route::get('/team-performance', [App\Http\Controllers\Api\AnalyticsController::class, 'teamPerformance'])
            ->name('team-performance');

        // User productivity stats
        Route::get('/user-productivity', [App\Http\Controllers\Api\AnalyticsController::class, 'userProductivity'])
            ->name('user-productivity');

        // Organization overview (for mobile dashboard)
        Route::get('/organization-overview', [App\Http\Controllers\Api\AnalyticsController::class, 'organizationOverview'])
            ->name('organization-overview');
    });

    // ================================
    // MOBILE PRODUCT INTAKE ROUTES
    // ================================
    Route::prefix('mobile/intake')->name('mobile.intake.')->group(function () {
        Route::post('/', [App\Http\Controllers\Product\IntakeQueueController::class, 'mobileStore'])->middleware('api.permission:intake.process')->name('store');
        Route::post('/scan-barcode', [App\Http\Controllers\Product\IntakeQueueController::class, 'scanBarcode'])->name('scan-barcode');
        Route::get('/queue-stats', [App\Http\Controllers\Product\IntakeQueueController::class, 'getMobileQueueStats'])->name('queue-stats');
        Route::get('/identifier-types', [App\Http\Controllers\Product\IntakeQueueController::class, 'getIdentifierTypes'])->name('identifier-types');
        Route::get('/form-options', [App\Http\Controllers\Product\IntakeQueueController::class, 'getFormOptions'])->name('form-options');
        Route::get('/my-captures', [App\Http\Controllers\Product\IntakeQueueController::class, 'getMyCaptures'])->name('my-captures');
        Route::post('/{queueItem}/photos', [App\Http\Controllers\Product\IntakeQueueController::class, 'addPhotos'])->middleware('api.permission:intake.process')->name('add-photos');
        Route::patch('/{queueItem}', [App\Http\Controllers\Product\IntakeQueueController::class, 'mobileUpdate'])->middleware('api.permission:intake.process')->name('update');
    });

    // ================================
    // OPTIMIZATION MONITORING API (Mobile Dashboard)
    // ================================
    Route::prefix('monitoring')->name('monitoring.')->group(function () {

        // System health and rate limits
        Route::get('/rate-limit-status', [App\Http\Controllers\Api\MonitoringController::class, 'rateLimitStatus'])->name('rate-limit-status');

        // Quality metrics for mobile dashboard
        Route::get('/quality-metrics', [App\Http\Controllers\Api\MonitoringController::class, 'qualityMetrics'])->name('quality-metrics');

        // Recent activity (last processed items)
        Route::get('/recent-activity', [App\Http\Controllers\Api\MonitoringController::class, 'recentActivity'])->name('recent-activity');

        // Error tracking
        Route::get('/recent-errors', [App\Http\Controllers\Api\MonitoringController::class, 'recentErrors'])->name('recent-errors');

        // Data source performance
        Route::get('/source-performance', [App\Http\Controllers\Api\MonitoringController::class, 'sourcePerformance'])->name('source-performance');

        // ROI and business metrics (for managers)
        Route::get('/roi-summary', function () {
            $bi = app(\App\Services\BusinessIntelligenceService::class);
            $summary = $bi->generateExecutiveSummary(now()->subDays(30), now());

            return response()->json([
                'success' => true,
                'data' => $summary,
            ]);
        })->name('roi-summary');

        // Daily performance snapshot
        Route::get('/daily-snapshot', function () {
            $bi = app(\App\Services\BusinessIntelligenceService::class);
            $date = request()->input('date', now()->format('Y-m-d'));
            $snapshot = $bi->getDailySnapshot(\Carbon\Carbon::parse($date));

            return response()->json([
                'success' => true,
                'data' => $snapshot,
            ]);
        })->name('daily-snapshot');

        // Weekly comparison
        Route::get('/weekly-comparison', function () {
            $bi = app(\App\Services\BusinessIntelligenceService::class);
            $comparison = $bi->getWeeklyComparison();

            return response()->json([
                'success' => true,
                'data' => $comparison,
            ]);
        })->name('weekly-comparison');
    });
    // ================================
    // INTAKE QUEUE MANAGEMENT (Mobile)
    // ================================
    Route::prefix('intake-queue')->name('api.intake-queue.')->group(function () {

        // Chrome extension research data submission
        Route::post('/{queueItem}/extension-research', [App\Http\Controllers\Product\IntakeQueueController::class, 'storeExtensionResearch'])->middleware('api.permission:intake.process')->name('extension-research');

        // Retry failed item (mobile can trigger retry)
        Route::post('/{queueItem}/retry', [App\Http\Controllers\Api\MonitoringController::class, 'retryItem'])->middleware('api.permission:intake.process')->name('retry');

        // Get item details with quality score
        Route::get('/{queueItem}', function (\App\Models\Catalog\ProductIntakeQueue $queueItem) {
            return response()->json([
                'success' => true,
                'data' => [
                    'id' => $queueItem->id,
                    'manufacturer_part_number' => $queueItem->manufacturer_part_number,
                    'product_number' => $queueItem->product_number,
                    'barcode' => $queueItem->barcode,
                    'processing_status' => $queueItem->processing_status,
                    'quality_score' => $queueItem->quality_score,
                    'quality_breakdown' => $queueItem->quality_breakdown,
                    'validation_errors' => $queueItem->validation_errors,
                    'missing_fields' => $queueItem->missing_fields,
                    'data_sources' => $queueItem->data_sources,
                    'photos' => $queueItem->photos,
                    'enriched_data' => $queueItem->enriched_data,
                    'last_processed_at' => $queueItem->last_processed_at?->toIso8601String(),
                    'created_at' => $queueItem->created_at->toIso8601String(),
                ],
            ]);
        })->name('show');

        // Get user's queue items (mobile history)
        Route::get('/', function (Request $request) {
            $items = \App\Models\Catalog\ProductIntakeQueue::where('captured_by_user_id', $request->user()->id)
                ->where('team_id', $request->user()->currentTeam->id)
                ->latest()
                ->paginate(20);

            return response()->json([
                'success' => true,
                'data' => $items->map(function ($item) {
                    return [
                        'id' => $item->id,
                        'manufacturer_part_number' => $item->manufacturer_part_number,
                        'processing_status' => $item->processing_status,
                        'quality_score' => $item->quality_score,
                        'photos_count' => count($item->photos ?? []),
                        'created_at' => $item->created_at->toIso8601String(),
                        'last_processed_at' => $item->last_processed_at?->toIso8601String(),
                    ];
                }),
                'meta' => [
                    'total' => $items->total(),
                    'per_page' => $items->perPage(),
                    'current_page' => $items->currentPage(),
                    'last_page' => $items->lastPage(),
                ],
            ]);
        })->name('index');
    });

    // ================================
    // FULFILLMENT PICKING API (Mobile App)
    // ================================
    Route::middleware('api.role:fulfillment_manager,shipper,manager,admin')->prefix('fulfillment/pick')->name('api.fulfillment.pick.')->group(function () {
        Route::get('/', [App\Http\Controllers\Api\MobilePickController::class, 'index'])->name('index');
        Route::get('/{order}', [App\Http\Controllers\Api\MobilePickController::class, 'show'])->name('show');
        Route::post('/{order}/start', [App\Http\Controllers\Api\MobilePickController::class, 'start'])->name('start');
        Route::post('/{order}/complete', [App\Http\Controllers\Api\MobilePickController::class, 'complete'])->name('complete');
        Route::post('/items/{orderItem}/pick', [App\Http\Controllers\Api\MobilePickController::class, 'pickItem'])->name('item.pick');
        Route::post('/items/{orderItem}/short', [App\Http\Controllers\Api\MobilePickController::class, 'shortItem'])->name('item.short');
        Route::post('/items/{orderItem}/scan-verify', [App\Http\Controllers\Api\MobilePickController::class, 'scanVerify'])->name('item.scan-verify');
    });

    // ================================
    // FULFILLMENT ORDERS API (Mobile App)
    // ================================
    Route::middleware('api.role:fulfillment_manager,shipper,manager,admin')->prefix('fulfillment/orders')->name('api.fulfillment.orders.')->group(function () {
        Route::get('/', [App\Http\Controllers\Api\MobileOrderController::class, 'index'])->name('index');
        Route::get('/{order}', [App\Http\Controllers\Api\MobileOrderController::class, 'show'])->name('show');
        Route::post('/{order}/assign', [App\Http\Controllers\Api\MobileOrderController::class, 'assign'])->name('assign');
        Route::post('/{order}/hold', [App\Http\Controllers\Api\MobileOrderController::class, 'hold'])->name('hold');
        Route::post('/{order}/release', [App\Http\Controllers\Api\MobileOrderController::class, 'release'])->name('release');
    });

    // ================================
    // FULFILLMENT PACK API (Mobile App)
    // ================================
    Route::middleware('api.role:fulfillment_manager,shipper,manager,admin')->prefix('fulfillment/pack')->name('api.fulfillment.pack.')->group(function () {
        Route::get('/', [App\Http\Controllers\Api\MobilePackController::class, 'index'])->name('index');
        Route::get('/{order}', [App\Http\Controllers\Api\MobilePackController::class, 'show'])->name('show');
        Route::post('/{order}/start', [App\Http\Controllers\Api\MobilePackController::class, 'start'])->name('start');
        Route::post('/{order}/rates', [App\Http\Controllers\Api\MobilePackController::class, 'rates'])->name('rates');
        Route::post('/{order}/purchase-label', [App\Http\Controllers\Api\MobilePackController::class, 'purchaseLabel'])->name('purchase-label');
        Route::post('/{order}/complete', [App\Http\Controllers\Api\MobilePackController::class, 'complete'])->name('complete');
    });

    // ================================
    // FULFILLMENT SHIP API (Mobile App)
    // ================================
    Route::middleware('api.role:fulfillment_manager,shipper,manager,admin')->prefix('fulfillment/ship')->name('api.fulfillment.ship.')->group(function () {
        Route::get('/', [App\Http\Controllers\Api\MobileShipController::class, 'index'])->name('index');
        Route::get('/{shipment}', [App\Http\Controllers\Api\MobileShipController::class, 'show'])->name('show');
        Route::post('/{shipment}/confirm', [App\Http\Controllers\Api\MobileShipController::class, 'confirm'])->name('confirm');
        Route::post('/{shipment}/void', [App\Http\Controllers\Api\MobileShipController::class, 'voidLabel'])->name('void');
        Route::get('/{shipment}/label', [App\Http\Controllers\Api\MobileShipController::class, 'label'])->name('label');
    });

    // ================================
    // FULFILLMENT RETURNS API (Mobile App)
    // ================================
    Route::middleware('api.role:fulfillment_manager,support_agent,manager,admin')->prefix('fulfillment/returns')->name('api.fulfillment.returns.')->group(function () {
        Route::get('/', [App\Http\Controllers\Api\MobileReturnController::class, 'index'])->name('index');
        Route::get('/{returnRequest}', [App\Http\Controllers\Api\MobileReturnController::class, 'show'])->name('show');
        Route::post('/{returnRequest}/approve', [App\Http\Controllers\Api\MobileReturnController::class, 'approve'])->name('approve');
        Route::post('/{returnRequest}/receive', [App\Http\Controllers\Api\MobileReturnController::class, 'receive'])->name('receive');
        Route::post('/{returnRequest}/inspect', [App\Http\Controllers\Api\MobileReturnController::class, 'inspect'])->name('inspect');
        Route::post('/{returnRequest}/restock', [App\Http\Controllers\Api\MobileReturnController::class, 'restock'])->name('restock');
    });

    // ================================
    // PART-OUT API (Mobile App)
    // ================================
    Route::middleware('api.permission:inventory.manage_stock')->prefix('part-out')->name('api.part-out.')->group(function () {
        Route::get('/', [App\Http\Controllers\Api\MobilePartOutController::class, 'index'])->name('index');
        Route::get('/{workOrder}', [App\Http\Controllers\Api\MobilePartOutController::class, 'show'])->name('show');
        Route::post('/{workOrder}/start', [App\Http\Controllers\Api\MobilePartOutController::class, 'start'])->name('start');
        Route::post('/{workOrder}/complete', [App\Http\Controllers\Api\MobilePartOutController::class, 'complete'])->name('complete');
        Route::post('/{workOrder}/cancel', [App\Http\Controllers\Api\MobilePartOutController::class, 'cancel'])->name('cancel');
        Route::post('/items/{item}/extract', [App\Http\Controllers\Api\MobilePartOutController::class, 'extractItem'])->name('item.extract');
        Route::post('/items/{item}/skip', [App\Http\Controllers\Api\MobilePartOutController::class, 'skipItem'])->name('item.skip');
        Route::post('/items/{item}/notes', [App\Http\Controllers\Api\MobilePartOutController::class, 'updateItemNotes'])->name('item.notes');
        Route::post('/items/{item}/scan-verify', [App\Http\Controllers\Api\MobilePartOutController::class, 'scanVerify'])->name('item.scan-verify');
    });

    // ================================
    // LABELING API (Mobile App)
    // ================================
    Route::middleware('api.role:fulfillment_manager,shipper,manager,admin')->prefix('labeling')->name('api.labeling.')->group(function () {
        Route::get('/', [App\Http\Controllers\Api\MobileLabelingController::class, 'index'])->name('index');
        Route::get('/suggest-bin', [App\Http\Controllers\Api\MobileLabelingController::class, 'suggestBin'])->name('suggest-bin');
        Route::get('/{workOrder}', [App\Http\Controllers\Api\MobileLabelingController::class, 'show'])->name('show');
        Route::post('/{workOrder}/start', [App\Http\Controllers\Api\MobileLabelingController::class, 'start'])->name('start');
        Route::post('/{workOrder}/mark-all-labeled', [App\Http\Controllers\Api\MobileLabelingController::class, 'markAllLabeled'])->name('mark-all-labeled');
        Route::post('/{workOrder}/complete', [App\Http\Controllers\Api\MobileLabelingController::class, 'complete'])->name('complete');
        Route::post('/items/{item}/mark-labeled', [App\Http\Controllers\Api\MobileLabelingController::class, 'markLabeled'])->name('item.mark-labeled');
        Route::post('/items/{item}/scan-bin', [App\Http\Controllers\Api\MobileLabelingController::class, 'scanBin'])->name('item.scan-bin');
        Route::post('/items/{item}/skip', [App\Http\Controllers\Api\MobileLabelingController::class, 'skipItem'])->name('item.skip');
        Route::post('/items/{item}/verify', [App\Http\Controllers\Api\MobileLabelingController::class, 'verifyShelf'])->name('item.verify');
    });

    // ================================
    // PO RECEIVING API (Mobile App)
    // ================================
    Route::middleware('api.role:buyer,sourcing_specialist,manager,admin')->prefix('receiving')->name('api.receiving.')->group(function () {
        Route::get('/', [App\Http\Controllers\Api\MobileReceivingController::class, 'index'])->name('index');
        Route::get('/{purchaseOrder}', [App\Http\Controllers\Api\MobileReceivingController::class, 'show'])->name('show');
        Route::post('/{purchaseOrder}/complete', [App\Http\Controllers\Api\MobileReceivingController::class, 'complete'])->name('complete');
        Route::post('/{purchaseOrder}/items/{item}/scan-verify', [App\Http\Controllers\Api\MobileReceivingController::class, 'scanVerify'])->name('item.scan-verify');
        Route::post('/{purchaseOrder}/items/{item}/receive', [App\Http\Controllers\Api\MobileReceivingController::class, 'receiveItem'])->name('item.receive');
    });

    // ================================
    // BUYER MESSAGING API (Mobile App)
    // ================================
    Route::middleware('api.role:support_agent,manager,admin')->prefix('messages')->name('api.messages.')->group(function () {
        Route::get('/', [App\Http\Controllers\Api\MobileMessageController::class, 'index'])->name('index');
        Route::get('/{message}', [App\Http\Controllers\Api\MobileMessageController::class, 'show'])->name('show');
        Route::post('/{message}/reply', [App\Http\Controllers\Api\MobileMessageController::class, 'reply'])->name('reply');
        Route::post('/{message}/mark-read', [App\Http\Controllers\Api\MobileMessageController::class, 'markRead'])->name('mark-read');
        Route::post('/{message}/archive', [App\Http\Controllers\Api\MobileMessageController::class, 'archive'])->name('archive');
    });

    // ================================
    // WAREHOUSE BINS API (Mobile App)
    // ================================
    Route::prefix('bins')->name('api.bins.')->group(function () {
        Route::get('/', [App\Http\Controllers\Api\MobileBinController::class, 'index'])->name('index');
        Route::get('/scan', [App\Http\Controllers\Api\MobileBinController::class, 'scan'])->name('scan');
        Route::get('/{bin}', [App\Http\Controllers\Api\MobileBinController::class, 'show'])->name('show');
        Route::post('/{bin}/assign', [App\Http\Controllers\Api\MobileBinController::class, 'assign'])->middleware('api.permission:inventory.manage_stock')->name('assign');
    });

    // ================================
    // BUNDLES API (Mobile App, read-only)
    // ================================
    Route::prefix('bundles')->name('api.bundles.')->group(function () {
        Route::get('/', [App\Http\Controllers\Api\MobileBundleController::class, 'index'])->name('index');
        Route::get('/{bundle}', [App\Http\Controllers\Api\MobileBundleController::class, 'show'])->name('show');
    });

    // ================================
    // INVENTORY API (Mobile App)
    // ================================
    Route::prefix('inventory')->name('api.inventory.')->group(function () {
        Route::get('/levels', [App\Http\Controllers\Api\Inventory\InventoryController::class, 'index'])->name('levels');
        Route::post('/adjust', [App\Http\Controllers\Api\Inventory\InventoryController::class, 'adjustStock'])->middleware('api.permission:inventory.manage_stock')->name('adjust');
        Route::post('/transfer', [App\Http\Controllers\Api\Inventory\InventoryController::class, 'transferStock'])->middleware('api.permission:inventory.manage_stock')->name('transfer');
        Route::get('/movements', [App\Http\Controllers\Api\Inventory\InventoryController::class, 'movements'])->name('movements');
        Route::get('/low-stock', [App\Http\Controllers\Api\Inventory\InventoryController::class, 'lowStockAlerts'])->name('low-stock');
        Route::get('/valuation', [App\Http\Controllers\Api\Inventory\InventoryController::class, 'valuation'])->name('valuation');
        Route::get('/analytics', [App\Http\Controllers\Api\Inventory\InventoryController::class, 'analytics'])->name('analytics');
    });

    // ================================
    // CATEGORIES API (Mobile App)
    // ================================
    Route::prefix('categories')->name('api.categories.')->group(function () {
        Route::get('/', [App\Http\Controllers\Api\Inventory\CategoryController::class, 'index'])->name('index');
        Route::post('/', [App\Http\Controllers\Api\Inventory\CategoryController::class, 'store'])->middleware('api.permission:inventory.create')->name('store');
        Route::get('/{category}', [App\Http\Controllers\Api\Inventory\CategoryController::class, 'show'])->name('show');
        Route::put('/{category}', [App\Http\Controllers\Api\Inventory\CategoryController::class, 'update'])->middleware('api.permission:inventory.edit')->name('update');
        Route::delete('/{category}', [App\Http\Controllers\Api\Inventory\CategoryController::class, 'destroy'])->middleware('api.permission:inventory.delete')->name('destroy');
    });

    // ================================
    // LOCATIONS API (Mobile App)
    // ================================
    Route::prefix('locations')->name('api.locations.')->group(function () {
        Route::get('/', [App\Http\Controllers\Api\Inventory\LocationController::class, 'index'])->name('index');
        Route::post('/', [App\Http\Controllers\Api\Inventory\LocationController::class, 'store'])->middleware('api.permission:inventory.create')->name('store');
        Route::get('/{location}', [App\Http\Controllers\Api\Inventory\LocationController::class, 'show'])->name('show');
        Route::put('/{location}', [App\Http\Controllers\Api\Inventory\LocationController::class, 'update'])->middleware('api.permission:inventory.edit')->name('update');
        Route::delete('/{location}', [App\Http\Controllers\Api\Inventory\LocationController::class, 'destroy'])->middleware('api.permission:inventory.delete')->name('destroy');
    });

    // ================================
    // ANALYTICS API (Mobile App)
    // ================================
    Route::prefix('analytics')->name('api.analytics.')->group(function () {
        Route::get('/kpis', [\App\Http\Controllers\Inventory\InventoryAnalyticsController::class, 'getKPIs'])->name('kpis');
        Route::get('/stock-health', [\App\Http\Controllers\Inventory\InventoryAnalyticsController::class, 'getStockHealth'])->name('stock-health');
        Route::get('/movement-trends', [\App\Http\Controllers\Inventory\InventoryAnalyticsController::class, 'getMovementTrends'])->name('movement-trends');
        Route::get('/top-performers', [\App\Http\Controllers\Inventory\InventoryAnalyticsController::class, 'getTopPerformers'])->name('top-performers');
        Route::get('/location-performance', [\App\Http\Controllers\Inventory\InventoryAnalyticsController::class, 'getLocationPerformance'])->name('location-performance');
        Route::get('/abc', [\App\Http\Controllers\Inventory\InventoryAnalyticsController::class, 'getABCAnalysis'])->name('abc');
        Route::get('/turnover', [\App\Http\Controllers\Inventory\InventoryAnalyticsController::class, 'getTurnoverAnalysis'])->name('turnover');
        Route::get('/turnover-trend', [\App\Http\Controllers\Inventory\InventoryAnalyticsController::class, 'getTurnoverTrend'])->name('turnover-trend');
        Route::get('/inventory-value', [\App\Http\Controllers\Inventory\InventoryAnalyticsController::class, 'getInventoryValue'])->name('inventory-value');
    });

    // ================================
    // MARKETPLACE STATUS API (Mobile App)
    // ================================
    Route::get('/marketplace/status', function (Request $request) {
        $user = $request->user();
        $organizationId = $user->organization_id
            ?? $user->current_organization_id
            ?? $user->currentTeam?->id ?? 1;

        $credentials = \App\Models\Integration\IntegrationCredential::where('organization_id', $organizationId)
            ->whereIn('platform', ['ebay', 'shopify', 'amazon'])
            ->get();

        $marketplaces = $credentials->map(function ($cred) {
            return [
                'id' => $cred->id,
                'platform' => $cred->platform,
                'name' => $cred->name ?? $cred->platform,
                'is_active' => $cred->is_active ?? true,
                'connected_at' => $cred->created_at?->toIso8601String(),
                'last_sync_at' => $cred->last_sync_at?->toIso8601String(),
            ];
        });

        return response()->json([
            'success' => true,
            'data' => $marketplaces,
        ]);
    })->name('api.marketplace.status');

    // ================================
    // PRODUCTS API (Mobile App)
    // ================================
    Route::prefix('products')->name('api.products.')->group(function () {
        Route::get('/barcode-lookup', [ProductController::class, 'barcodeLookup'])->name('barcode-lookup');
        Route::get('/', [ProductController::class, 'index'])->name('index');
        Route::post('/', [ProductController::class, 'store'])->middleware('api.permission:products.create')->name('store');
        Route::get('/statistics', [ProductController::class, 'statistics'])->name('statistics');
        Route::get('/categories', [ProductController::class, 'categories'])->name('categories');
        Route::get('/locations', [ProductController::class, 'locations'])->name('locations');
        Route::get('/{product}', [ProductController::class, 'show'])->name('show');
        Route::put('/{product}', [ProductController::class, 'update'])->middleware('api.permission:products.edit')->name('update');
        Route::delete('/{product}', [ProductController::class, 'destroy'])->middleware('api.permission:products.delete')->name('destroy');
        Route::get('/{product}/deletion-impact', [ProductController::class, 'deletionImpact'])->name('deletion-impact');
        Route::delete('/{product}/force', [ProductController::class, 'forceDestroy'])->middleware('api.permission:products.delete')->name('force-destroy');
        Route::post('/{product}/restore', [ProductController::class, 'restoreProduct'])->middleware('api.permission:products.edit')->name('restore');
    });

    // ================================
    // DASHBOARD API (Mobile App)
    // ================================
    Route::prefix('dashboard')->name('api.dashboard.')->group(function () {
        Route::get('/{type}', [DashboardLiveDataController::class, 'getLiveData'])->name('live-data');
    });

    // ================================
    // PROFILE API (Mobile App)
    // ================================
    Route::prefix('profile')->name('api.profile.')->group(function () {
        Route::put('/', [AuthController::class, 'updateProfile'])->name('update');
        Route::put('/password', [AuthController::class, 'updatePassword'])->name('update-password');
        Route::get('/sessions', [AuthController::class, 'sessions'])->name('sessions');
        Route::delete('/sessions/{sessionId}', [AuthController::class, 'revokeSession'])->name('revoke-session');
    });
});
// Phase 2.3: Real-time Stock Tracking & Alerts
require __DIR__.'/api-inventory-alerts.php';
