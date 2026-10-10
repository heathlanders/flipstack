<?php

use App\Http\Controllers\Api\SearchController;
use App\Http\Controllers\Inventory\CategoryController;
use App\Http\Controllers\Inventory\InventoryController;
use App\Http\Controllers\Inventory\LocationController;
// Import Inventory Controllers
use App\Http\Controllers\Inventory\ProductController;
use App\Http\Controllers\Notification\NotificationController;
use App\Http\Controllers\Notification\NotificationPreferenceController;
use App\Http\Controllers\Organization\InvitationController;
use App\Http\Controllers\Organization\TeamManagementController;
use App\Http\Controllers\Settings\CurrencyController;
use App\Http\Controllers\User\RoleManagementController;
use App\Http\Controllers\User\UserManagementController;
use Illuminate\Foundation\Application;
// Import Notification Controllers
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/', function () {
    return Inertia::render('Welcome', [
        'canLogin' => Route::has('login'),
        'canRegister' => Route::has('register'),
        'laravelVersion' => Application::VERSION,
        'phpVersion' => PHP_VERSION,
    ]);
});

Route::get('/download', [App\Http\Controllers\DownloadController::class, 'index'])->name('download');

// ================================
// PASSWORD CHANGE ROUTES (Auth required but before full middleware)
// ================================
Route::middleware(['auth:sanctum'])->group(function () {
    Route::get('/password/change', [App\Http\Controllers\Auth\PasswordChangeController::class, 'show'])
        ->name('password.change');
    Route::post('/password/change', [App\Http\Controllers\Auth\PasswordChangeController::class, 'update'])
        ->name('password.change.update');
});

// ================================
// AUTHENTICATED ROUTES (MAIN GROUP)
// ================================
Route::middleware([
    'auth:sanctum',
    config('jetstream.auth_session'),
    'onboarding',
])->group(function () {

    // ================================
    // DASHBOARD ROUTES
    // ================================

    // Live data endpoints for each dashboard type
    Route::get('/live-data/{dashboardType}', [App\Http\Controllers\Api\DashboardLiveDataController::class, 'getLiveData'])
        ->name('live-data')
        ->where('dashboardType', 'owner|buyer|lister|fulfillment');

    // Additional endpoints for specific data
    Route::get('/alerts', [App\Http\Controllers\Api\DashboardLiveDataController::class, 'getAlerts'])
        ->name('alerts');

    Route::get('/platform-health', [App\Http\Controllers\Api\DashboardLiveDataController::class, 'getPlatformHealth'])
        ->name('platform-health');

    Route::get('/business-metrics', [App\Http\Controllers\Api\DashboardLiveDataController::class, 'getBusinessMetrics'])
        ->name('business-metrics');

    // Enhanced Dashboard Route
    Route::get('/dashboard', [App\Http\Controllers\Dashboard\DashboardController::class, 'index'])
        ->name('dashboard');

    // Role-specific dashboard routes
    Route::prefix('dashboard')->name('dashboard.')->group(function () {
        Route::get('/main', [App\Http\Controllers\Dashboard\MainDashboardController::class, 'show'])
            ->middleware('can:dashboard.owner')
            ->name('main');

        Route::get('/owner', [App\Http\Controllers\Dashboard\OwnerDashboardController::class, 'show'])
            ->middleware('can:dashboard.owner')
            ->name('owner');

        Route::get('/buyer', [App\Http\Controllers\Dashboard\BuyerDashboardController::class, 'show'])
            ->middleware('can:dashboard.buyer')
            ->name('buyer');

        Route::get('/lister', [App\Http\Controllers\Dashboard\ListerDashboardController::class, 'show'])
            ->middleware('can:dashboard.lister')
            ->name('lister');

        Route::get('/fulfillment', [App\Http\Controllers\Dashboard\FulfillmentDashboardController::class, 'show'])
            ->middleware('can:dashboard.fulfillment')
            ->name('fulfillment');

        Route::get('/support', [App\Http\Controllers\Dashboard\SupportDashboardController::class, 'show'])
            ->middleware('can:dashboard.support')
            ->name('support');
    });

    // ================================
    // SEARCH & WORKFLOW ROUTES (for session-based auth)
    // ================================
    Route::prefix('api')->group(function () {
        Route::get('/omni-search', [SearchController::class, 'omniSearch'])->name('web.omni-search');
        Route::get('/workflow-counts', [SearchController::class, 'workflowCounts'])->name('web.workflow-counts');
        Route::post('/intake-queue/quick-add', [SearchController::class, 'quickIntakeAdd'])->name('web.intake-queue.quick-add');
    });

    // ================================
    // NOTIFICATION ROUTES
    // ================================
    Route::prefix('notifications')->name('notifications.')->group(function () {
        Route::get('/', [NotificationController::class, 'index'])->name('index');
        Route::get('/preferences', [NotificationPreferenceController::class, 'edit'])->name('preferences');
        Route::post('/preferences', [NotificationPreferenceController::class, 'update'])->name('preferences.update');

        // API endpoints for notification bell
        Route::get('/api/recent', [NotificationController::class, 'recent'])->name('api.recent');
        Route::post('/api/{id}/read', [NotificationController::class, 'markAsRead'])->name('api.read');
        Route::post('/api/mark-all-read', [NotificationController::class, 'markAllAsRead'])->name('api.mark-all-read');
        Route::delete('/api/{id}', [NotificationController::class, 'destroy'])->name('api.destroy');

        // Per-org rules engine (owner-only authorization in controller)
        Route::get('/rules', [\App\Http\Controllers\Notification\NotificationRuleController::class, 'index'])->name('rules.index');
        Route::post('/rules', [\App\Http\Controllers\Notification\NotificationRuleController::class, 'store'])->name('rules.store');
        Route::delete('/rules/{rule}', [\App\Http\Controllers\Notification\NotificationRuleController::class, 'destroy'])->name('rules.destroy');

        // Delivery metrics dashboard
        Route::get('/metrics', [\App\Http\Controllers\Notification\NotificationMetricsController::class, 'index'])->name('metrics.index');
    });

    // ================================
    // USER MANAGEMENT ROUTES
    // ================================
    Route::prefix('users')->name('users.')->group(function () {
        Route::get('/', [UserManagementController::class, 'index'])
            ->name('index')
            ->middleware('can:users.view');

        Route::get('/{user}', [UserManagementController::class, 'show'])
            ->name('show')
            ->middleware('can:view-user,user');

        Route::get('/{user}/edit', [UserManagementController::class, 'edit'])
            ->name('edit')
            ->middleware('can:edit-user,user');

        Route::get('/{user}/activity', [UserManagementController::class, 'activity'])
            ->name('activity')
            ->middleware('can:viewUserActivity,user');

        Route::put('/{user}', [UserManagementController::class, 'update'])
            ->name('update')
            ->middleware('can:edit-user,user');

        Route::delete('/{user}', [UserManagementController::class, 'destroy'])
            ->name('destroy')
            ->middleware('can:delete-user,user');

        // Bulk actions
        Route::post('/bulk-action', [UserManagementController::class, 'bulkAction'])
            ->name('bulk-action')
            ->middleware('can:users.edit');
    });

    // ================================
    // TEAM MANAGEMENT ROUTES
    // ================================
    Route::prefix('teams')->name('teams.')->group(function () {
        Route::get('/', [TeamManagementController::class, 'index'])
            ->name('index')
            ->middleware('can:view-teams');

        Route::get('/create', [TeamManagementController::class, 'create'])
            ->name('create')
            ->middleware('can:create-teams');

        Route::post('/', [TeamManagementController::class, 'store'])
            ->name('store')
            ->middleware('can:create-teams');

        Route::get('/{team}', [TeamManagementController::class, 'show'])
            ->name('show')
            ->middleware('can:view-team,team');

        Route::get('/{team}/edit', [TeamManagementController::class, 'edit'])
            ->name('edit')
            ->middleware('can:manage-team,team');

        Route::put('/{team}', [TeamManagementController::class, 'update'])
            ->name('update')
            ->middleware('can:manage-team,team');

        Route::delete('/{team}', [TeamManagementController::class, 'destroy'])
            ->name('destroy')
            ->middleware('can:delete-team,team');

        // Team membership
        Route::post('/{team}/join', [TeamManagementController::class, 'join'])
            ->name('join')
            ->middleware('can:join-team,team');

        Route::delete('/{team}/leave', [TeamManagementController::class, 'leave'])
            ->name('leave')
            ->middleware('can:leave-team,team');

        Route::post('/{team}/invite', [TeamManagementController::class, 'invite'])
            ->name('invite')
            ->middleware('can:manage-team,team');

        Route::post('/{team}/remove-member', [TeamManagementController::class, 'removeMember'])
            ->name('remove-member')
            ->middleware('can:manage-team,team');

        Route::put('/{team}/members/{user}/role', [TeamManagementController::class, 'updateMemberRole'])
            ->name('update-member-role')
            ->middleware('can:manage-team,team');
    });

    // ================================
    // ROLE MANAGEMENT ROUTES
    // ================================
    Route::prefix('roles')->name('roles.')->group(function () {
        Route::get('/', [RoleManagementController::class, 'index'])
            ->name('index')
            ->middleware('can:roles.view');

        Route::get('/create', [RoleManagementController::class, 'create'])
            ->name('create')
            ->middleware('can:roles.create');

        Route::post('/', [RoleManagementController::class, 'store'])
            ->name('store')
            ->middleware('can:roles.create');

        Route::get('/{role}', [RoleManagementController::class, 'show'])
            ->name('show')
            ->middleware('can:view-role,role');

        Route::get('/{role}/edit', [RoleManagementController::class, 'edit'])
            ->name('edit')
            ->middleware('can:edit-role,role');

        Route::put('/{role}', [RoleManagementController::class, 'update'])
            ->name('update')
            ->middleware('can:edit-role,role');

        Route::delete('/{role}', [RoleManagementController::class, 'destroy'])
            ->name('destroy')
            ->middleware('can:delete-role,role');

        // Permission management
        Route::get('/{role}/permissions', [RoleManagementController::class, 'permissions'])
            ->name('permissions')
            ->middleware('can:manage-permissions,role');

        Route::post('/{role}/permissions', [RoleManagementController::class, 'updatePermissions'])
            ->name('update-permissions')
            ->middleware('can:manage-permissions,role');
    });

    // ================================
    // ORGANIZATION SETTINGS ROUTES
    // ================================
    Route::prefix('organization')->name('organization.')->group(function () {
        Route::get('/settings', [App\Http\Controllers\Organization\OrganizationController::class, 'settings'])
            ->name('settings')
            ->middleware('can:organization.manage');

        Route::put('/settings', [App\Http\Controllers\Organization\OrganizationController::class, 'updateSettings'])
            ->name('update-settings')
            ->middleware('can:organization.manage');

        Route::get('/branding', [App\Http\Controllers\Organization\OrganizationController::class, 'branding'])
            ->name('branding')
            ->middleware('can:organization.manage');

        Route::put('/branding', [App\Http\Controllers\Organization\OrganizationController::class, 'updateBranding'])
            ->name('update-branding')
            ->middleware('can:organization.manage');

        Route::get('/audit-log', [App\Http\Controllers\Organization\OrganizationController::class, 'auditLog'])
            ->name('audit-log')
            ->middleware('can:audit_log.view');

        Route::put('/security', [App\Http\Controllers\Organization\OrganizationController::class, 'updateSecurity'])
            ->name('update-security')
            ->middleware('can:organization.manage');

        // Chrome Extension
        Route::post('/extension-token', [App\Http\Controllers\Organization\ExtensionTokenController::class, 'generateToken'])
            ->name('extension-token.generate');
        Route::delete('/extension-token', [App\Http\Controllers\Organization\ExtensionTokenController::class, 'revokeTokens'])
            ->name('extension-token.revoke');
        Route::get('/extension-download', [App\Http\Controllers\Organization\ExtensionTokenController::class, 'download'])
            ->name('extension-download');

        // Organization Invitations
        Route::prefix('invitations')->name('invitations.')->group(function () {
            Route::get('/', [InvitationController::class, 'index'])->name('index');
            Route::post('/', [InvitationController::class, 'store'])->name('store');
            Route::post('/bulk', [InvitationController::class, 'bulkStore'])->name('bulk');
            Route::delete('/{invitation}', [InvitationController::class, 'destroy'])->name('destroy');
            Route::post('/{invitation}/resend', [InvitationController::class, 'resend'])->name('resend');
        });
    });

    // ================================
    // LABEL PRINTING SYSTEM ROUTES (FIXED - NO DOUBLE MIDDLEWARE)
    // ================================
    Route::prefix('printing')->name('printing.')->group(function () {

        // Dashboard
        Route::get('/', [App\Http\Controllers\Printing\DashboardController::class, 'index'])
            ->name('dashboard');

        // Printers (Full CRUD)
        Route::resource('printers', App\Http\Controllers\Printing\PrinterController::class);
        Route::get('printers/discovery', [App\Http\Controllers\Printing\PrinterController::class, 'discovery'])
            ->name('printers.discovery');

        // Templates (Full CRUD)
        Route::resource('templates', App\Http\Controllers\Printing\TemplateController::class);

        // Jobs (Read-only)
        Route::resource('jobs', App\Http\Controllers\Printing\JobController::class)
            ->only(['index', 'show']);

        // Locations (Full CRUD)
        Route::resource('locations', App\Http\Controllers\Printing\LocationController::class)
            ->names([
                'index' => 'locations.index',
                'create' => 'locations.create',
                'store' => 'locations.store',
                'show' => 'locations.show',
                'edit' => 'locations.edit',
                'update' => 'locations.update',
                'destroy' => 'locations.destroy',
            ]);

        // Bulk actions for warehouse locations
        Route::post('locations/bulk-action', [App\Http\Controllers\Printing\LocationController::class, 'bulkAction'])
            ->name('locations.bulk-action');

    });

    // ================================
    // ONBOARDING ROUTES
    // ================================
    Route::prefix('onboarding')->name('onboarding.')->group(function () {
        Route::get('/', [App\Http\Controllers\Organization\OnboardingController::class, 'index'])
            ->name('index');

        Route::post('/complete-step', [App\Http\Controllers\Organization\OnboardingController::class, 'completeStep'])
            ->name('complete-step');

        Route::post('/skip', [App\Http\Controllers\Organization\OnboardingController::class, 'skip'])
            ->name('skip');

        Route::post('/finish', [App\Http\Controllers\Organization\OnboardingController::class, 'complete'])
            ->name('complete');

        // SKU Prefix Setup Routes
        Route::post('/sku-prefix/check-availability', [App\Http\Controllers\Organization\OnboardingController::class, 'checkSkuPrefixAvailability'])
            ->name('sku-prefix.check-availability');
    });

    // ================================
    // INVENTORY MANAGEMENT ROUTES
    // ================================
    Route::prefix('inventory')->name('inventory.')->group(function () {

        // Inventory Dashboard
        Route::get('/', [InventoryController::class, 'dashboard'])->name('dashboard');

        // Product Management
        Route::prefix('products')->name('products.')->group(function () {
            Route::get('/', [ProductController::class, 'index'])->name('index');
            Route::get('/create', [ProductController::class, 'create'])->name('create');
            Route::post('/', [ProductController::class, 'store'])->name('store');
            Route::get('/{product}', [ProductController::class, 'show'])->name('show');
            Route::get('/{product}/edit', [ProductController::class, 'edit'])->name('edit');
            Route::put('/{product}', [ProductController::class, 'update'])->name('update');
            Route::delete('/{product}', [ProductController::class, 'destroy'])->name('destroy');

            // Additional product actions
            Route::post('/{product}/duplicate', [ProductController::class, 'duplicate'])->name('duplicate');
            Route::get('/{product}/analytics', [ProductController::class, 'analytics'])->name('analytics');
            Route::post('/bulk-import', [ProductController::class, 'bulkImport'])->name('bulk-import');
        });

        // Category Management
        Route::prefix('categories')->name('categories.')->group(function () {
            Route::get('/', [CategoryController::class, 'index'])->name('index');
            Route::get('/{category}', [CategoryController::class, 'show'])->name('show');
            Route::get('/{category}/products', [CategoryController::class, 'products'])->name('products');

            // Admin-only: create/edit/delete/move categories
            Route::middleware('can:admin.access')->group(function () {
                Route::get('/create', [CategoryController::class, 'create'])->name('create');
                Route::post('/', [CategoryController::class, 'store'])->name('store');
                Route::get('/{category}/edit', [CategoryController::class, 'edit'])->name('edit');
                Route::put('/{category}', [CategoryController::class, 'update'])->name('update');
                Route::delete('/{category}', [CategoryController::class, 'destroy'])->name('destroy');
                Route::post('/{category}/move', [CategoryController::class, 'move'])->name('move');
            });
        });

        // Supplier Management — consolidated under /admin/suppliers/*
        // (see routes/supplier.php). These redirects keep old bookmarks and
        // any in-flight links working until they're updated upstream.
        Route::prefix('suppliers')->name('suppliers.')->group(function () {
            Route::redirect('/', '/admin/suppliers', 301)->name('index');
            Route::redirect('/create', '/admin/suppliers', 301)->name('create');
            Route::redirect('/{supplier}', '/admin/suppliers/{supplier}', 301)->name('show');
            Route::redirect('/{supplier}/edit', '/admin/suppliers/{supplier}', 301)->name('edit');
            Route::redirect('/{supplier}/performance', '/admin/suppliers/{supplier}', 301)->name('performance');
        });

        // Inventory Location Management (separate from printing locations)
        Route::prefix('locations')->name('locations.')->group(function () {
            Route::get('/', [LocationController::class, 'index'])->name('index');
            Route::get('/{location}', [LocationController::class, 'show'])->name('show');
            Route::get('/{location}/analytics', [LocationController::class, 'analytics'])->name('analytics');

            // Admin-only: create/edit/delete locations
            Route::middleware('can:admin.access')->group(function () {
                Route::get('/create', [LocationController::class, 'create'])->name('create');
                Route::post('/', [LocationController::class, 'store'])->name('store');
                Route::get('/{location}/edit', [LocationController::class, 'edit'])->name('edit');
                Route::put('/{location}', [LocationController::class, 'update'])->name('update');
                Route::delete('/{location}', [LocationController::class, 'destroy'])->name('destroy');
            });
        });

        // Inventory Operations
        Route::prefix('operations')->name('operations.')->group(function () {
            // Stock Management
            Route::get('/adjust', [InventoryController::class, 'adjustForm'])->name('adjust.form');
            Route::post('/adjust', [InventoryController::class, 'adjustStock'])->name('adjust.store');

            Route::get('/transfer', [InventoryController::class, 'transferForm'])->name('transfer.form');
            Route::post('/transfer', [InventoryController::class, 'transferStock'])->name('transfer.store');

            Route::get('/reserve', [InventoryController::class, 'reserveForm'])->name('reserve.form');
            Route::post('/reserve', [InventoryController::class, 'reserveStock'])->name('reserve.store');

            // Inventory Reports
            Route::get('/levels', [InventoryController::class, 'levels'])->name('levels');
            Route::get('/movements', [InventoryController::class, 'movements'])->name('movements');
            Route::get('/valuation', [InventoryController::class, 'valuation'])->name('valuation');
            Route::get('/low-stock', [InventoryController::class, 'lowStockAlerts'])->name('low-stock');
        });

        // Inventory Analytics & Reports
        Route::prefix('reports')->name('reports.')->group(function () {
            Route::get('/analytics', [InventoryController::class, 'analytics'])->name('analytics');
            Route::get('/valuation', [InventoryController::class, 'valuationReport'])->name('valuation');
            Route::get('/movement-summary', [InventoryController::class, 'movementSummary'])->name('movement-summary');
            Route::get('/low-stock-report', [InventoryController::class, 'lowStockReport'])->name('low-stock-report');
            Route::get('/abc-analysis', [InventoryController::class, 'abcAnalysis'])->name('abc-analysis');
        });
    });

    // ================================
    // PRODUCT INTAKE QUEUE ROUTES
    // ================================
    Route::prefix('intake-queue')->name('intake-queue.')->group(function () {
        Route::get('/', [App\Http\Controllers\Product\IntakeQueueController::class, 'index'])->name('index');
        Route::get('/create', [App\Http\Controllers\Product\IntakeQueueController::class, 'create'])->name('create');
        Route::get('/{queueItem}', [App\Http\Controllers\Product\IntakeQueueController::class, 'show'])->name('show');
        Route::post('/', [App\Http\Controllers\Product\IntakeQueueController::class, 'store'])->name('store');
        Route::patch('/{queueItem}', [App\Http\Controllers\Product\IntakeQueueController::class, 'update'])->name('update');
        Route::post('/{queueItem}/complete', [App\Http\Controllers\Product\IntakeQueueController::class, 'complete'])->name('complete');
        Route::post('/bulk-approve', [App\Http\Controllers\Product\IntakeQueueController::class, 'bulkApprove'])->name('bulk-approve');
        Route::post('/{queueItem}/save-draft', [App\Http\Controllers\Product\IntakeQueueController::class, 'saveDraft'])->name('save-draft');
        Route::post('/{queueItem}/publish', [App\Http\Controllers\Product\IntakeQueueController::class, 'publish'])->name('publish');
        Route::post('/{queueItem}/retry', [App\Http\Controllers\Product\IntakeQueueController::class, 'retry'])->name('retry');
        Route::post('/{queueItem}/reresearch', [App\Http\Controllers\Product\IntakeQueueController::class, 'reresearch'])->name('reresearch');
        Route::post('/products/lookup', [App\Http\Controllers\Product\IntakeQueueController::class, 'productLookup'])->name('products-lookup');

        // Extension polling (plain JSON, no Inertia)
        Route::get('/{queueItem}/extension-status', [App\Http\Controllers\Product\IntakeQueueController::class, 'extensionStatus'])
            ->name('extension-status');

        // eBay Integration Routes
        Route::get('/{queueItem}/conditions', [App\Http\Controllers\Product\IntakeQueueController::class, 'getConditions'])
            ->name('conditions');

        Route::post('/{queueItem}/create-ebay-listing', [App\Http\Controllers\Product\IntakeQueueController::class, 'createEbayListing'])
            ->name('create-ebay-listing');

        Route::post('/bulk-create-ebay-listings', [App\Http\Controllers\Product\IntakeQueueController::class, 'bulkCreateEbayListings'])
            ->name('bulk-create-ebay-listings');

        Route::post('/{queueItem}/publish-with-ebay', [App\Http\Controllers\Product\IntakeQueueController::class, 'publishWithEbayIntegration'])
            ->name('publish-with-ebay');

        // Shipping Rate Routes
        Route::post('/{queueItem}/shipping-rates', [App\Http\Controllers\IntakeQueue\IntakeQueueShippingController::class, 'getRates'])
            ->name('shipping-rates');
        Route::get('/{queueItem}/shipping-rates/cached', [App\Http\Controllers\IntakeQueue\IntakeQueueShippingController::class, 'getCachedRates'])
            ->name('shipping-rates.cached');
        Route::post('/{queueItem}/shipping-rates/refresh', [App\Http\Controllers\IntakeQueue\IntakeQueueShippingController::class, 'refreshRates'])
            ->name('shipping-rates.refresh');
    });

    // ================================
    // MARKETPLACES ROUTES
    // ================================
    Route::prefix('marketplaces')->name('marketplaces.')->group(function () {
        // Marketplace Hub (cross-platform overview)
        Route::get('/', [App\Http\Controllers\Marketplaces\MarketplaceHubController::class, 'index'])->name('hub.index');
        Route::get('/listings', [App\Http\Controllers\Marketplaces\MarketplaceHubController::class, 'listings'])->name('hub.listings');

        // Marketplace Sync Rules (admin-only)
        Route::prefix('sync-rules')->name('sync-rules.')->middleware('can:admin.access')->group(function () {
            Route::get('/', [App\Http\Controllers\Marketplaces\MarketplaceSyncRuleController::class, 'index'])->name('index');
            Route::post('/', [App\Http\Controllers\Marketplaces\MarketplaceSyncRuleController::class, 'store'])->name('store');
            Route::put('/{rule}', [App\Http\Controllers\Marketplaces\MarketplaceSyncRuleController::class, 'update'])->name('update');
            Route::delete('/{rule}', [App\Http\Controllers\Marketplaces\MarketplaceSyncRuleController::class, 'destroy'])->name('destroy');
            Route::post('/reorder', [App\Http\Controllers\Marketplaces\MarketplaceSyncRuleController::class, 'reorder'])->name('reorder');
        });

        // Scheduled Listings
        Route::get('/scheduled-listings', [App\Http\Controllers\Marketplace\ScheduledListingController::class, 'index'])->name('scheduled-listings.index');
        Route::get('/scheduled-listings/create', [App\Http\Controllers\Marketplace\ScheduledListingController::class, 'create'])->name('scheduled-listings.create');
        Route::post('/scheduled-listings', [App\Http\Controllers\Marketplace\ScheduledListingController::class, 'store'])->name('scheduled-listings.store');
        Route::post('/scheduled-listings/{scheduledListing}/cancel', [App\Http\Controllers\Marketplace\ScheduledListingController::class, 'cancel'])->name('scheduled-listings.cancel');
        Route::delete('/scheduled-listings/{scheduledListing}', [App\Http\Controllers\Marketplace\ScheduledListingController::class, 'destroy'])->name('scheduled-listings.destroy');

        // Shopify routes are registered in routes/shopify.php

        Route::prefix('ebay')->name('ebay.')->middleware('can:admin.access')->group(function () {
            Route::get('/', [App\Http\Controllers\Marketplaces\EbayMarketplaceController::class, 'index'])->name('index');
            Route::get('/setup', [App\Http\Controllers\Marketplaces\EbayMarketplaceController::class, 'setup'])->name('setup');
            Route::post('/setup', [App\Http\Controllers\Marketplaces\EbayMarketplaceController::class, 'saveSetup'])->name('setup.save');
            Route::get('/import', [App\Http\Controllers\Marketplaces\EbayMarketplaceController::class, 'import'])->name('import');
            Route::post('/import/start', [App\Http\Controllers\Marketplaces\EbayMarketplaceController::class, 'startImport'])->name('import.start');
            Route::post('/import/csv', [App\Http\Controllers\Marketplaces\EbayMarketplaceController::class, 'startCsvImport'])->name('import.csv');
            Route::get('/import/status', [App\Http\Controllers\Marketplaces\EbayMarketplaceController::class, 'importStatus'])->name('import.status');
            Route::post('/import/cancel', [App\Http\Controllers\Marketplaces\EbayMarketplaceController::class, 'cancelImport'])->name('import.cancel');
            Route::post('/import/resume', [App\Http\Controllers\Marketplaces\EbayMarketplaceController::class, 'resumeImport'])->name('import.resume');
            Route::get('/import/debug', [App\Http\Controllers\Marketplaces\EbayMarketplaceController::class, 'importDebug'])->name('import.debug');
            Route::get('/sku-resolution', [App\Http\Controllers\Marketplaces\SkuResolutionController::class, 'index'])->name('sku-resolution');
            Route::post('/sku-resolution/{product}/resolve', [App\Http\Controllers\Marketplaces\SkuResolutionController::class, 'resolve'])->name('sku-resolution.resolve');
            Route::post('/sku-resolution/bulk-resolve', [App\Http\Controllers\Marketplaces\SkuResolutionController::class, 'bulkResolve'])->name('sku-resolution.bulk-resolve');
            Route::get('/listings', [App\Http\Controllers\Marketplaces\EbayMarketplaceController::class, 'listings'])->name('listings');
            Route::get('/logs', [App\Http\Controllers\Marketplaces\EbayMarketplaceController::class, 'logs'])->name('logs');
            Route::get('/products', [App\Http\Controllers\Marketplaces\EbayMarketplaceController::class, 'products'])->name('products');
            Route::post('/products/{product}/sync', [App\Http\Controllers\Marketplaces\EbayMarketplaceController::class, 'syncProduct'])->name('products.sync');
            Route::post('/sync/bulk', [App\Http\Controllers\Marketplaces\EbayMarketplaceController::class, 'syncBulk'])->name('sync.bulk');
            Route::post('/listings/{listing}/update', [App\Http\Controllers\Marketplaces\EbayMarketplaceController::class, 'updateListing'])->name('listings.update');
            Route::post('/listings/{listing}/retry', [App\Http\Controllers\Marketplaces\EbayMarketplaceController::class, 'retryListing'])->name('listings.retry');
            Route::post('/retry/all', [App\Http\Controllers\Marketplaces\EbayMarketplaceController::class, 'retryAll'])->name('retry.all');
            Route::post('/location', [App\Http\Controllers\Marketplaces\EbayMarketplaceController::class, 'createLocation'])->name('location.create');
            Route::get('/category-suggestions', [App\Http\Controllers\Marketplaces\EbayMarketplaceController::class, 'categorySuggestions'])->name('categories.suggest');
            Route::post('/auto-sync', [App\Http\Controllers\Marketplaces\EbayMarketplaceController::class, 'toggleAutoSync'])->name('auto-sync.toggle');
            Route::post('/credentials/{credential}/auto-sync', [App\Http\Controllers\Marketplaces\EbayMarketplaceController::class, 'toggleCredentialAutoSync'])->name('credentials.auto-sync.toggle');
            Route::post('/credentials/{credential}/register-notifications', [App\Http\Controllers\Marketplaces\EbayMarketplaceController::class, 'registerNotifications'])->name('credentials.register-notifications');
            Route::post('/sku-parser/test', [App\Http\Controllers\Marketplaces\EbayMarketplaceController::class, 'testSkuParser'])->name('sku-parser.test');
            Route::put('/sku-parser', [App\Http\Controllers\Marketplaces\EbayMarketplaceController::class, 'saveSkuParser'])->name('sku-parser.save');

            // eBay Policy Management
            Route::get('/policies', [App\Http\Controllers\Ebay\EbayPolicyController::class, 'index'])->name('policies.index');
            Route::post('/policies/sync', [App\Http\Controllers\Ebay\EbayPolicyController::class, 'sync'])->name('policies.sync');
            Route::post('/policies', [App\Http\Controllers\Ebay\EbayPolicyController::class, 'store'])->name('policies.store');
            Route::put('/policies/{policy}', [App\Http\Controllers\Ebay\EbayPolicyController::class, 'update'])->name('policies.update');
            Route::delete('/policies/{policy}', [App\Http\Controllers\Ebay\EbayPolicyController::class, 'destroy'])->name('policies.destroy');
            Route::post('/policies/{policy}/default', [App\Http\Controllers\Ebay\EbayPolicyController::class, 'setDefault'])->name('policies.default');
            Route::post('/policies/evaluate', [App\Http\Controllers\Ebay\EbayPolicyController::class, 'evaluateForProduct'])->name('policies.evaluate');

            // eBay Policy Rules
            Route::get('/policy-rules', [App\Http\Controllers\Ebay\EbayPolicyRuleController::class, 'index'])->name('policy-rules.index');
            Route::post('/policy-rules', [App\Http\Controllers\Ebay\EbayPolicyRuleController::class, 'store'])->name('policy-rules.store');
            Route::put('/policy-rules/{rule}', [App\Http\Controllers\Ebay\EbayPolicyRuleController::class, 'update'])->name('policy-rules.update');
            Route::delete('/policy-rules/{rule}', [App\Http\Controllers\Ebay\EbayPolicyRuleController::class, 'destroy'])->name('policy-rules.destroy');
            Route::post('/policy-rules/reorder', [App\Http\Controllers\Ebay\EbayPolicyRuleController::class, 'reorder'])->name('policy-rules.reorder');
            Route::post('/policy-rules/test', [App\Http\Controllers\Ebay\EbayPolicyRuleController::class, 'test'])->name('policy-rules.test');

            // eBay Promoted Listings
            Route::prefix('promoted')->name('promoted.')->group(function () {
                Route::get('/', [App\Http\Controllers\Ebay\PromotedListingsController::class, 'dashboard'])->name('dashboard');
                Route::get('/campaigns', [App\Http\Controllers\Ebay\PromotedListingsController::class, 'campaigns'])->name('campaigns');
                Route::post('/campaigns', [App\Http\Controllers\Ebay\PromotedListingsController::class, 'store'])->name('store');
                Route::get('/campaigns/{campaign}', [App\Http\Controllers\Ebay\PromotedListingsController::class, 'show'])->name('show');
                Route::post('/campaigns/{campaign}/pause', [App\Http\Controllers\Ebay\PromotedListingsController::class, 'pause'])->name('pause');
                Route::post('/campaigns/{campaign}/resume', [App\Http\Controllers\Ebay\PromotedListingsController::class, 'resume'])->name('resume');
                Route::post('/campaigns/{campaign}/end', [App\Http\Controllers\Ebay\PromotedListingsController::class, 'end'])->name('end');
                Route::post('/campaigns/{campaign}/listings', [App\Http\Controllers\Ebay\PromotedListingsController::class, 'addListing'])->name('add-listing');
                Route::post('/campaigns/{campaign}/sync-stats', [App\Http\Controllers\Ebay\PromotedListingsController::class, 'syncStats'])->name('sync-stats');
                Route::post('/listings/{listing}/remove', [App\Http\Controllers\Ebay\PromotedListingsController::class, 'removeListing'])->name('remove-listing');
            });
        });

        // Cross-Listing / Listing Cloning
        Route::prefix('cross-list')->name('cross-list.')->group(function () {
            Route::get('/preview', [App\Http\Controllers\Marketplace\CrossListingController::class, 'preview'])->name('preview');
            Route::post('/execute', [App\Http\Controllers\Marketplace\CrossListingController::class, 'execute'])->name('execute');
            Route::get('/products', [App\Http\Controllers\Marketplace\CrossListingController::class, 'products'])->name('products');
        });
    });

    // ================================
    // OPTIMIZATION DASHBOARD ROUTES
    // ================================
    Route::get('/optimization-dashboard', function () {
        return Inertia::render('Optimization/Dashboard');
    })->name('optimization.dashboard');

    Route::get('/optimization-roi', function () {
        return Inertia::render('Optimization/ROIReport');
    })->name('optimization.roi');

    // ================================
    // INTEGRATION ROUTES
    // ================================

    // Integration Credentials Management (admin-only)
    Route::prefix('settings/integrations')->name('integrations.')->middleware('can:admin.access')->group(function () {
        Route::post('/', [App\Http\Controllers\Integration\IntegrationController::class, 'store'])->name('store');
        Route::put('/{credential}', [App\Http\Controllers\Integration\IntegrationController::class, 'update'])->name('update');
        Route::delete('/{credential}', [App\Http\Controllers\Integration\IntegrationController::class, 'destroy'])->name('destroy');
        Route::post('/{credential}/verify', [App\Http\Controllers\Integration\IntegrationController::class, 'verifyCredentials'])->name('verify');
    });

    // eBay OAuth (admin-only)
    Route::prefix('integrations/ebay')->name('integrations.ebay.')->middleware('can:admin.access')->group(function () {
        Route::post('/connect', [App\Http\Controllers\Integration\IntegrationController::class, 'connectEbay'])->name('connect');
        Route::get('/{environment}/callback', [App\Http\Controllers\Ebay\EbayOAuthController::class, 'callback'])->name('callback');
        Route::post('/{environment}/disconnect', [App\Http\Controllers\Ebay\EbayOAuthController::class, 'disconnect'])->name('disconnect');
        Route::post('/{environment}/refresh', [App\Http\Controllers\Ebay\EbayOAuthController::class, 'refreshToken'])->name('refresh');
    });

    // ================================
    // ADMIN AUDIT HUB
    // ================================
    Route::prefix('admin/audit-hub')->name('admin.audit-hub.')->middleware('can:admin.access')->group(function () {
        Route::get('/', [App\Http\Controllers\Admin\AuditHubController::class, 'index'])->name('index');
        Route::get('/export', [App\Http\Controllers\Admin\AuditHubController::class, 'export'])->name('export');
        Route::get('/security', [App\Http\Controllers\Admin\AuditHubController::class, 'security'])->name('security');
        Route::get('/entity/{type}/{id}', [App\Http\Controllers\Admin\AuditHubController::class, 'entity'])
            ->where('type', '.*')
            ->name('entity');
    });

    // ================================
    // AI PROMPTS MANAGEMENT ROUTES
    // ================================
    Route::prefix('admin/ai-prompts')->name('admin.ai-prompts.')->middleware('can:ai_prompts.view')->group(function () {
        Route::get('/', [App\Http\Controllers\Admin\AiPromptController::class, 'index'])->name('index');
        Route::get('/create', [App\Http\Controllers\Admin\AiPromptController::class, 'create'])->name('create')->middleware('can:ai_prompts.create');
        Route::post('/', [App\Http\Controllers\Admin\AiPromptController::class, 'store'])->name('store')->middleware('can:ai_prompts.create');
        Route::get('/{prompt}/edit', [App\Http\Controllers\Admin\AiPromptController::class, 'edit'])->name('edit');
        Route::put('/{prompt}', [App\Http\Controllers\Admin\AiPromptController::class, 'update'])->name('update')->middleware('can:ai_prompts.edit');
        Route::delete('/{prompt}', [App\Http\Controllers\Admin\AiPromptController::class, 'destroy'])->name('destroy')->middleware('can:ai_prompts.delete');
        Route::post('/{prompt}/preview', [App\Http\Controllers\Admin\AiPromptController::class, 'preview'])->name('preview');
        Route::post('/{prompt}/test', [App\Http\Controllers\Admin\AiPromptController::class, 'test'])->name('test');
        Route::post('/{prompt}/duplicate', [App\Http\Controllers\Admin\AiPromptController::class, 'duplicate'])->name('duplicate')->middleware('can:ai_prompts.create');
        Route::get('/{prompt}/history', [App\Http\Controllers\Admin\AiPromptController::class, 'history'])->name('history');

        // Import/Export
        Route::get('/export', [App\Http\Controllers\Admin\AiPromptController::class, 'export'])->name('export');
        Route::post('/import', [App\Http\Controllers\Admin\AiPromptController::class, 'import'])->name('import')->middleware('can:ai_prompts.create');

        // Bulk Operations
        Route::post('/bulk-update', [App\Http\Controllers\Admin\AiPromptController::class, 'bulkUpdate'])->name('bulk-update')->middleware('can:ai_prompts.edit');
    });

    // ================================
    // MARKETING ROUTES
    // ================================
    Route::prefix('marketing/campaigns')->name('marketing.campaigns.')->group(function () {
        Route::get('/', [App\Http\Controllers\Marketing\EmailCampaignController::class, 'index'])->name('index');
        Route::get('/create', [App\Http\Controllers\Marketing\EmailCampaignController::class, 'create'])->name('create');
        Route::post('/', [App\Http\Controllers\Marketing\EmailCampaignController::class, 'store'])->name('store');
        Route::get('/{campaign}/edit', [App\Http\Controllers\Marketing\EmailCampaignController::class, 'edit'])->name('edit');
        Route::put('/{campaign}', [App\Http\Controllers\Marketing\EmailCampaignController::class, 'update'])->name('update');
        Route::delete('/{campaign}', [App\Http\Controllers\Marketing\EmailCampaignController::class, 'destroy'])->name('destroy');
        Route::post('/{campaign}/toggle', [App\Http\Controllers\Marketing\EmailCampaignController::class, 'toggle'])->name('toggle');
        Route::get('/{campaign}/stats', [App\Http\Controllers\Marketing\EmailCampaignController::class, 'stats'])->name('stats');
        Route::post('/{campaign}/preview', [App\Http\Controllers\Marketing\EmailCampaignController::class, 'preview'])->name('preview');
    });

    // ================================
    // ANALYTICS / FORECASTING ROUTES
    // ================================
    Route::prefix('analytics/forecasting')->name('analytics.forecasting.')->group(function () {
        Route::get('/', [App\Http\Controllers\Analytics\ForecastController::class, 'dashboard'])->name('dashboard');
        Route::get('/suggestions', [App\Http\Controllers\Analytics\ForecastController::class, 'suggestions'])->name('suggestions');
        Route::post('/generate', [App\Http\Controllers\Analytics\ForecastController::class, 'generate'])->name('generate');
        Route::post('/suggestions/{suggestion}/accept', [App\Http\Controllers\Analytics\ForecastController::class, 'accept'])->name('accept');
        Route::post('/suggestions/{suggestion}/dismiss', [App\Http\Controllers\Analytics\ForecastController::class, 'dismiss'])->name('dismiss');
    });

    // ================================
    // COMMUNICATION / MESSAGES ROUTES
    // ================================
    Route::prefix('messages')->name('messages.')->group(function () {
        Route::get('/', [App\Http\Controllers\Communication\MessageController::class, 'inbox'])->name('inbox');
        Route::get('/{message}', [App\Http\Controllers\Communication\MessageController::class, 'thread'])->name('thread');
        Route::post('/{message}/reply', [App\Http\Controllers\Communication\MessageController::class, 'reply'])->name('reply');
        Route::post('/{message}/read', [App\Http\Controllers\Communication\MessageController::class, 'markRead'])->name('read');
        Route::post('/{message}/archive', [App\Http\Controllers\Communication\MessageController::class, 'archive'])->name('archive');
    });

    // ================================
    // CURRENCY SETTINGS ROUTES
    // ================================
    Route::prefix('settings/currency')->name('settings.currency.')->group(function () {
        Route::get('/', [CurrencyController::class, 'index'])->name('index');
        Route::post('/sync', [CurrencyController::class, 'sync'])->name('sync');
        Route::post('/convert', [CurrencyController::class, 'convert'])->name('convert');
    });

    // Performance targets (manager-only access enforced in controller)
    Route::prefix('settings/targets')->name('settings.targets.')->group(function () {
        Route::get('/', [App\Http\Controllers\Settings\UserTargetController::class, 'index'])->name('index');
        Route::post('/', [App\Http\Controllers\Settings\UserTargetController::class, 'store'])->name('store');
        Route::put('/{target}', [App\Http\Controllers\Settings\UserTargetController::class, 'update'])->name('update');
        Route::delete('/{target}', [App\Http\Controllers\Settings\UserTargetController::class, 'destroy'])->name('destroy');
    });

    // Labor rates / per-user compensation (manager-only access enforced in controller)
    Route::prefix('settings/labor-rates')->name('settings.labor-rates.')->group(function () {
        Route::get('/', [App\Http\Controllers\Settings\UserCompensationController::class, 'index'])->name('index');
        Route::post('/', [App\Http\Controllers\Settings\UserCompensationController::class, 'store'])->name('store');
        Route::put('/{rate}', [App\Http\Controllers\Settings\UserCompensationController::class, 'update'])->name('update');
        Route::delete('/{rate}', [App\Http\Controllers\Settings\UserCompensationController::class, 'destroy'])->name('destroy');
    });
});

// ================================
// PUBLIC ROUTES (NO AUTHENTICATION)
// ================================

// ================================
// INVITATION ACCEPTANCE ROUTES (Public - for guests)
// ================================
Route::get('/invitations/{token}/accept', [InvitationController::class, 'showAcceptForm'])->name('invitations.accept');
Route::post('/invitations/{token}/accept', [InvitationController::class, 'accept'])->name('invitations.accept.store');

// ================================
// HEALTH CHECK ENDPOINT (Public - for load balancers/Coolify)
// ================================
Route::get('/health', function () {
    $checks = [
        'app' => true,
        'database' => false,
        'redis' => false,
    ];

    try {
        \Illuminate\Support\Facades\DB::connection()->getPdo();
        $checks['database'] = true;
    } catch (\Exception $e) {
        // Database connection failed
    }

    try {
        \Illuminate\Support\Facades\Redis::connection()->ping();
        $checks['redis'] = true;
    } catch (\Exception $e) {
        // Redis connection failed
    }

    $healthy = $checks['app'] && $checks['database'] && $checks['redis'];

    return response()->json([
        'status' => $healthy ? 'healthy' : 'unhealthy',
        'checks' => $checks,
        'timestamp' => now()->toIso8601String(),
    ], $healthy ? 200 : 503);
});

// Storefront routes
Route::get('/shop', [App\Http\Controllers\Storefront\StorefrontController::class, 'index'])->name('shop.index');
Route::get('/shop/{slug}', [App\Http\Controllers\Storefront\StorefrontController::class, 'show'])->name('shop.show');

// eBay Testing Routes (with basic auth)
Route::middleware(['auth'])->prefix('ebay-testing')->group(function () {
    Route::get('/', [App\Http\Controllers\Ebay\EbayTestingController::class, 'dashboard'])->name('ebay-testing.dashboard');
    Route::post('/test-listing', [App\Http\Controllers\Ebay\EbayTestingController::class, 'testSingleListing'])->name('ebay-testing.single');
    Route::post('/bulk-test', [App\Http\Controllers\Ebay\EbayTestingController::class, 'bulkTest'])->name('ebay-testing.bulk');
    Route::get('/item/{id}', [App\Http\Controllers\Ebay\EbayTestingController::class, 'showItem'])->name('ebay-testing.show-item');
    Route::patch('/item/{id}/status', [App\Http\Controllers\Ebay\EbayTestingController::class, 'updateItemStatus'])->name('ebay-testing.update-status');
    Route::post('/item/{id}/retry', [App\Http\Controllers\Ebay\EbayTestingController::class, 'retryFailedItem'])->name('ebay-testing.retry');
});

// Product identifier detection (public utility)
Route::post('/detect-identifier-type', function (Request $request) {
    $request->validate(['value' => 'required|string']);

    $type = \App\Models\Catalog\ProductIdentifier::detectIdentifierType($request->value);

    return response()->json([
        'success' => true,
        'detected_type' => $type,
        'formatted_type' => \App\Models\Catalog\ProductIdentifier::getValidIdentifierTypes()[$type] ?? 'Unknown',
    ]);
});

// ================================
// API ROUTES (for AJAX/Vue.js calls)
// ================================
Route::middleware(['auth:sanctum'])->prefix('api/v1')->name('api.v1.')->group(function () {

    // Product API Routes
    Route::prefix('products')->name('products.')->group(function () {
        Route::get('/', [ProductController::class, 'apiIndex'])->name('index');
        Route::post('/', [ProductController::class, 'apiStore'])->name('store');
        Route::get('/{product}', [ProductController::class, 'apiShow'])->name('show');
        Route::put('/{product}', [ProductController::class, 'apiUpdate'])->name('update');
        Route::delete('/{product}', [ProductController::class, 'apiDestroy'])->name('destroy');
        Route::post('/{product}/duplicate', [ProductController::class, 'apiDuplicate'])->name('duplicate');
        Route::post('/bulk-import', [ProductController::class, 'apiBulkImport'])->name('bulk-import');
    });

    // Category API Routes
    Route::prefix('categories')->name('categories.')->group(function () {
        Route::get('/', [CategoryController::class, 'apiIndex'])->name('index');
        Route::post('/', [CategoryController::class, 'apiStore'])->name('store');
        Route::get('/{category}', [CategoryController::class, 'apiShow'])->name('show');
        Route::put('/{category}', [CategoryController::class, 'apiUpdate'])->name('update');
        Route::delete('/{category}', [CategoryController::class, 'apiDestroy'])->name('destroy');
        Route::post('/{category}/move', [CategoryController::class, 'apiMove'])->name('move');
    });

    // Supplier API Routes — removed when Inventory\SupplierController was
    // deprecated. No frontend or mobile consumers found. If supplier API
    // access is needed, add endpoints to SupplierManagementController and
    // gate via the same view-suppliers ability used on the web routes.

    // Inventory API Routes
    Route::prefix('inventory')->name('inventory.')->group(function () {
        Route::get('/levels', [InventoryController::class, 'apiLevels'])->name('levels');
        Route::get('/movements', [InventoryController::class, 'apiMovements'])->name('movements');
        Route::get('/analytics', [InventoryController::class, 'apiAnalytics'])->name('analytics');
        Route::get('/low-stock-alerts', [InventoryController::class, 'apiLowStockAlerts'])->name('low-stock-alerts');

        Route::post('/adjust-stock', [InventoryController::class, 'apiAdjustStock'])->name('adjust-stock');
        Route::post('/transfer-stock', [InventoryController::class, 'apiTransferStock'])->name('transfer-stock');
        Route::post('/reserve-stock', [InventoryController::class, 'apiReserveStock'])->name('reserve-stock');
        Route::post('/release-stock', [InventoryController::class, 'apiReleaseStock'])->name('release-stock');
    });

    // Location API Routes (Inventory)
    Route::prefix('locations')->name('locations.')->group(function () {
        Route::get('/', [LocationController::class, 'apiIndex'])->name('index');
        Route::post('/', [LocationController::class, 'apiStore'])->name('store');
        Route::get('/{location}', [LocationController::class, 'apiShow'])->name('show');
        Route::put('/{location}', [LocationController::class, 'apiUpdate'])->name('update');
        Route::delete('/{location}', [LocationController::class, 'apiDestroy'])->name('destroy');
    });

    // Mobile Product Intake Routes
    Route::prefix('mobile/intake')->name('mobile.intake.')->group(function () {
        Route::post('/', [App\Http\Controllers\Product\IntakeQueueController::class, 'mobileStore'])->name('store');
        Route::post('/scan-barcode', [App\Http\Controllers\Product\IntakeQueueController::class, 'scanBarcode'])->name('scan-barcode');
        Route::get('/queue-stats', [App\Http\Controllers\Product\IntakeQueueController::class, 'getMobileQueueStats'])->name('queue-stats');
        Route::get('/identifier-types', [App\Http\Controllers\Product\IntakeQueueController::class, 'getIdentifierTypes'])->name('identifier-types');
        Route::get('/my-captures', [App\Http\Controllers\Product\IntakeQueueController::class, 'getMyCaptures'])->name('my-captures');
        Route::post('/{queueItem}/photos', [App\Http\Controllers\Product\IntakeQueueController::class, 'addPhotos'])->name('add-photos');
        Route::patch('/{queueItem}', [App\Http\Controllers\Product\IntakeQueueController::class, 'mobileUpdate'])->name('update');
    });

    // Monitoring endpoints
    Route::prefix('monitoring')->name('monitoring.')->group(function () {
        Route::get('/rate-limit-status', [App\Http\Controllers\Api\MonitoringController::class, 'rateLimitStatus'])->name('rate-limit-status');
        Route::get('/quality-metrics', [App\Http\Controllers\Api\MonitoringController::class, 'qualityMetrics'])->name('quality-metrics');
        Route::get('/recent-activity', [App\Http\Controllers\Api\MonitoringController::class, 'recentActivity'])->name('recent-activity');
        Route::get('/recent-errors', [App\Http\Controllers\Api\MonitoringController::class, 'recentErrors'])->name('recent-errors');
        Route::get('/source-performance', [App\Http\Controllers\Api\MonitoringController::class, 'sourcePerformance'])->name('source-performance');

        // ROI and business intelligence
        Route::get('/roi-summary', function () {
            $bi = app(\App\Services\BusinessIntelligenceService::class);
            $summary = $bi->generateExecutiveSummary(now()->subDays(30), now());

            return response()->json($summary);
        })->name('roi-summary');

        Route::get('/daily-snapshot', function () {
            $bi = app(\App\Services\BusinessIntelligenceService::class);
            $date = request()->input('date', now()->format('Y-m-d'));
            $snapshot = $bi->getDailySnapshot(\Carbon\Carbon::parse($date));

            return response()->json($snapshot);
        })->name('daily-snapshot');

        Route::get('/weekly-comparison', function () {
            $bi = app(\App\Services\BusinessIntelligenceService::class);
            $comparison = $bi->getWeeklyComparison();

            return response()->json($comparison);
        })->name('weekly-comparison');
    });

});

// ================================
// ADMIN - SUPPLIER SUBMISSIONS (Web Routes)
// ================================
Route::middleware(['auth:sanctum'])->prefix('admin/supplier-submissions')->name('admin.supplier-submissions.')->group(function () {
    Route::get('/', [\App\Http\Controllers\Admin\SupplierSubmissionController::class, 'index'])->name('index');
    Route::get('/{submission}', [\App\Http\Controllers\Admin\SupplierSubmissionController::class, 'review'])->name('review');
    Route::post('/{submission}/approve', [\App\Http\Controllers\Admin\SupplierSubmissionController::class, 'approve'])->name('approve');
    Route::post('/{submission}/reject', [\App\Http\Controllers\Admin\SupplierSubmissionController::class, 'reject'])->name('reject');
});

// ================================
// HEALTH CHECK ENDPOINT (Public)
// ================================
Route::get('/api/health', function () {
    try {
        $dbUp = \Illuminate\Support\Facades\DB::connection()->getPdo() !== null;
        $redisUp = \Illuminate\Support\Facades\Redis::connection()->ping();

        $isHealthy = $dbUp && $redisUp;

        $rateLimiterStatus = 'unknown';
        try {
            $rateLimiter = app(\App\Services\EbayRateLimiter::class);
            $status = $rateLimiter->getStatus();
            $rateLimiterStatus = $status['daily']['percentage_used'] < 90 ? 'healthy' : 'warning';
        } catch (\Exception $e) {
            $rateLimiterStatus = 'unavailable';
        }

        return response()->json([
            'status' => $isHealthy ? 'healthy' : 'degraded',
            'timestamp' => now()->toIso8601String(),
            'services' => [
                'database' => $dbUp ? 'up' : 'down',
                'redis' => $redisUp ? 'up' : 'down',
                'rate_limiter' => $rateLimiterStatus,
            ],
        ], $isHealthy ? 200 : 503);
    } catch (\Exception $e) {
        return response()->json([
            'status' => 'error',
            'message' => 'System error',
            'timestamp' => now()->toIso8601String(),
        ], 503);
    }
});

require __DIR__.'/inventory-bulk-routes.php';
require __DIR__.'/inventory.php';
require __DIR__.'/inventory-movements.php';
require __DIR__.'/supplier.php';
require __DIR__.'/fulfillment.php';
