<?php

/**
 * Inventory Movement Routes
 *
 * Add this to your routes/web.php inside the authenticated middleware group:
 *
 * require __DIR__.'/inventory-movements.php';
 */

use App\Http\Controllers\Inventory\InventoryMovementController;

/*
|--------------------------------------------------------------------------
| Inventory Movement Web Routes
|--------------------------------------------------------------------------
*/

Route::prefix('inventory/movements')->name('inventory.movements.')->group(function () {
    Route::get('/', [InventoryMovementController::class, 'index'])->name('index');
    Route::get('/create', [InventoryMovementController::class, 'create'])->name('create');
    Route::post('/', [InventoryMovementController::class, 'store'])->name('store');
    Route::get('/{movement}', [InventoryMovementController::class, 'show'])->name('show');
});

/*
|--------------------------------------------------------------------------
| Inventory Movement API Routes
|--------------------------------------------------------------------------
*/

Route::prefix('api/v1/inventory')->name('api.inventory.')->middleware(['auth:sanctum'])->group(function () {
    Route::get('/stock-levels/{product}', [InventoryMovementController::class, 'productStockLevels'])->name('stock-levels');
    Route::get('/locations', [InventoryMovementController::class, 'locations'])->name('locations');
    Route::get('/reason-codes', [InventoryMovementController::class, 'reasonCodes'])->name('reason-codes');
});
