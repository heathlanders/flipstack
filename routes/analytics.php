<?php

use App\Http\Controllers\Inventory\InventoryAnalyticsController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Analytics Routes
|--------------------------------------------------------------------------
|
| These routes handle all inventory analytics functionality.
| Add these to your routes/web.php file within the authenticated middleware group.
|
*/

Route::middleware(['auth:sanctum', 'can:analytics.view'])->prefix('inventory/analytics')->name('inventory.analytics.')->group(function () {

    // Dashboard
    Route::get('/', [InventoryAnalyticsController::class, 'dashboard'])->name('dashboard');

    // ABC Analysis
    Route::get('/abc', [InventoryAnalyticsController::class, 'abcAnalysis'])->name('abc');

    // Turnover Report
    Route::get('/turnover', [InventoryAnalyticsController::class, 'turnoverReport'])->name('turnover');

    // Valuation Report
    Route::get('/valuation', [InventoryAnalyticsController::class, 'valuationReport'])->name('valuation');

    // Export
    Route::get('/export', [InventoryAnalyticsController::class, 'export'])->name('export');

    // Clear Cache
    Route::post('/clear-cache', [InventoryAnalyticsController::class, 'clearCache'])->name('clear-cache');
});

/*
|--------------------------------------------------------------------------
| Analytics API Routes
|--------------------------------------------------------------------------
|
| These routes provide JSON API endpoints for analytics data.
| Add these to your routes/api.php or include in web.php with api prefix.
|
*/

Route::middleware(['auth:sanctum', 'can:analytics.view'])->prefix('api/analytics')->name('api.analytics.')->group(function () {

    // KPIs
    Route::get('/kpis', [InventoryAnalyticsController::class, 'getKPIs'])->name('kpis');

    // Inventory Value
    Route::get('/inventory-value', [InventoryAnalyticsController::class, 'getInventoryValue'])->name('inventory-value');

    // Stock Health
    Route::get('/stock-health', [InventoryAnalyticsController::class, 'getStockHealth'])->name('stock-health');

    // Movement Trends
    Route::get('/movement-trends', [InventoryAnalyticsController::class, 'getMovementTrends'])->name('movement-trends');

    // Top Performers
    Route::get('/top-performers', [InventoryAnalyticsController::class, 'getTopPerformers'])->name('top-performers');

    // Location Performance
    Route::get('/location-performance', [InventoryAnalyticsController::class, 'getLocationPerformance'])->name('location-performance');

    // ABC Analysis
    Route::get('/abc', [InventoryAnalyticsController::class, 'getABCAnalysis'])->name('abc');

    // Turnover Analysis
    Route::get('/turnover', [InventoryAnalyticsController::class, 'getTurnoverAnalysis'])->name('turnover');

    // Turnover Trend
    Route::get('/turnover-trend', [InventoryAnalyticsController::class, 'getTurnoverTrend'])->name('turnover-trend');
});

/*
|--------------------------------------------------------------------------
| Profit & Loss Routes
|--------------------------------------------------------------------------
*/

Route::middleware(['auth:sanctum', 'can:analytics.view'])->prefix('analytics')->name('analytics.')->group(function () {
    Route::get('/profit-loss', [\App\Http\Controllers\Analytics\ProfitLossController::class, 'dashboard'])->name('profit-loss.dashboard');

    // Analytics Hub
    Route::prefix('hub')->name('hub.')->group(function () {
        Route::get('/', [\App\Http\Controllers\Analytics\AnalyticsHubController::class, 'index'])->name('index');
        Route::get('/live', [\App\Http\Controllers\Analytics\AnalyticsHubController::class, 'liveOps'])->name('live');
        Route::get('/executive', [\App\Http\Controllers\Analytics\AnalyticsHubController::class, 'executive'])->name('executive');
        Route::get('/fulfillment', [\App\Http\Controllers\Analytics\AnalyticsHubController::class, 'fulfillment'])->name('fulfillment');
        Route::get('/workforce', [\App\Http\Controllers\Analytics\AnalyticsHubController::class, 'workforce'])->name('workforce');
        Route::get('/users/{user}', [\App\Http\Controllers\Analytics\AnalyticsHubController::class, 'userDetail'])->name('users.show');
        Route::get('/compensation', [\App\Http\Controllers\Analytics\AnalyticsHubController::class, 'compensation'])->name('compensation');
        Route::get('/procurement', [\App\Http\Controllers\Analytics\AnalyticsHubController::class, 'procurement'])->name('procurement');
        Route::get('/marketplace', [\App\Http\Controllers\Analytics\AnalyticsHubController::class, 'marketplace'])->name('marketplace');
        Route::get('/customers', [\App\Http\Controllers\Analytics\AnalyticsHubController::class, 'customer'])->name('customers');
        Route::get('/communication', [\App\Http\Controllers\Analytics\AnalyticsHubController::class, 'communication'])->name('communication');
        Route::get('/marketing', [\App\Http\Controllers\Analytics\AnalyticsHubController::class, 'marketing'])->name('marketing');
        Route::get('/pricing', [\App\Http\Controllers\Analytics\AnalyticsHubController::class, 'pricing'])->name('pricing');
    });

    Route::prefix('reports')->name('reports.')->group(function () {
        Route::get('/', [\App\Http\Controllers\Analytics\ReportController::class, 'index'])->name('index');
        Route::get('/export', [\App\Http\Controllers\Analytics\ReportController::class, 'export'])->name('export');
        Route::post('/saved', [\App\Http\Controllers\Analytics\ReportController::class, 'storeSaved'])->name('saved.store');
        Route::delete('/saved/{saved}', [\App\Http\Controllers\Analytics\ReportController::class, 'destroySaved'])->name('saved.destroy');
        Route::post('/scheduled', [\App\Http\Controllers\Analytics\ReportController::class, 'storeScheduled'])->name('scheduled.store');
        Route::put('/scheduled/{scheduled}', [\App\Http\Controllers\Analytics\ReportController::class, 'updateScheduled'])->name('scheduled.update');
        Route::delete('/scheduled/{scheduled}', [\App\Http\Controllers\Analytics\ReportController::class, 'destroyScheduled'])->name('scheduled.destroy');
    });
});
