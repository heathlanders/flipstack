<?php

use App\Http\Controllers\Api\V1\InventoryAlertController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Inventory Alert API Routes
|--------------------------------------------------------------------------
|
| These routes handle inventory stock alerts and notifications.
| All routes require authentication via Sanctum.
|
*/

Route::prefix('api/v1/inventory/alerts')
    ->middleware(['auth:sanctum'])
    ->name('api.v1.inventory.alerts.')
    ->group(function () {

        // List all current alerts
        // GET /api/v1/inventory/alerts
        // Query params: type (out_of_stock|low_stock|overstocked), location_id, exclude_dismissed, per_page
        Route::get('/', [InventoryAlertController::class, 'index'])
            ->name('index');

        // Get alert summary/counts by type
        // GET /api/v1/inventory/alerts/summary
        Route::get('/summary', [InventoryAlertController::class, 'summary'])
            ->name('summary');

        // Get products with alerts (grouped by product)
        // GET /api/v1/inventory/alerts/products
        Route::get('/products', [InventoryAlertController::class, 'products'])
            ->name('products');

        // Get alert configuration
        // GET /api/v1/inventory/alerts/config
        Route::get('/config', [InventoryAlertController::class, 'config'])
            ->name('config');

        // Update alert configuration for an inventory level
        // PUT /api/v1/inventory/alerts/config
        // Body: { inventory_level_id, reorder_point?, reorder_quantity?, max_stock_level? }
        Route::put('/config', [InventoryAlertController::class, 'updateConfig'])
            ->name('config.update');

        // Dismiss a single alert
        // POST /api/v1/inventory/alerts/{id}/dismiss
        Route::post('/{id}/dismiss', [InventoryAlertController::class, 'dismiss'])
            ->name('dismiss')
            ->where('id', '[a-f0-9\-]{36}'); // UUID format

        // Dismiss all alerts of a specific type
        // POST /api/v1/inventory/alerts/dismiss-all
        // Body: { type: 'low_stock'|'out_of_stock'|'overstocked'|'all' }
        Route::post('/dismiss-all', [InventoryAlertController::class, 'dismissAll'])
            ->name('dismiss.all');

        // Restore a dismissed alert
        // POST /api/v1/inventory/alerts/{id}/restore
        Route::post('/{id}/restore', [InventoryAlertController::class, 'restore'])
            ->name('restore')
            ->where('id', '[a-f0-9\-]{36}'); // UUID format

        // Clear all dismissed alerts
        // DELETE /api/v1/inventory/alerts/dismissed
        Route::delete('/dismissed', [InventoryAlertController::class, 'clearDismissed'])
            ->name('dismissed.clear');
    });
