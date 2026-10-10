<?php

use App\Http\Controllers\Inventory\ProductController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Inventory API Routes
|--------------------------------------------------------------------------
|
| These routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "api" middleware group with the "api/inventory" prefix.
|
*/

Route::middleware(['auth:sanctum'])->group(function () {

    // Filter options for the product filter component
    Route::get('/filter-options', [ProductController::class, 'getFilterOptionsApi'])
        ->name('api.inventory.filter-options');

    // Quick stats for the filter bar (stock counts by status)
    Route::get('/quick-stats', [ProductController::class, 'getQuickStats'])
        ->name('api.inventory.quick-stats');

    // Search suggestions for autocomplete
    Route::get('/search-suggestions', [ProductController::class, 'searchSuggestions'])
        ->name('api.inventory.search-suggestions');

    // Filter presets (saved searches)
    Route::prefix('filter-presets')->name('api.inventory.filter-presets.')->group(function () {
        Route::get('/', [ProductController::class, 'getFilterPresets'])
            ->name('index');

        Route::post('/', [ProductController::class, 'storeFilterPreset'])
            ->name('store');

        Route::delete('/{preset}', [ProductController::class, 'deleteFilterPreset'])
            ->name('destroy');

        Route::put('/{preset}/default', [ProductController::class, 'setDefaultPreset'])
            ->name('set-default');
    });

});
