<?php

use App\Http\Controllers\Admin\SupplierManagementController;
use App\Http\Controllers\Supplier\PerformanceController;
use App\Http\Controllers\Supplier\SettlementController;
use App\Http\Controllers\Supplier\SupplierDashboardController;
use App\Http\Controllers\Supplier\SupplierDocumentController;
use App\Http\Controllers\Supplier\SupplierNotificationController;
use App\Http\Controllers\Supplier\SupplierOnboardingController;
use App\Http\Controllers\Supplier\SupplierProductController;
use App\Http\Controllers\Supplier\SupplierSettingsController;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

/*
|--------------------------------------------------------------------------
| Supplier Workflow Routes
|--------------------------------------------------------------------------
|
| These routes handle all supplier-related functionality including
| the supplier portal, performance tracking, settlements, and
| admin management features.
|
*/

// ============================================
// Supplier Status Pages (no `supplier` middleware to avoid redirect loops)
// ============================================
Route::middleware(['auth:sanctum'])->prefix('supplier')->name('supplier.')->group(function () {
    Route::get('/suspended', function () {
        return Inertia::render('Supplier/Suspended', [
            'supplier' => request()->user()?->supplier,
            'support_email' => config('mail.from.address'),
        ]);
    })->name('suspended');

    Route::get('/inactive', function () {
        return Inertia::render('Supplier/Inactive', [
            'supplier' => request()->user()?->supplier,
            'support_email' => config('mail.from.address'),
        ]);
    })->name('inactive');
});

// ============================================
// Supplier Portal Routes (authenticated suppliers)
// ============================================
Route::middleware(['auth:sanctum', 'supplier'])->prefix('supplier')->name('supplier.')->group(function () {

    // Dashboard
    Route::get('/dashboard', [SupplierDashboardController::class, 'index'])->name('dashboard');
    Route::get('/overview', [SupplierDashboardController::class, 'overview'])->name('overview');
    Route::get('/earnings', [SupplierDashboardController::class, 'earnings'])->name('earnings');
    Route::get('/tier-progress', [SupplierDashboardController::class, 'tierProgress'])->name('tier-progress');
    Route::get('/quick-stats', [SupplierDashboardController::class, 'quickStats'])->name('quick-stats');
    Route::get('/activity', [SupplierDashboardController::class, 'activityFeed'])->name('activity');

    // Commission Preview
    Route::post('/commission/preview', [SupplierDashboardController::class, 'commissionPreview'])->name('commission.preview');
    Route::post('/commission/tier-comparison', [SupplierDashboardController::class, 'tierComparison'])->name('commission.tier-comparison');

    // Notifications
    Route::prefix('notifications')->name('notifications.')->group(function () {
        Route::get('/', [SupplierNotificationController::class, 'index'])->name('index');
        Route::get('/recent', [SupplierNotificationController::class, 'recent'])->name('recent');
        Route::get('/unread-count', [SupplierNotificationController::class, 'unreadCount'])->name('unread-count');
        Route::get('/preferences', [SupplierNotificationController::class, 'preferences'])->name('preferences');
        Route::post('/preferences', [SupplierNotificationController::class, 'updatePreferences'])->name('preferences.update');
        Route::post('/read-all', [SupplierNotificationController::class, 'markAllAsRead'])->name('read-all');
        Route::post('/{notification}/read', [SupplierNotificationController::class, 'markAsRead'])->name('read');
        Route::delete('/{notification}', [SupplierNotificationController::class, 'destroy'])->name('destroy');
    });

    // Onboarding
    Route::prefix('onboarding')->name('onboarding.')->group(function () {
        Route::get('/', [SupplierOnboardingController::class, 'index'])->name('index');
        Route::get('/status', [SupplierOnboardingController::class, 'status'])->name('status');
        Route::post('/step1', [SupplierOnboardingController::class, 'completeStep1'])->name('step1');
        Route::post('/step2', [SupplierOnboardingController::class, 'completeStep2'])->name('step2');
        Route::post('/step3', [SupplierOnboardingController::class, 'completeStep3'])->name('step3');
        Route::post('/step4', [SupplierOnboardingController::class, 'completeStep4'])->name('step4');
        Route::post('/step5', [SupplierOnboardingController::class, 'completeStep5'])->name('step5');
        Route::post('/skip-docs', [SupplierOnboardingController::class, 'skipDocumentation'])->name('skip-docs');
    });

    // Performance
    Route::prefix('performance')->name('performance.')->group(function () {
        Route::get('/', [PerformanceController::class, 'index'])->name('index');
        Route::get('/metrics/{metric}', [PerformanceController::class, 'show'])->name('show');
        Route::get('/trends', [PerformanceController::class, 'trends'])->name('trends');
        Route::get('/score-breakdown', [PerformanceController::class, 'scoreBreakdown'])->name('score-breakdown');
        Route::get('/tier-requirements', [PerformanceController::class, 'tierRequirements'])->name('tier-requirements');
        Route::get('/recommendations', [PerformanceController::class, 'recommendations'])->name('recommendations');
        Route::get('/benchmark', [PerformanceController::class, 'benchmark'])->name('benchmark');
    });

    // Settings
    Route::get('/settings', [SupplierSettingsController::class, 'index'])->name('settings');
    Route::put('/settings', [SupplierSettingsController::class, 'update'])->name('settings.update');

    // Supplier Documents
    Route::prefix('documents')->name('documents.')->group(function () {
        Route::get('/', [SupplierDocumentController::class, 'index'])->name('index');
        Route::post('/', [SupplierDocumentController::class, 'store'])->name('store');
        Route::get('/{type}', [SupplierDocumentController::class, 'download'])->name('download');
    });

    // Supplier Product Submissions
    Route::prefix('products')->name('products.')->group(function () {
        Route::get('/submit', [SupplierProductController::class, 'submit'])->name('submit');
        Route::post('/submit', [SupplierProductController::class, 'store'])->name('store');
        Route::get('/submissions', [SupplierProductController::class, 'submissions'])->name('submissions');
    });

    // Settlements
    Route::prefix('settlements')->name('settlements.')->group(function () {
        Route::get('/', [SettlementController::class, 'index'])->name('index');
        Route::get('/pending', [SettlementController::class, 'pending'])->name('pending');
        Route::get('/paid', [SettlementController::class, 'paid'])->name('paid');
        Route::get('/statistics', [SettlementController::class, 'statistics'])->name('statistics');
        Route::get('/preview', [SettlementController::class, 'preview'])->name('preview');
        Route::get('/export', [SettlementController::class, 'exportCsv'])->name('export');
        Route::get('/tax-documents', [SettlementController::class, 'taxDocuments'])->name('tax-documents');

        Route::get('/{settlement}', [SettlementController::class, 'show'])->name('show');
        Route::get('/{settlement}/items', [SettlementController::class, 'items'])->name('items');
        Route::get('/{settlement}/pdf', [SettlementController::class, 'downloadPdf'])->name('pdf');
        Route::post('/{settlement}/dispute', [SettlementController::class, 'dispute'])->name('dispute');
    });
});

// ============================================
// Admin Routes (organization staff/admins)
// ============================================
Route::middleware(['auth:sanctum', 'can:view-suppliers'])->prefix('admin/suppliers')->name('admin.suppliers.')->group(function () {

    // Supplier Management
    Route::get('/', [SupplierManagementController::class, 'index'])->name('index');
    Route::post('/', [SupplierManagementController::class, 'store'])->name('store');
    Route::get('/export', [SupplierManagementController::class, 'export'])->name('export');
    Route::post('/bulk-update', [SupplierManagementController::class, 'bulkUpdate'])->name('bulk-update');

    // Individual Supplier
    Route::get('/{supplier}', [SupplierManagementController::class, 'show'])->name('show');
    Route::put('/{supplier}', [SupplierManagementController::class, 'update'])->name('update');
    Route::get('/{supplier}/activity', [SupplierManagementController::class, 'activityLog'])->name('activity');

    // Tab data endpoints (paginated JSON)
    Route::get('/{supplier}/products', [SupplierManagementController::class, 'products'])->name('products');
    Route::get('/{supplier}/purchase-orders', [SupplierManagementController::class, 'purchaseOrders'])->name('purchase-orders');
    Route::get('/{supplier}/inventory-batches', [SupplierManagementController::class, 'inventoryBatches'])->name('inventory-batches');
    Route::get('/{supplier}/orders', [SupplierManagementController::class, 'ordersList'])->name('orders');
    Route::get('/{supplier}/submissions', [SupplierManagementController::class, 'submissions'])->name('submissions');

    // Notes
    Route::post('/{supplier}/notes', [SupplierManagementController::class, 'storeNote'])->name('notes.store');
    Route::delete('/{supplier}/notes/{note}', [SupplierManagementController::class, 'destroyNote'])->name('notes.destroy');

    // Supplier Updates
    Route::patch('/{supplier}/status', [SupplierManagementController::class, 'updateStatus'])->name('update-status');
    Route::patch('/{supplier}/tier', [SupplierManagementController::class, 'updateTier'])->name('update-tier');
    Route::patch('/{supplier}/commission', [SupplierManagementController::class, 'updateCommission'])->name('update-commission');
    Route::patch('/{supplier}/settlement-schedule', [SupplierManagementController::class, 'updateSettlementSchedule'])->name('update-settlement-schedule');

    // Supplier Actions
    Route::post('/{supplier}/generate-settlement', [SupplierManagementController::class, 'generateSettlement'])->name('generate-settlement');
    Route::post('/{supplier}/recalculate-performance', [SupplierManagementController::class, 'recalculatePerformance'])->name('recalculate-performance');

    // Onboarding documents
    Route::get('/{supplier}/documents/{type}', [SupplierManagementController::class, 'downloadDocument'])->name('documents.download');
    Route::patch('/{supplier}/documents/{type}/verify', [SupplierManagementController::class, 'verifyDocument'])->name('documents.verify');
    Route::patch('/{supplier}/documents/{type}/reject', [SupplierManagementController::class, 'rejectDocument'])->name('documents.reject');
});

// ============================================
// API Routes (for external integrations)
// ============================================
Route::middleware(['auth:sanctum'])->prefix('api/v1/supplier')->name('api.supplier.')->group(function () {

    // Supplier Profile
    Route::get('/profile', [SupplierDashboardController::class, 'overview'])->name('profile');
    Route::get('/earnings', [SupplierDashboardController::class, 'earnings'])->name('earnings');

    // Performance
    Route::get('/performance', [PerformanceController::class, 'index'])->name('performance');
    Route::get('/performance/trends', [PerformanceController::class, 'trends'])->name('performance.trends');

    // Settlements
    Route::get('/settlements', [SettlementController::class, 'index'])->name('settlements');
    Route::get('/settlements/pending', [SettlementController::class, 'pending'])->name('settlements.pending');
    Route::get('/settlements/{settlement}', [SettlementController::class, 'show'])->name('settlements.show');
});
